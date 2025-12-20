<?php

declare(strict_types=1);

namespace FP\Resv\Kernel;

/**
 * Main Plugin Class
 * 
 * Coordinates plugin initialization and provides access to core functionality.
 * This class will be instantiated by the Bootstrap after all providers are registered.
 *
 * @package FP\Resv\Kernel
 */
final class Plugin
{
    /**
     * Current plugin semantic version.
     * Keep this in sync with the plugin header in fp-restaurant-reservations.php.
     */
    public const VERSION = '0.9.0-rc10.3';
    
    /** @var Container Service container */
    private Container $container;
    
    /** @var string Plugin file path */
    private string $pluginFile;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     * @param string $pluginFile Plugin file path
     */
    public function __construct(Container $container, string $pluginFile)
    {
        $this->container = $container;
        $this->pluginFile = $pluginFile;
    }
    
    /**
     * Boot the plugin
     * 
     * This method is called after all service providers have been registered.
     * Use it to initialize plugin-wide functionality.
     * 
     * @return void
     */
    public function boot(): void
    {
        // Set plugin file and directory constants for backward compatibility
        if (!defined('FP_RESV_FILE')) {
            define('FP_RESV_FILE', $this->pluginFile);
        }
        
        if (!defined('FP_RESV_DIR')) {
            define('FP_RESV_DIR', dirname($this->pluginFile));
        }
        
        // Initialize plugin
        $this->initialize();
    }
    
    /**
     * Initialize plugin functionality
     * 
     * @return void
     */
    private function initialize(): void
    {
        // Plugin initialization will happen through service providers
        // This method is here for any plugin-wide initialization that doesn't
        // fit into a specific provider
        
        // Check for version upgrade
        $this->checkUpgrade();
    }
    
    /**
     * Check if plugin needs to be upgraded
     * 
     * @return void
     */
    private function checkUpgrade(): void
    {
        $storedVersion = get_option('fp_resv_version', '0.0.0');
        
        if (version_compare($storedVersion, self::VERSION, '<')) {
            // Run upgrade
            Lifecycle::upgrade($storedVersion, self::VERSION);
            
            // Update stored version
            update_option('fp_resv_version', self::VERSION);
        }
    }
    
    /**
     * Get the service container
     * 
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }
    
    /**
     * Get plugin file path
     * 
     * @return string
     */
    public function pluginFile(): string
    {
        return $this->pluginFile;
    }
    
    /**
     * Get plugin directory path
     * 
     * @return string
     */
    public function pluginDir(): string
    {
        return dirname($this->pluginFile);
    }
}










