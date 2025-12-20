<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

/**
 * Sanitizer Interface
 * 
 * Provides input sanitization and output escaping functionality.
 *
 * @package FP\Resv\Core\Services
 */
interface SanitizerInterface
{
    /**
     * Sanitize text field
     * 
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    public function textField(string $value): string;
    
    /**
     * Sanitize email
     * 
     * @param string $value Email to sanitize
     * @return string Sanitized email
     */
    public function email(string $value): string;
    
    /**
     * Sanitize URL
     * 
     * @param string $value URL to sanitize
     * @return string Sanitized URL
     */
    public function url(string $value): string;
    
    /**
     * Sanitize integer
     * 
     * @param mixed $value Value to sanitize
     * @return int Sanitized integer
     */
    public function integer($value): int;
    
    /**
     * Sanitize float
     * 
     * @param mixed $value Value to sanitize
     * @return float Sanitized float
     */
    public function float($value): float;
    
    /**
     * Sanitize array recursively
     * 
     * @param array<string|int, mixed> $value Array to sanitize
     * @return array<string|int, mixed> Sanitized array
     */
    public function array(array $value): array;
    
    /**
     * Escape HTML output
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escapeHtml(string $value): string;
    
    /**
     * Escape attribute output
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escapeAttr(string $value): string;
}
