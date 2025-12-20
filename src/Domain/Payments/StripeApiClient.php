<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use FP\Resv\Domain\Settings\Options;
use RuntimeException;
use WP_Error;
use function apply_filters;
use function home_url;
use function is_array;
use function is_string;
use function json_decode;
use function rawurlencode;
use function sprintf;
use function strtolower;
use function wp_json_encode;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_response_message;

/**
 * Gestisce le richieste API a Stripe.
 * Estratto da StripeService per migliorare la manutenibilità.
 */
final class StripeApiClient
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Esegue una richiesta API a Stripe.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $body = []): array
    {
        $secret = $this->secretKey();
        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        $headers = [
            'Authorization'  => 'Bearer ' . $secret,
            'Stripe-Version' => '2022-11-15',
        ];

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];

        if ($body !== []) {
            $args['body'] = $this->encodeBody($body);
        }

        $response = wp_remote_request(self::API_BASE . $path, $args);
        if ($response instanceof WP_Error) {
            throw new RuntimeException('Stripe request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Stripe API returned an invalid response.');
        }

        if ($code >= 400) {
            $message = $data['error']['message'] ?? wp_remote_retrieve_response_message($response);
            throw new RuntimeException(sprintf('Stripe API error (%d): %s', $code, (string) $message));
        }

        return $data;
    }

    /**
     * Ottiene la chiave segreta Stripe.
     */
    public function secretKey(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', []);
        $mode     = $this->mode();
        $key      = $mode === 'live' ? 'stripe_secret_key_live' : 'stripe_secret_key_test';

        return (string) ($settings[$key] ?? '');
    }

    /**
     * Ottiene la modalità Stripe (test/live).
     */
    public function mode(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_mode' => 'test',
        ]);

        $mode = sanitize_text_field((string) ($settings['stripe_mode'] ?? 'test'));
        if (!in_array($mode, ['test', 'live'], true)) {
            $mode = 'test';
        }

        return $mode;
    }

    /**
     * Codifica il body della richiesta in formato form-urlencoded.
     *
     * @param array<string, mixed> $body
     */
    private function encodeBody(array $body): string
    {
        $fields = [];
        foreach ($body as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $fields[] = rawurlencode($key . '[' . $subKey . ']') . '=' . rawurlencode((string) $subValue);
                }
            } else {
                $fields[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            }
        }

        return implode('&', $fields);
    }
}















