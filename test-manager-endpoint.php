<?php
/**
 * Test DIRETTO dell'endpoint manager
 * Simula esattamente cosa fa il JavaScript
 */

// Simula una chiamata REST API come admin
define('DOING_AJAX', true);
$_SERVER['REQUEST_METHOD'] = 'GET';

// Trova wp-load.php nel sito WordPress reale
// Questo file dovrebbe essere messo nella root di WordPress o in wp-content
$wpRoot = dirname(dirname(dirname(__FILE__))); // Dalla cartella plugin alla root
$wpLoad = $wpRoot . '/wp-load.php';

if (!file_exists($wpLoad)) {
    die("ERRORE: Questo file deve essere nella cartella del plugin.\nPath cercato: $wpLoad\n");
}

require_once $wpLoad;

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die("ERRORE: Devi essere loggato come admin.\n");
}

echo "=== TEST ENDPOINT MANAGER ===\n\n";

global $wpdb;

// 1. Verifica prenotazioni nel DB
$table = $wpdb->prefix . 'fp_reservations';
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "📊 Prenotazioni nel database: $count\n\n";

if ($count == 0) {
    die("❌ Non ci sono prenotazioni! Questo è il problema.\n");
}

// 2. Test query come AdminREST::handleAgenda
$today = current_time('Y-m-d');
$monthStart = date('Y-m-01', strtotime($today));
$monthEnd = date('Y-m-t', strtotime($today));

echo "📅 Cerco prenotazioni tra: $monthStart e $monthEnd (mese corrente)\n\n";

$sql = "SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang
        FROM $table r
        LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
        WHERE r.date BETWEEN %s AND %s
        ORDER BY r.date ASC, r.time ASC";

$rows = $wpdb->get_results($wpdb->prepare($sql, $monthStart, $monthEnd), ARRAY_A);

echo "✅ Query eseguita\n";
echo "📋 Risultati trovati: " . count($rows) . "\n\n";

if (count($rows) == 0) {
    echo "⚠️ LA QUERY NON HA TROVATO PRENOTAZIONI NEL MESE CORRENTE!\n\n";
    
    // Verifica date delle prenotazioni esistenti
    $dates = $wpdb->get_results("
        SELECT date, COUNT(*) as count
        FROM $table
        GROUP BY date
        ORDER BY date DESC
    ", ARRAY_A);
    
    echo "📅 Date delle prenotazioni esistenti:\n";
    foreach ($dates as $d) {
        echo "   {$d['date']} → {$d['count']} prenotazioni\n";
    }
    echo "\n";
    echo "💡 LE PRENOTAZIONI ESISTONO MA SONO IN DATE DIVERSE DAL MESE CORRENTE!\n";
    echo "   Il manager cerca nel mese corrente ma le prenotazioni sono in altri mesi.\n\n";
    
} else {
    echo "✅ Prenotazioni trovate nel range cercato:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($rows as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($name === '') $name = $r['email'] ?? 'N/A';
        
        printf("  %s | %s | %d coperti | %s | %s\n",
            $r['date'],
            substr($r['time'], 0, 5),
            $r['party'],
            strtoupper($r['status']),
            $name
        );
    }
    echo str_repeat("-", 80) . "\n\n";
    echo "✅ L'ENDPOINT FUNZIONA! Il manager dovrebbe mostrare queste prenotazioni.\n";
}

echo "\n=== DIAGNOSI ===\n";
if ($count > 0 && count($rows) == 0) {
    echo "❌ PROBLEMA: Le prenotazioni esistono ma sono fuori dal range cercato dal manager\n";
    echo "   Soluzione: Naviga a un mese diverso nel manager o crea prenotazioni per il mese corrente\n";
} elseif (count($rows) > 0) {
    echo "✅ Tutto OK: Le prenotazioni sono nel database e nel range corretto\n";
    echo "   Se il manager non le mostra, il problema è nel JavaScript o nei permessi\n";
}

echo "\n=== TEST ENDPOINT REST API ===\n";
echo "URL da testare nel browser:\n";
echo admin_url('admin-ajax.php') . "?action=fp_resv_test_endpoint\n\n";

// Simula chiamata REST
$request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
$request->set_param('date', $today);
$request->set_param('range', 'month');

$server = rest_get_server();
$response = $server->dispatch($request);

echo "Status: " . $response->get_status() . "\n";
$data = $response->get_data();

if (is_array($data)) {
    echo "Keys nella risposta: " . implode(', ', array_keys($data)) . "\n";
    if (isset($data['reservations'])) {
        echo "Prenotazioni nell'array: " . count($data['reservations']) . "\n";
    }
    if (isset($data['meta'])) {
        echo "Range cercato: {$data['meta']['start_date']} → {$data['meta']['end_date']}\n";
    }
} else {
    echo "❌ Risposta non è un array valido!\n";
}

echo "\n✅ Test completato!\n";
?>

