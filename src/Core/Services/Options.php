<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

use function get_option;
use function update_option;
use function delete_option;
use function get_alloptions;

/**
 * Options Service
 * 
 * Provides abstraction for WordPress options with prefix support.
 *
 * @package FP\Resv\Core\Services
 */
final class Options implements OptionsInterface
{
    private const OPTION_PREFIX = 'fp_resv_';
    
    /**
     * Get an option value
     * 
     * @param string $key Option key
     * @param mixed $default Default value if option not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $fullKey = $this->getFullKey($key);
        return get_option($fullKey, $default);
    }
    
    /**
     * Set an option value
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @param bool $autoload Whether to autoload the option
     * @return bool Success status
     */
    public function set(string $key, $value, bool $autoload = true): bool
    {
        $fullKey = $this->getFullKey($key);
        return update_option($fullKey, $value, $autoload);
    }
    
    /**
     * Delete an option
     * 
     * @param string $key Option key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        $fullKey = $this->getFullKey($key);
        return delete_option($fullKey);
    }
    
    /**
     * Check if an option exists
     * 
     * @param string $key Option key
     * @return bool
     */
    public function has(string $key): bool
    {
        $fullKey = $this->getFullKey($key);
        return get_option($fullKey, '__FP_RESV_NOT_FOUND__') !== '__FP_RESV_NOT_FOUND__';
    }
    
    /**
     * Get all options with a specific prefix
     * 
     * @param string $prefix Option key prefix
     * @return array<string, mixed>
     */
    public function getAll(string $prefix): array
    {
        $fullPrefix = $this->getFullKey($prefix);
        $allOptions = get_alloptions();
        $filtered = [];
        
        foreach ($allOptions as $key => $value) {
            if (strpos($key, $fullPrefix) === 0) {
                // Remove prefix from key
                $shortKey = substr($key, strlen(self::OPTION_PREFIX));
                $filtered[$shortKey] = $value;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get full option key with prefix
     * 
     * @param string $key Option key
     * @return string Full option key
     */
    private function getFullKey(string $key): string
    {
        // Don't double-prefix
        if (strpos($key, self::OPTION_PREFIX) === 0) {
            return $key;
        }
        
        return self::OPTION_PREFIX . $key;
    }
}














