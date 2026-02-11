<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use DateTimeImmutable;

/**
 * Provides special openings as meals for the frontend form.
 * Special openings are closures of type 'special_opening' that allow
 * reservations on days that would normally be closed.
 */
final class SpecialOpeningsProvider
{
    private const TYPE_SPECIAL_OPENING = 'special_opening';
    /**
     * Load active special openings for admin (Turni e disponibilità).
     * Returns minimal data: id, meal_key, label, capacity. No available_days calculation.
     *
     * @return array<int, array{id:int, meal_key:string, label:string, capacity:int, slots:array}>
     */
    public function getSpecialOpeningsForAdmin(): array
    {
        try {
            global $wpdb;

            if (!$wpdb instanceof \wpdb) {
                return [];
            }

            $table = $wpdb->prefix . 'fp_closures';
            $now = (new \DateTimeImmutable('now', wp_timezone()))->format('Y-m-d H:i:s');

            $sql = $wpdb->prepare(
                "SELECT id, capacity_override_json
                 FROM {$table}
                 WHERE active = 1
                   AND type = %s
                   AND (end_at >= %s OR recurrence_json IS NOT NULL)
                 ORDER BY start_at ASC",
                self::TYPE_SPECIAL_OPENING,
                $now
            );

            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows) || $rows === []) {
                return [];
            }

