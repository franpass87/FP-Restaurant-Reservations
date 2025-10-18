<?php
/**
 * Temporary script to force refresh CSS/JS assets cache
 * 
 * Usage: 
 * 1. Upload this file to the WordPress root directory
 * 2. Visit: https://your-site.com/force-refresh-assets.php
 * 3. Delete this file after use for security
 * 
 * Or use WP-CLI: wp eval-file force-refresh-assets.php
 */

// Load WordPress
require_once __DIR__ . '/wp-load.php';

// Check if user is admin (for security)
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('❌ Non autorizzato. Devi essere amministratore.');
}

echo "<h1>🔄 Force Refresh Assets Cache</h1>";

// Update the timestamp to force cache invalidation
$timestamp = time();
$updated = update_option('fp_resv_last_upgrade', $timestamp, false);

if ($updated) {
    echo "<p style='color: green; font-weight: bold;'>✅ Timestamp aggiornato con successo!</p>";
    echo "<p>Nuovo timestamp: <code>{$timestamp}</code></p>";
} else {
    echo "<p style='color: orange;'>⚠️ Timestamp già aggiornato o nessun cambiamento necessario.</p>";
}

// Clear WordPress cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "<p>✅ Cache WordPress pulita</p>";
}

echo "<hr>";
echo "<h2>✅ Operazione completata!</h2>";
echo "<p>Ora:</p>";
echo "<ol>";
echo "<li>Vai alla pagina con il form di prenotazione</li>";
echo "<li>Fai un <strong>Hard Refresh</strong> del browser:";
echo "<ul>";
echo "<li>Windows: <code>Ctrl + F5</code> o <code>Ctrl + Shift + R</code></li>";
echo "<li>Mac: <code>Cmd + Shift + R</code></li>";
echo "</ul>";
echo "</li>";
echo "<li>Verifica che i cambiamenti grafici siano visibili</li>";
echo "<li><strong>ELIMINA QUESTO FILE</strong> dopo l'uso per motivi di sicurezza</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>Script eseguito il: " . date('Y-m-d H:i:s') . "</em></p>";
