<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

use function defined;
use function error_log;
use function get_current_user_id;
use function is_admin;
use function wp_json_encode;

/**
 * Logger Service
 * 
 * Provides structured logging with context support and WP_DEBUG gating.
 *
 * @package FP\Resv\Core\Services
 */
final class Logger implements LoggerInterface
{
    private const LOG_PREFIX = '[FP Resv]';
    
    /**
     * Log a debug message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        // Debug logs only in WP_DEBUG mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Log an info message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log a warning message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log an error message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Internal log method
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Build log entry
        $logEntry = sprintf(
            '%s [%s] %s',
            self::LOG_PREFIX,
            $level,
            $message
        );
        
        // Add context if present
        if ($context !== []) {
            // Add automatic context
            $fullContext = $this->enrichContext($context);
            $logEntry .= ' ' . wp_json_encode($fullContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        // Write to error log
        error_log($logEntry);
    }
    
    /**
     * Enrich context with automatic information
     * 
     * @param array<string, mixed> $context Original context
     * @return array<string, mixed> Enriched context
     */
    private function enrichContext(array $context): array
    {
        $enriched = $context;
        
        // Add user ID if available
        if (function_exists('get_current_user_id')) {
            $userId = get_current_user_id();
            if ($userId > 0) {
                $enriched['user_id'] = $userId;
            }
        }
        
        // Add context type
        if (is_admin()) {
            $enriched['context'] = 'admin';
        } elseif (defined('REST_REQUEST') && REST_REQUEST) {
            $enriched['context'] = 'rest';
        } elseif (defined('WP_CLI') && WP_CLI) {
            $enriched['context'] = 'cli';
        } else {
            $enriched['context'] = 'frontend';
        }
        
        return $enriched;
    }
}














