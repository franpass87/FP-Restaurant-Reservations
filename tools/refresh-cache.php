#!/usr/bin/env php
<?php
/**
 * Cache refresh helper
 * 
 * This script can be used to refresh the plugin cache after deployment.
 * 
 * Usage:
 *   1. Via WP-CLI: wp eval-file tools/refresh-cache.php
 *   2. Via HTTP: Add ?fp_resv_refresh_cache=1 to any admin page URL (requires admin login)
 *   3. Via REST API: POST to /wp-json/fp-resv/v1/diagnostics/refresh-cache
 */

// If running via WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    if (class_exists('FP\Resv\Core\Plugin')) {
        \FP\Resv\Core\Plugin::forceRefreshAssets();
        WP_CLI::success('Cache refreshed successfully!');
    } else {
        WP_CLI::error('Plugin not loaded.');
    }
    return;
}

// If running as standalone PHP script, provide instructions
if (php_sapi_name() === 'cli' && !defined('ABSPATH')) {
    fwrite(STDOUT, "⚠️  This script requires WordPress to be loaded.\n\n");
    fwrite(STDOUT, "Usage options:\n");
    fwrite(STDOUT, "  1. WP-CLI:     wp eval-file tools/refresh-cache.php\n");
    fwrite(STDOUT, "  2. Admin URL:  Add ?fp_resv_refresh_cache=1 to any admin page\n");
    fwrite(STDOUT, "  3. REST API:   POST to /wp-json/fp-resv/v1/diagnostics/refresh-cache\n");
    exit(0);
}

// Hook for admin page refresh
if (defined('ABSPATH') && is_admin() && current_user_can('manage_options')) {
    add_action('admin_init', function() {
        if (isset($_GET['fp_resv_refresh_cache']) && $_GET['fp_resv_refresh_cache'] === '1') {
            if (class_exists('FP\Resv\Core\Plugin')) {
                \FP\Resv\Core\Plugin::forceRefreshAssets();
                wp_safe_redirect(admin_url('admin.php?page=fp-resv-settings&cache_refreshed=1'));
                exit;
            }
        }
    });
    
    add_action('admin_notices', function() {
        if (isset($_GET['cache_refreshed']) && $_GET['cache_refreshed'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Cache plugin aggiornata con successo!', 'fp-restaurant-reservations');
            echo '</p></div>';
        }
    });
}
