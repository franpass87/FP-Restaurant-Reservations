<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

use InvalidArgumentException;
use RuntimeException;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function max;
use function preg_match;
use function sprintf;
use function strtolower;
use function substr;
use function trim;
use function uniqid;
use function usort;

final class LayoutService
{
    public function __construct(private readonly Repository $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        $rooms = [];
        $groups = [];

        $existingRooms = $this->repository->getRooms();
        if ($existingRooms === []) {
            // Auto-bootstrap: crea una sala di default se non ne esistono
            $defaultRoomId = $this->repository->insertRoom([
                'name'     => 'Sala principale',
                'color'    => '#4338ca',
                'capacity' => 0,
                'active'   => true,
            ]);
            // Ricarica l'elenco completo per evitare inconsistenze
            $existingRooms = $this->repository->getRooms();
        }

        foreach ($existingRooms as $roomRow) {
            $room = $this->hydrateRoom($roomRow);
            $tableRows = $this->repository->getTables($room->id);
            $roomTables = [];
            foreach ($tableRows as $tableRow) {
                $table = $this->hydrateTable($tableRow);
                $roomTables[] = $this->tableToArray($table);
                if ($table->joinGroup !== null && $table->joinGroup !== '') {
                    $groups[$table->joinGroup]['tables'][] = $table->id;
                    $groups[$table->joinGroup]['room_id'] = $table->roomId;
                }
            }

            $rooms[] = $this->roomToArray($room, $roomTables);
        }

        foreach ($groups as $code => &$group) {
            $group['code'] = $code;
            $group['tables'] = array_values(array_unique($group['tables']));
        }

        return [
            'rooms'  => $rooms,
            'groups' => array_values($groups),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function saveRoom(array $data, ?int $roomId = null): array
    {
        $payload = $this->normalizeRoomData($data);
        if ($roomId === null) {
            $roomId = $this->repository->insertRoom($payload);
        } else {
            $this->assertRoomExists($roomId);
            $this->repository->updateRoom($roomId, $payload);
        }

        $room = $this->repository->findRoom($roomId);
        if ($room === null) {
            throw new RuntimeException('Room could not be loaded after save.');
        }

        return $this->roomToArray($this->hydrateRoom($room));
    }

    public function deleteRoom(int $roomId): void
    {
        $this->assertRoomExists($roomId);
        $this->repository->deleteRoom($roomId);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function saveTable(array $data, ?int $tableId = null): array
    {
        $payload = $this->normalizeTableData($data);
        $roomId = (int) $payload['room_id'];
        $this->assertRoomExists($roomId);

        if ($tableId === null) {
            $tableId = $this->repository->insertTable($payload);
        } else {
            $this->assertTableExists($tableId);
            $this->repository->updateTable($tableId, $payload);
        }

        $table = $this->repository->findTable($tableId);
        if ($table === null) {
            throw new RuntimeException('Table could not be loaded after save.');
        }

        return $this->tableToArray($this->hydrateTable($table));
    }

    public function deleteTable(int $tableId): void
    {
        $this->assertTableExists($tableId);
        $this->repository->deleteTable($tableId);
    }

    /**
     * Crea rapidamente più tavoli in una sala.
     * Accetta sia una lista di tavoli sia parametri generativi (prefisso, quantità, seats_std).
     *
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>> Tavoli creati
     */
    public function createTablesBulk(array $data): array
    {
        $roomId = (int) ($data['room_id'] ?? 0);
        $this->assertRoomExists($roomId);

        $created = [];
        $skipped = [];
        $onDuplicate = isset($data['on_duplicate']) ? strtolower((string) $data['on_duplicate']) : 'error'; // error|skip

        // Caso 1: lista esplicita di tavoli
        if (isset($data['tables']) && is_array($data['tables'])) {
            $existingCodes = $this->repository->getExistingCodesByRoom($roomId);
            $this->repository->beginTransaction();
            foreach ($data['tables'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $payload = $this->normalizeTableData(array_merge($entry, ['room_id' => $roomId]));
                $code = (string) $payload['code'];
                if (isset($existingCodes[$code])) {
                    if ($onDuplicate === 'skip') {
                        $skipped[] = $code;
                        continue;
                    }
                    $this->repository->rollback();
                    throw new InvalidArgumentException(sprintf('Esiste già un tavolo con codice "%s" in questa sala.', $code));
                }
                try {
                    $tableId = $this->repository->insertTable($payload);
                    $existingCodes[$code] = true;
                    $row = $this->repository->findTable($tableId);
                    if ($row !== null) {
                        $created[] = $this->tableToArray($this->hydrateTable($row));
                    }
                } catch (\Throwable $e) {
                    $this->repository->rollback();
                    throw $e;
                }
            }
            $this->repository->commit();
            return $created;
        }

        // Caso 2: generazione automatica
        $prefix = isset($data['prefix']) ? trim((string) $data['prefix']) : 'T';
        if ($prefix !== '' && preg_match('/^[A-Za-z0-9_-]{1,8}$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Prefisso non valido. Usa lettere, numeri, trattini o underscore (max 8).');
        }
        $count  = (int) ($data['count'] ?? 0);
        $count  = max(1, min(200, $count));
        $seats  = isset($data['seats_std']) ? (int) $data['seats_std'] : 2;
        if ($seats < 1) {
            $seats = 1;
        }

        // Calcola order_index di partenza
        $existing = $this->repository->getTables($roomId);
        $maxOrder = 0;
        foreach ($existing as $t) {
            $maxOrder = max($maxOrder, (int) ($t['order_index'] ?? 0));
        }

        $existingCodes = $this->repository->getExistingCodesByRoom($roomId);
        $this->repository->beginTransaction();
        for ($i = 1; $i <= $count; $i++) {
            $code = sprintf('%s%d', $prefix, $i);
            if (isset($existingCodes[$code])) {
                if ($onDuplicate === 'skip') {
                    $skipped[] = $code;
                    continue;
                }
                $this->repository->rollback();
                throw new InvalidArgumentException(sprintf('Esiste già un tavolo con codice "%s" in questa sala.', $code));
            }
            $payload = $this->normalizeTableData([
                'room_id'   => $roomId,
                'code'      => $code,
                'seats_std' => $seats,
                'status'    => 'available',
                'pos_x'     => 40 + (($i - 1) % 10) * 60,
                'pos_y'     => 40 + (int) (floor(($i - 1) / 10)) * 60,
                'order_index' => $maxOrder + $i,
                'active'    => true,
            ]);
            try {
                $tableId = $this->repository->insertTable($payload);
                $existingCodes[$code] = true;
                $row = $this->repository->findTable($tableId);
                if ($row !== null) {
                    $created[] = $this->tableToArray($this->hydrateTable($row));
                }
            } catch (\Throwable $e) {
                $this->repository->rollback();
                throw $e;
            }
        }
        $this->repository->commit();
        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $positions
     */
    public function updatePositions(array $positions): void
    {
        foreach ($positions as $entry) {
            if (!isset($entry['id'])) {
                continue;
            }

            $id = (int) $entry['id'];
            $x  = isset($entry['x']) ? (float) $entry['x'] : 0.0;
            $y  = isset($entry['y']) ? (float) $entry['y'] : 0.0;

            $this->repository->updatePosition($id, $x, $y);
        }
    }

    /**
     * @param array<int, int> $tableIds
     *
     * @return array<string, mixed>
     */
    public function mergeTables(array $tableIds, ?string $groupCode = null): array
    {
        if (count($tableIds) < 2) {
            throw new InvalidArgumentException('At least two tables are required to create a merge group.');
        }

        $tables = [];
        $roomId = null;
        foreach ($tableIds as $tableId) {
            $table = $this->repository->findTable((int) $tableId);
            if ($table === null) {
                throw new InvalidArgumentException(sprintf('Table %d not found.', (int) $tableId));
            }

            if ($roomId === null) {
                $roomId = (int) $table['room_id'];
            }

            if ((int) $table['room_id'] !== $roomId) {
                throw new InvalidArgumentException('All tables in a merge group must belong to the same room.');
            }

            $tables[] = $this->hydrateTable($table);
        }

        $code = $groupCode !== null ? $this->normalizeGroupCode($groupCode) : $this->generateGroupCode($roomId ?? 0);
        $this->repository->updateJoinGroup($tableIds, $code);

        $capacity = $this->calculateCapacity($tables);

        return [
            'code'     => $code,
            'room_id'  => $roomId,
            'tables'   => array_map(static fn (Table $table): int => $table->id, $tables),
            'capacity' => $capacity,
        ];
    }

    /**
     * @param array<int, int> $tableIds
     */
    public function splitTables(array $tableIds): void
    {
        if ($tableIds === []) {
            return;
        }

        $this->repository->updateJoinGroup($tableIds, null);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function suggest(array $criteria): array
    {
        $party = max(1, (int) ($criteria['party'] ?? 0));
        $roomId = isset($criteria['room_id']) ? (int) $criteria['room_id'] : null;
        $allowInactive = !empty($criteria['include_inactive']);
        $allowUnavailable = !empty($criteria['include_unavailable']);
        $maxTables = max(1, (int) ($criteria['max_tables'] ?? 3));

        $tablesData = $this->repository->getTables($roomId);
        $candidates = [];
        foreach ($tablesData as $tableRow) {
            $table = $this->hydrateTable($tableRow);
            if (!$allowInactive && !$table->active) {
                continue;
            }

            if (!$allowUnavailable && strtolower($table->status) !== 'available') {
                continue;
            }

            $candidates[] = $table;
        }

        if ($candidates === []) {
            return [
                'party'        => $party,
                'best'         => null,
                'alternatives' => [],
            ];
        }

        $suggestions = $this->buildSuggestions($candidates, $party, $maxTables);

        return [
            'party'        => $party,
            'best'         => $suggestions['best'] ?? null,
            'alternatives' => $suggestions['alternatives'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRoom(array $row): Room
    {
        $room = new Room();
        $room->id = (int) $row['id'];
        $room->name = (string) $row['name'];
        $room->description = (string) ($row['description'] ?? '');
        $room->color = (string) ($row['color'] ?? '');
        $room->capacity = (int) ($row['capacity'] ?? 0);
        $room->orderIndex = (int) ($row['order_index'] ?? 0);
        $room->active = !empty($row['active']);

        return $room;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateTable(array $row): Table
    {
        $table = new Table();
        $table->id = (int) $row['id'];
        $table->roomId = (int) $row['room_id'];
        $table->code = (string) $row['code'];
        $table->seatsMin = $row['seats_min'] !== null ? (int) $row['seats_min'] : null;
        $table->seatsStd = $row['seats_std'] !== null ? (int) $row['seats_std'] : null;
        $table->seatsMax = $row['seats_max'] !== null ? (int) $row['seats_max'] : null;
        $table->attributes = $row['attributes'] ?? [];
        $table->joinGroup = $row['join_group'] !== null && $row['join_group'] !== '' ? (string) $row['join_group'] : null;
        $table->posX = $row['pos_x'] !== null ? (float) $row['pos_x'] : null;
        $table->posY = $row['pos_y'] !== null ? (float) $row['pos_y'] : null;
        $table->status = (string) ($row['status'] ?? 'available');
        $table->active = !empty($row['active']);
        $table->orderIndex = (int) ($row['order_index'] ?? 0);

        return $table;
    }

    /**
     * @param array<int, array<string, mixed>> $tables
     *
     * @return array<string, mixed>
     */
    private function calculateCapacity(array $tables): array
    {
        $min = 0;
        $std = 0;
        $maxValue = 0;
        foreach ($tables as $table) {
            if (!$table instanceof Table) {
                continue;
            }

            $min      += $table->seatsMin ?? $table->seatsStd ?? 0;
            $std      += $table->seatsStd ?? $table->seatsMax ?? 0;
            $maxValue += $table->seatsMax ?? $table->seatsStd ?? 0;
        }

        return [
            'min' => $min,
            'std' => $std,
            'max' => $maxValue,
        ];
    }

    /**
     * @param array<string, mixed> $room
     * @param array<int, array<string, mixed>> $tables
     *
     * @return array<string, mixed>
     */
    private function roomToArray(Room $room, array $tables = []): array
    {
        return [
            'id'          => $room->id,
            'name'        => $room->name,
            'description' => $room->description,
            'color'       => $room->color,
            'capacity'    => $room->capacity,
            'order_index' => $room->orderIndex,
            'active'      => $room->active,
            'tables'      => $tables,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tableToArray(Table $table): array
    {
        return [
            'id'          => $table->id,
            'room_id'     => $table->roomId,
            'code'        => $table->code,
            'seats_min'   => $table->seatsMin,
            'seats_std'   => $table->seatsStd,
            'seats_max'   => $table->seatsMax,
            'attributes'  => $table->attributes,
            'join_group'  => $table->joinGroup,
            'pos_x'       => $table->posX,
            'pos_y'       => $table->posY,
            'status'      => $table->status,
            'active'      => $table->active,
            'order_index' => $table->orderIndex,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeRoomData(array $data): array
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        if ($name === '') {
            throw new InvalidArgumentException('Room name is required.');
        }

        $color = isset($data['color']) ? trim((string) $data['color']) : '';
        if ($color !== '' && preg_match('/^#?[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new InvalidArgumentException('Room color must be a valid hex value.');
        }

        if ($color !== '' && $color[0] !== '#') {
            $color = '#' . $color;
        }

        return [
            'name'        => $name,
            'description' => isset($data['description']) ? trim((string) $data['description']) : '',
            'color'       => $color,
            'capacity'    => isset($data['capacity']) ? max(0, (int) $data['capacity']) : 0,
            'order_index' => isset($data['order_index']) ? (int) $data['order_index'] : 0,
            'active'      => !empty($data['active']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeTableData(array $data): array
    {
        $code = isset($data['code']) ? trim((string) $data['code']) : '';
        if ($code === '') {
            throw new InvalidArgumentException('Table code is required.');
        }

        if (!isset($data['room_id'])) {
            throw new InvalidArgumentException('Table room_id is required.');
        }

        $roomId = (int) $data['room_id'];
        if ($roomId <= 0) {
            throw new InvalidArgumentException('Table room_id must be a positive integer.');
        }

        $status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : 'available';
        if (!in_array($status, ['available', 'blocked', 'maintenance', 'hidden'], true)) {
            $status = 'available';
        }

        $attributes = [];
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $attributes = $data['attributes'];
        }

        return [
            'room_id'    => $roomId,
            'code'       => $code,
            'status'     => $status,
            'seats_min'  => isset($data['seats_min']) ? $this->positiveOrNull($data['seats_min']) : null,
            'seats_std'  => isset($data['seats_std']) ? $this->positiveOrNull($data['seats_std']) : null,
            'seats_max'  => isset($data['seats_max']) ? $this->positiveOrNull($data['seats_max']) : null,
            'attributes' => $attributes,
            'pos_x'      => isset($data['pos_x']) ? (float) $data['pos_x'] : null,
            'pos_y'      => isset($data['pos_y']) ? (float) $data['pos_y'] : null,
            'active'     => !empty($data['active']),
            'order_index'=> isset($data['order_index']) ? (int) $data['order_index'] : 0,
        ];
    }

    private function positiveOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function assertRoomExists(int $roomId): void
    {
        if ($roomId <= 0 || $this->repository->findRoom($roomId) === null) {
            throw new InvalidArgumentException(sprintf('Room %d does not exist.', $roomId));
        }
    }

    private function assertTableExists(int $tableId): void
    {
        if ($tableId <= 0 || $this->repository->findTable($tableId) === null) {
            throw new InvalidArgumentException(sprintf('Table %d does not exist.', $tableId));
        }
    }

    private function normalizeGroupCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return $this->generateGroupCode();
        }

        if (preg_match('/^[A-Za-z0-9_-]{3,30}$/', $code) !== 1) {
            throw new InvalidArgumentException('Group code must contain only alphanumeric characters, dashes, or underscores.');
        }

        return strtolower($code);
    }

    private function generateGroupCode(int $roomId = 0): string
    {
        return 'grp-' . ($roomId > 0 ? $roomId . '-' : '') . substr(uniqid('', true), -6);
    }

    /**
     * @param array<int, Table> $tables
     * @return array<string, mixed>
     */
    private function buildSuggestions(array $tables, int $party, int $maxTables): array
    {
        $best = null;
        $alternatives = [];

        $grouped = [];
        foreach ($tables as $table) {
            if ($table->joinGroup !== null) {
                $grouped[$table->joinGroup][] = $table;
            }
        }

        // Evaluate single tables first.
        foreach ($tables as $table) {
            $capacity = $this->calculateCapacity([$table]);
            if ($capacity['max'] < $party) {
                continue;
            }

            $score = $this->scoreSuggestion($capacity, $party, 1);
            $suggestion = $this->formatSuggestion([$table], $capacity, $score, $table->joinGroup);
            $this->maybeRegisterSuggestion($suggestion, $best, $alternatives);
        }

        // Evaluate join groups.
        foreach ($grouped as $groupCode => $groupTables) {
            $capacity = $this->calculateCapacity($groupTables);
            if ($capacity['max'] < $party) {
                continue;
            }

            $score = $this->scoreSuggestion($capacity, $party, count($groupTables));
            $suggestion = $this->formatSuggestion($groupTables, $capacity, $score, $groupCode);
            $this->maybeRegisterSuggestion($suggestion, $best, $alternatives);
        }

        // Greedy fallback combinations up to max tables.
        $sorted = $tables;
        usort($sorted, static function (Table $a, Table $b): int {
            return ($b->seatsStd ?? $b->seatsMax ?? 0) <=> ($a->seatsStd ?? $a->seatsMax ?? 0);
        });

        $selection = [];
        $capacity = ['min' => 0, 'std' => 0, 'max' => 0];
        foreach ($sorted as $table) {
            if (count($selection) >= $maxTables) {
                break;
            }

            $selection[] = $table;
            $capacity = $this->calculateCapacity($selection);
            if ($capacity['max'] >= $party) {
                $score = $this->scoreSuggestion($capacity, $party, count($selection));
                $suggestion = $this->formatSuggestion($selection, $capacity, $score, null);
                $this->maybeRegisterSuggestion($suggestion, $best, $alternatives);
                break;
            }
        }

        return [
            'best'         => $best,
            'alternatives' => array_values($alternatives),
        ];
    }

    /**
     * @param array<int, Table> $tables
     * @param array<string, mixed> $capacity
     * @return array<string, mixed>
     */
    private function formatSuggestion(array $tables, array $capacity, float $score, ?string $groupCode): array
    {
        $roomId = $tables[0]->roomId ?? 0;

        return [
            'room_id'    => $roomId,
            'table_ids'  => array_map(static fn (Table $table): int => $table->id, $tables),
            'join_group' => $groupCode,
            'capacity'   => $capacity,
            'score'      => $score,
        ];
    }

    /**
     * @param array<string, mixed> $suggestion
     * @param array<string, mixed>|null $best
     * @param array<string, array<string, mixed>> $alternatives
     */
    private function maybeRegisterSuggestion(array $suggestion, ?array &$best, array &$alternatives): void
    {
        $key = implode('-', $suggestion['table_ids']);
        if (isset($alternatives[$key])) {
            return;
        }

        if ($best === null || $suggestion['score'] < $best['score']) {
            if ($best !== null) {
                $alternatives[implode('-', $best['table_ids'])] = $best;
            }

            $best = $suggestion;

            return;
        }

        $alternatives[$key] = $suggestion;
    }

    private function scoreSuggestion(array $capacity, int $party, int $tablesCount): float
    {
        $std = $capacity['std'] > 0 ? $capacity['std'] : $capacity['max'];
        if ($std <= 0) {
            return 1000.0;
        }

        $overCapacity = max(0, $std - $party);
        return $overCapacity + ($tablesCount * 0.1);
    }
}
