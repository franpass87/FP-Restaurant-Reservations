<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function apply_filters;
use function array_filter;
use function array_map;
use function explode;
use function filter_var;
use function in_array;
use function is_array;
use function is_string;
use function trim;
use const FILTER_VALIDATE_IP;

final class Helpers
{
    public static function pluginVersion(): string
    {
        return Plugin::VERSION;
    }

    public static function pluginFile(): string
    {
        return Plugin::$file;
    }

    public static function pluginDir(): string
    {
        return Plugin::$dir;
    }

    public static function pluginUrl(): string
    {
        return Plugin::$url;
    }

    public static function clientIp(): string
    {
        $trustedConfig  = apply_filters('fp_resv_trusted_proxies', null);
        $trustedProxies = null;

        if (is_array($trustedConfig)) {
            $trustedProxies = array_filter(array_map(static function ($value): ?string {
                if (!is_string($value)) {
                    return null;
                }

                return self::normalizeIp($value);
            }, $trustedConfig));
        }

        $remoteIp       = self::normalizeIp($_SERVER['REMOTE_ADDR'] ?? null);
        $allowForwarded = $trustedProxies === null
            || ($remoteIp !== null && in_array($remoteIp, $trustedProxies, true));

        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }

            $value = $_SERVER[$key];
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                if (!$allowForwarded) {
                    continue;
                }

                $parts = array_filter(array_map('trim', explode(',', $value)), static fn ($part): bool => $part !== '');
                foreach ($parts as $part) {
                    $ip = self::normalizeIp($part);
                    if ($ip !== null) {
                        return $ip;
                    }
                }

                continue;
            }

            if ($key !== 'REMOTE_ADDR' && !$allowForwarded) {
                continue;
            }

            $ip = self::normalizeIp($value);
            if ($ip !== null) {
                return $ip;
            }
        }

        if ($remoteIp !== null) {
            return $remoteIp;
        }

        return '0.0.0.0';
    }

    private static function normalizeIp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $ip = filter_var($value, FILTER_VALIDATE_IP);

        return $ip === false ? null : $ip;
    }
}
