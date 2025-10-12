<?php
/**
 * DIAGNOSTICA COMPLETA AGENDA FP RESTAURANT RESERVATIONS
 * 
 * Esegui questo file per identificare esattamente cosa non funziona
 * 
 * COME USARLO:
 * 1. Carica questo file nella root di WordPress
 * 2. Apri nel browser: https://tuosito.com/DIAGNOSTICA-AGENDA-COMPLETA.php
 * 3. Leggi il report dettagliato
 */

// Bootstrap WordPress
require_once __DIR__ . '/wp-load.php';

// Stile per output
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnostica Agenda FP Restaurant Reservations</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #1d2327;
            border-bottom: 2px solid #dcdcde;
            padding-bottom: 8px;
            margin-top: 0;
        }
        .success {
            color: #008a00;
            background: #d5f4d5;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #cc1818;
            background: #ffd8d8;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            color: #996800;
            background: #fff8d5;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #135e96;
            background: #e5f5fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .test {
            margin: 15px 0;
            padding: 10px;
            border-left: 4px solid #dcdcde;
        }
        .test.passed {
            border-left-color: #00a32a;
            background: #f6f7f7;
        }
        .test.failed {
            border-left-color: #d63638;
            background: #f6f7f7;
        }
        .icon-success::before { content: "✅ "; }
        .icon-error::before { content: "❌ "; }
        .icon-warning::before { content: "⚠️ "; }
        .icon-info::before { content: "ℹ️ "; }
    </style>
</head>
<body>

<h1>🔍 Diagnostica Completa Agenda</h1>

<?php
$errors = [];
$warnings = [];
$allPassed = true;

// =====================================================
// TEST 1: AMBIENTE
// =====================================================
?>
<div class="section">
    <h2>1. Ambiente e Requisiti</h2>
    
    <?php
    echo '<div class="test passed"><strong class="icon-success">PHP Version:</strong> ' . phpversion() . '</div>';
    echo '<div class="test passed"><strong class="icon-success">WordPress Version:</strong> ' . get_bloginfo('version') . '</div>';
    
    // Controlla se il plugin è attivo
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $pluginActive = is_plugin_active('fp-restaurant-reservations/fp-restaurant-reservations.php');
    if ($pluginActive) {
        echo '<div class="test passed"><strong class="icon-success">Plugin FP Restaurant Reservations:</strong> ATTIVO</div>';
    } else {
        echo '<div class="test failed"><strong class="icon-error">Plugin FP Restaurant Reservations:</strong> NON ATTIVO</div>';
        $errors[] = 'Plugin non attivo';
        $allPassed = false;
    }
    
    // Controlla Composer autoload
    $autoloadExists = file_exists(__DIR__ . '/wp-content/plugins/fp-restaurant-reservations/vendor/autoload.php');
    if ($autoloadExists) {
        echo '<div class="test passed"><strong class="icon-success">Composer autoload:</strong> TROVATO</div>';
    } else {
        echo '<div class="test failed"><strong class="icon-error">Composer autoload:</strong> NON TROVATO</div>';
        $errors[] = 'Esegui: cd wp-content/plugins/fp-restaurant-reservations && composer install';
        $allPassed = false;
    }
    ?>
</div>

