<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

/**
 * Hooks Adapter Interface
 * 
 * Provides abstraction for WordPress hooks (actions and filters).
 *
 * @package FP\Resv\Core\Adapters
 */
interface HooksAdapterInterface
{
    /**
     * Add an action hook
     * 
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $acceptedArgs Number of accepted arguments
     * @return void
     */
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;
    
    /**
     * Add a filter hook
     * 
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $acceptedArgs Number of accepted arguments
     * @return void
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;
    
    /**
     * Execute an action
     * 
     * @param string $hook Hook name
     * @param mixed ...$args Arguments
     * @return void
     */
    public function doAction(string $hook, ...$args): void;
    
    /**
     * Apply a filter
     * 
     * @param string $hook Hook name
     * @param mixed $value Value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed Filtered value
     */
    public function applyFilter(string $hook, $value, ...$args);
}










