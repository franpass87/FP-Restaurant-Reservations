<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\REST;

use FP\Resv\Core\DataLayer;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\Logging;
use FP\Resv\Core\RateLimiter;
use FP\Resv\Domain\Reservations\Repository;
use FP\Resv\Domain\Reservations\Service;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use function __;
use function absint;
use function add_query_arg;
use function apply_filters;
use function defined;
use function esc_url_raw;
use function hash_hmac;
use function home_url;
use function in_array;
use function is_array;
use function is_string;
use function is_user_logged_in;
use function sanitize_text_field;
use function strtolower;
use function substr;
use function trailingslashit;
use function trim;
use function wp_salt;
use function is_scalar;
use function wp_verify_nonce;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Gestisce le richieste REST relative alle prenotazioni.
 * Estratto da REST.php per migliorare modularità.
 */
final class ReservationHandler
{
    public function __construct(
        private readonly Service $service,
        private readonly Repository $repository
    ) {
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

        // Verifica il nonce (accetta anche -1 per nonce "vecchi" ma ancora validi)
        $nonceValid = wp_verify_nonce($nonce, 'fp_resv_submit');
        
        // SEMPRE includi info di debug in caso di fallimento (non solo con WP_DEBUG)
        // Nonce valido può essere: 1 (corrente) o 2 (vecchio ma accettato)
        // false = completamente invalido
        if (!is_string($nonce) || $nonce === '' || $nonceValid === false) {
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
            
            return $this->createError(
                'fp_resv_invalid_nonce',
                __('Errore di sicurezza. Ricarica la pagina e riprova.', 'fp-restaurant-reservations'),
                array_merge(['status' => 403], $debugInfo)
            );
        }

        $ip = Helpers::clientIp();
        if (!RateLimiter::allow('reservation:' . $ip, 5, 300)) {
            return $this->createError(
                'fp_resv_rate_limited',
                __('Hai effettuato troppe richieste. Attendi qualche minuto e riprova.', 'fp-restaurant-reservations'),
                ['status' => 429]
            );
        }

        $honeypot = $this->param($request, ['fp_resv_hp']);
        if ($honeypot !== null && $honeypot !== '') {
            return $this->createError(
                'fp_resv_bot_detected',
                __('Non è stato possibile elaborare la richiesta.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        $captchaPassed = apply_filters('fp_resv_validate_captcha', true, $request);
        if ($captchaPassed === false) {
            return $this->createError(
                'fp_resv_captcha_failed',
                __('Verifica anti-spam non superata.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        if (!$this->consentGiven($request)) {
            return $this->createError(
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
                
                $manageUrl = $this->generateManageUrl($existing->id, $existing->email);
                
                $payload = [
                    'reservation' => [
                        'id'         => $existing->id,
                        'status'     => $existing->status,
                        'manage_url' => $manageUrl,
                    ],
                    'message'     => __('Prenotazione già registrata.', 'fp-restaurant-reservations'),
                ];

                // Ritorna risposta standard per idempotenza
                $response = new WP_REST_Response($payload, 200);
                $response->set_headers(['X-FP-Resv-Idempotent' => 'true']);
                return $response;
            }
        }

        // DEBUG: log minimale del payload, senza PII
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $jsonParams = $request->get_json_params();
            $bodyParams = $request->get_body_params();

            Logging::log('api', 'Creazione prenotazione - metadata richiesta', [
                'method' => $request->get_method(),
                'content_type' => $request->get_content_type(),
                'has_json' => is_array($jsonParams),
                'json_keys' => is_array($jsonParams) ? array_keys($jsonParams) : [],
                'has_body' => is_array($bodyParams),
                'body_keys' => is_array($bodyParams) ? array_keys($bodyParams) : [],
            ]);
        }

        $timeValue = $this->param($request, ['time', 'fp_resv_time']) ?? '';
        $slotStartValue = $this->param($request, ['fp_resv_slot_start', 'slot_start']) ?? '';
        
        $payload = [
            'date'        => $this->param($request, ['date', 'fp_resv_date']) ?? '',
            'time'        => $timeValue,
            'party'       => (int) ($this->param($request, ['party', 'fp_resv_party']) ?? 0),
            'meal'        => $this->param($request, ['meal', 'fp_resv_meal']) ?? '',
            'room'        => (int) ($this->param($request, ['room', 'fp_resv_room']) ?? 0),
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

        Logging::log('api', '>>> Chiamo service->create()');
        
        try {
            $result = $this->service->create($payload);
            
            Logging::log('api', '>>> service->create() completato', [
                'result_keys' => is_array($result) ? array_keys($result) : 'not_array',
                'result' => $result,
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            // Log l'errore di validazione
            Logging::log('api', 'Errore validazione prenotazione', [
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
                'payload_keys' => array_keys($payload),
                'date' => $payload['date'] ?? null,
                'time' => $payload['time'] ?? null,
                'party' => $payload['party'] ?? null,
                'meal' => $payload['meal'] ?? null,
            ]);
            
            return $this->createError(
                'fp_resv_invalid_reservation',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (Throwable $exception) {
            // Log l'errore generico
            Logging::log('api', 'Errore generico durante creazione prenotazione', [
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            
            return $this->createError(
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
        
        Logging::log('api', '>>> Costruisco risposta', [
            'payload_keys' => array_keys($payload),
            'has_reservation' => isset($payload['reservation']),
            'reservation_id' => $payload['reservation']['id'] ?? null,
        ]);

        Logging::log('api', '>>> Costruisco WP_REST_Response standard');
        
        $response = new WP_REST_Response($payload, 201);
        
        Logging::log('api', '>>> RETURN response standard');
        
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
        $jsonParams = $request->get_json_params();
        if (is_array($jsonParams)) {
            foreach ($keys as $key) {
                if (isset($jsonParams[$key])) {
                    $value = $jsonParams[$key];
                    if (is_string($value)) {
                        return trim($value);
                    }
                    if (is_scalar($value)) {
                        return (string) $value;
                    }
                }
            }
        }

        foreach ($keys as $key) {
            $value = $request->get_param($key);
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return sanitize_text_field($trimmed);
                }
            }
            if (is_scalar($value)) {
                return sanitize_text_field((string) $value);
            }
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

    private function createError(string $code, string $message, array $data = []): WP_Error
    {
        return new WP_Error($code, $message, $data);
    }
}

