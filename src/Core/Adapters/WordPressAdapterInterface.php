<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

/**
 * WordPress Adapter Interface
 * 
 * Provides abstraction for WordPress core functions.
 *
 * @package FP\Resv\Core\Adapters
 */
interface WordPressAdapterInterface
{
    /**
     * Get current time in specified format
     * 
     * @param string $type Type of time (mysql, timestamp, etc.)
     * @return string|int
     */
    public function currentTime(string $type = 'mysql');
    
    /**
     * Get WordPress timezone
     * 
     * @return \DateTimeZone
     */
    public function timezone(): \DateTimeZone;
    
    /**
     * Format date according to WordPress settings
     * 
     * @param string $format Date format
     * @param int|string $timestamp Timestamp or date string
     * @return string
     */
    public function formatDate(string $format, $timestamp = null): string;
    
    /**
     * Check if current user has capability
     * 
     * @param string $capability Capability name
     * @return bool
     */
    public function currentUserCan(string $capability): bool;
    
    /**
     * Get current user ID
     * 
     * @return int
     */
    public function currentUserId(): int;
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isUserLoggedIn(): bool;
}










