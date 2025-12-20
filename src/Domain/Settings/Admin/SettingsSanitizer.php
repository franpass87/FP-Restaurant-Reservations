<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings\Admin;

use FP\Resv\Domain\Settings\PagesConfig;
use function __;
use function add_settings_error;
use function array_key_first;
use function array_keys;
use function array_unique;
use function array_values;
use function esc_url_raw;
use function explode;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_email;
use function is_scalar;
use function json_decode;
use function preg_match;
use function preg_split;
use function sanitize_email;
use function sanitize_hex_color;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sprintf;
use function str_replace;
use function strtolower;
use function strtoupper;
use function substr;
use function timezone_identifiers_list;
use function update_option;
use function wp_kses_post;
use FP\Resv\Domain\Settings\MealPlan;
use function array_fill_keys;
use function array_key_exists;
use function array_map;
use function esc_html;
use function is_string;
use function ltrim;
use function max;
use function min;
use function preg_match_all;
use function strpos;
use function trim;
use function wp_json_encode;
use function wp_strip_all_tags;
use const FILTER_FLAG_ALLOW_FRACTION;
use const FILTER_SANITIZE_NUMBER_FLOAT;
use const FILTER_VALIDATE_URL;
use const PREG_SET_ORDER;

/**
 * Gestisce la sanitizzazione delle opzioni delle impostazioni.
 * Estratto da AdminPages.php per migliorare modularità.
 */
