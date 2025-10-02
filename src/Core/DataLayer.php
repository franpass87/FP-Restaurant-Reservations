<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use InvalidArgumentException;
use function apply_filters;
use function headers_sent;
use function is_array;
use function is_ssl;
use function is_string;
use function json_decode;
use function max;
use function sanitize_text_field;
use function setcookie;
use function time;
use function trim;
use function wp_json_encode;
use const DAY_IN_SECONDS;

final class DataLayer
{
    private const UTM_COOKIE = 'fp_resv_utm';

    /**
     * @var array<int, array<string, mixed>>
     */
    private static $queue = [];

    /**
     * @return array<string, mixed>
     */
    public static function basePayload(): array
    {
        return [
            'plugin'  => 'fp-restaurant-reservations',
            'version' => Plugin::VERSION,
            'consent' => Consent::all(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function push(array $payload): array
    {
        if (!isset($payload['event']) || !is_string($payload['event']) || trim($payload['event']) === '') {
            throw new InvalidArgumentException('DataLayer events require an "event" key.');
        }

        $event = array_merge(self::basePayload(), $payload);
        self::$queue[] = $event;

        return $event;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function consume(): array
    {
        $events     = self::$queue;
        self::$queue = [];

        return $events;
    }

    public static function hasEvents(): bool
    {
        return self::$queue !== [];
    }

    /**
     * @param array<string, string> $values
     */
    public static function storeAttribution(array $values, int $ttlDays): void
    {
        $allowedKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'msclkid', 'ttclid'];
        $data        = [];

        foreach ($allowedKeys as $key) {
            if (!isset($values[$key])) {
                continue;
            }

            $value = sanitize_text_field($values[$key]);
            if ($value === '') {
                continue;
            }

            $data[$key] = $value;
        }

        if ($data === []) {
            return;
        }

        $encoded = wp_json_encode($data);
        if (!is_string($encoded) || headers_sent()) {
            return;
        }

        $expiry = $ttlDays > 0 ? time() + (max(0, $ttlDays) * DAY_IN_SECONDS) : 0;

        setcookie(
            self::UTM_COOKIE,
            $encoded,
            [
                'expires'  => $expiry,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );

        $_COOKIE[self::UTM_COOKIE] = $encoded;
    }

    /**
     * @return array<string, string>
     */
    public static function attribution(): array
    {
        $cookie = $_COOKIE[self::UTM_COOKIE] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return [];
        }

        $decoded = json_decode($cookie, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $result[(string) $key] = sanitize_text_field($value);
        }

        return apply_filters('fp_resv_tracking_attribution', $result);
    }
}
