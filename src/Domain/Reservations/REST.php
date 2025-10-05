<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\DataLayer;
use FP\Resv\Core\Helpers;
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
use function apply_filters;
use function defined;
use function get_transient;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function set_transient;
use function strtolower;
use function wp_json_encode;
use function wp_rand;
use function wp_verify_nonce;

final class REST
{
    public function __construct(
        private readonly Availability $availability,
        private readonly Service $service
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

        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            $response = rest_ensure_response($cached);
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'Cache-Control'    => 'no-store, no-cache, must-revalidate, max-age=0',
                    'X-FP-Resv-Cache'  => 'hit',
                ]);
            }

            return $response;
        }

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
        $nonce = $request->get_param('fp_resv_nonce') ?? $request->get_param('_wpnonce');
        if (!is_string($nonce)) {
            $nonce = $request->get_header('X-WP-Nonce');
        }

        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'fp_resv_submit')) {
            return new WP_Error(
                'fp_resv_invalid_nonce',
                __('Verifica di sicurezza non superata. Riprova.', 'fp-restaurant-reservations'),
                ['status' => 403]
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
}
