<?php
/**
 * TEST RAPIDO AGENDA - Per terminale/SSH
 * 
 * Esegui: php test-agenda-quick.php
 */

// Bootstrap WordPress
define('WP_USE_THEMES', false);
require_once __DIR__ . '/wp-load.php';

echo "\n===========================================\n";
echo "  TEST RAPIDO AGENDA FP RESERVATIONS\n";
echo "===========================================\n\n";

$passed = 0;
$failed = 0;

// Test 1: Plugin attivo
echo "1. Plugin attivo... ";
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if (is_plugin_active('fp-restaurant-reservations/fp-restaurant-reservations.php')) {
    echo "✅ OK\n";
    $passed++;
} else {
    echo "❌ FALLITO\n";
    $failed++;
}

// Test 2: Tabelle database
echo "2. Tabelle database... ";
global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';
if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    echo "✅ OK ($count prenotazioni)\n";
    $passed++;
} else {
    echo "❌ FALLITO (tabella non esiste)\n";
    $failed++;
}

// Test 3: Endpoint REST
echo "3. Endpoint REST... ";
$request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
$request->set_param('date', date('Y-m-d'));
$response = rest_do_request($request);

if (!$response->is_error()) {
    $status = $response->get_status();
    echo "✅ OK (HTTP $status)\n";
    $passed++;
    
    // Mostra struttura risposta
    $data = $response->get_data();
    if (is_array($data)) {
        echo "   Tipo: Array (" . count($data) . " elementi)\n";
    } elseif (isset($data['reservations'])) {
        echo "   Tipo: Oggetto strutturato (" . count($data['reservations']) . " prenotazioni)\n";
        if (isset($data['stats'])) {
            echo "   Stats: Totale " . $data['stats']['total'] . ", Confermati " . $data['stats']['confirmed'] . "\n";
        }
    }
} else {
    echo "❌ FALLITO\n";
    echo "   Errore: " . $response->as_error()->get_error_message() . "\n";
    $failed++;
}

// Test 4: File JavaScript
echo "4. File JavaScript... ";
$jsFile = WP_PLUGIN_DIR . '/fp-restaurant-reservations/assets/js/admin/agenda-app.js';
if (file_exists($jsFile)) {
    echo "✅ OK (" . round(filesize($jsFile)/1024, 1) . " KB)\n";
    $passed++;
} else {
    echo "❌ FALLITO\n";
    $failed++;
}

// Test 5: Permessi utente
echo "5. Permessi utente... ";
if (current_user_can('manage_options') || current_user_can('manage_fp_reservations')) {
    $user = wp_get_current_user();
    echo "✅ OK (utente: " . $user->user_login . ")\n";
    $passed++;
} else {
    echo "❌ FALLITO (permessi insufficienti)\n";
    $failed++;
}

// Riepilogo
echo "\n===========================================\n";
echo "  RIEPILOGO\n";
echo "===========================================\n";
echo "Test passati: $passed\n";
echo "Test falliti: $failed\n";

if ($failed === 0) {
    echo "\n✅ Tutti i test sono passati!\n";
    echo "\nSe l'agenda non funziona ancora:\n";
    echo "1. Apri la Console del browser (F12)\n";
    echo "2. Cerca messaggi [Agenda] o errori in rosso\n";
    echo "3. Esegui: php DIAGNOSTICA-AGENDA-COMPLETA.php\n";
} else {
    echo "\n❌ Alcuni test sono falliti. Verifica i problemi sopra.\n";
}

echo "\n";

