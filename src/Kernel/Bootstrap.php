<?php

declare(strict_types=1);

namespace FP\Resv\Kernel;

use FP\Resv\Providers\CoreServiceProvider;
use FP\Resv\Providers\ServiceProvider;

/**
 * Plugin Bootstrap
 * 
 * Handles plugin initialization, container setup, and service provider registration.
 *
 * @package FP\Resv\Kernel
 */
final class Bootstrap
{
    /** @var Container|null Container instance */
    private static ?Container $container = null;
    
    /** @var string|null Plugin file path */
    private static ?string $pluginFile = null;
    
    /**
     * Boot the plugin
     * 
     * @param string $pluginFile Path to main plugin file
     * @return void
     */
    public static function boot(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;
        
        // 1. Check requirements - ensure class is loaded
        if (!class_exists('\FP\Resv\Core\Requirements')) {
            // Try to load the class explicitly if autoloader hasn't loaded it yet
            $pluginDir = dirname($pluginFile);
            $requirementsPath = $pluginDir . '/src/Core/Requirements.php';
            
            // Diagnostica avanzata per debug in produzione
            $diagnostics = [];
            $diagnostics['plugin_dir'] = $pluginDir;
            $diagnostics['requirements_path'] = $requirementsPath;
            $diagnostics['file_exists'] = file_exists($requirementsPath) ? 'yes' : 'no';
            $diagnostics['is_readable'] = is_readable($requirementsPath) ? 'yes' : 'no';
            
            if (file_exists($requirementsPath)) {
                $diagnostics['file_size'] = filesize($requirementsPath);
                
                if (is_readable($requirementsPath)) {
                    // Usa include con output buffering per catturare errori
                    ob_start();
                    $includeResult = @include_once $requirementsPath;
                    $includeOutput = ob_get_clean();
                    
                    $diagnostics['include_result'] = $includeResult ? 'success' : 'failed';
                    if ($includeOutput) {
                        $diagnostics['include_output'] = substr($includeOutput, 0, 500);
                    }
                    
                    // Verifica di nuovo se la classe esiste ora
                    $diagnostics['class_exists_after_include'] = class_exists('\FP\Resv\Core\Requirements') ? 'yes' : 'no';
                }
            }
            
            // Se ancora non trovata, lancia eccezione con diagnostica completa
            if (!class_exists('\FP\Resv\Core\Requirements')) {
                $errorMsg = 'Class "FP\\Resv\\Core\\Requirements" not found. ';
                $errorMsg .= 'Diagnostics: ' . json_encode($diagnostics, JSON_UNESCAPED_SLASHES);
                throw new \RuntimeException($errorMsg);
            }
        }
        
        if (!\FP\Resv\Core\Requirements::validate()) {
            return;
        }
        
        // 2. Verify PSR Container interface exists before creating container
        if (!interface_exists('Psr\Container\ContainerInterface')) {
            throw new \RuntimeException('PSR Container interface not found. Run "composer install" to install dependencies.');
        }
        
        // 3. Create container
        self::$container = new Container();
        
        // 3. Register core services first
        $coreProvider = new CoreServiceProvider();
        $coreProvider->register(self::$container);
        
        // 4. Boot core provider (which will register and boot feature providers)
        $coreProvider->boot(self::$container);
        
        // 5. Initialize legacy bridge for backward compatibility
        LegacyBridge::init(self::$container);
        
        // 6. Set legacy Plugin static properties for backward compatibility
        if (class_exists('\FP\Resv\Core\Plugin')) {
            \FP\Resv\Core\Plugin::$file = $pluginFile;
            \FP\Resv\Core\Plugin::$dir = plugin_dir_path($pluginFile);
            \FP\Resv\Core\Plugin::$url = plugin_dir_url($pluginFile);
        }
        
        // 7. Initialize plugin-level services
        self::initializePluginServices($pluginFile);
        
        // 8. Initialize plugin
        $plugin = new Plugin(self::$container, $pluginFile);
        $plugin->boot();
        
        // 9. Ensure REST routes are registered early for REST API requests
        // This is needed because RESTServiceProvider might not be loaded early enough
        // Check for REST requests by URI pattern as REST_REQUEST might not be defined yet
        $isRestRequest = (defined('REST_REQUEST') && REST_REQUEST) 
            || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false);
            
        if ($isRestRequest) {
            add_action('rest_api_init', function() {
                // Force instantiation of AdminREST to register routes
                if (self::$container->has(\FP\Resv\Domain\Reservations\AdminREST::class)) {
                    try {
                        self::$container->get(\FP\Resv\Domain\Reservations\AdminREST::class);
                    } catch (\Throwable $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[FP Resv Bootstrap] ❌ Errore istanziazione AdminREST: ' . $e->getMessage());
                        }
                    }
                }
            }, 1); // Priority 1 to register very early
        }
    }
    
    /**
     * Initialize plugin-level services that don't belong to a specific provider
     */
    private static function initializePluginServices(string $pluginFile): void
    {
        // Auto cache buster
        if (class_exists('\FP\Resv\Core\AutoCacheBuster')) {
            \FP\Resv\Core\AutoCacheBuster::init();
        }
        
        // Ensure admin capabilities
        if (class_exists('\FP\Resv\Core\Roles')) {
            \FP\Resv\Core\Roles::ensureAdminCapabilities();
        }
        
        // Run migrations
        if (class_exists('\FP\Resv\Core\Migrations')) {
            \FP\Resv\Core\Migrations::run();
        }
        
        // Initialize i18n
        if (class_exists('\FP\Resv\Core\I18n')) {
            \FP\Resv\Core\I18n::init();
        }
        
        // Protect REST API endpoints from redirects
        add_filter('redirect_canonical', static function ($redirect_url, $requested_url) {
            if (strpos($requested_url, '/wp-json/fp-resv/') !== false) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);
        
        add_filter('wp_redirect', static function ($location, $status) {
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return false;
            }
            return $location;
        }, 1, 2);
        
        // Initialize scheduler
        if (class_exists('\FP\Resv\Core\Scheduler')) {
            \FP\Resv\Core\Scheduler::init();
        }
        
        // Initialize legacy REST (if still needed)
        if (class_exists('\FP\Resv\Core\REST')) {
            \FP\Resv\Core\REST::init();
        }
    }
    
    /**
     * Get the container instance
     * 
     * @return Container
     * @throws \RuntimeException If container not initialized
     */
    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException('Container not initialized. Call Bootstrap::boot() first.');
        }
        
        return self::$container;
    }
    
    /**
     * Get the plugin file path
     * 
     * @return string
     */
    public static function pluginFile(): string
    {
        if (self::$pluginFile === null) {
            throw new \RuntimeException('Plugin file not set.');
        }
        
        return self::$pluginFile;
    }
    
    /**
     * Register a service provider
     * 
     * @param ServiceProvider $provider Service provider instance
     * @return void
     */
    private static function registerProvider(ServiceProvider $provider): void
    {
        if (self::$container === null) {
            throw new \RuntimeException('Container not initialized.');
        }
        
        $provider->register(self::$container);
    }
    
    /**
     * Boot all registered providers
     * 
     * @return void
     */
    private static function bootProviders(): void
    {
        // Providers are booted by CoreServiceProvider
        // This method is here for future extensibility
    }
}

