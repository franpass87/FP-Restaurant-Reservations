<?php
/**
 * Test Admin Reservation Creation
 * Simula un operatore che crea una prenotazione dal backend
 * Access via: http://fp-development.local/wp-content/plugins/FP-Restaurant-Reservations/test-admin-reservation.php
 */

// Load WordPress - resolve real path to handle junctions/symlinks
$plugin_dir = realpath(__DIR__);
if ($plugin_dir === false) {
    $plugin_dir = __DIR__;
}
$wp_load = dirname(dirname(dirname($plugin_dir))) . '/wp-load.php';

// If still not found, try absolute path based on workspace
if (!file_exists($wp_load)) {
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}

if (!file_exists($wp_load)) {
    die('WordPress not found. Plugin dir: ' . $plugin_dir . ', Looking for: ' . $wp_load);
}

require_once $wp_load;

// Simula un utente admin (per test)
if (!function_exists('wp_set_current_user')) {
    die('WordPress non caricato correttamente');
}

// Prova a trovare un utente admin
$admin_users = get_users(['role' => 'administrator', 'number' => 1]);
if (!empty($admin_users)) {
    wp_set_current_user($admin_users[0]->ID);
} else {
    // Se non ci sono admin, crea un utente temporaneo per il test
    $user_id = wp_create_user('test_admin_' . time(), 'test123', 'test@example.com');
    if (!is_wp_error($user_id)) {
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        wp_set_current_user($user_id);
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Creazione Prenotazione - Backend</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        button {
            background: #2271b1;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #135e96;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="container">
        <h1>🧑‍💼 Test Creazione Prenotazione - Backend (Operatore)</h1>
        <p>Simula un operatore del ristorante che inserisce una prenotazione dal backend.</p>
        
        <?php
        // Gestisci l'invio del form
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_reservation'])) {
            $nonce = wp_create_nonce('wp_rest');
            
            // Prepara i dati della prenotazione (come farebbe un operatore)
            $reservation_data = [
                'date' => sanitize_text_field($_POST['date'] ?? ''),
                'time' => sanitize_text_field($_POST['time'] ?? ''),
                'party' => absint($_POST['party'] ?? 2),
                'meal' => sanitize_text_field($_POST['meal'] ?? 'dinner'),
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                'allergies' => sanitize_textarea_field($_POST['allergies'] ?? ''),
                'status' => sanitize_text_field($_POST['status'] ?? 'confirmed'),
            ];
            
            // Usa direttamente il Use Case invece dell'API REST per evitare timeout
            try {
                // Aggiungi allow_partial_contact per backend
                $reservation_data['allow_partial_contact'] = true;
                
                // Crea direttamente il Use Case con le sue dipendenze
                // Questo è più semplice e diretto per il test
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
                
                // Crea la prenotazione
                $reservation = $createUseCase->execute($reservation_data);
                
                echo '<div class="result success">';
                echo '<h3>✅ Prenotazione creata con successo!</h3>';
                echo '<p><strong>ID Prenotazione:</strong> ' . esc_html($reservation->getId() ?? 'N/A') . '</p>';
                echo '<p><strong>Data:</strong> ' . esc_html($reservation->getDate()) . '</p>';
                echo '<p><strong>Orario:</strong> ' . esc_html($reservation->getTime()) . '</p>';
                echo '<p><strong>Persone:</strong> ' . esc_html($reservation->getParty()) . '</p>';
                echo '<p><strong>Nome:</strong> ' . esc_html($reservation->getFirstName()) . '</p>';
                echo '<p><strong>Cognome:</strong> ' . esc_html($reservation->getLastName()) . '</p>';
                echo '<p><strong>Email:</strong> ' . esc_html($reservation->getEmail()) . '</p>';
                echo '<p><strong>Telefono:</strong> ' . esc_html($reservation->getPhone()) . '</p>';
                echo '<p><strong>Stato:</strong> ' . esc_html($reservation->getStatus()) . '</p>';
                echo '</div>';
            } catch (\FP\Resv\Core\Exceptions\ValidationException $e) {
                echo '<div class="result error">';
                echo '<h3>❌ Errore di Validazione</h3>';
                echo '<p>' . esc_html($e->getMessage()) . '</p>';
                if (method_exists($e, 'getErrors')) {
                    $errors = $e->getErrors();
                    echo '<p><strong>Errori:</strong></p>';
                    echo '<ul>';
                    foreach ($errors as $field => $message) {
                        echo '<li><strong>' . esc_html($field) . ':</strong> ' . esc_html($message) . '</li>';
                    }
                    echo '</ul>';
                }
                echo '</div>';
            } catch (Throwable $e) {
                echo '<div class="result error">';
                echo '<h3>❌ Errore</h3>';
                echo '<p><strong>Messaggio:</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '<p><strong>Classe:</strong> ' . esc_html(get_class($e)) . '</p>';
                echo '<p><small>File: ' . esc_html($e->getFile()) . ':' . esc_html($e->getLine()) . '</small></p>';
                if (method_exists($e, 'getTraceAsString')) {
                    echo '<details><summary>Stack Trace</summary><pre>' . esc_html($e->getTraceAsString()) . '</pre></details>';
                }
                echo '</div>';
            }
        }
        ?>
        
        <div class="info" style="padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <strong>ℹ️ Nota:</strong> Questo form simula un operatore che può inserire prenotazioni anche con dati cliente parziali (come modificato nel codice).
        </div>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="date">Data *</label>
                    <input type="date" id="date" name="date" required 
                           value="<?php echo esc_attr(date('Y-m-d', strtotime('+3 days'))); ?>">
                </div>
                <div class="form-group">
                    <label for="time">Orario *</label>
                    <input type="time" id="time" name="time" required value="19:00">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="party">Numero Persone *</label>
                    <input type="number" id="party" name="party" min="1" max="50" required value="2">
                </div>
                <div class="form-group">
                    <label for="meal">Servizio</label>
                    <select id="meal" name="meal">
                        <option value="dinner">Cena</option>
                        <option value="lunch">Pranzo</option>
                        <option value="pranzo-domenicale">Pranzo Domenicale</option>
                        <option value="cena-weekend">Cena Weekend</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Stato</label>
                    <select id="status" name="status">
                        <option value="pending">In Attesa</option>
                        <option value="confirmed" selected>Confermato</option>
                        <option value="visited">Visitato</option>
                        <option value="cancelled">Cancellato</option>
                    </select>
                </div>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px;">Dati Cliente (Opzionali per Backend)</h3>
            <p style="color: #666; margin-bottom: 15px;">
                <em>Come operatore, puoi salvare anche con dati parziali (almeno nome o cognome richiesto)</em>
            </p>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Nome</label>
                    <input type="text" id="first_name" name="first_name" 
                           placeholder="Opzionale (almeno nome o cognome)">
                </div>
                <div class="form-group">
                    <label for="last_name">Cognome</label>
                    <input type="text" id="last_name" name="last_name" 
                           placeholder="Opzionale (almeno nome o cognome)">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           placeholder="Opzionale">
                </div>
                <div class="form-group">
                    <label for="phone">Telefono</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="Opzionale">
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Note</label>
                <textarea id="notes" name="notes" rows="3" 
                          placeholder="Note aggiuntive..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="allergies">Allergie / Intolleranze</label>
                <textarea id="allergies" name="allergies" rows="2" 
                          placeholder="Eventuali allergie..."></textarea>
            </div>
            
            <button type="submit" name="create_reservation">Crea Prenotazione</button>
        </form>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