<?php
// =====================================================
// TEST 2: DATABASE
// =====================================================
?>
<div class="section">
    <h2>2. Database</h2>
    
    <?php
    global $wpdb;
    
    $tableReservations = $wpdb->prefix . 'fp_reservations';
    $tableCustomers = $wpdb->prefix . 'fp_customers';
    
    // Controlla se le tabelle esistono
    $reservationsExists = $wpdb->get_var("SHOW TABLES LIKE '$tableReservations'") === $tableReservations;
    $customersExists = $wpdb->get_var("SHOW TABLES LIKE '$tableCustomers'") === $tableCustomers;
    
    if ($reservationsExists) {
        echo '<div class="test passed"><strong class="icon-success">Tabella prenotazioni:</strong> ' . $tableReservations . ' ESISTE</div>';
        
        // Conta le prenotazioni
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $tableReservations");
        echo '<div class="info"><strong>Totale prenotazioni:</strong> ' . $count . '</div>';
        
        if ($count > 0) {
            // Mostra alcune prenotazioni di esempio
            $samples = $wpdb->get_results("SELECT id, date, time, party, status FROM $tableReservations ORDER BY date DESC, time DESC LIMIT 5");
            echo '<div class="info"><strong>Ultime prenotazioni:</strong>';
            echo '<pre>';
            foreach ($samples as $s) {
                echo sprintf("ID: %d | Data: %s | Ora: %s | Coperti: %d | Stato: %s\n", 
                    $s->id, $s->date, $s->time, $s->party, $s->status);
            }
            echo '</pre></div>';
        } else {
            echo '<div class="warning"><strong class="icon-warning">Database vuoto!</strong> Non ci sono prenotazioni da visualizzare.</div>';
            $warnings[] = 'Database vuoto - crea una prenotazione di test';
        }
    } else {
        echo '<div class="test failed"><strong class="icon-error">Tabella prenotazioni:</strong> NON ESISTE</div>';
        $errors[] = 'Tabella prenotazioni mancante';
        $allPassed = false;
    }
    
    if ($customersExists) {
        echo '<div class="test passed"><strong class="icon-success">Tabella clienti:</strong> ' . $tableCustomers . ' ESISTE</div>';
    } else {
        echo '<div class="test failed"><strong class="icon-error">Tabella clienti:</strong> NON ESISTE</div>';
        $errors[] = 'Tabella clienti mancante';
        $allPassed = false;
    }
    ?>
</div>

<?php
// =====================================================
// TEST 3: UTENTE E PERMESSI
// =====================================================
?>
<div class="section">
    <h2>3. Utente e Permessi</h2>
    
    <?php
    $currentUser = wp_get_current_user();
    
    if ($currentUser->ID > 0) {
        echo '<div class="test passed"><strong class="icon-success">Utente loggato:</strong> ' . $currentUser->user_login . '</div>';
        echo '<div class="info"><strong>Ruolo:</strong> ' . implode(', ', $currentUser->roles) . '</div>';
        
        // Controlla permessi
        $hasManageOptions = current_user_can('manage_options');
        $hasManageReservations = current_user_can('manage_fp_reservations');
        
        if ($hasManageOptions || $hasManageReservations) {
            echo '<div class="test passed"><strong class="icon-success">Permessi:</strong> OK (può gestire l\'agenda)</div>';
        } else {
            echo '<div class="test failed"><strong class="icon-error">Permessi:</strong> INSUFFICIENTI</div>';
            $errors[] = 'Utente senza permessi per gestire prenotazioni';
            $allPassed = false;
        }
    } else {
        echo '<div class="test failed"><strong class="icon-error">Utente:</strong> NON LOGGATO</div>';
        $errors[] = 'Nessun utente loggato';
        $allPassed = false;
    }
    ?>
</div>

