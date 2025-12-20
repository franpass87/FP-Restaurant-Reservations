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
        try {
            // Load requirements
            $requirementsPath = dirname($pluginFile) . '/src/Core/Requirements.php';
            if (!file_exists($requirementsPath) || !is_readable($requirementsPath)) {
                // Salva errore invece di wp_die
                $errors = get_option('fp_resv_activation_errors', []);
                if (!is_array($errors)) {
                    $errors = [];
                }
                $errors[] = 'File Requirements.php non trovato in: ' . $requirementsPath;
                update_option('fp_resv_activation_errors', $errors);
                return;
            }
            
            require_once $requirementsPath;
            
            // Validate requirements only if class exists
            if (!class_exists('FP\Resv\Core\Requirements')) {
                // Salva errore invece di wp_die
                $errors = get_option('fp_resv_activation_errors', []);
                if (!is_array($errors)) {
                    $errors = [];
                }
                $errors[] = 'Classe Requirements non trovata dopo il caricamento del file.';
                update_option('fp_resv_activation_errors', $errors);
                return;
            }
            
            // Validate requirements
            if (!\FP\Resv\Core\Requirements::validate()) {
                // Salva errore invece di wp_die e deactivate_plugins
                $errors = get_option('fp_resv_activation_errors', []);
                if (!is_array($errors)) {
                    $errors = [];
                }
                $errors[] = 'L\'ambiente non soddisfa i requisiti minimi del plugin.';
                update_option('fp_resv_activation_errors', $errors);
                return;
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
            if (function_exists('update_option')) {
                update_option('fp_resv_activated', time());
            }
            
            // Clear any caches
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        } catch (Throwable $e) {
            // Salva errore invece di wp_die
            $errors = get_option('fp_resv_activation_errors', []);
            if (!is_array($errors)) {
                $errors = [];
            }
            $errors[] = 'Errore durante attivazione Lifecycle: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Linea: ' . $e->getLine() . ')';
            update_option('fp_resv_activation_errors', $errors);
        }
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
        if (file_exists($migrationsFile) && is_readable($migrationsFile)) {
            try {
                require_once $migrationsFile;
                // Run migrations only if class exists
                if (class_exists('FP\Resv\Core\Migrations')) {
                    \FP\Resv\Core\Migrations::run();
                }
            } catch (Throwable $e) {
                // Ignora errori durante le migrazioni durante l'attivazione
                if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                    error_log('[FP Restaurant Reservations] Errore durante le migrazioni: ' . $e->getMessage());
                }
            }
        }
    }
}

