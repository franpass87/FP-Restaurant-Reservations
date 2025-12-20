<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

use function filter_var;
use function preg_match;
use function trim;
use DateTimeImmutable;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

/**
 * Validator Service
 * 
 * Provides validation functionality for common data types.
 *
 * @package FP\Resv\Core\Services
 */
final class Validator implements ValidatorInterface
{
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool
     */
    public function isEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @param string $format Expected format (default: Y-m-d)
     * @return bool
     */
    public function isDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTimeImmutable::createFromFormat($format, $date);
        return $d !== false && $d->format($format) === $date;
    }
    
    /**
     * Validate time format
     * 
     * @param string $time Time string
     * @param string $format Expected format (default: H:i)
     * @return bool
     */
    public function isTime(string $time, string $format = 'H:i'): bool
    {
        $t = DateTimeImmutable::createFromFormat($format, $time);
        return $t !== false && $t->format($format) === $time;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool
     */
    public function isUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate phone number
     * 
     * @param string $phone Phone number to validate
     * @return bool
     */
    public function isPhone(string $phone): bool
    {
        // Basic phone validation - allows international format
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        return strlen($cleaned) >= 7 && strlen($cleaned) <= 20;
    }
    
    /**
     * Validate required field
     * 
     * @param mixed $value Value to check
     * @return bool
     */
    public function isRequired($value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        
        if (is_array($value)) {
            return $value !== [];
        }
        
        return $value !== null;
    }
}

