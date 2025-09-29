<?php

declare(strict_types=1);

namespace FP\Resv\Core;

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

            $value = trim((string) $_SERVER[$key]);
            if ($value === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $value));
                $value = $parts[0] ?? '';
            }

            if ($value !== '') {
                return $value;
            }
        }

        return '0.0.0.0';
    }
}
