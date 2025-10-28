<?php
/**
 * Script di test per verificare le prenotazioni nel database
 * e debuggare il problema con findAgendaRange()
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

if (!defined('ABSPATH')) {
    die('WordPress non caricato correttamente');
}

echo "=== TEST PRENOTAZIONI NEL DATABASE ===\n\n";

global $wpdb;

// 1. Verifica tabelle
$reservationsTable = $wpdb->prefix . 'fp_reservations';
$customersTable = $wpdb->prefix . 'fp_customers';

echo "1. VERIFICA TABELLE\n";
echo "   Tabella prenotazioni: $reservationsTable\n";
echo "   Tabella clienti: $customersTable\n\n";

// 2. Conta totale prenotazioni
$totalCount = $wpdb->get_var("SELECT COUNT(*) FROM $reservationsTable");
echo "2. TOTALE PRENOTAZIONI\n";
echo "   Trovate: $totalCount prenotazioni\n\n";

if ($totalCount == 0) {
    echo "❌ PROBLEMA: Non ci sono prenotazioni nel database!\n";
    exit;
}

// 3. Mostra alcune prenotazioni
echo "3. ULTIME 5 PRENOTAZIONI\n";
$recentReservations = $wpdb->get_results(
    "SELECT id, date, time, party, status, customer_id, created_at 
     FROM $reservationsTable 
     ORDER BY date DESC, time DESC 
     LIMIT 5",
    ARRAY_A
);

foreach ($recentReservations as $resv) {
    echo "   ID: {$resv['id']}, Data: {$resv['date']}, Ora: {$resv['time']}, " .
         "Coperti: {$resv['party']}, Status: {$resv['status']}\n";
}
echo "\n";

// 4. Prenotazioni per data specifica (oggi)
$today = gmdate('Y-m-d');
echo "4. PRENOTAZIONI PER OGGI ($today)\n";
$todayCount = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $reservationsTable WHERE date = %s",
        $today
    )
);
echo "   Trovate: $todayCount prenotazioni\n\n";

// 5. Range di date nelle prenotazioni
echo "5. RANGE DATE PRENOTAZIONI\n";
$dateRange = $wpdb->get_row(
    "SELECT MIN(date) as min_date, MAX(date) as max_date FROM $reservationsTable",
    ARRAY_A
);
echo "   Prima prenotazione: {$dateRange['min_date']}\n";
echo "   Ultima prenotazione: {$dateRange['max_date']}\n\n";

// 6. Test query findAgendaRange
echo "6. TEST QUERY findAgendaRange()\n";
$startDate = gmdate('Y-m-d');
$endDate = gmdate('Y-m-d');

echo "   Cercando prenotazioni tra $startDate e $endDate...\n";

$sql = "SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang 
        FROM $reservationsTable r 
        LEFT JOIN $customersTable c ON r.customer_id = c.id 
        WHERE r.date BETWEEN %s AND %s 
        ORDER BY r.date ASC, r.time ASC";

$preparedSql = $wpdb->prepare($sql, $startDate, $endDate);
echo "   Query SQL:\n   $preparedSql\n\n";

$rows = $wpdb->get_results($preparedSql, ARRAY_A);

if ($wpdb->last_error) {
    echo "   ❌ ERRORE SQL: {$wpdb->last_error}\n\n";
}

echo "   Risultato: " . count($rows) . " prenotazioni trovate\n";

if (count($rows) > 0) {
    echo "   ✓ Query funzionante!\n";
    echo "\n   Prime prenotazioni trovate:\n";
    foreach (array_slice($rows, 0, 3) as $row) {
        echo "      ID: {$row['id']}, Data: {$row['date']}, Ora: {$row['time']}, ";
        echo "Cliente: {$row['first_name']} {$row['last_name']}\n";
    }
} else {
    echo "   ❌ PROBLEMA: Query non ritorna risultati!\n";
    echo "   Provo con un range più ampio...\n\n";
    
    // 7. Test con range più ampio
    $startWide = gmdate('Y-m-d', strtotime('-30 days'));
    $endWide = gmdate('Y-m-d', strtotime('+30 days'));
    
    echo "7. TEST CON RANGE AMPIO ($startWide to $endWide)\n";
    $wideRows = $wpdb->get_results(
        $wpdb->prepare($sql, $startWide, $endWide),
        ARRAY_A
    );
    
    echo "   Risultato: " . count($wideRows) . " prenotazioni trovate\n";
    
    if (count($wideRows) > 0) {
        echo "   ✓ Con range più ampio funziona!\n";
        echo "   Questo significa che le prenotazioni non sono per oggi.\n\n";
        echo "   Date delle prenotazioni trovate:\n";
        $dates = array_unique(array_column($wideRows, 'date'));
        sort($dates);
        foreach (array_slice($dates, 0, 10) as $date) {
            $count = count(array_filter($wideRows, function($r) use ($date) {
                return $r['date'] === $date;
            }));
            echo "      $date: $count prenotazioni\n";
        }
    }
}

echo "\n=== TEST COMPLETATO ===\n";
