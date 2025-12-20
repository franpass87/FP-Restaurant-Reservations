<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function sort;
use function strcmp;
use function usort;

/**
 * Genera preview delle chiusure per un intervallo di date.
 * Estratto da Service.php per migliorare modularità.
 */
final class PreviewGenerator
{
    private const MAX_PREVIEW_DAYS = 120;

    public function __construct(
        private readonly RecurrenceHandler $recurrenceHandler
    ) {
    }

    /**
     * @param array<int, Model> $models
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function generate(DateTimeImmutable $start, DateTimeImmutable $end, array $models, array $filters = []): array
    {
        if ($end < $start) {
            throw new InvalidArgumentException('The end date must be after the start date.');
        }

        $diff = $start->diff($end);
        if ($diff->days !== false && $diff->days > self::MAX_PREVIEW_DAYS) {
            throw new InvalidArgumentException(sprintf(
                'Seleziona un intervallo inferiore o uguale a %d giorni per la preview.',
                self::MAX_PREVIEW_DAYS
            ));
        }

        $events          = [];
        $blockedHours    = 0.0;
        $capacityPercent = [];
        $scopeHits       = [];
        $specialHours    = 0;

        foreach ($models as $model) {
            $occurrences = $this->recurrenceHandler->expandOccurrences($model, $start, $end);
            foreach ($occurrences as $occurrence) {
                $events[] = [
                    'id'                => $model->id,
                    'scope'             => $model->scope,
                    'type'              => $model->type,
                    'start'             => $occurrence['start']->format(DateTimeInterface::ATOM),
                    'end'               => $occurrence['end']->format(DateTimeInterface::ATOM),
                    'note'              => $model->note,
                    'priority'          => $model->priority,
                    'capacity_override' => $model->capacityOverride,
                    'active'            => $model->active,
                ];

                $scopeHits[$model->scope] = ($scopeHits[$model->scope] ?? 0) + 1;

                if ($model->type === 'full' && $model->scope === 'restaurant') {
                    $blockedHours += ($occurrence['end']->getTimestamp() - $occurrence['start']->getTimestamp()) / 3600;
                }

                if ($model->capacityOverride !== null && array_key_exists('percent', $model->capacityOverride)) {
                    $capacityPercent[] = (int) $model->capacityOverride['percent'];
                }

                if ($model->type === 'special_hours') {
                    ++$specialHours;
                }
            }
        }

        usort(
            $events,
            static function (array $a, array $b): int {
                if ($a['start'] === $b['start']) {
                    return $b['priority'] <=> $a['priority'];
                }

                return strcmp($a['start'], $b['start']);
            }
        );

        sort($capacityPercent);

        return [
            'range'   => [
                'start' => $start->format(DateTimeInterface::ATOM),
                'end'   => $end->format(DateTimeInterface::ATOM),
            ],
            'events'  => $events,
            'summary' => [
                'total_events'       => count($events),
                'blocked_hours'      => round($blockedHours, 2),
                'capacity_reduction' => [
                    'count' => count($capacityPercent),
                    'min'   => $capacityPercent[0] ?? null,
                    'max'   => $capacityPercent !== [] ? $capacityPercent[count($capacityPercent) - 1] : null,
                ],
                'special_hours'      => $specialHours,
                'impacted_scopes'    => array_map('intval', $scopeHits),
            ],
        ];
    }
}
















