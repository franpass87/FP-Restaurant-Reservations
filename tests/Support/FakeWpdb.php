<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

class FakeWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public string $last_error = '';

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $tables = [];

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $id = $data['id'] ?? ($this->insert_id + 1);
        $this->insert_id = (int) $id;

        $row       = $data;
        $row['id'] = $this->insert_id;

        $this->tables[$table][$this->insert_id] = $row;

        return 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $id = $where['id'] ?? null;
        if ($id === null) {
            throw new RuntimeException('FakeWpdb::update requires an id condition.');
        }

        $id = (int) $id;
        if (!isset($this->tables[$table][$id])) {
            return 0;
        }

        $this->tables[$table][$id] = array_merge($this->tables[$table][$id], $data);

        return 1;
    }

    public function prepare(string $query, mixed ...$args): string
    {
        if ($args === []) {
            return $query;
        }

        $escaped = array_map(static function (mixed $value): string {
            if (is_numeric($value)) {
                return (string) $value;
            }

            return "'" . str_replace("'", "''", (string) $value) . "'";
        }, $args);

        $normalized = preg_replace('/%[dfs]/', '%s', $query);

        return vsprintf($normalized, $escaped);
    }

    public function get_row(string $query, string $outputType = ARRAY_A): ?array
    {
        if (preg_match('/FROM\s+([`\\w]+)\s+.*WHERE\s+id\s*=\s*(\d+)/i', $query, $matches) === 1) {
            $table = $this->normalizeTable($matches[1]);
            $id    = (int) $matches[2];

            return $this->tables[$table][$id] ?? null;
        }

        if (preg_match('/FROM\s+([`\\w]+)\s+.*WHERE\s+email\s*=\s*\'([^\']+)\'/i', $query, $matches) === 1) {
            $table = $this->normalizeTable($matches[1]);
            $email = $matches[2];
            foreach ($this->tables[$table] ?? [] as $row) {
                if (($row['email'] ?? null) === $email) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_results(string $query, string $outputType = ARRAY_A): array
    {
        if (preg_match('/FROM\s+([`\\w]+)\s+r\s+LEFT\s+JOIN\s+([`\\w]+)/i', $query, $matches) === 1) {
            $reservationsTable = $this->normalizeTable($matches[1]);
            $customersTable    = $this->normalizeTable($matches[2]);

            $rows = [];
            foreach ($this->tables[$reservationsTable] ?? [] as $row) {
                $customerId = (int) ($row['customer_id'] ?? 0);
                $customer   = $this->tables[$customersTable][$customerId] ?? [];
                $rows[]     = array_merge($row, $customer);
            }

            return $rows;
        }

        if (preg_match('/FROM\s+([`\\w]+)/i', $query, $matches) !== 1) {
            return [];
        }

        $table = $this->normalizeTable($matches[1]);
        $rows  = array_values($this->tables[$table] ?? []);

        $normalized = strtolower($query);

        if (str_contains($normalized, ' where ')) {
            $rows = array_filter($rows, function (array $row) use ($query, $normalized): bool {
                if (str_contains($normalized, ' active = 1') && (int) ($row['active'] ?? 0) !== 1) {
                    return false;
                }

                if (preg_match("/(?<!_)id\s*=\s*(\d+)/i", $query, $match) === 1) {
                    if ((int) ($row['id'] ?? 0) !== (int) $match[1]) {
                        return false;
                    }
                }

                if (preg_match('/room_id\s*=\s*(\d+)/i', $query, $match) === 1) {
                    if ((int) ($row['room_id'] ?? 0) !== (int) $match[1]) {
                        return false;
                    }
                }

                if (preg_match('/room_id\s+is\s+null\s+or\s+room_id\s*=\s*(\d+)/i', $query, $match) === 1) {
                    $roomId = (int) $match[1];
                    $value  = $row['room_id'] ?? null;
                    if ($value !== null && (int) $value !== $roomId) {
                        return false;
                    }
                }

                if (preg_match("/location_id\s*=\s*'([^']*)'/i", $query, $match) === 1) {
                    if ((string) ($row['location_id'] ?? '') !== $match[1]) {
                        return false;
                    }
                }

                if (preg_match("/location_id\s+is\s+null\s+or\s+location_id\s*=\s*'([^']*)'/i", $query, $match) === 1) {
                    $location = $row['location_id'] ?? null;
                    if ($location !== null && (string) $location !== $match[1]) {
                        return false;
                    }
                }

                if (preg_match("/date\s*=\s*'([^']+)'/i", $query, $match) === 1) {
                    if ((string) ($row['date'] ?? '') !== $match[1]) {
                        return false;
                    }
                }

                if (preg_match("/time\s*=\s*'([^']+)'/i", $query, $match) === 1) {
                    if ((string) ($row['time'] ?? '') !== $match[1]) {
                        return false;
                    }
                }

                if (preg_match('/customer_id\s*=\s*(\d+)/i', $query, $match) === 1) {
                    $rowCustomer = (int) ($row['customer_id'] ?? 0);
                    if ($rowCustomer !== (int) $match[1]) {
                        return false;
                    }
                }

                if (preg_match("/status\s+in\s*\(([^)]+)\)/i", $query, $match) === 1) {
                    $statuses = array_map(static function (string $status): string {
                        return trim($status, "' \"");
                    }, explode(',', $match[1]));

                    if (!in_array((string) ($row['status'] ?? ''), $statuses, true)) {
                        return false;
                    }
                }

                return true;
            });
        }

        return array_values($rows);
    }

    public function get_table(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    private function normalizeTable(string $identifier): string
    {
        return str_replace('`', '', $identifier);
    }
}

