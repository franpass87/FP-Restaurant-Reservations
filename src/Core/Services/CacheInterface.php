<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

/**
 * Cache Interface
 * 
 * Provides abstraction for caching operations.
 *
 * @package FP\Resv\Core\Services
 */
interface CacheInterface
{
    /**
     * Get a value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null);
    
    /**
     * Set a value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool Success status
     */
    public function set(string $key, $value, int $ttl = 0): bool;
    
    /**
     * Delete a value from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool;
    
    /**
     * Flush cache (optionally by group)
     * 
     * @param string $group Cache group (empty = all)
     * @return bool Success status
     */
    public function flush(string $group = ''): bool;
    
    /**
     * Check if a key exists in cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool;
}














