<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use function array_filter;
use function array_key_exists;
use function strtolower;
use function trim;

/**
 * Gestisce il dispatch di eventi Brevo e la costruzione delle proprietà.
 * Estratto da AutomationService.php per migliorare modularità.
 */
final class EventDispatcher
{
    public function __construct(
        private readonly Client $client,
        private readonly Repository $repository,
        private readonly Language $language,
        private readonly Options $options
    ) {
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function dispatchEvent(string $event, string $email, array $properties, int $reservationId): void
    {
        if ($email === '') {
            return;
        }

        if ($this->repository->hasSuccessfulLog($reservationId, $event)) {
            return;
        }

        $response = $this->client->sendEvent($event, [
            'email'      => strtolower(trim($email)),
            'properties' => $properties,
        ]);

        $status = $response['success'] ? 'success' : 'error';

        $this->repository->log($reservationId, $event, [
            'email'      => $email,
            'properties' => $properties,
            'response'   => $response,
        ], $status, $response['success'] ? null : ($response['message'] ?? null));
    }

    /**
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $reservation
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public function buildEventProperties(
        array $contact,
        array $attributes,
        array $reservation,
        array $meta = []
    ): array {
        $reservationPayload = array_filter(
            $reservation,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        $metaPayload = array_filter(
            $meta,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        // Formatta data e ora con il timezone corretto
        $language = (string) ($meta['language'] ?? '');
        if ($language === '') {
            $language = $this->language->getDefaultLanguage();
        }
        
        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_timezone' => 'Europe/Rome',
        ]);
        $timezone = (string) ($general['restaurant_timezone'] ?? 'Europe/Rome');
        if ($timezone === '') {
            $timezone = 'Europe/Rome';
        }

        if (!empty($reservation['date']) && !empty($reservation['time'])) {
            $reservationPayload['formatted_date'] = $this->language->formatDate(
                (string) $reservation['date'],
                $language
            );
            $reservationPayload['formatted_time'] = $this->language->formatTime(
                (string) $reservation['time'],
                $language
            );
            $reservationPayload['formatted_datetime'] = $this->language->formatDateTime(
                (string) $reservation['date'],
                (string) $reservation['time'],
                $language,
                $timezone
            );
        }

        $firstNameKey = $this->findAttributeKey($attributes, ['FIRSTNAME', 'firstname', 'first_name']);
        $lastNameKey = $this->findAttributeKey($attributes, ['LASTNAME', 'lastname', 'last_name']);
        $phoneKey = $this->findAttributeKey($attributes, ['PHONE', 'phone']);

        $properties = [
            'reservation' => $reservationPayload,
            'contact'     => array_filter(
                [
                    'email'      => $contact['email'] ?? '',
                    'first_name' => $firstNameKey ? ($attributes[$firstNameKey] ?? '') : '',
                    'last_name'  => $lastNameKey ? ($attributes[$lastNameKey] ?? '') : '',
                    'phone'      => $phoneKey ? ($attributes[$phoneKey] ?? '') : '',
                ],
                static fn ($value): bool => $value !== null && $value !== ''
            ),
            'attributes'  => $attributes,
        ];

        if ($metaPayload !== []) {
            $properties['meta'] = $metaPayload;
        }

        foreach ($attributes as $key => $value) {
            if (!array_key_exists($key, $properties)) {
                $properties[$key] = $value;
            }
        }

        return $properties;
    }

    /**
     * Trova la chiave di un attributo negli attributes array provando diverse varianti.
     * 
     * @param array<string, mixed> $attributes
     * @param array<int, string> $possibleKeys
     */
    private function findAttributeKey(array $attributes, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (array_key_exists($key, $attributes)) {
                return $key;
            }
        }
        
        return null;
    }
}
















