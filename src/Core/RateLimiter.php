<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class RateLimiter
{
    /**
     * @return array{allowed: bool, remaining: int, retry_after: int, limit: int}
     */
    public static function check(string $key, int $limit, int $seconds): array
    {
        $now       = time();
        $cacheKey  = 'fp_resv_rl_' . md5($key);
        $window    = get_transient($cacheKey);

        if (!is_array($window)) {
            $window = [];
        }

        $startedAt = (int) ($window['started_at'] ?? 0);
        if ($startedAt <= 0 || ($startedAt + $seconds) <= $now) {
            $startedAt = $now;
            $window    = [
                'count'      => 0,
                'started_at' => $startedAt,
            ];
        }

        $count   = (int) ($window['count'] ?? 0);
        $allowed = $count < $limit;

        if ($allowed) {
            $count++;
            $window['count'] = $count;
        }

        $window['started_at'] = $startedAt;

        $expiresAt  = $startedAt + $seconds;
        $retryAfter = max(0, $expiresAt - $now);
        $remaining  = max(0, $limit - $count);

        $ttl = max(1, $retryAfter === 0 ? $seconds : $retryAfter);
        set_transient($cacheKey, $window, $ttl);

        return [
            'allowed'     => $allowed,
            'remaining'   => $remaining,
            'retry_after' => $allowed ? 0 : $retryAfter,
            'limit'       => $limit,
        ];
    }

    public static function allow(string $key, int $limit, int $seconds): bool
    {
        $result = self::check($key, $limit, $seconds);

        return $result['allowed'];
    }
}
