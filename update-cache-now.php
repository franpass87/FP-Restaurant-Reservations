<?php
/**
 * FORCE UPDATE CACHE - Esegui questo script via browser
 * 
 * Carica nella ROOT di WordPress e vai su:
 * https://www.villadianella.it/update-cache-now.php
 */

// Trova e carica WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php', 
    __DIR__ . '/../wp-load.php',
    __DIR__ . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('❌ Impossibile trovare WordPress. Carica questo file nella ROOT di WordPress (dove c\'è wp-config.php)');
}

// Verifica permessi
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('❌ Devi essere loggato come amministratore. <a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Login</a>');
}

// Aggiorna timestamp
$old_value = get_option('fp_resv_last_upgrade', 'non impostato');
$timestamp = time();
$updated = update_option('fp_resv_last_upgrade', $timestamp, false);
$new_value = get_option('fp_resv_last_upgrade');

// Pulisci cache
$cache_flushed = false;
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    $cache_flushed = true;
}

// Pulisci transient
global $wpdb;
$deleted_transients = $wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '%_transient_%fp_resv%' 
        OR option_name LIKE '%_transient_timeout_%fp_resv%'"
);

// Se ci sono plugin di cache, prova a pulirli
$cache_plugins_cleared = [];
if (function_exists('w3tc_flush_all')) {
    w3tc_flush_all();
    $cache_plugins_cleared[] = 'W3 Total Cache';
}
if (function_exists('wp_cache_clear_cache')) {
    wp_cache_clear_cache();
    $cache_plugins_cleared[] = 'WP Super Cache';
}
if (function_exists('rocket_clean_domain')) {
    rocket_clean_domain();
    $cache_plugins_cleared[] = 'WP Rocket';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Aggiornata</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #10b981;
            font-size: 2.5em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .emoji { font-size: 1.2em; }
        .info-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .warning-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .steps-box {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
        }
        .value {
            font-family: 'Courier New', monospace;
            background: #e5e7eb;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1em;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        ol, ul { 
            margin-left: 25px; 
            line-height: 1.8;
        }
        li { margin: 10px 0; }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .status.success { background: #d1fae5; color: #065f46; }
        .status.warning { background: #fef3c7; color: #92400e; }
        hr { 
            border: none; 
            border-top: 2px solid #e5e7eb; 
            margin: 30px 0; 
        }
        .debug-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.9em;
            color: #6b7280;
            margin-top: 30px;
        }
        .check-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 12px 0;
        }
        .check-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .check-icon.success { background: #d1fae5; color: #065f46; }
        .check-icon.warning { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="emoji">✅</span> Cache Aggiornata con Successo!</h1>
        
        <div class="info-box">
            <h3 style="margin-bottom: 15px;">📊 Risultati Operazione:</h3>
            
            <div class="check-item">
                <div class="check-icon <?= $updated ? 'success' : 'warning' ?>">
                    <?= $updated ? '✓' : '⚠' ?>
                </div>
                <div>
                    <strong>Timestamp aggiornato:</strong><br>
                    Vecchio: <span class="value"><?= $old_value ?></span><br>
                    Nuovo: <span class="value"><?= $new_value ?></span>
                </div>
            </div>

            <div class="check-item">
                <div class="check-icon <?= $cache_flushed ? 'success' : 'warning' ?>">
                    <?= $cache_flushed ? '✓' : '⚠' ?>
                </div>
                <div>
                    <strong>Cache WordPress:</strong>
                    <span class="status <?= $cache_flushed ? 'success' : 'warning' ?>">
                        <?= $cache_flushed ? 'Pulita' : 'Non disponibile' ?>
                    </span>
                </div>
            </div>

            <div class="check-item">
                <div class="check-icon success">✓</div>
                <div>
                    <strong>Transient eliminati:</strong> 
                    <span class="value"><?= $deleted_transients ?></span>
                </div>
            </div>

            <?php if (!empty($cache_plugins_cleared)): ?>
            <div class="check-item">
                <div class="check-icon success">✓</div>
                <div>
                    <strong>Plugin cache puliti:</strong>
                    <?php foreach ($cache_plugins_cleared as $plugin): ?>
                        <span class="status success"><?= $plugin ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #d1fae5;">
                <strong>🎯 Nuova versione asset:</strong><br>
                <span class="value">0.1.10.<?= $new_value ?></span>
            </div>
        </div>

        <div class="warning-box">
            <h3 style="margin-bottom: 15px;">⚠️ IMPORTANTE - Non hai finito!</h3>
            <p style="font-size: 1.1em; line-height: 1.6;">
                La cache è stata aggiornata sul <strong>server</strong>, ma il tuo <strong>browser</strong> 
                potrebbe ancora avere la versione vecchia in cache!
            </p>
        </div>

        <div class="steps-box">
            <h2 style="margin-bottom: 20px;">🔧 Adesso fai questi step nel BROWSER:</h2>
            <ol style="font-size: 1.05em;">
                <li><strong>Clicca sul pulsante "Apri Agenda" qui sotto</strong></li>
                <li>Premi <strong>F12</strong> per aprire DevTools</li>
                <li>Vai nel tab <strong>Network</strong></li>
                <li>Spunta la checkbox <strong>"Disable cache"</strong></li>
                <li>Fai Hard Refresh: <strong>Ctrl+Shift+R</strong> (Windows) o <strong>Cmd+Shift+R</strong> (Mac)</li>
                <li>Nella <strong>Console</strong> cerca: <span class="value">[Agenda] Tipo risposta: object</span></li>
                <li>Nel tab <strong>Network</strong>, trova <span class="value">agenda-app.js</span></li>
                <li>Verifica che abbia: <span class="value">?ver=0.1.10.<?= $new_value ?></span></li>
            </ol>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="<?= admin_url('admin.php?page=fp-resv-agenda') ?>" class="btn">
                🚀 Apri Agenda Adesso
            </a>
        </div>

        <hr>

        <h3 style="margin-bottom: 15px;">🔍 Se l'errore persiste ancora:</h3>
        <ol>
            <li><strong>Prova in modalità Incognito:</strong>
                <ul>
                    <li>Chrome: Ctrl+Shift+N</li>
                    <li>Firefox: Ctrl+Shift+P</li>
                    <li>Logga come admin e apri l'agenda</li>
                </ul>
            </li>
            
            <li><strong>Pulisci cache CDN</strong> (se ne hai uno):
                <ul>
                    <li>Cloudflare: Dashboard → Caching → Purge Everything</li>
                    <li>Altri CDN: cerca "Invalidate" o "Purge"</li>
                </ul>
            </li>
            
            <li><strong>Verifica nel Network tab:</strong>
                <ul>
                    <li>Il file <code>agenda-app.js</code> deve avere Status <strong>200</strong></li>
                    <li>NON deve dire "from cache" o "from disk cache"</li>
                    <li>Deve avere <code>?ver=0.1.10.<?= $new_value ?></code></li>
                </ul>
            </li>

            <li><strong>Controlla la risposta API:</strong>
                <ul>
                    <li>Nel Network tab cerca: <code>agenda?date=</code></li>
                    <li>Click → tab Response</li>
                    <li>Deve essere JSON valido con <code>{"reservations": [...]}</code></li>
                </ul>
            </li>
        </ol>

        <div class="info-box" style="margin-top: 30px;">
            <h4 style="margin-bottom: 10px;">💡 Cosa cercare nella Console:</h4>
            <div style="background: white; padding: 15px; border-radius: 6px; margin: 10px 0;">
                <div style="color: #10b981; font-family: monospace; margin: 5px 0;">
                    ✅ FUNZIONA:<br>
                    [Agenda] Tipo risposta: object<br>
                    [Agenda] È array? false<br>
                    [Agenda] Ha reservations? true<br>
                    [Agenda] ✓ Caricate X prenotazioni
                </div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 6px; margin: 10px 0;">
                <div style="color: #ef4444; font-family: monospace; margin: 5px 0;">
                    ❌ NON FUNZIONA (cache vecchia):<br>
                    Error: Risposta API non valida<br>
                    at AgendaApp.loadReservations (...:233:23)
                </div>
            </div>
        </div>

        <div class="debug-info">
            <strong>📝 Info Debug:</strong><br>
            Eseguito: <?= date('d/m/Y H:i:s') ?><br>
            WordPress: <?= get_bloginfo('version') ?><br>
            PHP: <?= PHP_VERSION ?><br>
            Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?><br>
            <br>
            <small>Puoi ricaricare questa pagina per aggiornare di nuovo il timestamp.</small>
        </div>
    </div>
</body>
</html>
