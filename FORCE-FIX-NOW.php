<?php
/**
 * FORCE FIX CACHE - Accesso diretto al database
 * 
 * Vai su: https://www.villadianella.it/wp-content/plugins/fp-restaurant-reservations/FORCE-FIX-NOW.php
 */

// Trova wp-config.php
$wp_config_paths = [
    __DIR__ . '/../../../wp-config.php',
    __DIR__ . '/../../../../wp-config.php',
];

$wp_config = null;
foreach ($wp_config_paths as $path) {
    if (file_exists($path)) {
        $wp_config = $path;
        break;
    }
}

if (!$wp_config) {
    die('❌ Impossibile trovare wp-config.php');
}

// Leggi credenziali database da wp-config.php
$config_content = file_get_contents($wp_config);

preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $config_content, $db_name_match);
preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'/", $config_content, $db_user_match);
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'/", $config_content, $db_pass_match);
preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $config_content, $db_host_match);
preg_match("/\\\$table_prefix\s*=\s*'([^']+)'/", $config_content, $prefix_match);

$db_name = $db_name_match[1] ?? null;
$db_user = $db_user_match[1] ?? null;
$db_pass = $db_pass_match[1] ?? null;
$db_host = $db_host_match[1] ?? 'localhost';
$prefix = $prefix_match[1] ?? 'wp_';

if (!$db_name || !$db_user) {
    die('❌ Impossibile leggere credenziali database da wp-config.php');
}

// Connetti al database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('❌ Errore connessione database: ' . $e->getMessage());
}

// Leggi valore corrente
$stmt = $pdo->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = 'fp_resv_last_upgrade' LIMIT 1");
$stmt->execute();
$old_value = $stmt->fetchColumn();

