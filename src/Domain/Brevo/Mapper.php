<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use FP\Resv\Domain\Settings\Options;
use function array_filter;
use function ctype_digit;
use function in_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function round;
use function sprintf;
use function strtolower;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

final class Mapper
{
    private const DEFAULT_ATTRIBUTES = [
        'firstname'                => 'FIRSTNAME',
        'lastname'                 => 'LASTNAME',
        'email'                    => 'EMAIL',
        'phone'                    => 'PHONE',
        'sms'                      => 'SMS',
        'whatsapp'                 => 'WHATSAPP',
        'lang'                     => 'LANG',
        'lingua'                   => 'LINGUA',
        'reservation_date'         => 'RESERVATION_DATE',
        'prenotazione_data'        => 'PRENOTAZIONE_DATA',
        'reservation_time'         => 'RESERVATION_TIME',
        'prenotazione_orario'      => 'PRENOTAZIONE_ORARIO',
        'reservation_party'        => 'RESERVATION_PARTY',
        'persone'                  => 'PERSONE',
        'reservation_meal'         => 'RESERVATION_MEAL',
        'servizio'                 => 'SERVIZIO',
        'reservation_status'       => 'RESERVATION_STATUS',
        'note'                     => 'NOTE',
        'notes'                    => 'NOTES',
        'marketing_consent'        => 'MARKETING_CONSENT',
        'resvid'                   => 'RESVID',
        'reservation_id'           => 'RESERVATION_ID',
        'reservation_location'     => 'RESERVATION_LOCATION',
        'reservation_manage_link'  => 'RESERVATION_MANAGE_LINK',
        'utm_source'               => 'RESERVATION_UTM_SOURCE',
        'utm_medium'               => 'RESERVATION_UTM_MEDIUM',
        'utm_campaign'             => 'RESERVATION_UTM_CAMPAIGN',
        'gclid'                    => 'GCLID',
        'fbclid'                   => 'FBCLID',
        'msclkid'                  => 'MSCLKID',
        'ttclid'                   => 'TTCLID',
        'amount'                   => 'AMOUNT',
        'value'                    => 'VALUE',
        'currency'                 => 'CURRENCY',
    ];

    public function __construct(private readonly Options $options)
    {
    }

    /**
     * Recupera il nome dell'attributo Brevo dalla configurazione.
     */
    private function getAttributeName(string $key): string
    {
        $options = $this->options->getGroup('fp_resv_brevo', []);
        $configKey = 'brevo_attr_' . $key;
        
        return trim((string) ($options[$configKey] ?? self::DEFAULT_ATTRIBUTES[$key] ?? strtoupper($key)));
    }

