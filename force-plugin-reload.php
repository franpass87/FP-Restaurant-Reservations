<?php
/**
 * Forza il ricaricamento del plugin
 */

// Aggiungi un timestamp per forzare il refresh
$timestamp = time();
echo "<!-- Plugin reload forced at: " . date('Y-m-d H:i:s', $timestamp) . " -->\n";

// Forza la pulizia della cache di WordPress
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Forza la pulizia della cache degli oggetti
if (function_exists('wp_cache_delete')) {
    wp_cache_delete('fp_resv_plugin_version', 'options');
}

// Aggiungi un header per forzare il refresh del browser
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "Plugin reloaded successfully at " . date('Y-m-d H:i:s') . "\n";
