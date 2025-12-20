<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use wpdb;
use function array_fill;
use function array_map;
use function array_merge;
use function count;
use function implode;
use function is_array;

/**
 * Elenca i ticket di un evento.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class TicketLister
{
    /** @var array<int, string> */
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_EVENTS;

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly ReservationsRepository $reservationsRepository,
        private readonly CustomersRepository $customers
    ) {
    }

    /**
     * Elenca i ticket di un evento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(int $eventId): array
    {
        $ticketsTable      = $this->ticketsTable();
        $reservationsTable = $this->reservationsRepository->tableName();
        $customersTable    = $this->customers->tableName();

        $statusPlaceholder = implode(', ', array_fill(0, count(self::ACTIVE_STATUSES), '%s'));
        $sql               = $this->wpdb->prepare(
            "SELECT t.*, c.email AS email, CONCAT_WS(' ', c.first_name, c.last_name) AS holder_name " .
            "FROM {$ticketsTable} t " .
            "LEFT JOIN {$reservationsTable} r ON r.id = t.reservation_id " .
            "LEFT JOIN {$customersTable} c ON c.id = r.customer_id " .
            "WHERE t.event_id = %d AND t.status IN ({$statusPlaceholder}) ORDER BY t.created_at ASC",
            array_merge([$eventId], self::ACTIVE_STATUSES)
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => Ticket::fromRow($row)->toArray(), $rows);
    }

    /**
     * Ottiene il nome della tabella ticket.
     */
    private function ticketsTable(): string
    {
        return $this->wpdb->prefix . 'fp_tickets';
    }
}















