<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * REST API Service Provider
 * 
 * Registers REST API endpoints and related services.
 * Only loads during REST API requests.
 *
 * @package FP\Resv\Providers
 */
final class RESTServiceProvider extends ServiceProvider
{
    /**
     * Register REST services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerNewEndpoints($container);
        $this->registerLegacyEndpoints($container);
    }
    
    /**
     * Register new architecture endpoints (Presentation layer)
     */
    private function registerNewEndpoints(Container $container): void
    {
        // ReservationsEndpoint requires: Logger, Use Cases, Repository, Sanitizer
        $container->singleton(
            \FP\Resv\Presentation\API\REST\ReservationsEndpoint::class,
            function (Container $container) {
                $logger = $container->get(\FP\Resv\Core\Services\LoggerInterface::class);
                $createUseCase = $container->get(\FP\Resv\Application\Reservations\CreateReservationUseCase::class);
                $updateUseCase = $container->get(\FP\Resv\Application\Reservations\UpdateReservationUseCase::class);
                $deleteUseCase = $container->get(\FP\Resv\Application\Reservations\DeleteReservationUseCase::class);
                $repository = $container->get(\FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface::class);
                $sanitizer = $container->get(\FP\Resv\Core\Services\SanitizerInterface::class);
                
                return new \FP\Resv\Presentation\API\REST\ReservationsEndpoint(
                    $logger,
                    $createUseCase,
                    $updateUseCase,
                    $deleteUseCase,
                    $repository,
                    $sanitizer
                );
            }
        );
        
        // AvailabilityEndpoint requires: Logger, Use Case, Sanitizer
        $container->singleton(
            \FP\Resv\Presentation\API\REST\AvailabilityEndpoint::class,
            function (Container $container) {
                $logger = $container->get(\FP\Resv\Core\Services\LoggerInterface::class);
                $getAvailabilityUseCase = $container->get(\FP\Resv\Application\Availability\GetAvailabilityUseCase::class);
                $sanitizer = $container->get(\FP\Resv\Core\Services\SanitizerInterface::class);
                
                return new \FP\Resv\Presentation\API\REST\AvailabilityEndpoint(
                    $logger,
                    $getAvailabilityUseCase,
                    $sanitizer
                );
            }
        );
        
        // EventsEndpoint requires: Logger, Use Cases, Repository, Sanitizer
        $container->singleton(
            \FP\Resv\Presentation\API\REST\EventsEndpoint::class,
            function (Container $container) {
                $logger = $container->get(\FP\Resv\Core\Services\LoggerInterface::class);
                $createUseCase = $container->get(\FP\Resv\Application\Events\CreateEventUseCase::class);
                $updateUseCase = $container->get(\FP\Resv\Application\Events\UpdateEventUseCase::class);
                $deleteUseCase = $container->get(\FP\Resv\Application\Events\DeleteEventUseCase::class);
                $repository = $container->get(\FP\Resv\Domain\Events\Repositories\EventRepositoryInterface::class);
                $sanitizer = $container->get(\FP\Resv\Core\Services\SanitizerInterface::class);
                
                return new \FP\Resv\Presentation\API\REST\EventsEndpoint(
                    $logger,
                    $createUseCase,
                    $updateUseCase,
                    $deleteUseCase,
                    $repository,
                    $sanitizer
                );
            }
        );
        
        // ClosuresEndpoint requires: Logger, Use Cases, Repository, Sanitizer
        $container->singleton(
            \FP\Resv\Presentation\API\REST\ClosuresEndpoint::class,
            function (Container $container) {
                $logger = $container->get(\FP\Resv\Core\Services\LoggerInterface::class);
                $createUseCase = $container->get(\FP\Resv\Application\Closures\CreateClosureUseCase::class);
                $updateUseCase = $container->get(\FP\Resv\Application\Closures\UpdateClosureUseCase::class);
                $deleteUseCase = $container->get(\FP\Resv\Application\Closures\DeleteClosureUseCase::class);
                $repository = $container->get(\FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface::class);
                $sanitizer = $container->get(\FP\Resv\Core\Services\SanitizerInterface::class);
                
                return new \FP\Resv\Presentation\API\REST\ClosuresEndpoint(
                    $logger,
                    $createUseCase,
                    $updateUseCase,
                    $deleteUseCase,
                    $repository,
                    $sanitizer
                );
            }
        );
    }
    
