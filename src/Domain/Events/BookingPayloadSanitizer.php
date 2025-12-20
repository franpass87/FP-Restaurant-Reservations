<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use function absint;
use function array_merge;
use function max;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;

/**
 * Sanitizza il payload di prenotazione evento.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class BookingPayloadSanitizer
{
    /**
     * Sanitizza il payload di prenotazione.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array
    {
        $defaults = [
            'first_name'   => '',
            'last_name'    => '',
            'email'        => '',
            'phone'        => '',
            'notes'        => '',
            'quantity'     => 1,
            'category'     => '',
            'language'     => 'it',
            'locale'       => 'it_IT',
            'location'     => 'event',
            'currency'     => 'EUR',
            'utm_source'   => '',
            'utm_medium'   => '',
            'utm_campaign' => '',
        ];

        $payload = array_merge($defaults, $payload);

        $payload['first_name'] = sanitize_text_field((string) $payload['first_name']);
        $payload['last_name']  = sanitize_text_field((string) $payload['last_name']);
        $payload['email']      = sanitize_email((string) $payload['email']);
        $payload['phone']      = sanitize_text_field((string) $payload['phone']);
        $payload['notes']      = sanitize_textarea_field((string) $payload['notes']);
        $payload['quantity']   = max(1, absint($payload['quantity']));
        $payload['category']   = sanitize_text_field((string) $payload['category']);
        $payload['language']   = sanitize_text_field((string) $payload['language']);
        $payload['locale']     = sanitize_text_field((string) $payload['locale']);
        $payload['location']   = sanitize_text_field((string) $payload['location']);
        $payload['currency']   = sanitize_text_field((string) $payload['currency']);
        $payload['utm_source'] = sanitize_text_field((string) $payload['utm_source']);
        $payload['utm_medium'] = sanitize_text_field((string) $payload['utm_medium']);
        $payload['utm_campaign'] = sanitize_text_field((string) $payload['utm_campaign']);

        return $payload;
    }
}















