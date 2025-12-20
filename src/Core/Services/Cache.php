<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

use function get_transient;
use function set_transient;
use function delete_transient;
use function wp_cache_get;
use function wp_cache_set;
use function wp_cache_delete;
use function wp_cache_flush_group;

/**
 * Cache Service
 * 
 * Provides abstraction for WordPress transients and object cache.
 * Uses object cache (wp_cache) when available, falls back to transients.
 *
 * @package FP\Resv\Core\Services
 */
final class Cache implements CacheInterface
{
    private const CACHE_GROUP = 'fp_resv';
    private const TRANSIENT_PREFIX = 'fp_resv_';
    
    /**
     * Get a value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // Try object cache first (faster)
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Fallback to transient
        $transientKey = $this->getTransientKey($key);
        $cached = get_transient($transientKey);
        
        if ($cached !== false) {
            // Populate object cache for next request
            wp_cache_set($key, $cached, self::CACHE_GROUP);
            return $cached;
        }
        
        return $default;
    }
    
    /**
     * Set a value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool Success status
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        // Set in object cache
        wp_cache_set($key, $value, self::CACHE_GROUP, $ttl);
        
        // Also set in transient as fallback
        $transientKey = $this->getTransientKey($key);
        return set_transient($transientKey, $value, $ttl);
    }
    
    /**
     * Delete a value from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        // Delete from object cache
        wp_cache_delete($key, self::CACHE_GROUP);
        
        // Delete from transient
        $transientKey = $this->getTransientKey($key);
        return delete_transient($transientKey);
    }
    
    /**
     * Flush cache (optionally by group)
     * 
     * @param string $group Cache group (empty = all)
     * @return bool Success status
     */
    public function flush(string $group = ''): bool
    {
        if ($group === '' || $group === self::CACHE_GROUP) {
            // Flush object cache group
            wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        // Note: WordPress doesn't provide a way to flush all transients
        // by prefix, so we can't flush transients efficiently here
        // Individual keys should be deleted explicitly
        
        return true;
    }
    
    /**
     * Check if a key exists in cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__FP_RESV_NOT_FOUND__') !== '__FP_RESV_NOT_FOUND__';
    }
    
    /**
     * Get transient key from cache key
     * 
     * @param string $key Cache key
     * @return string Transient key
     */
    private function getTransientKey(string $key): string
    {
        // Ensure key is valid for WordPress transients (max 172 chars)
        $transientKey = self::TRANSIENT_PREFIX . $key;
        
        if (strlen($transientKey) > 172) {
            // Hash long keys
            $transientKey = self::TRANSIENT_PREFIX . md5($key);
        }
        
        return $transientKey;
    }
}














