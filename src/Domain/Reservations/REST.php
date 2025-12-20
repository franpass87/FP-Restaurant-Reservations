<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\DataLayer;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Metrics;
use FP\Resv\Core\RateLimiter;
use FP\Resv\Domain\Reservations\REST\AvailabilityHandler;
use FP\Resv\Domain\Reservations\REST\ReservationHandler;
use FP\Resv\Domain\Reservations\Service;
use FP\Resv\Domain\Reservations\MealPlanService;
use FP\Resv\Domain\Reservations\AvailabilityService;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function add_action;
use function add_query_arg;
use function apply_filters;
use function defined;
use function esc_url_raw;
use function get_transient;
use function hash_hmac;
use function home_url;
use function in_array;
use function is_array;
use function is_string;
use function md5;
use function preg_match;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function serialize;
use function set_transient;
use function sprintf;
use function strtolower;
use function trailingslashit;
use function trim;
use function wp_cache_get;
use function wp_cache_set;
use function wp_json_encode;
use function wp_rand;
use function wp_salt;
use function wp_timezone;
use function wp_verify_nonce;

/**
 * Legacy REST Endpoint
 * 
 * @deprecated 0.9.0-rc11 Use Presentation\API\REST\ReservationsEndpoint instead.
 *             This class is kept for backward compatibility but should not be used in new code.
 *             New code should use the Application layer (Use Cases) via Presentation layer.
 */
