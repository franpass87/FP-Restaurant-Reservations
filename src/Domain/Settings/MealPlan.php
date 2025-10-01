<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use function array_key_exists;
use function array_merge;
use function explode;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_split;
use function sanitize_key;
use function sanitize_text_field;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;
use function wp_strip_all_tags;

final class MealPlan
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $definition): array
    {
        if (trim($definition) === '') {
            return [];
        }

        $decoded = json_decode($definition, true);
        if (is_array($decoded)) {
            return self::normalizeList($decoded);
        }

        return self::parseLegacy($definition);
    }

    /**
     * @param mixed $value
     *
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized     = [];
        $defaultDefined = false;

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $meal = self::normalizeMeal($entry);
            if ($meal === null) {
                continue;
            }

            if (!empty($meal['active'])) {
                $defaultDefined = true;
            }

            $normalized[] = $meal;
        }

        if ($normalized === []) {
            return [];
        }

        if (!$defaultDefined) {
            $normalized[0]['active'] = true;
        }

        return array_values($normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $meals
     *
     * @return array<string, array<string, mixed>>
     */
    public static function indexByKey(array $meals): array
    {
        $indexed = [];
        foreach ($meals as $meal) {
            if (!is_array($meal) || !isset($meal['key'])) {
                continue;
            }

            $key = (string) $meal['key'];
            if ($key === '') {
                continue;
            }

            $indexed[$key] = $meal;
        }

        return $indexed;
    }

    /**
     * @param array<int, array<string, mixed>> $meals
     */
    public static function getDefaultKey(array $meals): string
    {
        foreach ($meals as $meal) {
            if (!is_array($meal)) {
                continue;
            }

            if (!empty($meal['active']) && isset($meal['key'])) {
                return (string) $meal['key'];
            }
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function parseLegacy(string $definition): array
    {
        $lines = preg_split("/\r\n|\r|\n/", $definition);
        if (!is_array($lines)) {
            return [];
        }

        $entries = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\\s*\|\\s*/', $line) ?: [];
            if ($parts === []) {
                continue;
            }

            $rawKey = array_shift($parts);
            if (!is_string($rawKey) || $rawKey === '') {
                continue;
            }

            $isDefault = false;
            if ($rawKey[0] === '*') {
                $isDefault = true;
                $rawKey    = ltrim($rawKey, '* ');
            }

            if ($rawKey === '') {
                continue;
            }

            $attributes = [];
            if ($parts !== []) {
                $attributes = self::extractAttributes($parts);
            }

            $entries[] = array_merge(
                [
                    'key'     => $rawKey,
                    'label'   => $parts[0] ?? '',
                    'hint'    => $parts[1] ?? '',
                    'notice'  => $parts[2] ?? '',
                    'price'   => $parts[3] ?? '',
                    'badge'   => $parts[4] ?? '',
                    'badge_icon' => $parts[5] ?? '',
                    'active'  => $isDefault,
                ],
                $attributes
            );
        }

        return self::normalizeList($entries);
    }

    /**
     * @param array<int, string> $parts
     *
     * @return array<string, mixed>
     */
    private static function extractAttributes(array $parts): array
    {
        $attributes = [];
        foreach ($parts as $part) {
            if (!is_string($part)) {
                continue;
            }

            if (!str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $part, 2));
            if ($key === '') {
                continue;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    private static function normalizeMeal(array $entry): ?array
    {
        $key = isset($entry['key']) ? sanitize_key((string) $entry['key']) : '';
        if ($key === '') {
            return null;
        }

        $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
        if ($label === '') {
            $label = ucwords(str_replace(['_', '-'], ' ', $key));
        }

        $meal = [
            'key'   => $key,
            'label' => $label,
        ];

        if (!empty($entry['hint'])) {
            $meal['hint'] = sanitize_text_field((string) $entry['hint']);
        }

        if (!empty($entry['notice'])) {
            $meal['notice'] = sanitize_text_field((string) $entry['notice']);
        }

        if (!empty($entry['badge'])) {
            $meal['badge'] = sanitize_text_field((string) $entry['badge']);
        }

        if (!empty($entry['badge_icon'])) {
            $meal['badge_icon'] = sanitize_key((string) $entry['badge_icon']);
        }

        if (isset($entry['price'])) {
            $price = self::normalizePrice($entry['price']);
            if ($price !== null) {
                $meal['price'] = $price;
            }
        }

        if (!empty($entry['active'])) {
            $meal['active'] = true;
        }

        $availability = self::normalizeAvailability($entry);
        if ($availability !== []) {
            $meal = array_merge($meal, $availability);
        }

        return $meal;
    }

    private static function normalizePrice(mixed $value): ?string
    {
        if (is_int($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));
            if ($normalized === '') {
                return null;
            }

            if (!is_numeric($normalized)) {
                return null;
            }

            return number_format((float) $normalized, 2, '.', '');
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function normalizeAvailability(array $entry): array
    {
        $availability = [];
        $source       = [];

        if (isset($entry['availability']) && is_array($entry['availability'])) {
            $source = $entry['availability'];
        }

        foreach (['hours', 'schedule', 'service_hours'] as $key) {
            if (array_key_exists($key, $entry) && !array_key_exists($key, $source)) {
                $source[$key] = $entry[$key];
            }
        }

        foreach ([
            'slot'         => ['slot_interval', 5],
            'slot_interval'=> ['slot_interval', 5],
            'slotInterval' => ['slot_interval', 5],
            'turn'         => ['turnover', 5],
            'turnover'     => ['turnover', 5],
            'turnover_minutes' => ['turnover', 5],
            'buffer'       => ['buffer', 0],
            'buffer_minutes' => ['buffer', 0],
            'parallel'     => ['max_parallel', 1],
            'max_parallel' => ['max_parallel', 1],
            'maxParallel'  => ['max_parallel', 1],
            'capacity'     => ['capacity', 1],
        ] as $key => [$target, $minimum]) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = self::normalizeInteger($source[$key], $minimum);
            if ($value !== null) {
                $availability[$target] = $value;
            }
        }

        foreach (['hours', 'schedule', 'service_hours'] as $hoursKey) {
            if (!array_key_exists($hoursKey, $source)) {
                continue;
            }

            $hours = self::normalizeHours($source[$hoursKey]);
            if ($hours !== '') {
                $availability['hours_definition'] = $hours;
                break;
            }
        }

        return $availability;
    }

    private static function normalizeHours(mixed $value): string
    {
        if (is_array($value)) {
            $lines = [];
            foreach ($value as $day => $ranges) {
                $dayKey = sanitize_key((string) $day);
                if ($dayKey === '') {
                    continue;
                }

                $normalizedRanges = [];
                if (is_array($ranges)) {
                    foreach ($ranges as $range) {
                        if (is_string($range) && preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', trim($range))) {
                            $normalizedRanges[] = trim($range);
                        }
                    }
                } elseif (is_string($ranges)) {
                    $segments = preg_split('/[|,]/', $ranges) ?: [];
                    foreach ($segments as $segment) {
                        $segment = trim($segment);
                        if (preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $segment)) {
                            $normalizedRanges[] = $segment;
                        }
                    }
                }

                if ($normalizedRanges !== []) {
                    $lines[] = $dayKey . '=' . implode('|', $normalizedRanges);
                }
            }

            return implode("\n", $lines);
        }

        if (!is_string($value)) {
            return '';
        }

        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $segments = preg_split('/[;\n]+/', $raw) ?: [];
        $lines    = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            if (str_contains($segment, '=')) {
                [$day, $ranges] = array_map('trim', explode('=', $segment, 2));
            } elseif (str_contains($segment, ':')) {
                [$day, $ranges] = array_map('trim', explode(':', $segment, 2));
            } else {
                continue;
            }

            $dayKey = sanitize_key($day);
            if ($dayKey === '') {
                continue;
            }

            $rangeSegments = preg_split('/[|,]/', $ranges) ?: [];
            $validRanges   = [];
            foreach ($rangeSegments as $range) {
                $range = trim($range);
                if ($range === '') {
                    continue;
                }

                if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $range)) {
                    continue;
                }

                $validRanges[] = $range;
            }

            if ($validRanges !== []) {
                $lines[] = $dayKey . '=' . implode('|', $validRanges);
            }
        }

        return implode("\n", $lines);
    }

    private static function normalizeInteger(mixed $value, int $minimum): ?int
    {
        if (is_int($value)) {
            return max($minimum, $value);
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            if (!preg_match('/^-?\d+$/', $value)) {
                return null;
            }

            return max($minimum, (int) $value);
        }

        if (is_numeric($value)) {
            return max($minimum, (int) $value);
        }

        return null;
    }
}
