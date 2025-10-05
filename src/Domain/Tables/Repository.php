<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

use RuntimeException;
use wpdb;
use function absint;
use function array_fill;
use function array_map;
use function array_values;
use function current_time;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function sanitize_text_field;
use function wp_json_encode;

final class Repository
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function beginTransaction(): void
    {
        $this->wpdb->query('START TRANSACTION');
    }

    public function commit(): void
    {
        $this->wpdb->query('COMMIT');
    }

    public function rollback(): void
    {
        $this->wpdb->query('ROLLBACK');
    }

    public function roomsTable(): string
    {
        return $this->wpdb->prefix . 'fp_rooms';
    }

    public function tablesTable(): string
    {
        return $this->wpdb->prefix . 'fp_tables';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRooms(): array
    {
        $rows = $this->wpdb->get_results('SELECT * FROM ' . $this->roomsTable() . ' ORDER BY order_index ASC, name ASC', ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'color'       => (string) ($row['color'] ?? ''),
                'capacity'    => (int) ($row['capacity'] ?? 0),
                'order_index' => (int) ($row['order_index'] ?? 0),
                'active'      => (int) ($row['active'] ?? 0) === 1,
                'created_at'  => (string) ($row['created_at'] ?? ''),
                'updated_at'  => (string) ($row['updated_at'] ?? ''),
            ];
        }, $rows);
    }

    public function findRoom(int $roomId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->roomsTable() . ' WHERE id = %d', $roomId),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'description' => (string) ($row['description'] ?? ''),
            'color'       => (string) ($row['color'] ?? ''),
            'capacity'    => (int) ($row['capacity'] ?? 0),
            'order_index' => (int) ($row['order_index'] ?? 0),
            'active'      => (int) ($row['active'] ?? 0) === 1,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRoom(array $data): int
    {
        $payload = [
            'name'        => (string) ($data['name'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'color'       => (string) ($data['color'] ?? ''),
            'capacity'    => absint((int) ($data['capacity'] ?? 0)),
            'order_index' => (int) ($data['order_index'] ?? 0),
            'active'      => !empty($data['active']) ? 1 : 0,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ];

        $result = $this->wpdb->insert($this->roomsTable(), $payload);
        if ($result === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to create room.');
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRoom(int $roomId, array $data): void
    {
        $payload = [
            'updated_at' => current_time('mysql'),
        ];

        foreach (['name', 'description', 'color'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = (string) $data[$key];
            }
        }

        if (array_key_exists('capacity', $data)) {
            $payload['capacity'] = absint((int) $data['capacity']);
        }

        if (array_key_exists('order_index', $data)) {
            $payload['order_index'] = (int) $data['order_index'];
        }

        if (array_key_exists('active', $data)) {
            $payload['active'] = !empty($data['active']) ? 1 : 0;
        }

        $updated = $this->wpdb->update(
            $this->roomsTable(),
            $payload,
            ['id' => $roomId]
        );

        if ($updated === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to update room.');
        }
    }

    public function deleteRoom(int $roomId): void
    {
        $this->wpdb->delete($this->tablesTable(), ['room_id' => $roomId]);
        $this->wpdb->delete($this->roomsTable(), ['id' => $roomId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTables(?int $roomId = null): array
    {
        $sql = 'SELECT * FROM ' . $this->tablesTable();
        $args = [];
        if ($roomId !== null) {
            $sql   .= ' WHERE room_id = %d';
            $args[] = $roomId;
        }

        $sql .= ' ORDER BY order_index ASC, code ASC';

        $rows = $args === []
            ? $this->wpdb->get_results($sql, ARRAY_A)
            : $this->wpdb->get_results($this->wpdb->prepare($sql, ...$args), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'mapTableRow'], $rows);
    }

    /**
     * @return array<string, true> Set di codici tavolo esistenti per una sala
     */
    public function getExistingCodesByRoom(int $roomId): array
    {
        $sql = 'SELECT code FROM ' . $this->tablesTable() . ' WHERE room_id = %d';
        $rows = $this->wpdb->get_col($this->wpdb->prepare($sql, $roomId));
        $set = [];
        if (is_array($rows)) {
            foreach ($rows as $code) {
                $set[(string) $code] = true;
            }
        }

        return $set;
    }

    public function findTable(int $tableId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tablesTable() . ' WHERE id = %d', $tableId),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return $this->mapTableRow($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTable(array $data): int
    {
        $payload = $this->prepareTablePayload($data);
        $payload['created_at'] = current_time('mysql');
        $payload['updated_at'] = current_time('mysql');

        $result = $this->wpdb->insert($this->tablesTable(), $payload);
        if ($result === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to create table.');
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTable(int $tableId, array $data): void
    {
        $payload = $this->prepareTablePayload($data);
        $payload['updated_at'] = current_time('mysql');

        $updated = $this->wpdb->update(
            $this->tablesTable(),
            $payload,
            ['id' => $tableId]
        );

        if ($updated === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to update table.');
        }
    }

    public function deleteTable(int $tableId): void
    {
        $this->wpdb->delete($this->tablesTable(), ['id' => $tableId]);
    }

    /**
     * @param array<int, int> $tableIds
     */
    public function updateJoinGroup(array $tableIds, ?string $group): void
    {
        if ($tableIds === []) {
            return;
        }

        $tableIds = array_values(array_map('absint', $tableIds));
        $placeholders = implode(', ', array_fill(0, count($tableIds), '%d'));
        $groupValue = $group === null ? null : sanitize_text_field($group);

        $sql = 'UPDATE ' . $this->tablesTable() . ' SET join_group = %s, updated_at = %s WHERE id IN (' . $placeholders . ')';
        $params = array_merge([
            $groupValue,
            current_time('mysql'),
        ], $tableIds);

        $result = $this->wpdb->query($this->wpdb->prepare($sql, ...$params));
        if ($result === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to update join group.');
        }
    }

    public function updatePosition(int $tableId, float $x, float $y): void
    {
        $updated = $this->wpdb->update(
            $this->tablesTable(),
            [
                'pos_x'      => $x,
                'pos_y'      => $y,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $tableId]
        );

        if ($updated === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to update table position.');
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapTableRow(array $row): array
    {
        $attributes = [];
        if (isset($row['attributes_json']) && is_string($row['attributes_json']) && $row['attributes_json'] !== '') {
            $decoded = json_decode($row['attributes_json'], true);
            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }

        return [
            'id'          => (int) $row['id'],
            'room_id'     => (int) $row['room_id'],
            'code'        => (string) $row['code'],
            'seats_min'   => $row['seats_min'] !== null ? (int) $row['seats_min'] : null,
            'seats_std'   => $row['seats_std'] !== null ? (int) $row['seats_std'] : null,
            'seats_max'   => $row['seats_max'] !== null ? (int) $row['seats_max'] : null,
            'attributes'  => $attributes,
            'join_group'  => isset($row['join_group']) ? (string) $row['join_group'] : null,
            'pos_x'       => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
            'pos_y'       => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
            'status'      => (string) ($row['status'] ?? 'available'),
            'active'      => (int) ($row['active'] ?? 0) === 1,
            'order_index' => (int) ($row['order_index'] ?? 0),
            'created_at'  => (string) ($row['created_at'] ?? ''),
            'updated_at'  => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function prepareTablePayload(array $data): array
    {
        $payload = [];

        foreach (['room_id', 'code', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $key === 'room_id'
                    ? absint((int) $data[$key])
                    : (string) $data[$key];
            }
        }

        foreach (['seats_min', 'seats_std', 'seats_max'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                $payload[$key] = $value !== null ? absint((int) $value) : null;
            }
        }

        if (array_key_exists('join_group', $data)) {
            $payload['join_group'] = $data['join_group'] === null ? null : sanitize_text_field((string) $data['join_group']);
        }

        if (array_key_exists('pos_x', $data)) {
            $payload['pos_x'] = $data['pos_x'] !== null ? (float) $data['pos_x'] : null;
        }

        if (array_key_exists('pos_y', $data)) {
            $payload['pos_y'] = $data['pos_y'] !== null ? (float) $data['pos_y'] : null;
        }

        if (array_key_exists('active', $data)) {
            $payload['active'] = !empty($data['active']) ? 1 : 0;
        }

        if (array_key_exists('order_index', $data)) {
            $payload['order_index'] = (int) $data['order_index'];
        }

        if (array_key_exists('attributes', $data)) {
            $payload['attributes_json'] = $data['attributes'] === null
                ? null
                : wp_json_encode($data['attributes']);
        }

        return $payload;
    }
}
