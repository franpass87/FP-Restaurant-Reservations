<?php
/**
 * Force Refresh Assets - Script per forzare il refresh della cache degli asset
 * 
 * Questo script forza WordPress a ricaricare tutti i file JS/CSS aggiornando
 * il timestamp usato per il cache busting.
 * 
 * UTILIZZO:
 * 1. Carica questo file nella root del plugin
 * 2. Naviga su: https://tuosito.it/wp-admin/admin.php?page=fp-resv-agenda&force_refresh_assets=1
 * 3. Oppure via WP-CLI: wp eval-file force-refresh-assets.php
 * 
 * Lo script si auto-eliminerà dopo l'esecuzione per sicurezza.
 */

// Sicurezza: verifica che siamo in ambiente WordPress
if (!defined('ABSPATH')) {
    // Se chiamato direttamente, carica WordPress
    require_once __DIR__ . '/../../../wp-load.php';
}

// Verifica permessi admin
if (!current_user_can('manage_options')) {
    wp_die('Permessi insufficienti. Devi essere amministratore.');
}

// Force refresh assets
$timestamp = time();
update_option('fp_resv_last_upgrade', $timestamp, false);

// Clear all WordPress caches
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Clear transients
global $wpdb;
if (isset($wpdb) && isset($wpdb->options)) {
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_') . '%fp_resv%',
            $wpdb->esc_like('_transient_timeout_') . '%fp_resv%'
        )
    );
}

// Log dell'operazione
if (class_exists('FP\\Resv\\Core\\Logging')) {
    \FP\Resv\Core\Logging::log('plugin', 'Asset cache manually refreshed via script', [
        'timestamp' => $timestamp,
        'user' => wp_get_current_user()->user_login ?? 'unknown',
        'transients_deleted' => $deleted ?? 0,
    ]);
}

// Output risultato
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Cache Refresh</title>";
echo "<style>body{font-family:system-ui;max-width:600px;margin:50px auto;padding:20px;}</style></head><body>";
echo "<h1>✅ Cache Asset Aggiornata con Successo!</h1>";
echo "<p><strong>Nuovo timestamp:</strong> " . $timestamp . "</p>";
echo "<p><strong>Nuova versione asset:</strong> 0.1.10." . $timestamp . "</p>";
echo "<p><strong>Transient eliminati:</strong> " . ($deleted ?? 0) . "</p>";
echo "<hr>";
echo "<h2>Cosa fare ora:</h2>";
echo "<ol>";
echo "<li>Premi <strong>Ctrl+Shift+R</strong> (o Cmd+Shift+R su Mac) per fare hard refresh del browser</li>";
echo "<li>Verifica che il file JS caricato abbia la nuova versione nella console del browser</li>";
echo "<li>Il file JS dovrebbe avere parametro: <code>?ver=0.1.10." . $timestamp . "</code></li>";
echo "</ol>";
echo "<p><a href='" . admin_url('admin.php?page=fp-resv-agenda') . "' style='display:inline-block;background:#2271b1;color:white;padding:10px 20px;text-decoration:none;border-radius:3px;'>Vai all'Agenda</a></p>";
echo "</body></html>";

// Auto-elimina questo file per sicurezza (opzionale - commentato per default)
// unlink(__FILE__);

exit;
