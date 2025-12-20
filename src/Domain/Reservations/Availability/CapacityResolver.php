<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use function array_map;
use function array_sum;
use function floor;
use function max;

/**
 * Risolve e calcola le capacità per sale e tavoli.
 * Estratto da Availability per migliorare la manutenibilità.
 */
final class CapacityResolver
{
    /**
     * Aggrega le capacità delle sale e dei tavoli.
     *
     * @param array<int, array{id:int, capacity:int}> $rooms
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     * @return array<int, array{capacity:int, table_capacity:int}>
     */
    public function aggregateRoomCapacities(array $rooms, array $tables, int $defaultRoomCap): array
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

        // FIX: Se non ci sono sale né tavoli, crea una sala virtuale con capacità di default
        if (empty($capacities)) {
            $capacities[0] = [
                'capacity'       => $defaultRoomCap,
                'table_capacity' => 0,
            ];
        }

        return $capacities;
    }

    /**
     * Risolve la capacità per uno scope specifico.
     *
     * @param array<int, array{capacity:int, table_capacity:int}> $roomCapacities
     */
    public function resolveForScope(array $roomCapacities, ?int $roomId, bool $hasTables): int
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
     * Applica riduzioni di capacità.
     *
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     */
    public function applyReductions(int $baseCapacity, array $tables, int $unassignedCapacity, int $capacityPercent): int
    {
        $tableCapacity = array_sum(array_map(static fn (array $table): int => $table['capacity'], $tables));
        $capacity      = $tables === [] ? $baseCapacity : $tableCapacity;

        $capacity = max(0, $capacity - $unassignedCapacity);

        if ($capacityPercent < 100) {
            $capacity = (int) floor($capacity * ($capacityPercent / 100));
        }

        return max(0, $capacity);
    }
}















