<?php
/**
 * Force Debug Shortcode - Attiva il debug temporaneo
 * 
 * Carica questo file per forzare WP_DEBUG e vedere gli errori
 * RIMUOVILO DOPO IL DEBUG!
 */

// Define WP_DEBUG if not already defined
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Bootstrap WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Force clear all caches
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Set up page context
global $wp_query;
$wp_query->is_singular = true;
$wp_query->is_page = true;

// Execute shortcode
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Shortcode</title></head><body>";
echo "<h1>Test Form Shortcode - Debug Mode</h1>";
echo "<hr>";

// Show if shortcode is registered
global $shortcode_tags;
if (isset($shortcode_tags['fp_reservations'])) {
    echo "<p style='color:green;'>✓ Shortcode 'fp_reservations' è registrato</p>";
} else {
    echo "<p style='color:red;'>✗ Shortcode 'fp_reservations' NON è registrato</p>";
    echo "<p>Shortcode disponibili: " . implode(', ', array_keys($shortcode_tags)) . "</p>";
}

echo "<hr>";
echo "<h2>Output dello shortcode:</h2>";

// Trigger wp_enqueue_scripts
do_action('wp_enqueue_scripts');

// Execute shortcode
$output = do_shortcode('[fp_reservations]');
echo $output;

echo "<hr>";
echo "<p><em>Se non vedi il form sopra, controlla i messaggi di errore in rosso.</em></p>";
echo "</body></html>";

