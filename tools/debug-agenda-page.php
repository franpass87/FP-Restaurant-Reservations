<?php
/**
 * Script di debug per verificare perché la pagina Agenda non funziona
 * 
 * Usage: wp eval-file tools/debug-agenda-page.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

echo "\n=== DEBUG: Pagina Agenda Non Funzionante ===\n\n";

// Verifica che il plugin sia caricato
if (!class_exists('FP\\Resv\\Core\\Plugin')) {
    echo "❌ Plugin non caricato!\n";
    exit(1);
}

echo "✅ Plugin caricato correttamente\n\n";

// 1. Verifica che i file esistano
echo "--- VERIFICA FILE ---\n";

$pluginDir = plugin_dir_path(dirname(__FILE__));
$filesToCheck = [
    'assets/js/admin/agenda-app.js' => 'JavaScript Agenda',
    'assets/css/admin-agenda.css' => 'CSS Agenda',
    'assets/css/admin-shell.css' => 'CSS Shell',
    'src/Admin/Views/agenda.php' => 'View PHP Agenda',
    'src/Domain/Reservations/AdminController.php' => 'Controller PHP Agenda',
    'src/Domain/Reservations/AdminREST.php' => 'REST API Agenda',
];

$missingFiles = [];
foreach ($filesToCheck as $file => $description) {
    $path = $pluginDir . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        echo sprintf("✅ %s: %s (%s KB)\n", $description, $file, number_format($size / 1024, 2));
    } else {
        echo sprintf("❌ %s: MANCANTE (%s)\n", $description, $file);
        $missingFiles[] = $file;
    }
}

if (count($missingFiles) > 0) {
    echo "\n⚠️  PROBLEMA: File mancanti rilevati!\n";
    echo "   Questi file sono necessari per il funzionamento dell'Agenda.\n";
    echo "   Reinstalla il plugin o ripristina i file dal repository.\n\n";
}

// 2. Verifica permessi utente
echo "\n--- VERIFICA PERMESSI UTENTE ---\n";

$currentUser = wp_get_current_user();
if (!$currentUser->ID) {
    echo "❌ Nessun utente loggato! Questo script deve essere eseguito nel contesto di WordPress.\n";
} else {
    echo sprintf("Utente corrente: %s (#%d)\n", $currentUser->user_login, $currentUser->ID);
    
    $requiredCaps = [
        'manage_fp_reservations' => 'Gestione prenotazioni',
        'manage_options' => 'Amministratore',
    ];
    
    $hasPermission = false;
    foreach ($requiredCaps as $cap => $description) {
        $has = current_user_can($cap);
        echo sprintf("%s %s: %s\n", $has ? '✅' : '❌', $description, $cap);
        if ($has) $hasPermission = true;
    }
    
    if (!$hasPermission) {
        echo "\n⚠️  PROBLEMA: L'utente non ha i permessi per accedere all'Agenda!\n";
        echo "   L'utente deve avere almeno uno di questi permessi:\n";
        echo "   - manage_fp_reservations (Gestione prenotazioni)\n";
        echo "   - manage_options (Amministratore)\n\n";
    }
}

// 3. Verifica che la pagina menu esista
echo "\n--- VERIFICA MENU WORDPRESS ---\n";

global $submenu;
$menuFound = false;

if (isset($submenu['fp-resv-settings'])) {
    foreach ($submenu['fp-resv-settings'] as $item) {
        if (isset($item[2]) && $item[2] === 'fp-resv-agenda') {
            $menuFound = true;
            echo sprintf("✅ Voce menu trovata: %s\n", $item[0]);
            echo sprintf("   Capability richiesta: %s\n", $item[1]);
            echo sprintf("   Slug: %s\n", $item[2]);
            break;
        }
    }
}

if (!$menuFound) {
    echo "❌ Voce menu 'Agenda' non trovata!\n";
    echo "   Il menu potrebbe non essere stato registrato correttamente.\n";
}

// 4. Verifica che gli endpoint REST API siano registrati
echo "\n--- VERIFICA ENDPOINT REST API ---\n";

$restServer = rest_get_server();
$routes = $restServer->get_routes();

$requiredEndpoints = [
    '/fp-resv/v1/agenda' => 'Caricamento agenda',
    '/fp-resv/v1/agenda/reservations' => 'Creazione prenotazione',
    '/fp-resv/v1/agenda/reservations/(?P<id>\d+)' => 'Aggiornamento prenotazione',
    '/fp-resv/v1/agenda/reservations/(?P<id>\d+)/move' => 'Spostamento prenotazione',
];

$missingEndpoints = [];
foreach ($requiredEndpoints as $route => $description) {
    // Cerca il route esattamente o come pattern
    $found = false;
    foreach ($routes as $registeredRoute => $handlers) {
        if ($registeredRoute === $route || strpos($registeredRoute, $route) === 0) {
            $found = true;
            echo sprintf("✅ %s: %s\n", $description, $route);
            break;
        }
    }
    
    if (!$found) {
        echo sprintf("❌ %s: %s NON REGISTRATO\n", $description, $route);
        $missingEndpoints[] = $route;
    }
}

if (count($missingEndpoints) > 0) {
    echo "\n⚠️  PROBLEMA: Endpoint REST API mancanti!\n";
    echo "   Gli endpoint REST sono necessari per il funzionamento dell'Agenda.\n";
    echo "   Verifica che AdminREST sia registrato correttamente nel plugin.\n\n";
}

// 5. Test chiamata API Agenda
echo "\n--- TEST CHIAMATA API AGENDA ---\n";

if ($currentUser->ID && (current_user_can('manage_fp_reservations') || current_user_can('manage_options'))) {
    $testDate = date('Y-m-d');
    $endpoint = rest_url('fp-resv/v1/agenda');
    
    echo sprintf("Testing endpoint: %s?date=%s\n", $endpoint, $testDate);
    
    try {
        $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
        $request->set_query_params(['date' => $testDate]);
        
        $response = rest_do_request($request);
        $data = $response->get_data();
        
        if (is_wp_error($data)) {
            echo sprintf("❌ Errore API: %s\n", $data->get_error_message());
        } elseif ($response->get_status() !== 200) {
            echo sprintf("❌ Status HTTP: %d\n", $response->get_status());
            echo sprintf("   Risposta: %s\n", json_encode($data));
        } else {
            echo "✅ API risponde correttamente\n";
            $count = is_array($data) ? count($data) : 0;
            echo sprintf("   Prenotazioni trovate: %d\n", $count);
        }
    } catch (Exception $e) {
        echo sprintf("❌ Errore durante la chiamata: %s\n", $e->getMessage());
    }
} else {
    echo "⏭️  Skip (utente non ha permessi per testare API)\n";
}

// 6. Verifica errori PHP
echo "\n--- VERIFICA ERRORI PHP RECENTI ---\n";

$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo sprintf("Log errori: %s\n", $errorLog);
    
    // Leggi ultime 20 righe
    $lines = [];
    $handle = fopen($errorLog, 'r');
    if ($handle) {
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line && (stripos($line, 'fp-resv') !== false || stripos($line, 'fp_resv') !== false)) {
                $lines[] = trim($line);
            }
        }
        fclose($handle);
        
        if (count($lines) > 0) {
            echo "\nErrori PHP relativi al plugin (ultimi 10):\n";
            $lastLines = array_slice($lines, -10);
            foreach ($lastLines as $line) {
                echo "   " . $line . "\n";
            }
        } else {
            echo "✅ Nessun errore PHP recente trovato per questo plugin\n";
        }
    }
} else {
    echo "⏭️  File di log PHP non configurato o non accessibile\n";
}

// 7. Verifica stato database
echo "\n--- VERIFICA TABELLE DATABASE ---\n";

global $wpdb;
$requiredTables = [
    'fp_reservations' => 'Prenotazioni',
    'fp_customers' => 'Clienti',
];

$missingTables = [];
foreach ($requiredTables as $table => $description) {
    $fullTableName = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$fullTableName'") === $fullTableName;
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $fullTableName");
        echo sprintf("✅ %s (%s): %d record\n", $description, $fullTableName, $count);
    } else {
        echo sprintf("❌ %s (%s): TABELLA MANCANTE\n", $description, $fullTableName);
        $missingTables[] = $table;
    }
}

if (count($missingTables) > 0) {
    echo "\n⚠️  PROBLEMA: Tabelle database mancanti!\n";
    echo "   Esegui le migrazioni del plugin per creare le tabelle.\n\n";
}

// 8. Riepilogo finale
echo "\n\n=== RIEPILOGO E RACCOMANDAZIONI ===\n\n";

$criticalIssues = [];

if (count($missingFiles) > 0) {
    $criticalIssues[] = sprintf("File mancanti: %d", count($missingFiles));
}

if (!$hasPermission) {
    $criticalIssues[] = "Permessi utente insufficienti";
}

if (!$menuFound) {
    $criticalIssues[] = "Menu Agenda non registrato";
}

if (count($missingEndpoints) > 0) {
    $criticalIssues[] = sprintf("Endpoint REST mancanti: %d", count($missingEndpoints));
}

if (count($missingTables) > 0) {
    $criticalIssues[] = sprintf("Tabelle database mancanti: %d", count($missingTables));
}

if (count($criticalIssues) > 0) {
    echo "❌ PROBLEMI CRITICI RILEVATI:\n";
    foreach ($criticalIssues as $i => $issue) {
        echo sprintf("   %d. %s\n", $i + 1, $issue);
    }
    
    echo "\n📋 AZIONI CONSIGLIATE:\n";
    
    if (count($missingFiles) > 0 || count($missingEndpoints) > 0) {
        echo "   1. Reinstalla il plugin\n";
        echo "      - Disattiva il plugin\n";
        echo "      - Elimina la cartella del plugin\n";
        echo "      - Reinstalla da zero\n\n";
    }
    
    if (count($missingTables) > 0) {
        echo "   2. Esegui le migrazioni database:\n";
        echo "      wp eval 'do_action(\"fp_resv_run_migrations\");'\n\n";
    }
    
    if (!$hasPermission) {
        echo "   3. Assegna i permessi corretti all'utente:\n";
        echo "      - Vai su Utenti → Profilo\n";
        echo "      - Verifica che l'utente sia Amministratore\n";
        echo "      - Oppure assegna il ruolo personalizzato con manage_fp_reservations\n\n";
    }
    
    echo "   4. Controlla la Console JavaScript del browser:\n";
    echo "      - Apri la pagina Agenda\n";
    echo "      - Premi F12 per aprire DevTools\n";
    echo "      - Vai su Console\n";
    echo "      - Cerca errori in rosso\n";
    echo "      - Condividi gli errori trovati\n\n";
    
    echo "   5. Controlla la tab Network del browser:\n";
    echo "      - Apri DevTools (F12)\n";
    echo "      - Vai su Network\n";
    echo "      - Ricarica la pagina\n";
    echo "      - Cerca richieste fallite (status 4xx o 5xx)\n";
    echo "      - Verifica che agenda-app.js e admin-agenda.css vengano caricati\n\n";
} else {
    echo "✅ Nessun problema critico rilevato!\n\n";
    
    echo "Se l'Agenda ancora non funziona, il problema è probabilmente nel browser:\n\n";
    
    echo "📋 COSA FARE:\n";
    echo "   1. Apri la Console JavaScript del browser:\n";
    echo "      - Vai su WordPress Admin → FP Reservations → Agenda\n";
    echo "      - Premi F12 per aprire DevTools\n";
    echo "      - Vai sulla tab Console\n";
    echo "      - Cerca messaggi di errore in rosso\n";
    echo "      - Copia gli errori e condividili\n\n";
    
    echo "   2. Controlla il Network del browser:\n";
    echo "      - Apri DevTools (F12)\n";
    echo "      - Vai sulla tab Network\n";
    echo "      - Ricarica la pagina Agenda\n";
    echo "      - Verifica che tutti i file JS/CSS si carichino (status 200)\n";
    echo "      - Verifica che le chiamate API a /wp-json/fp-resv/v1/agenda rispondano\n\n";
    
    echo "   3. Controlla cosa vedi sulla pagina:\n";
    echo "      - La pagina è completamente bianca?\n";
    echo "      - Vedi solo l'header ma nessun contenuto?\n";
    echo "      - Vedi il messaggio 'Nessuna prenotazione'?\n";
    echo "      - Vedi un errore specifico?\n\n";
    
    echo "   4. Prova in incognito:\n";
    echo "      - Apri una finestra in incognito\n";
    echo "      - Accedi come admin\n";
    echo "      - Vai sull'Agenda\n";
    echo "      - Se funziona, il problema è nella cache del browser\n\n";
    
    echo "   5. Svuota la cache:\n";
    echo "      - Cache del browser (Ctrl+Shift+Del)\n";
    echo "      - Cache di WordPress (se usi plugin di caching)\n";
    echo "      - CDN cache (se ne usi uno)\n\n";
}

echo "=== FINE DEBUG ===\n";