final class SettingsSanitizer
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $pages;

    public function __construct()
    {
        $this->pages = PagesConfig::getPages();
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function sanitizePageOptions(string $pageKey, array $input): array
    {
        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return $input;
        }

        $sanitized = [];
        foreach ($page['sections'] as $section) {
            foreach ($section['fields'] as $fieldKey => $field) {
                $value = $input[$fieldKey] ?? null;
                $sanitized[$fieldKey] = $this->sanitizeField($pageKey, $fieldKey, $field, $value);
            }
        }

        if ($pageKey === 'general') {
            if (isset($sanitized['default_currency'])) {
                $sanitized['default_currency'] = strtoupper(substr((string) $sanitized['default_currency'], 0, 3));
            }
            if (!empty($sanitized['restaurant_timezone']) && !in_array($sanitized['restaurant_timezone'], timezone_identifiers_list(), true)) {
                $sanitized['restaurant_timezone'] = 'Europe/Rome';
            }
            
            // Salva l'opzione di conservazione dati in un'opzione separata per il file uninstall.php
            if (isset($sanitized['keep_data_on_uninstall'])) {
                update_option('fp_resv_keep_data_on_uninstall', $sanitized['keep_data_on_uninstall'], false);
            }
        }

        if ($pageKey === 'payments' && isset($sanitized['stripe_currency'])) {
            $sanitized['stripe_currency'] = strtoupper(substr((string) $sanitized['stripe_currency'], 0, 3));
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $field
     */
    public function sanitizeField(string $pageKey, string $fieldKey, array $field, mixed $value): mixed
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return (!empty($value) && $value !== '0') ? '1' : '0';
            case 'integer':
                $int = (int) ($value ?? 0);
                if (isset($field['min'])) {
                    $int = max((int) $field['min'], $int);
                }
                if (isset($field['max'])) {
                    $int = min((int) $field['max'], $int);
                }

                return (string) $int;
            case 'number':
                $raw = is_scalar($value) ? (string) $value : '';
                $normalized = filter_var($raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($normalized === false || $normalized === null || $normalized === '') {
                    return '';
                }

                return $normalized;
            case 'url':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }

                $sanitized = esc_url_raw($raw);
                if ($sanitized === '' || filter_var($sanitized, FILTER_VALIDATE_URL) === false) {
                    $this->addError($pageKey, $fieldKey . '_invalid_url', sprintf(
                        __('L\'URL fornito per %s non è valido.', 'fp-restaurant-reservations'),
                        $field['label']
                    ));

                    return '';
                }

                return $sanitized;
            case 'email':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }

                $sanitized = sanitize_email($raw);
                if ($sanitized === '' || !is_email($sanitized)) {
                    $this->addError($pageKey, $fieldKey . '_invalid_email', sprintf(
                        __('L\'indirizzo email per %s non è valido.', 'fp-restaurant-reservations'),
                        $field['label']
                    ));

                    return '';
                }

                return $sanitized;
            case 'email_list':
                return $this->sanitizeEmailList($pageKey, $fieldKey, $value, $field);
            case 'language_map':
                return $this->sanitizeLanguageMap($pageKey, $fieldKey, $value);
            case 'phone_prefix_map':
                return $this->sanitizePhonePrefixMap($pageKey, $fieldKey, $value);
            case 'select':
                $allowed = $field['options'] ?? [];
                $raw     = is_scalar($value) ? (string) $value : '';
                if (array_key_exists($raw, $allowed)) {
                    return $raw;
                }

                return (string) ($field['default'] ?? (array_key_first($allowed) ?? ''));
            case 'color':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }

                $sanitized = sanitize_hex_color($raw);
                if ($sanitized === null) {
                    $this->addError($pageKey, $fieldKey . '_invalid_color', sprintf(
                        __('Il colore specificato per %s non è valido.', 'fp-restaurant-reservations'),
                        $field['label']
                    ));

                    return '';
                }

                return $sanitized;
            case 'textarea':
                $raw = is_scalar($value) ? (string) $value : '';

                return sanitize_textarea_field($raw);
            case 'textarea_html':
                $raw = is_scalar($value) ? (string) $value : '';

                return wp_kses_post($raw);
            case 'password':
                $raw = is_scalar($value) ? (string) $value : '';

                return trim($raw);
            default:
                $raw = is_scalar($value) ? (string) $value : '';

                return sanitize_text_field($raw);
        }
    }

    private function sanitizeEmailList(string $pageKey, string $fieldKey, mixed $value, array $field): array
    {
        $rawList = [];
        if (is_string($value)) {
            $rawList = preg_split('/[\n,;]/', $value) ?: [];
        } elseif (is_array($value)) {
            $rawList = $value;
        }

        $valid   = [];
        $invalid = [];

        foreach ($rawList as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }

            $sanitized = sanitize_email($email);
            if ($sanitized === '' || !is_email($sanitized)) {
                $invalid[] = $email;
                continue;
            }

            $valid[] = $sanitized;
        }

        $valid = array_values(array_unique($valid));

        if ($invalid !== []) {
            $this->addError(
                $pageKey,
                $fieldKey . '_invalid_emails',
                sprintf(
                    __('Alcune email sono state ignorate perché non valide: %s', 'fp-restaurant-reservations'),
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }

        if (!empty($field['required']) && $valid === []) {
            $this->addError(
                $pageKey,
                $fieldKey . '_required',
                sprintf(
                    __('Il campo %s richiede almeno un indirizzo email valido.', 'fp-restaurant-reservations'),
                    $field['label']
                )
            );
        }

        return $valid;
    }

    private function sanitizeLanguageMap(string $pageKey, string $fieldKey, mixed $value): array
    {
        $lines = [];
        if (is_string($value)) {
            $lines = preg_split('/\n/', $value) ?: [];
        } elseif (is_array($value)) {
            foreach ($value as $lang => $url) {
                $lines[] = $lang . '=' . $url;
            }
        }

        $map     = [];
        $invalid = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                $invalid[] = $line;
                continue;
            }

            [$lang, $url] = explode('=', $line, 2);
            $lang = trim($lang);
            $url  = trim($url);

            $normalizedLang = $this->normalizeLanguageCode($lang);
            if ($normalizedLang === '') {
                $invalid[] = $line;
                continue;
            }

            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $invalid[] = $line;
                continue;
            }

            $map[$normalizedLang] = $url;
        }

        if ($invalid !== []) {
            $this->addError(
                $pageKey,
                $fieldKey . '_invalid_entries',
                sprintf(
                    __('Alcune voci sono state ignorate perché non valide: %s', 'fp-restaurant-reservations'),
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }

        return $map;
    }

    private function sanitizePhonePrefixMap(string $pageKey, string $fieldKey, mixed $value): string
    {
        $raw      = is_scalar($value) ? (string) $value : '';
        $segments = preg_split('/[\r\n,]+/', $raw) ?: [];
        $map      = [];
        $invalid  = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            if (!str_contains($segment, '=')) {
                $invalid[] = $segment;
                continue;
            }

            [$prefixRaw, $langRaw] = array_map('trim', explode('=', $segment, 2));
            if ($prefixRaw === '' || $langRaw === '') {
                $invalid[] = $segment;
                continue;
            }

            $normalizedPrefix = str_replace(' ', '', $prefixRaw);
            if (strpos($normalizedPrefix, '00') === 0) {
                $normalizedPrefix = '+' . substr($normalizedPrefix, 2);
            }
            if (strpos($normalizedPrefix, '+') !== 0) {
                $normalizedPrefix = '+' . ltrim($normalizedPrefix, '+');
            }
            if ($normalizedPrefix === '+') {
                $invalid[] = $segment;
                continue;
            }

            $languageCode = $this->normalizeLanguageCode($langRaw, true);
            if ($languageCode === '') {
                $languageCode = 'INT';
            }

            $map[$normalizedPrefix] = $languageCode;
        }

        if ($invalid !== []) {
            $definition = $this->getFieldDefinition($pageKey, $fieldKey);
            $fieldLabel = is_array($definition) && isset($definition['label']) ? (string) $definition['label'] : $fieldKey;

            $this->addError(
                $pageKey,
                $fieldKey . '_invalid_prefix_map',
                sprintf(
                    __('Alcune righe non sono state accettate per il campo %s: %s', 'fp-restaurant-reservations'),
                    $fieldLabel,
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }

        $defaultMap = ['+39' => 'IT'];
        if ($map === []) {
            $map = $defaultMap;
        }

        $encoded = wp_json_encode($map);
        if (!is_string($encoded)) {
            $encoded = wp_json_encode($defaultMap);
        }

        return (string) $encoded;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, string>
     */
    public function decodePhonePrefixMapValue(mixed $value, array $field): array
    {
        $defaultMap = ['+39' => 'IT'];
        $raw = '';
        if (is_string($value) && $value !== '') {
            $raw = $value;
        } elseif (is_string($field['default'] ?? null) && $field['default'] !== '') {
            $raw = (string) $field['default'];
        } else {
            $raw = (string) wp_json_encode($defaultMap);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaultMap;
        }

        $map = [];
        foreach ($decoded as $prefix => $language) {
            if (!is_string($prefix) || !is_string($language)) {
                continue;
            }

            $prefix = trim($prefix);
            if ($prefix === '') {
                continue;
            }

            if (strpos($prefix, '+') !== 0) {
                $prefix = '+' . ltrim($prefix, '+');
            }

            if ($prefix === '+') {
                continue;
            }

            $langCode = $this->normalizeLanguageCode($language, true);
            if ($langCode === '') {
                $langCode = 'INT';
            }

            $map[$prefix] = $langCode;
        }

        if ($map === []) {
            $map = $defaultMap;
        }

        return $map;
    }

    public function formatPhonePrefixMapValue(array $map): string
    {
        if ($map === []) {
            return '';
        }

        $pairs = [];
        foreach ($map as $prefix => $language) {
            $pairs[] = $prefix . '=' . $language;
        }

        return implode(', ', $pairs);
    }

    private function normalizeLanguageCode(string $value, bool $allowInternational = false): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return '';
        }

        if (strpos($upper, 'IT') === 0) {
            return 'IT';
        }

        if (strpos($upper, 'EN') === 0) {
            return 'EN';
        }

        if ($allowInternational && strpos($upper, 'INT') === 0) {
            return 'INT';
        }

        return '';
    }

    /**
     * @return list<string>
     */
    public function collectInvalidServiceHoursEntries(string $definition): array
    {
        $lines       = preg_split('/\n/', $definition) ?: [];
        $allowedDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $invalid     = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $entries = $this->splitServiceHoursEntries($line);

            foreach ($entries as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }

                if (!str_contains($entry, '=')) {
                    $invalid[] = $entry;
                    continue;
                }

                [$day, $ranges] = array_map('trim', explode('=', $entry, 2));
                $day            = strtolower($day);

                if (!in_array($day, $allowedDays, true)) {
                    $invalid[] = $entry;
                    continue;
                }

                $segments = preg_split('/[|,]/', $ranges) ?: [];
                if ($segments === []) {
                    $invalid[] = $entry;
                    continue;
                }

                $invalidEntry = false;
                foreach ($segments as $segment) {
                    $segment = trim($segment);
                    if ($segment === '') {
                        $invalidEntry = true;
                        break;
                    }

                    if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                        $invalidEntry = true;
                        break;
                    }

                    $startMinutes = ((int) $matches[1] * 60) + (int) $matches[2];
                    $endMinutes   = ((int) $matches[3] * 60) + (int) $matches[4];

                    if ($endMinutes <= $startMinutes) {
                        $invalidEntry = true;
                        break;
                    }
                }

                if ($invalidEntry) {
                    $invalid[] = $entry;
                }
            }
        }

        return $invalid;
    }

    public function validateMealPlanDefinition(string $definition): void
    {
        if (trim($definition) === '') {
            return;
        }

        $meals = MealPlan::parse($definition);
        if ($meals === []) {
            return;
        }

        foreach ($meals as $meal) {
            if (!is_array($meal)) {
                continue;
            }

            $hoursDefinition = isset($meal['hours_definition']) ? (string) $meal['hours_definition'] : '';
            if ($hoursDefinition === '') {
                continue;
            }

            $invalid = $this->collectInvalidServiceHoursEntries($hoursDefinition);
            if ($invalid === []) {
                continue;
            }

            $label = isset($meal['label']) ? (string) $meal['label'] : '';
            $key   = isset($meal['key']) ? sanitize_key((string) $meal['key']) : '';

            $this->addError(
                'general',
                'invalid_service_hours_meal_' . ($key !== '' ? $key : md5($hoursDefinition)),
                sprintf(
                    __('Gli orari di servizio configurati per %1$s non sono validi: %2$s', 'fp-restaurant-reservations'),
                    $label !== '' ? $label : ($key !== '' ? strtoupper($key) : __('Servizio', 'fp-restaurant-reservations')),
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }
    }

    /**
     * @return list<string>
     */
    public function splitServiceHoursEntries(string $line): array
    {
        $normalized = trim(str_replace("\xc2\xa0", ' ', $line));
        if ($normalized === '') {
            return [];
        }

        $matches = [];
        if (preg_match_all('/(?:^|\s+)([A-Za-z]{3})\s*=\s*(.+?)(?=\s*$|\s+[A-Za-z]{3}\s*=)/u', $normalized, $matches, PREG_SET_ORDER) > 0) {
            $entries = [];
            foreach ($matches as $match) {
                if (!is_array($match) || !isset($match[1], $match[2])) {
                    continue;
                }

                $entries[] = $match[1] . '=' . trim($match[2]);
            }

            if ($entries !== []) {
                return $entries;
            }
        }

        return [$normalized];
    }

    public function isValidPlaceId(string $placeId): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{10,}$/', $placeId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFieldDefinition(string $pageKey, string $fieldKey): ?array
    {
        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return null;
        }

        foreach ($page['sections'] as $section) {
            if (isset($section['fields'][$fieldKey])) {
                return $section['fields'][$fieldKey];
            }
        }

        return null;
    }

    private function addError(string $pageKey, string $code, string $message): void
    {
        $optionGroup = (string) ($this->pages[$pageKey]['option_group'] ?? 'fp_resv_' . $pageKey);
        add_settings_error($optionGroup, $code, $message);
    }
}

