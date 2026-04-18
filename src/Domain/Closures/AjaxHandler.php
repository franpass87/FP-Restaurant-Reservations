<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use function add_action;
use function array_key_exists;
use function check_ajax_referer;
use function current_user_can;
use function filter_var;
use function sanitize_key;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_NULL_ON_FAILURE;

final class AjaxHandler
{
    public function __construct(private readonly Service $service)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_fp_resv_closures_list', [$this, 'handleList']);
        add_action('wp_ajax_fp_resv_closures_create', [$this, 'handleCreate']);
        add_action('wp_ajax_fp_resv_closures_update', [$this, 'handleUpdate']);
        add_action('wp_ajax_fp_resv_closures_delete', [$this, 'handleDelete']);
    }

    public function handleList(): void
    {
        check_ajax_referer('fp_resv_admin', 'nonce');
        if (!$this->canManageClosures()) {
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);

            return;
        }

        try {
            $includeInactive = $this->sanitizeBoolean($_REQUEST['include_inactive'] ?? null);
            $items = $this->service->list([
                'include_inactive' => $includeInactive,
            ]);

            wp_send_json_success([
                'items' => $items,
                'count' => count($items),
            ]);
        } catch (InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handleCreate(): void
    {
        check_ajax_referer('fp_resv_admin', 'nonce');
        if (!$this->canManageClosures()) {
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);

            return;
        }

        try {
            $payload = $this->buildClosurePayloadFromRequest(false);
            $model   = $this->service->create($payload);
            $result  = $this->closureToAjaxRow($model);

            wp_send_json_success($result);
        } catch (InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handleUpdate(): void
    {
        check_ajax_referer('fp_resv_admin', 'nonce');
        if (!$this->canManageClosures()) {
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);

            return;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(['message' => 'ID non valido'], 400);

            return;
        }

        try {
            $payload = $this->buildClosurePayloadFromRequest(true);
            $model   = $this->service->update($id, $payload);
            $result  = $this->closureToAjaxRow($model);

            wp_send_json_success($result);
        } catch (InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handleDelete(): void
    {
        check_ajax_referer('fp_resv_admin', 'nonce');
        if (!$this->canManageClosures()) {
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);

            return;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            wp_send_json_error(['message' => 'ID non valido'], 400);
            return;
        }

        try {
            $this->service->deactivate($id);
            wp_send_json_success(['id' => $id, 'message' => 'Chiusura eliminata']);
        } catch (InvalidArgumentException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 404);
        } catch (RuntimeException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    private function canManageClosures(): bool
    {
        return current_user_can('manage_options') || current_user_can('manage_fp_reservations');
    }

    private function closureToAjaxRow(Model $model): array
    {
        return [
            'id'                => $model->id,
            'scope'             => $model->scope,
            'type'              => $model->type,
            'start_at'          => $model->startAt->format(DateTimeInterface::ATOM),
            'end_at'            => $model->endAt->format(DateTimeInterface::ATOM),
            'note'              => $model->note,
            'active'            => $model->active,
            'capacity_override' => $model->capacityOverride,
            'priority'          => $model->priority,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClosurePayloadFromRequest(bool $isUpdate): array
    {
        $payload = [
            'scope'            => sanitize_text_field((string) ($_POST['scope'] ?? 'restaurant')),
            'type'             => sanitize_text_field((string) ($_POST['type'] ?? 'full')),
            'start_at'         => sanitize_text_field((string) ($_POST['start_at'] ?? '')),
            'end_at'           => sanitize_text_field((string) ($_POST['end_at'] ?? '')),
            'note'             => sanitize_text_field((string) ($_POST['note'] ?? '')),
            'capacity_percent' => isset($_POST['capacity_percent']) ? (int) $_POST['capacity_percent'] : null,
        ];

        if (array_key_exists('active', $_POST)) {
            $payload['active'] = $this->sanitizeBoolean($_POST['active']);
        } elseif (!$isUpdate) {
            $payload['active'] = true;
        }

        if (($payload['type'] ?? '') === 'special_opening') {
            $payload['label']         = sanitize_text_field((string) ($_POST['label'] ?? ''));
            $payload['meal_key']      = isset($_POST['meal_key']) ? sanitize_key((string) $_POST['meal_key']) : '';
            $payload['capacity']      = isset($_POST['capacity']) ? (int) $_POST['capacity'] : 40;
            $payload['special_hours'] = $this->sanitizeSpecialHours($_POST['special_hours'] ?? []);
        }

        return $payload;
    }

    private function sanitizeBoolean(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Fasce orari per apertura speciale / orari speciali: array di { start, end, label? } o JSON in stringa.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeSpecialHours(mixed $rawSpecialHours): array
    {
        $specialHours = $rawSpecialHours;
        if (is_string($specialHours)) {
            $decoded = json_decode(stripslashes($specialHours), true);
            $specialHours = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($specialHours)) {
            return [];
        }

        $sanitized = [];
        foreach ($specialHours as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (isset($row['start']) || isset($row['end'])) {
                $s = sanitize_text_field((string) ($row['start'] ?? ''));
                $e = sanitize_text_field((string) ($row['end'] ?? ''));
                if ($s === '' || $e === '') {
                    continue;
                }
                $sanitized[] = [
                    'start' => $s,
                    'end'   => $e,
                    'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                ];

                continue;
            }
        }

        return $sanitized;
    }
}
