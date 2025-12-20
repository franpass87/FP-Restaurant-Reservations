<?php
/**
 * Plugin Name: FP Restaurant Reservations
 * Plugin URI: https://francescopasseri.com/projects/fp-restaurant-reservations
 * Description: Prenotazioni ristorante con eventi, calendario drag&drop, Brevo + Google Calendar, tracking GA4/Ads/Meta/Clarity e stile personalizzabile.
 * Version: 0.9.0-rc10.3
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-restaurant-reservations
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$minPhp = '8.1';
if (version_compare(PHP_VERSION, $minPhp, '<')) {
    $message = sprintf(
        /* translators: 1: Minimum supported PHP version, 2: Detected PHP version. */
        'FP Restaurant Reservations richiede PHP %1$s o superiore. Questo sito esegue PHP %2$s.',
        $minPhp,
        PHP_VERSION
    );

    if (function_exists('add_action')) {
        add_action('admin_notices', function () use ($message) {
            if (!function_exists('esc_html')) {
                echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
                return;
            }

            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        });
    }

    if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
        deactivate_plugins(plugin_basename(__FILE__));
    }

    if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
        \WP_CLI::warning($message);
    }

    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[FP Restaurant Reservations] ' . $message);
    }

    return;
}

// Load autoloader
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require $autoload;
}

// Inizializza sistema di auto-aggiornamento da GitHub
if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/franpass87/FP-Restaurant-Reservations/',
        __FILE__,
        'fp-restaurant-reservations'
    );
    
    // Usa le GitHub Releases per gli aggiornamenti
    $updateChecker->getVcsApi()->enableReleaseAssets();
}

// Bootstrap plugin using new architecture
// Keep BootstrapGuard for error handling during transition
require_once __DIR__ . '/src/Core/BootstrapGuard.php';

$pluginFile = __FILE__;

FP\Resv\Core\BootstrapGuard::run($pluginFile, static function () use ($pluginFile): void {
    // Use new Bootstrap architecture
    require_once __DIR__ . '/src/Kernel/Bootstrap.php';
    
    $boot = static function () use ($pluginFile): void {
        FP\Resv\Kernel\Bootstrap::boot($pluginFile);
    };

    // Call on plugins_loaded instead of wp_loaded to ensure compatibility with legacy system
    // This ensures ServiceRegistry and AdminPages are registered at the right time
    if (\did_action('plugins_loaded')) {
        $boot();
    } else {
        \add_action('plugins_loaded', $boot, 20); // Priority 20 to run after most plugins
    }
});

// Register activation/deactivation hooks
register_activation_hook(__FILE__, static function () use ($pluginFile): void {
    require_once __DIR__ . '/src/Kernel/Lifecycle.php';
    FP\Resv\Kernel\Lifecycle::activate($pluginFile);
});

register_deactivation_hook(__FILE__, static function (): void {
    require_once __DIR__ . '/src/Kernel/Lifecycle.php';
    FP\Resv\Kernel\Lifecycle::deactivate();
});
