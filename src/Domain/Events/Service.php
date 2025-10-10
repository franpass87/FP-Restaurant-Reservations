<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Payments\StripeService;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use FP\Resv\Domain\Reservations\Service as ReservationsService;
use InvalidArgumentException;
use RuntimeException;
use wpdb;
use function __;
use function absint;
use function apply_filters;
use function array_fill;
use function array_map;
use function current_time;
use function do_action;
use function filter_var;
use function get_page_by_path;
use function get_permalink;
use function implode;
use function json_decode;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sprintf;
use function strtolower;
use function substr;
use function trim;
use const FILTER_VALIDATE_EMAIL;

final class Service
{
    /** @var array<int, string> */
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_EVENTS;

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly ReservationsService $reservations,
        private readonly ReservationsRepository $reservationsRepository,
        private readonly CustomersRepository $customers,
        private readonly StripeService $stripe
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUpcoming(int $limit = 10): array
    {
        $table = $this->eventsTable();
        $now   = current_time('mysql');

        $sql  = $this->wpdb->prepare("SELECT * FROM {$table} WHERE status = %s AND end_at >= %s ORDER BY start_at ASC LIMIT %d", 'published', $now, $limit);
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->formatEvent($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEvent(int $eventId): ?array
    {
        $table = $this->eventsTable();
        $sql   = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $eventId);
        $row   = $this->wpdb->get_row($sql, ARRAY_A);

        if (!is_array($row)) {
            return null;
        }

        return $this->formatEvent($row);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function bookEvent(int $eventId, array $payload): array
    {
        $event = $this->getEvent($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(__('Evento non trovato.', 'fp-restaurant-reservations'));
        }

        if ($event['status'] !== 'published') {
            throw new RuntimeException(__('L\'evento non è prenotabile in questo momento.', 'fp-restaurant-reservations'));
        }

        $sanitized = $this->sanitizeBookingPayload($payload);
        $this->assertBookingPayload($sanitized);

        $remaining = $event['remaining_capacity'];
        if ($remaining !== null && $remaining < $sanitized['quantity']) {
            throw new RuntimeException(__('Posti esauriti per questo evento.', 'fp-restaurant-reservations'));
        }

        $reservationPayload = [
            'date'        => substr($event['start_at'], 0, 10),
            'time'        => substr($event['start_at'], 11, 5),
            'party'       => $sanitized['quantity'],
            'first_name'  => $sanitized['first_name'],
            'last_name'   => $sanitized['last_name'],
            'email'       => $sanitized['email'],
            'phone'       => $sanitized['phone'],
            'notes'       => $this->buildReservationNotes($sanitized['notes'], $event),
            'allergies'   => '',
            'language'    => $sanitized['language'],
            'locale'      => $sanitized['locale'],
            'location'    => $sanitized['location'],
            'currency'    => $event['currency'] !== '' ? $event['currency'] : $sanitized['currency'],
            'utm_source'  => $sanitized['utm_source'],
            'utm_medium'  => $sanitized['utm_medium'],
            'utm_campaign'=> $sanitized['utm_campaign'],
        ];

        $reservation = $this->reservations->create($reservationPayload);
        $reservationId = (int) ($reservation['id'] ?? 0);

        $customer = $this->customers->findByEmail($sanitized['email']);
        $customerId = $customer?->id ?? null;

        $paymentRequired = $this->stripe->isEnabled() && (bool) apply_filters('fp_resv_event_payment_required', true, $event, $sanitized);
        $paymentUrl      = null;

        $tickets = [];
        for ($i = 0; $i < $sanitized['quantity']; $i++) {
            $ticket = $this->createTicket(
                $event,
                $reservationId,
                $customerId,
                $sanitized,
                $paymentRequired,
                $i + 1
            );
            $tickets[] = $ticket->toArray();
        }

        if ($paymentRequired) {
            $paymentUrl = apply_filters('fp_resv_event_checkout_url', null, $event, $reservation, $tickets, $sanitized);
        }

        $value = $event['price'] !== null ? (float) $event['price'] * $sanitized['quantity'] : 0.0;

        $dataLayer = array_merge(
            DataLayer::basePayload(),
            [
                'event'    => $paymentRequired ? 'fp_resv_event_add_to_cart' : 'fp_resv_event_purchase',
                'ga4'      => [
                    'event'     => $paymentRequired ? 'add_to_cart' : 'purchase',
                    'ecommerce' => [
                        'currency' => $event['currency'] !== '' ? $event['currency'] : $sanitized['currency'],
                        'value'    => $value,
                        'items'    => [
                            [
                                'item_id'   => 'event-' . $event['id'],
                                'item_name' => $event['title'],
                                'quantity'  => $sanitized['quantity'],
                                'price'     => $event['price'] !== null ? (float) $event['price'] : 0.0,
                            ],
                        ],
                    ],
                ],
            ]
        );

        do_action('fp_resv_event_booked', $event, $reservation, $tickets, $sanitized);

        return [
            'event'       => $event,
            'reservation' => $reservation,
            'tickets'     => $tickets,
            'payment'     => [
                'required'    => $paymentRequired,
                'status'      => $paymentRequired ? 'pending' : 'not_required',
                'checkout_url'=> $paymentUrl,
                'publishable_key' => $this->stripe->publishableKey(),
                'mode'           => $this->stripe->mode(),
            ],
            'data_layer'  => $dataLayer,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTickets(int $eventId): array
    {
        $ticketsTable      = $this->ticketsTable();
        $reservationsTable = $this->reservationsRepository->tableName();
        $customersTable    = $this->customers->tableName();

        $statusPlaceholder = implode(', ', array_fill(0, count(self::ACTIVE_STATUSES), '%s'));
        $sql               = $this->wpdb->prepare(
            "SELECT t.*, c.email AS email, CONCAT_WS(' ', c.first_name, c.last_name) AS holder_name " .
            "FROM {$ticketsTable} t " .
            "LEFT JOIN {$reservationsTable} r ON r.id = t.reservation_id " .
            "LEFT JOIN {$customersTable} c ON c.id = r.customer_id " .
            "WHERE t.event_id = %d AND t.status IN ({$statusPlaceholder}) ORDER BY t.created_at ASC",
            array_merge([$eventId], self::ACTIVE_STATUSES)
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => Ticket::fromRow($row)->toArray(), $rows);
    }

    public function exportTicketsCsv(int $eventId): string
    {
        $tickets = $this->listTickets($eventId);
        if ($tickets === []) {
            return "id;status;email;holder;qr_code\n";
        }

        $lines = ["id;status;email;holder;qr_code"];
        foreach ($tickets as $ticket) {
            $lines[] = sprintf(
                '%d;%s;%s;%s;%s',
                $ticket['id'],
                $ticket['status'],
                $ticket['email'] ?? '',
                $ticket['holder'] ?? '',
                $ticket['qr_code']
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function formatEvent(array $row): array
    {
        $eventId = (int) ($row['id'] ?? 0);
        $capacity = isset($row['capacity']) ? (int) $row['capacity'] : null;
        $sold     = $this->countActiveTickets($eventId);

        $settings = [];
        if (!empty($row['settings_json'])) {
            $decoded = json_decode((string) $row['settings_json'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        $startAt = (string) ($row['start_at'] ?? '');

        return [
            'id'                  => $eventId,
            'title'               => (string) ($row['title'] ?? ''),
            'slug'                => (string) ($row['slug'] ?? ''),
            'start_at'            => $startAt,
            'end_at'              => (string) ($row['end_at'] ?? ''),
            'capacity'            => $capacity,
            'remaining_capacity'  => $capacity !== null ? max(0, $capacity - $sold) : null,
            'price'               => isset($row['price']) ? (float) $row['price'] : null,
            'currency'            => (string) ($row['currency'] ?? ''),
            'status'              => (string) ($row['status'] ?? 'draft'),
            'lang'                => (string) ($row['lang'] ?? ''),
            'settings'            => $settings,
            'permalink'           => $this->resolvePermalink((string) ($row['slug'] ?? '')),
        ];
    }

    private function buildReservationNotes(string $notes, array $event): string
    {
        $notes = trim($notes);
        $eventLabel = sprintf(__('Evento: %s (%s)', 'fp-restaurant-reservations'), $event['title'], $event['start_at']);

        return $notes === '' ? $eventLabel : $notes . "\n" . $eventLabel;
    }

    private function resolvePermalink(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        $post = get_page_by_path($slug, \OBJECT, 'fp_event');
        if ($post !== null) {
            $link = get_permalink($post);
            if (is_string($link)) {
                return $link;
            }
        }

        return '';
    }

    private function createTicket(array $event, int $reservationId, ?int $customerId, array $payload, bool $paymentRequired, int $index): Ticket
    {
        $status  = $paymentRequired ? 'pending_payment' : 'confirmed';
        $price   = $event['price'] !== null ? (float) $event['price'] : null;
        $currency = $event['currency'] !== '' ? $event['currency'] : $payload['currency'];

        $data = [
            'event_id'       => $event['id'],
            'reservation_id' => $reservationId,
            'customer_id'    => $customerId,
            'category'       => $payload['category'],
            'price'          => $price,
            'currency'       => $currency,
            'status'         => $status,
            'qr_code_text'   => '',
            'created_at'     => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ];

        $inserted = $this->wpdb->insert($this->ticketsTable(), $data);
        if ($inserted === false) {
            throw new RuntimeException($this->wpdb->last_error ?: __('Impossibile creare il biglietto.', 'fp-restaurant-reservations'));
        }

        $ticketId = (int) $this->wpdb->insert_id;
        $seed     = sprintf('event:%d|ticket:%d|email:%s|n:%d', $event['id'], $ticketId, strtolower($payload['email']), $index);
        $qr       = QR::encode($seed);

        $this->wpdb->update(
            $this->ticketsTable(),
            ['qr_code_text' => $qr],
            ['id' => $ticketId]
        );

        $ticketRow = [
            'id'             => $ticketId,
            'event_id'       => $event['id'],
            'reservation_id' => $reservationId,
            'customer_id'    => $customerId,
            'status'         => $status,
            'category'       => $payload['category'],
            'price'          => $price,
            'currency'       => $currency,
            'qr_code_text'   => $qr,
            'created_at'     => current_time('mysql'),
            'email'          => $payload['email'],
            'holder_name'    => trim($payload['first_name'] . ' ' . $payload['last_name']),
        ];

        do_action('fp_resv_event_ticket_created', $ticketRow, $event, $payload);

        return Ticket::fromRow($ticketRow);
    }

    private function countActiveTickets(int $eventId): int
    {
        if ($eventId <= 0) {
            return 0;
        }

        $statuses     = implode(', ', array_fill(0, count(self::ACTIVE_STATUSES), '%s'));
        $placeholders = array_merge([$eventId], self::ACTIVE_STATUSES);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->ticketsTable()} WHERE event_id = %d AND status IN ({$statuses})",
            $placeholders
        );

        $count = $this->wpdb->get_var($sql);

        return $count !== null ? (int) $count : 0;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function sanitizeBookingPayload(array $payload): array
    {
        $defaults = [
            'first_name'   => '',
            'last_name'    => '',
            'email'        => '',
            'phone'        => '',
            'notes'        => '',
            'quantity'     => 1,
            'category'     => '',
            'language'     => 'it',
            'locale'       => 'it_IT',
            'location'     => 'event',
            'currency'     => 'EUR',
            'utm_source'   => '',
            'utm_medium'   => '',
            'utm_campaign' => '',
        ];

        $payload = array_merge($defaults, $payload);

        $payload['first_name'] = sanitize_text_field((string) $payload['first_name']);
        $payload['last_name']  = sanitize_text_field((string) $payload['last_name']);
        $payload['email']      = sanitize_email((string) $payload['email']);
        $payload['phone']      = sanitize_text_field((string) $payload['phone']);
        $payload['notes']      = sanitize_textarea_field((string) $payload['notes']);
        $payload['quantity']   = max(1, absint($payload['quantity']));
        $payload['category']   = sanitize_text_field((string) $payload['category']);
        $payload['language']   = sanitize_text_field((string) $payload['language']);
        $payload['locale']     = sanitize_text_field((string) $payload['locale']);
        $payload['location']   = sanitize_text_field((string) $payload['location']);
        $payload['currency']   = sanitize_text_field((string) $payload['currency']);
        $payload['utm_source'] = sanitize_text_field((string) $payload['utm_source']);
        $payload['utm_medium'] = sanitize_text_field((string) $payload['utm_medium']);
        $payload['utm_campaign'] = sanitize_text_field((string) $payload['utm_campaign']);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertBookingPayload(array $payload): void
    {
        if ($payload['first_name'] === '' || $payload['last_name'] === '') {
            throw new RuntimeException(__('Nome e cognome sono obbligatori per i biglietti evento.', 'fp-restaurant-reservations'));
        }

        if ($payload['email'] === '' || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(__('Inserisci un indirizzo email valido per l\'evento.', 'fp-restaurant-reservations'));
        }
    }

    private function eventsTable(): string
    {
        return $this->wpdb->prefix . 'fp_events';
    }

    private function ticketsTable(): string
    {
        return $this->wpdb->prefix . 'fp_tickets';
    }
}
