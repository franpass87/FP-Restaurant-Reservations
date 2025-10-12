<?php
/**
 * Script di verifica endpoint agenda
 * 
 * Questo script testa:
 * 1. Che l'endpoint /agenda-test funzioni
 * 2. Che l'endpoint /agenda funzioni con e senza autenticazione
 * 3. Che i dati vengano restituiti correttamente
 */

// Carica WordPress
require_once __DIR__ . '/wp-load.php';

// Forza header JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$results = [
    'timestamp' => current_time('mysql'),
    'tests' => [],
    'summary' => [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
    ]
];

/**
 * Test helper
 */
function runTest($name, $callable) {
    global $results;
    
    $results['summary']['total']++;
    
    try {
        $result = $callable();
        $results['tests'][] = [
            'name' => $name,
            'status' => 'PASS',
            'result' => $result,
        ];
        $results['summary']['passed']++;
        return true;
    } catch (Exception $e) {
        $results['tests'][] = [
            'name' => $name,
            'status' => 'FAIL',
            'error' => $e->getMessage(),
        ];
        $results['summary']['failed']++;
        return false;
    }
}

// TEST 1: Verifica che l'endpoint test sia registrato
runTest('Endpoint /agenda-test è registrato', function() {
    $routes = rest_get_server()->get_routes();
    if (!isset($routes['/fp-resv/v1/agenda-test'])) {
        throw new Exception('Endpoint /agenda-test NON registrato');
    }
    return 'Endpoint registrato correttamente';
});

// TEST 2: Verifica che l'endpoint agenda sia registrato
runTest('Endpoint /agenda è registrato', function() {
    $routes = rest_get_server()->get_routes();
    if (!isset($routes['/fp-resv/v1/agenda'])) {
        throw new Exception('Endpoint /agenda NON registrato');
    }
    return 'Endpoint registrato correttamente';
});

// TEST 3: Chiamata diretta all'endpoint test
runTest('Chiamata diretta endpoint /agenda-test', function() {
    $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda-test');
    $response = rest_get_server()->dispatch($request);
    
    if ($response->is_error()) {
        throw new Exception('Errore nella risposta: ' . $response->get_error_message());
    }
    
    $data = $response->get_data();
    if (!isset($data['success']) || $data['success'] !== true) {
        throw new Exception('Risposta non contiene success: true');
    }
    
    return $data;
});

// TEST 4: Verifica che AdminREST sia inizializzato
runTest('Classe AdminREST esiste', function() {
    if (!class_exists('FP\Resv\Domain\Reservations\AdminREST')) {
        throw new Exception('Classe AdminREST non trovata');
    }
    return 'Classe trovata';
});

// TEST 5: Verifica permessi utente corrente
runTest('Verifica permessi utente', function() {
    $user_id = get_current_user_id();
    $can_manage = current_user_can('manage_options');
    
    return [
        'user_id' => $user_id,
        'is_logged_in' => is_user_logged_in(),
        'can_manage_options' => $can_manage,
    ];
});

// TEST 6: Test query database prenotazioni
runTest('Query database prenotazioni', function() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'fp_reservations';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    
    if ($wpdb->last_error) {
        throw new Exception('Errore SQL: ' . $wpdb->last_error);
    }
    
    return [
        'total_reservations' => (int) $count,
        'table_name' => $table,
    ];
});

// TEST 7: Chiamata simulata all'endpoint agenda (se l'utente è autenticato)
if (is_user_logged_in() && current_user_can('manage_options')) {
    runTest('Chiamata simulata endpoint /agenda', function() {
        $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
        $request->set_param('date', date('Y-m-d'));
        
        $response = rest_get_server()->dispatch($request);
        
        if ($response->is_error()) {
            throw new Exception('Errore: ' . $response->get_error_message());
        }
        
        $data = $response->get_data();
        
        // Verifica struttura risposta
        if (!is_array($data)) {
            throw new Exception('Risposta non è un array, è: ' . gettype($data));
        }
        
        if (!isset($data['reservations'])) {
            throw new Exception('Risposta non contiene chiave reservations');
        }
        
        return [
            'keys' => array_keys($data),
            'reservations_count' => count($data['reservations']),
            'has_meta' => isset($data['meta']),
            'has_stats' => isset($data['stats']),
            'has_data' => isset($data['data']),
        ];
    });
} else {
    $results['tests'][] = [
        'name' => 'Chiamata simulata endpoint /agenda',
        'status' => 'SKIP',
        'reason' => 'Utente non autenticato o senza permessi',
    ];
}

// TEST 8: Verifica file di log
runTest('Verifica file di log', function() {
    $logFile = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/agenda-endpoint-calls.log' : '/tmp/agenda-endpoint-calls.log';
    
    $exists = file_exists($logFile);
    $writable = is_writable(dirname($logFile));
    $size = $exists ? filesize($logFile) : 0;
    
    return [
        'log_file' => $logFile,
        'exists' => $exists,
        'dir_writable' => $writable,
        'size_bytes' => $size,
        'last_lines' => $exists && $size > 0 ? array_slice(file($logFile), -10) : [],
    ];
});

// Output risultati
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
