<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class Validator
{
    /**
     * Recursively sanitize a mixed payload preserving non-string primitives.
     *
     * Strings are filtered using `sanitize_text_field()` by default while
     * multi-line strings fallback to `sanitize_textarea_field()` so line breaks
     * are preserved. Arrays are traversed recursively and scalars such as
     * integers, floats, booleans and null values are returned untouched.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitized[$key] = self::sanitizeValue($value);
        }

        return $sanitized;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];

            foreach ($value as $nestedKey => $nestedValue) {
                $result[$nestedKey] = self::sanitizeValue($nestedValue);
            }

            return $result;
        }

        if (is_string($value)) {
            $hasLineBreaks = str_contains($value, "\n") || str_contains($value, "\r");

            return $hasLineBreaks
                ? sanitize_textarea_field($value)
                : sanitize_text_field($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return sanitize_text_field((string) $value);
    }
}
