#!/usr/bin/env php
<?php
/**
 * Diagnostica: Perché l'agenda mostra "Nessuna prenotazione" quando ci sono dati
 * 
 * Questo script verifica:
 * 1. Se ci sono prenotazioni nel database
 * 2. Se l'endpoint API funziona correttamente
 * 3. Problemi di permessi o configurazione
 */

// Carica WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
];

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die("❌ Impossibile trovare wp-load.php\n");
}

echo "==========================================\n";
echo "DIAGNOSI AGENDA - NESSUNA PRENOTAZIONE\n";
echo "==========================================\n\n";

// 1. Verifica prenotazioni nel database
echo "📊 STEP 1: Verifica database\n";
echo "----------------------------------------\n";

global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';

// Conta tutte le prenotazioni
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
echo "✓ Totale prenotazioni nel database: {$total}\n";

if ($total == 0) {
    echo "⚠️  Non ci sono prenotazioni nel database!\n\n";
    exit(0);
}

// Prenotazioni recenti (ultimi 7 giorni)
$date_from = date('Y-m-d', strtotime('-7 days'));
$recent = $wpdb->get_results($wpdb->prepare(
    "SELECT id, date, time, status, party, customer_first_name, customer_last_name, created_at
     FROM {$table}
     WHERE date >= %s
     ORDER BY date DESC, time DESC
     LIMIT 10",
    $date_from
));

echo "\n📅 Prenotazioni recenti (ultimi 7 giorni): " . count($recent) . "\n";
if (!empty($recent)) {
    echo "\nID   | Data       | Ora   | Stato     | Coperti | Cliente\n";
    echo "-----+------------+-------+-----------+---------+------------------------\n";
    foreach ($recent as $r) {
        $name = trim($r->customer_first_name . ' ' . $r->customer_last_name) ?: 'N/D';
        printf(
            "%-4d | %s | %s | %-9s | %-7d | %s\n",
            $r->id,
            $r->date,
            substr($r->time, 0, 5),
            $r->status,
            $r->party,
            $name
        );
    }
}

// Prenotazioni di ieri
echo "\n🕐 Prenotazioni di IERI:\n";
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_bookings = $wpdb->get_results($wpdb->prepare(
    "SELECT id, time, status, party, customer_first_name, customer_last_name
     FROM {$table}
     WHERE date = %s
     ORDER BY time",
    $yesterday
));

if (empty($yesterday_bookings)) {
    echo "⚠️  Nessuna prenotazione trovata per ieri ({$yesterday})\n";
} else {
    echo "✓ Trovate " . count($yesterday_bookings) . " prenotazioni per {$yesterday}:\n";
    foreach ($yesterday_bookings as $b) {
        $name = trim($b->customer_first_name . ' ' . $b->customer_last_name) ?: 'N/D';
        echo "  - ID {$b->id}: {$b->time} - {$name} ({$b->party} coperti) - {$b->status}\n";
    }
}

// Prenotazioni di oggi
echo "\n📆 Prenotazioni di OGGI:\n";
$today = date('Y-m-d');
$today_bookings = $wpdb->get_results($wpdb->prepare(
    "SELECT id, time, status, party, customer_first_name, customer_last_name
     FROM {$table}
     WHERE date = %s
     ORDER BY time",
    $today
));

if (empty($today_bookings)) {
    echo "⚠️  Nessuna prenotazione trovata per oggi ({$today})\n";
} else {
    echo "✓ Trovate " . count($today_bookings) . " prenotazioni per {$today}:\n";
    foreach ($today_bookings as $b) {
        $name = trim($b->customer_first_name . ' ' . $b->customer_last_name) ?: 'N/D';
        echo "  - ID {$b->id}: {$b->time} - {$name} ({$b->party} coperti) - {$b->status}\n";
    }
}

// 2. Testa l'endpoint API
echo "\n\n🔌 STEP 2: Test endpoint API\n";
echo "----------------------------------------\n";

// Verifica che la classe repository esista
if (!class_exists('FP_Reservations\\Domain\\Reservations\\Repository')) {
    echo "❌ Classe Repository non trovata!\n";
    exit(1);
}

