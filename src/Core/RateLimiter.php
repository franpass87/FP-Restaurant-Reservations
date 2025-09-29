<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class RateLimiter
{
    public static function allow(string $key, int $limit, int $seconds): bool
    {
        $key        = 'fp_resv_rl_' . md5($key);
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
    }
}
