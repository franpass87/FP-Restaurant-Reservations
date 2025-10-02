<?php
/**
 * Plugin Name: FP Restaurant Reservations
 * Plugin URI: https://francescopasseri.com/projects/fp-restaurant-reservations
 * Description: Prenotazioni ristorante con eventi, calendario drag&drop, Brevo + Google Calendar, tracking GA4/Ads/Meta/Clarity e stile personalizzabile.
 * Version: 0.1.1
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

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require $autoload;
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
