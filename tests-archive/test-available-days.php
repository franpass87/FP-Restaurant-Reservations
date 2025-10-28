<?php
/**
 * Test script per l'endpoint available-days
 */

// Carica WordPress
require_once '../../../wp-load.php';

// Test dell'endpoint
$url = home_url('/wp-json/fp-resv/v1/available-days');
$params = [
    'from' => '2025-10-19',
    'to' => '2026-01-19',
    'meal' => 'pranzo'
];

$full_url = $url . '?' . http_build_query($params);

echo "Testing URL: " . $full_url . "\n\n";

// Test con wp_remote_get
$response = wp_remote_get($full_url);

if (is_wp_error($response)) {
    echo "WP Error: " . $response->get_error_message() . "\n";
} else {
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "Status Code: " . $status_code . "\n";
    echo "Response Body:\n" . $body . "\n";
    
    // Prova a decodificare JSON
    $json = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\nJSON decodificato correttamente:\n";
        print_r($json);
    } else {
        echo "\nErrore JSON: " . json_last_error_msg() . "\n";
    }
}

// Test diretto della classe Availability
echo "\n\n=== Test diretto della classe Availability ===\n";

try {
    $availability = new \FP\Domain\Reservations\Availability();
    $result = $availability->findAvailableDaysForAllMeals('2025-10-19', '2026-01-19');
    echo "Risultato Availability:\n";
    print_r($result);
} catch (Exception $e) {
    echo "Errore Availability: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
