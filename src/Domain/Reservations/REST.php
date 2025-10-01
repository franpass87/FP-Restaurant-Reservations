<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\DataLayer;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\RateLimiter;
use FP\Resv\Domain\Reservations\AvailabilityCache;
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
use function is_bool;
use function is_numeric;
use function is_string;
use function is_email;
use function preg_match;
use function register_rest_route;
use function rest_ensure_response;
use function max;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
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
                    'location' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => static fn ($value): string => sanitize_text_field((string) $value),
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
                'args'                => $this->reservationArgsSchema(),
            ]
        );
    }

    public function handleAvailability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimit = $this->resolveRateLimit('fp_resv_rate_limit_availability', 30, 60, $request);
        $ip = Helpers::clientIp();
        $limitResult = RateLimiter::check('availability:' . $ip, $rateLimit['limit'], $rateLimit['seconds']);
        if (!$limitResult['allowed']) {
            $retryAfter = $limitResult['retry_after'] > 0 ? $limitResult['retry_after'] : $rateLimit['seconds'];
            $payload = [
                'code'    => 'fp_resv_availability_rate_limited',
                'message' => __('Hai effettuato troppe richieste di disponibilità. Attendi qualche secondo e riprova.', 'fp-restaurant-reservations'),
                'data'    => [
                    'status'      => 429,
                    'retry_after' => $retryAfter,
                ],
            ];

            $response = new WP_REST_Response($payload, 429);
            $response->set_headers([
                'Retry-After'   => (string) max(1, $retryAfter),
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

        $location = $request->get_param('location');
        if (is_string($location) && $location !== '') {
            $criteria['location'] = sanitize_text_field($location);
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
            'location'=> $criteria['location'] ?? '',
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

        AvailabilityCache::remember($cacheKey, $result, wp_rand(30, 60));

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

        $rateLimit = $this->resolveRateLimit('fp_resv_rate_limit_reservations', 5, 300, $request);
        $ip = Helpers::clientIp();
        $limitResult = RateLimiter::check('reservation:' . $ip, $rateLimit['limit'], $rateLimit['seconds']);
        if (!$limitResult['allowed']) {
            $retryAfter = $limitResult['retry_after'] > 0 ? $limitResult['retry_after'] : $rateLimit['seconds'];

            return $this->buildReservationRateLimitResponse($retryAfter);
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
            'meal'        => $this->param($request, ['meal', 'fp_resv_meal']) ?? '',
            'first_name'  => $this->param($request, ['first_name', 'fp_resv_first_name']) ?? '',
            'last_name'   => $this->param($request, ['last_name', 'fp_resv_last_name']) ?? '',
            'email'       => $this->param($request, ['email', 'fp_resv_email']) ?? '',
            'phone'       => $this->param($request, ['phone', 'fp_resv_phone']) ?? '',
            'phone_e164'  => $this->param($request, ['phone_e164', 'fp_resv_phone_e164']) ?? '',
            'phone_country' => $this->param($request, ['phone_country', 'fp_resv_phone_cc']) ?? '',
            'phone_national'=> $this->param($request, ['phone_national', 'fp_resv_phone_local']) ?? '',
            'notes'       => $this->param($request, ['notes', 'fp_resv_notes']) ?? '',
            'allergies'   => $this->param($request, ['allergies', 'fp_resv_allergies']) ?? '',
            'language'    => $this->param($request, ['language', 'fp_resv_language']) ?? '',
            'locale'      => $this->param($request, ['locale', 'fp_resv_locale']) ?? '',
            'location'    => $this->param($request, ['location', 'fp_resv_location']) ?? '',
            'currency'    => $this->param($request, ['currency', 'fp_resv_currency']) ?? '',
            'status'      => $this->param($request, ['status', 'fp_resv_status']),
            'room_id'     => $this->param($request, ['room_id', 'fp_resv_room_id', 'fp_resv_room']),
            'table_id'    => $this->param($request, ['table_id', 'fp_resv_table_id', 'fp_resv_table']),
            'utm_source'  => $this->param($request, ['utm_source']) ?? '',
            'utm_medium'  => $this->param($request, ['utm_medium']) ?? '',
            'utm_campaign'=> $this->param($request, ['utm_campaign']) ?? '',
            'utm_content' => $this->param($request, ['utm_content']) ?? '',
            'utm_term'    => $this->param($request, ['utm_term']) ?? '',
            'gclid'       => $this->param($request, ['gclid']) ?? '',
            'fbclid'      => $this->param($request, ['fbclid']) ?? '',
            'msclkid'     => $this->param($request, ['msclkid']) ?? '',
            'ttclid'      => $this->param($request, ['ttclid']) ?? '',
            'marketing_consent' => $this->param($request, ['marketing_consent', 'fp_resv_marketing_consent']) ?? '',
            'profiling_consent' => $this->param($request, ['profiling_consent', 'fp_resv_profiling_consent']) ?? '',
            'policy_version'    => $this->param($request, ['policy_version', 'fp_resv_policy_version']) ?? '',
            'consent_timestamp' => $this->param($request, ['consent_ts', 'fp_resv_consent_ts']) ?? '',
            'value'       => $this->param($request, ['value', 'fp_resv_value']),
            'price_per_person' => $this->param($request, ['price_per_person', 'fp_resv_price_per_person']),
        ];

        $emailForLimit = sanitize_email((string) $payload['email']);
        if ($emailForLimit !== '') {
            $emailLimit = $this->resolveRateLimit('fp_resv_rate_limit_reservations_email', 5, 300, $request);
            $emailResult = RateLimiter::check(
                'reservation-email:' . md5(strtolower($emailForLimit)),
                $emailLimit['limit'],
                $emailLimit['seconds']
            );

            if (!$emailResult['allowed']) {
                $retryAfter = $emailResult['retry_after'] > 0 ? $emailResult['retry_after'] : $emailLimit['seconds'];

                return $this->buildReservationRateLimitResponse($retryAfter);
            }
        }

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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reservationArgsSchema(): array
    {
        $text = static fn ($value): string => sanitize_text_field((string) $value);
        $textarea = static fn ($value): string => sanitize_textarea_field((string) $value);
        $email = static fn ($value): string => sanitize_email((string) $value);

        $dateValidator = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            if (!is_string($value)) {
                return false;
            }

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
        };

        $timeValidator = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            if (!is_string($value)) {
                return false;
            }

            return preg_match('/^\d{2}:\d{2}$/', $value) === 1;
        };

        $emailValidator = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            if (!is_string($value)) {
                return false;
            }

            return is_email($value) !== false;
        };

        $booleanLike = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            if (is_bool($value)) {
                return true;
            }

            if (!is_string($value)) {
                return false;
            }

            return in_array(strtolower($value), ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'], true);
        };

        $positiveInt = static fn ($value): int => max(0, absint($value));

        $partyValidator = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            return absint($value) > 0;
        };

        $consentValidator = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            if (!is_string($value)) {
                return false;
            }

            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        };

        $decimalValidator = static function ($value): bool {
            if ($value === null || $value === '') {
                return true;
            }

            if (!is_string($value) && !is_numeric($value)) {
                return false;
            }

            $stringValue = (string) $value;

            return preg_match('/^-?\d+(?:[\.,]\d+)?$/', $stringValue) === 1;
        };

        return [
            'fp_resv_nonce' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            '_wpnonce' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_hp' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'date' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $dateValidator,
            ],
            'fp_resv_date' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $dateValidator,
            ],
            'time' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $timeValidator,
            ],
            'fp_resv_time' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $timeValidator,
            ],
            'party' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
                'validate_callback' => $partyValidator,
            ],
            'fp_resv_party' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
                'validate_callback' => $partyValidator,
            ],
            'meal' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_meal' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'first_name' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_first_name' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'last_name' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_last_name' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'email' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $email,
                'validate_callback' => $emailValidator,
            ],
            'fp_resv_email' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $email,
                'validate_callback' => $emailValidator,
            ],
            'phone' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'phone_e164' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'phone_country' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'phone_national' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_phone' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_phone_e164' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_phone_cc' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_phone_local' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'notes' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $textarea,
            ],
            'fp_resv_notes' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $textarea,
            ],
            'allergies' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $textarea,
            ],
            'fp_resv_allergies' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $textarea,
            ],
            'language' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_language' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'locale' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_locale' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'location' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_location' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'currency' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_currency' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'status' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_status' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'room_id' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
            ],
            'fp_resv_room_id' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
            ],
            'fp_resv_room' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
            ],
            'table_id' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
            ],
            'fp_resv_table_id' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
            ],
            'fp_resv_table' => [
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => $positiveInt,
            ],
            'utm_source' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'utm_medium' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'utm_campaign' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'utm_content' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'utm_term' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'gclid' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fbclid' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'msclkid' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'ttclid' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'marketing_consent' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $booleanLike,
            ],
            'fp_resv_marketing_consent' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $booleanLike,
            ],
            'profiling_consent' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $booleanLike,
            ],
            'fp_resv_profiling_consent' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $booleanLike,
            ],
            'policy_version' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_policy_version' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'consent_ts' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'fp_resv_consent_ts' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
            ],
            'consent' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $consentValidator,
            ],
            'fp_resv_consent' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $consentValidator,
            ],
            'value' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $decimalValidator,
            ],
            'fp_resv_value' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $decimalValidator,
            ],
            'price_per_person' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $decimalValidator,
            ],
            'fp_resv_price_per_person' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => $text,
                'validate_callback' => $decimalValidator,
            ],
        ];
    }

    /**
     * @return array{limit: int, seconds: int}
     */
    private function resolveRateLimit(string $filter, int $defaultLimit, int $defaultSeconds, WP_REST_Request $request): array
    {
        $config = apply_filters($filter, [
            'limit'   => $defaultLimit,
            'seconds' => $defaultSeconds,
        ], $request);

        $limit = $defaultLimit;
        $seconds = $defaultSeconds;

        if (is_array($config)) {
            if (isset($config['limit'])) {
                $limit = (int) $config['limit'];
            }

            if (isset($config['seconds'])) {
                $seconds = (int) $config['seconds'];
            }
        } elseif (is_numeric($config)) {
            $limit = (int) $config;
        }

        if ($limit < 1) {
            $limit = $defaultLimit;
        }

        if ($seconds < 1) {
            $seconds = $defaultSeconds;
        }

        return [
            'limit'   => $limit,
            'seconds' => $seconds,
        ];
    }

    private function buildReservationRateLimitResponse(int $retryAfter): WP_REST_Response
    {
        $retryAfter = max(1, $retryAfter);

        $payload = [
            'code'    => 'fp_resv_rate_limited',
            'message' => __('Hai effettuato troppe richieste. Attendi qualche minuto e riprova.', 'fp-restaurant-reservations'),
            'data'    => [
                'status'      => 429,
                'retry_after' => $retryAfter,
            ],
        ];

        $response = new WP_REST_Response($payload, 429);
        $response->set_headers([
            'Retry-After'   => (string) $retryAfter,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);

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
