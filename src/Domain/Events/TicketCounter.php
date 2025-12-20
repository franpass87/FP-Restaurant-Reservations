<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use FP\Resv\Domain\Reservations\ReservationStatuses;
use wpdb;
use function array_fill;
use function array_merge;
use function count;
use function implode;

/**
 * Conta i ticket attivi per un evento.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class TicketCounter
{
    /** @var array<int, string> */
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_EVENTS;

    public function __construct(
        private readonly wpdb $wpdb
    ) {
    }

    /**
     * Conta i ticket attivi per un evento.
     */
    public function count(int $eventId): int
    {
        if ($eventId <= 0) {
            return 0;
        }

        $statuses     = implode(', ', array_fill(0, count(self::ACTIVE_STATUSES), '%s'));
        $placeholders = array_merge([$eventId], self::ACTIVE_STATUSES);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->ticketsTable()} WHERE event_id = %d AND status IN ({$statuses})",
            $placeholders
        );

        $count = $this->wpdb->get_var($sql);

        return $count !== null ? (int) $count : 0;
    }

    /**
     * Ottiene il nome della tabella ticket.
     */
    private function ticketsTable(): string
    {
        return $this->wpdb->prefix . 'fp_tickets';
    }
}















