<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

final class ContactBuilder
{
    private Mapper $mapper;

    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Build contact from reservation payload.
     * 
     * @param array<string, mixed> $reservation
     * @return array<string, mixed>
     */
    public function fromReservation(array $reservation): array
    {
        return $this->mapper->mapReservation([
            'email' => $reservation['email'] ?? '',
            'first_name' => $reservation['first_name'] ?? '',
            'last_name' => $reservation['last_name'] ?? '',
            'phone' => $reservation['phone'] ?? '',
            'language' => $reservation['language'] ?? '',
            'date' => $reservation['date'] ?? '',
            'time' => $reservation['time'] ?? '',
            'party' => $reservation['party'] ?? '',
            'status' => $reservation['status'] ?? '',
            'location' => $reservation['location'] ?? '',
            'manage_url' => $reservation['manage_url'] ?? '',
            'notes' => $reservation['notes'] ?? '',
            'marketing_consent' => $reservation['marketing_consent'] ?? null,
            'reservation_id' => $reservation['reservation_id'] ?? 0,
            'value' => $reservation['value'] ?? null,
            'currency' => $reservation['currency'] ?? '',
            'utm_source' => $reservation['utm_source'] ?? '',
            'utm_medium' => $reservation['utm_medium'] ?? '',
            'utm_campaign' => $reservation['utm_campaign'] ?? '',
            'gclid' => $reservation['gclid'] ?? '',
            'fbclid' => $reservation['fbclid'] ?? '',
            'msclkid' => $reservation['msclkid'] ?? '',
            'ttclid' => $reservation['ttclid'] ?? '',
        ]);
    }

    /**
     * Build contact from context (status change, etc.).
     * 
     * @param int $reservationId
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function fromContext(int $reservationId, array $context): array
    {
        $email = (string) ($context['email'] ?? '');
        
        if ($email === '' && isset($context['customer']['email'])) {
            $email = (string) $context['customer']['email'];
        }

        return $this->mapper->mapReservation([
            'email' => $email,
            'first_name' => $context['first_name'] ?? ($context['customer']['first_name'] ?? ''),
            'last_name' => $context['last_name'] ?? ($context['customer']['last_name'] ?? ''),
            'phone' => $context['phone'] ?? ($context['customer']['phone'] ?? ''),
            'language' => $context['customer']['language'] ?? ($context['customer_lang'] ?? ''),
            'date' => $context['date'] ?? '',
            'time' => isset($context['time']) ? substr((string) $context['time'], 0, 5) : '',
            'party' => $context['party'] ?? 0,
            'status' => $context['status'] ?? '',
            'location' => $context['location_id'] ?? '',
            'manage_url' => $context['manage_url'] ?? '',
            'notes' => $context['notes'] ?? '',
            'marketing_consent' => $context['marketing_consent'] ?? ($context['customer']['marketing_consent'] ?? null),
            'reservation_id' => $reservationId,
            'value' => $context['value'] ?? null,
            'currency' => $context['currency'] ?? '',
            'utm_source' => $context['utm_source'] ?? '',
            'utm_medium' => $context['utm_medium'] ?? '',
            'utm_campaign' => $context['utm_campaign'] ?? '',
            'gclid' => $context['gclid'] ?? '',
            'fbclid' => $context['fbclid'] ?? '',
            'msclkid' => $context['msclkid'] ?? '',
            'ttclid' => $context['ttclid'] ?? '',
        ]);
    }

    /**
     * Build event properties for Brevo events.
     * 
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $reservation
     * @param array<string, mixed> $meta
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

        $properties = [
            'reservation' => $reservationPayload,
            'contact' => array_filter(
                [
                    'email' => $contact['email'] ?? '',
                    'first_name' => $attributes['FIRSTNAME'] ?? '',
                    'last_name' => $attributes['LASTNAME'] ?? '',
                    'phone' => $attributes['PHONE'] ?? '',
                ],
                static fn ($value): bool => $value !== null && $value !== ''
            ),
            'attributes' => $attributes,
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
     * Extract subscription context from payload/context.
     * 
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function extractSubscriptionContext(array $data): array
    {
        return [
            'forced_language' => $data['language_forced'] ?? ($data['forced_language'] ?? ''),
            'page_language' => $data['language'] ?? ($data['page_language'] ?? ($data['customer_lang'] ?? '')),
            'phone' => $data['phone'] ?? ($data['customer']['phone'] ?? ''),
        ];
    }
}
