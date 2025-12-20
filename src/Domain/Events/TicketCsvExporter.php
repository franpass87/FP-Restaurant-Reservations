<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use function implode;
use function sprintf;

/**
 * Esporta ticket in formato CSV.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class TicketCsvExporter
{
    public function __construct(
        private readonly TicketLister $ticketLister
    ) {
    }

    /**
     * Esporta i ticket di un evento in formato CSV.
     */
    public function export(int $eventId): string
    {
        $tickets = $this->ticketLister->list($eventId);
        if ($tickets === []) {
            return "id;status;email;holder;qr_code\n";
        }

        $lines = ["id;status;email;holder;qr_code"];
        foreach ($tickets as $ticket) {
            $lines[] = sprintf(
                '%d;%s;%s;%s;%s',
                $ticket['id'],
                $ticket['status'],
                $ticket['email'] ?? '',
                $ticket['holder'] ?? '',
                $ticket['qr_code']
            );
        }

        return implode("\n", $lines) . "\n";
    }
}















