<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use DateTimeImmutable;
use function array_map;
use function explode;
use function preg_match;
use function preg_split;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Parsa e normalizza gli orari di apertura.
 * Estratto da Availability.php per migliorare modularità.
 */
final class ScheduleParser
{
    private const DEFAULT_SCHEDULE = [
        'mon' => ['19:00-23:00'],
        'tue' => ['19:00-23:00'],
        'wed' => ['19:00-23:00'],
        'thu' => ['19:00-23:00'],
        'fri' => ['19:00-23:30'],
        'sat' => ['12:30-15:00', '19:00-23:30'],
        'sun' => ['12:30-15:00'],
    ];

    /**
     * @param array<string, array<string, mixed>> $scheduleMap
     * @return array<int, array{start:int,end:int}>
     */
    public function resolveScheduleForDay(DateTimeImmutable $day, array $scheduleMap): array
    {
        $dayKey = strtolower($day->format('D')); // mon, tue, wed, etc.
        
        // Mapping italiano -> inglese per compatibilità
        $italianToEnglish = [
            'lun' => 'mon',
            'mar' => 'tue',
            'mer' => 'wed',
            'gio' => 'thu',
            'ven' => 'fri',
            'sab' => 'sat',
            'dom' => 'sun'
        ];
        
        // Prova prima con il giorno inglese (formato standard PHP)
        $schedule = $scheduleMap[$dayKey] ?? [];
        
        // Se vuoto, prova con il mapping italiano
        if (empty($schedule)) {
            foreach ($scheduleMap as $key => $value) {
                if (isset($italianToEnglish[$key]) && $italianToEnglish[$key] === $dayKey) {
                    $schedule = $value;
                    break;
                }
            }
        }

        return $schedule;
    }

    /**
     * @return array<int, array{start:int,end:int}>
     */
    public function parseScheduleDefinition(string $raw): array
    {
        $schedule = [];
        $lines    = $raw !== '' ? preg_split('/\n/', $raw) : false;

        if ($lines === false || $lines === []) {
            return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$day, $ranges] = array_map('trim', explode('=', $line, 2));
            $day            = strtolower($day);

            $segments = preg_split('/[|,]/', $ranges) ?: [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end   = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $schedule[$day][] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        if ($schedule === []) {
            return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);
        }

        return $schedule;
    }

    /**
     * @param array<string, string[]> $definition
     *
     * @return array<string, array<int, array{start:int,end:int}>>
     */
    public function normalizeSchedule(array $definition): array
    {
        $normalized = [];
        foreach ($definition as $day => $segments) {
            foreach ($segments as $segment) {
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end   = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $normalized[$day][] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        return $normalized;
    }
}
















