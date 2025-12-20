<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * WP-CLI Service Provider
 * 
 * Registers WP-CLI commands and related services.
 * Only loads in WP-CLI context.
 *
 * @package FP\Resv\Providers
 */
final class CLIServiceProvider extends ServiceProvider
{
    /**
     * Register CLI services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        // CLI services will be registered here in Phase 4
        // For now, this is a placeholder
    }
    
    /**
     * Boot CLI services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // CLI commands will be registered here in Phase 4
    }
}