try {
    $repo = new \FP_Reservations\Domain\Reservations\Repository($wpdb);
    
    // Test: get per oggi
    echo "Test 1: Caricamento prenotazioni per oggi ({$today})...\n";
    $results_today = $repo->findByDateRange($today, $today);
    echo "✓ Risultato: " . count($results_today) . " prenotazioni\n";
    
    // Test: get per ieri
    echo "\nTest 2: Caricamento prenotazioni per ieri ({$yesterday})...\n";
    $results_yesterday = $repo->findByDateRange($yesterday, $yesterday);
    echo "✓ Risultato: " . count($results_yesterday) . " prenotazioni\n";
    
    if (count($results_yesterday) > 0) {
        echo "\nDettaglio prenotazioni di ieri caricate dal repository:\n";
        foreach ($results_yesterday as $r) {
            echo "  - ID {$r['id']}: ";
            echo isset($r['slot_start']) ? $r['slot_start'] : $r['date'] . ' ' . $r['time'];
            echo " - {$r['customer']['first_name']} {$r['customer']['last_name']}";
            echo " ({$r['party']} coperti)\n";
        }
    }
    
    // Test: range ultimi 7 giorni
    echo "\nTest 3: Caricamento prenotazioni ultimi 7 giorni...\n";
    $results_week = $repo->findByDateRange($date_from, $today);
    echo "✓ Risultato: " . count($results_week) . " prenotazioni\n";
    
} catch (\Exception $e) {
    echo "❌ Errore durante test repository: " . $e->getMessage() . "\n";
}

// 3. Verifica configurazione API REST
echo "\n\n⚙️  STEP 3: Verifica configurazione API REST\n";
echo "----------------------------------------\n";

$rest_url = rest_url('fp-resv/v1/agenda');
echo "Endpoint agenda: {$rest_url}\n";

// Verifica se l'endpoint è registrato
$routes = rest_get_server()->get_routes();
if (isset($routes['/fp-resv/v1/agenda'])) {
    echo "✓ Endpoint /fp-resv/v1/agenda registrato\n";
    
    $route = $routes['/fp-resv/v1/agenda'][0];
    echo "\nMetodi permessi: " . implode(', ', array_keys($route['methods'])) . "\n";
    
    if (isset($route['permission_callback'])) {
        echo "Permission callback: presente\n";
    }
} else {
    echo "❌ Endpoint /fp-resv/v1/agenda NON registrato!\n";
}

// 4. Verifica permessi utente corrente
echo "\n\n👤 STEP 4: Verifica permessi utente\n";
echo "----------------------------------------\n";

if (!is_user_logged_in()) {
    echo "⚠️  Nessun utente loggato (esecuzione da CLI)\n";
    echo "ℹ️  I test permessi vengono saltati\n";
} else {
    $current_user = wp_get_current_user();
    echo "Utente corrente: {$current_user->user_login} (ID: {$current_user->ID})\n";
    echo "Ruoli: " . implode(', ', $current_user->roles) . "\n";
    
    $required_caps = ['manage_fp_reservations', 'edit_posts'];
    echo "\nPermessi richiesti:\n";
    foreach ($required_caps as $cap) {
        $has = current_user_can($cap);
        echo "  " . ($has ? '✓' : '❌') . " {$cap}\n";
    }
}

// 5. Verifica nonce e settings JavaScript
echo "\n\n🔐 STEP 5: Verifica configurazione JavaScript\n";
echo "----------------------------------------\n";

// Simula caricamento pagina admin
set_current_screen('toplevel_page_fp-resv-agenda');

// Verifica se gli scripts sono registrati
global $wp_scripts;
if (isset($wp_scripts->registered['fp-resv-agenda'])) {
    echo "✓ Script 'fp-resv-agenda' registrato\n";
    
    $script = $wp_scripts->registered['fp-resv-agenda'];
    if (!empty($script->extra['data'])) {
        echo "\nDati localizzati (preview):\n";
        $data = $script->extra['data'];
        echo substr($data, 0, 300) . "...\n";
    }
} else {
    echo "⚠️  Script 'fp-resv-agenda' non trovato\n";
}

// 6. Suggerimenti
echo "\n\n💡 SUGGERIMENTI\n";
echo "==========================================\n";

if ($total == 0) {
    echo "❌ Non ci sono prenotazioni nel database.\n";
    echo "   Crea alcune prenotazioni di test.\n";
} elseif (count($results_week ?? []) == 0) {
    echo "⚠️  Il repository non restituisce risultati.\n";
    echo "   Possibile problema:\n";
    echo "   - Formato dati non valido\n";
    echo "   - Query con filtri troppo restrittivi\n";
} elseif (count($today_bookings) == 0 && count($yesterday_bookings) > 0) {
    echo "ℹ️  Le prenotazioni sono di ieri, non di oggi.\n";
    echo "   Assicurati di selezionare la data corretta nell'agenda.\n";
    echo "   Controlla che il date picker sia impostato su: {$yesterday}\n";
} else {
    echo "✓ I dati sembrano corretti.\n";
    echo "  Possibili cause del problema:\n";
    echo "  1. JavaScript non carica correttamente\n";
    echo "  2. Errore nonce/autenticazione\n";
    echo "  3. Filtri applicati (es. servizio pranzo/cena)\n";
    echo "  4. Cache del browser\n\n";
    echo "  Prova a:\n";
    echo "  - Aprire DevTools e controllare Console/Network\n";
    echo "  - Fare hard refresh (Ctrl+Shift+R)\n";
    echo "  - Verificare che la data selezionata sia corretta\n";
}

echo "\n✅ Diagnosi completata!\n\n";
