<?php
// Fix minimo - metti questo nella ROOT di WordPress e vai su: tuosito.it/fix.php

// Trova wp-config.php
$config_paths = [
    __DIR__ . '/wp-config.php',
    __DIR__ . '/../wp-config.php',
    __DIR__ . '/../../wp-config.php',
];

$found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = file_get_contents($path);
        $found = true;
        break;
    }
}

if (!$found) {
    die('Carica questo file nella cartella ROOT di WordPress (dove c\'è wp-config.php)');
}

// Estrai credenziali
preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $config, $db);
preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'/", $config, $user);
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'/", $config, $pass);
preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $config, $host);
preg_match("/\\\$table_prefix\s*=\s*'([^']+)'/", $config, $prefix);

$dbname = $db[1];
$dbuser = $user[1];
$dbpass = $pass[1];
$dbhost = $host[1] ?? 'localhost';
$table_prefix = $prefix[1] ?? 'wp_';

// Connetti
$pdo = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
$timestamp = time();

// Aggiorna
$sql = "INSERT INTO {$table_prefix}options (option_name, option_value, autoload) 
        VALUES ('fp_resv_last_upgrade', :ts, 'no')
        ON DUPLICATE KEY UPDATE option_value = :ts";
$stmt = $pdo->prepare($sql);
$stmt->execute(['ts' => $timestamp]);

// Output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Fix Completato</title>
<style>body{font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;line-height:1.6;}
h1{color:#10b981;}code{background:#f3f4f6;padding:2px 6px;border-radius:3px;}</style>
</head>
<body>
<h1>✅ CACHE AGGIORNATA!</h1>
<p><strong>Nuovo timestamp:</strong> <code><?=$timestamp?></code></p>
<p><strong>Versione asset:</strong> <code>0.1.10.<?=$timestamp?></code></p>
<hr>
<h2>Adesso fai questo:</h2>
<ol>
<li>Vai su <a href="/wp-admin/admin.php?page=fp-resv-agenda" target="_blank">Agenda</a></li>
<li>Premi <strong>F12</strong></li>
<li>Tab <strong>Network</strong></li>
<li>Spunta <strong>"Disable cache"</strong></li>
<li>Premi <strong>Ctrl+Shift+R</strong></li>
<li>Nella Console cerca: <code>[Agenda] Tipo risposta: object</code></li>
</ol>
<p style="color:#ef4444;"><strong>IMPORTANTE:</strong> Se non fai Ctrl+Shift+R vedrai ancora l'errore!</p>
</body>
</html>
<?php
// Auto-elimina per sicurezza
@unlink(__FILE__);
?>
