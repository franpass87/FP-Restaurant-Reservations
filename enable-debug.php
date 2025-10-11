<?php
/**
 * Script per abilitare temporaneamente il debug WordPress
 * Esegui: https://tuosito.com/wp-content/plugins/fp-restaurant-reservations/enable-debug.php
 * ELIMINA dopo l'uso!
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

// Abilita debug
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test API Agenda</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        .panel { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Test API Agenda</h1>
    
    <div class="panel">
        <h2>Test Database Query</h2>
        <?php
        global $wpdb;
        $table = $wpdb->prefix . 'fp_reservations';
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        echo "<p><strong>Oggi:</strong> {$today}</p>";
        echo "<p><strong>Ieri:</strong> {$yesterday}</p>";
        
        // Count totale
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "<p class='success'><strong>Totale prenotazioni nel DB:</strong> {$total}</p>";
        
        if ($total > 0) {
            // Prenotazioni di ieri
            $yesterday_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE date = %s",
                $yesterday
            ));
            echo "<p><strong>Prenotazioni di ieri:</strong> {$yesterday_count}</p>";
            
            // Prenotazioni di oggi
            $today_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE date = %s",
                $today
            ));
            echo "<p><strong>Prenotazioni di oggi:</strong> {$today_count}</p>";
            
            // Ultime 5 prenotazioni
            $recent = $wpdb->get_results("
                SELECT id, date, time, party, status, created_at
                FROM {$table}
                ORDER BY created_at DESC
                LIMIT 5
            ", ARRAY_A);
            
            echo "<h3>Ultime 5 prenotazioni:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Data</th><th>Ora</th><th>Coperti</th><th>Stato</th><th>Creata</th></tr>";
            foreach ($recent as $r) {
                echo "<tr>";
                echo "<td>{$r['id']}</td>";
                echo "<td>{$r['date']}</td>";
                echo "<td>{$r['time']}</td>";
                echo "<td>{$r['party']}</td>";
                echo "<td>{$r['status']}</td>";
                echo "<td>{$r['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        ?>
    </div>
    
    <div class="panel">
        <h2>Test Repository</h2>
        <?php
        try {
            $repo = new \FP\Resv\Domain\Reservations\Repository($wpdb);
            
            echo "<h3>Test: findAgendaRange per ieri</h3>";
            $results = $repo->findAgendaRange($yesterday, $yesterday);
            
            if (empty($results)) {
                echo "<p class='error'>❌ Repository restituisce array vuoto per ieri!</p>";
            } else {
                echo "<p class='success'>✓ Repository trovato " . count($results) . " prenotazioni</p>";
                echo "<pre>" . print_r($results, true) . "</pre>";
            }
            
            echo "<h3>Test: findAgendaRange per oggi</h3>";
            $results_today = $repo->findAgendaRange($today, $today);
            
            if (empty($results_today)) {
                echo "<p>Nessuna prenotazione per oggi</p>";
            } else {
                echo "<p class='success'>✓ Repository trovato " . count($results_today) . " prenotazioni</p>";
                echo "<pre>" . print_r($results_today, true) . "</pre>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>Errore: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="panel">
        <h2>Test API REST Endpoint</h2>
        <?php
        echo "<h3>Simulazione chiamata API per ieri</h3>";
        
        $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
        $request->set_param('date', $yesterday);
        
        $response = rest_do_request($request);
        $data = $response->get_data();
        
        echo "<p><strong>Status:</strong> " . $response->get_status() . "</p>";
        
        if ($response->is_error()) {
            echo "<p class='error'>❌ Errore API</p>";
            echo "<pre>" . print_r($data, true) . "</pre>";
        } else {
            $count = is_array($data) ? count($data) : 0;
            if ($count > 0) {
                echo "<p class='success'>✓ API restituisce {$count} prenotazioni</p>";
                echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "<p class='error'>❌ API restituisce array vuoto!</p>";
                echo "<p>Tipo di dato restituito: " . gettype($data) . "</p>";
                echo "<pre>" . print_r($data, true) . "</pre>";
            }
        }
        ?>
    </div>
    
    <div class="panel">
        <h2>Log PHP</h2>
        <?php
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $log_lines = file($log_file);
            $recent_logs = array_slice($log_lines, -50); // Ultimi 50 righe
            
            echo "<pre>";
            foreach ($recent_logs as $line) {
                if (strpos($line, '[Agenda') !== false) {
                    echo "<strong>" . htmlspecialchars($line) . "</strong>";
                } else {
                    echo htmlspecialchars($line);
                }
            }
            echo "</pre>";
        } else {
            echo "<p>File debug.log non trovato</p>";
        }
        ?>
    </div>
    
    <p style="background: #ffdddd; padding: 10px; border-radius: 5px;">
        <strong>⚠️ IMPORTANTE:</strong> Elimina questo file dopo l'uso!<br>
        <code>rm <?php echo __FILE__; ?></code>
    </p>
</body>
</html>
