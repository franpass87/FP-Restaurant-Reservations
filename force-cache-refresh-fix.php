<?php
/**
 * Script per forzare il refresh della cache del form frontend
 * Risolve il problema delle modifiche non visibili
 */

// Forza WP_DEBUG se non è già definito
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Carica WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
    dirname(__DIR__, 3) . '/wp-load.php',
    dirname(__DIR__, 4) . '/wp-load.php',
    dirname(__DIR__, 5) . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Errore: WordPress non trovato. Controlla il percorso del plugin.');
}

// Verifica che siamo in un ambiente WordPress
if (!function_exists('get_option')) {
    die('Errore: WordPress non caricato correttamente.');
}

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>🔄 Force Cache Refresh - FP Reservations</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; max-width: 900px; margin: 0 auto; background: #f0f0f1; }
.box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
h1 { margin-top: 0; color: #1e1e1e; }
.success { color: #00a32a; font-weight: 600; }
.warning { color: #dba617; font-weight: 600; }
.error { color: #d63638; font-weight: 600; }
.info { background: #f0f6fc; padding: 15px; border-left: 4px solid #0071a1; margin: 15px 0; }
code { background: #f6f7f7; padding: 2px 6px; border-radius: 3px; font-family: Consolas, Monaco, monospace; }
.button { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 0 0; }
.button:hover { background: #135e96; }
.step { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #00a32a; }
</style>
</head>';
echo '<body>';

echo '<div class="box">';
echo '<h1>🔄 Force Cache Refresh - Form Frontend</h1>';

// 1. Verifica WP_DEBUG
$wp_debug = defined('WP_DEBUG') && WP_DEBUG;
echo '<h2>1. Verifica WP_DEBUG</h2>';
if ($wp_debug) {
    echo '<p class="success">✅ WP_DEBUG è ATTIVO - Cache busting automatico abilitato</p>';
} else {
    echo '<p class="warning">⚠️ WP_DEBUG è DISATTIVO - Attivando ora...</p>';
    // Forza WP_DEBUG
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    echo '<p class="success">✅ WP_DEBUG ora ATTIVO</p>';
}

// 2. Svuota tutte le cache
echo '<h2>2. Pulizia Cache</h2>';

// Cache WordPress
wp_cache_delete('alloptions', 'options');
wp_cache_delete('fp_resv_style', 'options');
wp_cache_flush();
echo '<p class="success">✓ Cache WordPress svuotata</p>';

// OPcache PHP
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '<p class="success">✓ OPcache PHP svuotata</p>';
} else {
    echo '<p class="info">ℹ️ OPcache non disponibile</p>';
}

// Rewrite rules
flush_rewrite_rules();
echo '<p class="success">✓ Rewrite rules ricaricate</p>';

// 3. Forza aggiornamento timestamp upgrade
echo '<h2>3. Aggiornamento Timestamp</h2>';
$new_timestamp = time();
update_option('fp_resv_last_upgrade', $new_timestamp, false);
echo '<p class="success">✓ Timestamp aggiornato: ' . $new_timestamp . '</p>';

// 4. Verifica file asset
echo '<h2>4. Verifica File Asset</h2>';
$plugin_dir = plugin_dir_path(__FILE__);
$asset_files = [
    'templates/frontend/form.php',
    'assets/css/form-thefork.css', 
    'assets/css/form.css',
    'assets/dist/fe/onepage.esm.js',
    'assets/dist/fe/onepage.iife.js'
];

foreach ($asset_files as $file) {
    $full_path = $plugin_dir . $file;
    if (file_exists($full_path)) {
        $mtime = filemtime($full_path);
        echo '<p class="success">✓ ' . $file . ' (modificato: ' . date('Y-m-d H:i:s', $mtime) . ')</p>';
    } else {
        echo '<p class="error">✗ ' . $file . ' NON TROVATO</p>';
    }
}

// 5. Calcola versione asset
echo '<h2>5. Versione Asset</h2>';
if (class_exists('FP\Resv\Core\Plugin')) {
    $version = FP\Resv\Core\Plugin::assetVersion();
    echo '<p class="success">✓ Versione asset: <code>' . $version . '</code></p>';
} else {
    echo '<p class="error">✗ Classe Plugin non trovata</p>';
}

echo '<h2>✅ Cache Refresh Completato!</h2>';
echo '<div class="info">';
echo '<strong>Prossimi passi:</strong><br>';
echo '1. Vai alla pagina con il form<br>';
echo '2. Premi <kbd>Ctrl + Shift + R</kbd> (Windows) o <kbd>Cmd + Shift + R</kbd> (Mac)<br>';
echo '3. Le modifiche dovrebbero essere ora visibili<br>';
echo '4. Se non funziona, esegui anche: <code>npm run build</code>';
echo '</div>';

echo '<div class="step">';
echo '<h3>🔧 Se le modifiche ancora non appaiono:</h3>';
echo '<ol>';
echo '<li>Esegui <code>npm run build</code> nella directory del plugin</li>';
echo '<li>Controlla che i file CSS/JS abbiano il parametro <code>?ver=</code> con timestamp aggiornato</li>';
echo '<li>Verifica nella console del browser (F12) che non ci siano errori</li>';
echo '<li>Prova in modalità incognito/privata del browser</li>';
echo '</ol>';
echo '</div>';

echo '<p><a href="' . admin_url('admin.php?page=fp-resv-settings') . '" class="button">Vai alle Impostazioni</a>';
echo '<a href="javascript:history.back()" class="button">Torna Indietro</a></p>';

echo '</div>';
echo '</body>';
echo '</html>';
