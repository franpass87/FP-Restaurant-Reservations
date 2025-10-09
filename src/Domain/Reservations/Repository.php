<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateTimeImmutable;
use FP\Resv\Domain\Reservations\Models\Reservation;
use wpdb;
use function absint;
use function array_key_exists;
use function current_time;
use function gmdate;
use function is_array;
use function wp_timezone;

final class Repository
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'fp_reservations';
    }

    public function customersTableName(): string
    {
        return $this->wpdb->prefix . 'fp_customers';
    }

    public function auditTable(): string
    {
        return $this->wpdb->prefix . 'fp_audit_log';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $defaults = [
            'status'      => 'pending',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
            'date'        => gmdate('Y-m-d'),
            'time'        => gmdate('H:i:s'),
            'party'       => 2,
            'room_id'     => null,
            'table_id'    => null,
            'customer_id' => null,
            'calendar_event_id'    => null,
            'calendar_sync_status' => 'pending',
            'calendar_last_error'  => null,
        ];

        $payload = array_merge($defaults, $data);

        $inserted = $this->wpdb->insert($this->tableName(), $payload);
        if ($inserted === false) {
            throw new \RuntimeException($this->wpdb->last_error ?: 'Unable to create reservation.');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function find(int $id): ?Reservation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $reservation         = new Reservation();
        $reservation->id      = (int) $row['id'];
        $reservation->status  = (string) $row['status'];
        $reservation->date    = (string) $row['date'];
        $reservation->time    = (string) $row['time'];
        $reservation->party   = (int) $row['party'];
        $reservation->email   = (string) ($row['email'] ?? '');
        $reservation->created = new DateTimeImmutable((string) $row['created_at'], wp_timezone());
        if (array_key_exists('calendar_event_id', $row)) {
            $reservation->calendarEventId = $row['calendar_event_id'] !== null
                ? (string) $row['calendar_event_id']
                : null;
        }

        if (array_key_exists('calendar_sync_status', $row)) {
            $reservation->calendarSyncStatus = $row['calendar_sync_status'] !== null
                ? (string) $row['calendar_sync_status']
                : null;
        }

        if (!empty($row['calendar_synced_at'])) {
            $reservation->calendarSyncedAt = new DateTimeImmutable((string) $row['calendar_synced_at']);
        }

    return $reservation;
    }

    public function findByRequestId(string $requestId): ?Reservation
    {
        if ($requestId === '') {
            return null;
        }

        // JOIN con customers per recuperare l'email necessaria per il manage_url
        $sql = 'SELECT r.*, c.email '
            . 'FROM ' . $this->tableName() . ' r '
            . 'LEFT JOIN ' . $this->customersTableName() . ' c ON r.customer_id = c.id '
            . 'WHERE r.request_id = %s ORDER BY r.id DESC LIMIT 1';

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $requestId),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $reservation         = new Reservation();
        $reservation->id      = (int) $row['id'];
        $reservation->status  = (string) $row['status'];
        $reservation->date    = (string) $row['date'];
        $reservation->time    = (string) $row['time'];
        $reservation->party   = (int) $row['party'];
        $reservation->email   = (string) ($row['email'] ?? '');
        $reservation->created = new DateTimeImmutable((string) $row['created_at'], wp_timezone());
        if (array_key_exists('calendar_event_id', $row)) {
            $reservation->calendarEventId = $row['calendar_event_id'] !== null
                ? (string) $row['calendar_event_id']
                : null;
        }

        if (array_key_exists('calendar_sync_status', $row)) {
            $reservation->calendarSyncStatus = $row['calendar_sync_status'] !== null
                ? (string) $row['calendar_sync_status']
                : null;
        }

        if (!empty($row['calendar_synced_at'])) {
            $reservation->calendarSyncedAt = new DateTimeImmutable((string) $row['calendar_synced_at']);
        }

        return $reservation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAgendaRange(string $startDate, string $endDate): array
    {
        $sql = 'SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang '
            . 'FROM ' . $this->tableName() . ' r '
            . 'LEFT JOIN ' . $this->customersTableName() . ' c ON r.customer_id = c.id '
            . 'WHERE r.date BETWEEN %s AND %s '
            . 'ORDER BY r.date ASC, r.time ASC';

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $startDate, $endDate),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function findArrivals(string $startDate, string $endDate, array $filters = []): array
    {
        $tablesTable = $this->wpdb->prefix . 'fp_tables';
        $roomsTable  = $this->wpdb->prefix . 'fp_rooms';

        $sql = 'SELECT r.*, '
            . 'c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang, '
            . 't.code AS table_code, rm.name AS room_name '
            . 'FROM ' . $this->tableName() . ' r '
            . 'LEFT JOIN ' . $this->customersTableName() . ' c ON r.customer_id = c.id '
            . 'LEFT JOIN ' . $tablesTable . ' t ON r.table_id = t.id '
            . 'LEFT JOIN ' . $roomsTable . ' rm ON r.room_id = rm.id '
            . 'WHERE r.date BETWEEN %s AND %s';

        $params = [$startDate, $endDate];

        if (isset($filters['room']) && $filters['room'] !== '') {
            $sql      .= ' AND r.room_id = %d';
            $params[] = absint((string) $filters['room']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql      .= ' AND r.status = %s';
            $params[] = (string) $filters['status'];
        }

        $sql .= ' ORDER BY r.date ASC, r.time ASC';

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$params),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByCustomer(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $sql = 'SELECT id, status, date, time, party, notes, allergies, utm_source, utm_medium, utm_campaign, '
            . 'location_id, value, currency, created_at, updated_at, visited_at '
            . 'FROM ' . $this->tableName() . ' '
            . 'WHERE customer_id = %d '
            . 'ORDER BY date DESC, time DESC';

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $customerId),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAgendaEntry(int $id): ?array
    {
        $sql = 'SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang, '
            . 'c.marketing_consent, c.profiling_consent '
            . 'FROM ' . $this->tableName() . ' r '
            . 'LEFT JOIN ' . $this->customersTableName() . ' c ON r.customer_id = c.id '
            . 'WHERE r.id = %d';

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return true;
        }

        if (!array_key_exists('updated_at', $data)) {
            $data['updated_at'] = current_time('mysql');
        }

        $result = $this->wpdb->update($this->tableName(), $data, ['id' => $id]);
        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error ?: 'Unable to update reservation.');
        }

        return true;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function logAudit(array $entry): void
    {
        $defaults = [
            'actor_id'    => null,
            'actor_role'  => null,
            'action'      => 'create',
            'entity'      => 'reservation',
            'entity_id'   => null,
            'before_json' => null,
            'after_json'  => null,
            'created_at'  => current_time('mysql'),
            'ip'          => null,
        ];

        $payload = array_merge($defaults, $entry);

        $this->wpdb->insert($this->auditTable(), $payload);
    }

    public function anonymizeOlderThan(DateTimeImmutable $cutoff): int
    {
        $sql = $this->wpdb->prepare(
            'UPDATE ' . $this->tableName()
            . ' SET notes = NULL, allergies = NULL, utm_source = NULL, utm_medium = NULL, utm_campaign = NULL '
            . 'WHERE date < %s',
            $cutoff->format('Y-m-d')
        );

        $result = $this->wpdb->query($sql);

        return $result === false ? 0 : (int) $result;
    }

    public function anonymizeCustomer(int $customerId): int
    {
        $sql = $this->wpdb->prepare(
            'UPDATE ' . $this->tableName()
            . ' SET customer_id = NULL, notes = NULL, allergies = NULL, utm_source = NULL, utm_medium = NULL, utm_campaign = NULL '
            . 'WHERE customer_id = %d',
            $customerId
        );

        $result = $this->wpdb->query($sql);

        return $result === false ? 0 : (int) $result;
    }
}
