<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Base Service Provider
 * 
 * All service providers must extend this class and implement
 * register() and boot() methods.
 *
 * @package FP\Resv\Providers
 */
abstract class ServiceProvider
{
    /**
     * Register services with the container
     * 
     * This method is called during container initialization.
     * Use it to bind services, singletons, and factories.
     * 
     * @param Container $container Service container
     */
    abstract public function register(Container $container): void;
    
    /**
     * Boot services after registration
     * 
     * This method is called after all providers have been registered.
     * Use it to initialize services, register hooks, etc.
     * 
     * @param Container $container Service container
     */
    public function boot(Container $container): void
    {
        // Override in child classes if needed
    }
}










