<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function ltrim;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strtoupper;
use function substr;
use function trim;
use function is_string;

/**
 * Helper per normalizzazione e gestione numeri di telefono.
 * Estratto da Service.php per riutilizzabilità.
 */
final class PhoneHelper
{
    /**
     * Normalizza un prefisso telefonico internazionale.
     * 
     * @param string $prefix Il prefisso da normalizzare (es. "+39", "0039", "39")
     * @return string Il prefisso normalizzato con formato +XX o stringa vuota
     */
    public static function normalizePrefix(string $prefix): string
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

    /**
     * Normalizza un numero di telefono completo.
     * 
     * @param string $phone Il numero di telefono da normalizzare
     * @return string Il numero normalizzato con formato +XXXXXXXXXXXX o stringa vuota
     */
    public static function normalizeNumber(string $phone): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($phone));
        if (!is_string($normalized) || $normalized === '') {
            return '';
        }

        if ($normalized[0] !== '+') {
            if (str_starts_with($normalized, '00')) {
                $normalized = '+' . substr($normalized, 2);
            } else {
                $normalized = '+' . ltrim($normalized, '+');
            }
        }

        $digits = preg_replace('/[^0-9]/', '', substr($normalized, 1));
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        return '+' . $digits;
    }

    /**
     * Normalizza l'indicazione di lingua per telefono.
     * 
     * @param string $value Il valore della lingua (es. "IT", "it", "EN", "en")
     * @return string La lingua normalizzata ("it" o "en") o stringa vuota
     */
    public static function normalizeLanguage(string $value): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return '';
        }

        if (str_starts_with($upper, 'IT')) {
            return 'it';
        }

        return 'en';
    }

    /**
     * Rileva la lingua dal numero di telefono.
     * Logica semplificata: +39 = italiano, tutto il resto = inglese.
     * 
     * @param string $phone Il numero di telefono completo
     * @param string $phoneCountry Il prefisso paese separato (opzionale)
     * @return string La lingua rilevata ("it" o "en")
     */
    public static function detectLanguage(string $phone, string $phoneCountry = ''): string
    {
        // Normalizza il prefisso dal campo country
        $normalizedCountry = self::normalizePrefix($phoneCountry);
        if ($normalizedCountry !== '') {
            // Logica semplificata: +39 = it, tutto il resto = en
            return str_starts_with($normalizedCountry, '+39') ? 'it' : 'en';
        }

        // Altrimenti normalizza il numero di telefono
        $normalizedPhone = self::normalizeNumber($phone);
        if ($normalizedPhone === '') {
            return 'en';
        }

        // Logica semplificata: +39 = it, tutto il resto = en
        return str_starts_with($normalizedPhone, '+39') ? 'it' : 'en';
    }

    /**
     * Estrae il prefisso internazionale da un numero di telefono completo.
     * 
     * @param string $phone Il numero di telefono completo
     * @return string Il prefisso estratto (es. "+39") o stringa vuota
     */
    public static function extractPrefix(string $phone): string
    {
        $normalized = self::normalizeNumber($phone);
        if ($normalized === '') {
            return '';
        }

        // Estrae i primi 1-4 digit dopo il +
        if (preg_match('/^\+(\d{1,4})/', $normalized, $matches)) {
            return '+' . $matches[1];
        }

        return '';
    }

    /**
     * Formatta un numero di telefono in modo leggibile.
     * 
     * @param string $phone Il numero di telefono da formattare
     * @param string $format Il formato desiderato ("international", "national", "default")
     * @return string Il numero formattato
     */
    public static function format(string $phone, string $format = 'international'): string
    {
        $normalized = self::normalizeNumber($phone);
        if ($normalized === '') {
            return '';
        }

        if ($format === 'international') {
            // +39 123 456 7890
            if (str_starts_with($normalized, '+39')) {
                $number = substr($normalized, 3);
                return '+39 ' . chunk_split($number, 3, ' ');
            }
            return $normalized;
        }

        if ($format === 'national') {
            // Rimuove il prefisso internazionale
            return ltrim($normalized, '+');
        }

        return $normalized;
    }

    /**
     * Verifica se un numero di telefono è valido.
     * 
     * @param string $phone Il numero di telefono da verificare
     * @return bool True se valido, false altrimenti
     */
    public static function isValid(string $phone): bool
    {
        $normalized = self::normalizeNumber($phone);
        if ($normalized === '') {
            return false;
        }

        // Verifica che abbia almeno 8 cifre (minimo per un numero valido)
        $digits = preg_replace('/[^0-9]/', '', $normalized);
        return is_string($digits) && strlen($digits) >= 8;
    }
}
