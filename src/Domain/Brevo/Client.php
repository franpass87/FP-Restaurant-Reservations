<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use FP\Resv\Domain\Settings\Options;
use function is_wp_error;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

final class Client
{
    public function __construct(private readonly Options $options)
    {
    }

    public function isConnected(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function upsertContact(array $payload): array
    {
        $request = [
            'method'  => 'POST',
            'headers' => [
                'api-key'      => $this->apiKey(),
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ],
            'timeout' => 10,
            'body'    => wp_json_encode(array_merge(['updateEnabled' => true], $payload)),
        ];

        $response = wp_remote_post('https://api.brevo.com/v3/contacts', $request);
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'code'    => 0,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $code >= 200 && $code < 300,
            'message' => (string) wp_remote_retrieve_body($response),
            'code'    => $code,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function sendEvent(string $event, array $payload): array
    {
        $body = wp_json_encode(array_merge(['event' => $event], $payload));
        $response = wp_remote_post(
            'https://in-automate.brevo.com/api/v2/trackEvent',
            [
                'method'  => 'POST',
                'headers' => [
                    'api-key'      => $this->apiKey(),
                    'accept'       => 'application/json',
                    'content-type' => 'application/json',
                ],
                'timeout' => 10,
                'body'    => $body,
            ]
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'code'    => 0,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $code >= 200 && $code < 300,
            'message' => (string) wp_remote_retrieve_body($response),
            'code'    => $code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brevoOptions(): array
    {
        return $this->options->getGroup('fp_resv_brevo', []);
    }

    private function apiKey(): string
    {
        $options = $this->brevoOptions();

        return trim((string) ($options['brevo_api_key'] ?? ''));
    }
}
