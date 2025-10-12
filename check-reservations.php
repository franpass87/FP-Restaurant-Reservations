<?php
/**
 * Script SEMPLICE per verificare le prenotazioni
 */
require_once __DIR__ . '/../../wp-load.php';

if (!current_user_can('manage_options')) {
    die('Devi essere admin');
}

global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';

echo "<h1>Controllo Prenotazioni</h1>";

// 1. Conta prenotazioni totali
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "<p><strong>Totale prenotazioni:</strong> $total</p>";

if ($total == 0) {
    echo "<p style='color: red;'>❌ NON CI SONO PRENOTAZIONI NEL DATABASE</p>";
    echo "<p>Questo è il motivo per cui il manager è vuoto!</p>";
    
    // Crea una prenotazione di test
    echo "<h2>Creo una prenotazione di test...</h2>";
    
    $customersTable = $wpdb->prefix . 'fp_customers';
    
    // Crea cliente
    $wpdb->insert($customersTable, [
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@test.com',
        'phone' => '+39 123 456 7890',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]);
    $customerId = $wpdb->insert_id;
    
    // Crea prenotazione per OGGI
    $wpdb->insert($table, [
        'customer_id' => $customerId,
        'status' => 'confirmed',
        'date' => current_time('Y-m-d'),
        'time' => '19:00:00',
        'party' => 4,
        'meal' => 'dinner',
        'notes' => 'Prenotazione di test creata automaticamente',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]);
    
    echo "<p style='color: green;'>✅ Prenotazione di test creata per OGGI alle 19:00</p>";
    echo "<p><a href='/wp-admin/admin.php?page=fp-resv-manager'>Vai al Manager</a></p>";
} else {
    echo "<h2>Ultime prenotazioni:</h2>";
    $reservations = $wpdb->get_results("
        SELECT r.*, c.first_name, c.last_name, c.email
        FROM $table r
        LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
        ORDER BY r.id DESC
        LIMIT 10
    ");
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Data</th><th>Ora</th><th>Cliente</th><th>Coperti</th><th>Stato</th></tr>";
    foreach ($reservations as $r) {
        $name = trim($r->first_name . ' ' . $r->last_name) ?: $r->email;
        echo "<tr>";
        echo "<td>{$r->id}</td>";
        echo "<td>{$r->date}</td>";
        echo "<td>{$r->time}</td>";
        echo "<td>{$name}</td>";
        echo "<td>{$r->party}</td>";
        echo "<td>{$r->status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='/wp-admin/admin.php?page=fp-resv-manager'>Vai al Manager</a></p>";
}
?>

