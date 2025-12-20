<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Frontend Service Provider
 * 
 * Registers frontend-specific services, shortcodes, blocks, and assets.
 *
 * @package FP\Resv\Providers
 */
final class FrontendServiceProvider extends ServiceProvider
{
    /**
     * Register frontend services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerAssetManagers($container);
        $this->registerShortcodes($container);
        $this->registerWidgets($container);
        $this->registerEventsCPT($container);
        $this->registerManageController($container);
    }
    
    /**
     * Register asset managers
     */
    private function registerAssetManagers(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Frontend\AssetManager::class,
            \FP\Resv\Frontend\AssetManager::class
        );
        
        $container->singleton(
            \FP\Resv\Frontend\CriticalCssManager::class,
            \FP\Resv\Frontend\CriticalCssManager::class
        );
        
        $container->singleton(
            \FP\Resv\Frontend\PageBuilderCompatibility::class,
            \FP\Resv\Frontend\PageBuilderCompatibility::class
        );
        
        $container->singleton(
            \FP\Resv\Frontend\ContentFilter::class,
            \FP\Resv\Frontend\ContentFilter::class
        );
    }
    
    /**
     * Register shortcodes
     */
    private function registerShortcodes(Container $container): void
    {
        // Shortcode renderer
        $container->singleton(
            \FP\Resv\Frontend\ShortcodeRenderer::class,
            function (Container $container) {
                $reservationsService = $container->get(\FP\Resv\Domain\Reservations\Service::class);
                $availability = $container->get(\FP\Resv\Domain\Reservations\Availability::class);
                return new \FP\Resv\Frontend\ShortcodeRenderer($reservationsService, $availability);
            }
        );
        
        // Diagnostic shortcode
        $container->singleton(
            \FP\Resv\Frontend\DiagnosticShortcode::class,
            function (Container $container) {
                $diagnosticsService = $container->get(\FP\Resv\Domain\Diagnostics\Service::class);
                return new \FP\Resv\Frontend\DiagnosticShortcode($diagnosticsService);
            }
        );
        
        // Shortcodes manager
        $container->singleton(
            \FP\Resv\Frontend\Shortcodes::class,
            function (Container $container) {
                $shortcodeRenderer = $container->get(\FP\Resv\Frontend\ShortcodeRenderer::class);
                $diagnosticShortcode = $container->get(\FP\Resv\Frontend\DiagnosticShortcode::class);
                $shortcodes = new \FP\Resv\Frontend\Shortcodes($shortcodeRenderer, $diagnosticShortcode);
                $shortcodes->register();
                return $shortcodes;
            }
        );
        
        $container->alias('frontend.shortcodes', \FP\Resv\Frontend\Shortcodes::class);
        
        // New architecture shortcode
        $container->singleton(
            \FP\Resv\Presentation\Frontend\Shortcodes\ReservationsShortcode::class,
            \FP\Resv\Presentation\Frontend\Shortcodes\ReservationsShortcode::class
        );
    }
    
    /**
     * Register widgets
     */
    private function registerWidgets(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Frontend\WidgetController::class,
            function (Container $container) {
                $assetManager = $container->get(\FP\Resv\Frontend\AssetManager::class);
                $cssManager = $container->get(\FP\Resv\Frontend\CriticalCssManager::class);
                $pageBuilderCompat = $container->get(\FP\Resv\Frontend\PageBuilderCompatibility::class);
                $contentFilter = $container->get(\FP\Resv\Frontend\ContentFilter::class);
                
                $widgets = new \FP\Resv\Frontend\WidgetController(
                    $assetManager,
                    $cssManager,
                    $pageBuilderCompat,
                    $contentFilter
                );
                $widgets->boot();
                return $widgets;
            }
        );
        
        $container->alias('frontend.widgets', \FP\Resv\Frontend\WidgetController::class);
    }
    
    /**
     * Register Events CPT
     */
    private function registerEventsCPT(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Events\CPT::class,
            function (Container $container) {
                $cpt = new \FP\Resv\Domain\Events\CPT();
                $cpt->register();
                return $cpt;
            }
        );
    }
    
    /**
     * Register Manage Controller
     */
    private function registerManageController(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Frontend\ManageController::class,
            function (Container $container) {
                $repository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $service = $container->get(\FP\Resv\Domain\Reservations\Service::class);
                $language = $container->get(\FP\Resv\Domain\Settings\Language::class);
                $options = $container->get(\FP\Resv\Domain\Settings\Options::class);
                $manage = new \FP\Resv\Frontend\ManageController($repository, $service, $language, $options);
                $manage->boot();
                return $manage;
            }
        );
        
        $container->alias('frontend.manage', \FP\Resv\Frontend\ManageController::class);
    }
    
    /**
     * Boot frontend services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Register shortcode hooks for new architecture
        $hooks = $container->get(\FP\Resv\Core\Adapters\HooksAdapterInterface::class);
        
        $hooks->addAction('init', function () use ($container): void {
            // Register new architecture shortcode
            if ($container->has(\FP\Resv\Presentation\Frontend\Shortcodes\ReservationsShortcode::class)) {
                $shortcode = $container->get(\FP\Resv\Presentation\Frontend\Shortcodes\ReservationsShortcode::class);
                add_shortcode('fp_reservations', [$shortcode, 'render']);
            }
            
            // Ensure legacy Shortcodes class registers fp_resv_test and fp_resv_debug
            // This ensures backward compatibility for test and debug shortcodes
            if ($container->has(\FP\Resv\Frontend\Shortcodes::class)) {
                // Shortcodes::register() is already called during instantiation in register()
                // But we ensure it's called here too for safety
                $legacyShortcodes = $container->get(\FP\Resv\Frontend\Shortcodes::class);
                // register() is static, so we call it directly
                \FP\Resv\Frontend\Shortcodes::register();
            }
        });
    }
}
