<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use function add_action;

final class AjaxDebug
{
    public function register(): void
    {
        // Hook into wp_ajax action BEFORE admin-ajax.php checks for it
        // This must be registered early, before admin-ajax.php loads
        add_action('wp_ajax_fp_resv_closures_list', function () {
            $logFile = (defined('ABSPATH') ? ABSPATH : '') . '.cursor/debug.log';
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'AjaxDebug: wp_ajax_fp_resv_closures_list HOOK FIRED (early)',
                'data' => [
                    'ob_level' => ob_get_level(),
                    'memory_usage' => memory_get_usage(true),
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
        }, 0); // Priority 0 to run first
        
        // Catch fatal errors and shutdown
        register_shutdown_function(function () {
            if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] === 'fp_resv_closures_list') {
                $error = error_get_last();
                $logFile = (defined('ABSPATH') ? ABSPATH : '') . '.cursor/debug.log';
                $logData = json_encode([
                    'id' => 'log_' . time() . '_' . uniqid(),
                    'timestamp' => time() * 1000,
                    'location' => __FILE__ . ':' . __LINE__,
                    'message' => 'Shutdown function called for AJAX request',
                    'data' => [
                        'error' => $error ? [
                            'type' => $error['type'],
                            'message' => $error['message'],
                            'file' => $error['file'],
                            'line' => $error['line']
                        ] : null,
                        'has_action' => has_action('wp_ajax_fp_resv_closures_list'),
                    ],
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A'
                ]) . "\n";
                @file_put_contents($logFile, $logData, FILE_APPEND);
            }
        });
        
        // Intercept all AJAX requests to debug - use earlier hook
        add_action('init', function () {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                $logFile = (defined('ABSPATH') ? ABSPATH : '') . '.cursor/debug.log';
                $logData = json_encode([
                    'id' => 'log_' . time() . '_' . uniqid(),
                    'timestamp' => time() * 1000,
                    'location' => __FILE__ . ':' . __LINE__,
                    'message' => 'AJAX REQUEST DETECTED in init',
                    'data' => [
                        'action' => $_REQUEST['action'] ?? 'N/A',
                        'has_nonce' => isset($_REQUEST['nonce']),
                        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                        'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
                    ],
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A'
                ]) . "\n";
                @file_put_contents($logFile, $logData, FILE_APPEND);
            }
        }, 1);
        
        // Also check in admin_init - check if hook is registered
        // Use early priority to run before other hooks
        add_action('admin_init', function () {
            $logFile = (defined('ABSPATH') ? ABSPATH : '') . '.cursor/debug.log';
            
            // Log ALL admin_init calls to see if it's executed for AJAX
            if (defined('DOING_AJAX') && DOING_AJAX) {
                $action = $_REQUEST['action'] ?? 'N/A';
                $hookName = 'wp_ajax_' . $action;
                $hasAction = has_action($hookName);
                
                $logData = json_encode([
                    'id' => 'log_' . time() . '_' . uniqid(),
                    'timestamp' => time() * 1000,
                    'location' => __FILE__ . ':' . __LINE__,
                    'message' => 'AJAX REQUEST DETECTED in admin_init - Hook check',
                    'data' => [
                        'action' => $action,
                        'hook_name' => $hookName,
                        'has_action' => $hasAction !== false,
                        'has_action_value' => $hasAction,
                        'has_nonce' => isset($_REQUEST['nonce']),
                        'is_user_logged_in' => is_user_logged_in(),
                        'all_actions_count' => did_action('admin_init'),
                    ],
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A'
                ]) . "\n";
                @file_put_contents($logFile, $logData, FILE_APPEND);
            }
        }, 1);
        
        // Check if hook is registered when WordPress looks for it
        add_action('admin_init', function () {
            if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] === 'fp_resv_closures_list') {
                $logFile = (defined('ABSPATH') ? ABSPATH : '') . '.cursor/debug.log';
                $hasAction = has_action('wp_ajax_fp_resv_closures_list');
                $logData = json_encode([
                    'id' => 'log_' . time() . '_' . uniqid(),
                    'timestamp' => time() * 1000,
                    'location' => __FILE__ . ':' . __LINE__,
                    'message' => 'Hook registration check in admin_init',
                    'data' => [
                        'has_action' => $hasAction !== false,
                        'has_action_value' => $hasAction,
                        'action' => $_REQUEST['action'] ?? 'N/A'
                    ],
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A'
                ]) . "\n";
                @file_put_contents($logFile, $logData, FILE_APPEND);
            }
        }, 999); // Late priority to run after other hooks
        
        // Hook into wp_ajax action to see if it fires
        add_action('wp_ajax_fp_resv_closures_list', function () {
            $logFile = (defined('ABSPATH') ? ABSPATH : '') . '.cursor/debug.log';
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'wp_ajax_fp_resv_closures_list HOOK FIRED',
                'data' => [],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
        }, 1); // Priority 1 to run before the actual handler
    }
}

