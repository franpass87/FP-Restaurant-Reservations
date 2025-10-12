<?php
/**
 * Test diretto SQL per verificare che le prenotazioni esistano
 * Questo bypassa tutto il sistema REST e va direttamente al database
 */

// Carica WordPress
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (!file_exists($wpLoadPath)) {
    die("ERRORE: Impossibile trovare wp-load.php. Assicurati che il plugin sia nella cartella wp-content/plugins/\n");
}

require_once $wpLoadPath;

if (!defined('ABSPATH')) {
    die('Questo script deve essere eseguito da WordPress');
}

echo "=== TEST DIRETTO DATABASE ===\n\n";

global $wpdb;

// 1. Verifica tabelle esistano
$reservationsTable = $wpdb->prefix . 'fp_reservations';
$customersTable = $wpdb->prefix . 'fp_customers';

echo "1. VERIFICA TABELLE\n";
echo str_repeat('-', 50) . "\n";

$tableExists = $wpdb->get_var("SHOW TABLES LIKE '$reservationsTable'");
echo "Tabella prenotazioni: " . ($tableExists ? "✓ Esiste" : "✗ NON ESISTE") . "\n";

$customersExists = $wpdb->get_var("SHOW TABLES LIKE '$customersTable'");
echo "Tabella clienti: " . ($customersExists ? "✓ Esiste" : "✗ NON ESISTE") . "\n\n";

if (!$tableExists) {
    die("ERRORE: La tabella delle prenotazioni non esiste!\n");
}

// 2. Conta prenotazioni totali
echo "2. CONTEGGIO PRENOTAZIONI\n";
echo str_repeat('-', 50) . "\n";

$totalCount = $wpdb->get_var("SELECT COUNT(*) FROM $reservationsTable");
echo "Totale prenotazioni: " . ($totalCount ?: 0) . "\n\n";

if ($totalCount == 0) {
    die("Il database è vuoto! Non ci sono prenotazioni da testare.\n");
}

// 3. Test query dell'agenda
echo "3. TEST QUERY AGENDA\n";
echo str_repeat('-', 50) . "\n";

$today = current_time('Y-m-d');
echo "Data di test: " . $today . "\n";

// Query esatta usata da findAgendaRange
$sql = "SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang 
        FROM $reservationsTable r 
        LEFT JOIN $customersTable c ON r.customer_id = c.id 
        WHERE r.date BETWEEN %s AND %s 
        ORDER BY r.date ASC, r.time ASC";

$preparedSql = $wpdb->prepare($sql, $today, $today);
echo "\nQuery preparata:\n" . $preparedSql . "\n\n";

$results = $wpdb->get_results($preparedSql, ARRAY_A);

echo "Risultati trovati: " . (is_array($results) ? count($results) : 'ERRORE') . "\n";

if ($wpdb->last_error) {
    echo "ERRORE SQL: " . $wpdb->last_error . "\n";
}

if (is_array($results) && count($results) > 0) {
    echo "\n✓ Prenotazioni trovate per oggi!\n\n";
    
    foreach ($results as $i => $row) {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $name = $name ?: ($row['email'] ?? 'N/A');
        
        echo sprintf(
            "%d. ID %s | %s %s | %d coperti | %s | %s\n",
            $i + 1,
            $row['id'] ?? 'N/A',
            $row['date'] ?? 'N/A',
            isset($row['time']) ? substr($row['time'], 0, 5) : 'N/A',
            $row['party'] ?? 0,
            $name,
            $row['status'] ?? 'N/A'
        );
    }
} else {
    echo "\n⚠ Nessuna prenotazione per oggi ($today)\n";
    
    // Cerca prenotazioni future
    echo "\nCerco prenotazioni future...\n";
    $futureSql = "SELECT date, COUNT(*) as count 
                  FROM $reservationsTable 
                  WHERE date >= %s 
                  GROUP BY date 
                  ORDER BY date ASC 
                  LIMIT 10";
    
    $futureDates = $wpdb->get_results($wpdb->prepare($futureSql, $today), ARRAY_A);
    
    if (is_array($futureDates) && count($futureDates) > 0) {
        echo "\nPrenotazioni future trovate:\n";
        foreach ($futureDates as $dateRow) {
            echo "  - " . $dateRow['date'] . ": " . $dateRow['count'] . " prenotazioni\n";
        }
        
        // Test con la prima data futura
        $firstFutureDate = $futureDates[0]['date'];
        echo "\nTESTO CON DATA FUTURA: " . $firstFutureDate . "\n";
        
        $futureResults = $wpdb->get_results(
            $wpdb->prepare($sql, $firstFutureDate, $firstFutureDate),
            ARRAY_A
        );
        
        echo "Risultati per " . $firstFutureDate . ": " . count($futureResults) . "\n\n";
        
        if (count($futureResults) > 0) {
            $row = $futureResults[0];
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            echo "Prima prenotazione:\n";
            echo "  ID: " . ($row['id'] ?? 'N/A') . "\n";
            echo "  Data: " . ($row['date'] ?? 'N/A') . "\n";
            echo "  Ora: " . (isset($row['time']) ? substr($row['time'], 0, 5) : 'N/A') . "\n";
            echo "  Cliente: " . ($name ?: 'N/A') . "\n";
            echo "  Coperti: " . ($row['party'] ?? 'N/A') . "\n";
            echo "  Stato: " . ($row['status'] ?? 'N/A') . "\n";
        }
    } else {
        echo "⚠ Nessuna prenotazione futura trovata!\n";
    }
}

echo "\n=== CONCLUSIONE ===\n";
if (is_array($results) && count($results) > 0) {
    echo "✓ La query SQL funziona e trova prenotazioni!\n";
    echo "✓ Il problema NON è nel database.\n";
    echo "→ Controlla i log di WordPress per vedere dove si perdono i dati.\n";
} else {
    echo "⚠ La query non trova prenotazioni per oggi.\n";
    echo "→ Prova l'agenda con una data che ha prenotazioni.\n";
    echo "→ Oppure crea una prenotazione per oggi per testare.\n";
}

echo "\n";
