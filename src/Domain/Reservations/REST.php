<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\DataLayer;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\Metrics;
use FP\Resv\Core\RateLimiter;
use FP\Resv\Domain\Reservations\Service;
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
use function wp_verify_nonce;

final class REST
{
    public function __construct(
        private readonly Availability $availability,
        private readonly Service $service,
        private readonly Repository $repository
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
                'callback'            => [$this, 'handleAvailability'],
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
            '/reservations',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleCreateReservation'],
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
    }

    public function handleGetNonce(WP_REST_Request $request): WP_REST_Response
    {
        $nonce = wp_create_nonce('fp_resv_submit');
        
        return new WP_REST_Response([
            'nonce' => $nonce,
        ], 200);
    }

    public function handleAvailability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ip = Helpers::clientIp();
        if (!RateLimiter::allow('availability:' . $ip, 30, 60)) {
            $payload = [
                'code'    => 'fp_resv_availability_rate_limited',
                'message' => __('Hai effettuato troppe richieste di disponibilità. Attendi qualche secondo e riprova.', 'fp-restaurant-reservations'),
                'data'    => [
                    'status'      => 429,
                    'retry_after' => 20,
                ],
            ];

            $response = new WP_REST_Response($payload, 429);
            $response->set_headers([
                'Retry-After'   => '20',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);

            Metrics::increment('availability.rate_limited');
            return $response;
        }

        $criteria = [
            'date'  => $request->get_param('date'),
            'party' => absint($request->get_param('party')),
        ];

        $meal = $request->get_param('meal');
        if ($meal !== null && $meal !== '') {
            $criteria['meal'] = sanitize_text_field((string) $meal);
        }

        $room = $request->get_param('room');
        if ($room !== null) {
            $criteria['room'] = absint($room);
        }

        $event = $request->get_param('event_id');
        if ($event !== null) {
            $criteria['event_id'] = absint($event);
        }

        $cacheKeyPayload = [
            'date'    => $criteria['date'],
            'party'   => $criteria['party'],
            'meal'    => $criteria['meal'] ?? '',
            'room'    => $criteria['room'] ?? '',
            'event'   => $criteria['event_id'] ?? '',
        ];

        $cacheKeyBase = wp_json_encode($cacheKeyPayload);
        if (!is_string($cacheKeyBase) || $cacheKeyBase === '') {
            $cacheKeyBase = serialize($cacheKeyPayload);
        }

        $cacheKey = 'fp_resv_avail_' . md5($cacheKeyBase);

        // Try wp_cache first (in-memory, faster)
        $wpCacheKey = 'fp_avail_' . md5($cacheKeyBase);
        $wpCached = wp_cache_get($wpCacheKey, 'fp_resv_api');
        
        if ($wpCached !== false && is_array($wpCached)) {
            Metrics::increment('availability.cache_hit', 1, ['type' => 'memory']);
            $response = rest_ensure_response($wpCached);
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'Cache-Control'    => 'no-store, no-cache, must-revalidate, max-age=0',
                    'X-FP-Resv-Cache'  => 'hit-memory',
                ]);
            }
            return $response;
        }

        // Fallback to transient (database)
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            Metrics::increment('availability.cache_hit', 1, ['type' => 'transient']);
            // Populate wp_cache for next request
            wp_cache_set($wpCacheKey, $cached, 'fp_resv_api', 10);
            
            $response = rest_ensure_response($cached);
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'Cache-Control'    => 'no-store, no-cache, must-revalidate, max-age=0',
                    'X-FP-Resv-Cache'  => 'hit-transient',
                ]);
            }

            return $response;
        }

        Metrics::increment('availability.cache_miss');

        try {
            $result = $this->availability->findSlots($criteria);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_resv_invalid_availability_params',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (Throwable $exception) {
            return new WP_Error(
                'fp_resv_availability_error',
                __('Impossibile calcolare la disponibilità in questo momento.', 'fp-restaurant-reservations'),
                [
                    'status'  => 500,
                    'details' => defined('WP_DEBUG') && WP_DEBUG ? $exception->getMessage() : null,
                ]
            );
        }

        // Cache in both wp_cache (10s, memory) and transient (30-60s, DB fallback)
        wp_cache_set($wpCacheKey, $result, 'fp_resv_api', 10);
        set_transient($cacheKey, $result, wp_rand(30, 60));

        $response = rest_ensure_response($result);
        if ($response instanceof WP_REST_Response) {
            $response->set_headers([
                'Cache-Control'   => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-FP-Resv-Cache' => 'miss',
            ]);
        }

        return $response;
    }

    public function handleCreateReservation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Cerca il nonce in ordine: body JSON params, body params, poi header
        $jsonParams = $request->get_json_params();
        $nonce = null;
        
        // Prima prova a leggere dal body JSON
        if (is_array($jsonParams) && isset($jsonParams['fp_resv_nonce'])) {
            $nonce = $jsonParams['fp_resv_nonce'];
        }
        
        // Poi dai parametri normali
        if (!is_string($nonce) || $nonce === '') {
            $nonce = $request->get_param('fp_resv_nonce');
        }
        if (!is_string($nonce) || $nonce === '') {
            $nonce = $request->get_param('_wpnonce');
        }
        
        // Solo come ultimo fallback usa l'header (che potrebbe essere il nonce REST standard)
        if (!is_string($nonce) || $nonce === '') {
            $nonce = $request->get_header('X-WP-Nonce');
        }

        // Verifica il nonce
        $nonceValid = wp_verify_nonce($nonce, 'fp_resv_submit');
        
        // SEMPRE includi info di debug in caso di fallimento (non solo con WP_DEBUG)
        if (!is_string($nonce) || $nonce === '' || !$nonceValid) {
            $debugInfo = [
                'nonce_found' => is_string($nonce) && $nonce !== '',
                'nonce_valid' => $nonceValid !== false,
                'nonce_action' => 'fp_resv_submit',
                'nonce_value' => is_string($nonce) ? substr($nonce, 0, 10) . '...' : 'null',
                'from_json' => is_array($jsonParams) && isset($jsonParams['fp_resv_nonce']),
                'from_param' => $request->get_param('fp_resv_nonce') !== null,
                'from_header' => $request->get_header('X-WP-Nonce') !== null,
                'user_logged_in' => is_user_logged_in(),
            ];
            
            return new WP_Error(
                'fp_resv_invalid_nonce',
                __('Errore di sicurezza. Ricarica la pagina e riprova.', 'fp-restaurant-reservations'),
                array_merge(['status' => 403], $debugInfo)
            );
        }

        $ip = Helpers::clientIp();
        if (!RateLimiter::allow('reservation:' . $ip, 5, 300)) {
            return new WP_Error(
                'fp_resv_rate_limited',
                __('Hai effettuato troppe richieste. Attendi qualche minuto e riprova.', 'fp-restaurant-reservations'),
                ['status' => 429]
            );
        }

        $honeypot = $this->param($request, ['fp_resv_hp']);
        if ($honeypot !== null && $honeypot !== '') {
            return new WP_Error(
                'fp_resv_bot_detected',
                __('Non è stato possibile elaborare la richiesta.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        $captchaPassed = apply_filters('fp_resv_validate_captcha', true, $request);
        if ($captchaPassed === false) {
            return new WP_Error(
                'fp_resv_captcha_failed',
                __('Verifica anti-spam non superata.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        if (!$this->consentGiven($request)) {
            return new WP_Error(
                'fp_resv_missing_consent',
                __('Per confermare la prenotazione è necessario accettare il trattamento dati.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        // Idempotency: controlla se esiste già una prenotazione con questo request_id
        $requestId = $this->param($request, ['request_id', 'fp_resv_request_id']) ?? '';
        if ($requestId !== '') {
            $existing = $this->repository->findByRequestId($requestId);
            if ($existing !== null) {
                // Restituisci la prenotazione esistente invece di crearne una nuova
                Logging::log('api', 'Request duplicata rilevata, restituita prenotazione esistente', [
                    'request_id'     => $requestId,
                    'reservation_id' => $existing->id,
                ]);
                
                $manageUrl = $this->generateManageUrl($existing->id, $existing->customer->email ?? '');
                
                $payload = [
                    'reservation' => [
                        'id'         => $existing->id,
                        'status'     => $existing->status,
                        'manage_url' => $manageUrl,
                    ],
                    'message'     => __('Prenotazione già registrata.', 'fp-restaurant-reservations'),
                ];

                $response = rest_ensure_response($payload);
                if ($response instanceof WP_REST_Response) {
                    $response->set_status(200);
                    $response->set_headers([
                        'X-FP-Resv-Idempotent' => 'true',
                    ]);
                }

                return $response;
            }
        }

        $payload = [
            'date'        => $this->param($request, ['date', 'fp_resv_date']) ?? '',
            'time'        => $this->param($request, ['time', 'fp_resv_time']) ?? '',
            'party'       => (int) ($this->param($request, ['party', 'fp_resv_party']) ?? 0),
            'first_name'  => $this->param($request, ['first_name', 'fp_resv_first_name']) ?? '',
            'last_name'   => $this->param($request, ['last_name', 'fp_resv_last_name']) ?? '',
            'email'       => $this->param($request, ['email', 'fp_resv_email']) ?? '',
            'phone'       => $this->param($request, ['phone', 'fp_resv_phone']) ?? '',
            'phone_country' => $this->param($request, ['phone_country', 'phone_cc', 'fp_resv_phone_cc']) ?? '',
            'notes'       => $this->param($request, ['notes', 'fp_resv_notes']) ?? '',
            'allergies'   => $this->param($request, ['allergies', 'fp_resv_allergies']) ?? '',
            'language'    => $this->param($request, ['language', 'fp_resv_language']) ?? '',
            'locale'      => $this->param($request, ['locale', 'fp_resv_locale']) ?? '',
            'location'    => $this->param($request, ['location', 'fp_resv_location']) ?? '',
            'currency'    => $this->param($request, ['currency', 'fp_resv_currency']) ?? '',
            'utm_source'  => $this->param($request, ['utm_source']) ?? '',
            'utm_medium'  => $this->param($request, ['utm_medium']) ?? '',
            'utm_campaign'=> $this->param($request, ['utm_campaign']) ?? '',
            'marketing_consent' => $this->param($request, ['marketing_consent', 'fp_resv_marketing_consent']) ?? '',
            'profiling_consent' => $this->param($request, ['profiling_consent', 'fp_resv_profiling_consent']) ?? '',
            'policy_version'    => $this->param($request, ['policy_version', 'fp_resv_policy_version']) ?? '',
            'consent_timestamp' => $this->param($request, ['consent_ts', 'fp_resv_consent_ts']) ?? '',
            'value'       => $this->param($request, ['value', 'fp_resv_value']),
            'request_id'  => $requestId, // Salva il request_id per idempotenza
            // extras
            'high_chair_count'  => $this->param($request, ['high_chair_count', 'fp_resv_high_chair_count']) ?? '0',
            'wheelchair_table'  => $this->param($request, ['wheelchair_table', 'fp_resv_wheelchair_table']) ?? '',
            'pets'              => $this->param($request, ['pets', 'fp_resv_pets']) ?? '',
        ];

        try {
            $result = $this->service->create($payload);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return new WP_Error(
                'fp_resv_invalid_reservation',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (Throwable $exception) {
            return new WP_Error(
                'fp_resv_reservation_error',
                __('Si è verificato un errore durante la creazione della prenotazione.', 'fp-restaurant-reservations'),
                [
                    'status'  => 500,
                    'details' => defined('WP_DEBUG') && WP_DEBUG ? $exception->getMessage() : null,
                ]
            );
        }

        $payload = [
            'reservation' => $result,
            'message'     => __('Prenotazione inviata con successo.', 'fp-restaurant-reservations'),
        ];

        $tracking = DataLayer::consume();
        if ($tracking !== []) {
            $payload['tracking'] = $tracking;
        }

        $response = rest_ensure_response($payload);

        if ($response instanceof WP_REST_Response) {
            $response->set_status(201);
        }

        return $response;
    }

    private function consentGiven(WP_REST_Request $request): bool
    {
        $value = $this->param($request, ['consent', 'fp_resv_consent']);
        if ($value === null) {
            return false;
        }

        $value = strtolower($value);

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<int, string> $keys
     */
    private function param(WP_REST_Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->get_param($key);
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $value = sanitize_text_field((string) $value);
            if ($value === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function generateManageUrl(int $reservationId, string $email): string
    {
        $base = trailingslashit(apply_filters('fp_resv_manage_base_url', home_url('/')));
        $token = hash_hmac('sha256', sprintf('%d|%s', $reservationId, strtolower(trim($email))), wp_salt('fp_resv_manage'));

        return esc_url_raw(add_query_arg([
            'fp_resv_manage' => $reservationId,
            'fp_resv_token'  => $token,
        ], $base));
    }
}
