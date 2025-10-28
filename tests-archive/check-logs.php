<?php
/**
 * Script per visualizzare facilmente i log del plugin
 * Esegui via browser
 */

// Carica WordPress
$wpLoadPaths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
];

$loaded = false;
foreach ($wpLoadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die("❌ Impossibile caricare WordPress. Esegui questo script dal browser.");
}

// Solo amministratori
if (!current_user_can('manage_options')) {
    die("❌ Non hai i permessi per accedere a questa pagina.");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Log Plugin Prenotazioni</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #4ec9b0; }
        .log-entry { margin: 10px 0; padding: 10px; border-left: 3px solid #4ec9b0; background: #252526; }
        .log-error { border-left-color: #f48771; }
        .log-success { border-left-color: #b5cea8; }
        .timestamp { color: #808080; }
        .context { color: #ce9178; margin-left: 20px; }
        .button { display: inline-block; padding: 10px 20px; background: #0e639c; color: white; text-decoration: none; border-radius: 3px; margin: 10px 5px; }
        .stats { background: #252526; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .filter { margin: 20px 0; padding: 15px; background: #2d2d30; border-radius: 5px; }
        input, select { padding: 8px; margin: 5px; background: #3c3c3c; color: #d4d4d4; border: 1px solid #555; }
    </style>
</head>
<body>
<div class="container">
    <h1>📋 Log Plugin Prenotazioni</h1>
    
    <?php
    // Leggi i log da wp-content/uploads/fp-logs/
    $logDir = WP_CONTENT_DIR . '/uploads/fp-logs';
    $logFile = $logDir . '/reservations.log';
    
    if (!file_exists($logFile)) {
        echo "<p style='color:#f48771'>⚠️ File di log non trovato: $logFile</p>";
        echo "<p>I log potrebbero non essere abilitati o non ci sono ancora prenotazioni.</p>";
        
        // Mostra file disponibili
        if (is_dir($logDir)) {
            $files = scandir($logDir);
            $files = array_filter($files, fn($f) => $f !== '.' && $f !== '..');
            
            if (!empty($files)) {
                echo "<h3>File disponibili:</h3><ul>";
                foreach ($files as $file) {
                    $path = $logDir . '/' . $file;
                    $size = filesize($path);
                    echo "<li><strong>$file</strong> (" . number_format($size) . " bytes)</li>";
                }
                echo "</ul>";
            }
        }
        
        echo "<hr>";
        echo "<h3>Verifica rapida database:</h3>";
        
        global $wpdb;
        $table = $wpdb->prefix . 'fp_reservations';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        echo "<div class='stats'>";
        echo "<strong>Prenotazioni nel database: $count</strong><br>";
        
        if ($count > 0) {
            echo "<br>✅ Ci sono prenotazioni nel database!<br>";
            echo "Il problema potrebbe essere nella visualizzazione del manager.<br><br>";
            
            // Ultime 5
            $recent = $wpdb->get_results("
                SELECT id, date, time, party, status, created_at
                FROM $table
                ORDER BY created_at DESC
                LIMIT 5
            ", ARRAY_A);
            
            echo "<strong>Ultime 5 prenotazioni:</strong><br>";
            foreach ($recent as $r) {
                echo "- ID {$r['id']}: {$r['date']} {$r['time']} - {$r['party']} persone - Status: {$r['status']}<br>";
            }
        } else {
            echo "<br>❌ Non ci sono prenotazioni nel database!<br>";
            echo "Il problema è sicuramente nel salvataggio.<br>";
        }
        echo "</div>";
        
        exit;
    }
    
    // Leggi il file di log
    $lines = file($logFile);
    $lines = array_reverse($lines); // Mostra i più recenti per primi
    
    // Filtri
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    echo "<div class='filter'>";
    echo "<form method='get'>";
    echo "Filtra: ";
    echo "<select name='filter'>";
    echo "<option value='all'" . ($filter === 'all' ? ' selected' : '') . ">Tutti</option>";
    echo "<option value='error'" . ($filter === 'error' ? ' selected' : '') . ">Solo Errori</option>";
    echo "<option value='success'" . ($filter === 'success' ? ' selected' : '') . ">Solo Success</option>";
    echo "<option value='insert'" . ($filter === 'insert' ? ' selected' : '') . ">Solo INSERT</option>";
    echo "<option value='transaction'" . ($filter === 'transaction' ? ' selected' : '') . ">Transazioni</option>";
    echo "</select> ";
    echo "Cerca: <input type='text' name='search' value='" . esc_attr($search) . "' placeholder='Cerca nel log'> ";
    echo "Righe: <input type='number' name='limit' value='$limit' min='10' max='1000' style='width:80px'> ";
    echo "<button type='submit' class='button'>Filtra</button> ";
    echo "<a href='?' class='button'>Reset</a>";
    echo "</form>";
    echo "</div>";
    
    $displayed = 0;
    $total = count($lines);
    
    echo "<div class='stats'>";
    echo "📊 <strong>Totale righe nel log:</strong> " . number_format($total);
    echo "</div>";
    
    echo "<h2>Log Entries</h2>";
    
    foreach ($lines as $line) {
        if ($displayed >= $limit) {
            break;
        }
        
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        // Applica filtri
        if ($filter === 'error' && stripos($line, 'error') === false && stripos($line, '❌') === false) {
            continue;
        }
        if ($filter === 'success' && stripos($line, 'success') === false && stripos($line, '✅') === false) {
            continue;
        }
        if ($filter === 'insert' && stripos($line, 'INSERT') === false && stripos($line, 'insert') === false) {
            continue;
        }
        if ($filter === 'transaction' && stripos($line, 'transaction') === false && stripos($line, 'commit') === false && stripos($line, 'rollback') === false) {
            continue;
        }
        if ($search !== '' && stripos($line, $search) === false) {
            continue;
        }
        
        // Determina classe CSS
        $class = 'log-entry';
        if (stripos($line, 'error') !== false || stripos($line, '❌') !== false) {
            $class .= ' log-error';
        } elseif (stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
            $class .= ' log-success';
        }
        
        echo "<div class='$class'>" . htmlspecialchars($line) . "</div>";
        $displayed++;
    }
    
    if ($displayed === 0) {
        echo "<p>Nessuna entry trovata con i filtri selezionati.</p>";
    } else {
        echo "<p style='color:#808080; margin-top:20px'>Mostrate $displayed di $total righe totali.</p>";
    }
    ?>
    
    <hr style="margin:40px 0; border:none; border-top:1px solid #555">
    
    <h2>🔍 Debug Rapido Database</h2>
    
    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'fp_reservations';
    $customersTable = $wpdb->prefix . 'fp_customers';
    
    $totalReservations = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $totalCustomers = $wpdb->get_var("SELECT COUNT(*) FROM $customersTable");
    $recentCount = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    
    echo "<div class='stats'>";
    echo "📊 <strong>Statistiche Database:</strong><br><br>";
    echo "Totale Prenotazioni: <strong>$totalReservations</strong><br>";
    echo "Totale Clienti: <strong>$totalCustomers</strong><br>";
    echo "Prenotazioni ultime 24h: <strong>$recentCount</strong><br>";
    echo "</div>";
    
    // Ultime 10 prenotazioni
    $recent = $wpdb->get_results("
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
        LEFT JOIN $customersTable c ON r.customer_id = c.id
        ORDER BY r.created_at DESC
        LIMIT 10
    ", ARRAY_A);
    
    if (!empty($recent)) {
        echo "<h3>Ultime 10 Prenotazioni:</h3>";
        echo "<div class='stats'>";
        foreach ($recent as $r) {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            echo "<strong>ID {$r['id']}</strong>: {$r['date']} " . substr($r['time'], 0, 5) . " | {$r['party']} persone | {$r['status']} | ";
            echo ($name ?: 'N/A') . " ({$r['email']}) | Creata: " . date('d/m H:i', strtotime($r['created_at'])) . "<br>";
        }
        echo "</div>";
    }
    ?>
    
    <p style="margin-top:40px; color:#808080">
        <a href="?" class="button">Ricarica</a>
        <a href="<?= admin_url('admin.php?page=fp-restaurant-reservations') ?>" class="button">Torna al Manager</a>
    </p>
    
</div>
</body>
</html>

