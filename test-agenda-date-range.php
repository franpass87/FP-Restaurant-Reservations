<?php
/**
 * Test specifico per debuggare il problema con la data 2025-10-12
 * che viene passata alla query ma non ritorna prenotazioni
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

if (!defined('ABSPATH')) {
    die('WordPress non caricato correttamente');
}

echo "=== DEBUG DATA 2025-10-12 ===\n\n";

global $wpdb;
$reservationsTable = $wpdb->prefix . 'fp_reservations';
$customersTable = $wpdb->prefix . 'fp_customers';

// 1. Data richiesta dall'agenda
$requestedDate = '2025-10-12';
echo "1. DATA RICHIESTA: $requestedDate\n\n";

// 2. Verifica formato data
echo "2. VERIFICA FORMATO DATA\n";
$dateTime = DateTime::createFromFormat('Y-m-d', $requestedDate);
if ($dateTime) {
    echo "   ✓ Formato valido\n";
    echo "   Anno: " . $dateTime->format('Y') . "\n";
    echo "   Mese: " . $dateTime->format('m') . "\n";
    echo "   Giorno: " . $dateTime->format('d') . "\n";
} else {
    echo "   ❌ Formato non valido!\n";
}
echo "\n";

// 3. Cerca prenotazioni esatte per questa data
echo "3. RICERCA ESATTA PER DATA\n";
$exactQuery = $wpdb->prepare(
    "SELECT id, date, time, party, status FROM $reservationsTable WHERE date = %s",
    $requestedDate
);
echo "   Query: $exactQuery\n";
$exactResults = $wpdb->get_results($exactQuery, ARRAY_A);
echo "   Risultati: " . count($exactResults) . " prenotazioni\n";

if (count($exactResults) > 0) {
    echo "   ✓ Trovate prenotazioni per questa data:\n";
    foreach ($exactResults as $resv) {
        echo "      ID: {$resv['id']}, Ora: {$resv['time']}, Coperti: {$resv['party']}, Status: {$resv['status']}\n";
    }
} else {
    echo "   ⚠ Nessuna prenotazione per questa data\n";
}
echo "\n";

// 4. Cerca con BETWEEN (come fa findAgendaRange)
echo "4. RICERCA CON BETWEEN (come findAgendaRange)\n";
$betweenQuery = $wpdb->prepare(
    "SELECT id, date, time, party, status FROM $reservationsTable WHERE date BETWEEN %s AND %s",
    $requestedDate,
    $requestedDate
);
echo "   Query: $betweenQuery\n";
$betweenResults = $wpdb->get_results($betweenQuery, ARRAY_A);
echo "   Risultati: " . count($betweenResults) . " prenotazioni\n\n";

// 5. Query completa come in findAgendaRange
echo "5. QUERY COMPLETA (con JOIN)\n";
$fullQuery = $wpdb->prepare(
    "SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang 
     FROM $reservationsTable r 
     LEFT JOIN $customersTable c ON r.customer_id = c.id 
     WHERE r.date BETWEEN %s AND %s 
     ORDER BY r.date ASC, r.time ASC",
    $requestedDate,
    $requestedDate
);
echo "   Query: $fullQuery\n\n";
$fullResults = $wpdb->get_results($fullQuery, ARRAY_A);
echo "   Risultati: " . count($fullResults) . " prenotazioni\n";

if ($wpdb->last_error) {
    echo "   ❌ ERRORE: {$wpdb->last_error}\n";
}

if (count($fullResults) > 0) {
    echo "   ✓ Query completa funziona!\n";
    foreach ($fullResults as $resv) {
        echo "      ID: {$resv['id']}, Data: {$resv['date']}, Ora: {$resv['time']}, ";
        echo "Cliente: {$resv['first_name']} {$resv['last_name']}\n";
    }
}
echo "\n";

// 6. Mostra tutte le date disponibili
echo "6. DATE DISPONIBILI NEL DATABASE\n";
$allDates = $wpdb->get_results(
    "SELECT date, COUNT(*) as count FROM $reservationsTable GROUP BY date ORDER BY date",
    ARRAY_A
);

echo "   Totale date con prenotazioni: " . count($allDates) . "\n";
if (count($allDates) > 0) {
    echo "   Date disponibili:\n";
    foreach (array_slice($allDates, 0, 20) as $row) {
        echo "      {$row['date']}: {$row['count']} prenotazioni\n";
    }
    if (count($allDates) > 20) {
        echo "      ... e altre " . (count($allDates) - 20) . " date\n";
    }
}
echo "\n";

// 7. Data di oggi
$today = gmdate('Y-m-d');
echo "7. CONFRONTO DATE\n";
echo "   Data richiesta: $requestedDate\n";
echo "   Data di oggi (gmdate): $today\n";
echo "   Data locale (date): " . date('Y-m-d') . "\n";
echo "   Timezone PHP: " . date_default_timezone_get() . "\n";
echo "   Timezone WP: " . (get_option('timezone_string') ?: 'UTC' . get_option('gmt_offset', 0)) . "\n";
echo "\n";

// 8. Verifica il Repository
echo "8. TEST REPOSITORY findAgendaRange()\n";
try {
    $container = FP\Resv\Core\ServiceContainer::getInstance();
    $repository = $container->get(FP\Resv\Domain\Reservations\Repository::class);
    
    echo "   Chiamando findAgendaRange('$requestedDate', '$requestedDate')...\n";
    $repoResults = $repository->findAgendaRange($requestedDate, $requestedDate);
    
    echo "   Risultati dal Repository: " . count($repoResults) . " prenotazioni\n";
    
    if (count($repoResults) > 0) {
        echo "   ✓ Repository funziona!\n";
        foreach (array_slice($repoResults, 0, 3) as $resv) {
            echo "      ID: {$resv['id']}, Data: {$resv['date']}, Ora: {$resv['time']}\n";
        }
    } else {
        echo "   ❌ Repository non ritorna risultati\n";
    }
} catch (Exception $e) {
    echo "   ❌ Errore: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETATO ===\n";
echo "\nSE NON CI SONO PRENOTAZIONI PER LA DATA 2025-10-12:\n";
echo "- Le prenotazioni potrebbero essere per date diverse\n";
echo "- Controllare le date disponibili nella sezione 6\n";
echo "- Cambiare la data nel datepicker dell'agenda\n";
