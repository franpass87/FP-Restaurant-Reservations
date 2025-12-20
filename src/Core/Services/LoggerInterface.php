<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

/**
 * Logger Interface
 * 
 * Provides structured logging with context support.
 *
 * @package FP\Resv\Core\Services
 */
interface LoggerInterface
{
    /**
     * Log a debug message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function debug(string $message, array $context = []): void;
    
    /**
     * Log an info message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function info(string $message, array $context = []): void;
    
    /**
     * Log a warning message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function warning(string $message, array $context = []): void;
    
    /**
     * Log an error message
     * 
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function error(string $message, array $context = []): void;
}














