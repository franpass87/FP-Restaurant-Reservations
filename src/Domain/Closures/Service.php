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
        private readonly Options $options
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
        $normalized = $this->normalizePayload($data, null);
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

        $normalized = $this->normalizePayload($data, $existing);
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

        $events          = [];
        $blockedHours    = 0.0;
        $capacityPercent = [];
        $scopeHits       = [];
        $specialHours    = 0;

        foreach ($models as $model) {
            $occurrences = $this->expandOccurrences($model, $start, $end);
            foreach ($occurrences as $occurrence) {
                $events[] = [
                    'id'                => $model->id,
                    'scope'             => $model->scope,
                    'type'              => $model->type,
                    'start'             => $occurrence['start']->format(DateTimeInterface::ATOM),
                    'end'               => $occurrence['end']->format(DateTimeInterface::ATOM),
                    'note'              => $model->note,
                    'priority'          => $model->priority,
                    'capacity_override' => $model->capacityOverride,
                    'active'            => $model->active,
                ];

                $scopeHits[$model->scope] = ($scopeHits[$model->scope] ?? 0) + 1;

                if ($model->type === 'full' && $model->scope === 'restaurant') {
                    $blockedHours += ($occurrence['end']->getTimestamp() - $occurrence['start']->getTimestamp()) / 3600;
                }

                if ($model->capacityOverride !== null && array_key_exists('percent', $model->capacityOverride)) {
                    $capacityPercent[] = (int) $model->capacityOverride['percent'];
                }

                if ($model->type === 'special_hours') {
                    ++$specialHours;
                }
            }
        }

        usort(
            $events,
            static function (array $a, array $b): int {
                if ($a['start'] === $b['start']) {
                    return $b['priority'] <=> $a['priority'];
                }

                return strcmp($a['start'], $b['start']);
            }
        );

        sort($capacityPercent);

        return [
            'range'   => [
                'start' => $start->format(DateTimeInterface::ATOM),
                'end'   => $end->format(DateTimeInterface::ATOM),
            ],
            'events'  => $events,
            'summary' => [
                'total_events'       => count($events),
                'blocked_hours'      => round($blockedHours, 2),
                'capacity_reduction' => [
                    'count' => count($capacityPercent),
                    'min'   => $capacityPercent[0] ?? null,
                    'max'   => $capacityPercent !== [] ? $capacityPercent[count($capacityPercent) - 1] : null,
                ],
                'special_hours'      => $specialHours,
                'impacted_scopes'    => array_map('intval', $scopeHits),
            ],
        ];
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
     * @param array<string, mixed> $data
     * @param Model|null $existing
     *
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?Model $existing): array
    {
        $defaults = $this->options->getGroup('fp_resv_closures', [
            'closure_default_scope'     => 'restaurant',
            'closure_capacity_override' => '100',
        ]);

        $scope = sanitize_key((string) ($data['scope'] ?? $existing?->scope ?? $defaults['closure_default_scope'] ?? 'restaurant'));
        if (!in_array($scope, ['restaurant', 'room', 'table'], true)) {
            $scope = 'restaurant';
        }

        $type = sanitize_key((string) ($data['type'] ?? $existing?->type ?? 'full'));
        if (!in_array($type, ['full', 'capacity_reduction', 'special_hours'], true)) {
            $type = 'full';
        }

        $timezone = wp_timezone();

        $start = $this->parseDateTime($data['start_at'] ?? null, $timezone) ?? $existing?->startAt ?? null;
        $end   = $this->parseDateTime($data['end_at'] ?? null, $timezone) ?? $existing?->endAt ?? null;

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Start and end date are required.');
        }

        if ($end <= $start) {
            throw new InvalidArgumentException('The end datetime must be after the start datetime.');
        }

        $roomId  = null;
        $tableId = null;

        if ($scope === 'room' || $scope === 'table') {
            $roomId = isset($data['room_id']) ? absint($data['room_id']) : $existing?->roomId;
            if ($roomId === null || $roomId <= 0) {
                throw new InvalidArgumentException('Seleziona una sala valida per la chiusura.');
            }
        }

        if ($scope === 'table') {
            $tableId = isset($data['table_id']) ? absint($data['table_id']) : $existing?->tableId;
            if ($tableId === null || $tableId <= 0) {
                throw new InvalidArgumentException('Seleziona un tavolo valido per la chiusura.');
            }
        }

        $note   = sanitize_textarea_field((string) ($data['note'] ?? $existing?->note ?? ''));
        $active = isset($data['active']) ? (bool) $data['active'] : ($existing?->active ?? true);

        $recurrence = null;
        if (isset($data['recurrence']) && is_array($data['recurrence'])) {
            $recurrence = $this->sanitizeRecurrence($data['recurrence']);
        } elseif ($existing?->recurrence !== null) {
            $recurrence = $existing->recurrence;
        }

        $capacityOverride = $this->buildCapacityOverride($type, $data, $existing, (int) ($defaults['closure_capacity_override'] ?? 100));

        return [
            'scope'             => $scope,
            'type'              => $type,
            'start'             => $start,
            'end'               => $end,
            'room_id'           => $roomId,
            'table_id'          => $tableId,
            'note'              => $note,
            'active'            => $active,
            'recurrence'        => $recurrence,
            'capacity_override' => $capacityOverride,
        ];
    }

    private function parseDateTime(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        
        error_log('[FP Closures Service] parseDateTime input: "' . $value . '"');
        error_log('[FP Closures Service] Timezone: ' . $timezone->getName());
        
        // Se la stringa ha già un timezone/offset, NON passare $timezone come secondo parametro
        // perché PHP ignorerebbe l'offset nella stringa e applicherebbe il secondo parametro
        $hasOffset = preg_match('/[+-]\d{2}:\d{2}$/', $value) || preg_match('/[+-]\d{4}$/', $value);
        
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if ($date instanceof DateTimeImmutable) {
            error_log('[FP Closures Service] Parsed as ATOM: ' . $date->format('Y-m-d H:i:s T P'));
            return $date;
        }

        try {
            // Se ha offset, parsea SENZA secondo parametro per rispettare l'offset nella stringa
            // Altrimenti usa il timezone di WordPress
            $result = $hasOffset 
                ? new DateTimeImmutable($value)
                : new DateTimeImmutable($value, $timezone);
            error_log('[FP Closures Service] Parsed as string (hasOffset=' . ($hasOffset ? 'true' : 'false') . '): ' . $result->format('Y-m-d H:i:s T P'));
            return $result;
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Formato data/ora non valido: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $recurrence
     *
     * @return array<string, mixed>
     */
    private function sanitizeRecurrence(array $recurrence): array
    {
        $type = sanitize_key((string) ($recurrence['type'] ?? ''));
        if (!in_array($type, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Tipo di ricorrenza non supportato.');
        }

        $result = ['type' => $type];

        if (isset($recurrence['from'])) {
            $result['from'] = $this->sanitizeDate((string) $recurrence['from']);
        }

        if (isset($recurrence['until'])) {
            $result['until'] = $this->sanitizeDate((string) $recurrence['until']);
        }

        if (isset($recurrence['days']) && is_array($recurrence['days'])) {
            $days = [];
            foreach ($recurrence['days'] as $day) {
                $dayValue = sanitize_key((string) $day);
                if ($dayValue === '') {
                    continue;
                }
                $days[] = $dayValue;
            }

            $result['days'] = array_values(array_unique($days));
        }

        if (isset($recurrence['week_of_month'])) {
            $result['week_of_month'] = sanitize_key((string) $recurrence['week_of_month']);
        }

        return $result;
    }

    private function sanitizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Le date di ricorrenza devono essere nel formato YYYY-MM-DD.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildCapacityOverride(string $type, array $data, ?Model $existing, int $defaultPercent): ?array
    {
        if ($type === 'capacity_reduction') {
            $percent = isset($data['capacity_percent']) ? (int) $data['capacity_percent'] : ($existing?->capacityOverride['percent'] ?? $defaultPercent);
            $percent = max(0, min(100, $percent));
            $unassigned = isset($data['unassigned_capacity']) ? max(0, (int) $data['unassigned_capacity']) : (int) ($existing?->capacityOverride['unassigned'] ?? 0);

            return [
                'mode'       => 'capacity_reduction',
                'percent'    => $percent,
                'unassigned' => $unassigned,
            ];
        }

        if ($type === 'special_hours') {
            $slots = [];
            if (isset($data['special_hours']) && is_array($data['special_hours'])) {
                foreach ($data['special_hours'] as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }

                    $slotStart = sanitize_text_field((string) ($slot['start'] ?? ''));
                    $slotEnd   = sanitize_text_field((string) ($slot['end'] ?? ''));
                    if ($slotStart === '' || $slotEnd === '') {
                        continue;
                    }

                    $slots[] = [
                        'start' => $slotStart,
                        'end'   => $slotEnd,
                        'label' => sanitize_text_field((string) ($slot['label'] ?? '')),
                    ];
                }
            } elseif ($existing?->capacityOverride['slots'] ?? null) {
                $slots = is_array($existing->capacityOverride['slots']) ? $existing->capacityOverride['slots'] : [];
            }

            $label = sanitize_text_field((string) ($data['label'] ?? $existing?->capacityOverride['label'] ?? ''));
            $percent = isset($data['capacity_percent']) ? (int) $data['capacity_percent'] : (int) ($existing?->capacityOverride['percent'] ?? 100);
            $percent = max(0, min(100, $percent));

            return [
                'mode'    => 'special_hours',
                'label'   => $label,
                'percent' => $percent,
                'slots'   => $slots,
            ];
        }

        return $existing?->capacityOverride ?? null;
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
    private function expandOccurrences(Model $model, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        if ($model->recurrence === null) {
            if ($model->endAt < $rangeStart || $model->startAt > $rangeEnd) {
                return [];
            }

            return [[
                'start' => $model->startAt > $rangeStart ? $model->startAt : $rangeStart,
                'end'   => $model->endAt < $rangeEnd ? $model->endAt : $rangeEnd,
            ]];
        }

        $occurrences = [];
        $cursor      = $rangeStart->setTime(0, 0, 0);
        $limit       = $rangeEnd->setTime(23, 59, 59);
        $timezone    = $model->startAt->getTimezone();

        while ($cursor <= $limit) {
            if ($this->recurrenceMatches($model, $cursor)) {
                $occurrenceStart = new DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $model->startAt->format('H:i:s'), $timezone);
                $occurrenceEnd   = new DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $model->endAt->format('H:i:s'), $timezone);

                if ($occurrenceEnd <= $occurrenceStart) {
                    $occurrenceEnd = $occurrenceEnd->add(new DateInterval('P1D'));
                }

                if ($occurrenceEnd < $rangeStart || $occurrenceStart > $rangeEnd) {
                    $cursor = $cursor->add(new DateInterval('P1D'));
                    continue;
                }

                $occurrences[] = [
                    'start' => $occurrenceStart > $rangeStart ? $occurrenceStart : $rangeStart,
                    'end'   => $occurrenceEnd < $rangeEnd ? $occurrenceEnd : $rangeEnd,
                ];
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $occurrences;
    }

    private function recurrenceMatches(Model $model, DateTimeImmutable $day): bool
    {
        $recurrence = $model->recurrence;
        if ($recurrence === null) {
            return false;
        }

        $timezone = $model->startAt->getTimezone();

        if (isset($recurrence['from']) && $recurrence['from'] !== '') {
            $from = new DateTimeImmutable($recurrence['from'] . ' 00:00:00', $timezone);
            if ($day < $from) {
                return false;
            }
        }

        if (isset($recurrence['until']) && $recurrence['until'] !== '') {
            $until = new DateTimeImmutable($recurrence['until'] . ' 23:59:59', $timezone);
            if ($day > $until) {
                return false;
            }
        }

        $type = sanitize_key((string) ($recurrence['type'] ?? ''));

        if ($type === 'daily') {
            return true;
        }

        if ($type === 'weekly') {
            $days = $recurrence['days'] ?? [];
            if (!is_array($days) || $days === []) {
                return false;
            }

            $dayName   = strtolower($day->format('D'));
            $dayNumber = $day->format('N');

            foreach ($days as $candidate) {
                $candidate = strtolower((string) $candidate);
                if ($candidate === $dayName || $candidate === $dayNumber) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'monthly') {
            $days = $recurrence['days'] ?? [];
            if (is_array($days) && $days !== []) {
                $dayOfMonth = (int) $day->format('j');
                foreach ($days as $candidate) {
                    if ((int) $candidate === $dayOfMonth) {
                        return true;
                    }
                }

                return false;
            }

            if (isset($recurrence['week_of_month'])) {
                $week    = strtolower((string) $recurrence['week_of_month']);
                if ($week === '') {
                    return false;
                }

                $weekday = strtolower($day->format('D'));
                $first   = $day->modify('first day of this month');
                $cursor  = $first;
                $index   = 0;

                while ($cursor->format('m') === $day->format('m')) {
                    if (strtolower($cursor->format('D')) === $weekday) {
                        ++$index;
                        $isLast = $cursor->modify('+7 days')->format('m') !== $day->format('m');
                        if ($cursor->format('j') === $day->format('j')) {
                            if ($week === 'last' && $isLast) {
                                return true;
                            }

                            $map = [
                                'first'  => 1,
                                'second' => 2,
                                'third'  => 3,
                                'fourth' => 4,
                            ];

                            if (isset($map[$week]) && $map[$week] === $index) {
                                return true;
                            }
                        }
                    }

                    $cursor = $cursor->add(new DateInterval('P1D'));
                }

                return false;
            }

            return false;
        }

        return false;
    }
}
