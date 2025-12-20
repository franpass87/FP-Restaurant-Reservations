<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use function max;
use function min;

/**
 * Determina lo stato di disponibilità di uno slot.
 * Estratto da Availability per migliorare la manutenibilità.
 */
final class SlotStatusDeterminer
{
    /**
     * Determina lo stato di disponibilità.
     */
    public function determine(int $capacity, int $allowedCapacity, int $party): string
    {
        if ($capacity <= 0 || $capacity < $party) {
            return 'full';
        }

        $normalizedAllowed = max($allowedCapacity, 0);
        // Se non c'è un limite configurato (allowedCapacity = 0), usa la capacità effettiva
        if ($normalizedAllowed === 0) {
            // Verifica comunque se la capacità è sufficiente per il party
            // e ritorna 'limited' se è sotto il 50% della capacità totale disponibile
            if ($capacity < $party * 2) {
                return 'limited';
            }
            return 'available';
        }

        $ratio = max(0, min(1, $capacity / $normalizedAllowed));
        if ($ratio <= 0.25) {
            return 'limited';
        }

        return 'available';
    }
}















