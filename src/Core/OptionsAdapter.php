<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use FP\Resv\Core\Services\OptionsInterface;
use function is_array;
use function wp_parse_args;

/**
 * Adapter to convert OptionsInterface to Domain\Settings\Options compatible object
 * Note: Cannot extend Options because it's final, so we implement the same interface
 */
final class OptionsAdapter
{
    private OptionsInterface $wrapped;
    
    public function __construct(OptionsInterface $options)
    {
        $this->wrapped = $options;
    }
    
    /**
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $optionName, array $defaults = []): array
    {
        $value = $this->wrapped->get($optionName, []);
        if (!is_array($value)) {
            return $defaults;
        }
        return wp_parse_args($value, $defaults);
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->wrapped->get($key, $default);
    }
    
    public function getField(string $optionName, string $field, mixed $default = null): mixed
    {
        $group = $this->getGroup($optionName);
        return $group[$field] ?? $default;
    }
}

