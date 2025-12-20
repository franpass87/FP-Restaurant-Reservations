<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

use function current_time;
use function wp_timezone;
use function wp_date;
use function current_user_can;
use function get_current_user_id;
use function is_user_logged_in;
use DateTimeZone;

/**
 * WordPress Adapter
 * 
 * Wraps WordPress core functions for dependency injection.
 *
 * @package FP\Resv\Core\Adapters
 */
final class WordPressAdapter implements WordPressAdapterInterface
{
    /**
     * Get current time in specified format
     * 
     * @param string $type Type of time (mysql, timestamp, etc.)
     * @return string|int
     */
    public function currentTime(string $type = 'mysql')
    {
        return current_time($type);
    }
    
    /**
     * Get WordPress timezone
     * 
     * @return \DateTimeZone
     */
    public function timezone(): DateTimeZone
    {
        return wp_timezone();
    }
    
    /**
     * Format date according to WordPress settings
     * 
     * @param string $format Date format
     * @param int|string|null $timestamp Timestamp or date string
     * @return string
     */
    public function formatDate(string $format, $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        return wp_date($format, $timestamp);
    }
    
    /**
     * Check if current user has capability
     * 
     * @param string $capability Capability name
     * @return bool
     */
    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }
    
    /**
     * Get current user ID
     * 
     * @return int
     */
    public function currentUserId(): int
    {
        return get_current_user_id();
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
