<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

/**
 * Database Adapter Interface
 * 
 * Provides abstraction for database operations.
 *
 * @package FP\Resv\Core\Adapters
 */
interface DatabaseAdapterInterface
{
    /**
     * Get table name with prefix
     * 
     * @param string $table Table name without prefix
     * @return string Full table name
     */
    public function getTableName(string $table): string;
    
    /**
     * Prepare SQL query
     * 
     * @param string $query SQL query with placeholders
     * @param mixed ...$args Arguments for placeholders
     * @return string Prepared query
     */
    public function prepare(string $query, ...$args): string;
    
    /**
     * Get database instance
     * 
     * @return \wpdb
     */
    public function getDb(): \wpdb;
}










