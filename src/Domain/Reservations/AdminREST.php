<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Tables\LayoutService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function array_keys;
use function array_filter;
use function array_map;
use function array_values;
use function add_action;
use function do_action;
use function current_time;
use function current_user_can;
use function gmdate;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_split;
use function rest_ensure_response;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function strtolower;
use function trim;
use function substr;
use function sprintf;
use function wp_timezone;

final class AdminREST
{
    public function __construct(
        private readonly Repository $reservations,
        private readonly Service $service,
        private readonly ?GoogleCalendarService $calendar = null,
        private readonly ?LayoutService $layout = null
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'fp-resv/v1',
            '/agenda',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleAgenda'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'date' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'range' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/agenda/reservations',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleCreateReservation'],
                'permission_callback' => [$this, 'checkPermissions'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/agenda/reservations/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'handleUpdateReservation'],
                'permission_callback' => [$this, 'checkPermissions'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/agenda/reservations/(?P<id>\d+)/move',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleMoveReservation'],
                'permission_callback' => [$this, 'checkPermissions'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/reservations/arrivals',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleArrivals'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'range' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'room' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'status' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public function handleArrivals(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $range = strtolower((string) $request->get_param('range'));
        if (!in_array($range, ['today', 'week'], true)) {
            $range = 'today';
        }

        $timezone = wp_timezone();
        $start    = new DateTimeImmutable('today', $timezone);
        $end      = $range === 'week' ? $start->add(new DateInterval('P6D')) : $start;

        $filters = [];

        $room = $request->get_param('room');
        if ($room !== null && $room !== '') {
            $filters['room'] = (string) $room;
        }

        $status = $request->get_param('status');
        if ($status !== null && $status !== '') {
            $filters['status'] = sanitize_text_field((string) $status);
        }

        $rows = $this->reservations->findArrivals(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $filters
        );

        $reservations = array_map([$this, 'mapArrivalReservation'], $rows);

        return rest_ensure_response([
            'range'        => [
                'mode'  => $range,
                'start' => $start->format('Y-m-d'),
                'end'   => $end->format('Y-m-d'),
            ],
            'reservations' => $reservations,
        ]);
    }

    public function handleAgenda(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $dateParam = $request->get_param('date');
        $date = $this->sanitizeDate($dateParam);
        if ($date === null) {
            $date = gmdate('Y-m-d');
        }

        $range = $request->get_param('range');
        $rangeMode = is_string($range) ? strtolower($range) : 'day';
        if (!in_array($rangeMode, ['day', 'week'], true)) {
            $rangeMode = 'day';
        }

        $start = DateTimeImmutable::createFromFormat('Y-m-d', $date) ?: new DateTimeImmutable($date);
        $end   = $rangeMode === 'week' ? $start->add(new DateInterval('P6D')) : $start;

        $rows = $this->reservations->findAgendaRange($start->format('Y-m-d'), $end->format('Y-m-d'));
        $reservations = array_map([$this, 'mapAgendaReservation'], $rows);

        $overview = $this->layout?->getOverview() ?? ['rooms' => [], 'groups' => []];
        $days      = $this->buildAgendaDays($reservations);
        $tables    = $this->flattenAgendaTables($overview);

        return rest_ensure_response([
            'range'        => [
                'mode'  => $rangeMode,
                'start' => $start->format('Y-m-d'),
                'end'   => $end->format('Y-m-d'),
            ],
            'reservations' => $reservations,
            'days'         => array_values($days),
            'tables'       => $tables,
            'rooms'        => $overview['rooms'] ?? [],
            'groups'       => $overview['groups'] ?? [],
            'meta'         => [
                'generated_at' => gmdate('c'),
            ],
        ]);
    }

    public function handleCreateReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = $this->extractReservationPayload($request);

        try {
            $result = $this->service->create($payload);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return new WP_Error(
                'fp_resv_admin_reservation_invalid',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (Throwable $exception) {
            return new WP_Error(
                'fp_resv_admin_reservation_error',
                __('Impossibile creare la prenotazione.', 'fp-restaurant-reservations'),
                ['status' => 500, 'details' => $exception->getMessage()]
            );
        }

        $entry = $this->reservations->findAgendaEntry((int) $result['id']);

        return rest_ensure_response([
            'reservation' => $entry !== null ? $this->mapAgendaReservation($entry) : null,
            'result'      => $result,
        ]);
    }

    public function handleUpdateReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = absint((string) $request->get_param('id'));
        if ($id <= 0) {
            return new WP_Error('fp_resv_invalid_reservation_id', __('ID prenotazione non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $updates = [];

        if ($request->offsetExists('status')) {
            $status = strtolower(sanitize_text_field((string) $request->get_param('status')));
            if ($status !== '' && !in_array($status, Service::ALLOWED_STATUSES, true)) {
                return new WP_Error('fp_resv_invalid_status', __('Stato prenotazione non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
            }
            $updates['status'] = $status === '' ? null : $status;
        }

        if ($request->offsetExists('party')) {
            $party = max(1, absint((string) $request->get_param('party')));
            $updates['party'] = $party;
        }

        if ($request->offsetExists('notes')) {
            $updates['notes'] = sanitize_textarea_field((string) $request->get_param('notes'));
        }

        if ($request->offsetExists('allergies')) {
            $updates['allergies'] = sanitize_textarea_field((string) $request->get_param('allergies'));
        }

        if ($request->offsetExists('visited')) {
            $visited = (string) $request->get_param('visited');
            if (in_array(strtolower($visited), ['1', 'true', 'yes', 'on'], true)) {
                $updates['visited_at'] = current_time('mysql');
                $updates['status']     = $updates['status'] ?? 'visited';
            } else {
                $updates['visited_at'] = null;
            }
        }

        if ($request->offsetExists('table_id')) {
            $tableId = absint((string) $request->get_param('table_id'));
            $updates['table_id'] = $tableId > 0 ? $tableId : null;
        }

        if ($request->offsetExists('room_id')) {
            $roomId = absint((string) $request->get_param('room_id'));
            $updates['room_id'] = $roomId > 0 ? $roomId : null;
        }

        if ($updates === []) {
            return new WP_Error('fp_resv_no_updates', __('Nessuna modifica fornita.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $original = $this->reservations->findAgendaEntry($id);
        if ($original === null) {
            return new WP_Error('fp_resv_reservation_not_found', __('Prenotazione non trovata.', 'fp-restaurant-reservations'), ['status' => 404]);
        }

        if ($this->calendar !== null && $this->calendar->shouldBlockOnBusy()) {
            $previousStatus = (string) ($original['status'] ?? '');
            $targetStatus   = $updates['status'] ?? $previousStatus;

            if ($targetStatus === 'confirmed' && $previousStatus !== 'confirmed') {
                $date = (string) ($original['date'] ?? '');
                $time = substr((string) ($original['time'] ?? ''), 0, 5);

                if ($date !== '' && $time !== '' && $this->calendar->isWindowBusy($date, $time, $this->calendar->eventId($id))) {
                    return new WP_Error(
                        'fp_resv_google_busy',
                        __('Lo slot selezionato risulta occupato su Google Calendar. Scegli un altro orario.', 'fp-restaurant-reservations'),
                        ['status' => 409]
                    );
                }
            }
        }

        try {
            $this->reservations->update($id, $updates);
        } catch (RuntimeException $exception) {
            return new WP_Error('fp_resv_update_failed', $exception->getMessage(), ['status' => 500]);
        }

        $entry = $this->reservations->findAgendaEntry($id);

        if (is_array($entry)) {
            $previousStatus = (string) ($original['status'] ?? '');
            $currentStatus  = (string) ($entry['status'] ?? $previousStatus);

            $statusChanged = $currentStatus !== $previousStatus;
            $visitedToggled = !empty($updates['visited_at']) && empty($original['visited_at']) && !empty($entry['visited_at']);

            if ($statusChanged || $visitedToggled) {
                do_action('fp_resv_reservation_status_changed', $id, $previousStatus, $currentStatus, $entry);
            }
        }

        do_action('fp_resv_reservation_updated', $id, $entry, $updates, $original);

        return rest_ensure_response([
            'reservation' => $entry !== null ? $this->mapAgendaReservation($entry) : null,
            'updated'     => array_keys($updates),
        ]);
    }

    public function handleMoveReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = absint((string) $request->get_param('id'));
        if ($id <= 0) {
            return new WP_Error('fp_resv_invalid_reservation_id', __('ID prenotazione non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $dateParam = $this->sanitizeDate($request->get_param('date'));
        if ($dateParam === null) {
            return new WP_Error('fp_resv_invalid_date', __('Data non valida.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $time = sanitize_text_field((string) $request->get_param('time'));
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return new WP_Error('fp_resv_invalid_time', __('Orario non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $updates = [
            'date'     => $dateParam,
            'time'     => $time . ':00',
            'table_id' => null,
            'room_id'  => null,
        ];

        $tableId = absint((string) $request->get_param('table_id'));
        if ($tableId > 0) {
            $updates['table_id'] = $tableId;
        }

        $roomId = absint((string) $request->get_param('room_id'));
        if ($roomId > 0) {
            $updates['room_id'] = $roomId;
        }

        if ($this->calendar !== null && $this->calendar->shouldBlockOnBusy()) {
            $guardTime = substr($updates['time'], 0, 5);
            if ($this->calendar->isWindowBusy($dateParam, $guardTime, $this->calendar->eventId($id))) {
                return new WP_Error(
                    'fp_resv_google_busy',
                    __('Lo slot selezionato risulta occupato su Google Calendar. Scegli un altro orario.', 'fp-restaurant-reservations'),
                    ['status' => 409]
                );
            }
        }

        try {
            $this->reservations->update($id, $updates);
        } catch (RuntimeException $exception) {
            return new WP_Error('fp_resv_move_failed', $exception->getMessage(), ['status' => 500]);
        }

        $entry = $this->reservations->findAgendaEntry($id);

        do_action('fp_resv_reservation_moved', $id, $entry, $updates);

        return rest_ensure_response([
            'reservation' => $entry !== null ? $this->mapAgendaReservation($entry) : null,
            'moved'       => true,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $reservations
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildAgendaDays(array $reservations): array
    {
        $days = [];

        foreach ($reservations as $reservation) {
            $date = (string) ($reservation['date'] ?? '');
            if ($date === '') {
                continue;
            }

            if (!isset($days[$date])) {
                $days[$date] = [
                    'date'          => $date,
                    'reservations'  => [],
                ];
            }

            $days[$date]['reservations'][] = $this->normalizeAgendaDayReservation($reservation);
        }

        foreach ($days as &$day) {
            usort($day['reservations'], static function (array $left, array $right): int {
                $leftKey = ($left['time'] ?? '') . '|' . ($left['table_id'] ?? 0);
                $rightKey = ($right['time'] ?? '') . '|' . ($right['table_id'] ?? 0);

                return $leftKey <=> $rightKey;
            });
        }

        return $days;
    }

    /**
     * @param array<string, mixed> $reservation
     *
     * @return array<string, mixed>
     */
    private function normalizeAgendaDayReservation(array $reservation): array
    {
        $customer = isset($reservation['customer']) && is_array($reservation['customer'])
            ? $reservation['customer']
            : [];

        return [
            'id'        => (int) $reservation['id'],
            'status'    => (string) ($reservation['status'] ?? 'pending'),
            'date'      => (string) ($reservation['date'] ?? ''),
            'time'      => (string) ($reservation['time'] ?? ''),
            'party'     => (int) ($reservation['party'] ?? 0),
            'room_id'   => isset($reservation['room_id']) ? (int) $reservation['room_id'] : null,
            'table_id'  => isset($reservation['table_id']) ? (int) $reservation['table_id'] : null,
            'notes'     => (string) ($reservation['notes'] ?? ''),
            'allergies' => (string) ($reservation['allergies'] ?? ''),
            'customer'  => $this->summarizeAgendaCustomer($customer),
            'meta'      => [
                'customer' => $customer,
                'visited_at' => $reservation['visited_at'] ?? null,
                'calendar_event_id' => $reservation['calendar_event_id'] ?? null,
                'calendar_sync_status' => $reservation['calendar_sync_status'] ?? null,
                'calendar_synced_at' => $reservation['calendar_synced_at'] ?? null,
                'calendar_last_error' => $reservation['calendar_last_error'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    private function summarizeAgendaCustomer(array $customer): array
    {
        $firstName = isset($customer['first_name']) ? (string) $customer['first_name'] : '';
        $lastName  = isset($customer['last_name']) ? (string) $customer['last_name'] : '';
        $email     = isset($customer['email']) ? (string) $customer['email'] : '';
        $phone     = isset($customer['phone']) ? (string) $customer['phone'] : '';

        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') {
            $name = $email !== '' ? $email : $phone;
        }

        return [
            'id'    => isset($customer['id']) ? (int) $customer['id'] : null,
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
            'language' => isset($customer['language']) ? (string) $customer['language'] : '',
        ];
    }

    /**
     * @param array<string, mixed> $overview
     *
     * @return array<int, array<string, mixed>>
     */
    private function flattenAgendaTables(array $overview): array
    {
        if (!isset($overview['rooms']) || !is_array($overview['rooms'])) {
            return [];
        }

        $tables = [];

        foreach ($overview['rooms'] as $room) {
            if (!is_array($room)) {
                continue;
            }

            $roomId   = isset($room['id']) ? (int) $room['id'] : null;
            $roomName = isset($room['name']) ? (string) $room['name'] : '';

            if (!isset($room['tables']) || !is_array($room['tables'])) {
                continue;
            }

            foreach ($room['tables'] as $table) {
                if (!is_array($table)) {
                    continue;
                }

                $tables[] = [
                    'id'        => isset($table['id']) ? (int) $table['id'] : null,
                    'room_id'   => $roomId,
                    'label'     => isset($table['code']) ? (string) $table['code'] : '',
                    'seats_min' => isset($table['seats_min']) ? $table['seats_min'] : null,
                    'seats_std' => isset($table['seats_std']) ? $table['seats_std'] : null,
                    'seats_max' => isset($table['seats_max']) ? $table['seats_max'] : null,
                    'status'    => isset($table['status']) ? (string) $table['status'] : '',
                    'active'    => !empty($table['active']),
                    'room_name' => $roomName,
                    'order_index' => isset($table['order_index']) ? (int) $table['order_index'] : 0,
                ];
            }
        }

        usort($tables, static function (array $left, array $right): int {
            $leftKey = sprintf('%05d-%05d', (int) ($left['room_id'] ?? 0), (int) ($left['order_index'] ?? 0));
            $rightKey = sprintf('%05d-%05d', (int) ($right['room_id'] ?? 0), (int) ($right['order_index'] ?? 0));

            return $leftKey <=> $rightKey;
        });

        return $tables;
    }

    private function checkPermissions(): bool
    {
        return current_user_can('manage_options');
    }

    private function sanitizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(sanitize_text_field($value));
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapAgendaReservation(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'status'     => (string) ($row['status'] ?? 'pending'),
            'date'       => (string) $row['date'],
            'time'       => substr((string) $row['time'], 0, 5),
            'party'      => (int) $row['party'],
            'notes'      => (string) ($row['notes'] ?? ''),
            'allergies'  => (string) ($row['allergies'] ?? ''),
            'room_id'    => isset($row['room_id']) ? (int) $row['room_id'] : null,
            'table_id'   => isset($row['table_id']) ? (int) $row['table_id'] : null,
            'customer'   => [
                'id'         => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name'  => (string) ($row['last_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'language'   => (string) ($row['customer_lang'] ?? ''),
            ],
            'visited_at' => $row['visited_at'] ?? null,
            'calendar_event_id'    => $row['calendar_event_id'] ?? null,
            'calendar_sync_status' => $row['calendar_sync_status'] ?? null,
            'calendar_synced_at'   => $row['calendar_synced_at'] ?? null,
            'calendar_last_error'  => $row['calendar_last_error'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractReservationPayload(WP_REST_Request $request): array
    {
        $payload = [
            'date'       => $request->get_param('date') ?? '',
            'time'       => $request->get_param('time') ?? '',
            'party'      => $request->get_param('party') ?? 0,
            'first_name' => $request->get_param('first_name') ?? '',
            'last_name'  => $request->get_param('last_name') ?? '',
            'email'      => $request->get_param('email') ?? '',
            'phone'      => $request->get_param('phone') ?? '',
            'notes'      => $request->get_param('notes') ?? '',
            'allergies'  => $request->get_param('allergies') ?? '',
            'language'   => $request->get_param('language') ?? '',
            'locale'     => $request->get_param('locale') ?? '',
            'location'   => $request->get_param('location') ?? '',
            'currency'   => $request->get_param('currency') ?? '',
            'utm_source' => $request->get_param('utm_source') ?? '',
            'utm_medium' => $request->get_param('utm_medium') ?? '',
            'utm_campaign' => $request->get_param('utm_campaign') ?? '',
            'status'     => $request->get_param('status') ?? null,
            'room_id'    => $request->get_param('room_id') ?? null,
            'table_id'   => $request->get_param('table_id') ?? null,
            'value'      => $request->get_param('value') ?? null,
        ];

        if ($request->offsetExists('visited') && in_array(strtolower((string) $request->get_param('visited')), ['1', 'true', 'yes', 'on'], true)) {
            $payload['status'] = 'visited';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapArrivalReservation(array $row): array
    {
        $time = isset($row['time']) ? substr((string) $row['time'], 0, 5) : '';

        $guestParts = [];
        if (!empty($row['first_name'])) {
            $guestParts[] = (string) $row['first_name'];
        }
        if (!empty($row['last_name'])) {
            $guestParts[] = (string) $row['last_name'];
        }

        $guest = trim(implode(' ', $guestParts));
        if ($guest === '') {
            $guest = (string) ($row['email'] ?? '');
        }

        $tableParts = [];
        if (!empty($row['table_code'])) {
            $tableParts[] = (string) $row['table_code'];
        }
        if (!empty($row['room_name'])) {
            $tableParts[] = (string) $row['room_name'];
        }

        $tableLabel = $tableParts !== [] ? implode(' · ', $tableParts) : '';

        $allergies = [];
        if (!empty($row['allergies']) && is_string($row['allergies'])) {
            $chunks = preg_split('/[\r\n,;]+/', (string) $row['allergies']) ?: [];
            $allergies = array_values(array_filter(array_map(static function ($value) {
                $value = trim((string) $value);

                return $value !== '' ? $value : null;
            }, $chunks)));
        }

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'date'         => (string) ($row['date'] ?? ''),
            'time'         => $time,
            'party'        => (int) ($row['party'] ?? 0),
            'table_label'  => $tableLabel,
            'guest'        => $guest,
            'notes'        => isset($row['notes']) ? (string) $row['notes'] : '',
            'allergies'    => $allergies,
            'status'       => (string) ($row['status'] ?? ''),
            'language'     => (string) ($row['customer_lang'] ?? ($row['lang'] ?? '')),
            'phone'        => isset($row['phone']) ? (string) $row['phone'] : '',
        ];
    }
}
