<?php
// Test script per verificare se l'endpoint è raggiungibile
// Questo script simula una chiamata all'endpoint agenda

// Trova WordPress
$wp_load = dirname(__FILE__) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("WordPress non trovato\n");
}

require_once $wp_load;

// Simula una richiesta REST
$url = rest_url('fp-resv/v1/agenda?date=' . date('Y-m-d'));
echo "Testing endpoint: $url\n\n";

// Test 1: Verifica se l'endpoint è registrato
$server = rest_get_server();
$routes = $server->get_routes();
echo "Endpoint /fp-resv/v1/agenda is registered: " . (isset($routes['/fp-resv/v1/agenda']) ? "YES" : "NO") . "\n";

// Test 2: Verifica permessi utente corrente
echo "Current user can manage_fp_reservations: " . (current_user_can('manage_fp_reservations') ? "YES" : "NO") . "\n";
echo "Current user can manage_options: " . (current_user_can('manage_options') ? "YES" : "NO") . "\n";

// Test 3: Prova a fare una chiamata diretta
if (is_user_logged_in()) {
    $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
    $request->set_param('date', date('Y-m-d'));
    $response = $server->dispatch($request);
    $data = $response->get_data();
    
    echo "\nResponse status: " . $response->get_status() . "\n";
    echo "Response data type: " . gettype($data) . "\n";
    if (is_array($data)) {
        echo "Number of reservations: " . count($data) . "\n";
    } else {
        echo "Response data: " . print_r($data, true) . "\n";
    }
} else {
    echo "\nNo user logged in\n";
}

echo "\nTest completato\n";
