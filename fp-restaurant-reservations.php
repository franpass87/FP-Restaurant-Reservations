<?php
/**
 * Plugin Name: FP Restaurant Reservations
 * Plugin URI: https://francescopasseri.com/projects/fp-restaurant-reservations
 * Description: Prenotazioni ristorante con eventi, calendario drag&drop, Brevo + Google Calendar, tracking GA4/Ads/Meta/Clarity e stile personalizzabile.
 * Version: 0.1.10
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

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require $autoload;
}

// Sistema di aggiornamento MANUALE da GitHub
// L'aggiornamento automatico è disabilitato per evitare problemi sui siti dei clienti
// Gli aggiornamenti devono essere attivati manualmente dalla pagina delle impostazioni
if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/francescopasseri/fp-restaurant-reservations/',
        __FILE__,
        'fp-restaurant-reservations'
    );
    
    // Usa le GitHub Releases per gli aggiornamenti
    $updateChecker->getVcsApi()->enableReleaseAssets();
    
    // IMPORTANTE: Disabilita il check automatico degli aggiornamenti
    // Gli aggiornamenti verranno controllati solo quando l'utente clicca "Aggiorna ora"
    add_filter('puc_check_now-fp-restaurant-reservations', '__return_false', 999);
    
    // Salva l'istanza dell'update checker globalmente per uso nella pagina admin
    global $fp_resv_update_checker;
    $fp_resv_update_checker = $updateChecker;
}

require_once __DIR__ . '/src/Core/Requirements.php';

if (!FP\Resv\Core\Requirements::validate()) {
    return;
}

require_once __DIR__ . '/src/Core/BootstrapGuard.php';

FP\Resv\Core\BootstrapGuard::run(__FILE__, static function (): void {
    require_once __DIR__ . '/src/Core/Plugin.php';

    FP\Resv\Core\Plugin::boot(__FILE__);
});
