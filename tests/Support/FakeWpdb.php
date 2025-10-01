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

    /** @var array<string, int> */
    private array $autoIncrement = [];

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $table = $this->normalizeTable($table);

        if (isset($data['id']) && (int) $data['id'] > 0) {
            $id = (int) $data['id'];
        } else {
            $nextId = ($this->autoIncrement[$table] ?? 0) + 1;
            $this->autoIncrement[$table] = $nextId;
            $id = $nextId;
        }

        $this->insert_id = $id;

        $row       = $data;
        $row['id'] = $id;

        $this->tables[$table][$id] = $row;

        return 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $table = $this->normalizeTable($table);

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

            $tablesTable = null;
            if (preg_match('/LEFT\s+JOIN\s+([`\\w]+)\s+t\s+ON/i', $query, $tableMatches) === 1) {
                $tablesTable = $this->normalizeTable($tableMatches[1]);
            }

            $roomsTable = null;
            if (preg_match('/LEFT\s+JOIN\s+([`\\w]+)\s+rm\s+ON/i', $query, $roomMatches) === 1) {
                $roomsTable = $this->normalizeTable($roomMatches[1]);
            }

            $startDate = null;
            $endDate   = null;
            if (preg_match("/BETWEEN\s+'([^']+)'\s+AND\s+'([^']+)'/i", $query, $rangeMatches) === 1) {
                $startDate = $rangeMatches[1];
                $endDate   = $rangeMatches[2];
            }

            $rows = [];
            foreach ($this->tables[$reservationsTable] ?? [] as $row) {
                $date = (string) ($row['date'] ?? '');
                if ($startDate !== null && $endDate !== null) {
                    if ($date < $startDate || $date > $endDate) {
                        continue;
                    }
                }

                $customerId = (int) ($row['customer_id'] ?? 0);
                $customer   = $this->tables[$customersTable][$customerId] ?? [];

                $tableData = [];
                if ($tablesTable !== null) {
                    $tableId   = (int) ($row['table_id'] ?? 0);
                    $tableData = $this->tables[$tablesTable][$tableId] ?? [];
                }

                $roomData = [];
                if ($roomsTable !== null) {
                    $roomId   = (int) ($row['room_id'] ?? 0);
                    $roomData = $this->tables[$roomsTable][$roomId] ?? [];
                }

                $rows[] = array_merge(
                    $row,
                    [
                        'first_name'     => $customer['first_name'] ?? ($row['first_name'] ?? null),
                        'last_name'      => $customer['last_name'] ?? ($row['last_name'] ?? null),
                        'email'          => $customer['email'] ?? ($row['email'] ?? null),
                        'phone'          => $customer['phone'] ?? ($row['phone'] ?? null),
                        'customer_lang'  => $customer['lang'] ?? ($row['customer_lang'] ?? null),
                        'table_code'     => $tableData['code'] ?? ($row['table_code'] ?? null),
                        'room_name'      => $roomData['name'] ?? ($row['room_name'] ?? null),
                    ]
                );
            }

            usort($rows, static function (array $left, array $right): int {
                $dateComparison = (($left['date'] ?? '') <=> ($right['date'] ?? ''));
                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return (($left['time'] ?? '') <=> ($right['time'] ?? ''));
            });

            return $rows;
        }

        if (preg_match('/SELECT\s+\*\s+FROM\s+([`\\w]+)(?:\s+WHERE\s+room_id\s*=\s*(\d+))?.*ORDER\s+BY/i', $query, $matches) === 1) {
            $table  = $this->normalizeTable($matches[1]);
            $roomId = isset($matches[2]) ? (int) $matches[2] : null;

            $rows = array_values($this->tables[$table] ?? []);

            if ($roomId !== null) {
                $rows = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['room_id'] ?? 0) === $roomId));
            }

            usort($rows, static function (array $left, array $right): int {
                $leftOrder  = sprintf('%05d-%05d-%s', (int) ($left['room_id'] ?? 0), (int) ($left['order_index'] ?? 0), (string) ($left['code'] ?? ''));
                $rightOrder = sprintf('%05d-%05d-%s', (int) ($right['room_id'] ?? 0), (int) ($right['order_index'] ?? 0), (string) ($right['code'] ?? ''));

                return $leftOrder <=> $rightOrder;
            });

            return $rows;
        }

        return [];
    }

    public function get_table(string $table): array
    {
        $table = $this->normalizeTable($table);

        return $this->tables[$table] ?? [];
    }

    private function normalizeTable(string $identifier): string
    {
        return str_replace('`', '', $identifier);
    }
}

