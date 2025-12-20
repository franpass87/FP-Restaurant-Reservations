<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

use function add_action;
use function add_filter;
use function do_action;
use function apply_filters;

/**
 * Hooks Adapter
 * 
 * Wraps WordPress hook functions for dependency injection.
 *
 * @package FP\Resv\Core\Adapters
 */
final class HooksAdapter implements HooksAdapterInterface
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
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }
    
    /**
     * Add a filter hook
     * 
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $acceptedArgs Number of accepted arguments
     * @return void
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }
    
    /**
     * Execute an action
     * 
     * @param string $hook Hook name
     * @param mixed ...$args Arguments
     * @return void
     */
    public function doAction(string $hook, ...$args): void
    {
        do_action($hook, ...$args);
    }
    
    /**
     * Apply a filter
     * 
     * @param string $hook Hook name
     * @param mixed $value Value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed Filtered value
     */
    public function applyFilter(string $hook, $value, ...$args)
    {
        return apply_filters($hook, $value, ...$args);
    }
}










