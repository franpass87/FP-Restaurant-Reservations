<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\PhoneHelper;
use FP\Resv\Core\Sanitizer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use function absint;
use function array_merge;
use function current_time;
use function is_array;
use function is_string;
use function max;
use function min;
use function round;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function str_replace;
use function strtolower;

/**
 * Sanitizza il payload di prenotazione.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class ReservationPayloadSanitizer
{
    public function __construct(
        private readonly Options $options,
        private readonly Language $language
    ) {
    }

    /**
     * Sanitizza il payload di prenotazione.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array
    {
        $defaults = [
            'date'        => current_time('Y-m-d'),
            'time'        => '19:00',
            'party'       => 2,
            'first_name'  => '',
            'last_name'   => '',
            'email'       => '',
            'phone'       => '',
            'phone_country' => '',
            'notes'       => '',
            'allergies'   => '',
            'meal'        => '',
            'language'    => 'it',
            'locale'      => 'it_IT',
            'location'    => 'default',
            'currency'    => $this->resolveDefaultCurrency(),
            'utm_source'  => '',
            'utm_medium'  => '',
            'utm_campaign'=> '',
            'gclid'       => '',
            'fbclid'      => '',
            'msclkid'     => '',
            'ttclid'      => '',
            'marketing_consent' => false,
            'profiling_consent' => false,
            'policy_version'    => '',
            'consent_timestamp' => '',
            'status'      => null,
            'room_id'     => null,
            'table_id'    => null,
            'value'       => null,
            'price_per_person' => null,
            'high_chair_count' => 0,
            'wheelchair_table' => false,
            'pets'             => false,
            'allow_partial_contact' => false,
        ];

        $payload = array_merge($defaults, $payload);

        $payload['date']       = Sanitizer::sanitizeDate($payload['date']);
        $payload['time']       = Sanitizer::sanitizeTime($payload['time']);
        $payload['party']      = Sanitizer::sanitizeInteger($payload['party'], ['min' => 1]);
        
        $maxCapacity = (int) $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40');
        if ($maxCapacity > 0) {
            $payload['party'] = min($payload['party'], $maxCapacity);
        }
        
        $payload['first_name'] = sanitize_text_field((string) $payload['first_name']);
        $payload['last_name']  = sanitize_text_field((string) $payload['last_name']);
        $payload['email']      = sanitize_email((string) $payload['email']);
        $payload['phone']         = sanitize_text_field((string) $payload['phone']);
        $payload['phone_country'] = sanitize_text_field((string) $payload['phone_country']);
        $detectedLanguage         = PhoneHelper::detectLanguage($payload['phone'], $payload['phone_country']);
        $payload['notes']      = sanitize_textarea_field((string) $payload['notes']);
        $payload['allergies']  = sanitize_textarea_field((string) $payload['allergies']);
        $payload['meal']       = sanitize_text_field((string) $payload['meal']);
        $payload['language']   = sanitize_text_field((string) $payload['language']);
        $payload['locale']     = sanitize_text_field((string) $payload['locale']);
        $payload['location']   = sanitize_text_field((string) $payload['location']);
        $payload['currency']   = Sanitizer::sanitizeCurrency($payload['currency']);
        if ($payload['currency'] === 'EUR' && $this->resolveDefaultCurrency() !== 'EUR') {
            $payload['currency'] = $this->resolveDefaultCurrency();
        }
        $payload['utm_source'] = sanitize_text_field((string) $payload['utm_source']);
        $payload['utm_medium'] = sanitize_text_field((string) $payload['utm_medium']);
        $payload['utm_campaign'] = sanitize_text_field((string) $payload['utm_campaign']);
        $payload['gclid'] = sanitize_text_field((string) $payload['gclid']);
        $payload['fbclid'] = sanitize_text_field((string) $payload['fbclid']);
        $payload['msclkid'] = sanitize_text_field((string) $payload['msclkid']);
        $payload['ttclid'] = sanitize_text_field((string) $payload['ttclid']);
        $payload['status']     = $payload['status'] !== null ? sanitize_text_field((string) $payload['status']) : '';
        $payload['status']     = $payload['status'] !== '' ? strtolower($payload['status']) : null;
        $payload['marketing_consent'] = $this->toBool($payload['marketing_consent']);
        $payload['profiling_consent'] = $this->toBool($payload['profiling_consent']);
        $payload['policy_version']    = sanitize_text_field((string) $payload['policy_version']);
        $payload['consent_timestamp'] = sanitize_text_field((string) $payload['consent_timestamp']);
        
        $payload['high_chair_count'] = max(0, absint($payload['high_chair_count']));
        if ($payload['high_chair_count'] > 5) {
            $payload['high_chair_count'] = 5;
        }
        $payload['wheelchair_table'] = $this->toBool($payload['wheelchair_table']);
        $payload['pets']             = $this->toBool($payload['pets']);
        $payload['room_id']    = absint((int) $payload['room_id']);
        if ($payload['room_id'] === 0) {
            $payload['room_id'] = null;
        }
        $payload['table_id']   = absint((int) $payload['table_id']);
        if ($payload['table_id'] === 0) {
            $payload['table_id'] = null;
        }
        $payload['request_id'] = isset($payload['request_id']) && is_string($payload['request_id'])
            ? sanitize_text_field($payload['request_id'])
            : null;
        $payload['allow_partial_contact'] = $this->toBool($payload['allow_partial_contact']);

        if (is_array($payload['value'])) {
            $payload['value'] = null;
        } elseif ($payload['value'] === null || $payload['value'] === '') {
            $payload['value'] = null;
        } else {
            $rawValue = is_string($payload['value']) ? str_replace(',', '.', $payload['value']) : (string) $payload['value'];
            $value    = (float) $rawValue;
            $payload['value'] = $value > 0 ? round($value, 2) : null;
        }

        if (is_array($payload['price_per_person'])) {
            $payload['price_per_person'] = null;
        } elseif ($payload['price_per_person'] === null || $payload['price_per_person'] === '') {
            $payload['price_per_person'] = null;
        } else {
            $rawPrice = is_string($payload['price_per_person']) ? str_replace(',', '.', $payload['price_per_person']) : (string) $payload['price_per_person'];
            $price    = (float) $rawPrice;
            $payload['price_per_person'] = $price > 0 ? round($price, 2) : null;
        }

        $payload['language'] = $this->language->ensureLanguage((string) $payload['language']);
        if ($detectedLanguage !== null) {
            $payload['language'] = $detectedLanguage;
        }
        $locale = (string) $payload['locale'];
        if ($locale === '') {
            $locale = $this->language->getFallbackLocale();
        }
        $payload['locale'] = $this->language->normalizeLocale($locale);

        unset($payload['phone_country']);

        return $payload;
    }

    /**
     * Risolve la valuta di default.
     */
    private function resolveDefaultCurrency(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'default_currency' => 'EUR',
        ]);

        $currency = strtoupper((string) ($general['default_currency'] ?? 'EUR'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        return $currency;
    }

    /**
     * Converte un valore in booleano.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}