<?php
// =====================================================
// TEST 4: ENDPOINT REST API
// =====================================================
?>
<div class="section">
    <h2>4. Endpoint REST API</h2>
    
    <?php
    // Controlla se REST API è disponibile
    $restUrl = rest_url('fp-resv/v1/agenda');
    echo '<div class="info"><strong>URL endpoint:</strong> <code>' . $restUrl . '</code></div>';
    
    // Verifica se l'endpoint è registrato
    $restServer = rest_get_server();
    $routes = $restServer->get_routes();
    
    $agendaEndpointExists = isset($routes['/fp-resv/v1/agenda']);
    
    if ($agendaEndpointExists) {
        echo '<div class="test passed"><strong class="icon-success">Endpoint /agenda:</strong> REGISTRATO</div>';
        
        // Prova a chiamare l'endpoint
        echo '<div class="info"><strong>Test chiamata API...</strong></div>';
        
        // Simula una richiesta REST
        $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
        $request->set_param('date', date('Y-m-d'));
        
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            $errorData = $response->as_error();
            echo '<div class="test failed"><strong class="icon-error">Risposta API:</strong> ERRORE</div>';
            echo '<div class="error"><strong>Errore:</strong> ' . $errorData->get_error_message() . '</div>';
            $errors[] = 'API endpoint non funzionante: ' . $errorData->get_error_message();
            $allPassed = false;
        } else {
            $statusCode = $response->get_status();
            echo '<div class="test passed"><strong class="icon-success">Risposta API:</strong> HTTP ' . $statusCode . '</div>';
            
            $data = $response->get_data();
            
            echo '<div class="info"><strong>Struttura risposta:</strong>';
            echo '<pre>';
            
            if (is_array($data)) {
                echo "Tipo: Array di prenotazioni (formato vecchio)\n";
                echo "Numero elementi: " . count($data) . "\n";
            } elseif (is_object($data) || (is_array($data) && isset($data['reservations']))) {
                echo "Tipo: Oggetto strutturato (formato nuovo)\n";
                if (isset($data['meta'])) echo "✓ meta\n";
                if (isset($data['stats'])) echo "✓ stats\n";
                if (isset($data['data'])) echo "✓ data\n";
                if (isset($data['reservations'])) {
                    echo "✓ reservations (" . count($data['reservations']) . " elementi)\n";
                }
            } else {
                echo "Tipo: Sconosciuto\n";
            }
            
            echo "\nPrima parte della risposta:\n";
            echo substr(json_encode($data, JSON_PRETTY_PRINT), 0, 500) . '...';
            echo '</pre></div>';
        }
    } else {
        echo '<div class="test failed"><strong class="icon-error">Endpoint /agenda:</strong> NON REGISTRATO</div>';
        $errors[] = 'Endpoint REST API non registrato';
        $allPassed = false;
        
        echo '<div class="warning">Endpoint registrati trovati:';
        echo '<pre>';
        foreach ($routes as $route => $handlers) {
            if (strpos($route, 'fp-resv') !== false) {
                echo $route . "\n";
            }
        }
        echo '</pre></div>';
    }
    ?>
</div>

<?php
// =====================================================
// TEST 5: ASSETS FRONTEND
// =====================================================
?>
<div class="section">
    <h2>5. Asset Frontend</h2>
    
    <?php
    $pluginDir = WP_PLUGIN_DIR . '/fp-restaurant-reservations';
    
    $agendaJs = $pluginDir . '/assets/js/admin/agenda-app.js';
    $agendaCss = $pluginDir . '/assets/css/admin-agenda.css';
    $templatePhp = $pluginDir . '/src/Admin/Views/agenda.php';
    
    if (file_exists($agendaJs)) {
        echo '<div class="test passed"><strong class="icon-success">JavaScript agenda:</strong> TROVATO</div>';
        echo '<div class="info">Dimensione: ' . round(filesize($agendaJs) / 1024, 2) . ' KB</div>';
    } else {
        echo '<div class="test failed"><strong class="icon-error">JavaScript agenda:</strong> NON TROVATO</div>';
        $errors[] = 'File agenda-app.js mancante';
        $allPassed = false;
    }
    
    if (file_exists($agendaCss)) {
        echo '<div class="test passed"><strong class="icon-success">CSS agenda:</strong> TROVATO</div>';
    } else {
        echo '<div class="test failed"><strong class="icon-error">CSS agenda:</strong> NON TROVATO</div>';
        $errors[] = 'File CSS mancante';
        $allPassed = false;
    }
    
    if (file_exists($templatePhp)) {
        echo '<div class="test passed"><strong class="icon-success">Template PHP:</strong> TROVATO</div>';
    } else {
        echo '<div class="test failed"><strong class="icon-error">Template PHP:</strong> NON TROVATO</div>';
        $errors[] = 'Template agenda.php mancante';
        $allPassed = false;
    }
    ?>
</div>

