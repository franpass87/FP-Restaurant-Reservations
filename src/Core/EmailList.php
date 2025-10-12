<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function preg_split;
use function sanitize_email;
use function trim;
use function is_email;

final class EmailList
{
    /**
     * @param mixed $value
     *
     * @return array<int, string>
     */
    public static function parse(mixed $value): array
    {
        if (is_array($value)) {
            $candidates = array_map(static fn ($email): string => trim((string) $email), $value);
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $candidates = array_map('trim', preg_split('/[,;\n]/', $value) ?: []);
        } else {
            return [];
        }

        $valid = [];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $sanitized = sanitize_email($candidate);
            if ($sanitized === '' || !is_email($sanitized)) {
                continue;
            }

            $valid[] = $sanitized;
        }

        return array_values(array_unique($valid));
    }
}
