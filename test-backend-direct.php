<?php
/**
 * Test Backend Diretto - Simula Operatore
 * Testa la creazione di prenotazioni chiamando direttamente il codice
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
} else {
    die('Nessun utente admin trovato');
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Backend Diretto</title>
    <style>
        body { font-family: Arial; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .test { margin: 20px 0; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; background: #d4edda; }
        .error { border-left: 4px solid #dc3545; background: #f8d7da; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🧪 Test Backend Diretto - Simulazione Operatore</h1>
    
    <?php
    // Test 1: Prenotazione con dati completi
    if (isset($_GET['test']) && $_GET['test'] === 'complete') {
        echo '<div class="test">';
        echo '<h2>✅ Test 1: Prenotazione con Dati Completi</h2>';
        
        try {
            // Crea una richiesta REST mock
            $request = new WP_REST_Request('POST', '/fp-resv/v1/agenda/reservations');
            $request->set_param('date', date('Y-m-d', strtotime('+3 days')));
            $request->set_param('time', '19:00');
            $request->set_param('party', 4);
            $request->set_param('meal', 'dinner');
            $request->set_param('first_name', 'Mario');
            $request->set_param('last_name', 'Rossi');
            $request->set_param('email', 'mario.rossi@example.com');
            $request->set_param('phone', '+39 333 1234567');
            $request->set_param('notes', 'Tavolo vicino alla finestra');
            $request->set_param('status', 'confirmed');
            
            // Ottieni AdminREST dal container del plugin
            $plugin = \FP\Resv\Kernel\Plugin::getInstance();
            $container = $plugin->container();
            $adminREST = $container->get(\FP\Resv\Domain\Reservations\AdminREST::class);
            
            // Chiama direttamente l'endpoint
            $response = $adminREST->handleCreateReservation($request);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $data = $response->get_data();
            $reservationId = $data['id'] ?? null;
            
            // Recupera la prenotazione per mostrare i dettagli
            global $wpdb;
            $repository = new \FP\Resv\Domain\Reservations\Repository($wpdb);
            $reservation = $repository->findById($reservationId);
            
            echo '<div class="success">';
            echo '<h3>✅ Prenotazione creata con successo!</h3>';
            echo '<table>';
            echo '<tr><th>Campo</th><th>Valore</th></tr>';
            echo '<tr><td>ID</td><td>' . esc_html($reservationId) . '</td></tr>';
            echo '<tr><td>Data</td><td>' . esc_html($reservation->getDate()) . '</td></tr>';
            echo '<tr><td>Orario</td><td>' . esc_html($reservation->getTime()) . '</td></tr>';
            echo '<tr><td>Persone</td><td>' . esc_html($reservation->getParty()) . '</td></tr>';
            echo '<tr><td>Servizio</td><td>' . esc_html($reservation->getMeal()) . '</td></tr>';
            echo '<tr><td>Nome</td><td>' . esc_html($reservation->getFirstName()) . '</td></tr>';
            echo '<tr><td>Cognome</td><td>' . esc_html($reservation->getLastName()) . '</td></tr>';
            echo '<tr><td>Email</td><td>' . esc_html($reservation->getEmail()) . '</td></tr>';
            echo '<tr><td>Telefono</td><td>' . esc_html($reservation->getPhone()) . '</td></tr>';
            echo '<tr><td>Stato</td><td>' . esc_html($reservation->getStatus()) . '</td></tr>';
            echo '</table>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Errore</h3>';
            echo '<p><strong>Messaggio:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p><small>File: ' . esc_html($e->getFile()) . ':' . esc_html($e->getLine()) . '</small></p>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Test 2: Prenotazione con dati parziali (solo nome)
    if (isset($_GET['test']) && $_GET['test'] === 'partial') {
        echo '<div class="test">';
        echo '<h2>✅ Test 2: Prenotazione con Dati Parziali (Solo Nome)</h2>';
        
        try {
            $request = new WP_REST_Request('POST', '/fp-resv/v1/agenda/reservations');
            $request->set_param('date', date('Y-m-d', strtotime('+4 days')));
            $request->set_param('time', '20:00');
            $request->set_param('party', 2);
            $request->set_param('meal', 'dinner');
            $request->set_param('first_name', 'Luigi');
            $request->set_param('last_name', ''); // Vuoto
            $request->set_param('email', ''); // Vuoto
            $request->set_param('phone', ''); // Vuoto
            $request->set_param('status', 'pending');
            
            global $wpdb;
            $repository = new \FP\Resv\Domain\Reservations\Repository($wpdb);
            $extractor = new \FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor();
            $payload = $extractor->extract($request);
            
            // Crea il Service (stesso codice del test 1)
            $reservationService = new \FP\Resv\Domain\Reservations\Service(
                $repository,
                new \FP\Resv\Domain\Reservations\Availability($repository, new \FP\Resv\Domain\Settings\Options()),
                new \FP\Resv\Domain\Settings\Options(),
                new \FP\Resv\Domain\Settings\Language(),
                new \FP\Resv\Domain\Reservations\EmailService(),
                new \FP\Resv\Domain\Reservations\AvailabilityGuard($repository),
                new \FP\Resv\Domain\Reservations\PaymentService(),
                new \FP\Resv\Domain\Customers\Repository($wpdb),
                new \FP\Resv\Domain\Notifications\Settings(),
                new \FP\Resv\Domain\Reservations\ReservationPayloadSanitizer(),
                new \FP\Resv\Domain\Reservations\SettingsResolver(),
                new \FP\Resv\Domain\Reservations\BrevoConfirmationEventSender()
            );
            
            $validator = new \FP\Resv\Core\Services\Validator();
            $logger = new \FP\Resv\Core\Services\Logger();
            $createUseCase = new \FP\Resv\Application\Reservations\CreateReservationUseCase(
                $reservationService,
                $validator,
                $logger
            );
            
            $reservation = $createUseCase->execute($payload);
            $reservationId = $reservation->getId();
            
            echo '<div class="success">';
            echo '<h3>✅ Prenotazione creata con dati parziali!</h3>';
            echo '<p><strong>Questo test verifica che gli operatori possano salvare prenotazioni anche con dati cliente incompleti.</strong></p>';
            echo '<table>';
            echo '<tr><th>Campo</th><th>Valore</th></tr>';
            echo '<tr><td>ID</td><td>' . esc_html($reservationId) . '</td></tr>';
            echo '<tr><td>Data</td><td>' . esc_html($reservation->getDate()) . '</td></tr>';
            echo '<tr><td>Orario</td><td>' . esc_html($reservation->getTime()) . '</td></tr>';
            echo '<tr><td>Nome</td><td>' . esc_html($reservation->getFirstName()) . '</td></tr>';
            echo '<tr><td>Cognome</td><td><em>' . (empty($reservation->getLastName()) ? 'Vuoto (OK per backend)' : esc_html($reservation->getLastName())) . '</em></td></tr>';
            echo '<tr><td>Email</td><td><em>' . (empty($reservation->getEmail()) ? 'Vuoto (OK per backend)' : esc_html($reservation->getEmail())) . '</em></td></tr>';
            echo '<tr><td>Telefono</td><td><em>' . (empty($reservation->getPhone()) ? 'Vuoto (OK per backend)' : esc_html($reservation->getPhone())) . '</em></td></tr>';
            echo '<tr><td>Stato</td><td>' . esc_html($reservation->getStatus()) . '</td></tr>';
            echo '</table>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Errore</h3>';
            echo '<p><strong>Messaggio:</strong> ' . esc_html($e->getMessage()) . '</p>';
            if (method_exists($e, 'getErrors')) {
                echo '<p><strong>Errori di validazione:</strong></p>';
                echo '<pre>' . esc_html(print_r($e->getErrors(), true)) . '</pre>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Test 3: Prenotazione con solo cognome
    if (isset($_GET['test']) && $_GET['test'] === 'partial2') {
        echo '<div class="test">';
        echo '<h2>✅ Test 3: Prenotazione con Solo Cognome</h2>';
        
        try {
            $request = new WP_REST_Request('POST', '/fp-resv/v1/agenda/reservations');
            $request->set_param('date', date('Y-m-d', strtotime('+5 days')));
            $request->set_param('time', '19:30');
            $request->set_param('party', 3);
            $request->set_param('meal', 'lunch');
            $request->set_param('first_name', ''); // Vuoto
            $request->set_param('last_name', 'Bianchi'); // Solo cognome
            $request->set_param('email', 'bianchi@example.com');
            $request->set_param('phone', '');
            $request->set_param('status', 'confirmed');
            
            global $wpdb;
            $repository = new \FP\Resv\Domain\Reservations\Repository($wpdb);
            $extractor = new \FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor();
            $payload = $extractor->extract($request);
            
            $reservationService = new \FP\Resv\Domain\Reservations\Service(
                $repository,
                new \FP\Resv\Domain\Reservations\Availability($repository, new \FP\Resv\Domain\Settings\Options()),
                new \FP\Resv\Domain\Settings\Options(),
                new \FP\Resv\Domain\Settings\Language(),
                new \FP\Resv\Domain\Reservations\EmailService(),
                new \FP\Resv\Domain\Reservations\AvailabilityGuard($repository),
                new \FP\Resv\Domain\Reservations\PaymentService(),
                new \FP\Resv\Domain\Customers\Repository($wpdb),
                new \FP\Resv\Domain\Notifications\Settings(),
                new \FP\Resv\Domain\Reservations\ReservationPayloadSanitizer(),
                new \FP\Resv\Domain\Reservations\SettingsResolver(),
                new \FP\Resv\Domain\Reservations\BrevoConfirmationEventSender()
            );
            
            $validator = new \FP\Resv\Core\Services\Validator();
            $logger = new \FP\Resv\Core\Services\Logger();
            $createUseCase = new \FP\Resv\Application\Reservations\CreateReservationUseCase(
                $reservationService,
                $validator,
                $logger
            );
            
            $reservation = $createUseCase->execute($payload);
            $reservationId = $reservation->getId();
            
            echo '<div class="success">';
            echo '<h3>✅ Prenotazione creata con solo cognome!</h3>';
            echo '<table>';
            echo '<tr><th>Campo</th><th>Valore</th></tr>';
            echo '<tr><td>ID</td><td>' . esc_html($reservationId) . '</td></tr>';
            echo '<tr><td>Nome</td><td><em>' . (empty($reservation->getFirstName()) ? 'Vuoto (OK per backend)' : esc_html($reservation->getFirstName())) . '</em></td></tr>';
            echo '<tr><td>Cognome</td><td>' . esc_html($reservation->getLastName()) . '</td></tr>';
            echo '<tr><td>Email</td><td>' . esc_html($reservation->getEmail()) . '</td></tr>';
            echo '</table>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Errore</h3>';
            echo '<p><strong>Messaggio:</strong> ' . esc_html($e->getMessage()) . '</p>';
            if (method_exists($e, 'getErrors')) {
                echo '<p><strong>Errori di validazione:</strong></p>';
                echo '<pre>' . esc_html(print_r($e->getErrors(), true)) . '</pre>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    ?>
    
    <div class="test">
        <h2>Esegui Test Backend</h2>
        <p>Simula un operatore che crea prenotazioni dal backend:</p>
        <a href="?test=complete"><button style="padding: 10px 20px; margin: 5px; cursor: pointer; background: #2271b1; color: white; border: none; border-radius: 4px;">Test 1: Dati Completi</button></a>
        <a href="?test=partial"><button style="padding: 10px 20px; margin: 5px; cursor: pointer; background: #2271b1; color: white; border: none; border-radius: 4px;">Test 2: Solo Nome</button></a>
        <a href="?test=partial2"><button style="padding: 10px 20px; margin: 5px; cursor: pointer; background: #2271b1; color: white; border: none; border-radius: 4px;">Test 3: Solo Cognome</button></a>
    </div>
</body>
</html>

