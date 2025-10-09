<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Settings\Options;
use function array_merge;
use function error_log;
use function is_wp_error;
use function trim;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_response_code;

final class GA4
{
    private const API_ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function __construct(private readonly Options $options)
    {
    }

    public function measurementId(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $id       = isset($settings['ga4_measurement_id']) ? (string) $settings['ga4_measurement_id'] : '';

        return trim($id);
    }

    public function apiSecret(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $secret   = isset($settings['ga4_api_secret']) ? (string) $settings['ga4_api_secret'] : '';

        return trim($secret);
    }

    public function isEnabled(): bool
    {
        return $this->measurementId() !== '';
    }

    public function isServerSideEnabled(): bool
    {
        return $this->measurementId() !== '' && $this->apiSecret() !== '';
    }

    /**
     * Invia evento server-side tramite GA4 Measurement Protocol
     *
     * @param string $eventName Nome dell'evento
     * @param array<string, mixed> $params Parametri dell'evento
     * @param string $clientId Client ID (GA cookie _ga)
     * @param array<string, mixed> $userProperties Proprietà utente opzionali
     * @return bool True se l'invio è riuscito
     */
    public function sendEvent(
        string $eventName,
        array $params = [],
        string $clientId = '',
        array $userProperties = []
    ): bool {
        if (!$this->isServerSideEnabled()) {
            return false;
        }

        if ($eventName === '') {
            return false;
        }

        // Genera un client_id se non fornito
        if ($clientId === '') {
            $clientId = $this->generateClientId();
        }

        $payload = [
            'client_id' => $clientId,
            'events' => [
                array_merge(
                    ['name' => $eventName],
                    $params !== [] ? ['params' => $params] : []
                ),
            ],
        ];

        if ($userProperties !== []) {
            $payload['user_properties'] = $userProperties;
        }

        $url = self::API_ENDPOINT . '?measurement_id=' . urlencode($this->measurementId()) . '&api_secret=' . urlencode($this->apiSecret());

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            $this->logError('GA4 API error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $this->logError('GA4 API returned code: ' . $code);
            return false;
        }

        return true;
    }

    /**
     * Genera un client ID univoco
     */
    private function generateClientId(): string
    {
        return sprintf('%d.%d', time(), wp_rand(100000000, 999999999));
    }

    /**
     * Log degli errori se il debug è abilitato
     */
    private function logError(string $message): void
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        if (($settings['tracking_enable_debug'] ?? '0') === '1') {
            error_log('[FP Resv GA4] ' . $message);
        }
    }
}
