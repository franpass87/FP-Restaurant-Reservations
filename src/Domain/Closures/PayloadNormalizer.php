<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Domain\Settings\Options;
use InvalidArgumentException;
use function absint;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function min;
use function preg_match;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function strtolower;
use function trim;
use function wp_timezone;

/**
 * Gestisce la normalizzazione dei payload per le chiusure.
 * Estratto da Service.php per migliorare modularità.
 */
final class PayloadNormalizer
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function normalizePayload(array $data, ?Model $existing): array
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
        if (!in_array($type, Model::VALID_TYPES, true)) {
            $type = Model::TYPE_FULL;
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
        
        // Se la stringa ha già un timezone/offset, NON passare $timezone come secondo parametro
        // perché PHP ignorerebbe l'offset nella stringa e applicherebbe il secondo parametro
        $hasOffset = preg_match('/[+-]\d{2}:\d{2}$/', $value) || preg_match('/[+-]\d{4}$/', $value);
        
        $date = DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        try {
            // Se ha offset, parsea SENZA secondo parametro per rispettare l'offset nella stringa
            if ($hasOffset) {
                $parsed = new DateTimeImmutable($value);
            } else {
                // Se non ha offset, usa il timezone fornito
                $parsed = new DateTimeImmutable($value, $timezone);
            }
            
            return $parsed;
        } catch (\Exception) {
            return null;
        }
    }

    /**
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
     *
     * @return array<string, mixed>|null
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

        if ($type === Model::TYPE_SPECIAL_OPENING) {
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
            $mealKey = sanitize_key((string) ($data['meal_key'] ?? $existing?->capacityOverride['meal_key'] ?? ''));
            
            // Auto-generate meal_key if not provided
            if ($mealKey === '' && $label !== '') {
                $mealKey = 'special_' . sanitize_key($label) . '_' . time();
            }
            
            $capacity = isset($data['capacity']) ? max(1, (int) $data['capacity']) : (int) ($existing?->capacityOverride['capacity'] ?? 40);

            return [
                'mode'     => Model::TYPE_SPECIAL_OPENING,
                'label'    => $label,
                'meal_key' => $mealKey,
                'capacity' => $capacity,
                'slots'    => $slots,
            ];
        }

        return $existing?->capacityOverride ?? null;
    }
}
















