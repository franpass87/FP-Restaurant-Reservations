<?php
/**
 * Test diretto database - eseguibile da CLI
 */

// Trova wp-load.php - prova diversi percorsi
$paths = [
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
];

$wpLoad = null;
foreach ($paths as $path) {
    if (file_exists($path)) {
        $wpLoad = $path;
        break;
    }
}

if (!$wpLoad) {
    echo "ERRORE: Non trovo wp-load.php\n";
    echo "Cercato in:\n";
    foreach ($paths as $path) {
        echo "  - $path\n";
    }
    exit(1);
}

require_once $wpLoad;

global $wpdb;

echo "=== TEST DATABASE PRENOTAZIONI ===\n\n";

// 1. Verifica tabella
$table = $wpdb->prefix . 'fp_reservations';
$tableExists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

if (!$tableExists) {
    echo "❌ Tabella $table NON ESISTE!\n";
    exit(1);
}

echo "✅ Tabella $table esiste\n\n";

// 2. Conta prenotazioni
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "📊 Totale prenotazioni: $count\n\n";

if ($count == 0) {
    echo "❌ NESSUNA PRENOTAZIONE NEL DATABASE!\n";
    echo "   Questo è il motivo per cui il manager è vuoto.\n\n";
    
    // Crea prenotazione di test
    echo "🔧 Creo prenotazione di test...\n";
    
    $customersTable = $wpdb->prefix . 'fp_customers';
    
    // Crea cliente
    $inserted = $wpdb->insert($customersTable, [
        'first_name' => 'Test',
        'last_name' => 'Manager',
        'email' => 'test@manager.local',
        'phone' => '+39 333 1234567',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]);
    
    if ($inserted === false) {
        echo "❌ Errore creazione cliente: " . $wpdb->last_error . "\n";
        exit(1);
    }
    
    $customerId = $wpdb->insert_id;
    echo "✅ Cliente creato (ID: $customerId)\n";
    
    // Crea prenotazione per OGGI
    $today = current_time('Y-m-d');
    $inserted = $wpdb->insert($table, [
        'customer_id' => $customerId,
        'status' => 'confirmed',
        'date' => $today,
        'time' => '19:30:00',
        'party' => 4,
        'meal' => 'dinner',
        'notes' => 'Prenotazione di test automatica',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]);
    
    if ($inserted === false) {
        echo "❌ Errore creazione prenotazione: " . $wpdb->last_error . "\n";
        exit(1);
    }
    
    $resvId = $wpdb->insert_id;
    echo "✅ Prenotazione creata (ID: $resvId)\n";
    echo "   Data: $today 19:30\n";
    echo "   Coperti: 4\n";
    echo "   Stato: confirmed\n\n";
    
    echo "✅ ORA il manager dovrebbe mostrare questa prenotazione!\n";
    
} else {
    echo "✅ Ci sono prenotazioni nel database\n\n";
    
    // Mostra ultime 5
    $reservations = $wpdb->get_results("
        SELECT r.id, r.date, r.time, r.party, r.status, 
               c.first_name, c.last_name, c.email
        FROM $table r
        LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
        ORDER BY r.date DESC, r.time DESC
        LIMIT 5
    ", ARRAY_A);
    
    echo "📋 Ultime 5 prenotazioni:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($reservations as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($name === '') $name = $r['email'] ?? 'N/A';
        
        printf("  ID: %d | %s %s | %d coperti | %s | %s\n",
            $r['id'],
            $r['date'],
            substr($r['time'], 0, 5),
            $r['party'],
            strtoupper($r['status']),
            $name
        );
    }
    echo str_repeat("-", 80) . "\n";
}

echo "\n✅ Test completato!\n";
?>

