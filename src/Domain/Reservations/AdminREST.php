<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Application\Reservations\DeleteReservationUseCase;
use FP\Resv\Application\Reservations\GetReservationUseCase;
use FP\Resv\Application\Reservations\UpdateReservationUseCase;
use FP\Resv\Application\Reservations\UpdateReservationStatusUseCase;
use FP\Resv\Core\Roles;
use FP\Resv\Core\ErrorLogger;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Reservations\Admin\AgendaHandler;
use FP\Resv\Domain\Reservations\Admin\ArrivalsHandler;
use FP\Resv\Domain\Reservations\Admin\OverviewHandler;
use FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor;
use FP\Resv\Domain\Reservations\Admin\StatsHandler;
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

/**
 * Legacy Admin REST Endpoint
 * 
 * @deprecated 0.9.0-rc11 This class should be migrated to use Application layer (Use Cases).
 *             For now, it's kept for backward compatibility but new code should use Presentation layer.
 */
final class AdminREST
{
    public function __construct(
        private readonly Repository $reservations,
        private readonly Service $service, // Kept for backward compatibility
        private readonly AgendaHandler $agendaHandler,
        private readonly StatsHandler $statsHandler,
        private readonly ArrivalsHandler $arrivalsHandler,
        private readonly OverviewHandler $overviewHandler,
        private readonly ReservationPayloadExtractor $payloadExtractor,
        private readonly CreateReservationUseCase $createUseCase,
        private readonly UpdateReservationUseCase $updateUseCase,
        private readonly DeleteReservationUseCase $deleteUseCase,
        private readonly GetReservationUseCase $getReservationUseCase,
        private readonly UpdateReservationStatusUseCase $updateStatusUseCase,
        private readonly ?GoogleCalendarService $calendar = null,
        private readonly ?LayoutService $layout = null
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        
        // Fallback: se siamo già in rest_api_init, registra subito
        if (did_action('rest_api_init')) {
            $this->registerRoutes();
        }
        
        // Clean output buffer before REST API responses
        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            // Only for our endpoints
            if (strpos($request->get_route(), '/fp-resv/') === 0) {
                // Clean ALL output buffers before serving REST response
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (ob_get_level() === 0) {
                    ob_start();
                }
            }
            return $served;
        }, 10, 4);
    }

    public function registerRoutes(): void
    {
        
        try {
            $result = register_rest_route(
            'fp-resv/v1',
            '/agenda',
            [
                'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'handleAgendaV2'],
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
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv AdminREST] ❌ ERRORE registrazione /agenda: ' . $e->getMessage());
            }
        }

        // DEBUG endpoint - solo se WP_DEBUG è attivo e utente ha permessi
        if (defined('WP_DEBUG') && WP_DEBUG) {
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
                            'debug_mode' => true
                        ], 200);
                    },
                    'permission_callback' => [$this, 'checkPermissions'],
                ]
            );
        }

        register_rest_route(
            'fp-resv/v1',
            '/agenda/reservations',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleCreateReservation'],
                'permission_callback' => [$this, 'checkManagePermissions'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/agenda/reservations/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'handleUpdateReservation'],
                    'permission_callback' => [$this, 'checkManagePermissions'],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'handleDeleteReservation'],
                    'permission_callback' => [$this, 'checkManagePermissions'],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/agenda/reservations/(?P<id>\d+)/move',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleMoveReservation'],
                'permission_callback' => [$this, 'checkManagePermissions'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/reservations/arrivals',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this->arrivalsHandler, 'handle'],
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
                'callback'            => [$this->overviewHandler, 'handle'],
                'permission_callback' => [$this, 'checkPermissions'],
            ]
        );
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
                $date = current_time('Y-m-d');
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
                    $reservations[] = $this->agendaHandler->mapAgendaReservation($row);
                } catch (Throwable $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[FP Resv Stats] Errore mapping prenotazione: ' . $e->getMessage());
                    }
                    continue;
                }
            }

            // Calcola statistiche dettagliate
            $stats = $this->statsHandler->calculateDetailedStats($reservations, $rangeMode);

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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Stats] Errore critico: ' . $e->getMessage());
            }
            return new WP_Error(
                'fp_resv_stats_error',
                sprintf(__('Errore nel caricamento delle statistiche: %s', 'fp-restaurant-reservations'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }


    public function handleAgendaV2(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // 🚨 LOG IMMEDIATO
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('=== HANDLEAGENDAV2 CHIAMATO! Timestamp: ' . time() . ' ===');
        }
        
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
            $date = is_string($dateParam) ? sanitize_text_field($dateParam) : current_time('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = current_time('Y-m-d');
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
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Admin handleAgendaV2] Chiamata findAgendaRange con startDate=' . $startDate . ' endDate=' . $endDate);
            }
            
            $rows = $this->reservations->findAgendaRange($startDate, $endDate);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Admin handleAgendaV2] findAgendaRange ha restituito: ' . (is_array($rows) ? count($rows) : 'NULL') . ' righe');
                
                if (is_array($rows) && count($rows) > 0) {
                    error_log('[FP Admin handleAgendaV2] Prima riga: ' . wp_json_encode($rows[0]));
                } else {
                    error_log('[FP Admin handleAgendaV2] ⚠️ NESSUNA RIGA RESTITUITA DA findAgendaRange!');
                }
            }
            
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
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("=== TROVATE {$totalReservations} PRENOTAZIONI ===");
            }
            
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Admin] === CREAZIONE PRENOTAZIONE DAL MANAGER START ===');
            error_log('[FP Resv Admin] Request method: ' . $request->get_method());
            error_log('[FP Resv Admin] Request route: ' . $request->get_route());
            error_log('[FP Resv Admin] Request params: ' . wp_json_encode($request->get_params()));
        }
        
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 1: Estrazione payload...');
            }
            
            $payload = $this->payloadExtractor->extract($request);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 2: Payload estratto con successo: ' . wp_json_encode([
                    'date' => $payload['date'] ?? 'N/A',
                    'time' => $payload['time'] ?? 'N/A',
                    'party' => $payload['party'] ?? 'N/A',
                    'meal' => $payload['meal'] ?? 'N/A',
                    'first_name' => $payload['first_name'] ?? 'N/A',
                    'email' => $payload['email'] ?? 'N/A',
                ]));
                error_log('[FP Resv Admin] STEP 3: Chiamata service->create()...');
            }
            
            // Use Application layer Use Case instead of direct service call
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 3: Chiamata CreateReservationUseCase->execute()...');
            }
            
            $reservation = $this->createUseCase->execute($payload);
            $reservationId = $reservation->getId();
            
            // Convert model to array for compatibility
            $result = $reservation->toArray();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 4: CreateReservationUseCase->execute() completato con successo');
                error_log('[FP Resv Admin] Reservation ID: ' . $reservationId);
            }
            
            if ($reservationId <= 0) {
                throw new RuntimeException('Prenotazione creata ma ID non valido');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] Prenotazione creata con ID: ' . $reservationId);
            }
            
            // Tenta di recuperare la prenotazione appena creata con retry
            $entry = null;
            $maxRetries = 3;
            for ($i = 0; $i < $maxRetries; $i++) {
                $entry = $this->reservations->findAgendaEntry($reservationId);
                if ($entry !== null) {
                    break;
                }
                // Piccolo delay prima di riprovare (WordPress potrebbe avere cache)
                if ($i < $maxRetries - 1) {
                    usleep(50000); // 50ms
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[FP Resv Admin] Retry ' . ($i + 1) . ' per trovare prenotazione #' . $reservationId);
                    }
                }
            }
            
            if ($entry === null) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[FP Resv Admin] ERRORE: Prenotazione #' . $reservationId . ' creata ma NON trovata dopo ' . $maxRetries . ' tentativi');
                    error_log('[FP Resv Admin] Dettagli result: ' . json_encode($result));
                }
                
                // La prenotazione esiste ma non riusciamo a recuperarla - restituiamo comunque successo
                // con i dati minimi necessari
                $responseData = [
                    'reservation' => [
                        'id' => $reservationId,
                        'status' => $result['status'] ?? 'pending',
                        'date' => $payload['date'] ?? '',
                        'time' => $payload['time'] ?? '',
                        'party' => $payload['party'] ?? 0,
                        'meal' => $payload['meal'] ?? '',
                        'customer' => [
                            'first_name' => $payload['first_name'] ?? '',
                            'last_name' => $payload['last_name'] ?? '',
                            'email' => $payload['email'] ?? '',
                            'phone' => $payload['phone'] ?? '',
                        ],
                    ],
                    'result' => $result,
                    'warning' => 'Prenotazione creata ma recupero dati ritardato',
                ];
            } else {
                $responseData = [
                    'reservation' => $this->agendaHandler->mapAgendaReservation($entry),
                    'result'      => $result,
                ];
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 6: Costruzione risposta...');
                error_log('[FP Resv Admin] Response data: ' . wp_json_encode([
                'has_reservation' => isset($responseData['reservation']),
                'reservation_id' => $responseData['reservation']['id'] ?? null,
            ]));
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 7: Chiamata rest_ensure_response()...');
            }
            $response = rest_ensure_response($responseData);
            
            // Aggiungi header personalizzati per il debug
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'X-FP-Resv-Debug' => 'creation-success',
                    'X-FP-Resv-ID' => (string) $reservationId,
                    'X-FP-Resv-Timestamp' => (string) time(),
                ]);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] STEP 8: Response creata: ' . get_class($response));
                error_log('[FP Resv Admin] Response status: ' . ($response instanceof WP_REST_Response ? $response->get_status() : 'N/A'));
                error_log('[FP Resv Admin] Response data size: ' . strlen(wp_json_encode($response->get_data())));
                error_log('[FP Resv Admin] === CREAZIONE PRENOTAZIONE COMPLETATA - RITORNO RESPONSE ===');
            }
            
            return $response;
            
        } catch (InvalidArgumentException|RuntimeException $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] Errore validazione: ' . $exception->getMessage());
                error_log('[FP Resv Admin] Stack trace: ' . $exception->getTraceAsString());
            }
            
            return new WP_Error(
                'fp_resv_admin_reservation_invalid',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (ValidationException $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] ValidationException: ' . $exception->getMessage());
                error_log('[FP Resv Admin] Context: ' . wp_json_encode($exception->getContext()));
            }

            return new WP_Error(
                'fp_resv_admin_reservation_validation_error',
                $exception->getMessage(),
                [
                    'status' => 422,
                    'errors' => $exception->getContext(),
                ]
            );
        } catch (Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] Errore critico: ' . $exception->getMessage());
                error_log('[FP Resv Admin] Stack trace: ' . $exception->getTraceAsString());
            }
            
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

        // Customer data updates - allow empty values for backend operations
        if ($request->offsetExists('first_name')) {
            $firstName = trim(sanitize_text_field((string) $request->get_param('first_name')));
            // Allow empty string for backend operations
            $updates['first_name'] = $firstName;
        }

        if ($request->offsetExists('last_name')) {
            $lastName = trim(sanitize_text_field((string) $request->get_param('last_name')));
            // Allow empty string for backend operations
            $updates['last_name'] = $lastName;
        }

        if ($request->offsetExists('email')) {
            $email = trim(sanitize_email((string) $request->get_param('email')));
            // If email is provided, it must be valid; empty email is allowed for backend
            if (!empty($email) && !is_email($email)) {
                return new WP_Error('fp_resv_invalid_email', __('Email non valida.', 'fp-restaurant-reservations'), ['status' => 400]);
            }
            $updates['email'] = $email;
        }

        if ($request->offsetExists('phone')) {
            $phone = trim(sanitize_text_field((string) $request->get_param('phone')));
            // Allow empty string for backend operations
            $updates['phone'] = $phone;
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

        // Use GetReservationUseCase to check if reservation exists
        try {
            $originalReservation = $this->getReservationUseCase->execute($id);
            $original = $this->reservations->findAgendaEntry($id); // Still need array format for comparison
        } catch (ValidationException $e) {
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
            // Use Application layer Use Case instead of direct repository call
            $reservation = $this->updateUseCase->execute($id, $updates);
        } catch (ValidationException $exception) {
            return new WP_Error('fp_resv_update_failed', $exception->getMessage(), ['status' => 400]);
        } catch (RuntimeException $exception) {
            return new WP_Error('fp_resv_update_failed', $exception->getMessage(), ['status' => 500]);
        }

        // Use the reservation model returned by the Use Case
        // Try to use mapAgendaReservationFromModel if available, otherwise fallback to findAgendaEntry
        $entry = null;
        if (method_exists($this->agendaHandler, 'mapAgendaReservationFromModel')) {
            $entry = $this->agendaHandler->mapAgendaReservationFromModel($reservation);
        } else {
            // Fallback to array format
            $entry = $this->reservations->findAgendaEntry($id);
        }

        // Cattura eventuali output indesiderati da hook durante l'update
        ob_start();
        
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
        
        $hookOutput = ob_get_clean();
        
        if ($hookOutput && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Admin] ATTENZIONE: Hook ha generato output durante update: ' . $hookOutput);
        }

        return rest_ensure_response([
            'reservation' => $entry !== null ? $this->agendaHandler->mapAgendaReservation($entry) : null,
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

        // Cattura eventuali output indesiderati da hook durante il move
        ob_start();
        do_action('fp_resv_reservation_moved', $id, $entry, $updates);
        $hookOutput = ob_get_clean();
        
        if ($hookOutput && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Admin] ATTENZIONE: Hook ha generato output durante move: ' . $hookOutput);
        }

        return rest_ensure_response([
            'reservation' => $entry !== null ? $this->agendaHandler->mapAgendaReservation($entry) : null,
            'moved'       => true,
        ]);
    }

    public function handleDeleteReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = absint((string) $request->get_param('id'));
        
        if ($id <= 0) {
            return new WP_Error('fp_resv_invalid_reservation_id', __('ID prenotazione non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        // Verifica che la prenotazione esista usando GetReservationUseCase
        try {
            $reservationModel = $this->getReservationUseCase->execute($id);
            // Still need array format for hooks
            $entry = $this->reservations->findAgendaEntry($id);
        } catch (ValidationException $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] Prenotazione non trovata: ' . $e->getMessage());
            }
            return new WP_Error('fp_resv_not_found', __('Prenotazione non trovata.', 'fp-restaurant-reservations'), ['status' => 404]);
        }
        
        if ($entry === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] Prenotazione non trovata nel database');
            }
            return new WP_Error('fp_resv_not_found', __('Prenotazione non trovata.', 'fp-restaurant-reservations'), ['status' => 404]);
        }

        try {
            // Use Application layer Use Case instead of direct repository call
            $deleted = $this->deleteUseCase->execute($id);
            
            if (!$deleted) {
                throw new RuntimeException('Impossibile eliminare la prenotazione.');
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] Prenotazione eliminata dal DB, triggering action...');
            }
            
            // Trigger action per eventuali integrazioni - cattura output indesiderato
            ob_start();
            do_action('fp_resv_reservation_deleted', $id, $entry);
            $hookOutput = ob_get_clean();
            
            if ($hookOutput && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] ATTENZIONE: Hook ha generato output: ' . $hookOutput);
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] Eliminazione prenotazione ID ' . $id . ' completata');
            }
            
            $responseData = [
                'success' => true,
                'id'      => $id,
                'message' => __('Prenotazione eliminata con successo.', 'fp-restaurant-reservations'),
            ];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] Restituisco risposta: ' . json_encode($responseData));
            }
            
            // Crea risposta REST esplicita
            $response = new WP_REST_Response($responseData, 200);
            $response->set_headers([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-FP-Delete-Success' => 'true',
                'X-FP-Reservation-ID' => (string) $id,
            ]);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] Headers impostati, returnando response object');
                error_log('[FP Resv] Response data type: ' . gettype($response));
                error_log('[FP Resv] Response data class: ' . get_class($response));
                error_log('[FP Resv] Response status: ' . $response->get_status());
                error_log('[FP Resv] Response data: ' . json_encode($response->get_data()));
            }
            
            // Verifica se ci sono filter attivi
            $filters = $GLOBALS['wp_filter']['rest_pre_serve_request'] ?? null;
            if ($filters && defined('WP_DEBUG') && WP_DEBUG) {
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv] ERRORE ELIMINAZIONE: ' . $exception->getMessage());
                error_log('[FP Resv] Stack trace: ' . $exception->getTraceAsString());
            }
            
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

    /**
     * Verifica permessi per endpoint di sola lettura (GET)
     * Permette accesso a: admin, manager, viewer
     */
    public function checkPermissions(): bool
    {
        $userId = get_current_user_id();
        $canManage = current_user_can(Roles::MANAGE_RESERVATIONS);
        $canView = current_user_can(Roles::VIEW_RESERVATIONS_MANAGER);
        $canManageOptions = current_user_can('manage_options');
        $result = $canManage || $canView || $canManageOptions;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Permissions] User ID: ' . $userId);
            error_log('[FP Resv Permissions] Can manage: ' . ($canManage ? 'YES' : 'NO'));
            error_log('[FP Resv Permissions] Can view: ' . ($canView ? 'YES' : 'NO'));
            error_log('[FP Resv Permissions] Can manage options: ' . ($canManageOptions ? 'YES' : 'NO'));
            error_log('[FP Resv Permissions] Result: ' . ($result ? 'ALLOWED' : 'DENIED'));
            
            if (!$result) {
                error_log('[FP Resv Permissions] ❌ ACCESSO NEGATO!');
            }
        }
        
        return $result;
    }

    /**
     * Verifica permessi per endpoint di scrittura (POST/PUT/DELETE)
     * Permette accesso a: admin, manager E viewer
     * Il viewer ha accesso completo alle operazioni del Manager
     */
    public function checkManagePermissions(): bool
    {
        $userId = get_current_user_id();
        $canManage = current_user_can(Roles::MANAGE_RESERVATIONS);
        $canView = current_user_can(Roles::VIEW_RESERVATIONS_MANAGER);
        $canManageOptions = current_user_can('manage_options');
        $result = $canManage || $canView || $canManageOptions;
        
        if (defined('WP_DEBUG') && WP_DEBUG && !$result) {
            error_log('[FP Resv Manage Permissions] ❌ ACCESSO NEGATO per user ' . $userId);
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
