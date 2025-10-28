<?php
/**
 * Test per verificare le estensioni PHP disponibili sul server
 */

echo "=== TEST ESTENSIONI PHP ===\n";

$required_extensions = [
    'curl'     => 'cURL',
    'json'     => 'JSON', 
    'mbstring' => 'mbstring',
    'ctype'    => 'ctype',
];

echo "Estensioni richieste dal plugin:\n";
foreach ($required_extensions as $ext => $name) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "  {$status} {$name} ({$ext})\n";
}

echo "\nVersione PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";

echo "\nTutte le estensioni caricate:\n";
$loaded_extensions = get_loaded_extensions();
sort($loaded_extensions);
foreach ($loaded_extensions as $ext) {
    echo "  - {$ext}\n";
}

echo "\n=== TEST COMPLETATO ===\n";
?>
