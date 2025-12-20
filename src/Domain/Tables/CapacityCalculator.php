<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

use function array_sum;
use function count;
use function max;

/**
 * Calcola la capacità di un insieme di tavoli.
 * Estratto da LayoutService.php per migliorare modularità.
 */
final class CapacityCalculator
{
    /**
     * @param array<int, Table> $tables
     *
     * @return array{min: int, std: int, max: int}
     */
    public function calculateCapacity(array $tables): array
    {
        if ($tables === []) {
            return ['min' => 0, 'std' => 0, 'max' => 0];
        }

        $min = 0;
        $std = 0;
        $max = 0;

        foreach ($tables as $table) {
            $min += $table->seatsMin ?? 0;
            $std += $table->seatsStd ?? $table->seatsMax ?? 0;
            $max += $table->seatsMax ?? 0;
        }

        return [
            'min' => max(0, $min),
            'std' => max(0, $std),
            'max' => max(0, $max),
        ];
    }
}
















