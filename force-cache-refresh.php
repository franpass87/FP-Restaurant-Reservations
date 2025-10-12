<?php
/**
 * Script per forzare il refresh della cache dello stile
 * 
 * Esegui questo script navigando su:
 * http://tuosito.local/wp-content/plugins/fp-restaurant-reservations/force-cache-refresh.php
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Verifica che siamo in un ambiente WordPress
if (!function_exists('get_option')) {
    die('Errore: WordPress non caricato correttamente.');
}

// Verifica permessi admin
if (!current_user_can('manage_options')) {
    die('Errore: Devi essere amministratore per eseguire questo script.');
}

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>Force Cache Refresh - FP Reservations</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; background: #f0f0f1; }
.box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
h1 { margin-top: 0; color: #1e1e1e; }
.success { color: #00a32a; font-weight: 600; }
.info { background: #f0f6fc; padding: 15px; border-left: 4px solid #0071a1; margin: 15px 0; }
code { background: #f6f7f7; padding: 2px 6px; border-radius: 3px; font-family: Consolas, Monaco, monospace; }
.button { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 0 0; }
.button:hover { background: #135e96; }
</style>
</head>';
echo '<body>';
echo '<div class="box">';
echo '<h1>🔄 Force Cache Refresh</h1>';

// 1. Svuota la cache delle opzioni
wp_cache_delete('alloptions', 'options');
wp_cache_delete('fp_resv_style', 'options');
echo '<p class="success">✓ Cache delle opzioni svuotata</p>';

// 2. Leggi le impostazioni correnti
$current_settings = get_option('fp_resv_style', []);
echo '<h2>Impostazioni attuali:</h2>';
echo '<div class="info">';
echo '<code>style_button_bg</code>: ' . esc_html($current_settings['style_button_bg'] ?? 'NON PRESENTE') . '<br>';
echo '<code>style_button_text</code>: ' . esc_html($current_settings['style_button_text'] ?? 'NON PRESENTE') . '<br>';
echo '</div>';

// 3. Se le impostazioni non sono presenti, aggiungiamole
$updated = false;
if (!isset($current_settings['style_button_bg'])) {
    $current_settings['style_button_bg'] = '#000000';
    $updated = true;
}
if (!isset($current_settings['style_button_text'])) {
    $current_settings['style_button_text'] = '#ffffff';
    $updated = true;
}

if ($updated) {
    update_option('fp_resv_style', $current_settings);
    echo '<p class="success">✓ Impostazioni bottone aggiunte al database</p>';
} else {
    echo '<p class="success">✓ Impostazioni bottone già presenti nel database</p>';
}

// 4. Svuota opcode cache se disponibile
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '<p class="success">✓ OPcache PHP svuotata</p>';
}

// 5. Flush rewrite rules (per sicurezza)
flush_rewrite_rules();
echo '<p class="success">✓ Rewrite rules ricaricate</p>';

echo '<h2>✅ Cache aggiornata con successo!</h2>';
echo '<div class="info">';
echo '<strong>Prossimi passi:</strong><br>';
echo '1. Vai alla pagina <strong>Impostazioni → Stile</strong><br>';
echo '2. Troverai i nuovi campi <code>Sfondo bottone "Continua"</code> e <code>Testo bottone "Continua"</code><br>';
echo '3. Modifica i colori come preferisci<br>';
echo '4. Salva le impostazioni<br>';
echo '5. Ricarica la pagina frontend per vedere le modifiche';
echo '</div>';

echo '<p><a href="' . admin_url('admin.php?page=fp-resv-style') . '" class="button">Vai alle Impostazioni Stile</a>';
echo '<a href="' . admin_url('admin.php?page=fp-resv-settings') . '" class="button">Vai alle Impostazioni</a></p>';

echo '</div>';
echo '</body>';
echo '</html>';