    /**
     * Register legacy endpoints (Domain layer - for backward compatibility)
     */
    private function registerLegacyEndpoints(Container $container): void
    {
        // Reservations REST handlers
        $container->singleton(
            \FP\Resv\Domain\Reservations\REST\AvailabilityHandler::class,
            function (Container $container) {
                $availability = $container->get(\FP\Resv\Domain\Reservations\Availability::class);
                return new \FP\Resv\Domain\Reservations\REST\AvailabilityHandler($availability);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\REST\ReservationHandler::class,
            function (Container $container) {
                $reservationsService = $container->get(\FP\Resv\Domain\Reservations\Service::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                return new \FP\Resv\Domain\Reservations\REST\ReservationHandler($reservationsService, $reservationsRepository);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\REST::class,
            function (Container $container) {
                $availability = $container->get(\FP\Resv\Domain\Reservations\Availability::class);
                $reservationsService = $container->get(\FP\Resv\Domain\Reservations\Service::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $availabilityHandler = $container->get(\FP\Resv\Domain\Reservations\REST\AvailabilityHandler::class);
                $reservationHandler = $container->get(\FP\Resv\Domain\Reservations\REST\ReservationHandler::class);
                
                $rest = new \FP\Resv\Domain\Reservations\REST(
                    $availability,
                    $reservationsService,
                    $reservationsRepository,
                    $availabilityHandler,
                    $reservationHandler
                );
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('reservations.rest', \FP\Resv\Domain\Reservations\REST::class);
        
        // Direct endpoint (bypasses WordPress REST)
        $container->singleton(
            \FP\Resv\Domain\Reservations\DirectEndpoint::class,
            function (Container $container) {
                $reservationsRest = $container->get(\FP\Resv\Domain\Reservations\REST::class);
                $directEndpoint = new \FP\Resv\Domain\Reservations\DirectEndpoint($reservationsRest);
                $directEndpoint->register();
                return $directEndpoint;
            }
        );
        
        // Events REST
        $container->singleton(
            \FP\Resv\Domain\Events\REST::class,
            function (Container $container) {
                $eventsService = $container->get(\FP\Resv\Domain\Events\Service::class);
                $rest = new \FP\Resv\Domain\Events\REST($eventsService);
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('events.rest', \FP\Resv\Domain\Events\REST::class);
        
        // Payments REST
        $container->singleton(
            \FP\Resv\Domain\Payments\REST::class,
            function (Container $container) {
                $stripe = $container->get(\FP\Resv\Domain\Payments\StripeService::class);
                $paymentsRepository = $container->get(\FP\Resv\Domain\Payments\Repository::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $rest = new \FP\Resv\Domain\Payments\REST($stripe, $paymentsRepository, $reservationsRepository);
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('payments.rest', \FP\Resv\Domain\Payments\REST::class);
        
        // Admin REST handlers
        $container->singleton(
            \FP\Resv\Domain\Reservations\Admin\AgendaHandler::class,
            function (Container $container) {
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                return new \FP\Resv\Domain\Reservations\Admin\AgendaHandler($reservationsRepository);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Admin\StatsHandler::class,
            \FP\Resv\Domain\Reservations\Admin\StatsHandler::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Admin\ArrivalsHandler::class,
            function (Container $container) {
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                return new \FP\Resv\Domain\Reservations\Admin\ArrivalsHandler($reservationsRepository);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Admin\OverviewHandler::class,
            function (Container $container) {
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $agendaHandler = $container->get(\FP\Resv\Domain\Reservations\Admin\AgendaHandler::class);
                $statsHandler = $container->get(\FP\Resv\Domain\Reservations\Admin\StatsHandler::class);
                return new \FP\Resv\Domain\Reservations\Admin\OverviewHandler($reservationsRepository, $agendaHandler, $statsHandler);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor::class,
            \FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\AdminREST::class,
            function (Container $container) {
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $reservationsService = $container->get(\FP\Resv\Domain\Reservations\Service::class);
                $agendaHandler = $container->get(\FP\Resv\Domain\Reservations\Admin\AgendaHandler::class);
                $statsHandler = $container->get(\FP\Resv\Domain\Reservations\Admin\StatsHandler::class);
                $arrivalsHandler = $container->get(\FP\Resv\Domain\Reservations\Admin\ArrivalsHandler::class);
                $overviewHandler = $container->get(\FP\Resv\Domain\Reservations\Admin\OverviewHandler::class);
                $payloadExtractor = $container->get(\FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor::class);
                $createUseCase = $container->get(\FP\Resv\Application\Reservations\CreateReservationUseCase::class);
                $updateUseCase = $container->get(\FP\Resv\Application\Reservations\UpdateReservationUseCase::class);
                $deleteUseCase = $container->get(\FP\Resv\Application\Reservations\DeleteReservationUseCase::class);
                $getReservationUseCase = $container->get(\FP\Resv\Application\Reservations\GetReservationUseCase::class);
                $updateStatusUseCase = $container->get(\FP\Resv\Application\Reservations\UpdateReservationStatusUseCase::class);
                $googleCalendar = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarService::class);
                $tablesLayout = $container->get(\FP\Resv\Domain\Tables\LayoutService::class);
                
                $rest = new \FP\Resv\Domain\Reservations\AdminREST(
                    $reservationsRepository,
                    $reservationsService,
                    $agendaHandler,
                    $statsHandler,
                    $arrivalsHandler,
                    $overviewHandler,
                    $payloadExtractor,
                    $createUseCase,
                    $updateUseCase,
                    $deleteUseCase,
                    $getReservationUseCase,
                    $updateStatusUseCase,
                    $googleCalendar,
                    $tablesLayout
                );
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('reservations.admin_rest', \FP\Resv\Domain\Reservations\AdminREST::class);
        
        // Tables REST (conditional)
        $container->singleton(
            \FP\Resv\Domain\Tables\REST::class,
            function (Container $container) {
                if (!$container->has('feature.tables_enabled') || !$container->get('feature.tables_enabled')) {
                    return null;
                }
                
                $tablesLayout = $container->get(\FP\Resv\Domain\Tables\LayoutService::class);
                $rest = new \FP\Resv\Domain\Tables\REST($tablesLayout);
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('tables.rest', \FP\Resv\Domain\Tables\REST::class);
        
        // Closures REST helpers
        $container->singleton(
            \FP\Resv\Domain\Closures\ClosuresDateRangeResolver::class,
            \FP\Resv\Domain\Closures\ClosuresDateRangeResolver::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\ClosuresPayloadCollector::class,
            \FP\Resv\Domain\Closures\ClosuresPayloadCollector::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\ClosuresModelExporter::class,
            \FP\Resv\Domain\Closures\ClosuresModelExporter::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\ClosuresResponseBuilder::class,
            \FP\Resv\Domain\Closures\ClosuresResponseBuilder::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\REST::class,
            function (Container $container) {
                $closuresService = $container->get(\FP\Resv\Domain\Closures\Service::class);
                $dateRangeResolver = $container->get(\FP\Resv\Domain\Closures\ClosuresDateRangeResolver::class);
                $payloadCollector = $container->get(\FP\Resv\Domain\Closures\ClosuresPayloadCollector::class);
                $modelExporter = $container->get(\FP\Resv\Domain\Closures\ClosuresModelExporter::class);
                $responseBuilder = $container->get(\FP\Resv\Domain\Closures\ClosuresResponseBuilder::class);
                
                $rest = new \FP\Resv\Domain\Closures\REST(
                    $closuresService,
                    $dateRangeResolver,
                    $payloadCollector,
                    $modelExporter,
                    $responseBuilder
                );
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('closures.rest', \FP\Resv\Domain\Closures\REST::class);
        
        // Closures AJAX handler
        $container->singleton(
            \FP\Resv\Domain\Closures\AjaxHandler::class,
            function (Container $container) {
                $closuresService = $container->get(\FP\Resv\Domain\Closures\Service::class);
                $handler = new \FP\Resv\Domain\Closures\AjaxHandler($closuresService);
                $handler->register();
                return $handler;
            }
        );
        
        // Surveys REST
        $container->singleton(
            \FP\Resv\Domain\Surveys\REST::class,
            function (Container $container) {
                $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                
                $rest = new \FP\Resv\Domain\Surveys\REST($options, $languageSettings, $reservationsRepository, $db);
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('surveys.rest', \FP\Resv\Domain\Surveys\REST::class);
        
        // Reports REST
        $container->singleton(
            \FP\Resv\Domain\Reports\REST::class,
            function (Container $container) {
                $reportsService = $container->get(\FP\Resv\Domain\Reports\Service::class);
                $rest = new \FP\Resv\Domain\Reports\REST($reportsService);
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('reports.rest', \FP\Resv\Domain\Reports\REST::class);
        
        // Diagnostics REST
        $container->singleton(
            \FP\Resv\Domain\Diagnostics\REST::class,
            function (Container $container) {
                $diagnosticsService = $container->get(\FP\Resv\Domain\Diagnostics\Service::class);
                $rest = new \FP\Resv\Domain\Diagnostics\REST($diagnosticsService);
                $rest->register();
                return $rest;
            }
        );
        
        $container->alias('diagnostics.rest', \FP\Resv\Domain\Diagnostics\REST::class);
    }
    
    /**
     * Boot REST services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Register AJAX handlers EARLY (before admin-ajax.php checks for hooks)
        // This must be done outside rest_api_init callback
        if ($container->has(\FP\Resv\Domain\Closures\AjaxHandler::class)) {
            $container->get(\FP\Resv\Domain\Closures\AjaxHandler::class);
        }
        
        // Register REST routes
        $this->registerRoutes($container);
    }
    
    /**
     * Register REST routes
     * 
     * @param Container $container Service container
     * @return void
     */
    private function registerRoutes(Container $container): void
    {
        $hooks = $container->get(\FP\Resv\Core\Adapters\HooksAdapterInterface::class);
        
        // Force instantiation of legacy REST to ensure /nonce endpoint is registered
        if ($container->has(\FP\Resv\Domain\Reservations\REST::class)) {
            $container->get(\FP\Resv\Domain\Reservations\REST::class);
        }
        
        // Register routes immediately if rest_api_init already fired, otherwise wait for it
        $registerRoutesCallback = function () use ($container): void {
            $reservationsEndpoint = $container->get(\FP\Resv\Presentation\API\REST\ReservationsEndpoint::class);
            $availabilityEndpoint = $container->get(\FP\Resv\Presentation\API\REST\AvailabilityEndpoint::class);
            $eventsEndpoint = $container->get(\FP\Resv\Presentation\API\REST\EventsEndpoint::class);
            $closuresEndpoint = $container->get(\FP\Resv\Presentation\API\REST\ClosuresEndpoint::class);
            
            // Ensure legacy REST is instantiated for /nonce endpoint
            if ($container->has(\FP\Resv\Domain\Reservations\REST::class)) {
                $container->get(\FP\Resv\Domain\Reservations\REST::class);
            }
            
            // Reservations routes
            register_rest_route('fp-resv/v1', '/reservations', [
                [
                    'methods' => 'POST',
                    'callback' => [$reservationsEndpoint, 'create'],
                    'permission_callback' => '__return_true',
                ],
            ]);
            
            register_rest_route('fp-resv/v1', '/reservations/(?P<id>\d+)', [
                [
                    'methods' => 'GET',
                    'callback' => [$reservationsEndpoint, 'get'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [$reservationsEndpoint, 'update'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [$reservationsEndpoint, 'delete'],
                    'permission_callback' => '__return_true',
                ],
            ]);
            
            // Availability routes
            register_rest_route('fp-resv/v1', '/availability', [
                [
                    'methods' => 'GET',
                    'callback' => [$availabilityEndpoint, 'getAvailability'],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'date' => [
                            'required' => true,
                            'type' => 'string',
                            'validate_callback' => static function ($value): bool {
                                return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
                            },
                        ],
                        'party' => [
                            'required' => true,
                            'type' => 'integer',
                            'sanitize_callback' => static fn ($value): int => max(0, absint($value)),
                            'validate_callback' => static fn ($value): bool => absint($value) > 0,
                        ],
                        'meal' => [
                            'required' => false,
                            'type' => 'string',
                        ],
                    ],
                ],
            ]);
            
            register_rest_route('fp-resv/v1', '/available-days', [
                [
                    'methods' => 'GET',
                    'callback' => [$availabilityEndpoint, 'getAvailableDays'],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'from' => [
                            'required' => false,
                            'type' => 'string',
                        ],
                        'to' => [
                            'required' => false,
                            'type' => 'string',
                        ],
                        'meal' => [
                            'required' => false,
                            'type' => 'string',
                        ],
                    ],
                ],
            ]);
            
            register_rest_route('fp-resv/v1', '/available-slots', [
                [
                    'methods' => 'GET',
                    'callback' => [$availabilityEndpoint, 'getAvailableSlots'],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'date' => [
                            'required' => true,
                            'type' => 'string',
                            'validate_callback' => static function ($value): bool {
                                return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
                            },
                        ],
                        'party' => [
                            'required' => true,
                            'type' => 'integer',
                            'sanitize_callback' => static fn ($value): int => max(0, absint($value)),
                            'validate_callback' => static fn ($value): bool => absint($value) > 0,
                        ],
                        'meal' => [
                            'required' => true,
                            'type' => 'string',
                        ],
                    ],
                ],
            ]);
            
            // Add filter to clean response data for available-slots endpoint
            // This ensures only the expected fields are returned, removing any extra fields
            // Priority 9999 ensures this runs after all other filters
            add_filter('rest_prepare_response', function ($response, $server, $request) {
                // Check if this is the available-slots endpoint
                // The route might be '/fp-resv/v1/available-slots' or '/fp-resv/v1/available-slots/' (with trailing slash)
                $route = $request->get_route();
                if (strpos($route, '/fp-resv/v1/available-slots') === 0) {
                    if ($response instanceof \WP_REST_Response) {
                        $data = $response->get_data();
                        if (is_array($data) && isset($data['slots']) && is_array($data['slots'])) {
                            // Clean slots array - keep only time, slot_start, available
                            $cleanSlots = [];
                            foreach ($data['slots'] as $slot) {
                                if (is_array($slot)) {
                                    $cleanSlots[] = [
                                        'time' => $slot['time'] ?? '',
                                        'slot_start' => $slot['slot_start'] ?? '',
                                        'available' => $slot['available'] ?? false,
                                    ];
                                }
                            }
                            // Return ONLY slots array, remove any extra root fields (date, meal, party, etc.)
                            $response->set_data(['slots' => $cleanSlots]);
                        }
                    }
                }
                return $response;
            }, 9999, 3);
            
            // Also add filter to clean JSON output directly
            // This catches any fields added during JSON serialization
            add_filter('rest_post_dispatch', function ($result, $server, $request) {
                $route = $request->get_route();
                if (strpos($route, '/fp-resv/v1/available-slots') === 0) {
                    if ($result instanceof \WP_REST_Response) {
                        $data = $result->get_data();
                        if (is_array($data) && isset($data['slots']) && is_array($data['slots'])) {
                            // Clean slots array - keep only time, slot_start, available
                            $cleanSlots = [];
                            foreach ($data['slots'] as $slot) {
                                if (is_array($slot)) {
                                    $cleanSlots[] = [
                                        'time' => $slot['time'] ?? '',
                                        'slot_start' => $slot['slot_start'] ?? '',
                                        'available' => $slot['available'] ?? false,
                                    ];
                                }
                            }
                            // Return ONLY slots array, remove any extra root fields
                            $result->set_data(['slots' => $cleanSlots]);
                        }
                    }
                }
                return $result;
            }, 9999, 3);
            
            // Events routes
            register_rest_route('fp-resv/v1', '/events', [
                [
                    'methods' => 'GET',
                    'callback' => [$eventsEndpoint, 'list'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$eventsEndpoint, 'create'],
                    'permission_callback' => '__return_true',
                ],
            ]);
            
            register_rest_route('fp-resv/v1', '/events/(?P<id>\d+)', [
                [
                    'methods' => 'GET',
                    'callback' => [$eventsEndpoint, 'get'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [$eventsEndpoint, 'update'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [$eventsEndpoint, 'delete'],
                    'permission_callback' => '__return_true',
                ],
            ]);
            
            // Ensure Diagnostics REST is instantiated (registers its own routes)
            if ($container->has(\FP\Resv\Domain\Diagnostics\REST::class)) {
                $container->get(\FP\Resv\Domain\Diagnostics\REST::class);
            }
            
            // Ensure Reports REST is instantiated (registers its own routes)
            if ($container->has(\FP\Resv\Domain\Reports\REST::class)) {
                $container->get(\FP\Resv\Domain\Reports\REST::class);
            }
            
            // Closures routes
            register_rest_route('fp-resv/v1', '/closures', [
                [
                    'methods' => 'GET',
                    'callback' => [$closuresEndpoint, 'list'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$closuresEndpoint, 'create'],
                    'permission_callback' => '__return_true',
                ],
            ]);
            
            register_rest_route('fp-resv/v1', '/closures/(?P<id>\d+)', [
                [
                    'methods' => 'GET',
                    'callback' => [$closuresEndpoint, 'get'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [$closuresEndpoint, 'update'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [$closuresEndpoint, 'delete'],
                    'permission_callback' => '__return_true',
                ],
            ]);
        };
        
        // Register on rest_api_init hook
        $hooks->addAction('rest_api_init', $registerRoutesCallback);
        
        // Also register immediately if rest_api_init already fired
        if (did_action('rest_api_init')) {
            $registerRoutesCallback();
        }
    }
}
