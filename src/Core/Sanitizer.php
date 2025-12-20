<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function absint;
use function filter_var;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_string;
use function max;
use function min;
use function preg_match;
use function sanitize_email;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function str_replace;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use const FILTER_SANITIZE_NUMBER_FLOAT;
use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;
use const FILTER_FLAG_ALLOW_FRACTION;

/**
 * Utility centralizzata per sanitizzazione di dati.
 * Riduce duplicazione e fornisce API consistente per sanitizzazione.
 */
final class Sanitizer
{
    /**
     * Sanitizza un campo in base al tipo specificato.
     *
     * @param mixed $value Valore da sanitizzare
     * @param string $type Tipo di campo (text, email, url, integer, number, etc.)
     * @param array<string, mixed> $options Opzioni aggiuntive per la sanitizzazione
     * @return mixed Valore sanitizzato
     */
    public static function sanitizeField(mixed $value, string $type, array $options = []): mixed
    {
        if ($value === null) {
            return $options['default'] ?? null;
        }

        return match ($type) {
            'text' => self::sanitizeText($value),
            'email' => self::sanitizeEmail($value),
            'url' => self::sanitizeUrl($value),
            'integer' => self::sanitizeInteger($value, $options),
            'number', 'float' => self::sanitizeNumber($value, $options),
            'textarea' => self::sanitizeTextarea($value),
            'key' => self::sanitizeKey($value),
            'color' => self::sanitizeColor($value),
            'phone' => self::sanitizePhone($value),
            'currency' => self::sanitizeCurrency($value),
            'timezone' => self::sanitizeTimezone($value),
            'checkbox', 'bool' => self::sanitizeBool($value),
            default => self::sanitizeText($value),
        };
    }

    /**
     * Sanitizza un intero payload in base a regole specificate.
     *
     * @param array<string, mixed> $payload Payload da sanitizzare
     * @param array<string, array<string, mixed>> $rules Regole di sanitizzazione per ogni campo
     * @return array<string, mixed> Payload sanitizzato
     */
    public static function sanitizePayload(array $payload, array $rules): array
    {
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            $type = is_string($rule) ? $rule : ($rule['type'] ?? 'text');
            $options = is_array($rule) ? $rule : [];
            $value = $payload[$field] ?? null;

            $sanitized[$field] = self::sanitizeField($value, $type, $options);
        }

        return $sanitized;
    }

    /**
     * Sanitizza un campo di testo.
     */
    public static function sanitizeText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Sanitizza un indirizzo email.
     */
    public static function sanitizeEmail(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $email = trim((string) $value);
        if ($email === '') {
            return '';
        }

        return sanitize_email($email);
    }

    /**
     * Valida e sanitizza un indirizzo email.
     *
     * @return array{valid: bool, email: string, error: string|null}
     */
    public static function validateEmail(mixed $value): array
    {
        $email = self::sanitizeEmail($value);

        if ($email === '') {
            return [
                'valid' => false,
                'email' => '',
                'error' => __('L\'email è obbligatoria.', 'fp-restaurant-reservations'),
            ];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'valid' => false,
                'email' => $email,
                'error' => __('L\'email non è valida.', 'fp-restaurant-reservations'),
            ];
        }

        return [
            'valid' => true,
            'email' => $email,
            'error' => null,
        ];
    }

    /**
     * Sanitizza un URL.
     */
    public static function sanitizeUrl(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }

        $sanitized = esc_url_raw($url);
        if ($sanitized === '' || filter_var($sanitized, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitizza un numero intero con opzioni min/max.
     *
     * @param array<string, mixed> $options Opzioni: min, max, default
     */
    public static function sanitizeInteger(mixed $value, array $options = []): int
    {
        $int = absint($value ?? 0);

        if (isset($options['min'])) {
            $int = max((int) $options['min'], $int);
        }

        if (isset($options['max'])) {
            $int = min((int) $options['max'], $int);
        }

        return $int;
    }

    /**
     * Sanitizza un numero decimale.
     *
     * @param array<string, mixed> $options Opzioni: min, max, default, precision
     */
    public static function sanitizeNumber(mixed $value, array $options = []): float
    {
        if (!is_scalar($value)) {
            return (float) ($options['default'] ?? 0);
        }

        $raw = str_replace(',', '.', (string) $value);
        $normalized = filter_var($raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        if ($normalized === false || $normalized === null || $normalized === '') {
            return (float) ($options['default'] ?? 0);
        }

        $number = (float) $normalized;
        $precision = isset($options['precision']) ? (int) $options['precision'] : 2;

        if (isset($options['min'])) {
            $number = max((float) $options['min'], $number);
        }

        if (isset($options['max'])) {
            $number = min((float) $options['max'], $number);
        }

        return round($number, $precision);
    }

    /**
     * Sanitizza un campo textarea.
     */
    public static function sanitizeTextarea(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_textarea_field((string) $value);
    }

    /**
     * Sanitizza una chiave (per slug, key, etc.).
     */
    public static function sanitizeKey(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_key((string) $value);
    }

    /**
     * Sanitizza un colore hex.
     */
    public static function sanitizeColor(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $color = trim((string) $value);
        if ($color === '') {
            return '';
        }

        $sanitized = sanitize_hex_color($color);

        return $sanitized ?? '';
    }

    /**
     * Sanitizza un numero di telefono.
     */
    public static function sanitizePhone(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        // Rimuove caratteri non numerici tranne +, spazi e trattini
        $phone = preg_replace('/[^\d+\s-]/', '', (string) $value);

        return trim($phone);
    }

    /**
     * Sanitizza un codice valuta (3 lettere maiuscole).
     */
    public static function sanitizeCurrency(mixed $value): string
    {
        if (!is_scalar($value)) {
            return 'EUR';
        }

        $currency = strtoupper(trim((string) $value));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return 'EUR';
        }

        return $currency;
    }

    /**
     * Sanitizza un timezone identifier.
     */
    public static function sanitizeTimezone(mixed $value): string
    {
        if (!is_scalar($value)) {
            return 'Europe/Rome';
        }

        $timezone = trim((string) $value);
        if ($timezone === '') {
            return 'Europe/Rome';
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            return 'Europe/Rome';
        }

        return $timezone;
    }

    /**
     * Sanitizza un valore booleano.
     */
    public static function sanitizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Sanitizza un valore booleano restituendo stringa '1' o '0'.
     */
    public static function sanitizeBoolString(mixed $value): string
    {
        return self::sanitizeBool($value) ? '1' : '0';
    }

    /**
     * Sanitizza un orario nel formato HH:MM.
     */
    public static function sanitizeTime(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '19:00';
        }

        $time = trim((string) $value);
        if (!preg_match('/^\d{2}:\d{2}/', $time)) {
            return '19:00';
        }

        return substr($time, 0, 5);
    }

    /**
     * Sanitizza una data nel formato Y-m-d.
     */
    public static function sanitizeDate(mixed $value): string
    {
        if (!is_scalar($value)) {
            return current_time('Y-m-d');
        }

        $date = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return current_time('Y-m-d');
        }

        return $date;
    }
}
















