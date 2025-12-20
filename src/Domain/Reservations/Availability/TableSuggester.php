<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use function array_map;
use function array_slice;
use function count;

/**
 * Suggerisce tavoli disponibili per una prenotazione.
 * Estratto da Availability.php per migliorare modularità.
 */
final class TableSuggester
{
    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $availableTables
     * @param int $party
     * @param string $mergeStrategy
     * @return array<int, array{tables:int[], seats:int, type:string}>
     */
    public function suggestTables(array $availableTables, int $party, string $mergeStrategy): array
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
}
















