<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use function array_fill;
use function array_map;
use function array_merge;
use function implode;
use function is_array;
use function json_decode;
use function max;
use function trim;
use function wp_cache_get;
use function wp_cache_set;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use const ARRAY_A;
use wpdb;

/**
 * Carica dati necessari per il calcolo della disponibilità.
 * Estratto da Availability.php per migliorare modularità.
 */
final class DataLoader
{

    public function __construct(
        private readonly wpdb $wpdb
    ) {
    }

    /**
     * @return array<int, array{id:int, capacity:int}>
     */
    public function loadRooms(?int $roomId): array
    {
        $cacheKey = 'fp_resv_rooms_' . ($roomId ?? 'all');
        $cached = wp_cache_get($cacheKey, 'fp_resv');

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $table = $this->wpdb->prefix . 'fp_rooms';
        $where = 'active = 1';
        if ($roomId !== null) {
            $where .= $this->wpdb->prepare(' AND id = %d', $roomId);
        }

        $rows = $this->wpdb->get_results("SELECT id, capacity FROM {$table} WHERE {$where}", ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $rooms = [];
        foreach ($rows as $row) {
            $rooms[(int) $row['id']] = [
                'id'       => (int) $row['id'],
                'capacity' => max(0, (int) $row['capacity']),
            ];
        }

        wp_cache_set($cacheKey, $rooms, 'fp_resv', 300); // 5 minutes

        return $rooms;
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     room_id:int,
     *     capacity:int,
     *     seats_min:int,
     *     seats_max:int,
     *     join_group:string|null
     * }>
     */
    public function loadTables(?int $roomId): array
    {
        $cacheKey = 'fp_resv_tables_' . ($roomId ?? 'all');
        $cached = wp_cache_get($cacheKey, 'fp_resv');

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $table = $this->wpdb->prefix . 'fp_tables';
        $where = 'active = 1';
        if ($roomId !== null) {
            $where .= $this->wpdb->prepare(' AND room_id = %d', $roomId);
        }

        $rows = $this->wpdb->get_results("SELECT id, room_id, seats_min, seats_std, seats_max, join_group FROM {$table} WHERE {$where}", ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $tables = [];
        foreach ($rows as $row) {
            $seatsMin = max(0, (int) ($row['seats_min'] ?? 0));
            $seatsStd = max($seatsMin, (int) ($row['seats_std'] ?? 0));
            $seatsMax = max($seatsStd, (int) ($row['seats_max'] ?? 0));
            $capacity = $seatsMax > 0 ? $seatsMax : ($seatsStd > 0 ? $seatsStd : $seatsMin);

            $tables[(int) $row['id']] = [
                'id'         => (int) $row['id'],
                'room_id'    => (int) $row['room_id'],
                'capacity'   => max(0, $capacity),
                'seats_min'  => $seatsMin > 0 ? $seatsMin : 1,
                'seats_max'  => $seatsMax > 0 ? $seatsMax : max(1, $capacity),
                'join_group' => $row['join_group'] !== null ? trim((string) $row['join_group']) : null,
            ];
        }

        wp_cache_set($cacheKey, $tables, 'fp_resv', 300); // 5 minutes

        return $tables;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadClosures(DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, DateTimeZone $timezone): array
    {
        $table = $this->wpdb->prefix . 'fp_closures';
        $sql   = $this->wpdb->prepare(
            "SELECT id, scope, room_id, table_id, type, start_at, end_at, recurrence_json, capacity_override_json FROM {$table} WHERE active = 1 AND ((start_at <= %s AND end_at >= %s) OR recurrence_json IS NOT NULL AND recurrence_json <> '')",
            $dayEnd->format('Y-m-d H:i:s'),
            $dayStart->format('Y-m-d H:i:s')
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $closures = [];
        foreach ($rows as $row) {
            $start = new DateTimeImmutable((string) $row['start_at'], $timezone);
            $end   = new DateTimeImmutable((string) $row['end_at'], $timezone);

            $recurrence = null;
            if (!empty($row['recurrence_json'])) {
                $decoded = json_decode((string) $row['recurrence_json'], true);
                $recurrence = is_array($decoded) ? $decoded : null;
            }

            $capacityOverride = null;
            if (!empty($row['capacity_override_json'])) {
                $decoded = json_decode((string) $row['capacity_override_json'], true);
                if (is_array($decoded)) {
                    $capacityOverride = $decoded;
                }
            }

            $closures[] = [
                'id'                 => (int) $row['id'],
                'scope'              => (string) $row['scope'],
                'room_id'            => $row['room_id'] !== null ? (int) $row['room_id'] : null,
                'table_id'           => $row['table_id'] !== null ? (int) $row['table_id'] : null,
                'type'               => (string) $row['type'],
                'start'              => $start,
                'end'                => $end,
                'recurrence'         => $recurrence,
                'capacity_override'  => $capacityOverride,
            ];
        }

        return $closures;
    }

    /**
     * Conta le prenotazioni attive per una data specifica.
     *
     * @param string $date Data nel formato Y-m-d
     * @return int Numero di prenotazioni attive nella giornata
     */
    public function countDailyActiveReservations(string $date): int
    {
        $table = $this->wpdb->prefix . 'fp_reservations';
        $activeStatuses = ReservationStatuses::ACTIVE_FOR_AVAILABILITY;
        $statusPlaceholders = implode(',', array_fill(0, count($activeStatuses), '%s'));

        $sql = "SELECT COUNT(*) FROM {$table} WHERE date = %s AND status IN ({$statusPlaceholders})";
        $params = array_merge([$date], $activeStatuses);

        $count = $this->wpdb->get_var($this->wpdb->prepare($sql, ...$params));

        return $count !== null ? (int) $count : 0;
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     party:int,
     *     table_id:int|null,
     *     room_id:int|null,
     *     window_start:DateTimeImmutable,
     *     window_end:DateTimeImmutable
     * }>
     */
    public function loadReservations(
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEnd,
        ?int $roomId,
        int $turnoverMinutes,
        int $bufferMinutes,
        DateTimeZone $timezone
    ): array {
        $table    = $this->wpdb->prefix . 'fp_reservations';
        
        // Usa placeholders per gli status invece di concatenazione
        $activeStatuses = ReservationStatuses::ACTIVE_FOR_AVAILABILITY;
        $statusPlaceholders = implode(',', array_fill(0, count($activeStatuses), '%s'));
        $sql = "SELECT id, party, room_id, table_id, time FROM {$table} WHERE date = %s AND status IN ({$statusPlaceholders})";
        
        $params = array_merge([$dayStart->format('Y-m-d')], $activeStatuses);
        $preparedSql = $this->wpdb->prepare($sql, ...$params);

        $rows = $this->wpdb->get_results($preparedSql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $reservations = [];
        foreach ($rows as $row) {
            if ($roomId !== null && $row['room_id'] !== null && (int) $row['room_id'] !== $roomId) {
                continue;
            }

            $time       = (string) $row['time'];
            $start      = new DateTimeImmutable($dayStart->format('Y-m-d') . ' ' . $time, $timezone);
            $windowFrom = $start->sub(new DateInterval('PT' . $bufferMinutes . 'M'));
            $windowTo   = $start->add(new DateInterval('PT' . ($turnoverMinutes + $bufferMinutes) . 'M'));

            $reservations[] = [
                'id'           => (int) $row['id'],
                'party'        => max(1, (int) $row['party']),
                'table_id'     => $row['table_id'] !== null ? (int) $row['table_id'] : null,
                'room_id'      => $row['room_id'] !== null ? (int) $row['room_id'] : null,
                'window_start' => $windowFrom,
                'window_end'   => $windowTo,
            ];
        }

        return $reservations;
    }
}

