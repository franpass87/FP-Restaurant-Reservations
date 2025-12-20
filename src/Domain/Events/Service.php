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
use function apply_filters;
use function array_map;
use function do_action;
use function substr;

final class Service
{
    /** @var array<int, string> */
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_EVENTS;

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly ReservationsService $reservations,
        private readonly ReservationsRepository $reservationsRepository,
        private readonly CustomersRepository $customers,
        private readonly StripeService $stripe,
        private readonly EventFormatter $eventFormatter,
        private readonly TicketCreator $ticketCreator,
        private readonly TicketCounter $ticketCounter,
        private readonly TicketLister $ticketLister,
        private readonly TicketCsvExporter $ticketCsvExporter,
        private readonly BookingPayloadSanitizer $payloadSanitizer,
        private readonly BookingPayloadValidator $payloadValidator,
        private readonly EventNotesBuilder $notesBuilder
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

        return array_map(fn (array $row): array => $this->eventFormatter->format($row), $rows);
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

        return $this->eventFormatter->format($row);
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

        $sanitized = $this->payloadSanitizer->sanitize($payload);
        $this->payloadValidator->assert($sanitized);

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
            'notes'       => $this->notesBuilder->build($sanitized['notes'], $event),
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
            $ticket = $this->ticketCreator->create(
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
        return $this->ticketLister->list($eventId);
    }

    public function exportTicketsCsv(int $eventId): string
    {
        return $this->ticketCsvExporter->export($eventId);
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
