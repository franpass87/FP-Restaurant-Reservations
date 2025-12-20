<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

/**
 * Options Interface
 * 
 * Provides abstraction for WordPress options management.
 *
 * @package FP\Resv\Core\Services
 */
interface OptionsInterface
{
    /**
     * Get an option value
     * 
     * @param string $key Option key
     * @param mixed $default Default value if option not found
     * @return mixed
     */
    public function get(string $key, $default = null);
    
    /**
     * Set an option value
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @param bool $autoload Whether to autoload the option
     * @return bool Success status
     */
    public function set(string $key, $value, bool $autoload = true): bool;
    
    /**
     * Delete an option
     * 
     * @param string $key Option key
     * @return bool Success status
     */
    public function delete(string $key): bool;
    
    /**
     * Check if an option exists
     * 
     * @param string $key Option key
     * @return bool
     */
    public function has(string $key): bool;
    
    /**
     * Get all options with a specific prefix
     * 
     * @param string $prefix Option key prefix
     * @return array<string, mixed>
     */
    public function getAll(string $prefix): array;
}














