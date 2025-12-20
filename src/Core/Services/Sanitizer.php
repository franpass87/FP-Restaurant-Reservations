<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

use function sanitize_text_field;
use function sanitize_email;
use function esc_url_raw;
use function absint;
use function floatval;
use function esc_html;
use function esc_attr;
use function is_array;
use function is_string;
use function is_int;
use function is_float;

/**
 * Sanitizer Service
 * 
 * Provides input sanitization and output escaping using WordPress functions.
 *
 * @package FP\Resv\Core\Services
 */
final class Sanitizer implements SanitizerInterface
{
    /**
     * Sanitize text field
     * 
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    public function textField(string $value): string
    {
        return sanitize_text_field($value);
    }
    
    /**
     * Sanitize email
     * 
     * @param string $value Email to sanitize
     * @return string Sanitized email
     */
    public function email(string $value): string
    {
        return sanitize_email($value);
    }
    
    /**
     * Sanitize URL
     * 
     * @param string $value URL to sanitize
     * @return string Sanitized URL
     */
    public function url(string $value): string
    {
        return esc_url_raw($value);
    }
    
    /**
     * Sanitize integer
     * 
     * @param mixed $value Value to sanitize
     * @return int Sanitized integer
     */
    public function integer($value): int
    {
        if (is_int($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return absint($value);
        }
        
        if (is_float($value)) {
            return (int) $value;
        }
        
        return 0;
    }
    
    /**
     * Sanitize float
     * 
     * @param mixed $value Value to sanitize
     * @return float Sanitized float
     */
    public function float($value): float
    {
        if (is_float($value)) {
            return $value;
        }
        
        if (is_int($value)) {
            return (float) $value;
        }
        
        if (is_string($value)) {
            return floatval($value);
        }
        
        return 0.0;
    }
    
    /**
     * Sanitize array recursively
     * 
     * @param array<string|int, mixed> $value Array to sanitize
     * @return array<string|int, mixed> Sanitized array
     */
    public function array(array $value): array
    {
        $sanitized = [];
        
        foreach ($value as $key => $item) {
            $sanitizedKey = is_string($key) ? sanitize_text_field($key) : $key;
            
            if (is_string($item)) {
                $sanitized[$sanitizedKey] = sanitize_text_field($item);
            } elseif (is_array($item)) {
                $sanitized[$sanitizedKey] = $this->array($item);
            } elseif (is_int($item)) {
                $sanitized[$sanitizedKey] = $this->integer($item);
            } elseif (is_float($item)) {
                $sanitized[$sanitizedKey] = $this->float($item);
            } else {
                $sanitized[$sanitizedKey] = $item;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Escape HTML output
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escapeHtml(string $value): string
    {
        return esc_html($value);
    }
    
    /**
     * Escape attribute output
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escapeAttr(string $value): string
    {
        return esc_attr($value);
    }
}
