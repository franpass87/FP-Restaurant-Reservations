<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeInterface;

/**
 * Esporta un Model in array per risposte REST.
 * Estratto da REST per migliorare la manutenibilità.
 */
final class ClosuresModelExporter
{
    /**
     * Converte un Model in array per la risposta REST.
     *
     * @return array<string, mixed>
     */
    public function export(Model $model): array
    {
        return [
            'id'                => $model->id,
            'scope'             => $model->scope,
            'type'              => $model->type,
            'start_at'          => $model->startAt->format(DateTimeInterface::ATOM),
            'end_at'            => $model->endAt->format(DateTimeInterface::ATOM),
            'room_id'           => $model->roomId,
            'table_id'          => $model->tableId,
            'note'              => $model->note,
            'priority'          => $model->priority,
            'capacity_override' => $model->capacityOverride,
            'active'            => $model->active,
            'recurrence'        => $model->recurrence,
        ];
    }
}















