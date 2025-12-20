<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Kernel\LegacyBridge;

/**
 * Legacy Service Adapter
 * 
 * Provides adapter methods to bridge old code with new services.
 * This allows existing code to use new services without full refactoring.
 *
 * @package FP\Resv\Core\Adapters
 */
final class LegacyServiceAdapter
{
    /**
     * Get logger (replaces error_log calls)
     * 
     * @return LoggerInterface
     */
    public static function logger(): LoggerInterface
    {
        return LegacyBridge::get(\FP\Resv\Core\Services\LoggerInterface::class);
    }
    
    /**
     * Log debug message (replaces error_log with WP_DEBUG check)
     * 
     * @param string $message Message
     * @param array<string, mixed> $context Context
     * @return void
     */
    public static function logDebug(string $message, array $context = []): void
    {
        self::logger()->debug($message, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message
     * @param array<string, mixed> $context Context
     * @return void
     */
    public static function logInfo(string $message, array $context = []): void
    {
        self::logger()->info($message, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Message
     * @param array<string, mixed> $context Context
     * @return void
     */
    public static function logWarning(string $message, array $context = []): void
    {
        self::logger()->warning($message, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Message
     * @param array<string, mixed> $context Context
     * @return void
     */
    public static function logError(string $message, array $context = []): void
    {
        self::logger()->error($message, $context);
    }
}










