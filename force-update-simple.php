<?php
/**
 * Script ultra-semplice per forzare refresh cache
 * Carica questo file nella ROOT di WordPress
 * Poi vai su: https://tuosito.it/force-update-simple.php
 */

// Carica WordPress
require_once(__DIR__ . '/wp-load.php');

// Verifica permessi
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('❌ Devi essere loggato come amministratore!');
}

// Aggiorna timestamp
$timestamp = time();
$updated = update_option('fp_resv_last_upgrade', $timestamp, false);

// Pulisci cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Pulisci transient
global $wpdb;
$deleted = $wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '%_transient_%fp_resv%'"
);

// Verifica valore aggiornato
$check = get_option('fp_resv_last_upgrade');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cache Updated</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 700px;
            margin: 50px auto;
            padding: 20px;
            line-height: 1.6;
        }
        .success { color: #10b981; font-size: 1.5em; }
        .info { background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .steps { background: #f9fafb; padding: 20px; border-radius: 8px; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { 
            display: inline-block; 
            background: #2563eb; 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 6px; 
            margin: 10px 0;
        }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <h1 class="success">✅ Cache Aggiornata!</h1>
    
    <div class="info">
        <strong>Timestamp aggiornato:</strong> <?= $timestamp ?><br>
        <strong>Nuova versione asset:</strong> <code>0.1.10.<?= $timestamp ?></code><br>
        <strong>Transient eliminati:</strong> <?= $deleted ?><br>
        <strong>Verifica valore:</strong> <?= $check ?> <?= $check == $timestamp ? '✅' : '⚠️ DIVERSO!' ?>
    </div>

    <div class="warning">
        <strong>⚠️ IMPORTANTE:</strong> La cache è stata aggiornata sul server, ma il browser potrebbe ancora avere la versione vecchia in cache!
    </div>

    <div class="steps">
        <h2>🔧 Prossimi Step:</h2>
        <ol>
            <li>Apri l'Agenda (click sul pulsante sotto)</li>
            <li>Apri DevTools: premi <strong>F12</strong></li>
            <li>Vai nel tab <strong>Network</strong></li>
            <li>Spunta <strong>"Disable cache"</strong></li>
            <li>Fai Hard Refresh: <strong>Ctrl+Shift+R</strong> (Win) o <strong>Cmd+Shift+R</strong> (Mac)</li>
            <li>Nella Console verifica che vedi: <code>[Agenda] Tipo risposta: object</code></li>
            <li>Nel tab Network, cerca il file <code>agenda-app.js</code></li>
            <li>Verifica che abbia: <code>?ver=0.1.10.<?= $timestamp ?></code></li>
        </ol>
    </div>

    <a href="<?= admin_url('admin.php?page=fp-resv-agenda') ?>" class="btn">🚀 Apri Agenda</a>

    <hr style="margin: 40px 0;">

    <h3>🔍 Se il problema persiste:</h3>
    <ol>
        <li><strong>Verifica che il file sia aggiornato in produzione:</strong>
            <pre>ssh user@server
cd /path/to/plugin
grep -n "Tipo risposta:" assets/js/admin/agenda-app.js</pre>
            Se NON trova niente = devi fare <code>git pull</code>!
        </li>
        
        <li><strong>Pulisci cache CDN (se presente):</strong>
            <ul>
                <li>Cloudflare: Dashboard → Caching → Purge Everything</li>
                <li>Altri CDN: cerca "Invalidate cache" o "Purge"</li>
            </ul>
        </li>
        
        <li><strong>Pulisci cache plugin WordPress:</strong>
            <ul>
                <li>W3 Total Cache: Performance → Purge All Caches</li>
                <li>WP Super Cache: Settings → Delete Cache</li>
                <li>Altri: cerca opzione simile</li>
            </ul>
        </li>

        <li><strong>Prova in finestra incognito:</strong>
            <ul>
                <li>Chrome: Ctrl+Shift+N</li>
                <li>Firefox: Ctrl+Shift+P</li>
                <li>Safari: Cmd+Shift+N</li>
            </ul>
        </li>
    </ol>

    <div class="info">
        <strong>💡 Debug:</strong> Nel tab Network, quando carichi l'agenda, il file <code>agenda-app.js</code> 
        deve avere Status <strong>200</strong> (dal server), NON "from cache" o "from disk cache".
    </div>

    <p style="color: #6b7280; font-size: 0.9em; margin-top: 40px;">
        Script eseguito il: <?= date('d/m/Y H:i:s') ?><br>
        Puoi chiudere questa pagina e ricaricare per aggiornare di nuovo il timestamp.
    </p>
</body>
</html>
