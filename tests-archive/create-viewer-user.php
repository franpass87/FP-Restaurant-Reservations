<?php
/**
 * Script Helper: Crea Utente Reservations Viewer
 * 
 * Questo script crea rapidamente un utente di test con il ruolo Reservations Viewer
 * 
 * ISTRUZIONI:
 * 1. Modifica le variabili $username, $email, $password se necessario
 * 2. Carica questo file nel browser o eseguilo da CLI
 * 3. L'utente verrà creato automaticamente
 */

// Se eseguito da CLI, includi WordPress
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../../../wp-load.php';
}

// Verifica che WordPress sia caricato
if (!function_exists('wp_insert_user')) {
    die("❌ WordPress non è caricato correttamente.\n");
}

// ====================================
// CONFIGURAZIONE
// ====================================

$username = 'test_viewer';
$email = 'viewer@example.com';
$password = 'ViewerTest123!'; // Password sicura
$first_name = 'Test';
$last_name = 'Viewer';
$role = 'fp_reservations_viewer';

// ====================================
// CREAZIONE UTENTE
// ====================================

echo "\n";
echo "==============================================\n";
echo "CREAZIONE UTENTE RESERVATIONS VIEWER\n";
echo "==============================================\n\n";

echo "Configurazione:\n";
echo "   Username:    $username\n";
echo "   Email:       $email\n";
echo "   Password:    $password\n";
echo "   Nome:        $first_name\n";
echo "   Cognome:     $last_name\n";
echo "   Ruolo:       $role\n\n";

// Verifica se il ruolo esiste
if (!get_role($role)) {
    echo "❌ ERRORE: Il ruolo '$role' non esiste!\n";
    echo "   Soluzione: Disattiva e riattiva il plugin FP Restaurant Reservations\n\n";
    exit(1);
}

// Verifica se l'username esiste già
if (username_exists($username)) {
    echo "⚠️ WARNING: L'username '$username' esiste già\n";
    echo "   Vuoi eliminare l'utente esistente? (y/n): ";
    
    if (php_sapi_name() === 'cli') {
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim(strtolower($line)) === 'y') {
            $existing_user = get_user_by('login', $username);
            if ($existing_user) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user($existing_user->ID);
                echo "   ✅ Utente esistente eliminato\n\n";
            }
        } else {
            echo "   ❌ Operazione annullata\n\n";
            exit(0);
        }
        fclose($handle);
    } else {
        echo "\n   Per eliminare l'utente, vai su Utenti > Tutti gli Utenti in WordPress\n\n";
        exit(1);
    }
}

// Verifica se l'email esiste già
if (email_exists($email)) {
    echo "⚠️ WARNING: L'email '$email' è già in uso\n";
    echo "   Modifica la variabile \$email nello script\n\n";
    exit(1);
}

// Crea l'utente
$user_id = wp_insert_user([
    'user_login' => $username,
    'user_email' => $email,
    'user_pass'  => $password,
    'role'       => $role,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'display_name' => "$first_name $last_name",
]);

if (is_wp_error($user_id)) {
    echo "❌ ERRORE nella creazione dell'utente:\n";
    echo "   " . $user_id->get_error_message() . "\n\n";
    exit(1);
}

echo "✅ UTENTE CREATO CON SUCCESSO!\n\n";
echo "Dettagli:\n";
echo "   User ID:     $user_id\n";
echo "   Username:    $username\n";
echo "   Email:       $email\n";
echo "   Password:    $password\n";
echo "   Ruolo:       $role\n\n";

// Verifica le capabilities
$user = new WP_User($user_id);
echo "Capabilities assegnate:\n";
foreach ($user->allcaps as $cap => $enabled) {
    if ($enabled && (strpos($cap, 'fp_reservations') !== false || $cap === 'read')) {
        echo "   ✓ $cap\n";
    }
}
echo "\n";

// Istruzioni per il login
echo "==============================================\n";
echo "COME TESTARE\n";
echo "==============================================\n\n";

echo "1. LOGOUT dall'account admin corrente\n\n";

echo "2. LOGIN con le credenziali:\n";
echo "   URL:      " . wp_login_url() . "\n";
echo "   Username: $username\n";
echo "   Password: $password\n\n";

echo "3. VERIFICA che vedi:\n";
echo "   ✅ Solo la voce 'Prenotazioni' nel menu\n";
echo "   ✅ Puoi gestire le prenotazioni\n";
echo "   ❌ NON vedi Impostazioni, Plugin, Utenti, etc.\n\n";

echo "4. ELIMINA l'utente dopo i test:\n";
echo "   - Login come admin\n";
echo "   - Vai su Utenti > Tutti gli Utenti\n";
echo "   - Elimina l'utente '$username'\n\n";

// Script di eliminazione rapida
echo "==============================================\n";
echo "ELIMINAZIONE RAPIDA\n";
echo "==============================================\n\n";
echo "Per eliminare rapidamente questo utente, esegui:\n\n";
echo "<?php\n";
echo "require_once(ABSPATH . 'wp-admin/includes/user.php');\n";
echo "\$user = get_user_by('login', '$username');\n";
echo "if (\$user) {\n";
echo "    wp_delete_user(\$user->ID);\n";
echo "    echo 'Utente eliminato';\n";
echo "}\n";
echo "?>\n\n";

echo "==============================================\n";
echo "COMPLETATO\n";
echo "==============================================\n\n";

