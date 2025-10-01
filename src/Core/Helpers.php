<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function array_map;
use function ctype_digit;
use function explode;
use function filter_var;
use function str_contains;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function substr_count;
use function substr;
use function trim;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
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
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_FORWARDED',
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

            $allowPrivate = $key === 'REMOTE_ADDR';
            $values = [$value];

            if (
                $key === 'HTTP_X_FORWARDED_FOR'
                || $key === 'HTTP_FORWARDED'
                || $key === 'HTTP_X_REAL_IP'
            ) {
                $values = array_map('trim', explode(',', $value));
            }

            foreach ($values as $part) {
                if ($part === '') {
                    continue;
                }

                $ip = self::extractIpCandidate($part, $allowPrivate);
                if ($ip === '') {
                    continue;
                }

                return $ip;
            }
        }

        return '0.0.0.0';
    }

    private static function extractIpCandidate(string $value, bool $allowPrivate = false): string
    {
        $normalized = self::normalizeIpToken($value);
        if ($normalized === '') {
            return '';
        }

        if ($allowPrivate) {
            return filter_var($normalized, FILTER_VALIDATE_IP) !== false ? $normalized : '';
        }

        return filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            ? $normalized
            : '';
    }

    private static function normalizeIpToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $forPosition = stripos($token, 'for=');
        if ($forPosition !== false) {
            $token = substr($token, $forPosition + 4);
        }

        $token = trim($token, "\"' ");
        if ($token === '') {
            return '';
        }

        $semicolon = strpos($token, ';');
        if ($semicolon !== false) {
            $token = substr($token, 0, $semicolon);
        }

        $token = trim($token, "\"' ");
        if ($token === '') {
            return '';
        }

        if (strlen($token) > 0 && $token[0] === '[') {
            $closing = strpos($token, ']');
            if ($closing !== false) {
                $token = substr($token, 1, $closing - 1);
            }
        }

        $token = trim($token, "\"' ");
        if ($token === '') {
            return '';
        }

        if (str_contains($token, '.') && substr_count($token, ':') === 1) {
            $lastColon = strrpos($token, ':');
            if ($lastColon !== false) {
                $port = substr($token, $lastColon + 1);
                if ($port !== '' && ctype_digit($port)) {
                    $token = substr($token, 0, $lastColon);
                }
            }
        }

        $zonePosition = strpos($token, '%');
        if ($zonePosition !== false && str_contains($token, ':')) {
            $token = substr($token, 0, $zonePosition);
        }

        return trim($token, "\"' ");
    }
}