            $result = [];
            foreach ($rows as $row) {
                $capacityOverride = json_decode((string) ($row['capacity_override_json'] ?? '{}'), true);
                if (!is_array($capacityOverride)) {
                    continue;
                }

                $label = $capacityOverride['label'] ?? '';
                $mealKey = $capacityOverride['meal_key'] ?? '';
                $slots = $capacityOverride['slots'] ?? [];

                if ($label === '' || $mealKey === '' || empty($slots)) {
                    continue;
                }

                $result[] = [
                    'id' => (int) $row['id'],
                    'meal_key' => $mealKey,
                    'label' => $label,
                    'capacity' => (int) ($capacityOverride['capacity'] ?? 40),
                    'slots' => $slots,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Load active special openings from database and convert them to meal format.
     *
     * @return array<int, array<string, mixed>> Array of meals in the same format as regular meals
     */
    public function getSpecialOpeningsAsMeals(): array
    {
        try {
            global $wpdb;
            
            if (!$wpdb instanceof \wpdb) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[FP-RESV] SpecialOpeningsProvider: wpdb non disponibile');
                }
                return [];
            }
            
            $table = $wpdb->prefix . 'fp_closures';
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('Europe/Rome');
            $now = new DateTimeImmutable('now', $timezone);
            $futureLimit = $now->modify('+90 days');
            
            // Query for active special_opening closures that are current or in the future
            $sql = $wpdb->prepare(
                "SELECT id, start_at, end_at, capacity_override_json, note, recurrence_json
                 FROM {$table}
                 WHERE active = 1
                   AND type = %s
                   AND (
                       end_at >= %s
                       OR recurrence_json IS NOT NULL
                   )
                 ORDER BY start_at ASC",
                self::TYPE_SPECIAL_OPENING,
                $now->format('Y-m-d H:i:s')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-RESV] SpecialOpeningsProvider SQL: ' . $sql);
            }
            
            $rows = $wpdb->get_results($sql, ARRAY_A);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-RESV] SpecialOpeningsProvider: trovate ' . (is_array($rows) ? count($rows) : 0) . ' aperture speciali');
            }
            
            if (!is_array($rows) || $rows === []) {
                return [];
            }
            
            $meals = [];
            foreach ($rows as $row) {
                $meal = $this->convertToMeal($row, $now, $futureLimit, $timezone);
                if ($meal !== null) {
                    $meals[] = $meal;
                }
            }
            
            return $meals;
        } catch (\Throwable $e) {
            // Log error but don't break the form
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-RESV] SpecialOpeningsProvider error: ' . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Convert a special_opening closure row to a meal array.
     *
     * @param array<string, mixed> $row Database row
     * @return array<string, mixed>|null Meal array or null if invalid
     */
    private function convertToMeal(array $row, DateTimeImmutable $now, DateTimeImmutable $futureLimit, \DateTimeZone $timezone): ?array
    {
        $capacityOverride = json_decode((string) ($row['capacity_override_json'] ?? '{}'), true);
        if (!is_array($capacityOverride)) {
            return null;
        }
        
        $label = $capacityOverride['label'] ?? '';
        $mealKey = $capacityOverride['meal_key'] ?? '';
        $slots = $capacityOverride['slots'] ?? [];
        
        if ($label === '' || $mealKey === '' || empty($slots)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-RESV] SpecialOpeningsProvider: apertura id=' . ($row['id'] ?? '?') . ' scartata - label=' . $label . ', mealKey=' . $mealKey . ', slots=' . count($slots));
            }
            return null;
        }
        
        // Calculate available days based on the closure dates
        $startAt = new DateTimeImmutable($row['start_at'], $timezone);
        $endAt = new DateTimeImmutable($row['end_at'], $timezone);
        
        // Build available_days array - dates when this special opening is active
        $availableDays = $this->calculateAvailableDays($startAt, $endAt, $row['recurrence_json'], $now, $futureLimit);
        
        if ($availableDays === []) {
            return null;
        }
        
        // Build hours_definition from slots
        $hoursDefinition = $this->buildHoursDefinition($slots, $availableDays);
        
        return [
            'key' => $mealKey,
            'label' => $label,
            'hint' => $row['note'] ?? '',
            'badge' => __('Evento speciale', 'fp-restaurant-reservations'),
            'badge_icon' => 'star',
            'active' => false, // Not default
            'hours_definition' => $hoursDefinition,
            'available_days' => $availableDays,
            'capacity' => (int) ($capacityOverride['capacity'] ?? 40),
            'is_special_opening' => true, // Flag to identify special openings
            'closure_id' => (int) $row['id'],
        ];
    }
    
    /**
     * Calculate which days are available for this special opening.
     *
     * @return array<int, string> Array of date strings (Y-m-d)
     */
    private function calculateAvailableDays(
        DateTimeImmutable $startAt,
        DateTimeImmutable $endAt,
        ?string $recurrenceJson,
        DateTimeImmutable $now,
        DateTimeImmutable $futureLimit
    ): array {
        $days = [];
        
        // For non-recurring, just list the days in the range
        if ($recurrenceJson === null || $recurrenceJson === '') {
            $current = $startAt->setTime(0, 0, 0);
            $end = $endAt->setTime(23, 59, 59);
            
            while ($current <= $end && $current <= $futureLimit) {
                if ($current >= $now->setTime(0, 0, 0)) {
                    $days[] = $current->format('Y-m-d');
                }
                $current = $current->modify('+1 day');
            }
            
            return $days;
        }
        
        // For recurring, calculate occurrences
        $recurrence = json_decode($recurrenceJson, true);
        if (!is_array($recurrence)) {
            return $days;
        }
        
        $type = strtolower($recurrence['type'] ?? '');
        $from = isset($recurrence['from']) ? new DateTimeImmutable($recurrence['from'], wp_timezone()) : $now;
        $until = isset($recurrence['until']) ? new DateTimeImmutable($recurrence['until'], wp_timezone()) : $futureLimit;
        
        // Limit the range
        $from = max($from, $now->setTime(0, 0, 0));
        $until = min($until, $futureLimit);
        
        $current = $from;
        while ($current <= $until) {
            $matches = false;
            
            switch ($type) {
                case 'daily':
                    $matches = true;
                    break;
                    
                case 'weekly':
                    $weekDays = $recurrence['days'] ?? [];
                    $dayKey = strtolower($current->format('D'));
                    $dayNum = $current->format('N');
                    $matches = in_array($dayKey, $weekDays, true) || in_array($dayNum, $weekDays, true);
                    break;
                    
                case 'monthly':
                    $monthDays = $recurrence['days'] ?? [];
                    $dayOfMonth = (int) $current->format('j');
                    $matches = in_array($dayOfMonth, array_map('intval', $monthDays), true);
                    break;
            }
            
            if ($matches) {
                $days[] = $current->format('Y-m-d');
            }
            
            $current = $current->modify('+1 day');
        }
        
        return $days;
    }
    
    /**
     * Build hours_definition string from slots.
     * This creates a schedule that applies to all days the opening is active.
     *
     * @param array<int, array<string, string>> $slots
     * @param array<int, string> $availableDays
     */
    private function buildHoursDefinition(array $slots, array $availableDays): string
    {
        // Convert slots to time ranges
        $ranges = [];
        foreach ($slots as $slot) {
            $start = $slot['start'] ?? '';
            $end = $slot['end'] ?? '';
            if ($start !== '' && $end !== '') {
                $ranges[] = $start . '-' . $end;
            }
        }
        
        if ($ranges === []) {
            return '';
        }
        
        $rangeStr = implode('|', $ranges);
        
        // Get unique day keys from available days
        $dayKeys = [];
        foreach ($availableDays as $dateStr) {
            $date = new DateTimeImmutable($dateStr);
            $dayKey = strtolower($date->format('D'));
            if (!in_array($dayKey, $dayKeys, true)) {
                $dayKeys[] = $dayKey;
            }
        }
        
        // Build definition for each day
        $lines = [];
        foreach ($dayKeys as $dayKey) {
            $lines[] = $dayKey . '=' . $rangeStr;
        }
        
        return implode("\n", $lines);
    }
}
