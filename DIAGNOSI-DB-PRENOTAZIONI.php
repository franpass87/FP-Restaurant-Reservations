<?php
/**
 * DIAGNOSI COMPLETA PRENOTAZIONI
 * 
 * Carica questo file via FTP nella root di WordPress e aprilo nel browser:
 * https://tuosito.com/DIAGNOSI-DB-PRENOTAZIONI.php
 * 
 * Questo script verifica:
 * 1. Se le prenotazioni sono nel database
 * 2. Quante sono e gli ultimi inserimenti
 * 3. Se l'endpoint REST /agenda funziona
 */

// Carica WordPress
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../../wp-load.php';

// Se non riesci a caricare WordPress, prova questi percorsi alternativi:
// require_once __DIR__ . '/wp-load.php';  // Se il file è nella root di WP
// require_once __DIR__ . '/../wp-load.php';  // Se il file è in una sottocartella

if (!defined('ABSPATH')) {
    die('❌ Impossibile caricare WordPress. Verifica il percorso del file.');
}

// Stile CSS inline per output carino
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnosi Prenotazioni</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #1e3a8a; margin-top: 0; }
        h2 { color: #2563eb; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
        .success { color: #059669; font-weight: bold; }
        .error { color: #dc2626; font-weight: bold; }
        .warning { color: #d97706; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; }
        tr:hover { background: #f9fafb; }
        .code { background: #1f2937; color: #10b981; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>🔍 Diagnosi Prenotazioni FP Restaurant</h1>

<?php

global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';
$customersTable = $wpdb->prefix . 'fp_customers';

// ============================================================================
// STEP 1: Verifica esistenza tabella
// ============================================================================
echo '<div class="panel">';
echo '<h2>1️⃣ Verifica Tabella Database</h2>';

$tableExists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

if (!$tableExists) {
    echo '<p class="error">❌ PROBLEMA CRITICO: La tabella <code>' . $table . '</code> NON ESISTE!</p>';
    echo '<p>Il plugin non è stato installato correttamente. Disattiva e riattiva il plugin per creare le tabelle.</p>';
    echo '</div></body></html>';
    exit;
}

echo '<p class="success">✅ Tabella <code>' . $table . '</code> esiste</p>';
echo '</div>';

// ============================================================================
// STEP 2: Conta prenotazioni
// ============================================================================
echo '<div class="panel">';
echo '<h2>2️⃣ Statistiche Prenotazioni</h2>';

$totalCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

echo '<p><strong>Totale prenotazioni nel database:</strong> <span class="' . ($totalCount > 0 ? 'success' : 'error') . '">' . $totalCount . '</span></p>';

if ($totalCount === 0) {
    echo '<p class="error">❌ <strong>PROBLEMA TROVATO!</strong></p>';
    echo '<p>Non ci sono prenotazioni nel database. Questo significa che:</p>';
    echo '<ul>';
    echo '<li>Il form NON sta salvando i dati nel database</li>';
    echo '<li>Le email partono ma il record non viene scritto</li>';
    echo '<li>Potrebbe esserci un errore PHP silenzioso durante il salvataggio</li>';
    echo '</ul>';
    echo '<p><strong>SOLUZIONE:</strong> Attiva i log di debug di WordPress e invia una prenotazione di test.</p>';
    echo '</div></body></html>';
    exit;
}

// Statistiche per stato
$statusStats = $wpdb->get_results("
    SELECT status, COUNT(*) as count
    FROM $table
    GROUP BY status
    ORDER BY count DESC
", ARRAY_A);

if ($statusStats) {
    echo '<table>';
    echo '<thead><tr><th>Stato</th><th>Numero</th></tr></thead>';
    echo '<tbody>';
    foreach ($statusStats as $stat) {
        $badgeClass = 'badge badge-' . ($stat['status'] ?? 'pending');
        echo '<tr>';
        echo '<td><span class="' . $badgeClass . '">' . strtoupper($stat['status'] ?? 'N/A') . '</span></td>';
        echo '<td><strong>' . $stat['count'] . '</strong></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// Prenotazioni per data
$today = date('Y-m-d');
$todayCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE date = %s", $today));
$futureCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE date >= %s", $today));

echo '<p><strong>Prenotazioni oggi (' . $today . '):</strong> ' . $todayCount . '</p>';
echo '<p><strong>Prenotazioni future (da oggi in poi):</strong> ' . $futureCount . '</p>';

echo '</div>';

// ============================================================================
// STEP 3: Ultime prenotazioni
// ============================================================================
echo '<div class="panel">';
echo '<h2>3️⃣ Ultime 10 Prenotazioni Inserite</h2>';

$recentReservations = $wpdb->get_results("
    SELECT 
        r.id,
        r.date,
        r.time,
        r.party,
        r.status,
        r.created_at,
        r.customer_id,
        c.first_name,
        c.last_name,
        c.email
    FROM $table r
    LEFT JOIN $customersTable c ON r.customer_id = c.id
    ORDER BY r.created_at DESC
    LIMIT 10
", ARRAY_A);

if ($recentReservations) {
    echo '<table>';
    echo '<thead><tr><th>ID</th><th>Data/Ora</th><th>Persone</th><th>Stato</th><th>Cliente</th><th>Inserito il</th></tr></thead>';
    echo '<tbody>';
    foreach ($recentReservations as $r) {
        $badgeClass = 'badge badge-' . ($r['status'] ?? 'pending');
        echo '<tr>';
        echo '<td>#' . $r['id'] . '</td>';
        echo '<td>' . $r['date'] . ' ' . substr($r['time'], 0, 5) . '</td>';
        echo '<td>' . $r['party'] . '</td>';
        echo '<td><span class="' . $badgeClass . '">' . strtoupper($r['status']) . '</span></td>';
        echo '<td>';
        if ($r['first_name'] || $r['last_name']) {
            echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']);
            if ($r['email']) {
                echo '<br><small>' . htmlspecialchars($r['email']) . '</small>';
            }
        } else if ($r['customer_id']) {
            echo 'Customer ID: ' . $r['customer_id'];
        } else {
            echo '<em>Nessun cliente associato</em>';
        }
        echo '</td>';
        echo '<td>' . $r['created_at'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="warning">Nessuna prenotazione trovata (strano, visto che il conteggio è > 0)</p>';
}

echo '</div>';

// ============================================================================
// STEP 4: Test Endpoint REST
// ============================================================================
echo '<div class="panel">';
echo '<h2>4️⃣ Test Endpoint REST /agenda</h2>';

// Prepara i parametri per testare l'endpoint
$testDate = date('Y-m-d');
$restUrl = rest_url('fp-resv/v1/agenda');
$fullUrl = add_query_arg([
    'date' => $testDate,
    'range' => 'month'
], $restUrl);

echo '<p><strong>Endpoint da testare:</strong><br><code>' . esc_html($fullUrl) . '</code></p>';

// Simula chiamata REST interna
$request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
$request->set_query_params([
    'date' => $testDate,
    'range' => 'month'
]);

// Esegui la richiesta
$response = rest_do_request($request);

if (is_wp_error($response)) {
    echo '<p class="error">❌ Errore nella chiamata REST: ' . $response->get_error_message() . '</p>';
} else {
    $data = $response->get_data();
    $statusCode = $response->get_status();
    
    echo '<p><strong>Status Code:</strong> <span class="' . ($statusCode === 200 ? 'success' : 'error') . '">' . $statusCode . '</span></p>';
    
    if ($statusCode === 200) {
        echo '<p class="success">✅ L\'endpoint risponde correttamente</p>';
        
        // Verifica contenuto risposta
        if (isset($data['reservations']) && is_array($data['reservations'])) {
            $reservationsInResponse = count($data['reservations']);
            echo '<p><strong>Prenotazioni nella risposta:</strong> ' . $reservationsInResponse . '</p>';
            
            if ($reservationsInResponse === 0 && $totalCount > 0) {
                echo '<p class="error">❌ <strong>PROBLEMA TROVATO!</strong></p>';
                echo '<p>Ci sono ' . $totalCount . ' prenotazioni nel DB ma l\'endpoint ne restituisce 0.</p>';
                echo '<p><strong>Possibili cause:</strong></p>';
                echo '<ul>';
                echo '<li>Le prenotazioni sono in date diverse dal mese corrente</li>';
                echo '<li>C\'è un filtro che esclude le prenotazioni dalla risposta</li>';
                echo '<li>C\'è un problema nella query SQL dell\'endpoint</li>';
                echo '</ul>';
            } else if ($reservationsInResponse > 0) {
                echo '<p class="success">✅ L\'endpoint restituisce correttamente le prenotazioni!</p>';
                echo '<p><strong>Prime 3 prenotazioni nella risposta:</strong></p>';
                echo '<div class="code"><pre>' . print_r(array_slice($data['reservations'], 0, 3), true) . '</pre></div>';
            }
        } else {
            echo '<p class="warning">⚠️ La risposta non contiene l\'array "reservations"</p>';
            echo '<div class="code"><pre>' . print_r($data, true) . '</pre></div>';
        }
    } else {
        echo '<p class="error">❌ L\'endpoint ha restituito un errore</p>';
        echo '<div class="code"><pre>' . print_r($data, true) . '</pre></div>';
    }
}

echo '</div>';

// ============================================================================
// STEP 5: Verifica permissions
// ============================================================================
echo '<div class="panel">';
echo '<h2>5️⃣ Verifica Permessi</h2>';

$currentUser = wp_get_current_user();
if ($currentUser->ID > 0) {
    echo '<p class="success">✅ Utente loggato: <strong>' . $currentUser->user_login . '</strong></p>';
    echo '<p>Ruoli: ' . implode(', ', $currentUser->roles) . '</p>';
    
    if (current_user_can('manage_fp_reservations')) {
        echo '<p class="success">✅ Hai i permessi per gestire le prenotazioni</p>';
    } else {
        echo '<p class="error">❌ Non hai i permessi per gestire le prenotazioni</p>';
        echo '<p>Il manager potrebbe non funzionare correttamente.</p>';
    }
} else {
    echo '<p class="warning">⚠️ Non sei loggato. Il manager richiede autenticazione.</p>';
}

echo '</div>';

// ============================================================================
// STEP 6: Range date prenotazioni
// ============================================================================
echo '<div class="panel">';
echo '<h2>6️⃣ Range Date Prenotazioni</h2>';

$dateRange = $wpdb->get_row("
    SELECT 
        MIN(date) as prima_data,
        MAX(date) as ultima_data
    FROM $table
", ARRAY_A);

if ($dateRange) {
    echo '<p><strong>Prima prenotazione:</strong> ' . ($dateRange['prima_data'] ?? 'N/A') . '</p>';
    echo '<p><strong>Ultima prenotazione:</strong> ' . ($dateRange['ultima_data'] ?? 'N/A') . '</p>';
    
    // Se tutte le prenotazioni sono nel passato
    if ($dateRange['ultima_data'] && strtotime($dateRange['ultima_data']) < strtotime($today)) {
        echo '<p class="warning">⚠️ <strong>ATTENZIONE:</strong> Tutte le prenotazioni sono nel passato!</p>';
        echo '<p>Il manager di default mostra il mese corrente, quindi non vedrà prenotazioni vecchie.</p>';
    }
}

echo '</div>';

// ============================================================================
// RIEPILOGO FINALE
// ============================================================================
echo '<div class="panel">';
echo '<h2>📋 Riepilogo Diagnosi</h2>';

if ($totalCount === 0) {
    echo '<p class="error"><strong>❌ PROBLEMA: Nessuna prenotazione nel database</strong></p>';
    echo '<p>Il form non sta salvando i dati. Verifica gli errori PHP.</p>';
} else if ($reservationsInResponse === 0 && $totalCount > 0) {
    echo '<p class="error"><strong>❌ PROBLEMA: Prenotazioni nel DB ma l\'endpoint non le restituisce</strong></p>';
    echo '<p>Il problema è nell\'endpoint REST /agenda, non nel salvataggio.</p>';
} else if ($reservationsInResponse > 0) {
    echo '<p class="success"><strong>✅ TUTTO OK!</strong></p>';
    echo '<p>Ci sono prenotazioni nel DB e l\'endpoint le restituisce correttamente.</p>';
    echo '<p>Se il manager non mostra nulla, il problema è nel JavaScript frontend.</p>';
    echo '<p><strong>Cosa controllare:</strong></p>';
    echo '<ul>';
    echo '<li>Console JavaScript del browser (F12) per errori</li>';
    echo '<li>Verifica che il nonce sia valido</li>';
    echo '<li>Cancella la cache del browser</li>';
    echo '</ul>';
} else {
    echo '<p class="warning"><strong>⚠️ Diagnosi incompleta</strong></p>';
    echo '<p>Alcune informazioni mancano. Ricarica la pagina.</p>';
}

echo '</div>';

?>

<div class="panel">
    <h2>🔧 Prossimi Passi</h2>
    <p>In base ai risultati sopra, ecco cosa fare:</p>
    <ol>
        <li>Se <strong>non ci sono prenotazioni nel DB</strong>: il form non salva → attiva debug WP</li>
        <li>Se <strong>ci sono prenotazioni ma l'endpoint ne restituisce 0</strong>: problema nella query dell'endpoint</li>
        <li>Se <strong>tutto funziona ma il manager non mostra nulla</strong>: problema JavaScript → controlla console browser</li>
    </ol>
    <p><strong>Dopo aver visto i risultati, copia l'output e mandamelo per aiutarti!</strong></p>
</div>

<p style="text-align: center; color: #6b7280; margin-top: 40px;">
    <small>Script diagnostico FP Restaurant Reservations v1.0 - <?php echo date('Y-m-d H:i:s'); ?></small>
</p>

</body>
</html>
<?php

