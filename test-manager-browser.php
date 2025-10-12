<?php
/**
 * TEST MANAGER - Da aprire nel BROWSER come admin WordPress
 * 
 * ISTRUZIONI:
 * 1. Copia questo file nella ROOT di WordPress (dove c'è wp-config.php)
 * 2. Apri: https://tuo-sito.com/test-manager-browser.php
 * 3. Devi essere loggato come admin
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('<h1>Errore</h1><p>Devi essere loggato come amministratore.</p>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Manager Prenotazioni</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f0f0f0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .box { background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Test Manager Prenotazioni</h1>
    
    <?php
    global $wpdb;
    
    // 1. VERIFICA DATABASE
    echo '<div class="box">';
    echo '<h2>1️⃣ Verifica Database</h2>';
    
    $table = $wpdb->prefix . 'fp_reservations';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    
    if ($count == 0) {
        echo '<p class="error">❌ NESSUNA PRENOTAZIONE NEL DATABASE!</p>';
        echo '<p>Questo è il problema principale. Il manager non può mostrare prenotazioni che non esistono.</p>';
    } else {
        echo "<p class='success'>✅ Trovate <strong>$count prenotazioni</strong> nel database</p>";
        
        // Mostra le date
        $dates = $wpdb->get_results("
            SELECT date, COUNT(*) as count
            FROM $table
            GROUP BY date
            ORDER BY date DESC
            LIMIT 10
        ", ARRAY_A);
        
        echo '<p><strong>Date delle prenotazioni:</strong></p>';
        echo '<table>';
        echo '<tr><th>Data</th><th>Numero Prenotazioni</th></tr>';
        foreach ($dates as $d) {
            echo "<tr><td>{$d['date']}</td><td>{$d['count']}</td></tr>";
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 2. TEST QUERY MANAGER
    echo '<div class="box">';
    echo '<h2>2️⃣ Test Query Manager (mese corrente)</h2>';
    
    $today = current_time('Y-m-d');
    $monthStart = date('Y-m-01', strtotime($today));
    $monthEnd = date('Y-m-t', strtotime($today));
    
    echo "<p>Il manager cerca prenotazioni tra: <strong>$monthStart</strong> e <strong>$monthEnd</strong></p>";
    
    $sql = "SELECT r.*, c.first_name, c.last_name, c.email
            FROM $table r
            LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
            WHERE r.date BETWEEN %s AND %s
            ORDER BY r.date ASC, r.time ASC";
    
    $rows = $wpdb->get_results($wpdb->prepare($sql, $monthStart, $monthEnd), ARRAY_A);
    
    if (count($rows) == 0) {
        echo '<p class="error">❌ Nessuna prenotazione trovata nel mese corrente!</p>';
        echo '<p class="warning">⚠️ Le 4 prenotazioni che hai ricevuto sono probabilmente in altri mesi.</p>';
        echo '<p><strong>Soluzione:</strong> Nel manager, naviga ai mesi dove hai le prenotazioni usando i pulsanti di navigazione.</p>';
    } else {
        echo "<p class='success'>✅ Trovate <strong>" . count($rows) . " prenotazioni</strong> nel mese corrente</p>";
        
        echo '<table>';
        echo '<tr><th>ID</th><th>Data</th><th>Ora</th><th>Cliente</th><th>Coperti</th><th>Stato</th></tr>';
        foreach ($rows as $r) {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            if ($name === '') $name = $r['email'] ?? 'N/A';
            
            echo '<tr>';
            echo "<td>{$r['id']}</td>";
            echo "<td>{$r['date']}</td>";
            echo "<td>" . substr($r['time'], 0, 5) . "</td>";
            echo "<td>" . esc_html($name) . "</td>";
            echo "<td>{$r['party']}</td>";
            echo "<td>{$r['status']}</td>";
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 3. TEST ENDPOINT REST
    echo '<div class="box">';
    echo '<h2>3️⃣ Test Endpoint REST API</h2>';
    
    $restUrl = rest_url('fp-resv/v1/agenda');
    $testUrl = add_query_arg([
        'date' => $today,
        'range' => 'month'
    ], $restUrl);
    
    echo '<p>Endpoint: <code>' . esc_html($testUrl) . '</code></p>';
    
    // Simula chiamata REST
    $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
    $request->set_param('date', $today);
    $request->set_param('range', 'month');
    
    $server = rest_get_server();
    $response = $server->dispatch($request);
    
    $status = $response->get_status();
    
    if ($status == 200) {
        echo "<p class='success'>✅ Endpoint risponde con status $status</p>";
        
        $data = $response->get_data();
        
        if (is_array($data) && isset($data['reservations'])) {
            $resvCount = count($data['reservations']);
            echo "<p><strong>Prenotazioni nella risposta:</strong> $resvCount</p>";
            
            if ($resvCount == 0) {
                echo '<p class="warning">⚠️ L\'endpoint restituisce 0 prenotazioni anche se ne esistono nel database!</p>';
                echo '<p>Problema nell\'endpoint o nella query.</p>';
            } else {
                echo "<p class='success'>✅ L'endpoint funziona correttamente!</p>";
            }
            
            // Mostra struttura risposta
            echo '<details><summary>Vedi risposta completa</summary>';
            echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
            echo '</details>';
        } else {
            echo '<p class="error">❌ Risposta non contiene l\'array "reservations"</p>';
            echo '<pre>' . print_r($data, true) . '</pre>';
        }
    } else {
        echo "<p class='error'>❌ Endpoint risponde con status $status (errore)</p>";
    }
    
    echo '</div>';
    
    // 4. CONCLUSIONI
    echo '<div class="box">';
    echo '<h2>📋 Conclusioni</h2>';
    
    if ($count == 0) {
        echo '<p class="error"><strong>PROBLEMA:</strong> Non ci sono prenotazioni nel database.</p>';
        echo '<p><strong>SOLUZIONE:</strong> Ricevi/crea almeno una prenotazione.</p>';
    } elseif (count($rows) == 0 && $count > 0) {
        echo '<p class="error"><strong>PROBLEMA:</strong> Le prenotazioni esistono ma sono in mesi diversi da quello corrente.</p>';
        echo '<p><strong>SOLUZIONE:</strong> Nel manager, usa i pulsanti "Mese precedente/successivo" per navigare ai mesi con prenotazioni.</p>';
    } elseif (count($rows) > 0 && isset($resvCount) && $resvCount == 0) {
        echo '<p class="error"><strong>PROBLEMA:</strong> Le prenotazioni ci sono ma l\'endpoint non le restituisce.</p>';
        echo '<p><strong>SOLUZIONE:</strong> Problema nel codice PHP dell\'endpoint (AdminREST.php).</p>';
    } elseif (count($rows) > 0 && isset($resvCount) && $resvCount > 0) {
        echo '<p class="success"><strong>✅ TUTTO OK!</strong> Il backend funziona perfettamente.</p>';
        echo '<p>Se il manager non mostra le prenotazioni, il problema è nel JavaScript del frontend.</p>';
        echo '<p><strong>SOLUZIONE:</strong> Apri la Console del browser (F12) e cerca errori JavaScript.</p>';
    }
    
    echo '</div>';
    ?>
    
    <p><a href="<?php echo admin_url('admin.php?page=fp-resv-manager'); ?>">➡️ Vai al Manager</a></p>
</body>
</html>

