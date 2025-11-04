<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use function add_action;
use function check_ajax_referer;
use function current_user_can;
use function sanitize_text_field;
use function wp_json_encode;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_timezone_string;

/**
 * Handler AJAX per Closures - Più robusto di REST API
 */
final class AjaxHandler
{
    public function __construct(private readonly Service $service)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_fp_resv_closures_list', [$this, 'handleList']);
        add_action('wp_ajax_fp_resv_closures_create', [$this, 'handleCreate']);
        add_action('wp_ajax_fp_resv_closures_delete', [$this, 'handleDelete']);
    }

    public function handleList(): void
    {
        error_log('[FP Closures AJAX] handleList() START');
        
        check_ajax_referer('fp_resv_admin', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_fp_reservations')) {
            error_log('[FP Closures AJAX] Permission denied');
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);
            return;
        }

        try {
            $includeInactive = isset($_REQUEST['include_inactive']) && rest_sanitize_boolean($_REQUEST['include_inactive']);
            
            error_log('[FP Closures AJAX] Chiamata service->list()');
            
            $items = $this->service->list([
                'include_inactive' => $includeInactive,
            ]);
            
            error_log('[FP Closures AJAX] Items ricevuti: ' . count($items));
            error_log('[FP Closures AJAX] Items JSON: ' . wp_json_encode($items));

            wp_send_json_success([
                'items' => $items,
                'count' => count($items),
            ]);
        } catch (\Throwable $e) {
            error_log('[FP Closures AJAX] Errore: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handleCreate(): void
    {
        error_log('[FP Closures AJAX] handleCreate() START');
        
        check_ajax_referer('fp_resv_admin', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_fp_reservations')) {
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);
            return;
        }

        try {
            error_log('[FP Closures AJAX] $_POST ricevuto: ' . wp_json_encode($_POST));
            
            $payload = [
                'scope'            => sanitize_text_field($_POST['scope'] ?? 'restaurant'),
                'type'             => sanitize_text_field($_POST['type'] ?? 'full'),
                'start_at'         => sanitize_text_field($_POST['start_at'] ?? ''),
                'end_at'           => sanitize_text_field($_POST['end_at'] ?? ''),
                'note'             => sanitize_text_field($_POST['note'] ?? ''),
                'active'           => true,
                'capacity_percent' => isset($_POST['capacity_percent']) ? (int) $_POST['capacity_percent'] : null,
            ];

            error_log('[FP Closures AJAX] Payload sanitizzato: ' . wp_json_encode($payload));
            error_log('[FP Closures AJAX] Timezone WordPress: ' . wp_timezone_string());

            $model = $this->service->create($payload);
            
            error_log('[FP Closures AJAX] Chiusura creata con ID: ' . $model->id);

            // Converti Model in array
            $result = [
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

            error_log('[FP Closures AJAX] Response: ' . wp_json_encode($result));

            wp_send_json_success($result);
        } catch (InvalidArgumentException $e) {
            error_log('[FP Closures AJAX] Validation error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            error_log('[FP Closures AJAX] Runtime error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handleDelete(): void
    {
        check_ajax_referer('fp_resv_admin', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_fp_reservations')) {
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
}

