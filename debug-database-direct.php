<?php
/**
 * Script di debug diretto per verificare le prenotazioni nel database
 * Eseguilo via browser: http://tuosito.com/wp-content/plugins/fp-restaurant-reservations/debug-database-direct.php
 */

// Configurazione database - MODIFICA QUESTI VALORI SE NECESSARIO
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASSWORD', 'your_database_password');
define('DB_HOST', 'localhost');
define('DB_PREFIX', 'wp_'); // Modifica se usi un prefisso diverso

// Prova a caricare wp-config.php se esiste
$wpConfigPath = __DIR__ . '/../../../wp-config.php';
if (file_exists($wpConfigPath)) {
    require_once $wpConfigPath;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPassword = DB_PASSWORD;
    $dbHost = DB_HOST;
    $dbPrefix = isset($table_prefix) ? $table_prefix : 'wp_';
} else {
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPassword = DB_PASSWORD;
    $dbHost = DB_HOST;
    $dbPrefix = DB_PREFIX;
}

// Connessione database
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ ERRORE CONNESSIONE DATABASE: " . $e->getMessage() . "\n\n" . 
        "Per favore verifica le credenziali all'inizio di questo file.");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Database Prenotazioni</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        .success { color: #46b450; font-weight: bold; }
        .error { color: #dc3232; font-weight: bold; }
        .warning { color: #f56e28; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #0073aa; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .stat { display: inline-block; margin: 10px 20px 10px 0; padding: 15px 25px; background: #e8f5e9; border-radius: 5px; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .stat-value { font-size: 24px; font-weight: bold; color: #2e7d32; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .debug-section { margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #f56e28; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Debug Database Prenotazioni</h1>

<?php

// 1. Verifica esistenza tabella
$tableName = $dbPrefix . 'fp_reservations';
$customersTable = $dbPrefix . 'fp_customers';

$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
$stmt->execute([$tableName]);
$tableExists = $stmt->fetch();

if (!$tableExists) {
    echo "<p class='error'>❌ ERRORE: La tabella $tableName NON ESISTE!</p>";
    echo "<p>Il plugin potrebbe non essere installato correttamente.</p>";
    exit;
}

echo "<p class='success'>✅ Tabella $tableName esiste</p>";

// 2. Conta totale prenotazioni
$stmt = $pdo->query("SELECT COUNT(*) as total FROM $tableName");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "<h2>📊 Statistiche Generali</h2>";
echo "<div class='stat'>";
echo "<div class='stat-label'>Totale Prenotazioni</div>";
echo "<div class='stat-value'>$total</div>";
echo "</div>";

if ($total == 0) {
    echo "<div class='debug-section'>";
    echo "<h3 class='error'>⚠️ PROBLEMA IDENTIFICATO!</h3>";
    echo "<p><strong>Non ci sono prenotazioni nel database.</strong></p>";
    echo "<p>Questo significa che il form NON sta salvando le prenotazioni nel database, anche se invia le email correttamente.</p>";
    echo "<h4>Possibili cause:</h4>";
    echo "<ul>";
    echo "<li>Il metodo <code>Repository->insert()</code> fallisce silenziosamente</li>";
    echo "<li>C'è un errore nel metodo <code>Service->create()</code></li>";
    echo "<li>La transazione viene fatta rollback</li>";
    echo "<li>C'è un problema con i permessi del database</li>";
    echo "</ul>";
    echo "</div>";
    
    // Mostra struttura tabella
    echo "<h3>Struttura Tabella</h3>";
    $stmt = $pdo->query("DESCRIBE $tableName");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    exit;
}

// 3. Prenotazioni per stato
echo "<h2>📈 Prenotazioni per Stato</h2>";
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM $tableName 
    GROUP BY status 
    ORDER BY count DESC
");
$statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table><tr><th>Stato</th><th>Numero</th></tr>";
foreach ($statuses as $s) {
    echo "<tr><td>{$s['status']}</td><td><strong>{$s['count']}</strong></td></tr>";
}
echo "</table>";

// 4. Ultime 20 prenotazioni
echo "<h2>📝 Ultime 20 Prenotazioni</h2>";
$stmt = $pdo->query("
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
    FROM $tableName r
    LEFT JOIN $customersTable c ON r.customer_id = c.id
    ORDER BY r.created_at DESC
    LIMIT 20
");
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr>
        <th>ID</th>
        <th>Data</th>
        <th>Ora</th>
        <th>Persone</th>
        <th>Stato</th>
        <th>Cliente</th>
        <th>Email</th>
        <th>Creata il</th>
      </tr>";

foreach ($reservations as $r) {
    echo "<tr>";
    echo "<td><strong>#{$r['id']}</strong></td>";
    echo "<td>{$r['date']}</td>";
    echo "<td>" . substr($r['time'], 0, 5) . "</td>";
    echo "<td>{$r['party']}</td>";
    echo "<td><span style='padding:3px 8px;background:#e3f2fd;border-radius:3px'>{$r['status']}</span></td>";
    $customerName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    echo "<td>" . ($customerName ?: 'N/A') . "</td>";
    echo "<td>" . ($r['email'] ?? 'N/A') . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($r['created_at'])) . "</td>";
    echo "</tr>";
}
echo "</table>";

// 5. Prenotazioni per periodo
echo "<h2>📅 Prenotazioni per Periodo</h2>";
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$monthAgo = date('Y-m-d', strtotime('-30 days'));

$queries = [
    'Oggi' => ['SELECT COUNT(*) as count FROM ' . $tableName . ' WHERE date = ?', [$today]],
    'Ieri' => ['SELECT COUNT(*) as count FROM ' . $tableName . ' WHERE date = ?', [$yesterday]],
    'Ultimi 7 giorni' => ['SELECT COUNT(*) as count FROM ' . $tableName . ' WHERE date >= ?', [$weekAgo]],
    'Ultimi 30 giorni' => ['SELECT COUNT(*) as count FROM ' . $tableName . ' WHERE date >= ?', [$monthAgo]],
    'Future' => ['SELECT COUNT(*) as count FROM ' . $tableName . ' WHERE date >= ?', [$today]],
];

echo "<table><tr><th>Periodo</th><th>Numero Prenotazioni</th></tr>";
foreach ($queries as $label => $queryData) {
    $stmt = $pdo->prepare($queryData[0]);
    $stmt->execute($queryData[1]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<tr><td>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</td><td><strong>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</strong></td></tr>";
}
echo "</table>";

// 6. Test di scrittura (opzionale)
echo "<h2>🔧 Test Scrittura Database</h2>";
echo "<div class='debug-section'>";
echo "<p>Vuoi testare se il database accetta scritture? Questo creerà una prenotazione di test.</p>";

if (isset($_GET['test_write'])) {
    try {
        $testData = [
            'status' => 'pending',
            'date' => date('Y-m-d', strtotime('+1 day')),
            'time' => '19:00:00',
            'party' => 2,
            'notes' => 'TEST DEBUG SCRIPT - CANCELLAMI',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        $fields = implode(', ', array_keys($testData));
        $placeholders = implode(', ', array_fill(0, count($testData), '?'));
        
        $stmt = $pdo->prepare("INSERT INTO $tableName ($fields) VALUES ($placeholders)");
        $stmt->execute(array_values($testData));
        
        $insertId = $pdo->lastInsertId();
        
        echo "<p class='success'>✅ Test scrittura RIUSCITO! ID inserito: $insertId</p>";
        echo "<p>Questo significa che il database accetta scritture correttamente.</p>";
        echo "<p><a href='?'>Ricarica pagina</a> per vedere la prenotazione di test.</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Test scrittura FALLITO: " . $e->getMessage() . "</p>";
        echo "<p>Questo potrebbe indicare un problema di permessi sul database.</p>";
    }
} else {
    echo "<p><a href='?test_write=1' style='display:inline-block;padding:10px 20px;background:#0073aa;color:white;text-decoration:none;border-radius:5px'>Esegui Test Scrittura</a></p>";
}
echo "</div>";

// 7. Verifica customer_id
echo "<h2>🔗 Verifica Relazioni Customer</h2>";
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END) as without_customer,
        SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as with_customer
    FROM $tableName
");
$customerStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Descrizione</th><th>Numero</th></tr>";
echo "<tr><td>Con customer_id</td><td><strong>{$customerStats['with_customer']}</strong></td></tr>";
echo "<tr><td>Senza customer_id</td><td><strong>{$customerStats['without_customer']}</strong></td></tr>";
echo "</table>";

if ($customerStats['without_customer'] > 0) {
    echo "<p class='warning'>⚠️ Attenzione: ci sono {$customerStats['without_customer']} prenotazioni senza customer_id. Questo potrebbe causare problemi di visualizzazione.</p>";
}

?>

<h2>✅ Diagnosi Completata</h2>
<p>Script eseguito con successo il <?= date('d/m/Y H:i:s') ?></p>
<p><a href="?" style="display:inline-block;padding:10px 20px;background:#0073aa;color:white;text-decoration:none;border-radius:5px">Ricarica Pagina</a></p>

</div>
</body>
</html>

