<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use function absint;
use function apply_filters;
use function delete_option;
use function explode;
use function get_option;
use function hash_equals;
use function hash_hmac;
use function is_array;
use function sanitize_text_field;
use function strtolower;
use function time;
use function trim;
use function update_option;
use function wp_salt;
use const MINUTE_IN_SECONDS;
use const WEEK_IN_SECONDS;

final class ManageTokens
{
    private const OPTION_KEY = 'fp_resv_manage_tokens';

    public static function issue(int $reservationId, string $email): string
    {
        $normalized = strtolower(trim($email));
        $ttl = (int) apply_filters('fp_resv_manage_token_ttl', WEEK_IN_SECONDS, $reservationId, $normalized);
        if ($ttl < MINUTE_IN_SECONDS) {
            $ttl = MINUTE_IN_SECONDS;
        }

        $expiresAt = time() + $ttl;
        $signature = hash_hmac('sha256', self::payload($reservationId, $normalized, $expiresAt), wp_salt('fp_resv_manage'));
        $token = $expiresAt . '.' . $signature;

        self::store($token, $reservationId, $expiresAt);
        self::prune();

        return $token;
    }

    public static function verify(int $reservationId, string $email, string $token): bool
    {
        $token = sanitize_text_field($token);
        if ($token === '') {
            return false;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $expiresAt = absint($parts[0]);
        if ($expiresAt < time()) {
            return false;
        }

        $normalized = strtolower(trim($email));
        $expected = hash_hmac('sha256', self::payload($reservationId, $normalized, $expiresAt), wp_salt('fp_resv_manage'));

        if (!hash_equals($expected, $parts[1])) {
            return false;
        }

        $store = self::load();
        $meta = $store[$token] ?? null;
        if (!is_array($meta)) {
            return false;
        }

        if ((int) ($meta['reservation_id'] ?? 0) !== $reservationId) {
            return false;
        }

        if ((int) ($meta['expires_at'] ?? 0) < time()) {
            return false;
        }

        return true;
    }

    public static function revokeAll(): void
    {
        delete_option(self::OPTION_KEY);
    }

    private static function payload(int $reservationId, string $email, int $expiresAt): string
    {
        return $reservationId . '|' . $email . '|' . $expiresAt;
    }

    private static function store(string $token, int $reservationId, int $expiresAt): void
    {
        $store = self::load();
        $store[$token] = [
            'reservation_id' => $reservationId,
            'expires_at'     => $expiresAt,
        ];

        update_option(self::OPTION_KEY, $store, false);
    }

    /**
     * @return array<string, array<string, int>>
     */
    private static function load(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            return [];
        }

        return $stored;
    }

    private static function prune(): void
    {
        $store = self::load();
        if ($store === []) {
            return;
        }

        $now = time();
        $changed = false;

        foreach ($store as $token => $meta) {
            if (!is_array($meta) || (int) ($meta['expires_at'] ?? 0) < $now) {
                unset($store[$token]);
                $changed = true;
            }
        }

        if ($changed) {
            if ($store === []) {
                delete_option(self::OPTION_KEY);
            } else {
                update_option(self::OPTION_KEY, $store, false);
            }
        }
    }
}