// Aggiorna timestamp
$timestamp = time();
$stmt = $pdo->prepare("
    INSERT INTO {$prefix}options (option_name, option_value, autoload) 
    VALUES ('fp_resv_last_upgrade', :timestamp, 'no')
    ON DUPLICATE KEY UPDATE option_value = :timestamp
");
$updated = $stmt->execute(['timestamp' => $timestamp]);

// Verifica nuovo valore
$stmt = $pdo->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = 'fp_resv_last_upgrade' LIMIT 1");
$stmt->execute();
$new_value = $stmt->fetchColumn();

// Elimina transient
$stmt = $pdo->prepare("
    DELETE FROM {$prefix}options 
    WHERE option_name LIKE '%_transient_%fp_resv%' 
       OR option_name LIKE '%_transient_timeout_%fp_resv%'
");
$deleted = $stmt->execute();
$deleted_count = $stmt->rowCount();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Cache Forzata!</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 900px;
            margin: 50px auto;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
        }
        h1 {
            color: #10b981;
            font-size: 3em;
            margin-bottom: 30px;
            text-align: center;
        }
        .success {
            background: #d1fae5;
            border: 3px solid #10b981;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
        }
        .big-number {
            font-size: 4em;
            font-weight: bold;
            color: #10b981;
            text-align: center;
            margin: 20px 0;
        }
        .code {
            font-family: 'Courier New', monospace;
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            overflow-x: auto;
        }
        .warning {
            background: #fef3c7;
            border: 3px solid #f59e0b;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            font-size: 1.2em;
        }
        .steps {
            background: #f9fafb;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
        }
        .step {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-left: 5px solid #3b82f6;
            border-radius: 8px;
            font-size: 1.1em;
        }
        .step strong {
            color: #3b82f6;
            font-size: 1.3em;
        }
        .btn {
            display: block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 40px;
            text-decoration: none;
            border-radius: 15px;
            font-weight: bold;
            font-size: 1.5em;
            text-align: center;
            margin: 30px auto;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.7);
        }
        kbd {
            background: #1f2937;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-family: monospace;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ DATABASE AGGIORNATO!</h1>
        
        <div class="success">
            <h2 style="text-align: center; margin-bottom: 20px;">📊 Operazione Completata</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: center;">
                <div>
                    <div style="font-size: 0.9em; color: #6b7280;">Vecchio Timestamp</div>
                    <div class="code"><?= $old_value ?: 'non impostato' ?></div>
                </div>
                <div>
                    <div style="font-size: 0.9em; color: #6b7280;">Nuovo Timestamp</div>
                    <div class="code"><?= $new_value ?></div>
                </div>
            </div>
            
            <div class="big-number"><?= $new_value ?></div>
            
            <div style="text-align: center; margin-top: 20px;">
                <div style="font-size: 0.9em; color: #6b7280;">Nuova Versione Asset</div>
                <div class="code">agenda-app.js?ver=0.1.10.<?= $new_value ?></div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; color: #065f46;">
                ✅ Transient eliminati: <strong><?= $deleted_count ?></strong>
            </div>
        </div>

        <div class="warning">
            <h2 style="margin-bottom: 15px;">⚠️ ATTENZIONE!</h2>
            <p style="line-height: 1.8;">
                Ho aggiornato il database, ma il tuo <strong>BROWSER</strong> ha ancora 
                la versione vecchia in cache. Devi fare <strong>HARD REFRESH</strong> 
                altrimenti vedrai sempre l'errore!
            </p>
        </div>

        <div class="steps">
            <h2 style="text-align: center; margin-bottom: 30px;">🎯 FAI QUESTI STEP ORA:</h2>
            
            <div class="step">
                <strong>STEP 1:</strong> Clicca sul pulsante verde qui sotto per aprire l'Agenda
            </div>
            
            <div class="step">
                <strong>STEP 2:</strong> Quando si apre l'Agenda, premi subito <kbd>F12</kbd>
            </div>
            
            <div class="step">
                <strong>STEP 3:</strong> Clicca sul tab <strong>"Network"</strong> in alto
            </div>
            
            <div class="step">
                <strong>STEP 4:</strong> Cerca e spunta la checkbox <strong>"Disable cache"</strong>
            </div>
            
            <div class="step">
                <strong>STEP 5:</strong> Premi <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd> 
                (su Mac: <kbd>Cmd</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>)
            </div>
            
            <div class="step">
                <strong>STEP 6:</strong> Vai nel tab <strong>"Console"</strong> e cerca questa riga:<br>
                <div class="code">[Agenda] Tipo risposta: object</div>
                Se la vedi = <strong style="color: #10b981;">FUNZIONA!</strong> ✅
            </div>
        </div>

        <a href="https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda" class="btn">
            🚀 APRI AGENDA
        </a>

        <div style="background: #1f2937; color: white; padding: 30px; border-radius: 15px; margin: 30px 0;">
            <h3 style="margin-bottom: 20px;">🔍 COSA CERCARE:</h3>
            
            <div style="background: #065f46; padding: 20px; border-radius: 10px; margin: 15px 0;">
                <div style="font-weight: bold; margin-bottom: 10px;">✅ SE FUNZIONA vedrai:</div>
                <div class="code" style="background: transparent; color: #10b981;">
                    [Agenda] Inizializzazione...<br>
                    [Agenda] Caricamento prenotazioni...<br>
                    [API] GET https://...<br>
                    [Agenda] Tipo risposta: object ← QUESTO!<br>
                    [Agenda] È array? false<br>
                    [Agenda] ✓ Caricate X prenotazioni
                </div>
            </div>
            
            <div style="background: #7f1d1d; padding: 20px; border-radius: 10px; margin: 15px 0;">
                <div style="font-weight: bold; margin-bottom: 10px;">❌ SE NON FUNZIONA vedrai:</div>
                <div class="code" style="background: transparent; color: #ef4444;">
                    [Agenda] Errore nel caricamento: Error: Risposta API non valida<br>
                    at AgendaApp.loadReservations (agenda-app.js?ver=0.1.9.XXX:233:23)
                </div>
                <div style="margin-top: 10px; color: #fca5a5;">
                    Se vedi ancora questo = browser usa ancora cache vecchia!<br>
                    Riprova step 4-5 (Disable cache + Ctrl+Shift+R)
                </div>
            </div>
        </div>

        <div style="background: #eff6ff; padding: 25px; border-radius: 15px; border: 2px solid #3b82f6;">
            <h3 style="color: #1e40af; margin-bottom: 15px;">💡 Nel tab Network devi vedere:</h3>
            <ol style="margin-left: 25px; line-height: 2;">
                <li>Trova il file: <code style="background: #dbeafe; padding: 3px 8px; border-radius: 4px;">agenda-app.js</code></li>
                <li>Verifica URL: <code style="background: #dbeafe; padding: 3px 8px; border-radius: 4px;">?ver=0.1.10.<?= $new_value ?></code></li>
                <li>Status deve essere: <code style="background: #dcfce7; padding: 3px 8px; border-radius: 4px; color: #166534;">200</code></li>
                <li>NON deve dire "from cache" o "from disk cache"</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 40px; padding: 30px; background: #f9fafb; border-radius: 15px;">
            <h3 style="margin-bottom: 20px;">🆘 Se ancora non funziona:</h3>
            <p style="font-size: 1.1em; line-height: 1.8; color: #4b5563;">
                Prova ad aprire l'agenda in <strong>modalità Incognito</strong>:<br>
                <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>N</kbd> (Chrome)<br>
                <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>P</kbd> (Firefox)<br>
                <br>
                Poi fai login come admin e apri l'agenda.<br>
                In incognito non c'è cache, quindi <strong>deve</strong> funzionare!
            </p>
        </div>

        <div style="margin-top: 40px; padding: 20px; background: #f3f4f6; border-radius: 10px; font-size: 0.9em; color: #6b7280;">
            <strong>Debug Info:</strong><br>
            Database: <?= $db_name ?><br>
            Table Prefix: <?= $prefix ?><br>
            Timestamp: <?= $timestamp ?> (<?= date('d/m/Y H:i:s', $timestamp) ?>)<br>
            Eseguito: <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>
</body>
</html>
