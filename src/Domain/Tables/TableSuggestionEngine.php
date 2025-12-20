<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

use function array_map;
use function count;
use function implode;
use function strtolower;
use function usort;

/**
 * Gestisce la logica di suggerimento tavoli.
 * Estratto da LayoutService.php per migliorare modularità.
 */
final class TableSuggestionEngine
{
    public function __construct(
        private readonly CapacityCalculator $capacityCalculator
    ) {
    }

    /**
     * @param array<int, Table> $tables
     *
     * @return array{best: array<string, mixed>|null, alternatives: array<int, array<string, mixed>>}
     */
    public function buildSuggestions(array $tables, int $party, int $maxTables): array
    {
        $best = null;
        $alternatives = [];

        $grouped = [];
        foreach ($tables as $table) {
            if ($table->joinGroup !== null) {
                $grouped[$table->joinGroup][] = $table;
            }
        }

        // Evaluate single tables first.
        foreach ($tables as $table) {
            $capacity = $this->capacityCalculator->calculateCapacity([$table]);
            if ($capacity['max'] < $party) {
                continue;
            }

            $score = $this->scoreSuggestion($capacity, $party, 1);
            $suggestion = $this->formatSuggestion([$table], $capacity, $score, $table->joinGroup);
            $this->maybeRegisterSuggestion($suggestion, $best, $alternatives);
        }

        // Evaluate join groups.
        foreach ($grouped as $groupCode => $groupTables) {
            $capacity = $this->capacityCalculator->calculateCapacity($groupTables);
            if ($capacity['max'] < $party) {
                continue;
            }

            $score = $this->scoreSuggestion($capacity, $party, count($groupTables));
            $suggestion = $this->formatSuggestion($groupTables, $capacity, $score, $groupCode);
            $this->maybeRegisterSuggestion($suggestion, $best, $alternatives);
        }

        // Greedy fallback combinations up to max tables.
        $sorted = $tables;
        usort($sorted, static function (Table $a, Table $b): int {
            return ($b->seatsStd ?? $b->seatsMax ?? 0) <=> ($a->seatsStd ?? $a->seatsMax ?? 0);
        });

        $selection = [];
        $capacity = ['min' => 0, 'std' => 0, 'max' => 0];
        foreach ($sorted as $table) {
            if (count($selection) >= $maxTables) {
                break;
            }

            $selection[] = $table;
            $capacity = $this->capacityCalculator->calculateCapacity($selection);
            if ($capacity['max'] >= $party) {
                $score = $this->scoreSuggestion($capacity, $party, count($selection));
                $suggestion = $this->formatSuggestion($selection, $capacity, $score, null);
                $this->maybeRegisterSuggestion($suggestion, $best, $alternatives);
                break;
            }
        }

        return [
            'best'         => $best,
            'alternatives' => array_values($alternatives),
        ];
    }

    /**
     * @param array<int, Table> $tables
     * @param array{min: int, std: int, max: int} $capacity
     *
     * @return array<string, mixed>
     */
    private function formatSuggestion(array $tables, array $capacity, float $score, ?string $groupCode): array
    {
        $roomId = $tables[0]->roomId ?? 0;

        return [
            'room_id'    => $roomId,
            'table_ids'  => array_map(static fn (Table $table): int => $table->id, $tables),
            'join_group' => $groupCode,
            'capacity'   => $capacity,
            'score'      => $score,
        ];
    }

    /**
     * @param array<string, mixed> $suggestion
     * @param array<string, mixed>|null $best
     * @param array<string, array<string, mixed>> $alternatives
     */
    private function maybeRegisterSuggestion(array $suggestion, ?array &$best, array &$alternatives): void
    {
        $key = implode('-', $suggestion['table_ids']);
        if (isset($alternatives[$key])) {
            return;
        }

        if ($best === null || $suggestion['score'] < $best['score']) {
            if ($best !== null) {
                $alternatives[implode('-', $best['table_ids'])] = $best;
            }

            $best = $suggestion;

            return;
        }

        $alternatives[$key] = $suggestion;
    }

    /**
     * @param array{min: int, std: int, max: int} $capacity
     */
    private function scoreSuggestion(array $capacity, int $party, int $tablesCount): float
    {
        $std = $capacity['std'] > 0 ? $capacity['std'] : $capacity['max'];
        if ($std <= 0) {
            return 1000.0;
        }

        $overCapacity = max(0, $std - $party);
        return $overCapacity + ($tablesCount * 0.1);
    }
}
















