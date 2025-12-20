<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

/**
 * Validator Interface
 * 
 * Provides validation functionality.
 *
 * @package FP\Resv\Core\Services
 */
interface ValidatorInterface
{
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool
     */
    public function isEmail(string $email): bool;
    
    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @param string $format Expected format (default: Y-m-d)
     * @return bool
     */
    public function isDate(string $date, string $format = 'Y-m-d'): bool;
    
    /**
     * Validate time format
     * 
     * @param string $time Time string
     * @param string $format Expected format (default: H:i)
     * @return bool
     */
    public function isTime(string $time, string $format = 'H:i'): bool;
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool
     */
    public function isUrl(string $url): bool;
    
    /**
     * Validate phone number
     * 
     * @param string $phone Phone number to validate
     * @return bool
     */
    public function isPhone(string $phone): bool;
    
    /**
     * Validate required field
     * 
     * @param mixed $value Value to check
     * @return bool
     */
    public function isRequired($value): bool;
}














