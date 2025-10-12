<?php

declare(strict_types=1);

namespace FP\Resv\Core;

/**
 * Logger centralizzato per errori del plugin
 */
final class ErrorLogger
{
    private const OPTION_NAME = 'fp_resv_error_log';
    private const MAX_ERRORS = 50; // Max errori da tenere in memoria

    /**
     * Log un errore
     */
    public static function log(string $message, array $context = []): void
    {
        $errors = self::getErrors();
        
        $errors[] = [
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
        ];
        
        // Mantieni solo gli ultimi MAX_ERRORS
        if (count($errors) > self::MAX_ERRORS) {
            $errors = array_slice($errors, -self::MAX_ERRORS);
        }
        
        update_option(self::OPTION_NAME, $errors, false);
        
        // Log anche su error_log se WP_DEBUG è attivo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
            error_log('[FP Resv Error] ' . $message . $contextStr);
        }
    }

    /**
     * Ottieni tutti gli errori
     */
    public static function getErrors(): array
    {
        $errors = get_option(self::OPTION_NAME, []);
        return is_array($errors) ? $errors : [];
    }

    /**
     * Ottieni gli ultimi N errori
     */
    public static function getRecentErrors(int $limit = 10): array
    {
        $errors = self::getErrors();
        return array_slice($errors, -$limit);
    }

    /**
     * Pulisci tutti gli errori
     */
    public static function clear(): void
    {
        delete_option(self::OPTION_NAME);
    }

    /**
     * Conta gli errori
     */
    public static function count(): int
    {
        return count(self::getErrors());
    }
}

