<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
use function preg_split;
use function sort;
use function str_replace;
use function str_starts_with;
use function strnatcasecmp;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use function uasort;
use function usort;

/**
 * Gestisce il processing dei prefissi telefonici per il form.
 * Estratto da FormContext.php per migliorare modularità.
 */
final class PhonePrefixProcessor
{
    /**
     * @param mixed $raw
     *
     * @return array<string, string>
     */
    public function parsePhonePrefixMap(mixed $raw): array
    {
        // Se è una stringa JSON, decodificala
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedPrefix = $this->normalizePhonePrefix($key);
            if ($normalizedPrefix === '') {
                continue;
            }

            $languageCode = $this->normalizePhoneLanguage(is_string($value) ? $value : '');
            $map[$normalizedPrefix] = $languageCode;
        }

        return $map;
    }

    /**
     * Integra i prefissi di default con le personalizzazioni custom.
     *
     * @param array<int, array<string, string>> $defaults
     * @param array<string, string> $customMap
     *
     * @return array<int, array<string, string>>
     */
    public function mergePhonePrefixes(array $defaults, array $customMap): array
    {
        if ($customMap === []) {
            return $defaults;
        }

        $merged = [];

        foreach ($defaults as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $prefix = isset($entry['prefix']) ? (string) $entry['prefix'] : '';
            $normalizedPrefix = $this->normalizePhonePrefix($prefix);

            if ($normalizedPrefix === '' || !isset($entry['value']) || !isset($entry['label'])) {
                continue;
            }

            // Sovrascrivi la lingua se presente nella mappa custom
            $language = $customMap[$normalizedPrefix] ?? ($entry['language'] ?? 'INT');

            $merged[] = [
                'prefix'   => $normalizedPrefix,
                'value'    => (string) $entry['value'],
                'language' => $language,
                'label'    => (string) $entry['label'],
            ];
        }

        return $merged;
    }

    /**
     * @param array<int, array<string, string>> $prefixes
     *
     * @return array<int, array<string, string>>
     */
    public function condensePhonePrefixes(array $prefixes): array
    {
        $groups = [];

        foreach ($prefixes as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rawPrefix = isset($entry['prefix']) ? (string) $entry['prefix'] : '';
            $prefix    = $this->normalizePhonePrefix($rawPrefix);
            if ($prefix === '') {
                continue;
            }

            $digits = preg_replace('/[^0-9]/', '', substr($prefix, 1));
            if (!is_string($digits) || $digits === '') {
                continue;
            }

            $language = isset($entry['language']) ? (string) $entry['language'] : 'INT';
            $label    = isset($entry['label']) ? (string) $entry['label'] : $prefix;

            $normalizedLabel = str_replace("\u{00A0}", ' ', $label);
            $name            = trim($normalizedLabel);
            $parts           = preg_split('/\s*·\s*/u', $normalizedLabel, 2);
            if (is_array($parts) && count($parts) === 2) {
                $name = trim(str_replace("\u{00A0}", ' ', $parts[1]));
            }

            if (!isset($groups[$digits])) {
                $groups[$digits] = [
                    'prefix'    => $prefix,
                    'value'     => $digits,
                    'language'  => $language,
                    'countries' => [],
                ];
            }

            if ($name !== '') {
                $groups[$digits]['countries'][$name] = true;
            }
        }

        if ($groups === []) {
            return [];
        }

        // Deduplica i paesi che compaiono con prefissi diversi: mantiene
        // la prima occorrenza e scarta le successive per evitare ripetizioni.
        // Si preferiscono gruppi con prefisso più corto (es. +1 rispetto a +1869),
        // in caso di pari lunghezza si usa l'ordinamento numerico del prefisso.

        // Ordina i gruppi per lunghezza prefisso e poi per valore numerico
        uasort(
            $groups,
            static function (array $a, array $b): int {
                $lenA = strlen((string) ($a['value'] ?? ''));
                $lenB = strlen((string) ($b['value'] ?? ''));
                if ($lenA !== $lenB) {
                    return $lenA <=> $lenB;
                }

                $numA = (int) ($a['value'] ?? 0);
                $numB = (int) ($b['value'] ?? 0);

                return $numA <=> $numB;
            }
        );

        $usedCountries = [];
        $options = [];

        foreach ($groups as $group) {
            $countries = array_keys($group['countries']);
            sort($countries, SORT_NATURAL | SORT_FLAG_CASE);

            // Filtra i paesi già utilizzati da un altro gruppo
            $countries = array_values(array_filter(
                $countries,
                static function (string $name) use (&$usedCountries): bool {
                    if ($name === '') {
                        return false;
                    }
                    if (isset($usedCountries[$name])) {
                        return false;
                    }
                    $usedCountries[$name] = true;
                    return true;
                }
            ));

            if ($countries === []) {
                // Tutti i paesi di questo gruppo erano duplicati di altri prefissi
                continue;
            }

            $label = $group['prefix'];
            if ($countries !== []) {
                $label .= ' · ' . implode(', ', $countries);
            }

            $options[] = [
                'prefix'   => $group['prefix'],
                'value'    => $group['value'],
                'language' => $group['language'],
                'label'    => $label,
            ];
        }

        usort(
            $options,
            static function (array $first, array $second): int {
                $firstLabel  = isset($first['label']) ? (string) $first['label'] : '';
                $secondLabel = isset($second['label']) ? (string) $second['label'] : '';

                return strnatcasecmp($firstLabel, $secondLabel);
            }
        );

        return $options;
    }

    public function normalizePhonePrefix(string $prefix): string
    {
        $normalized = str_replace(' ', '', trim($prefix));
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        } elseif (!str_starts_with($normalized, '+')) {
            $normalized = '+' . ltrim($normalized, '+');
        }

        $digits = preg_replace('/[^0-9]/', '', substr($normalized, 1));
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        return '+' . $digits;
    }

    public function normalizePhoneLanguage(string $value): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return 'INT';
        }

        if (str_starts_with($upper, 'IT')) {
            return 'IT';
        }

        if (str_starts_with($upper, 'EN')) {
            return 'EN';
        }

        if (str_starts_with($upper, 'INT')) {
            return 'INT';
        }

        return 'INT';
    }
}