final class REST
{
    public function __construct(
        private readonly Availability $availability,
        private readonly Service $service,
        private readonly Repository $repository,
        private readonly AvailabilityHandler $availabilityHandler,
        private readonly ReservationHandler $reservationHandler
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
            '/availability',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this->availabilityHandler, 'handleAvailability'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'date' => [
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => static function ($value): bool {
                            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
                        },
                    ],
                    'party' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => static fn ($value): int => max(0, absint($value)),
                        'validate_callback' => static fn ($value): bool => absint($value) > 0,
                    ],
                    'meal' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => static fn ($value): string => sanitize_text_field((string) $value),
                    ],
                    'room' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => static fn ($value): int => absint($value),
                    ],
                    'event_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => static fn ($value): int => absint($value),
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/available-days',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this->availabilityHandler, 'handleAvailableDays'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'from' => [
                        'required'          => false,
                        'type'              => 'string',
                        'validate_callback' => static function ($value): bool {
                            if (!$value) {
                                return true; // Optional
                            }
                            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
                        },
                        'sanitize_callback' => static fn ($value): string => sanitize_text_field((string) $value),
                    ],
                    'to' => [
                        'required'          => false,
                        'type'              => 'string',
                        'validate_callback' => static function ($value): bool {
                            if (!$value) {
                                return true; // Optional
                            }
                            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
                        },
                        'sanitize_callback' => static fn ($value): string => sanitize_text_field((string) $value),
                    ],
                    'meal' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => static fn ($value): string => sanitize_text_field((string) $value),
                        'validate_callback' => static function ($value): bool {
                            if (!$value) {
                                return true; // Optional
                            }
                            // Whitelist meal validi (alfanumerici + underscore/hyphen, max 50 caratteri)
                            return is_string($value) && preg_match('/^[a-z0-9_-]{1,50}$/i', $value) === 1;
                        },
                    ],
                ],
            ]
        );
        register_rest_route(
            'fp-resv/v1',
            '/meal-config',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleMealConfig'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/available-slots',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this->availabilityHandler, 'handleAvailableSlots'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'date' => [
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => static function ($value): bool {
                            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
                        },
                    ],
                    'meal' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => static fn ($value): string => sanitize_text_field((string) $value),
                        'validate_callback' => static function ($value): bool {
                            // Whitelist meal validi (alfanumerici + underscore/hyphen, max 50 caratteri)
                            return is_string($value) && preg_match('/^[a-z0-9_-]{1,50}$/i', $value) === 1;
                        },
                    ],
                    'party' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => static fn ($value): int => max(0, absint($value)),
                        'validate_callback' => static fn ($value): bool => absint($value) > 0,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/reservations',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this->reservationHandler, 'handleCreateReservation'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/nonce',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleGetNonce'],
                'permission_callback' => '__return_true',
            ]
        );
        
        // DEBUG ENDPOINTS - Solo in modalità debug per sviluppo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            register_rest_route(
                'fp-resv/v1',
                '/test',
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => function() {
                        return new WP_REST_Response([
                            'success' => true,
                            'message' => 'REST API funziona!',
                            'timestamp' => time(),
                        ], 200);
                    },
                    'permission_callback' => '__return_true',
                ]
            );
            
            register_rest_route(
                'fp-resv/v1',
                '/test-output',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => function(WP_REST_Request $request) {
                        $data = [
                            'success' => true,
                            'message' => 'Test output funziona!',
                            'timestamp' => time(),
                            'payload' => $request->get_json_params(),
                        ];
                        
                        header('Content-Type: application/json');
                        http_response_code(200);
                        echo wp_json_encode($data);
                        exit;
                    },
                    'permission_callback' => '__return_true',
                ]
            );

            register_rest_route(
                'fp-resv/v1',
                '/test-die',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => function(WP_REST_Request $request) {
                        $data = [
                            'success' => true,
                            'message' => 'Test die() funziona!',
                            'timestamp' => time(),
                            'method' => 'die()',
                        ];
                        header('Content-Type: application/json');
                        http_response_code(200);
                        echo wp_json_encode($data);
                        die();
                    },
                    'permission_callback' => '__return_true',
                ]
            );

            register_rest_route(
                'fp-resv/v1',
                '/test-raw',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => function(WP_REST_Request $request) {
                        if (!headers_sent()) {
                            header('Content-Type: application/json');
                            http_response_code(200);
                        }
                        echo '{"success":true,"message":"Raw output","timestamp":' . time() . '}';
                        die();
                    },
                    'permission_callback' => '__return_true',
                ]
            );
        }
    }


    public function handleGetNonce(WP_REST_Request $request): WP_REST_Response
    {
        $nonce = wp_create_nonce('fp_resv_submit');

        return new WP_REST_Response([
            'nonce' => $nonce,
        ], 200);
    }

    public function handleMealConfig(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Recupera la configurazione dei meal dal backend
            $options = get_option('fp_resv_general');
            $frontendMeals = '';
            
            if (is_array($options) && isset($options['frontend_meals'])) {
                $frontendMeals = $options['frontend_meals'];
            }
            
            if (empty($frontendMeals)) {
                // Se non ci sono meal configurati, restituisci configurazione di default
                $defaultMeals = [
                    [
                        'key' => 'pranzo',
                        'name' => 'Pranzo',
                        'days_of_week' => [
                            'mon' => true,
                            'tue' => true,
                            'wed' => true,
                            'thu' => true,
                            'fri' => true,
                            'sat' => true,
                            'sun' => false,
                        ],
                        'hours_definition' => [
                            'mon' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
                            'tue' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
                            'wed' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
                            'thu' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
                            'fri' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
                            'sat' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
                            'sun' => ['enabled' => false, 'start' => '12:00', 'end' => '14:30'],
                        ]
                    ],
                    [
                        'key' => 'cena',
                        'name' => 'Cena',
                        'days_of_week' => [
                            'mon' => false,
                            'tue' => true,
                            'wed' => true,
                            'thu' => true,
                            'fri' => true,
                            'sat' => true,
                            'sun' => true,
                        ],
                        'hours_definition' => [
                            'mon' => ['enabled' => false, 'start' => '19:00', 'end' => '22:30'],
                            'tue' => ['enabled' => true, 'start' => '19:00', 'end' => '22:30'],
                            'wed' => ['enabled' => true, 'start' => '19:00', 'end' => '22:30'],
                            'thu' => ['enabled' => true, 'start' => '19:00', 'end' => '22:30'],
                            'fri' => ['enabled' => true, 'start' => '19:00', 'end' => '22:30'],
                            'sat' => ['enabled' => true, 'start' => '19:00', 'end' => '22:30'],
                            'sun' => ['enabled' => true, 'start' => '19:00', 'end' => '22:30'],
                        ]
                    ]
                ];
                
                return new WP_REST_Response([
                    'meals' => $defaultMeals,
                    'source' => 'default',
                    'message' => 'Usando configurazione di default'
                ], 200);
            }
            
            // Parsa la configurazione dei meal dal backend
            $meals = \FP\Resv\Domain\Settings\MealPlan::parse($frontendMeals);
            $mealsIndexed = \FP\Resv\Domain\Settings\MealPlan::indexByKey($meals);
            
            // Converte in formato compatibile con il frontend
            $formattedMeals = [];
            foreach ($mealsIndexed as $key => $meal) {
                $formattedMeals[] = [
                    'key' => $key,
                    'name' => $meal['name'] ?? ucfirst($key),
                    'days_of_week' => $meal['days_of_week'] ?? [],
                    'hours_definition' => $meal['hours_definition'] ?? [],
                ];
            }
            
            return new WP_REST_Response([
                'meals' => $formattedMeals,
                'source' => 'backend',
                'message' => 'Configurazione recuperata dal backend'
            ], 200);
            
        } catch (\Exception $e) {
            // In caso di errore, restituisci configurazione di default
            $defaultMeals = [
                [
                    'key' => 'pranzo',
                    'name' => 'Pranzo',
                    'days_of_week' => [
                        'mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => false,
                    ]
                ],
                [
                    'key' => 'cena',
                    'name' => 'Cena',
                    'days_of_week' => [
                        'mon' => false, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true,
                    ]
                ]
            ];
            
            return new WP_REST_Response([
                'meals' => $defaultMeals,
                'source' => 'fallback',
                'message' => 'Errore nel recupero configurazione, usando default',
                'error' => $e->getMessage()
            ], 200);
        }
    }

}
