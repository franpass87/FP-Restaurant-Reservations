<?php
/**
 * Script di test per diagnostica agenda
 * 
 * Esegui questo file da WordPress per verificare:
 * - Prenotazioni nel database
 * - Endpoint REST API
 * - Permessi utente
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

if (!defined('ABSPATH')) {
    die('Questo script deve essere eseguito da WordPress');
}

echo "=== TEST DIAGNOSTICO AGENDA ===\n\n";

// 1. Verifica database
echo "1. CONTROLLO DATABASE\n";
echo str_repeat('-', 50) . "\n";

global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';
$customersTable = $wpdb->prefix . 'fp_customers';

// Controlla se le tabelle esistono
$tableExists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
$customersExists = $wpdb->get_var("SHOW TABLES LIKE '$customersTable'");

echo "Tabella prenotazioni ({$table}): ";
echo $tableExists ? "✓ Esiste\n" : "✗ NON ESISTE\n";

echo "Tabella clienti ({$customersTable}): ";
echo $customersExists ? "✓ Esiste\n" : "✗ NON ESISTE\n";

if (!$tableExists) {
    die("\n✗ ERRORE: La tabella delle prenotazioni non esiste!\n");
}

// Conta prenotazioni
$totalCount = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "\nTotale prenotazioni nel database: " . ($totalCount ?: 0) . "\n";

if ($totalCount > 0) {
    // Mostra ultime 5 prenotazioni
    $sql = "SELECT r.id, r.date, r.time, r.party, r.status, c.first_name, c.last_name 
            FROM $table r 
            LEFT JOIN $customersTable c ON r.customer_id = c.id 
            ORDER BY r.date DESC, r.time DESC 
            LIMIT 5";
    
    $reservations = $wpdb->get_results($sql);
    
    echo "\nUltime 5 prenotazioni:\n";
    foreach ($reservations as $r) {
        $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
        $name = $name ?: '[Nessun nome]';
        echo sprintf(
            "  - ID %d: %s alle %s - %d persone - %s - %s\n",
            $r->id,
            $r->date,
            substr($r->time, 0, 5),
            $r->party,
            $name,
            $r->status
        );
    }
    
    // Prenotazioni oggi
    $today = current_time('Y-m-d');
    $todayCount = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE date = %s",
        $today
    ));
    echo "\nPrenotazioni oggi ({$today}): " . ($todayCount ?: 0) . "\n";
    
    // Prenotazioni future
    $futureCount = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE date >= %s",
        $today
    ));
    echo "Prenotazioni future (da oggi): " . ($futureCount ?: 0) . "\n";
} else {
    echo "\n⚠ Nessuna prenotazione nel database!\n";
    echo "Questo è il motivo per cui l'agenda è vuota.\n";
}

echo "\n";

// 2. Verifica permessi utente corrente
echo "2. CONTROLLO PERMESSI UTENTE\n";
echo str_repeat('-', 50) . "\n";

$currentUser = wp_get_current_user();
if ($currentUser->ID === 0) {
    echo "✗ Nessun utente autenticato\n";
} else {
    echo "Utente corrente: " . $currentUser->user_login . " (ID: {$currentUser->ID})\n";
    echo "Ruoli: " . implode(', ', $currentUser->roles) . "\n";
    
    $hasManageReservations = current_user_can('manage_fp_reservations');
    $hasManageOptions = current_user_can('manage_options');
    
    echo "\nPermessi:\n";
    echo "  - manage_fp_reservations: " . ($hasManageReservations ? "✓ SI" : "✗ NO") . "\n";
    echo "  - manage_options (admin): " . ($hasManageOptions ? "✓ SI" : "✗ NO") . "\n";
    
    if (!$hasManageReservations && !$hasManageOptions) {
        echo "\n⚠ L'utente NON ha permessi per accedere all'agenda!\n";
    }
}

echo "\n";

// 3. Test endpoint REST API
echo "3. TEST ENDPOINT REST API\n";
echo str_repeat('-', 50) . "\n";

$restUrl = rest_url('fp-resv/v1/agenda');
echo "URL endpoint: " . $restUrl . "\n";

// Simula richiesta REST API interna
$today = current_time('Y-m-d');
$request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
$request->set_query_params([
    'date' => $today,
    'range' => 'day',
]);

echo "Test con data: " . $today . "\n";

// Verifica che l'endpoint sia registrato
$server = rest_get_server();
$routes = $server->get_routes();

if (!isset($routes['/fp-resv/v1/agenda'])) {
    echo "✗ ERRORE: Endpoint /fp-resv/v1/agenda NON registrato!\n";
    echo "\nEndpoint disponibili:\n";
    foreach ($routes as $route => $handlers) {
        if (strpos($route, 'fp-resv') !== false) {
            echo "  - " . $route . "\n";
        }
    }
} else {
    echo "✓ Endpoint registrato correttamente\n";
    
    // Prova a eseguire la richiesta
    try {
        $response = $server->dispatch($request);
        $data = $response->get_data();
        
        if ($response->is_error()) {
            echo "✗ Errore nella risposta:\n";
            echo "  Codice: " . $response->get_status() . "\n";
            echo "  Messaggio: " . print_r($data, true) . "\n";
        } else {
            echo "✓ Risposta ricevuta con successo\n";
            echo "  Status code: " . $response->get_status() . "\n";
            
            if (is_array($data)) {
                if (isset($data['reservations'])) {
                    $count = count($data['reservations']);
                    echo "  Prenotazioni nella risposta: " . $count . "\n";
                    
                    if ($count > 0) {
                        echo "\n  Prima prenotazione:\n";
                        $first = $data['reservations'][0];
                        echo "    - ID: " . ($first['id'] ?? 'N/A') . "\n";
                        echo "    - Data: " . ($first['date'] ?? 'N/A') . "\n";
                        echo "    - Ora: " . ($first['time'] ?? 'N/A') . "\n";
                        echo "    - Coperti: " . ($first['party'] ?? 'N/A') . "\n";
                        echo "    - Cliente: " . ($first['customer']['first_name'] ?? 'N/A') . "\n";
                    }
                }
                
                if (isset($data['meta'])) {
                    echo "\n  Meta informazioni:\n";
                    echo "    - Range: " . ($data['meta']['range'] ?? 'N/A') . "\n";
                    echo "    - Data inizio: " . ($data['meta']['start_date'] ?? 'N/A') . "\n";
                    echo "    - Data fine: " . ($data['meta']['end_date'] ?? 'N/A') . "\n";
                }
                
                if (isset($data['stats'])) {
                    echo "\n  Statistiche:\n";
                    echo "    - Totale prenotazioni: " . ($data['stats']['total_reservations'] ?? 'N/A') . "\n";
                    echo "    - Totale coperti: " . ($data['stats']['total_guests'] ?? 'N/A') . "\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "✗ Eccezione durante l'esecuzione:\n";
        echo "  " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 4. Verifica configurazione plugin
echo "4. CONFIGURAZIONE PLUGIN\n";
echo str_repeat('-', 50) . "\n";

$pluginFile = __DIR__ . '/fp-restaurant-reservations.php';
echo "File plugin: " . ($pluginFile ? "✓ Trovato" : "✗ Non trovato") . "\n";

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
echo "Autoload Composer: " . (file_exists($vendorAutoload) ? "✓ Presente" : "✗ MANCANTE") . "\n";

$agendaJs = __DIR__ . '/assets/js/admin/agenda-app.js';
echo "File agenda-app.js: " . (file_exists($agendaJs) ? "✓ Presente (" . filesize($agendaJs) . " bytes)" : "✗ MANCANTE") . "\n";

$agendaCss = __DIR__ . '/assets/css/admin-agenda.css';
echo "File admin-agenda.css: " . (file_exists($agendaCss) ? "✓ Presente (" . filesize($agendaCss) . " bytes)" : "✗ MANCANTE") . "\n";

echo "\n";

// 5. Conclusioni
echo "5. CONCLUSIONI\n";
echo str_repeat('-', 50) . "\n";

if ($totalCount == 0) {
    echo "⚠ L'AGENDA È VUOTA PERCHÉ NON CI SONO PRENOTAZIONI NEL DATABASE\n\n";
    echo "Soluzioni:\n";
    echo "1. Crea manualmente una prenotazione dall'agenda\n";
    echo "2. Importa prenotazioni esistenti\n";
    echo "3. Verifica che il form di prenotazione frontend funzioni\n";
} elseif (!isset($routes['/fp-resv/v1/agenda'])) {
    echo "✗ L'ENDPOINT REST API NON È REGISTRATO\n\n";
    echo "Soluzioni:\n";
    echo "1. Verifica che il plugin sia attivo\n";
    echo "2. Flush permalink (Impostazioni > Permalink > Salva)\n";
    echo "3. Controlla vendor/autoload.php esista\n";
} elseif (!$hasManageReservations && !$hasManageOptions) {
    echo "✗ L'UTENTE NON HA I PERMESSI NECESSARI\n\n";
    echo "Soluzioni:\n";
    echo "1. Accedi come amministratore\n";
    echo "2. Assegna la capability manage_fp_reservations all'utente\n";
} else {
    echo "✓ TUTTO SEMBRA CONFIGURATO CORRETTAMENTE\n\n";
    echo "Se l'agenda non mostra prenotazioni:\n";
    echo "1. Apri la console browser (F12)\n";
    echo "2. Cerca errori JavaScript o 403/404\n";
    echo "3. Verifica che fpResvAgendaSettings sia definito\n";
    echo "4. Controlla il nonce nella richiesta AJAX\n";
}

echo "\n=== FINE TEST ===\n";
