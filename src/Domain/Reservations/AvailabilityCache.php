<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use function add_action;
use function array_values;
use function delete_option;
use function delete_transient;
use function get_option;
use function in_array;
use function is_array;
use function set_transient;
use function update_option;

final class AvailabilityCache
{
    private const OPTION_KEY = 'fp_resv_availability_cache_keys';

    /**
     * @param array<string, mixed> $value
     */
    public static function remember(string $key, array $value, int $expiration): void
    {
        set_transient($key, $value, $expiration);

        $keys = get_option(self::OPTION_KEY, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            update_option(self::OPTION_KEY, array_values($keys), false);
        }
    }

    public static function flush(): void
    {
        $keys = get_option(self::OPTION_KEY, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            delete_transient((string) $key);
        }

        delete_option(self::OPTION_KEY);
    }

    public static function bootstrap(): void
    {
        $callbacks = [self::class, 'flush'];

        add_action('fp_resv_reservation_created', $callbacks, 10, 0);
        add_action('fp_resv_reservation_updated', $callbacks, 10, 0);
        add_action('fp_resv_reservation_moved', $callbacks, 10, 0);
        add_action('fp_resv_reservation_status_changed', $callbacks, 10, 0);
        add_action('fp_resv_event_booked', $callbacks, 10, 0);
        add_action('fp_resv_closure_saved', $callbacks, 10, 0);
        add_action('fp_resv_closure_deleted', $callbacks, 10, 0);
    }
}
