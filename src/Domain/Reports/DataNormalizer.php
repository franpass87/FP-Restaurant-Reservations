<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use function is_numeric;
use function is_string;
use function number_format;
use function preg_match;
use function str_replace;
use function strtolower;
use function substr;
use function trim;

/**
 * Normalizza e formatta dati per i report.
 * Estratto da Service.php per migliorare modularità.
 */
final class DataNormalizer
{
    public function normalizeFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return 0.0;
    }

    public function formatTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }

        // Se è già nel formato HH:MM, restituiscilo
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        // Se è nel formato HH:MM:SS, rimuovi i secondi
        if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $time, $matches)) {
            return $matches[1];
        }

        return $time;
    }

    public function sanitizeMultiline(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Sostituisci newline con spazi
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        
        // Rimuovi spazi multipli
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    public function composeName(string $first, string $last): string
    {
        $first = trim($first);
        $last  = trim($last);

        if ($first === '' && $last === '') {
            return '';
        }

        if ($first === '') {
            return $last;
        }

        if ($last === '') {
            return $first;
        }

        return $first . ' ' . $last;
    }

    public function formatNumber(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }
}
















