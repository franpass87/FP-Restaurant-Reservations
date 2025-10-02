<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

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
            'FIRSTNAME'                => $reservation['first_name'] ?? '',
            'LASTNAME'                 => $reservation['last_name'] ?? '',
            'EMAIL'                    => $email,
            'PHONE'                    => $phone,
            'SMS'                      => $sms,
            'WHATSAPP'                 => $whatsapp,
            'LANG'                     => $language,
            'LINGUA'                   => $language,
            'RESERVATION_DATE'         => $date,
            'PRENOTAZIONE_DATA'        => $date,
            'RESERVATION_TIME'         => $time,
            'PRENOTAZIONE_ORARIO'      => $timeNumber,
            'RESERVATION_PARTY'        => $party,
            'PERSONE'                  => $party,
            'RESERVATION_STATUS'       => $reservation['status'] ?? '',
            'NOTE'                     => $reservation['notes'] ?? '',
            'NOTES'                    => $reservation['notes'] ?? '',
            'MARKETING_CONSENT'        => $marketing,
            'RESVID'                   => $reservationId,
            'RESERVATION_ID'           => $reservationId,
            'RESERVATION_LOCATION'     => $reservation['location'] ?? '',
            'RESERVATION_MANAGE_LINK'  => $reservation['manage_url'] ?? '',
            'RESERVATION_UTM_SOURCE'   => $reservation['utm_source'] ?? '',
            'RESERVATION_UTM_MEDIUM'   => $reservation['utm_medium'] ?? '',
            'RESERVATION_UTM_CAMPAIGN' => $reservation['utm_campaign'] ?? '',
            'GCLID'                    => $reservation['gclid'] ?? '',
            'FBCLID'                   => $reservation['fbclid'] ?? '',
            'MSCLKID'                  => $reservation['msclkid'] ?? '',
            'TTCLID'                   => $reservation['ttclid'] ?? '',
            'AMOUNT'                   => $amount,
            'VALUE'                    => $amount,
            'CURRENCY'                 => $reservation['currency'] ?? '',
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
