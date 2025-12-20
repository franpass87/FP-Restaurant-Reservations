<?php
/**
 * Test Backend API - Creazione Prenotazione
 * Testa la creazione di prenotazioni tramite API REST
 */

// Load WordPress
$wp_load = dirname(dirname(__DIR__)) . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}

require_once $wp_load;

// Simula un utente admin
$admin_users = get_users(['role' => 'administrator', 'number' => 1]);
if (!empty($admin_users)) {
    wp_set_current_user($admin_users[0]->ID);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Backend API</title>
    <style>
        body { font-family: Arial; max-width: 1000px; margin: 20px auto; padding: 20px; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🧪 Test Backend API - Prenotazioni</h1>
    
    <?php
    // Test 1: Prenotazione con dati completi
    if (isset($_GET['test']) && $_GET['test'] === 'complete') {
        echo '<div class="test">';
        echo '<h2>Test 1: Prenotazione con dati completi</h2>';
        
        try {
            // Crea una richiesta REST mock
            $data = [
                'date' => date('Y-m-d', strtotime('+3 days')),
                'time' => '19:00',
                'party' => 4,
                'meal' => 'dinner',
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'email' => 'mario.rossi@example.com',
                'phone' => '+39 333 1234567',
                'notes' => 'Tavolo vicino alla finestra',
                'status' => 'confirmed'
            ];
            
            // Crea una WP_REST_Request mock
            $request = new WP_REST_Request('POST', '/fp-resv/v1/agenda/reservations');
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
            
            // Ottieni l'istanza di AdminREST dal container/registry
            // Prova a ottenere tramite global o hook
            global $fp_resv_admin_rest;
            
            if (!isset($fp_resv_admin_rest)) {
                // Prova a ottenere tramite ServiceRegistry o Container
                if (class_exists('\FP\Resv\Core\ServiceRegistry')) {
                    // Non possiamo ottenere facilmente, usiamo direttamente il Use Case
                    global $wpdb;
                    $repository = new \FP\Resv\Domain\Reservations\Repository($wpdb);
                    $reservationService = new \FP\Resv\Domain\Reservations\Service($repository);
                    $validator = new \FP\Resv\Core\Services\Validator();
                    $logger = new \FP\Resv\Core\Services\Logger();
                    $createUseCase = new \FP\Resv\Application\Reservations\CreateReservationUseCase(
                        $reservationService,
                        $validator,
                        $logger
                    );
                    
                    // Estrai il payload come farebbe ReservationPayloadExtractor
                    $payload = [
                        'date' => sanitize_text_field($data['date']),
                        'time' => sanitize_text_field($data['time']),
                        'party' => absint($data['party']),
                        'meal' => sanitize_text_field($data['meal']),
                        'first_name' => sanitize_text_field($data['first_name']),
                        'last_name' => sanitize_text_field($data['last_name']),
                        'email' => sanitize_email($data['email']),
                        'phone' => sanitize_text_field($data['phone']),
                        'notes' => sanitize_textarea_field($data['notes'] ?? ''),
                        'status' => sanitize_text_field($data['status']),
                        'allow_partial_contact' => true, // Per backend
                    ];
                    
                    $reservation = $createUseCase->execute($payload);
                    $reservationId = $reservation->getId();
                    
                    echo '<div class="success">';
                    echo '<p><strong>✅ Successo!</strong> Prenotazione creata con ID: ' . esc_html($reservationId) . '</p>';
                    echo '<p><strong>Dati:</strong></p>';
                    echo '<ul>';
                    echo '<li>Data: ' . esc_html($reservation->getDate()) . '</li>';
                    echo '<li>Orario: ' . esc_html($reservation->getTime()) . '</li>';
                    echo '<li>Persone: ' . esc_html($reservation->getParty()) . '</li>';
                    echo '<li>Nome: ' . esc_html($reservation->getFirstName()) . '</li>';
                    echo '<li>Cognome: ' . esc_html($reservation->getLastName()) . '</li>';
                    echo '<li>Email: ' . esc_html($reservation->getEmail()) . '</li>';
                    echo '<li>Telefono: ' . esc_html($reservation->getPhone()) . '</li>';
                    echo '<li>Stato: ' . esc_html($reservation->getStatus()) . '</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    throw new Exception('ServiceRegistry non disponibile');
                }
            }
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<p><strong>Errore:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p><small>File: ' . esc_html($e->getFile()) . ':' . esc_html($e->getLine()) . '</small></p>';
            if (method_exists($e, 'getTraceAsString')) {
                echo '<details><summary>Stack Trace</summary><pre>' . esc_html($e->getTraceAsString()) . '</pre></details>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Test 2: Prenotazione con dati parziali (solo nome)
    if (isset($_GET['test']) && $_GET['test'] === 'partial') {
        echo '<div class="test">';
        echo '<h2>Test 2: Prenotazione con dati parziali (solo nome)</h2>';
        
        $nonce = wp_create_nonce('wp_rest');
        $rest_url = rest_url('fp-resv/v1/agenda/reservations');
        
        $data = [
            'date' => date('Y-m-d', strtotime('+4 days')),
            'time' => '20:00',
            'party' => 2,
            'meal' => 'dinner',
            'first_name' => 'Luigi',
            'last_name' => '', // Vuoto
            'email' => '', // Vuoto
            'phone' => '', // Vuoto
            'status' => 'pending'
        ];
        
        $response = wp_remote_post($rest_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => $nonce,
            ],
            'body' => json_encode($data),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            echo '<div class="error">';
            echo '<p><strong>Errore:</strong> ' . esc_html($response->get_error_message()) . '</p>';
            echo '</div>';
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($status === 200 || $status === 201) {
                echo '<div class="success">';
                echo '<p><strong>✅ Successo!</strong> Prenotazione creata con dati parziali. ID: ' . esc_html($body['id'] ?? 'N/A') . '</p>';
                echo '<pre>' . esc_html(print_r($body, true)) . '</pre>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<p><strong>❌ Errore HTTP ' . esc_html($status) . '</strong></p>';
                echo '<pre>' . esc_html(print_r($body, true)) . '</pre>';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    
    // Test 3: Prenotazione con solo cognome
    if (isset($_GET['test']) && $_GET['test'] === 'partial2') {
        echo '<div class="test">';
        echo '<h2>Test 3: Prenotazione con solo cognome</h2>';
        
        $nonce = wp_create_nonce('wp_rest');
        $rest_url = rest_url('fp-resv/v1/agenda/reservations');
        
        $data = [
            'date' => date('Y-m-d', strtotime('+5 days')),
            'time' => '19:30',
            'party' => 3,
            'meal' => 'lunch',
            'first_name' => '', // Vuoto
            'last_name' => 'Bianchi', // Solo cognome
            'email' => 'bianchi@example.com',
            'phone' => '',
            'status' => 'confirmed'
        ];
        
        $response = wp_remote_post($rest_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => $nonce,
            ],
            'body' => json_encode($data),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            echo '<div class="error">';
            echo '<p><strong>Errore:</strong> ' . esc_html($response->get_error_message()) . '</p>';
            echo '</div>';
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($status === 200 || $status === 201) {
                echo '<div class="success">';
                echo '<p><strong>✅ Successo!</strong> Prenotazione creata con solo cognome. ID: ' . esc_html($body['id'] ?? 'N/A') . '</p>';
                echo '<pre>' . esc_html(print_r($body, true)) . '</pre>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<p><strong>❌ Errore HTTP ' . esc_html($status) . '</strong></p>';
                echo '<pre>' . esc_html(print_r($body, true)) . '</pre>';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    ?>
    
    <div class="test">
        <h2>Esegui Test</h2>
        <p>Scegli quale test eseguire:</p>
        <a href="?test=complete"><button>Test 1: Dati Completi</button></a>
        <a href="?test=partial"><button>Test 2: Dati Parziali (solo nome)</button></a>
        <a href="?test=partial2"><button>Test 3: Dati Parziali (solo cognome)</button></a>
    </div>
</body>
</html>

