<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Calendar;

use FP\Resv\Domain\Settings\Options;
use function delete_option;
use function get_option;
use function http_build_query;
use function is_wp_error;
use function json_decode;
use function time;
use function update_option;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

/**
 * Gestisce le richieste API e l'autenticazione con Google Calendar.
 * Estratto da GoogleCalendarService per migliorare la manutenibilità.
 */
final class GoogleCalendarApiClient
{
    private const TOKEN_OPTION = 'fp_resv_google_calendar_tokens';
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Esegue una richiesta API a Google Calendar.
     *
     * @param array<string, mixed>|null $body
     * @return array{success: bool, code: int, data: mixed, message: string}
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'message' => 'missing_access_token',
            ];
        }

        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ];

        if ($body !== null) {
            $encoded = wp_json_encode($body);
            $args['body'] = $encoded === false ? '{}' : $encoded;
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        }

        $response = wp_remote_request(self::API_BASE . $path, $args);
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $bodyContent = wp_remote_retrieve_body($response);
        $data = $this->decodeResponse($bodyContent);

        $success = $code >= 200 && $code < 300;

        return [
            'success' => $success,
            'code'    => $code,
            'data'    => $data,
            'message' => $success ? '' : $this->extractErrorMessage($data, $bodyContent),
        ];
    }

    /**
     * Ottiene il token di accesso, rinnovandolo se necessario.
     */
    public function getAccessToken(): ?string
    {
        $tokens = $this->getStoredTokens();
        if ($tokens === []) {
            return null;
        }

        $expiresAt = isset($tokens['expires_at']) ? (int) $tokens['expires_at'] : 0;
        if (empty($tokens['access_token']) || $expiresAt <= time() + 60) {
            $refreshed = $this->refreshTokens($tokens);
            if ($refreshed === null) {
                return null;
            }

            $tokens = $refreshed;
        }

        return isset($tokens['access_token']) ? (string) $tokens['access_token'] : null;
    }

    /**
     * Scambia un authorization code per i token.
     */
    public function exchangeAuthorizationCode(string $code): bool
    {
        $settings = $this->googleSettings();
        $clientId = (string) ($settings['google_calendar_client_id'] ?? '');
        $clientSecret = (string) ($settings['google_calendar_client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            return false;
        }

        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'body' => [
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $this->getRedirectUri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = $this->decodeResponse($body);

        if (!is_array($data) || empty($data['access_token'])) {
            return false;
        }

        $tokens = [
            'access_token'  => (string) $data['access_token'],
            'refresh_token' => (string) ($data['refresh_token'] ?? ''),
            'expires_at'    => time() + (int) ($data['expires_in'] ?? 3600),
        ];

        $this->storeTokens($tokens);

        return true;
    }

    /**
     * Disconnette il servizio rimuovendo i token.
     */
    public function disconnect(): void
    {
        delete_option(self::TOKEN_OPTION);
    }

    /**
     * Verifica se il servizio è connesso.
     */
    public function isConnected(): bool
    {
        $tokens = $this->getStoredTokens();

        return isset($tokens['refresh_token']) && $tokens['refresh_token'] !== '';
    }

    /**
     * @param array<string, mixed> $current
     * @return array<string, mixed>|null
     */
    private function refreshTokens(array $current): ?array
    {
        if (empty($current['refresh_token'])) {
            return null;
        }

        $settings = $this->googleSettings();
        $clientId = (string) ($settings['google_calendar_client_id'] ?? '');
        $clientSecret = (string) ($settings['google_calendar_client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'body' => [
                'refresh_token' => $current['refresh_token'],
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = $this->decodeResponse($body);

        if (!is_array($data) || empty($data['access_token'])) {
            return null;
        }

        $tokens = [
            'access_token'  => (string) $data['access_token'],
            'refresh_token' => (string) ($current['refresh_token'] ?? ''),
            'expires_at'    => time() + (int) ($data['expires_in'] ?? 3600),
        ];

        $this->storeTokens($tokens);

        return $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    private function getStoredTokens(): array
    {
        $stored = get_option(self::TOKEN_OPTION, null);
        if (!is_array($stored)) {
            return [];
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function storeTokens(array $tokens): void
    {
        update_option(self::TOKEN_OPTION, $tokens, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function googleSettings(): array
    {
        return $this->options->getGroup('fp_resv_google_calendar', []);
    }

    /**
     * Ottiene l'URI di redirect per OAuth.
     */
    private function getRedirectUri(): string
    {
        return home_url('/wp-json/fp-resv/v1/google-calendar/callback');
    }

    /**
     * @return mixed
     */
    private function decodeResponse(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return $decoded !== null ? $decoded : null;
    }

    /**
     * @param mixed $data
     */
    private function extractErrorMessage(mixed $data, string $fallback): string
    {
        if (is_array($data) && isset($data['error']['message'])) {
            return (string) $data['error']['message'];
        }

        if (is_array($data) && isset($data['error'])) {
            return is_string($data['error']) ? $data['error'] : $fallback;
        }

        return $fallback;
    }
}















