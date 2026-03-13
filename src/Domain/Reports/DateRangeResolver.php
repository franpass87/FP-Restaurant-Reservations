<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use DateTimeImmutable;
use DateTimeZone;
use function preg_match;
use function trim;
use function wp_timezone;

/**
 * Risolve e normalizza range di date per i report.
 * Estratto da Service.php per migliorare modularità.
 */
final class DateRangeResolver
{
    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function resolveRange(string $start, ?string $end): array
    {
        $startDate = $this->createDate($start);
        $endDate   = $end !== null ? $this->createDate($end) : null;

        if ($startDate === null) {
            $startDate = new DateTimeImmutable('today', $this->resolveTimezone());
        }

        if ($endDate === null) {
            $endDate = $startDate;
        }

        if ($endDate < $startDate) {
            $endDate = $startDate;
        }

        return [$startDate, $endDate];
    }

    private function createDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = $this->normalizeDateString($value);
        if ($normalized === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($normalized, $this->resolveTimezone());
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeDateString(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Supporta formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Supporta formato DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        // Prova a parsare come data generica
        try {
            $date = new DateTimeImmutable($value, $this->resolveTimezone());
            return $date->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveTimezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        return new DateTimeZone('UTC');
    }
}
















