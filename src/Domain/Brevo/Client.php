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
     * @param array<string, mixed> $payload Expected format: ['email' => '...', 'properties' => [...]]
     *
     * @return array<string, mixed>
     */
    public function sendEvent(string $event, array $payload): array
    {
        // Estrai email e properties dal payload
        $email = (string) ($payload['email'] ?? '');
        $properties = $payload['properties'] ?? [];

        // Separa le proprietà del contatto dalle proprietà dell'evento
        $contactProperties = [];
        $eventProperties = [];

        // Se properties contiene 'attributes', quelli sono le proprietà del contatto
        if (isset($properties['attributes']) && is_array($properties['attributes'])) {
            $contactProperties = $properties['attributes'];
        }

        // Le altre proprietà vanno in event_properties
        foreach ($properties as $key => $value) {
            if ($key !== 'attributes') {
                $eventProperties[$key] = $value;
            }
        }

        // Costruisci il payload nel nuovo formato Brevo /v3/events
        $brevoPayload = [
            'event_name' => $event,
            'identifiers' => [
                'email_id' => $email,
            ],
        ];

        // Aggiungi contact_properties se presenti
        if (!empty($contactProperties)) {
            $brevoPayload['contact_properties'] = $contactProperties;
        }

        // Aggiungi event_properties se presenti
        if (!empty($eventProperties)) {
            $brevoPayload['event_properties'] = $eventProperties;
        }

        $response = wp_remote_post(
            'https://api.brevo.com/v3/events',
            [
                'method'  => 'POST',
                'headers' => [
                    'api-key'      => $this->apiKey(),
                    'accept'       => 'application/json',
                    'content-type' => 'application/json',
                ],
                'timeout' => 10,
                'body'    => wp_json_encode($brevoPayload),
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
