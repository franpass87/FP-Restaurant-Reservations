<?php
/**
 * Script per forzare il refresh completo della cache
 * Eseguire questo script per vedere le modifiche estetiche
 */

// Carica WordPress
require_once('../../../wp-load.php');

echo "<h2>🔄 Forzatura Cache Refresh Completa</h2>\n";

// 1. Pulisci cache WordPress
echo "<h3>1. Pulizia Cache WordPress</h3>\n";
wp_cache_flush();
echo "✅ Cache WordPress pulita<br>\n";

// 2. Pulisci cache opzioni
echo "<h3>2. Pulizia Cache Opzioni</h3>\n";
delete_option('fp_resv_asset_version');
delete_option('fp_resv_upgrade_timestamp');
echo "✅ Cache opzioni pulita<br>\n";

// 3. Forza aggiornamento timestamp
echo "<h3>3. Aggiornamento Timestamp</h3>\n";
update_option('fp_resv_upgrade_timestamp', time());
echo "✅ Timestamp aggiornato: " . time() . "<br>\n";

// 4. Pulisci OPcache se disponibile
echo "<h3>4. Pulizia OPcache</h3>\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache pulito<br>\n";
} else {
    echo "⚠️ OPcache non disponibile<br>\n";
}

// 5. Flush rewrite rules
echo "<h3>5. Flush Rewrite Rules</h3>\n";
flush_rewrite_rules();
echo "✅ Rewrite rules aggiornate<br>\n";

// 6. Verifica file modificati
echo "<h3>6. Verifica File Modificati</h3>\n";
$formFile = __DIR__ . '/templates/frontend/form-simple.php';
if (file_exists($formFile)) {
    $mtime = filemtime($formFile);
    echo "✅ Form file modificato: " . date('Y-m-d H:i:s', $mtime) . "<br>\n";
} else {
    echo "❌ Form file non trovato<br>\n";
}

// 7. Verifica asset version
echo "<h3>7. Verifica Asset Version</h3>\n";
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo "✅ WP_DEBUG attivo - versioning dinamico<br>\n";
} else {
    echo "⚠️ WP_DEBUG non attivo - usa versioning statico<br>\n";
}

// 8. Test URL
echo "<h3>8. Test URL</h3>\n";
$siteUrl = get_site_url();
echo "🌐 Sito: <a href='{$siteUrl}' target='_blank'>{$siteUrl}</a><br>\n";

echo "<h3>✅ Cache Refresh Completato!</h3>\n";
echo "<p><strong>Ora:</strong></p>\n";
echo "<ol>\n";
echo "<li>Vai al tuo sito</li>\n";
echo "<li>Fai <strong>Ctrl+F5</strong> (hard refresh)</li>\n";
echo "<li>Oppure <strong>Ctrl+Shift+R</strong></li>\n";
echo "<li>Le modifiche estetiche dovrebbero essere visibili</li>\n";
echo "</ol>\n";

echo "<h3>🔍 Se ancora non funziona:</h3>\n";
echo "<ol>\n";
echo "<li>Controlla che WP_DEBUG sia attivo in wp-config.php</li>\n";
echo "<li>Prova in modalità incognito</li>\n";
echo "<li>Pulisci cache del browser</li>\n";
echo "<li>Verifica che il form usi form-simple.php</li>\n";
echo "</ol>\n";
?>
