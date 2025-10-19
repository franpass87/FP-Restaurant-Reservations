<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Domain\Settings\MealPlan;
use FP\Resv\Domain\Settings\Options;

final class MealPlanService
{
    private ?array $mealPlanCache = null;

    public function __construct(private readonly mixed $options)
    {
    }

    /**
     * Get all configured meals
     */
    public function getMeals(): array
    {
        if ($this->mealPlanCache !== null) {
            return $this->mealPlanCache;
        }

        $definition = (string) $this->options->getField('fp_resv_general', 'frontend_meals', '');
        $parsed = MealPlan::parse($definition);
        $this->mealPlanCache = MealPlan::indexByKey($parsed);

        return $this->mealPlanCache;
    }

    /**
     * Get settings for a specific meal
     */
    public function getMealSettings(string $mealKey): array
    {
        $meals = $this->getMeals();
        return $meals[$mealKey] ?? [];
    }

    /**
     * Get meal schedule for a specific meal
     */
    public function getMealSchedule(string $mealKey): array
    {
        $meals = $this->getMeals();
        
        if (empty($meals)) {
            return $this->getDefaultSchedule();
        }

        if (!isset($meals[$mealKey])) {
            return $this->getDefaultSchedule();
        }

        $meal = $meals[$mealKey];
        
        if (!empty($meal['hours_definition'])) {
            return $this->parseScheduleDefinition((string) $meal['hours_definition']);
        }

        return $this->getDefaultSchedule();
    }

    /**
     * Parse schedule definition string into array format
     */
    private function parseScheduleDefinition(string $definition): array
    {
        return \FP\Resv\Domain\Settings\MealPlan::parseScheduleDefinition($definition);
    }

    /**
     * Check if a meal is available on a specific day
     */
    public function isMealAvailableOnDay(string $mealKey, \DateTimeImmutable $day): bool
    {
        $schedule = $this->getMealSchedule($mealKey);
        $dayKey = strtolower($day->format('D')); // mon, tue, wed, etc.
        
        return isset($schedule[$dayKey]) && !empty($schedule[$dayKey]);
    }

    /**
     * Get default schedule from backend
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
