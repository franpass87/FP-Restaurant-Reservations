<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use function get_option;
use function is_array;
use function wp_parse_args;

final class Options
{
    public function get(string $key, mixed $default = null): mixed
    {
        return get_option($key, $default);
    }

    /**
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $optionName, array $defaults = []): array
    {
        $value = get_option($optionName, []);
        if (!is_array($value)) {
            return $defaults;
        }

        return wp_parse_args($value, $defaults);
    }

    public function getField(string $optionName, string $field, mixed $default = null): mixed
    {
        $group = $this->getGroup($optionName);

        return $group[$field] ?? $default;
    }
}
