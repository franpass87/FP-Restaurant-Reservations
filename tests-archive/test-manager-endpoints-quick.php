<?php
/**
 * Test rapido endpoints manager
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Abilita error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test Manager Endpoints</h1>\n";

// Test 1: Verifica che il plugin sia caricato
echo "<h2>1. Plugin Status</h2>\n";
if (class_exists('\FP\Resv\Core\Plugin')) {
    echo "✅ Plugin loaded<br>\n";
} else {
    echo "❌ Plugin NOT loaded<br>\n";
    exit;
}

// Test 2: Verifica REST API base
echo "<h2>2. REST API Base</h2>\n";
$restBase = rest_url('fp-resv/v1');
echo "REST Base: <code>$restBase</code><br>\n";

// Test 3: Test endpoint /agenda
echo "<h2>3. Test /agenda endpoint</h2>\n";
$date = date('Y-m-d');
$url = $restBase . '/agenda?date=' . $date . '&range=day';
echo "URL: <code>$url</code><br>\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? '',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: <strong>$httpCode</strong><br>\n";

if ($error) {
    echo "❌ cURL Error: $error<br>\n";
} elseif ($httpCode === 200) {
    echo "✅ Request successful<br>\n";
    echo "Response length: " . strlen($response) . " bytes<br>\n";
    $data = json_decode($response, true);
    if ($data) {
        echo "✅ Valid JSON<br>\n";
        echo "<pre>";
        print_r(array_keys($data));
        echo "</pre>";
    } else {
        echo "❌ Invalid JSON<br>\n";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>\n";
    }
} else {
    echo "❌ HTTP Error $httpCode<br>\n";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>\n";
}

// Test 4: Test endpoint /agenda/overview
echo "<h2>4. Test /agenda/overview endpoint</h2>\n";
$url = $restBase . '/agenda/overview';
echo "URL: <code>$url</code><br>\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? '',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: <strong>$httpCode</strong><br>\n";

if ($error) {
    echo "❌ cURL Error: $error<br>\n";
} elseif ($httpCode === 200) {
    echo "✅ Request successful<br>\n";
    echo "Response length: " . strlen($response) . " bytes<br>\n";
    $data = json_decode($response, true);
    if ($data) {
        echo "✅ Valid JSON<br>\n";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    } else {
        echo "❌ Invalid JSON<br>\n";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>\n";
    }
} else {
    echo "❌ HTTP Error $httpCode<br>\n";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>\n";
}

echo "<hr>\n";
echo "<h2>Summary</h2>\n";
echo "<p>Se vedi ✅ per tutti i test, gli endpoint funzionano. Se vedi ❌, c'è un problema.</p>\n";
echo "<p><strong>Prossimo step:</strong> Apri la console del browser (F12) sulla pagina del manager e cerca errori JavaScript.</p>\n";

