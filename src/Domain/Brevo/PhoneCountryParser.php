<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use FP\Resv\Domain\Settings\Options;
use function json_decode;
use function preg_replace;
use function strpos;
use function strlen;
use function strtoupper;
use function substr;
use function trim;

/**
 * Gestisce il parsing dei prefissi telefonici per determinare la lingua/paese.
 * Estratto da AutomationService.php per migliorare modularità.
 */
final class PhoneCountryParser
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Determina la lingua dal numero di telefono usando la mappa configurata.
     * Usa brevo_phone_prefix_map per associare prefissi a liste (es. +39 => IT).
     * Fallback: EN per prefissi non mappati.
     */
    public function parsePhoneCountry(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return 'EN';
        }

        $normalized = preg_replace('/[^0-9+]/', '', $normalized);
        if (!is_string($normalized) || $normalized === '') {
            return 'EN';
        }

        if ($normalized[0] !== '+') {
            if (strpos($normalized, '00') === 0) {
                $normalized = '+' . substr($normalized, 2);
            } else {
                $normalized = '+' . $normalized;
            }
        }

        // Usa la mappa configurata per determinare la lista in base al prefisso
        $settings = $this->options->getGroup('fp_resv_brevo', []);
        $prefixMap = $this->decodePrefixMap($settings['brevo_phone_prefix_map'] ?? null);

        // Cerca il prefisso più lungo che corrisponde (per gestire +1, +1869, ecc.)
        $matchedLanguage = '';
        $matchedLength = 0;

        foreach ($prefixMap as $prefix => $language) {
            if (strpos($normalized, $prefix) === 0) {
                $prefixLength = strlen($prefix);
                if ($prefixLength > $matchedLength) {
                    $matchedLanguage = $language;
                    $matchedLength = $prefixLength;
                }
            }
        }

        if ($matchedLanguage !== '') {
            return strtoupper($matchedLanguage);
        }

        // Fallback: se non trova corrispondenze, usa EN
        return 'EN';
    }

    /**
     * Decodifica la mappa JSON dei prefissi telefonici.
     *
     * @return array<string, string>
     */
    private function decodePrefixMap(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $prefix => $language) {
            if (!is_string($prefix)) {
                continue;
            }

            $normalizedPrefix = $this->normalizePhonePrefix($prefix);
            if ($normalizedPrefix === '') {
                continue;
            }

            $languageCode = is_string($language) ? strtoupper(trim($language)) : '';
            if ($languageCode !== '') {
                $map[$normalizedPrefix] = $languageCode;
            }
        }

        return $map;
    }

    private function normalizePhonePrefix(string $prefix): string
    {
        $normalized = str_replace(' ', '', trim($prefix));
        if ($normalized === '') {
            return '';
        }

        if (strpos($normalized, '00') === 0) {
            $normalized = '+' . substr($normalized, 2);
        }

        if (strpos($normalized, '+') !== 0) {
            $normalized = '+' . ltrim($normalized, '+');
        }

        return $normalized === '+' ? '' : $normalized;
    }
}
















