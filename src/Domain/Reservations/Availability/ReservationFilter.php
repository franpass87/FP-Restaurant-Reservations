<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use DateTimeImmutable;

/**
 * Filtra prenotazioni e tavoli in base a vari criteri.
 * Estratto da Availability per migliorare la manutenibilità.
 */
final class ReservationFilter
{
    /**
     * Filtra i tavoli disponibili rimuovendo quelli bloccati.
     *
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     * @return array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}>
     */
    public function filterAvailableTables(array $tables, array $blockedTables): array
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
     * Filtra le prenotazioni che si sovrappongono con uno slot.
     *
     * @param array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}> $reservations
     * @return array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}>
     */
    public function filterOverlapping(array $reservations, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): array
    {
        $overlapping = [];
        foreach ($reservations as $reservation) {
            if ($reservation['window_start'] < $slotEnd && $reservation['window_end'] > $slotStart) {
                $overlapping[] = $reservation;
            }
        }

        return $overlapping;
    }
}