<?php
// =====================================================
// TEST 6: CONFIGURAZIONE JAVASCRIPT
// =====================================================
?>
<div class="section">
    <h2>6. Configurazione JavaScript</h2>
    
    <div class="info">
        <p>Per verificare se il JavaScript riceve la configurazione corretta, apri la <strong>Console del Browser (F12)</strong> nella pagina dell'agenda e digita:</p>
        <pre>console.log(window.fpResvAgendaSettings);</pre>
        <p>Dovresti vedere qualcosa tipo:</p>
        <pre>{
    "restRoot": "<?php echo rest_url('fp-resv/v1'); ?>",
    "nonce": "abc123...",
    ...
}</pre>
        <p><strong>Se vedi "undefined":</strong> Problema con wp_localize_script nel PHP</p>
    </div>
</div>

<?php
// =====================================================
// RIEPILOGO FINALE
// =====================================================
?>
<div class="section">
    <h2>📊 Riepilogo</h2>
    
    <?php if ($allPassed && count($warnings) === 0): ?>
        <div class="success">
            <h3 class="icon-success">Tutto sembra OK!</h3>
            <p>Non ho trovato problemi evidenti. Se l'agenda non funziona ancora:</p>
            <ol>
                <li>Apri la <strong>Console del Browser (F12)</strong> nella pagina dell'agenda</li>
                <li>Cerca messaggi di errore in rosso</li>
                <li>Cerca i log con prefisso <code>[Agenda]</code></li>
                <li>Copia e incolla i messaggi di errore</li>
            </ol>
        </div>
    <?php else: ?>
        <div class="error">
            <h3 class="icon-error">Problemi Trovati</h3>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (count($warnings) > 0): ?>
        <div class="warning">
            <h3 class="icon-warning">Avvisi</h3>
            <ul>
                <?php foreach ($warnings as $warning): ?>
                    <li><?php echo $warning; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
// =====================================================
// AZIONI SUGGERITE
// =====================================================
?>
<div class="section">
    <h2>🔧 Azioni Suggerite</h2>
    
    <h3>1. Se il database è vuoto:</h3>
    <div class="info">
        <p>Crea una prenotazione di test dalla pagina dell'agenda o esegui questo SQL:</p>
        <pre>INSERT INTO <?php echo $tableReservations; ?> (date, time, party, status, customer_id, created_at, updated_at)
VALUES ('<?php echo date('Y-m-d'); ?>', '19:30:00', 4, 'confirmed', NULL, NOW(), NOW());</pre>
    </div>
    
    <h3>2. Se l'endpoint non è registrato:</h3>
    <div class="info">
        <p>Vai su <strong>WordPress Admin > Impostazioni > Permalink</strong> e clicca <strong>Salva modifiche</strong> per rigenerare le regole.</p>
    </div>
    
    <h3>3. Se gli asset mancano:</h3>
    <div class="info">
        <p>Esegui questi comandi nella cartella del plugin:</p>
        <pre>composer install --no-dev
npm install
npm run build</pre>
    </div>
    
    <h3>4. Controllo Console Browser (FONDAMENTALE):</h3>
    <div class="warning">
        <p><strong>Questa è la parte più importante!</strong></p>
        <ol>
            <li>Vai su <strong>WordPress Admin > Prenotazioni > Agenda</strong></li>
            <li>Premi <strong>F12</strong> per aprire gli strumenti sviluppatore</li>
            <li>Vai al tab <strong>Console</strong></li>
            <li>Cerca messaggi con prefisso <code>[Agenda]</code> o errori in rosso</li>
            <li>Copia TUTTO il contenuto della console</li>
        </ol>
        <p><strong>Mandami l'output della console e potrò aiutarti meglio!</strong></p>
    </div>
</div>

<div class="section">
    <h2>📞 Prossimi Passi</h2>
    <div class="info">
        <p>Se hai eseguito questo script e ci sono ancora problemi:</p>
        <ol>
            <li>Fai uno screenshot di questa pagina</li>
            <li>Apri l'agenda nel browser</li>
            <li>Apri la Console (F12)</li>
            <li>Fai uno screenshot della console con i messaggi [Agenda]</li>
            <li>Condividi entrambi gli screenshot</li>
        </ol>
    </div>
</div>

</body>
</html>

