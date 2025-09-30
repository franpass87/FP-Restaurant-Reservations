<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use FP\Resv\Domain\Settings\Options;
use InvalidArgumentException;
use function __;
use function array_map;
use function array_slice;
use function array_sum;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function json_decode;
use function max;
use function min;
use function preg_match;
use function preg_split;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;
use const ARRAY_A;
use wpdb;

final class Availability
{
    private const DEFAULT_TIMEZONE = 'Europe/Rome';

    private const DEFAULT_SCHEDULE = [
        'mon' => ['19:00-23:00'],
        'tue' => ['19:00-23:00'],
        'wed' => ['19:00-23:00'],
        'thu' => ['19:00-23:00'],
        'fri' => ['19:00-23:30'],
        'sat' => ['12:30-15:00', '19:00-23:30'],
        'sun' => ['12:30-15:00'],
    ];

    /** @var string[] */
    private const ACTIVE_STATUSES = ['pending', 'confirmed', 'seated'];

    public function __construct(private readonly Options $options, private readonly wpdb $wpdb)
    {
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function findSlots(array $criteria): array
    {
        $dateString = isset($criteria['date']) ? trim((string) $criteria['date']) : '';
        $party      = isset($criteria['party']) ? (int) $criteria['party'] : 0;
        $roomId     = isset($criteria['room']) ? (int) $criteria['room'] : null;

        if ($dateString === '' || !$this->isValidDate($dateString)) {
            throw new InvalidArgumentException(__('La data richiesta non è valida.', 'fp-restaurant-reservations'));
        }

        if ($party <= 0) {
            throw new InvalidArgumentException(__('Il numero di coperti deve essere maggiore di zero.', 'fp-restaurant-reservations'));
        }

        if ($roomId !== null && $roomId <= 0) {
            $roomId = null;
        }

        $timezone = $this->resolveTimezone();
        $dayStart = new DateTimeImmutable($dateString . ' 00:00:00', $timezone);
        $dayEnd   = $dayStart->setTime(23, 59, 59);

        $schedule = $this->resolveSchedule($dayStart);
        if ($schedule === []) {
            return [
                'date'      => $dayStart->format('Y-m-d'),
                'timezone'  => $timezone->getName(),
                'criteria'  => $this->normalizeCriteria($party, $roomId, $criteria),
                'slots'     => [],
                'meta'      => [
                    'has_availability' => false,
                    'reason'           => __('Nessun turno configurato per la data selezionata.', 'fp-restaurant-reservations'),
                ],
            ];
        }

        $slotInterval      = max(5, (int) $this->options->getField('fp_resv_general', 'slot_interval_minutes', '15'));
        $turnoverMinutes   = max($slotInterval, (int) $this->options->getField('fp_resv_general', 'table_turnover_minutes', '120'));
        $bufferMinutes     = max(0, (int) $this->options->getField('fp_resv_general', 'buffer_before_minutes', '15'));
        $maxParallel       = max(1, (int) $this->options->getField('fp_resv_general', 'max_parallel_parties', '8'));
        $waitlistEnabled   = $this->options->getField('fp_resv_general', 'enable_waitlist', '0') === '1';
        $mergeStrategy     = (string) $this->options->getField('fp_resv_rooms', 'merge_strategy', 'smart');
        $defaultRoomCap    = max(1, (int) $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40'));

        $rooms      = $this->loadRooms($roomId);
        $tables     = $this->loadTables($roomId);
        $closures   = $this->loadClosures($dayStart, $dayEnd, $timezone);
        $reservations = $this->loadReservations($dayStart, $dayEnd, $roomId, $turnoverMinutes, $bufferMinutes, $timezone);

        $roomCapacities = $this->aggregateRoomCapacities($rooms, $tables, $defaultRoomCap);
        $slots          = [];

        foreach ($schedule as $window) {
            $startMinute = $window['start'];
            $endMinute   = $window['end'];

            for ($minute = $startMinute; $minute + $turnoverMinutes <= $endMinute; $minute += $slotInterval) {
                $slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
                $slotEnd   = $slotStart->add(new DateInterval('PT' . $turnoverMinutes . 'M'));

                $closureEffect = $this->evaluateClosures($closures, $slotStart, $slotEnd, $roomId);
                if ($closureEffect['status'] === 'blocked') {
                    $slots[] = $this->buildSlotPayload(
                        $slotStart,
                        $slotEnd,
                        'blocked',
                        0,
                        $party,
                        $waitlistEnabled,
                        $closureEffect['reasons'],
                        []
                    );
                    continue;
                }

                $availableTables    = $this->filterAvailableTables($tables, $closureEffect['blocked_tables']);
                $hasPhysicalTables  = $availableTables !== [];
                $overlapping        = $this->filterOverlappingReservations($reservations, $slotStart, $slotEnd);
                $parallelCount      = count($overlapping);
                $unassignedCapacity = 0;

                foreach ($overlapping as $reservation) {
                    if ($reservation['table_id'] !== null) {
                        unset($availableTables[$reservation['table_id']]);
                    } else {
                        $unassignedCapacity += $reservation['party'];
                    }
                }

                if ($parallelCount >= $maxParallel) {
                    $reasons = $closureEffect['reasons'];
                    $reasons[] = __('Limite di prenotazioni parallele raggiunto per lo slot selezionato.', 'fp-restaurant-reservations');

                    $slots[] = $this->buildSlotPayload(
                        $slotStart,
                        $slotEnd,
                        'full',
                        0,
                        $party,
                        $waitlistEnabled,
                        $reasons,
                        []
                    );
                    continue;
                }

                $baseCapacity = $this->resolveCapacityForScope($roomCapacities, $roomId, $hasPhysicalTables);
                $capacity     = $this->applyCapacityReductions(
                    $baseCapacity,
                    $availableTables,
                    $unassignedCapacity,
                    $closureEffect['capacity_percent']
                );

                $status  = $this->determineStatus($capacity, $party, $closureEffect['capacity_percent']);
                $reasons = $closureEffect['reasons'];

                if ($status === 'full' && $waitlistEnabled) {
                    $reasons[] = __('Disponibile solo lista di attesa per questo orario.', 'fp-restaurant-reservations');
                }

                $suggestions = $hasPhysicalTables
                    ? $this->suggestTables($availableTables, $party, $mergeStrategy)
                    : [];

                $slots[] = $this->buildSlotPayload(
                    $slotStart,
                    $slotEnd,
                    $status,
                    $capacity,
                    $party,
                    $waitlistEnabled,
                    $reasons,
                    $suggestions
                );
            }
        }

        return [
            'date'     => $dayStart->format('Y-m-d'),
            'timezone' => $timezone->getName(),
            'criteria' => $this->normalizeCriteria($party, $roomId, $criteria),
            'slots'    => $slots,
            'meta'     => [
                'has_availability' => $this->hasAvailability($slots),
            ],
        ];
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt instanceof DateTimeImmutable;
    }

    private function resolveTimezone(): DateTimeZone
    {
        $tz = (string) $this->options->getField('fp_resv_general', 'restaurant_timezone', self::DEFAULT_TIMEZONE);

        try {
            return new DateTimeZone($tz !== '' ? $tz : self::DEFAULT_TIMEZONE);
        } catch (
            \Exception $e
        ) {
            return new DateTimeZone(self::DEFAULT_TIMEZONE);
        }
    }

    /**
     * @return array<int, array{start:int,end:int}>
     */
    private function resolveSchedule(DateTimeImmutable $day): array
    {
        $raw       = (string) $this->options->getField('fp_resv_general', 'service_hours_definition', '');
        $parsed    = $this->parseScheduleDefinition($raw);
        $dayKey    = strtolower($day->format('D'));
        $schedule  = $parsed[$dayKey] ?? [];

        return $schedule;
    }

    /**
     * @return array<string, array<int, array{start:int,end:int}>>
     */
    private function parseScheduleDefinition(string $raw): array
    {
        $schedule = [];
        $lines    = $raw !== '' ? preg_split('/\n/', $raw) : false;

        if ($lines === false || $lines === []) {
            return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$day, $ranges] = array_map('trim', explode('=', $line, 2));
            $day            = strtolower($day);

            $segments = preg_split('/[|,]/', $ranges) ?: [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end   = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $schedule[$day][] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        if ($schedule === []) {
            return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);
        }

        return $schedule;
    }

    /**
     * @param array<string, string[]> $definition
     *
     * @return array<string, array<int, array{start:int,end:int}>>
     */
    private function normalizeSchedule(array $definition): array
    {
        $normalized = [];
        foreach ($definition as $day => $segments) {
            foreach ($segments as $segment) {
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end   = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $normalized[$day][] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, array{id:int, capacity:int}>
     */
    private function loadRooms(?int $roomId): array
    {
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
    private function loadTables(?int $roomId): array
    {
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

        return $tables;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadClosures(DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, DateTimeZone $timezone): array
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
     * @return array<int, array{
     *     id:int,
     *     party:int,
     *     table_id:int|null,
     *     room_id:int|null,
     *     window_start:DateTimeImmutable,
     *     window_end:DateTimeImmutable
     * }>
     */
    private function loadReservations(
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEnd,
        ?int $roomId,
        int $turnoverMinutes,
        int $bufferMinutes,
        DateTimeZone $timezone
    ): array {
        $table    = $this->wpdb->prefix . 'fp_reservations';
        $statuses = "'" . implode("','", self::ACTIVE_STATUSES) . "'";
        $sql      = $this->wpdb->prepare(
            "SELECT id, party, room_id, table_id, time FROM {$table} WHERE date = %s AND status IN ({$statuses})",
            $dayStart->format('Y-m-d')
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
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

    /**
     * @param array<int, array{id:int, capacity:int}> $rooms
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     *
     * @return array<int, array{capacity:int, table_capacity:int}>
     */
    private function aggregateRoomCapacities(array $rooms, array $tables, int $defaultRoomCap): array
    {
        $capacities = [];
        foreach ($rooms as $room) {
            $capacities[$room['id']] = [
                'capacity'       => max($room['capacity'], $defaultRoomCap),
                'table_capacity' => 0,
            ];
        }

        foreach ($tables as $table) {
            $roomId = $table['room_id'];
            if (!isset($capacities[$roomId])) {
                $capacities[$roomId] = [
                    'capacity'       => $defaultRoomCap,
                    'table_capacity' => 0,
                ];
            }

            $capacities[$roomId]['table_capacity'] += $table['capacity'];
            $capacities[$roomId]['capacity'] = max(
                $capacities[$roomId]['capacity'],
                $capacities[$roomId]['table_capacity']
            );
        }

        return $capacities;
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     *
     * @return array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}>
     */
    private function filterAvailableTables(array $tables, array $blockedTables): array
    {
        if ($blockedTables === []) {
            return $tables;
        }

        foreach ($blockedTables as $tableId) {
            unset($tables[$tableId]);
        }

        return $tables;
    }

    /**
     * @param array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}> $reservations
     *
     * @return array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}>
     */
    private function filterOverlappingReservations(array $reservations, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): array
    {
        $overlapping = [];
        foreach ($reservations as $reservation) {
            if ($reservation['window_start'] < $slotEnd && $reservation['window_end'] > $slotStart) {
                $overlapping[] = $reservation;
            }
        }

        return $overlapping;
    }

    /**
     * @param array<int, array{capacity:int, table_capacity:int}> $roomCapacities
     */
    private function resolveCapacityForScope(array $roomCapacities, ?int $roomId, bool $hasTables): int
    {
        if ($roomId !== null) {
            $capacity = $roomCapacities[$roomId] ?? ['capacity' => 0, 'table_capacity' => 0];

            return $hasTables ? max($capacity['table_capacity'], 0) : max($capacity['capacity'], 0);
        }

        $total = 0;
        foreach ($roomCapacities as $capacity) {
            $total += $hasTables ? $capacity['table_capacity'] : $capacity['capacity'];
        }

        return $total;
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     */
    private function applyCapacityReductions(int $baseCapacity, array $tables, int $unassignedCapacity, int $capacityPercent): int
    {
        $tableCapacity = array_sum(array_map(static fn (array $table): int => $table['capacity'], $tables));
        $capacity      = $tables === [] ? $baseCapacity : $tableCapacity;

        $capacity = max(0, $capacity - $unassignedCapacity);

        if ($capacityPercent < 100) {
            $capacity = (int) floor($capacity * ($capacityPercent / 100));
        }

        return max(0, $capacity);
    }

    private function determineStatus(int $capacity, int $party, int $capacityPercent): string
    {
        if ($capacity <= 0 || $capacity < $party) {
            return 'full';
        }

        if ($capacityPercent < 100 || $capacity <= ($party * 2)) {
            return 'limited';
        }

        return 'available';
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $availableTables
     *
     * @return array<int, array{tables:int[], seats:int, type:string}>
     */
    private function suggestTables(array $availableTables, int $party, string $mergeStrategy): array
    {
        $suggestions = [];

        foreach ($availableTables as $table) {
            if ($party >= $table['seats_min'] && $party <= $table['seats_max']) {
                $suggestions[] = [
                    'tables' => [$table['id']],
                    'seats'  => $table['seats_max'],
                    'type'   => 'single',
                ];
            }
        }

        if ($suggestions !== []) {
            return array_slice($this->sortSuggestions($suggestions), 0, 3);
        }

        if ($mergeStrategy !== 'smart') {
            return [];
        }

        $groups = [];
        foreach ($availableTables as $table) {
            $groupKey = $table['join_group'] ?: 'room_' . $table['room_id'];
            $groups[$groupKey][] = $table;
        }

        foreach ($groups as $tablesGroup) {
            $sorted = $this->sortTablesByCapacity($tablesGroup);
            $set    = [];
            $total  = 0;
            foreach ($sorted as $table) {
                $set[] = $table;
                $total += $table['seats_max'];
                if ($total >= $party) {
                    $suggestions[] = [
                        'tables' => array_map(static fn (array $t): int => $t['id'], $set),
                        'seats'  => $total,
                        'type'   => 'merge',
                    ];
                    break;
                }
            }
        }

        return array_slice($this->sortSuggestions($suggestions), 0, 3);
    }

    /**
     * @param array<int, array{tables:int[], seats:int, type:string}> $suggestions
     *
     * @return array<int, array{tables:int[], seats:int, type:string}>
     */
    private function sortSuggestions(array $suggestions): array
    {
        usort(
            $suggestions,
            static function (array $a, array $b): int {
                if ($a['seats'] === $b['seats']) {
                    return count($a['tables']) <=> count($b['tables']);
                }

                return $a['seats'] <=> $b['seats'];
            }
        );

        return $suggestions;
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     *
     * @return array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}>
     */
    private function sortTablesByCapacity(array $tables): array
    {
        usort(
            $tables,
            static function (array $a, array $b): int {
                if ($a['seats_max'] === $b['seats_max']) {
                    return $a['capacity'] <=> $b['capacity'];
                }

                return $a['seats_max'] <=> $b['seats_max'];
            }
        );

        return $tables;
    }

    /**
     * @param array<int, array<string, mixed>> $closures
     *
     * @return array{status:string, blocked_tables:int[], capacity_percent:int, reasons:string[]}
     */
    private function evaluateClosures(array $closures, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, ?int $roomId): array
    {
        $blockedTables   = [];
        $capacityPercent = 100;
        $status          = 'open';
        $reasons         = [];

        foreach ($closures as $closure) {
            if (!$this->closureApplies($closure, $slotStart, $slotEnd, $roomId)) {
                continue;
            }

            if ($closure['capacity_override'] !== null && isset($closure['capacity_override']['percent'])) {
                $percent = (int) $closure['capacity_override']['percent'];
                $capacityPercent = min($capacityPercent, max(0, min(100, $percent)));
                $reasons[] = sprintf(
                    __('Capienza ridotta al %d%% da una regola di orario speciale.', 'fp-restaurant-reservations'),
                    $capacityPercent
                );
                continue;
            }

            if ($closure['scope'] === 'table' && $closure['table_id'] !== null) {
                $blockedTables[] = $closure['table_id'];
                $reasons[]       = __('Tavolo non disponibile per chiusura programmata.', 'fp-restaurant-reservations');
                continue;
            }

            if ($closure['scope'] === 'room' && $roomId !== null && $closure['room_id'] !== null && $closure['room_id'] !== $roomId) {
                continue;
            }

            $status    = 'blocked';
            $reasons[] = __('Slot non prenotabile per chiusura programmata.', 'fp-restaurant-reservations');
        }

        return [
            'status'          => $status,
            'blocked_tables'  => $blockedTables,
            'capacity_percent'=> $capacityPercent,
            'reasons'         => $reasons,
        ];
    }

    private function closureApplies(array $closure, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, ?int $roomId): bool
    {
        if ($closure['scope'] === 'room' && $roomId !== null && $closure['room_id'] !== null && $closure['room_id'] !== $roomId) {
            return false;
        }

        if ($closure['scope'] === 'table' && $closure['table_id'] === null) {
            return false;
        }

        if ($closure['recurrence'] !== null) {
            return $this->recurringClosureApplies($closure, $slotStart, $slotEnd);
        }

        return $closure['start'] < $slotEnd && $closure['end'] > $slotStart;
    }

    private function recurringClosureApplies(array $closure, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): bool
    {
        $recurrence = $closure['recurrence'];
        $type       = strtolower((string) ($recurrence['type'] ?? ''));
        $until      = isset($recurrence['until']) ? trim((string) $recurrence['until']) : '';
        $from       = isset($recurrence['from']) ? trim((string) $recurrence['from']) : '';

        if ($from !== '') {
            $fromDate = new DateTimeImmutable($from . ' 00:00:00', $slotStart->getTimezone());
            if ($slotStart < $fromDate) {
                return false;
            }
        }

        if ($until !== '') {
            $untilDate = new DateTimeImmutable($until . ' 23:59:59', $slotStart->getTimezone());
            if ($slotStart > $untilDate) {
                return false;
            }
        }

        $dayKey = strtolower($slotStart->format('D'));

        switch ($type) {
            case 'weekly':
                $days = $recurrence['days'] ?? [];
                $days = is_array($days) ? array_map(static fn ($day): string => strtolower((string) $day), $days) : [];
                if (!in_array($dayKey, $days, true) && !in_array((string) $slotStart->format('N'), $days, true)) {
                    return false;
                }
                break;
            case 'daily':
                // Daily applies to all days within the from/until window.
                break;
            case 'monthly':
                $days = $recurrence['days'] ?? [];
                $days = is_array($days) ? $days : [];
                $dayOfMonth = (int) $slotStart->format('j');
                if ($days !== [] && !in_array($dayOfMonth, array_map('intval', $days), true)) {
                    return false;
                }
                break;
            default:
                return false;
        }

        $startTime = $closure['start']->format('H:i:s');
        $endTime   = $closure['end']->format('H:i:s');

        $occurrenceStart = new DateTimeImmutable($slotStart->format('Y-m-d') . ' ' . $startTime, $slotStart->getTimezone());
        $occurrenceEnd   = new DateTimeImmutable($slotStart->format('Y-m-d') . ' ' . $endTime, $slotStart->getTimezone());

        if ($occurrenceEnd <= $occurrenceStart) {
            $occurrenceEnd = $occurrenceEnd->add(new DateInterval('P1D'));
        }

        return $occurrenceStart < $slotEnd && $occurrenceEnd > $slotStart;
    }

    /**
     * @param array<int, array{tables:int[], seats:int, type:string}> $slots
     */
    private function hasAvailability(array $slots): bool
    {
        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot['status'])) {
                continue;
            }

            if (in_array($slot['status'], ['available', 'limited'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    private function normalizeCriteria(int $party, ?int $roomId, array $criteria): array
    {
        $normalized = [
            'party' => $party,
        ];

        if ($roomId !== null) {
            $normalized['room'] = $roomId;
        }

        if (isset($criteria['meal']) && $criteria['meal'] !== '') {
            $normalized['meal'] = (string) $criteria['meal'];
        }

        if (isset($criteria['event_id'])) {
            $normalized['event_id'] = (int) $criteria['event_id'];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{tables:int[], seats:int, type:string}> $suggestions
     *
     * @return array<string, mixed>
     */
    private function buildSlotPayload(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $status,
        int $capacity,
        int $party,
        bool $waitlist,
        array $reasons,
        array $suggestions
    ): array {
        return [
            'start'              => $start->format(DateTimeInterface::ATOM),
            'end'                => $end->format(DateTimeInterface::ATOM),
            'label'              => $start->format('H:i'),
            'status'             => $status,
            'available_capacity' => $capacity,
            'requested_party'    => $party,
            'waitlist_available' => $waitlist && $status === 'full',
            'reasons'            => $reasons,
            'suggested_tables'   => $suggestions,
        ];
    }
}
