<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_option;
use function delete_option;
use function get_transient;
use function md5;
use function set_transient;
use function time;
use function wp_cache_add;
use function wp_cache_incr;

final class RateLimiter
{
    /**
     * Check if the request is allowed based on rate limits.
     * Uses atomic increment when available to prevent race conditions.
     * 
     * @param string $key Unique identifier for the rate limit window
     * @param int $limit Maximum number of requests allowed
     * @param int $seconds Time window in seconds
     * @return bool True if request is allowed, false if rate limit exceeded
     */
    public static function allow(string $key, int $limit, int $seconds): bool
    {
        $key = 'fp_resv_rl_' . md5($key);

        // Try atomic increment first (available in Redis, Memcached)
        if (function_exists('wp_cache_incr')) {
            return self::allowWithAtomicIncr($key, $limit, $seconds);
        }

        // Fallback to optimistic locking
        return self::allowWithOptimisticLock($key, $limit, $seconds);
    }

    /**
     * Atomic increment implementation.
     */
    private static function allowWithAtomicIncr(string $key, int $limit, int $seconds): bool
    {
        $count = wp_cache_incr($key, 1, 'rate_limit');

        if ($count === false) {
            // Key doesn't exist, initialize it
            wp_cache_add($key, 1, 'rate_limit', $seconds);
            return true;
        }

        return $count <= $limit;
    }

    /**
     * Optimistic locking fallback for systems without atomic increment.
     */
    private static function allowWithOptimisticLock(string $key, int $limit, int $seconds): bool
    {
        $lockKey = $key . '_lock';
        $lockValue = time();

        // Try to acquire lock
        if (!add_option($lockKey, $lockValue, '', false)) {
            // Someone else has the lock, deny the request to be safe
            return false;
        }

        try {
            $windowData = get_transient($key);

            if (!is_array($windowData)) {
                $windowData = [
                    'count' => 0,
                ];
            }

            $count = (int) ($windowData['count'] ?? 0);

            if ($count >= $limit) {
                return false;
            }

            $windowData['count'] = $count + 1;
            set_transient($key, $windowData, $seconds);

            return true;
        } finally {
            // Always release the lock
            delete_option($lockKey);
        }
    }

    /**
     * Get remaining requests in current window.
     * 
     * @param string $key Unique identifier for the rate limit window
     * @param int $limit Maximum number of requests allowed
     * @return int Number of remaining requests (0 if limit reached)
     */
    public static function remaining(string $key, int $limit): int
    {
        $key = 'fp_resv_rl_' . md5($key);
        $windowData = get_transient($key);

        if (!is_array($windowData)) {
            return $limit;
        }

        $count = (int) ($windowData['count'] ?? 0);
        return max(0, $limit - $count);
    }

    /**
     * Reset rate limit for a specific key.
     * 
     * @param string $key Unique identifier for the rate limit window
     */
    public static function reset(string $key): void
    {
        $key = 'fp_resv_rl_' . md5($key);
        delete_transient($key);
    }
}
