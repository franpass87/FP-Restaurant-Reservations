<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;

final class AvailabilityService
{
    public function __construct(
        private readonly MealPlanService $mealPlanService
    ) {
    }

    /**
     * Find available days for all meals in a date range
     */
    public function findAvailableDaysForAllMeals(string $from, string $to): array
    {
        $startDate = new DateTimeImmutable($from . ' 00:00:00');
        $endDate = new DateTimeImmutable($to . ' 23:59:59');
        
        $meals = $this->mealPlanService->getMeals();
        
        // Se non ci sono meal configurati, usa default
        if (empty($meals)) {
            $meals = ['default' => []];
        }

        $results = [];
        $current = $startDate;

        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $hasAnyAvailability = false;
            $mealAvailability = [];

            // Controlla ogni meal per questo giorno
            foreach ($meals as $mealKey => $mealData) {
                $isAvailable = $this->mealPlanService->isMealAvailableOnDay($mealKey, $current);
                $mealAvailability[$mealKey] = $isAvailable;
                
                if ($isAvailable) {
                    $hasAnyAvailability = true;
                }
            }

            $results[$dateKey] = [
                'available' => $hasAnyAvailability,
                'meals' => $mealAvailability,
            ];

            $current = $current->add(new DateInterval('P1D'));
        }

        return $results;
    }

    /**
     * Find available days for a specific meal
     */
    public function findAvailableDaysForMeal(string $from, string $to, string $mealKey): array
    {
        $allDays = $this->findAvailableDaysForAllMeals($from, $to);
        
        $filteredDays = [];
        foreach ($allDays as $date => $info) {
            $mealAvailable = isset($info['meals'][$mealKey]) && $info['meals'][$mealKey];
            $filteredDays[$date] = [
                'available' => $mealAvailable,
                'meal' => $mealKey,
            ];
        }
        
        return $filteredDays;
    }

    /**
     * Check if a specific meal is available on a given day
     */
    private function isMealAvailableOnDay(string $mealKey, \DateTimeImmutable $day): bool
    {
        $mealData = $this->mealPlanService->getMealSettings($mealKey);
        
        // Use meal-specific schedule if available, otherwise use default
        $scheduleDefinition = $mealData['hours_definition'] ?? null;
        
        if ($scheduleDefinition === null || trim($scheduleDefinition) === '') {
            // Fallback to default schedule if no specific schedule is defined for the meal
            $schedule = $this->getDefaultSchedule();
        } else {
            // Parse meal-specific schedule
            $schedule = $this->parseScheduleDefinition($scheduleDefinition);
        }
        
        $dayKey = strtolower($day->format('D')); // 'mon', 'tue', etc.
        return !empty($schedule[$dayKey]);
    }

    /**
     * Parse schedule definition string into array format
     */
    private function parseScheduleDefinition(string $definition): array
    {
        $schedule = [];
        $lines = preg_split('/\n/', $definition) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$day, $ranges] = array_map('trim', explode('=', $line, 2));
            $day = strtolower($day);

            $segments = preg_split('/[|,]/', $ranges) ?: [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $schedule[$day][] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return $schedule;
    }

    /**
     * Get default schedule
     */
    private function getDefaultSchedule(): array
    {
        return [
            'mon' => ['19:00-23:00'],
            'tue' => ['19:00-23:00'],
            'wed' => ['19:00-23:00'],
            'thu' => ['19:00-23:00'],
            'fri' => ['19:00-23:30'],
            'sat' => ['12:30-15:00', '19:00-23:30'],
            'sun' => ['12:30-15:00'],
        ];
    }
}
