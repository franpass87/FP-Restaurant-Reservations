<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Core\Roles;
use FP\Resv\Core\ErrorLogger;
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
use function register_rest_route;
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
        error_log('[FP Resv AdminREST] ✅ register() chiamato - Aggiunta action rest_api_init');
        add_action('rest_api_init', [$this, 'registerRoutes']);
        error_log('[FP Resv AdminREST] ✅ Action rest_api_init aggiunta con successo');
        
        // Fallback: se siamo già in rest_api_init, registra subito
        if (did_action('rest_api_init')) {
            error_log('[FP Resv AdminREST] ⚠️ rest_api_init già eseguito, registro subito');
            $this->registerRoutes();
        }
    }

    public function registerRoutes(): void
    {
        error_log('[FP Resv AdminREST] ========================================');
        error_log('[FP Resv AdminREST] 🚀 registerRoutes() CHIAMATO!');
        error_log('[FP Resv AdminREST] Timestamp: ' . date('Y-m-d H:i:s'));
        error_log('[FP Resv AdminREST] ========================================');
        
        try {
            $result = register_rest_route(
            'fp-resv/v1',
            '/agenda',
            [
                'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'handleAgendaV2'],
                    'permission_callback' => '__return_true', // TEMPORANEO: Bypassa permissions
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
            
            error_log('[FP Resv AdminREST] Endpoint /agenda registrato: ' . ($result ? 'SUCCESS' : 'FAILED'));
        } catch (\Throwable $e) {
            error_log('[FP Resv AdminREST] ❌ ERRORE registrazione /agenda: ' . $e->getMessage());
        }

        // ENDPOINT TEMPORANEO DI DEBUG - Bypassa tutto
        register_rest_route(
            'fp-resv/v1',
            '/agenda-debug',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => function() {
                    return new WP_REST_Response([
                        'success' => true,
                        'message' => 'Endpoint debug funziona!',
                        'timestamp' => time(),
                        'reservations_count' => 'TODO'
                    ], 200);
                },
                'permission_callback' => '__return_true', // Pubblico per test
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
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'handleUpdateReservation'],
                    'permission_callback' => [$this, 'checkPermissions'],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'handleDeleteReservation'],
                    'permission_callback' => [$this, 'checkPermissions'],
                ],
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

        register_rest_route(
            'fp-resv/v1',
            '/agenda/stats',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleStats'],
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
            '/agenda/overview',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleOverview'],
                'permission_callback' => [$this, 'checkPermissions'],
            ]
        );
    }

    public function handleArrivals(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
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

            $responseData = [
                'range'        => [
                    'mode'  => $range,
                    'start' => $start->format('Y-m-d'),
                    'end'   => $end->format('Y-m-d'),
                ],
                'reservations' => $reservations,
            ];
            
            $response = rest_ensure_response($responseData);
            
            return $response;
        } catch (Throwable $e) {
            error_log('[FP Resv Arrivals] Errore critico: ' . $e->getMessage());
            return new WP_Error(
                'fp_resv_arrivals_error',
                sprintf(__('Errore nel caricamento degli arrivi: %s', 'fp-restaurant-reservations'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Endpoint dedicato per statistiche dettagliate
     */
    public function handleStats(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $dateParam = $request->get_param('date');
            $date = $this->sanitizeDate($dateParam);
            if ($date === null) {
                $date = gmdate('Y-m-d');
            }

            $range = $request->get_param('range');
            $rangeMode = is_string($range) ? strtolower($range) : 'day';
            if (!in_array($rangeMode, ['day', 'week', 'month'], true)) {
                $rangeMode = 'day';
            }

            $timezone = wp_timezone();
            $start = DateTimeImmutable::createFromFormat('Y-m-d', $date, $timezone);
            if ($start === false) {
                $start = new DateTimeImmutable($date, $timezone);
            }
            
            // Calcola range
            if ($rangeMode === 'week') {
                $dayOfWeek = (int)$start->format('N');
                $start = $start->modify('-' . ($dayOfWeek - 1) . ' days');
                $end = $start->add(new DateInterval('P6D'));
            } elseif ($rangeMode === 'month') {
                $start = $start->modify('first day of this month');
                $end = $start->modify('last day of this month');
            } else {
                $end = $start;
            }

            // Recupera prenotazioni
            $rows = $this->reservations->findAgendaRange($start->format('Y-m-d'), $end->format('Y-m-d'));
            
            if (!is_array($rows)) {
                $rows = [];
            }
            
            $reservations = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                try {
                    $reservations[] = $this->mapAgendaReservation($row);
                } catch (Throwable $e) {
                    error_log('[FP Resv Stats] Errore mapping prenotazione: ' . $e->getMessage());
                    continue;
                }
            }

            // Calcola statistiche dettagliate
            $stats = $this->calculateDetailedStats($reservations, $rangeMode);

            $responseData = [
                'range' => [
                    'mode'  => $rangeMode,
                    'start' => $start->format('Y-m-d'),
                    'end'   => $end->format('Y-m-d'),
                ],
                'stats' => $stats,
            ];
            
            $response = rest_ensure_response($responseData);
            
            return $response;
        } catch (Throwable $e) {
            error_log('[FP Resv Stats] Errore critico: ' . $e->getMessage());
            return new WP_Error(
                'fp_resv_stats_error',
                sprintf(__('Errore nel caricamento delle statistiche: %s', 'fp-restaurant-reservations'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Endpoint per overview dashboard con metriche aggregate
     */
    public function handleOverview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $timezone = wp_timezone();
            $today = new DateTimeImmutable('today', $timezone);
        
        // Oggi
        $todayRows = $this->reservations->findAgendaRange(
            $today->format('Y-m-d'),
            $today->format('Y-m-d')
        );
        
        // Questa settimana
        $weekStart = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
        $weekEnd = $weekStart->add(new DateInterval('P6D'));
        $weekRows = $this->reservations->findAgendaRange(
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );
        
        // Questo mese
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');
        $monthRows = $this->reservations->findAgendaRange(
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d')
        );

        // Mappa prenotazioni
        $todayReservations = array_map([$this, 'mapAgendaReservation'], is_array($todayRows) ? $todayRows : []);
        $weekReservations = array_map([$this, 'mapAgendaReservation'], is_array($weekRows) ? $weekRows : []);
        $monthReservations = array_map([$this, 'mapAgendaReservation'], is_array($monthRows) ? $monthRows : []);

            $responseData = [
                'today' => [
                    'date' => $today->format('Y-m-d'),
                    'stats' => $this->calculateStats($todayReservations),
                ],
                'week' => [
                    'start' => $weekStart->format('Y-m-d'),
                    'end' => $weekEnd->format('Y-m-d'),
                    'stats' => $this->calculateStats($weekReservations),
                ],
                'month' => [
                    'start' => $monthStart->format('Y-m-d'),
                    'end' => $monthEnd->format('Y-m-d'),
                    'stats' => $this->calculateStats($monthReservations),
                ],
                'trends' => $this->calculateTrends($todayReservations, $weekReservations, $monthReservations),
            ];
            
            $response = rest_ensure_response($responseData);
            
            return $response;
        } catch (Throwable $e) {
            error_log('[FP Resv Overview] Errore critico: ' . $e->getMessage());
            return new WP_Error(
                'fp_resv_overview_error',
                sprintf(__('Errore nel caricamento della panoramica: %s', 'fp-restaurant-reservations'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    public function handleAgendaV2(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // 🚨 LOG IMMEDIATO
        error_log('=== HANDLEAGENDAV2 CHIAMATO! Timestamp: ' . time() . ' ===');
        
        // LOGICA VERA - BYPASS RIMOSSO
        try {
            // STEP 1: Test base
            $step = 1;
            
            // STEP 2: Parametri
            $step = 2;
            $dateParam = $request->get_param('date');
            $rangeParam = $request->get_param('range');
            
            // STEP 3: Sanitizza
            $step = 3;
            $date = is_string($dateParam) ? sanitize_text_field($dateParam) : gmdate('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = gmdate('Y-m-d');
            }

            // STEP 4: Range
            $step = 4;
            $rangeMode = is_string($rangeParam) ? strtolower($rangeParam) : 'day';
            if (!in_array($rangeMode, ['day', 'week', 'month'], true)) {
                $rangeMode = 'day';
            }

            // STEP 5: DateTimeImmutable
            $step = 5;
            $start = new \DateTimeImmutable($date);
            $end = $start;
            
            if ($rangeMode === 'week') {
                $dayOfWeek = (int)$start->format('N');
                $start = $start->modify('-' . ($dayOfWeek - 1) . ' days');
                $end = $start->add(new \DateInterval('P6D'));
            } elseif ($rangeMode === 'month') {
                $start = $start->modify('first day of this month');
                $end = $start->modify('last day of this month');
            }
            
            // STEP 6: Test query (questo potrebbe essere il problema)
            $step = 6;
            if (!isset($this->reservations)) {
                return new WP_REST_Response([
                    'error' => 'Repository not initialized',
                    'step' => $step
                ], 500);
            }
            
            $step = 7;
            $startDate = $start->format('Y-m-d');
            $endDate = $end->format('Y-m-d');
            
            $rows = $this->reservations->findAgendaRange($startDate, $endDate);
            
            // STEP 8: Mappa prenotazioni
            $step = 8;
            $reservations = [];
            if (is_array($rows)) {
            foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    
                    $reservations[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'status' => (string)($row['status'] ?? 'pending'),
                        'date' => (string)($row['date'] ?? ''),
                        'time' => substr((string)($row['time'] ?? ''), 0, 5),
                        'party' => (int)($row['party'] ?? 0),
                        'meal' => $row['meal'] ?? null,
                        'notes' => (string)($row['notes'] ?? ''),
                        'allergies' => (string)($row['allergies'] ?? ''),
                        'created_at' => (string)($row['created_at'] ?? ''),
                        'customer' => [
                            'first_name' => (string)($row['first_name'] ?? ''),
                            'last_name' => (string)($row['last_name'] ?? ''),
                            'email' => (string)($row['email'] ?? ''),
                            'phone' => (string)($row['phone'] ?? ''),
                        ],
                    ];
                }
            }
            
            // STEP 9: Statistiche
            $step = 9;
            $totalReservations = count($reservations);
            $totalGuests = $totalReservations > 0 ? array_sum(array_column($reservations, 'party')) : 0;
            
            $statusCounts = [
                            'pending' => 0,
                            'confirmed' => 0,
                            'visited' => 0,
                            'no_show' => 0,
                            'cancelled' => 0,
            ];
            
            foreach ($reservations as $r) {
                $status = $r['status'];
                if (isset($statusCounts[$status])) {
                    $statusCounts[$status]++;
                }
            }
            
            // STEP 10: Risposta
            $step = 10;
            
            error_log("=== TROVATE {$totalReservations} PRENOTAZIONI ===");
            
            return new WP_REST_Response([
                'meta' => [
                    'range' => $rangeMode,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'current_date' => $date,
                ],
                'stats' => [
                    'total_reservations' => $totalReservations,
                    'total_guests' => $totalGuests,
                    'by_status' => $statusCounts,
                    'confirmed_percentage' => $totalReservations > 0 ? round(($statusCounts['confirmed'] / $totalReservations) * 100) : 0,
                ],
                'data' => [
                    'slots' => [],
                    'timeline' => [],
                ],
                'reservations' => $reservations,
            ], 200);
            
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'step' => $step ?? 'unknown',
                'trace' => explode("\n", $e->getTraceAsString())
            ], 500);
        }
    }

    public function handleCreateReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        error_log('[FP Resv Admin] === CREAZIONE PRENOTAZIONE DAL MANAGER START ===');
        
        try {
            $payload = $this->extractReservationPayload($request);
            
            error_log('[FP Resv Admin] Payload estratto: ' . json_encode([
                'date' => $payload['date'] ?? 'N/A',
                'time' => $payload['time'] ?? 'N/A',
                'party' => $payload['party'] ?? 'N/A',
                'meal' => $payload['meal'] ?? 'N/A',
            ]));

            $result = $this->service->create($payload);
            
            error_log('[FP Resv Admin] Prenotazione creata con ID: ' . ($result['id'] ?? 'N/A'));
            
            $entry = $this->reservations->findAgendaEntry((int) $result['id']);
            
            if ($entry === null) {
                error_log('[FP Resv Admin] WARNING: Prenotazione creata ma non trovata con findAgendaEntry');
            }
            
            $responseData = [
                'reservation' => $entry !== null ? $this->mapAgendaReservation($entry) : null,
                'result'      => $result,
            ];
            
            error_log('[FP Resv Admin] Response data: ' . json_encode($responseData));
            error_log('[FP Resv Admin] === CREAZIONE PRENOTAZIONE COMPLETATA ===');
            
            return rest_ensure_response($responseData);
            
        } catch (InvalidArgumentException|RuntimeException $exception) {
            error_log('[FP Resv Admin] Errore validazione: ' . $exception->getMessage());
            
            return new WP_Error(
                'fp_resv_admin_reservation_invalid',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (Throwable $exception) {
            error_log('[FP Resv Admin] Errore critico: ' . $exception->getMessage());
            error_log('[FP Resv Admin] Stack trace: ' . $exception->getTraceAsString());
            
            return new WP_Error(
                'fp_resv_admin_reservation_error',
                __('Impossibile creare la prenotazione.', 'fp-restaurant-reservations'),
                ['status' => 500, 'details' => $exception->getMessage()]
            );
        }
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

    public function handleDeleteReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        error_log('[FP Resv] === ELIMINAZIONE PRENOTAZIONE START ===');
        
        // Forza pulizia output buffer per evitare interferenze
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $id = absint((string) $request->get_param('id'));
        error_log('[FP Resv] ID da eliminare: ' . $id);
        
        if ($id <= 0) {
            error_log('[FP Resv] ID non valido');
            return new WP_Error('fp_resv_invalid_reservation_id', __('ID prenotazione non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        // Verifica che la prenotazione esista
        $entry = $this->reservations->findAgendaEntry($id);
        error_log('[FP Resv] Prenotazione trovata: ' . ($entry ? 'SI' : 'NO'));
        
        if ($entry === null) {
            error_log('[FP Resv] Prenotazione non trovata nel database');
            return new WP_Error('fp_resv_not_found', __('Prenotazione non trovata.', 'fp-restaurant-reservations'), ['status' => 404]);
        }

        try {
            error_log('[FP Resv] Chiamo delete() sul repository');
            
            // Elimina la prenotazione dal database
            $deleted = $this->reservations->delete($id);
            
            error_log('[FP Resv] Risultato delete(): ' . ($deleted ? 'TRUE' : 'FALSE'));
            
            if (!$deleted) {
                throw new RuntimeException('Impossibile eliminare la prenotazione.');
            }

            error_log('[FP Resv] Prenotazione eliminata dal DB, triggering action...');
            
            // Trigger action per eventuali integrazioni (ma cattura qualsiasi output)
            ob_start();
            do_action('fp_resv_reservation_deleted', $id, $entry);
            $hookOutput = ob_get_clean();
            
            if ($hookOutput) {
                error_log('[FP Resv] ATTENZIONE: Hook ha generato output: ' . $hookOutput);
            }

            error_log('[FP Resv] === ELIMINAZIONE COMPLETATA CON SUCCESSO ===');
            
            $responseData = [
                'success' => true,
                'id'      => $id,
                'message' => __('Prenotazione eliminata con successo.', 'fp-restaurant-reservations'),
            ];
            
            error_log('[FP Resv] Restituisco risposta: ' . json_encode($responseData));
            
            // Crea risposta REST esplicita
            $response = new WP_REST_Response($responseData, 200);
            $response->set_headers([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-FP-Delete-Success' => 'true',
                'X-FP-Reservation-ID' => (string) $id,
            ]);
            
            error_log('[FP Resv] Headers impostati, returnando response object');
            error_log('[FP Resv] Response data type: ' . gettype($response));
            error_log('[FP Resv] Response data class: ' . get_class($response));
            error_log('[FP Resv] Response status: ' . $response->get_status());
            error_log('[FP Resv] Response data: ' . json_encode($response->get_data()));
            
            // Verifica se ci sono filter attivi
            $filters = $GLOBALS['wp_filter']['rest_pre_serve_request'] ?? null;
            if ($filters) {
                $filterCount = count($filters->callbacks ?? []);
                error_log('[FP Resv] ATTENZIONE: Ci sono ' . $filterCount . ' filter su rest_pre_serve_request');
                
                ErrorLogger::log('REST filters potrebbero interferire con DELETE response', [
                    'endpoint' => 'DELETE /agenda/reservations/' . $id,
                    'filter_count' => $filterCount,
                    'response_status' => 200,
                    'response_has_data' => !empty($response->get_data()),
                ]);
            }
            
            return $response;
        } catch (Throwable $exception) {
            error_log('[FP Resv] ERRORE ELIMINAZIONE: ' . $exception->getMessage());
            error_log('[FP Resv] Stack trace: ' . $exception->getTraceAsString());
            
            ErrorLogger::log('Errore eliminazione prenotazione', [
                'reservation_id' => $id,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            
            return new WP_Error(
                'fp_resv_delete_failed',
                sprintf(__('Impossibile eliminare la prenotazione: %s', 'fp-restaurant-reservations'), $exception->getMessage()),
                ['status' => 500]
            );
        }
    }

    private function checkPermissions(): bool
    {
        // DEBUG: Log controllo permessi
        $userId = get_current_user_id();
        $canManage = current_user_can(Roles::MANAGE_RESERVATIONS);
        $canManageOptions = current_user_can('manage_options');
        $result = $canManage || $canManageOptions;
        
        error_log('[FP Resv Permissions] User ID: ' . $userId);
        error_log('[FP Resv Permissions] Can manage reservations: ' . ($canManage ? 'YES' : 'NO'));
        error_log('[FP Resv Permissions] Can manage options: ' . ($canManageOptions ? 'YES' : 'NO'));
        error_log('[FP Resv Permissions] Result: ' . ($result ? 'ALLOWED' : 'DENIED'));
        
        if (!$result) {
            error_log('[FP Resv Permissions] ❌ ACCESSO NEGATO! Endpoint bloccato.');
        }
        
        return $result;
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
        // Estrazione semplice e sicura dei dati
        $date = isset($row['date']) ? (string) $row['date'] : gmdate('Y-m-d');
        $time = isset($row['time']) ? substr((string) $row['time'], 0, 5) : '00:00';
        
        // Normalizza il tempo se non è nel formato corretto
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '00:00';
        }
        
        return [
            'id'         => isset($row['id']) ? (int) $row['id'] : 0,
            'status'     => isset($row['status']) ? (string) $row['status'] : 'pending',
            'date'       => $date,
            'time'       => $time,
            'slot_start' => $date . ' ' . $time,
            'party'      => isset($row['party']) ? (int) $row['party'] : 2,
            'meal'       => isset($row['meal']) ? (string) $row['meal'] : '',
            'notes'      => isset($row['notes']) ? (string) $row['notes'] : '',
            'allergies'  => isset($row['allergies']) ? (string) $row['allergies'] : '',
            'room_id'    => isset($row['room_id']) && $row['room_id'] !== null ? (int) $row['room_id'] : null,
            'table_id'   => isset($row['table_id']) && $row['table_id'] !== null ? (int) $row['table_id'] : null,
            'customer'   => [
                'id'         => isset($row['customer_id']) && $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
                'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : '',
                'last_name'  => isset($row['last_name']) ? (string) $row['last_name'] : '',
                'email'      => isset($row['email']) ? (string) $row['email'] : '',
                'phone'      => isset($row['phone']) ? (string) $row['phone'] : '',
                'language'   => isset($row['customer_lang']) ? (string) $row['customer_lang'] : '',
            ],
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
            'meal'       => $request->get_param('meal') ?? '',
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
            $chunks = preg_split('/[\r
,;]+/', (string) $row['allergies']) ?: [];
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

    /**
     * Calcola statistiche aggregate sulle prenotazioni
     * 
     * @param array<int, array<string, mixed>> $reservations
     * @return array<string, mixed>
     */
    private function calculateStats(array $reservations): array
    {
        $totalReservations = count($reservations);
        $totalGuests = 0;
        $statusCounts = [
            'pending' => 0,
            'confirmed' => 0,
            'visited' => 0,
            'no_show' => 0,
            'cancelled' => 0,
        ];
        
        foreach ($reservations as $resv) {
            $totalGuests += (int)($resv['party'] ?? 0);
            $status = (string)($resv['status'] ?? 'pending');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }
        
        return [
            'total_reservations' => $totalReservations,
            'total_guests' => $totalGuests,
            'by_status' => $statusCounts,
            'confirmed_percentage' => $totalReservations > 0 
                ? round(($statusCounts['confirmed'] / $totalReservations) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Calcola statistiche dettagliate con breakdown temporale
     * 
     * @param array<int, array<string, mixed>> $reservations
     * @param string $rangeMode
     * @return array<string, mixed>
     */
    private function calculateDetailedStats(array $reservations, string $rangeMode): array
    {
        $baseStats = $this->calculateStats($reservations);
        
        // Raggruppa per servizio (pranzo/cena basato su orario)
        $byService = [
            'lunch' => ['count' => 0, 'guests' => 0],
            'dinner' => ['count' => 0, 'guests' => 0],
            'other' => ['count' => 0, 'guests' => 0],
        ];
        
        // Raggruppa per giorno della settimana (solo per week/month)
        $byDayOfWeek = [];
        if ($rangeMode !== 'day') {
            $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($dayNames as $day) {
                $byDayOfWeek[$day] = ['count' => 0, 'guests' => 0];
            }
        }
        
        // Media coperti per prenotazione
        $partySizes = [];
        
        foreach ($reservations as $resv) {
            $time = isset($resv['time']) ? substr((string)$resv['time'], 0, 5) : '00:00';
            $hour = (int)substr($time, 0, 2);
            $party = (int)($resv['party'] ?? 0);
            
            // Servizio
            if ($hour >= 12 && $hour < 17) {
                $byService['lunch']['count']++;
                $byService['lunch']['guests'] += $party;
            } elseif ($hour >= 19 && $hour < 24) {
                $byService['dinner']['count']++;
                $byService['dinner']['guests'] += $party;
            } else {
                $byService['other']['count']++;
                $byService['other']['guests'] += $party;
            }
            
            // Giorno settimana
            if ($rangeMode !== 'day' && isset($resv['date'])) {
                $date = new DateTimeImmutable($resv['date']);
                $dayName = $date->format('l');
                if (isset($byDayOfWeek[$dayName])) {
                    $byDayOfWeek[$dayName]['count']++;
                    $byDayOfWeek[$dayName]['guests'] += $party;
                }
            }
            
            // Party sizes
            $partySizes[] = $party;
        }
        
        $result = array_merge($baseStats, [
            'by_service' => $byService,
            'average_party_size' => count($partySizes) > 0 
                ? round(array_sum($partySizes) / count($partySizes), 1) 
                : 0,
            'median_party_size' => count($partySizes) > 0 
                ? $this->calculateMedian($partySizes) 
                : 0,
        ]);
        
        if ($rangeMode !== 'day') {
            $result['by_day_of_week'] = $byDayOfWeek;
        }
        
        return $result;
    }

    /**
     * Calcola trend confrontando periodi diversi
     * 
     * @param array<int, array<string, mixed>> $todayReservations
     * @param array<int, array<string, mixed>> $weekReservations
     * @param array<int, array<string, mixed>> $monthReservations
     * @return array<string, mixed>
     */
    private function calculateTrends(array $todayReservations, array $weekReservations, array $monthReservations): array
    {
        $todayCount = count($todayReservations);
        $weekCount = count($weekReservations);
        $monthCount = count($monthReservations);
        
        $weekAverage = $weekCount > 0 ? round($weekCount / 7, 1) : 0;
        $monthAverage = $monthCount > 0 ? round($monthCount / 30, 1) : 0;
        
        // Trend oggi vs media settimanale
        $dailyTrend = 'stable';
        if ($weekAverage > 0) {
            $difference = (($todayCount - $weekAverage) / $weekAverage) * 100;
            if ($difference > 10) {
                $dailyTrend = 'up';
            } elseif ($difference < -10) {
                $dailyTrend = 'down';
            }
        }
        
        // Trend settimanale vs mensile
        $weeklyTrend = 'stable';
        if ($monthAverage > 0) {
            $difference = (($weekAverage - $monthAverage) / $monthAverage) * 100;
            if ($difference > 10) {
                $weeklyTrend = 'up';
            } elseif ($difference < -10) {
                $weeklyTrend = 'down';
            }
        }
        
        return [
            'daily' => [
                'trend' => $dailyTrend,
                'vs_week_average' => $weekAverage > 0 
                    ? round((($todayCount - $weekAverage) / $weekAverage) * 100, 1) 
                    : 0,
            ],
            'weekly' => [
                'trend' => $weeklyTrend,
                'average_per_day' => $weekAverage,
                'vs_month_average' => $monthAverage > 0 
                    ? round((($weekAverage - $monthAverage) / $monthAverage) * 100, 1) 
                    : 0,
            ],
            'monthly' => [
                'average_per_day' => $monthAverage,
                'total' => $monthCount,
            ],
        ];
    }

    /**
     * Calcola la mediana di un array di numeri
     * 
     * @param array<int, int> $numbers
     * @return float
     */
    private function calculateMedian(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        
        if ($count === 0) {
            return 0;
        }
        
        $middle = (int)floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
        }
        
        return (float)$numbers[$middle];
    }

    /**
     * Organizza le prenotazioni in base alla vista (day/week/month)
     * 
     * @param array<int, array<string, mixed>> $reservations
     * @param string $viewMode
     * @param DateTimeImmutable $start
     * @param DateTimeImmutable $end
     * @return array<string, mixed>
     */
    private function organizeByView(array $reservations, string $viewMode, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        switch ($viewMode) {
            case 'day':
                return $this->organizeByTimeSlots($reservations);
            case 'week':
                return $this->organizeByDays($reservations, $start, 7);
            case 'month':
                return $this->organizeByDays($reservations, $start, (int)$end->format('d'));
            default:
                return [];
        }
    }

    /**
     * Organizza le prenotazioni per slot orari (vista giornaliera)
     * 
     * @param array<int, array<string, mixed>> $reservations
     * @return array<string, mixed>
     */
    private function organizeByTimeSlots(array $reservations): array
    {
        $slots = [];
        $timeSlots = $this->generateTimeSlots();
        
        // Inizializza tutti gli slot vuoti
        foreach ($timeSlots as $slotTime) {
            $slots[$slotTime] = [
                'time' => $slotTime,
                'reservations' => [],
                'total_guests' => 0,
                'capacity_used' => 0,
            ];
        }
        
        // Raggruppa prenotazioni per slot
        foreach ($reservations as $resv) {
            $time = isset($resv['time']) ? substr((string)$resv['time'], 0, 5) : '00:00';
            
            // Trova lo slot pi\u00f9 vicino
            $slotTime = $this->findNearestSlot($time, $timeSlots);
            
            if (!isset($slots[$slotTime])) {
                $slots[$slotTime] = [
                    'time' => $slotTime,
                    'reservations' => [],
                    'total_guests' => 0,
                    'capacity_used' => 0,
                ];
            }
            
            $slots[$slotTime]['reservations'][] = $resv;
            $slots[$slotTime]['total_guests'] += (int)($resv['party'] ?? 0);
        }
        
        // Rimuovi slot vuoti
        $slots = array_filter($slots, static function($slot) {
            return count($slot['reservations']) > 0;
        });
        
        return [
            'slots' => array_values($slots),
            'timeline' => array_values($slots), // Alias per compatibilit\u00e0
        ];
    }

    /**
     * Organizza le prenotazioni per giorni (vista settimanale/mensile)
     * 
     * @param array<int, array<string, mixed>> $reservations
     * @param DateTimeImmutable $startDate
     * @param int $numDays
     * @return array<string, mixed>
     */
    private function organizeByDays(array $reservations, DateTimeImmutable $startDate, int $numDays): array
    {
        $days = [];
        
        // Inizializza tutti i giorni
        for ($i = 0; $i < $numDays; $i++) {
            $date = $startDate->add(new DateInterval('P' . $i . 'D'));
            $dateStr = $date->format('Y-m-d');
            
            $days[$dateStr] = [
                'date' => $dateStr,
                'day_name' => $this->getDayName($date),
                'day_number' => (int)$date->format('d'),
                'reservations' => [],
                'total_guests' => 0,
                'reservation_count' => 0,
            ];
        }
        
        // Raggruppa prenotazioni per giorno
        foreach ($reservations as $resv) {
            $date = (string)($resv['date'] ?? '');
            
            if (isset($days[$date])) {
                $days[$date]['reservations'][] = $resv;
                $days[$date]['total_guests'] += (int)($resv['party'] ?? 0);
                $days[$date]['reservation_count']++;
            }
        }
        
        return [
            'days' => array_values($days),
        ];
    }

    /**
     * Genera slot orari standard per la vista timeline
     * 
     * @return array<int, string>
     */
    private function generateTimeSlots(): array
    {
        $slots = [];
        
        // Pranzo: 12:00 - 15:00 (ogni 15 minuti)
        for ($hour = 12; $hour <= 15; $hour++) {
            for ($min = 0; $min < 60; $min += 15) {
                if ($hour === 15 && $min > 0) break;
                $slots[] = sprintf('%02d:%02d', $hour, $min);
            }
        }
        
        // Cena: 19:00 - 23:00 (ogni 15 minuti)
        for ($hour = 19; $hour <= 23; $hour++) {
            for ($min = 0; $min < 60; $min += 15) {
                if ($hour === 23 && $min > 0) break;
                $slots[] = sprintf('%02d:%02d', $hour, $min);
            }
        }
        
        return $slots;
    }

    /**
     * Trova lo slot pi\u00f9 vicino a un orario dato
     * 
     * @param string $time
     * @param array<int, string> $slots
     * @return string
     */
    private function findNearestSlot(string $time, array $slots): string
    {
        if (in_array($time, $slots, true)) {
            return $time;
        }
        
        // Converti in minuti
        [$hours, $minutes] = explode(':', $time);
        $totalMinutes = ((int)$hours * 60) + (int)$minutes;
        
        // Arrotonda a 15 minuti
        $roundedMinutes = (int)(round($totalMinutes / 15) * 15);
        $roundedHours = (int)floor($roundedMinutes / 60);
        $roundedMins = $roundedMinutes % 60;
        
        return sprintf('%02d:%02d', $roundedHours, $roundedMins);
    }

    /**
     * Ottiene il nome del giorno in italiano
     * 
     * @param DateTimeImmutable $date
     * @return string
     */
    private function getDayName(DateTimeImmutable $date): string
    {
        $dayNames = [
            'Monday' => 'Luned\u00ec',
            'Tuesday' => 'Marted\u00ec',
            'Wednesday' => 'Mercoled\u00ec',
            'Thursday' => 'Gioved\u00ec',
            'Friday' => 'Venerd\u00ec',
            'Saturday' => 'Sabato',
            'Sunday' => 'Domenica',
        ];
        
        $englishName = $date->format('l');
        return $dayNames[$englishName] ?? $englishName;
    }

    /**
     * Helper per debug: crea una rappresentazione della struttura dati senza i valori completi
     */
    private function debugStructure(mixed $data, int $depth = 0): string
    {
        if ($depth > 3) {
            return '...';
        }
        
        if (is_array($data)) {
            $keys = array_keys($data);
            $count = count($data);
            $sample = array_slice($keys, 0, 5);
            return sprintf('Array(%d)[%s]', $count, implode(', ', $sample));
        }
        
        if (is_object($data)) {
            return get_class($data);
        }
        
        if (is_string($data)) {
            return sprintf('string(%d)', strlen($data));
        }
        
        return gettype($data);
    }
}
