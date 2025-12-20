<?php

declare(strict_types=1);

namespace FP\Resv\Core\Exceptions;

use RuntimeException;

/**
 * Validation Exception
 * 
 * Thrown when validation fails.
 *
 * @package FP\Resv\Core\Exceptions
 */
final class ValidationException extends RuntimeException
{
    /** @var array<string, string> Validation errors */
    private array $errors = [];
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param array<string, string> $errors Field-specific errors
     */
    public function __construct(string $message = 'Validation failed', array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
    
    /**
     * Get validation errors
     * 
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Add a validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }
    
    /**
     * Check if there are any errors
     * 
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
