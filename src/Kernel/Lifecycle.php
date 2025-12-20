<?php

declare(strict_types=1);

namespace FP\Resv\Kernel;

/**
 * Plugin Lifecycle Manager
 * 
 * Handles activation, deactivation, and upgrade routines.
 *
 * @package FP\Resv\Kernel
 */
final class Lifecycle
{
    /**
     * Handle plugin activation
     * 
     * @param string $pluginFile Path to main plugin file
     * @return void
     */
    public static function activate(string $pluginFile): void
    {
        // Load requirements
        require_once dirname($pluginFile) . '/src/Core/Requirements.php';
        
        // Validate requirements
        if (!\FP\Resv\Core\Requirements::validate()) {
            deactivate_plugins(plugin_basename($pluginFile));
            wp_die(
                __('FP Restaurant Reservations non può essere attivato perché l\'ambiente non soddisfa i requisiti minimi.', 'fp-restaurant-reservations'),
                __('Errore di attivazione', 'fp-restaurant-reservations'),
                ['back_link' => true]
            );
        }
        
        // Install Composer dependencies if missing (during activation, context is cleaner)
        $autoload = dirname($pluginFile) . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            // Load the install function
            if (function_exists('fp_resv_install_composer_dependencies')) {
                $installSuccess = fp_resv_install_composer_dependencies(dirname($pluginFile));
                if (!$installSuccess) {
                    // If installation fails, try to continue anyway - user can install manually
                    // Don't block activation, just log it
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[FP Restaurant Reservations] Installazione dipendenze Composer fallita durante attivazione');
                    }
                }
            }
        }
        
        // Run database migrations
        self::runMigrations();
        
        // Set activation flag
        update_option('fp_resv_activated', time());
        
        // Clear any caches
        wp_cache_flush();
    }
    
    /**
     * Handle plugin deactivation
     * 
     * @return void
     */
    public static function deactivate(): void
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('fp_resv_cleanup');
        
        // Clear caches
        wp_cache_flush();
    }
    
    /**
     * Handle plugin upgrade
     * 
     * @param string $oldVersion Previous version
     * @param string $newVersion New version
     * @return void
     */
    public static function upgrade(string $oldVersion, string $newVersion): void
    {
        // Run database migrations
        self::runMigrations($oldVersion, $newVersion);
        
        // Clear caches
        wp_cache_flush();
    }
    
    /**
     * Run database migrations
     * 
     * @param string|null $fromVersion Version to migrate from
     * @param string|null $toVersion Version to migrate to
     * @return void
     */
    private static function runMigrations(?string $fromVersion = null, ?string $toVersion = null): void
    {
        // Load migrations if they exist
        // Note: We can't use Bootstrap::pluginFile() here as Bootstrap may not be initialized
        // during activation, so we'll load migrations through the existing Core\Migrations class
        $migrationsFile = __DIR__ . '/../Core/Migrations.php';
        if (file_exists($migrationsFile)) {
            require_once $migrationsFile;
            // Run migrations
            \FP\Resv\Core\Migrations::run();
        }
    }
}

