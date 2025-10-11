#!/usr/bin/env php
<?php
/**
 * Test rapido: esegue la query dell'agenda per oggi e ieri
 */

// Trova wp-load.php
$paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH')) {
    die("❌ WordPress non caricato\n");
}

global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';
$customers_table = $wpdb->prefix . 'fp_customers';

echo "==================================\n";
echo "TEST QUERY AGENDA\n";
echo "==================================\n\n";

// Parametri
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

echo "Oggi: {$today}\n";
echo "Ieri: {$yesterday}\n\n";

// Query 1: Count generale
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
echo "📊 Prenotazioni totali: {$total}\n\n";

if ($total == 0) {
    echo "❌ Database vuoto!\n";
    exit(0);
}

// Query 2: Prenotazioni di ieri (query semplice)
echo "🔍 Query 1: Prenotazioni di ieri (query semplice)\n";
echo "SQL: SELECT * FROM {$table} WHERE date = '{$yesterday}'\n";
$yesterday_simple = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE date = %s",
    $yesterday
), ARRAY_A);

echo "Risultati: " . count($yesterday_simple) . "\n";
if (!empty($yesterday_simple)) {
    foreach ($yesterday_simple as $r) {
        echo "  - ID {$r['id']}: {$r['date']} {$r['time']} - {$r['party']} coperti - {$r['status']}\n";
    }
}
echo "\n";

// Query 3: Prenotazioni di oggi (query semplice)
echo "🔍 Query 2: Prenotazioni di oggi (query semplice)\n";
echo "SQL: SELECT * FROM {$table} WHERE date = '{$today}'\n";
$today_simple = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE date = %s",
    $today
), ARRAY_A);

echo "Risultati: " . count($today_simple) . "\n";
if (!empty($today_simple)) {
    foreach ($today_simple as $r) {
        echo "  - ID {$r['id']}: {$r['date']} {$r['time']} - {$r['party']} coperti - {$r['status']}\n";
    }
}
echo "\n";

// Query 4: Query completa agenda (con JOIN)
echo "🔍 Query 3: Query agenda con JOIN customers (simulazione findAgendaRange)\n";
$sql = "SELECT 
    r.*,
    c.first_name as customer_first_name,
    c.last_name as customer_last_name,
    c.email as customer_email,
    c.phone as customer_phone,
    c.language as customer_language
FROM {$table} r
LEFT JOIN {$customers_table} c ON r.customer_id = c.id
WHERE r.date BETWEEN %s AND %s
ORDER BY r.date, r.time";

echo "SQL: " . str_replace(['{$table}', '{$customers_table}'], [$table, $customers_table], $sql) . "\n";
echo "Parametri: date BETWEEN '{$yesterday}' AND '{$today}'\n";

$agenda_results = $wpdb->get_results($wpdb->prepare(
    $sql,
    $yesterday,
    $today
), ARRAY_A);

echo "Risultati: " . count($agenda_results) . "\n";
if (!empty($agenda_results)) {
    foreach ($agenda_results as $r) {
        $name = trim(($r['customer_first_name'] ?? '') . ' ' . ($r['customer_last_name'] ?? ''));
        $name = $name ?: 'N/D';
        echo "  - ID {$r['id']}: {$r['date']} {$r['time']} - {$name} - {$r['party']} coperti - {$r['status']}\n";
    }
}
echo "\n";

// Query 5: Ultimi 10 record creati
echo "🔍 Query 4: Ultime 10 prenotazioni create\n";
$recent = $wpdb->get_results(
    "SELECT id, date, time, status, party, created_at FROM {$table} ORDER BY created_at DESC LIMIT 10",
    ARRAY_A
);

echo "Risultati: " . count($recent) . "\n";
foreach ($recent as $r) {
    echo "  - ID {$r['id']}: {$r['date']} {$r['time']} - creata il {$r['created_at']}\n";
}
echo "\n";

echo "✅ Test completato!\n\n";

// Stampa errori wpdb
if (!empty($wpdb->last_error)) {
    echo "❌ Ultimo errore wpdb: {$wpdb->last_error}\n";
}
