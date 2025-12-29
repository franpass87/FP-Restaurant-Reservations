<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Admin Service Provider
 * 
 * Registers admin-specific services, controllers, and hooks.
 * Only loads in admin context.
 *
 * @package FP\Resv\Providers
 */
final class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register admin services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerSettings($container);
        $this->registerControllers($container);
    }
    
    /**
     * Register settings admin
     */
    private function registerSettings(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Settings\Admin\SettingsSanitizer::class,
            \FP\Resv\Domain\Settings\Admin\SettingsSanitizer::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Settings\Admin\SettingsValidator::class,
            function (Container $container) {
                $sanitizer = $container->get(\FP\Resv\Domain\Settings\Admin\SettingsSanitizer::class);
                return new \FP\Resv\Domain\Settings\Admin\SettingsValidator($sanitizer);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Settings\AdminPages::class,
            function (Container $container) {
                // Ensure admin has capabilities before registering menu
                \FP\Resv\Core\Roles::ensureAdminCapabilities();
                
                $sanitizer = $container->get(\FP\Resv\Domain\Settings\Admin\SettingsSanitizer::class);
                $validator = $container->get(\FP\Resv\Domain\Settings\Admin\SettingsValidator::class);
                $adminPages = new \FP\Resv\Domain\Settings\AdminPages($sanitizer, $validator);
                $adminPages->register();
                return $adminPages;
            }
        );
        
        $container->alias('settings.admin_pages', \FP\Resv\Domain\Settings\AdminPages::class);
    }
    
    /**
     * Register admin controllers
     */
    private function registerControllers(Container $container): void
    {
        // Reservations admin controller
        $container->singleton(
            \FP\Resv\Domain\Reservations\AdminController::class,
            function (Container $container) {
                $controller = new \FP\Resv\Domain\Reservations\AdminController();
                $controller->register();
                return $controller;
            }
        );
        
        $container->alias('reservations.admin_controller', \FP\Resv\Domain\Reservations\AdminController::class);
        
        // Tables admin controller (conditional)
        $container->singleton(
            \FP\Resv\Domain\Tables\AdminController::class,
            function (Container $container) {
                $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
                $tablesEnabled = (string) $options->get('fp_resv_general', 'tables_enabled', '0') === '1';
                
                if (!$tablesEnabled) {
                    return null; // Will be handled by has() check
                }
                
                $tablesLayout = $container->get(\FP\Resv\Domain\Tables\LayoutService::class);
                $controller = new \FP\Resv\Domain\Tables\AdminController($tablesLayout);
                $controller->register();
                return $controller;
            }
        );
        
        $container->alias('tables.admin_controller', \FP\Resv\Domain\Tables\AdminController::class);
        
        // Store feature flag
        $container->singleton('feature.tables_enabled', function (Container $container) {
            $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
            return (string) $options->get('fp_resv_general', 'tables_enabled', '0') === '1';
        });
        
        // Closures admin controller
        $container->singleton(
            \FP\Resv\Domain\Closures\AdminController::class,
            function (Container $container) {
                $closuresService = $container->get(\FP\Resv\Domain\Closures\Service::class);
                $controller = new \FP\Resv\Domain\Closures\AdminController($closuresService);
                $controller->register();
                return $controller;
            }
        );
        
        $container->alias('closures.admin_controller', \FP\Resv\Domain\Closures\AdminController::class);
        
        // Reports admin controller
        $container->singleton(
            \FP\Resv\Domain\Reports\AdminController::class,
            function (Container $container) {
                $reportsService = $container->get(\FP\Resv\Domain\Reports\Service::class);
                $controller = new \FP\Resv\Domain\Reports\AdminController($reportsService);
                $controller->register();
                return $controller;
            }
        );
        
        $container->alias('reports.admin_controller', \FP\Resv\Domain\Reports\AdminController::class);
        
        // Diagnostics admin controller
        $container->singleton(
            \FP\Resv\Domain\Diagnostics\AdminController::class,
            function (Container $container) {
                $diagnosticsService = $container->get(\FP\Resv\Domain\Diagnostics\Service::class);
                $controller = new \FP\Resv\Domain\Diagnostics\AdminController($diagnosticsService);
                $controller->register();
                return $controller;
            }
        );
        
        $container->alias('diagnostics.admin_controller', \FP\Resv\Domain\Diagnostics\AdminController::class);
        
        // Presentation layer controller (new architecture)
        $container->singleton(
            \FP\Resv\Presentation\Admin\Controllers\ReservationsController::class,
            \FP\Resv\Presentation\Admin\Controllers\ReservationsController::class
        );
    }
    
    /**
     * Boot admin services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Ensure AdminPages is instantiated and registered early
        // This ensures hooks are registered before admin_menu fires
        if ($container->has(\FP\Resv\Domain\Settings\AdminPages::class)) {
            $container->get(\FP\Resv\Domain\Settings\AdminPages::class);
        }
        
        // Ensure AdminController is instantiated to register the Manager menu
        if ($container->has(\FP\Resv\Domain\Reservations\AdminController::class)) {
            $container->get(\FP\Resv\Domain\Reservations\AdminController::class);
        }
        
        // Ensure ClosuresAdminController is instantiated to register the Closures SPA menu
        if ($container->has(\FP\Resv\Domain\Closures\AdminController::class)) {
            $container->get(\FP\Resv\Domain\Closures\AdminController::class);
        }
        
        // Ensure TablesAdminController is instantiated to register the Tables menu
        if ($container->has(\FP\Resv\Domain\Tables\AdminController::class)) {
            $container->get(\FP\Resv\Domain\Tables\AdminController::class);
        }
        
        // Ensure ReportsAdminController is instantiated to register the Reports menu
        if ($container->has(\FP\Resv\Domain\Reports\AdminController::class)) {
            $container->get(\FP\Resv\Domain\Reports\AdminController::class);
        }
        
        // Ensure DiagnosticsAdminController is instantiated to register the Diagnostics menu
        if ($container->has(\FP\Resv\Domain\Diagnostics\AdminController::class)) {
            $container->get(\FP\Resv\Domain\Diagnostics\AdminController::class);
        }
        
        // Admin hooks are registered during controller instantiation
        // QA CLI and REST handlers are registered by BusinessServiceProvider
        if ($container->has(\FP\Resv\Domain\QA\CLI::class)) {
            $container->get(\FP\Resv\Domain\QA\CLI::class)->register();
        }
        
        if ($container->has(\FP\Resv\Domain\QA\REST::class)) {
            $container->get(\FP\Resv\Domain\QA\REST::class)->register();
        }
    }
}





