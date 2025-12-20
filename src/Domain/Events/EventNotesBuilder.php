<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use function sprintf;
use function trim;
use function __;

/**
 * Costruisce le note per una prenotazione evento.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class EventNotesBuilder
{
    /**
     * Costruisce le note per una prenotazione evento.
     */
    public function build(string $notes, array $event): string
    {
        $notes = trim($notes);
        $eventLabel = sprintf(__('Evento: %s (%s)', 'fp-restaurant-reservations'), $event['title'], $event['start_at']);

        return $notes === '' ? $eventLabel : $notes . "\n" . $eventLabel;
    }
}















