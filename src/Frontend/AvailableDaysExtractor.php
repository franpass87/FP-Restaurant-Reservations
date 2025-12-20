<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function array_merge;
use function array_unique;
use function explode;
use function preg_split;
use function sort;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Estrae e gestisce i giorni disponibili dalla configurazione.
 * Estratto da FormContext.php per migliorare modularità.
 */
final class AvailableDaysExtractor
{
    /**
     * Estrae i giorni disponibili dalla configurazione del servizio.
     *
     * @param array<string, mixed> $generalSettings
     * @param array<int, array<string, mixed>> $meals
     *
     * @return array<string>
     */
    public function extractAvailableDays(array $generalSettings, array $meals): array
    {
        $dayMapping = [
            'mon' => '1',
            'tue' => '2',
            'wed' => '3',
            'thu' => '4',
            'fri' => '5',
            'sat' => '6',
            'sun' => '0',
        ];

        $availableDays = [];

        // Se ci sono meal configurati, estrai i giorni da ciascun meal
        if ($meals !== []) {
            foreach ($meals as $meal) {
                if (!empty($meal['hours_definition'])) {
                    $days = $this->parseDaysFromSchedule((string) $meal['hours_definition']);
                    $availableDays = array_merge($availableDays, $days);
                }
            }
        }

        // Se non ci sono giorni dai meal, usa il service_hours_definition generale
        if ($availableDays === [] && !empty($generalSettings['service_hours_definition'])) {
            $availableDays = $this->parseDaysFromSchedule((string) $generalSettings['service_hours_definition']);
        }

        // Rimuovi duplicati e ordina
        $availableDays = array_unique($availableDays);
        sort($availableDays);

        // Converti i giorni in numeri ISO (0=domenica, 1=lunedì, ecc.)
        $dayNumbers = [];
        foreach ($availableDays as $day) {
            if (isset($dayMapping[$day])) {
                $dayNumbers[] = $dayMapping[$day];
            }
        }

        return $dayNumbers;
    }

    /**
     * Estrae i giorni dalla definizione dello schedule.
     *
     * @param string $scheduleDefinition
     *
     * @return array<string>
     */
    public function parseDaysFromSchedule(string $scheduleDefinition): array
    {
        $days = [];
        $lines = preg_split('/\n/', $scheduleDefinition) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$day] = explode('=', $line, 2);
            $day = strtolower(trim($day));

            if ($day !== '') {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Arricchisce ogni meal con i giorni disponibili specifici.
     *
     * @param array<int, array<string, mixed>> $meals
     * @param array<string, mixed> $generalSettings
     *
     * @return array<int, array<string, mixed>>
     */
    public function enrichMealsWithAvailableDays(array $meals, array $generalSettings): array
    {
        $dayMapping = [
            'mon' => '1',
            'tue' => '2',
            'wed' => '3',
            'thu' => '4',
            'fri' => '5',
            'sat' => '6',
            'sun' => '0',
        ];

        foreach ($meals as $index => $meal) {
            $days = [];

            // Se il meal ha hours_definition specifico, usalo
            if (!empty($meal['hours_definition'])) {
                $days = $this->parseDaysFromSchedule((string) $meal['hours_definition']);
            }
            // Altrimenti usa service_hours_definition generale come fallback
            elseif (!empty($generalSettings['service_hours_definition'])) {
                $days = $this->parseDaysFromSchedule((string) $generalSettings['service_hours_definition']);
            }

            // Converti i giorni in numeri ISO
            $dayNumbers = [];
            foreach ($days as $day) {
                if (isset($dayMapping[$day])) {
                    $dayNumbers[] = $dayMapping[$day];
                }
            }

            // Aggiungi i giorni disponibili al meal
            $meals[$index]['available_days'] = $dayNumbers;
        }

        return $meals;
    }
}
















