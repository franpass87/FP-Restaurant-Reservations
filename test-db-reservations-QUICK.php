<?php
/**
 * Script diagnostico VELOCE per verificare:
 * 1. Se ci sono prenotazioni nel database
 * 2. Quante sono
 * 3. Gli ultimi 10 record inseriti
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

// Nome tabella
$table = $wpdb->prefix . 'fp_reservations';

// Verifica che la tabella esista
$tableExists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$tableExists) {
    echo "❌ TABELLA $table NON ESISTE!\n";
    exit;
}

echo "✅ Tabella $table esiste\n\n";

// Conta totale prenotazioni
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "📊 TOTALE PRENOTAZIONI NEL DATABASE: $total\n\n";

if ($total == 0) {
    echo "⚠️ PROBLEMA TROVATO: Non ci sono prenotazioni nel database!\n";
    echo "Questo significa che il form NON sta salvando nel database.\n\n";
    
    // Verifica struttura tabella
    echo "Struttura tabella:\n";
    $columns = $wpdb->get_results("DESCRIBE $table");
    foreach ($columns as $col) {
        echo "  - {$col->Field} ({$col->Type})\n";
    }
    exit;
}

// Mostra ultime 10 prenotazioni
echo "📝 ULTIME 10 PRENOTAZIONI:\n";
echo str_repeat("-", 100) . "\n";

$reservations = $wpdb->get_results("
    SELECT 
        r.id,
        r.date,
        r.time,
        r.party,
        r.status,
        r.created_at,
        c.first_name,
        c.last_name,
        c.email
    FROM $table r
    LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
    ORDER BY r.created_at DESC
    LIMIT 10
", ARRAY_A);

foreach ($reservations as $r) {
    printf(
        "ID: %4d | %s %s | %s %s | %d persone | %s | Cliente: %s %s (%s)\n",
        $r['id'],
        $r['date'],
        substr($r['time'], 0, 5),
        $r['status'],
        str_repeat(' ', 12 - strlen($r['status'])),
        $r['party'],
        $r['created_at'],
        $r['first_name'] ?? 'N/A',
        $r['last_name'] ?? '',
        $r['email'] ?? 'N/A'
    );
}

echo str_repeat("-", 100) . "\n\n";

// Conta per status
echo "📊 PRENOTAZIONI PER STATO:\n";
$statuses = $wpdb->get_results("
    SELECT status, COUNT(*) as count
    FROM $table
    GROUP BY status
", ARRAY_A);

foreach ($statuses as $s) {
    echo "  {$s['status']}: {$s['count']}\n";
}

echo "\n";

// Prenotazioni oggi
$today = date('Y-m-d');
$todayCount = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table WHERE date = %s",
    $today
));
echo "📅 Prenotazioni per oggi ($today): $todayCount\n";

// Prenotazioni ultimi 7 giorni
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$weekCount = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table WHERE date >= %s",
    $weekAgo
));
echo "📅 Prenotazioni ultimi 7 giorni: $weekCount\n\n";

echo "✅ DIAGNOSI COMPLETATA\n";

