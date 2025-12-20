<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use WP_User;
use wpdb;
use FP\Resv\Core\Helpers;
use FP\Resv\Domain\Closures\PayloadNormalizer;
use FP\Resv\Domain\Closures\PreviewGenerator;
use FP\Resv\Domain\Closures\RecurrenceHandler;
use FP\Resv\Domain\Settings\Options;
use function absint;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function current_time;
use function get_current_user_id;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function min;
use function preg_match;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sort;
use function strtolower;
use function trim;
use function wp_get_current_user;
use function wp_json_encode;
use function wp_timezone;

final class Service
{
    private const MAX_PREVIEW_DAYS = 120;

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly Options $options,
        private readonly PayloadNormalizer $payloadNormalizer,
        private readonly RecurrenceHandler $recurrenceHandler,
        private readonly PreviewGenerator $previewGenerator
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $models = $this->fetchModels($filters);

        return array_map(fn (Model $model): array => $this->exportModel($model), $models);
    }

    public function find(int $id): ?Model
    {
        $models = $this->fetchModels(['id' => $id, 'include_inactive' => true]);

        return $models[0] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        $normalized = $this->payloadNormalizer->normalizePayload($data, null);
        $id         = $this->insert($normalized);

        $model = $this->find($id);
        if ($model === null) {
            throw new RuntimeException('Closure created but not found.');
        }

        $this->logAudit('create', null, $model);

        return $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Model
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Closure not found.');
        }

        $normalized = $this->payloadNormalizer->normalizePayload($data, $existing);
        $this->persistUpdate($id, $normalized);

        $model = $this->find($id);
        if ($model === null) {
            throw new RuntimeException('Closure updated but not found.');
        }

        $this->logAudit('update', $existing, $model);

        return $model;
    }

    public function deactivate(int $id): void
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Closure not found.');
        }

        $table = $this->wpdb->prefix . 'fp_closures';
        $updated = $this->wpdb->update(
            $table,
            [
                'active'     => 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id]
        );

        if ($updated === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to deactivate closure.');
        }

        $updatedModel = $this->find($id);
        if ($updatedModel !== null) {
            $this->logAudit('deactivate', $existing, $updatedModel);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(DateTimeImmutable $start, DateTimeImmutable $end, array $filters = []): array
    {
        if ($end < $start) {
            throw new InvalidArgumentException('The end date must be after the start date.');
        }

        $diff = $start->diff($end);
        if ($diff->days !== false && $diff->days > self::MAX_PREVIEW_DAYS) {
            throw new InvalidArgumentException(sprintf(
                'Seleziona un intervallo inferiore o uguale a %d giorni per la preview.',
                self::MAX_PREVIEW_DAYS
            ));
        }

        $filters['range_start'] = $start;
        $filters['range_end']   = $end;

        $models = $this->fetchModels($filters);

        return $this->previewGenerator->generate($start, $end, $models, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, Model>
     */
    private function fetchModels(array $filters = []): array
    {
        $table    = $this->wpdb->prefix . 'fp_closures';
        $timezone = wp_timezone();

        $where  = ['1=1'];
        $params = [];

        if (!($filters['include_inactive'] ?? false)) {
            $where[] = 'active = 1';
        }

        if (isset($filters['id'])) {
            $where[]  = 'id = %d';
            $params[] = (int) $filters['id'];
        }

        if (isset($filters['scope'])) {
            $scope = sanitize_key((string) $filters['scope']);
            if ($scope !== '' && $scope !== 'all') {
                $where[]  = 'scope = %s';
                $params[] = $scope;
            }
        }

        if (isset($filters['room_id'])) {
            $where[]  = 'room_id = %d';
            $params[] = absint($filters['room_id']);
        }

        if (isset($filters['table_id'])) {
            $where[]  = 'table_id = %d';
            $params[] = absint($filters['table_id']);
        }

        if (isset($filters['range_start'], $filters['range_end'])
            && $filters['range_start'] instanceof DateTimeImmutable
            && $filters['range_end'] instanceof DateTimeImmutable
        ) {
            $where[]  = '((start_at <= %s AND end_at >= %s) OR (recurrence_json IS NOT NULL AND recurrence_json <> \'\'))';
            $params[] = $filters['range_end']->format('Y-m-d H:i:s');
            $params[] = $filters['range_start']->format('Y-m-d H:i:s');
        }

        $sql = 'SELECT id, scope, type, room_id, table_id, start_at, end_at, recurrence_json, capacity_override_json, note, active '
            . "FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY start_at ASC, end_at ASC';

        if ($params !== []) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrateModel($row, $timezone);
        }

        return $models;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateModel(array $row, DateTimeZone $timezone): Model
    {
        $model = new Model();
        $model->id    = (int) $row['id'];
        $model->scope = (string) $row['scope'];
        $model->type  = (string) $row['type'];
        $model->startAt = new DateTimeImmutable((string) $row['start_at'], $timezone);
        $model->endAt   = new DateTimeImmutable((string) $row['end_at'], $timezone);
        $model->roomId  = $row['room_id'] !== null ? (int) $row['room_id'] : null;
        $model->tableId = $row['table_id'] !== null ? (int) $row['table_id'] : null;
        $model->note    = isset($row['note']) ? (string) $row['note'] : '';
        $model->active  = (bool) ((int) ($row['active'] ?? 1));

        if (!empty($row['recurrence_json'])) {
            $decoded = json_decode((string) $row['recurrence_json'], true);
            $model->recurrence = is_array($decoded) ? $decoded : null;
        }

        if (!empty($row['capacity_override_json'])) {
            $decoded = json_decode((string) $row['capacity_override_json'], true);
            $model->capacityOverride = is_array($decoded) ? $decoded : null;
        }

        $model->priority = $this->determinePriority($model);

        return $model;
    }

    private function determinePriority(Model $model): int
    {
        $scopePriority = match ($model->scope) {
            'restaurant' => 300,
            'room'       => 200,
            'table'      => 100,
            default      => 0,
        };

        $typePriority = match ($model->type) {
            'full'               => 30,
            'special_hours'      => 20,
            'capacity_reduction' => 10,
            default              => 0,
        };

        return $scopePriority + $typePriority;
    }


    /**
     * @param array<string, mixed> $normalized
     */
    private function insert(array $normalized): int
    {
        $table = $this->wpdb->prefix . 'fp_closures';
        $data  = $this->prepareDbPayload($normalized);
        $data['created_at'] = current_time('mysql');

        $result = $this->wpdb->insert($table, $data);
        if ($result === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to create closure.');
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function persistUpdate(int $id, array $normalized): void
    {
        $table = $this->wpdb->prefix . 'fp_closures';
        $data  = $this->prepareDbPayload($normalized);

        $result = $this->wpdb->update($table, $data, ['id' => $id]);
        if ($result === false) {
            throw new RuntimeException($this->wpdb->last_error ?: 'Unable to update closure.');
        }
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private function prepareDbPayload(array $normalized): array
    {
        $payload = [
            'scope'      => $normalized['scope'],
            'type'       => $normalized['type'],
            'room_id'    => $normalized['room_id'],
            'table_id'   => $normalized['table_id'],
            'start_at'   => $normalized['start']->format('Y-m-d H:i:s'),
            'end_at'     => $normalized['end']->format('Y-m-d H:i:s'),
            'note'       => $normalized['note'],
            'active'     => $normalized['active'] ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        $payload['recurrence_json'] = null;
        if ($normalized['recurrence'] !== null) {
            $json = wp_json_encode($normalized['recurrence']);
            $payload['recurrence_json'] = is_string($json) ? $json : null;
        }

        $payload['capacity_override_json'] = null;
        if ($normalized['capacity_override'] !== null) {
            $json = wp_json_encode($normalized['capacity_override']);
            $payload['capacity_override_json'] = is_string($json) ? $json : null;
        }

        return $payload;
    }

    private function logAudit(string $action, ?Model $before, ?Model $after): void
    {
        $table = $this->wpdb->prefix . 'fp_audit_log';

        $beforePayload = $before !== null ? $this->exportModel($before) : null;
        $afterPayload  = $after !== null ? $this->exportModel($after) : null;

        $this->wpdb->insert($table, [
            'actor_id'    => get_current_user_id() ?: null,
            'actor_role'  => $this->resolveCurrentUserRole(),
            'action'      => $action,
            'entity'      => 'closure',
            'entity_id'   => $after?->id ?? $before?->id ?? null,
            'before_json' => $beforePayload !== null ? wp_json_encode($beforePayload) : null,
            'after_json'  => $afterPayload !== null ? wp_json_encode($afterPayload) : null,
            'created_at'  => current_time('mysql'),
            'ip'          => Helpers::clientIp(),
        ]);
    }

    private function resolveCurrentUserRole(): ?string
    {
        $user = wp_get_current_user();
        if (!$user instanceof WP_User || !is_array($user->roles) || $user->roles === []) {
            return null;
        }

        return (string) $user->roles[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportModel(Model $model): array
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
            'active'            => $model->active,
            'recurrence'        => $model->recurrence,
            'capacity_override' => $model->capacityOverride,
            'priority'          => $model->priority,
        ];
    }

    /**
     * @return array<int, array{start:DateTimeImmutable, end:DateTimeImmutable}>
     */
}
