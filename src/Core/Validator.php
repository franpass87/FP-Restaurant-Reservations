<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     */
    public static function sanitize(array $data): array
    {
        // @todo Implement validation and sanitization.
        return array_map('sanitize_text_field', $data);
    }
}
