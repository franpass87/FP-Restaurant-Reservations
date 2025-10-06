<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function wp_cache_delete;
use function wp_cache_flush;
use function delete_transient;

final class CacheManager
{
    /**
     * Invalidate rooms cache.
     * Call this after creating/updating/deleting rooms.
     * 
     * @param int|null $roomId Specific room ID, or null for all rooms
     */
    public static function invalidateRooms(?int $roomId = null): void
    {
        if ($roomId !== null) {
            wp_cache_delete('fp_resv_rooms_' . $roomId, 'fp_resv');
        }
        
        // Always invalidate "all" cache
        wp_cache_delete('fp_resv_rooms_all', 'fp_resv');
        
        Metrics::increment('cache.invalidated', 1, ['type' => 'rooms']);
    }

    /**
     * Invalidate tables cache.
     * Call this after creating/updating/deleting tables.
     * 
     * @param int|null $roomId Room ID to invalidate tables for, or null for all
     */
    public static function invalidateTables(?int $roomId = null): void
    {
        if ($roomId !== null) {
            wp_cache_delete('fp_resv_tables_' . $roomId, 'fp_resv');
        }
        
        // Always invalidate "all" cache
        wp_cache_delete('fp_resv_tables_all', 'fp_resv');
        
        Metrics::increment('cache.invalidated', 1, ['type' => 'tables']);
    }

    /**
     * Invalidate availability cache for a specific date.
     * Call this after creating/updating/deleting reservations or closures.
     * 
     * @param string|null $date Date in Y-m-d format, or null to invalidate all
     */
    public static function invalidateAvailability(?string $date = null): void
    {
        if ($date !== null) {
            // We can't easily target specific availability queries, so we flush the group
            // In production with object cache, this would be more granular
            wp_cache_flush();
        } else {
            wp_cache_flush();
        }
        
        // Also clear transient caches
        // In a real scenario, you'd want to track transient keys
        
        Metrics::increment('cache.invalidated', 1, ['type' => 'availability']);
    }

    /**
     * Invalidate all plugin caches.
     * Use sparingly, typically only after major config changes.
     */
    public static function invalidateAll(): void
    {
        self::invalidateRooms();
        self::invalidateTables();
        self::invalidateAvailability();
        
        // Flush entire wp_cache for this plugin
        wp_cache_flush();
        
        Metrics::increment('cache.invalidated', 1, ['type' => 'all']);
    }

    /**
     * Warm up caches by preloading commonly accessed data.
     * Can be called on cron or after cache invalidation.
     */
    public static function warmUp(): void
    {
        global $wpdb;
        
        $stopTimer = Metrics::timer('cache.warmup');
        
        // Preload rooms
        $table = $wpdb->prefix . 'fp_rooms';
        $rows = $wpdb->get_results("SELECT id, capacity FROM {$table} WHERE active = 1", ARRAY_A);
        
        if (is_array($rows)) {
            $rooms = [];
            foreach ($rows as $row) {
                $rooms[(int) $row['id']] = [
                    'id' => (int) $row['id'],
                    'capacity' => max(0, (int) $row['capacity']),
                ];
            }
            wp_cache_set('fp_resv_rooms_all', $rooms, 'fp_resv', 300);
        }
        
        // Preload tables
        $table = $wpdb->prefix . 'fp_tables';
        $rows = $wpdb->get_results("SELECT id, room_id, seats_min, seats_std, seats_max, join_group FROM {$table} WHERE active = 1", ARRAY_A);
        
        if (is_array($rows)) {
            $tables = [];
            foreach ($rows as $row) {
                $seatsMin = max(0, (int) ($row['seats_min'] ?? 0));
                $seatsStd = max($seatsMin, (int) ($row['seats_std'] ?? 0));
                $seatsMax = max($seatsStd, (int) ($row['seats_max'] ?? 0));
                $capacity = $seatsMax > 0 ? $seatsMax : ($seatsStd > 0 ? $seatsStd : $seatsMin);

                $tables[(int) $row['id']] = [
                    'id' => (int) $row['id'],
                    'room_id' => (int) $row['room_id'],
                    'capacity' => max(0, $capacity),
                    'seats_min' => $seatsMin > 0 ? $seatsMin : 1,
                    'seats_max' => $seatsMax > 0 ? $seatsMax : max(1, $capacity),
                    'join_group' => $row['join_group'] !== null ? trim((string) $row['join_group']) : null,
                ];
            }
            wp_cache_set('fp_resv_tables_all', $tables, 'fp_resv', 300);
        }
        
        $stopTimer();
        Metrics::increment('cache.warmed_up');
    }
}