    /**
     * @param array<string, mixed> $reservation
     *
     * @return array<string, mixed>
     */
    public function mapReservation(array $reservation): array
    {
        $email = strtolower(trim((string) ($reservation['email'] ?? '')));
        $attributes = $this->reservationAttributes(array_merge($reservation, ['email' => $email]));

        return [
            'email'      => $email,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, mixed> $reservation
     *
     * @return array<string, mixed>
     */
    public function reservationAttributes(array $reservation): array
    {
        $email      = strtolower(trim((string) ($reservation['email'] ?? '')));
        $language   = (string) ($reservation['language'] ?? '');
        $date       = $this->normalizeDate($reservation['date'] ?? '');
        $phone      = $this->normalizePhone($reservation['phone'] ?? '');
        $sms        = $this->normalizePhone($reservation['sms'] ?? $phone);
        $whatsapp   = $this->normalizePhone($reservation['whatsapp'] ?? $phone);
        $time       = $this->normalizeTime($reservation['time'] ?? '');
        $timeNumber = $this->normalizeTimeNumber($reservation['time'] ?? '');
        $party      = $this->normalizeInteger($reservation['party'] ?? null);
        $reservationId = $this->normalizeInteger($reservation['reservation_id'] ?? ($reservation['id'] ?? null));
        $amount     = $this->normalizeNumber($reservation['value'] ?? null);
        $marketing  = $this->normalizeBoolean($reservation['marketing_consent'] ?? null);
        if ($marketing === null) {
            $marketing = false;
        }

        $attributes = [
            $this->getAttributeName('firstname')                => $reservation['first_name'] ?? '',
            $this->getAttributeName('lastname')                 => $reservation['last_name'] ?? '',
            $this->getAttributeName('email')                    => $email,
            $this->getAttributeName('phone')                    => $phone,
            $this->getAttributeName('sms')                      => $sms,
            $this->getAttributeName('whatsapp')                 => $whatsapp,
            $this->getAttributeName('lang')                     => $language,
            $this->getAttributeName('lingua')                   => $language,
            $this->getAttributeName('reservation_date')         => $date,
            $this->getAttributeName('prenotazione_data')        => $date,
            $this->getAttributeName('reservation_time')         => $time,
            $this->getAttributeName('prenotazione_orario')      => $timeNumber,
            $this->getAttributeName('reservation_party')        => $party,
            $this->getAttributeName('persone')                  => $party,
            $this->getAttributeName('reservation_meal')         => $reservation['meal'] ?? '',
            $this->getAttributeName('servizio')                 => $reservation['meal'] ?? '',
            $this->getAttributeName('reservation_status')       => $reservation['status'] ?? '',
            $this->getAttributeName('note')                     => $reservation['notes'] ?? '',
            $this->getAttributeName('notes')                    => $reservation['notes'] ?? '',
            $this->getAttributeName('marketing_consent')        => $marketing,
            $this->getAttributeName('resvid')                   => $reservationId,
            $this->getAttributeName('reservation_id')           => $reservationId,
            $this->getAttributeName('reservation_location')     => $reservation['location'] ?? '',
            $this->getAttributeName('reservation_manage_link')  => $reservation['manage_url'] ?? '',
            $this->getAttributeName('utm_source')               => $reservation['utm_source'] ?? '',
            $this->getAttributeName('utm_medium')               => $reservation['utm_medium'] ?? '',
            $this->getAttributeName('utm_campaign')             => $reservation['utm_campaign'] ?? '',
            $this->getAttributeName('gclid')                    => $reservation['gclid'] ?? '',
            $this->getAttributeName('fbclid')                   => $reservation['fbclid'] ?? '',
            $this->getAttributeName('msclkid')                  => $reservation['msclkid'] ?? '',
            $this->getAttributeName('ttclid')                   => $reservation['ttclid'] ?? '',
            $this->getAttributeName('amount')                   => $amount,
            $this->getAttributeName('value')                    => $amount,
            $this->getAttributeName('currency')                 => $reservation['currency'] ?? '',
        ];

        return array_filter(
            $attributes,
            static fn ($value): bool => $value !== null && $value !== ''
        );
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (is_numeric($trimmed)) {
                return (int) $trimmed;
            }
        }

        return null;
    }

    private function normalizeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));
            if ($normalized === '') {
                return null;
            }

            if (is_numeric($normalized)) {
                return round((float) $normalized, 2);
            }
        }

        return null;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    private function normalizeTime(mixed $value): string
    {
        $time = trim((string) $value);
        if ($time === '') {
            return '';
        }

        return substr($time, 0, 5);
    }

    private function normalizeTimeNumber(mixed $value): ?int
    {
        $time = trim((string) $value);
        if ($time === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', substr($time, 0, 5));
        if ($digits === null || $digits === '') {
            return null;
        }

        if (ctype_digit($digits)) {
            return (int) $digits;
        }

        return null;
    }

    private function normalizeDate(mixed $value): string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $matches) === 1) {
            return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $date, $matches) === 1) {
            return sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);
        }

        return substr($date, 0, 10);
    }

    private function normalizePhone(mixed $value): string
    {
        $phone = trim((string) $value);
        if ($phone === '') {
            return '';
        }

        $clean = preg_replace('/[^+0-9]/', '', $phone);
        if ($clean === null || $clean === '') {
            return '';
        }

        if (str_starts_with($clean, '00')) {
            return '+' . substr($clean, 2);
        }

        if ($clean[0] !== '+') {
            return $clean;
        }

        return $clean;
    }
}
