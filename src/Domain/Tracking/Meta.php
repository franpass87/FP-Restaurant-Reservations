<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Settings\Options;
use function array_filter;
use function error_log;
use function hash;
use function is_wp_error;
use function strtolower;
use function trim;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

final class Meta
{
    private const API_VERSION = 'v18.0';

    public function __construct(private readonly Options $options)
    {
    }

    public function pixelId(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $id       = isset($settings['meta_pixel_id']) ? (string) $settings['meta_pixel_id'] : '';

        return trim($id);
    }

    public function accessToken(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $token    = isset($settings['meta_access_token']) ? (string) $settings['meta_access_token'] : '';

        return trim($token);
    }

    public function isEnabled(): bool
    {
        return $this->pixelId() !== '';
    }

    public function isServerSideEnabled(): bool
    {
        return $this->pixelId() !== '' && $this->accessToken() !== '';
    }

    public function eventPayload(string $event, float $value, string $currency, int $reservationId = 0): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $params = array_filter([
            'value'    => $value > 0 ? $value : null,
            'currency' => $currency !== '' ? $currency : null,
            'contents' => $reservationId > 0 ? [['id' => 'reservation-' . $reservationId]] : null,
        ], static fn ($item) => $item !== null);

        return [
            'name'   => $event,
            'params' => $params,
        ];
    }

    /**
     * Invia evento server-side tramite Meta Conversions API
     *
     * @param string $eventName Nome dell'evento (es. Purchase, Lead)
     * @param array<string, mixed> $customData Dati custom dell'evento (value, currency, contents, etc.)
     * @param array<string, mixed> $userData Dati utente (email, phone, fbc, fbp, client_ip_address, client_user_agent)
     * @param string $eventSourceUrl URL della pagina dove è avvenuto l'evento
     * @param string|null $eventId ID univoco per deduplicazione con eventi client-side
     * @return bool True se l'invio è riuscito
     */
    public function sendEvent(
        string $eventName,
        array $customData = [],
        array $userData = [],
        string $eventSourceUrl = '',
        ?string $eventId = null
    ): bool {
        if (!$this->isServerSideEnabled()) {
            return false;
        }

        if ($eventName === '') {
            return false;
        }

        $eventTime = time();

        // Prepara i dati utente con hashing
        $processedUserData = $this->processUserData($userData);

        $eventData = array_filter([
            'event_name' => $eventName,
            'event_time' => $eventTime,
            'event_source_url' => $eventSourceUrl !== '' ? $eventSourceUrl : null,
            'event_id' => $eventId,
            'user_data' => $processedUserData !== [] ? $processedUserData : null,
            'custom_data' => $customData !== [] ? $customData : null,
            'action_source' => 'website',
        ], static fn ($item) => $item !== null);

        $payload = [
            'data' => [$eventData],
        ];

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events?access_token=%s',
            self::API_VERSION,
            $this->pixelId(),
            urlencode($this->accessToken())
        );

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            $this->logError('Meta API error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $this->logError('Meta API returned code: ' . $code . ' - ' . $body);
            return false;
        }

        return true;
    }

    /**
     * Processa i dati utente applicando l'hashing SHA256 dove richiesto
     *
     * @param array<string, mixed> $userData
     * @return array<string, mixed>
     */
    private function processUserData(array $userData): array
    {
        $processed = [];

        // Campi che richiedono hashing
        $hashFields = ['em' => 'email', 'ph' => 'phone', 'fn' => 'first_name', 'ln' => 'last_name', 'ct' => 'city', 'st' => 'state', 'zp' => 'zip', 'country' => 'country'];

        foreach ($hashFields as $key => $dataKey) {
            if (isset($userData[$dataKey]) && $userData[$dataKey] !== '') {
                $value = strtolower(trim((string) $userData[$dataKey]));
                $processed[$key] = hash('sha256', $value);
            }
        }

        // Campi che non richiedono hashing
        $plainFields = ['fbc', 'fbp', 'client_ip_address', 'client_user_agent'];

        foreach ($plainFields as $field) {
            if (isset($userData[$field]) && $userData[$field] !== '') {
                $processed[$field] = $userData[$field];
            }
        }

        return $processed;
    }

    /**
     * Log degli errori se il debug è abilitato
     */
    private function logError(string $message): void
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        if (($settings['tracking_enable_debug'] ?? '0') === '1') {
            error_log('[FP Resv Meta] ' . $message);
        }
    }
}
