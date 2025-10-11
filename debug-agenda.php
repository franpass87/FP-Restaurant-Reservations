<?php
/**
 * Script di Debug Agenda - Esegui via browser
 * URL: https://tuosito.com/wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
 * 
 * ATTENZIONE: Elimina questo file dopo il debug!
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Solo admin possono eseguire
if (!current_user_can('manage_options')) {
    die('❌ Accesso negato. Solo amministratori possono eseguire questo script.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Agenda Prenotazioni</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f0f0f0;
        }
        .panel {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 0; }
        .success { color: #46b450; font-weight: bold; }
        .error { color: #dc3232; font-weight: bold; }
        .warning { color: #f0b849; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-confirmed { background: #46b450; color: white; }
        .badge-pending { background: #f0b849; color: white; }
        .badge-cancelled { background: #dc3232; color: white; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>🔍 Debug Agenda Prenotazioni</h1>
    
<?php
global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';
$customers_table = $wpdb->prefix . 'fp_customers';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// 1. VERIFICA DATABASE
echo '<div class="panel">';
echo '<h2>📊 Step 1: Verifica Database</h2>';

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
echo "<p><strong>Totale prenotazioni:</strong> <span class='success'>{$total}</span></p>";

if ($total == 0) {
    echo '<p class="error">❌ Il database è VUOTO! Non ci sono prenotazioni.</p>';
    echo '<p>Le prenotazioni che pensavi fossero arrivate ieri potrebbero non essere state salvate.</p>';
    echo '</div></body></html>';
    exit;
}

// Prenotazioni per data
$by_date = $wpdb->get_results("
    SELECT date, COUNT(*) as count 
    FROM {$table} 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY date 
    ORDER BY date DESC
", ARRAY_A);

echo '<h3>Prenotazioni per data (ultimi 7 giorni):</h3>';
if (!empty($by_date)) {
    echo '<table>';
    echo '<tr><th>Data</th><th>Numero prenotazioni</th></tr>';
    foreach ($by_date as $row) {
        $label = '';
        if ($row['date'] == $today) $label = ' (OGGI)';
        if ($row['date'] == $yesterday) $label = ' (IERI)';
        echo "<tr><td><strong>{$row['date']}</strong>{$label}</td><td>{$row['count']}</td></tr>";
    }
    echo '</table>';
} else {
    echo '<p class="warning">⚠️ Nessuna prenotazione negli ultimi 7 giorni</p>';
}

echo '</div>';

// 2. PRENOTAZIONI DI IERI (dettaglio)
echo '<div class="panel">';
echo '<h2>🕐 Step 2: Prenotazioni di IERI (' . $yesterday . ')</h2>';

$yesterday_bookings = $wpdb->get_results($wpdb->prepare("
    SELECT r.*, c.first_name, c.last_name, c.email, c.phone
    FROM {$table} r
    LEFT JOIN {$customers_table} c ON r.customer_id = c.id
    WHERE r.date = %s
    ORDER BY r.time
", $yesterday), ARRAY_A);

$count_yesterday = count($yesterday_bookings);
echo "<p><strong>Totale:</strong> ";
if ($count_yesterday > 0) {
    echo "<span class='success'>{$count_yesterday} prenotazioni trovate</span></p>";
    
    echo '<table>';
    echo '<tr><th>ID</th><th>Ora</th><th>Cliente</th><th>Coperti</th><th>Stato</th><th>Email</th><th>Telefono</th><th>Creata il</th></tr>';
    foreach ($yesterday_bookings as $b) {
        $name = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
        $name = $name ?: '<em>N/D</em>';
        $status_class = 'badge badge-' . $b['status'];
        echo "<tr>";
        echo "<td>{$b['id']}</td>";
        echo "<td><strong>" . substr($b['time'], 0, 5) . "</strong></td>";
        echo "<td>{$name}</td>";
        echo "<td>{$b['party']}</td>";
        echo "<td><span class='{$status_class}'>{$b['status']}</span></td>";
        echo "<td>" . ($b['email'] ?? 'N/D') . "</td>";
        echo "<td>" . ($b['phone'] ?? 'N/D') . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($b['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo '</table>';
} else {
    echo "<span class='error'>0 prenotazioni</span></p>";
    echo '<p class="error">❌ Non ci sono prenotazioni per ieri nel database!</p>';
    echo '<p>Possibili spiegazioni:</p>';
    echo '<ul>';
    echo '<li>Le prenotazioni non sono state salvate correttamente</li>';
    echo '<li>Le prenotazioni sono per un\'altra data</li>';
    echo '<li>Le prenotazioni sono state cancellate</li>';
    echo '</ul>';
}

echo '</div>';

// 3. TEST REPOSITORY findAgendaRange
echo '<div class="panel">';
echo '<h2>🔌 Step 3: Test Repository API</h2>';

try {
    if (!class_exists('FP\\Resv\\Domain\\Reservations\\Repository')) {
        echo '<p class="error">❌ Classe Repository non trovata!</p>';
    } else {
        $repo = new \FP\Resv\Domain\Reservations\Repository($wpdb);
        
        echo '<h3>Test findAgendaRange per ieri:</h3>';
        $results = $repo->findAgendaRange($yesterday, $yesterday);
        
        echo "<p><strong>Risultato:</strong> ";
        if (count($results) > 0) {
            echo "<span class='success'>" . count($results) . " prenotazioni restituite dal repository</span></p>";
            
            echo '<pre>';
            foreach ($results as $r) {
                echo "ID {$r['id']}: {$r['date']} {$r['time']} - ";
                echo ($r['first_name'] ?? 'N/D') . " " . ($r['last_name'] ?? '');
                echo " ({$r['party']} coperti) - {$r['status']}\n";
            }
            echo '</pre>';
            
            // Mostra struttura dati completa del primo record
            if (isset($results[0])) {
                echo '<h3>Struttura dati primo record:</h3>';
                echo '<pre>' . print_r($results[0], true) . '</pre>';
            }
        } else {
            echo "<span class='error'>0 prenotazioni</span></p>";
            if ($count_yesterday > 0) {
                echo '<p class="error">❌ PROBLEMA: Ci sono ' . $count_yesterday . ' prenotazioni nel DB ma il repository non le restituisce!</p>';
                echo '<p>Questo indica un problema nella query del repository.</p>';
            }
        }
    }
} catch (Exception $e) {
    echo '<p class="error">❌ Errore: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '</div>';

// 4. TEST ENDPOINT API DIRETTO
echo '<div class="panel">';
echo '<h2>🌐 Step 4: Test Endpoint REST API</h2>';

$api_url = rest_url('fp-resv/v1/agenda');
echo "<p><strong>Endpoint:</strong> <code>{$api_url}</code></p>";

// Verifica che l'endpoint sia registrato
$routes = rest_get_server()->get_routes();
if (isset($routes['/fp-resv/v1/agenda'])) {
    echo '<p class="success">✓ Endpoint registrato correttamente</p>';
    
    // Simula chiamata API
    echo '<h3>Simulazione chiamata API per ieri:</h3>';
    
    $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
    $request->set_param('date', $yesterday);
    
    $response = rest_do_request($request);
    $data = $response->get_data();
    
    echo "<p><strong>Status:</strong> ";
    if ($response->is_error()) {
        echo "<span class='error'>" . $response->get_status() . " (ERRORE)</span></p>";
        echo '<p class="error">Messaggio errore: ' . $data['message'] . '</p>';
    } else {
        echo "<span class='success'>" . $response->get_status() . " OK</span></p>";
        
        echo "<p><strong>Risultato:</strong> ";
        if (is_array($data) && count($data) > 0) {
            echo "<span class='success'>" . count($data) . " prenotazioni</span></p>";
            echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
        } else {
            echo "<span class='error'>Array vuoto!</span></p>";
            echo '<p class="error">❌ L\'API restituisce un array vuoto anche se ci sono dati nel database!</p>';
        }
    }
} else {
    echo '<p class="error">❌ Endpoint NON registrato!</p>';
}

echo '</div>';

// 5. VERIFICA PERMESSI
echo '<div class="panel">';
echo '<h2>👤 Step 5: Verifica Permessi Utente</h2>';

$current_user = wp_get_current_user();
echo "<p><strong>Utente:</strong> {$current_user->user_login} (ID: {$current_user->ID})</p>";
echo "<p><strong>Ruoli:</strong> " . implode(', ', $current_user->roles) . "</p>";

$caps = ['manage_options', 'manage_fp_reservations', 'edit_posts'];
echo '<h3>Permessi:</h3>';
echo '<ul>';
foreach ($caps as $cap) {
    $has = current_user_can($cap);
    $icon = $has ? '✓' : '❌';
    $class = $has ? 'success' : 'error';
    echo "<li><span class='{$class}'>{$icon} {$cap}</span></li>";
}
echo '</ul>';

echo '</div>';

// 6. ULTIME PRENOTAZIONI CREATE
echo '<div class="panel">';
echo '<h2>📅 Step 6: Ultime 10 Prenotazioni Create</h2>';

$recent = $wpdb->get_results("
    SELECT r.id, r.date, r.time, r.status, r.party, r.created_at, r.updated_at,
           c.first_name, c.last_name, c.email
    FROM {$table} r
    LEFT JOIN {$customers_table} c ON r.customer_id = c.id
    ORDER BY r.created_at DESC
    LIMIT 10
", ARRAY_A);

if (!empty($recent)) {
    echo '<table>';
    echo '<tr><th>ID</th><th>Data prenotaz.</th><th>Ora</th><th>Cliente</th><th>Coperti</th><th>Stato</th><th>Creata il</th></tr>';
    foreach ($recent as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $name = $name ?: 'N/D';
        $highlight = $r['date'] == $yesterday ? ' style="background: #fff3cd;"' : '';
        echo "<tr{$highlight}>";
        echo "<td>{$r['id']}</td>";
        echo "<td><strong>{$r['date']}</strong></td>";
        echo "<td>" . substr($r['time'], 0, 5) . "</td>";
        echo "<td>{$name}</td>";
        echo "<td>{$r['party']}</td>";
        echo "<td>{$r['status']}</td>";
        echo "<td>" . date('d/m/Y H:i:s', strtotime($r['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo '</table>';
}

echo '</div>';

// 7. CONCLUSIONI
echo '<div class="panel">';
echo '<h2>💡 Conclusioni e Azioni</h2>';

if ($total == 0) {
    echo '<p class="error"><strong>❌ DATABASE VUOTO</strong></p>';
    echo '<p>Non ci sono prenotazioni nel database. Le prenotazioni che pensavi fossero state create non sono state salvate.</p>';
    echo '<h3>Cosa fare:</h3>';
    echo '<ol>';
    echo '<li>Verifica che il form di prenotazione funzioni correttamente</li>';
    echo '<li>Controlla i log di errore PHP per problemi durante il salvataggio</li>';
    echo '<li>Prova a creare una prenotazione di test dall\'agenda admin</li>';
    echo '</ol>';
} elseif ($count_yesterday == 0) {
    echo '<p class="warning"><strong>⚠️ NESSUNA PRENOTAZIONE PER IERI</strong></p>';
    echo '<p>Ci sono ' . $total . ' prenotazioni nel database, ma nessuna per ieri (' . $yesterday . ').</p>';
    echo '<h3>Verifica:</h3>';
    echo '<ol>';
    echo '<li>Le prenotazioni potrebbero essere per un\'altra data</li>';
    echo '<li>Controlla la tabella "Prenotazioni per data" sopra per vedere quando sono le prenotazioni</li>';
    echo '<li>Le prenotazioni potrebbero essere state cancellate</li>';
    echo '</ol>';
} elseif (isset($results) && count($results) == 0 && $count_yesterday > 0) {
    echo '<p class="error"><strong>❌ PROBLEMA NEL REPOSITORY</strong></p>';
    echo '<p>Ci sono ' . $count_yesterday . ' prenotazioni per ieri nel database, ma il Repository non le restituisce!</p>';
    echo '<h3>Cosa fare:</h3>';
    echo '<ol>';
    echo '<li>Verifica la query SQL nel file <code>Repository.php::findAgendaRange()</code></li>';
    echo '<li>Potrebbe essere un problema di JOIN con la tabella customers</li>';
    echo '<li>Controlla i log di errore PHP</li>';
    echo '</ol>';
} elseif (isset($data) && (!is_array($data) || count($data) == 0) && count($results) > 0) {
    echo '<p class="error"><strong>❌ PROBLEMA NELL\'ENDPOINT API</strong></p>';
    echo '<p>Il Repository restituisce ' . count($results) . ' prenotazioni, ma l\'API restituisce un array vuoto!</p>';
    echo '<h3>Cosa fare:</h3>';
    echo '<ol>';
    echo '<li>Verifica il metodo <code>AdminREST.php::handleAgenda()</code></li>';
    echo '<li>Potrebbe essere un problema nella mappatura dei dati</li>';
    echo '<li>Verifica che la funzione <code>mapAgendaReservation()</code> funzioni correttamente</li>';
    echo '</ol>';
} else {
    echo '<p class="success"><strong>✓ DATI CORRETTI</strong></p>';
    echo '<p>Le prenotazioni sono nel database e l\'API le restituisce correttamente.</p>';
    echo '<h3>Il problema potrebbe essere nel frontend:</h3>';
    echo '<ol>';
    echo '<li>Apri DevTools (F12) nella pagina dell\'agenda</li>';
    echo '<li>Vai al tab "Network"</li>';
    echo '<li>Ricarica la pagina e verifica la chiamata a <code>/wp-json/fp-resv/v1/agenda</code></li>';
    echo '<li>Controlla che il parametro <code>date</code> sia corretto</li>';
    echo '<li>Verifica la risposta JSON</li>';
    echo '<li>Controlla Console per errori JavaScript</li>';
    echo '</ol>';
    echo '<h3>Debug JavaScript:</h3>';
    echo '<p>Apri la Console DevTools e esegui:</p>';
    echo '<pre>';
    echo "// Mostra data corrente\n";
    echo "console.log('Data:', document.querySelector('[data-role=\"date-picker\"]').value);\n\n";
    echo "// Test chiamata API\n";
    echo "fetch('" . $api_url . "?date=" . $yesterday . "', {\n";
    echo "  headers: {\n";
    echo "    'X-WP-Nonce': window.fpResvAgendaSettings?.nonce || ''\n";
    echo "  }\n";
    echo "})\n";
    echo ".then(r => r.json())\n";
    echo ".then(d => console.log('API Result:', d));\n";
    echo '</pre>';
}

echo '</div>';
?>

<div class="panel" style="background: #fff3cd; border-left: 4px solid #f0b849;">
    <h2>⚠️ IMPORTANTE</h2>
    <p><strong>ELIMINA QUESTO FILE dopo aver completato il debug!</strong></p>
    <p>Questo script espone informazioni sensibili e deve essere rimosso:</p>
    <pre>rm <?php echo __FILE__; ?></pre>
</div>

</body>
</html>
