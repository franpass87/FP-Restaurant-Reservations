<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

use function esc_sql;
use wpdb;

/**
 * Database Adapter
 * 
 * Wraps WordPress database functions for dependency injection.
 *
 * @package FP\Resv\Core\Adapters
 */
final class DatabaseAdapter implements DatabaseAdapterInterface
{
    private wpdb $wpdb;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get table name with prefix
     * 
     * @param string $table Table name without prefix
     * @return string Full table name
     */
    public function getTableName(string $table): string
    {
        return $this->wpdb->prefix . $table;
    }
    
    /**
     * Prepare SQL query
     * 
     * @param string $query SQL query with placeholders
     * @param mixed ...$args Arguments for placeholders
     * @return string Prepared query
     */
    public function prepare(string $query, ...$args): string
    {
        return $this->wpdb->prepare($query, ...$args);
    }
    
    /**
     * Get database instance
     * 
     * @return \wpdb
     */
    public function getDb(): wpdb
    {
        return $this->wpdb;
    }
}










