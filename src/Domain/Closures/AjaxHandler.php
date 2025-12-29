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
use function filter_var;
use function sanitize_text_field;
use function wp_json_encode;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_timezone_string;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_NULL_ON_FAILURE;

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
        // #region agent log
        $logFile = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '.cursor/debug.log';
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'AjaxHandler::register() called - hooks registered',
            'data' => [],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'A'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        // Register with inline closure to add immediate logging
        // Use priority 10 (default) to ensure it runs at the right time
        add_action('wp_ajax_fp_resv_closures_list', function () {
            $logFile = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '.cursor/debug.log';
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'wp_ajax_fp_resv_closures_list HOOK EXECUTED - calling handleList',
                'data' => [
                    'ob_level' => ob_get_level(),
                    'memory_usage' => memory_get_usage(true),
                    'error_get_last' => error_get_last() ? error_get_last()['message'] : null
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            
            try {
                $this->handleList();
            } catch (\Throwable $e) {
                $logData = json_encode([
                    'id' => 'log_' . time() . '_' . uniqid(),
                    'timestamp' => time() * 1000,
                    'location' => __FILE__ . ':' . __LINE__,
                    'message' => 'Exception in handleList call',
                    'data' => [
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'error_trace' => $e->getTraceAsString()
                    ],
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'E'
                ]) . "\n";
                @file_put_contents($logFile, $logData, FILE_APPEND);
                throw $e;
            }
        }, 0); // Priority 0 to register early, before other plugins' init hooks
        add_action('wp_ajax_fp_resv_closures_create', [$this, 'handleCreate'], 0);
        add_action('wp_ajax_fp_resv_closures_delete', [$this, 'handleDelete'], 0);
    }

    public function handleList(): void
    {
        // #region agent log
        $logFile = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '.cursor/debug.log';
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'handleList ENTRY - METHOD CALLED',
            'data' => [
                'ob_level_before' => ob_get_level(),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'action' => $_REQUEST['action'] ?? 'N/A',
                'has_nonce' => isset($_REQUEST['nonce'])
            ],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'A'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        // Start output buffering to catch any unintended output (HYPOTHESIS A)
        $obStarted = false;
        if (ob_get_level() === 0) {
            ob_start();
            $obStarted = true;
        } else {
            ob_clean();
        }
        
        // #region agent log
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'Output buffer status',
            'data' => ['ob_level_after' => ob_get_level(), 'ob_started' => $obStarted],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'A'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        // #region agent log
        $logFile = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '.cursor/debug.log';
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'Before check_ajax_referer',
            'data' => ['nonce_present' => isset($_REQUEST['nonce'])],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'A'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        error_log('[FP Closures AJAX] handleList() START');
        
        try {
            check_ajax_referer('fp_resv_admin', 'nonce');
        } catch (\Exception $e) {
            // #region agent log
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'check_ajax_referer FAILED',
                'data' => ['error' => $e->getMessage()],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            // #endregion
            throw $e;
        }
        
        // #region agent log
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'After check_ajax_referer - SUCCESS',
            'data' => [],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'A'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion

        if (!current_user_can('manage_options') && !current_user_can('manage_fp_reservations')) {
            error_log('[FP Closures AJAX] Permission denied');
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);
            return;
        }

        try {
            // Sanitize boolean - use rest_sanitize_boolean if available, otherwise manual check
            $includeInactive = false;
            if (isset($_REQUEST['include_inactive'])) {
                if (function_exists('rest_sanitize_boolean')) {
                    $includeInactive = rest_sanitize_boolean($_REQUEST['include_inactive']);
                } else {
                    // Fallback: manual boolean sanitization
                    $value = $_REQUEST['include_inactive'];
                    $includeInactive = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                    if ($includeInactive === null) {
                        $includeInactive = in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
                    }
                }
            }
            
            error_log('[FP Closures AJAX] Chiamata service->list()');
            
            // #region agent log
            $logFile = __DIR__ . '/../../../../../../.cursor/debug.log';
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'Before service->list call',
                'data' => ['include_inactive' => $includeInactive],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'B'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            $listStartTime = microtime(true);
            // #endregion
            
            $items = $this->service->list([
                'include_inactive' => $includeInactive,
            ]);
            
            // #region agent log
            $listEndTime = microtime(true);
            $listDuration = ($listEndTime - $listStartTime) * 1000;
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'After service->list call',
                'data' => [
                    'items_count' => is_array($items) ? count($items) : 0,
                    'list_duration_ms' => round($listDuration, 2)
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'B'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            // #endregion
            
            error_log('[FP Closures AJAX] Items ricevuti: ' . count($items));
            error_log('[FP Closures AJAX] Items JSON: ' . wp_json_encode($items));

            // #region agent log
            $obContents = ob_get_contents();
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'Before wp_send_json_success',
                'data' => [
                    'ob_contents_length' => strlen($obContents),
                    'ob_contents_preview' => substr($obContents, 0, 100)
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            // #endregion
            
            // Clean ALL output buffers before sending JSON
            // Handle multiple nested output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // Ensure no output is sent
            if (ob_get_level() === 0) {
                ob_start();
            }
            
            // Prevent caching of AJAX responses
            nocache_headers();
            header('X-FP-Resv-Cache: no-store');
            
            wp_send_json_success([
                'items' => $items,
                'count' => count($items),
            ]);
        } catch (\Throwable $e) {
            // #region agent log
            $logFile = __DIR__ . '/../../../../../../.cursor/debug.log';
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'Exception caught',
                'data' => [
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine()
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'E'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            // #endregion
            
            if ($obStarted) {
                ob_end_clean();
            } else {
                ob_clean();
            }
            
            error_log('[FP Closures AJAX] Errore: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function handleCreate(): void
    {
        // Clean any output buffer to ensure clean JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
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
        // Clean any output buffer to ensure clean JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
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

