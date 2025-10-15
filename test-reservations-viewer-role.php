<?php
/**
 * Script di test per il nuovo ruolo Reservations Viewer
 * 
 * Questo script testa:
 * 1. Creazione del ruolo
 * 2. Verifica delle capabilities
 * 3. Verifica dei permessi
 * 
 * ISTRUZIONI:
 * 1. Copia questo file nella root del plugin
 * 2. Caricalo in una pagina WordPress o eseguilo da CLI
 * 3. Controlla l'output per verificare che tutto funzioni
 */

// Se eseguito da CLI, includi WordPress
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../../../wp-load.php';
}

// Verifica che WordPress sia caricato
if (!function_exists('get_role')) {
    die("❌ WordPress non è caricato correttamente.\n");
}

echo "==============================================\n";
echo "TEST RUOLO RESERVATIONS VIEWER\n";
echo "==============================================\n\n";

// 1. Verifica che il ruolo esista
echo "1️⃣ VERIFICA ESISTENZA RUOLO\n";
echo "----------------------------------------------\n";

$viewer_role = get_role('fp_reservations_viewer');
if ($viewer_role) {
    echo "✅ Ruolo 'fp_reservations_viewer' esiste\n";
    echo "   Capabilities del ruolo:\n";
    foreach ($viewer_role->capabilities as $cap => $enabled) {
        echo "   - $cap: " . ($enabled ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "❌ Ruolo 'fp_reservations_viewer' NON esiste\n";
    echo "   Esegui: Disattiva e riattiva il plugin per creare il ruolo\n";
}
echo "\n";

// 2. Verifica il ruolo Restaurant Manager
echo "2️⃣ VERIFICA RUOLO RESTAURANT MANAGER\n";
echo "----------------------------------------------\n";

$manager_role = get_role('fp_restaurant_manager');
if ($manager_role) {
    echo "✅ Ruolo 'fp_restaurant_manager' esiste\n";
    echo "   Capabilities del ruolo:\n";
    foreach ($manager_role->capabilities as $cap => $enabled) {
        echo "   - $cap: " . ($enabled ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "❌ Ruolo 'fp_restaurant_manager' NON esiste\n";
}
echo "\n";

// 3. Verifica le capabilities dell'amministratore
echo "3️⃣ VERIFICA CAPABILITIES ADMINISTRATOR\n";
echo "----------------------------------------------\n";

$admin_role = get_role('administrator');
if ($admin_role) {
    echo "✅ Ruolo 'administrator' esiste\n";
    $fp_caps = array_filter($admin_role->capabilities, function($cap) {
        return strpos($cap, 'fp_reservations') !== false || strpos($cap, 'manage_fp') !== false;
    }, ARRAY_FILTER_USE_KEY);
    
    if (!empty($fp_caps)) {
        echo "   Capabilities del plugin:\n";
        foreach ($fp_caps as $cap => $enabled) {
            echo "   - $cap: " . ($enabled ? 'YES' : 'NO') . "\n";
        }
    } else {
        echo "   ⚠️ Nessuna capability del plugin trovata per l'administrator\n";
    }
} else {
    echo "❌ Ruolo 'administrator' NON esiste (impossibile!)\n";
}
echo "\n";

// 4. Test capabilities con utente simulato
echo "4️⃣ TEST SIMULAZIONE CAPABILITIES\n";
echo "----------------------------------------------\n";

if ($viewer_role) {
    echo "Simulazione utente con ruolo Reservations Viewer:\n";
    
    // Simula le capabilities del viewer
    $viewer_caps = $viewer_role->capabilities;
    
    echo "   - view_fp_reservations_manager: ";
    echo isset($viewer_caps['view_fp_reservations_manager']) && $viewer_caps['view_fp_reservations_manager'] ? '✅ YES' : '❌ NO';
    echo "\n";
    
    echo "   - manage_fp_reservations: ";
    echo isset($viewer_caps['manage_fp_reservations']) && $viewer_caps['manage_fp_reservations'] ? '✅ YES' : '❌ NO (corretto)';
    echo "\n";
    
    echo "   - manage_options: ";
    echo isset($viewer_caps['manage_options']) && $viewer_caps['manage_options'] ? '✅ YES' : '❌ NO (corretto)';
    echo "\n";
    
    echo "   - read: ";
    echo isset($viewer_caps['read']) && $viewer_caps['read'] ? '✅ YES' : '❌ NO';
    echo "\n";
}
echo "\n";

// 5. Lista tutti i ruoli disponibili
echo "5️⃣ TUTTI I RUOLI WORDPRESS DISPONIBILI\n";
echo "----------------------------------------------\n";

$roles = wp_roles()->get_names();
foreach ($roles as $role_slug => $role_name) {
    $role = get_role($role_slug);
    $fp_caps = array_filter($role->capabilities, function($cap) {
        return strpos($cap, 'fp_reservations') !== false || strpos($cap, 'manage_fp') !== false;
    }, ARRAY_FILTER_USE_KEY);
    
    if (!empty($fp_caps) || $role_slug === 'fp_reservations_viewer' || $role_slug === 'fp_restaurant_manager') {
        echo "   $role_name ($role_slug)\n";
        if (!empty($fp_caps)) {
            foreach ($fp_caps as $cap => $enabled) {
                echo "      - $cap\n";
            }
        }
    }
}
echo "\n";

// 6. Suggerimenti
echo "6️⃣ COME TESTARE IL RUOLO\n";
echo "----------------------------------------------\n";
echo "1. Crea un nuovo utente di test:\n";
echo "   - Username: test_viewer\n";
echo "   - Email: test@example.com\n";
echo "   - Ruolo: Reservations Viewer\n";
echo "\n";
echo "2. Fai logout dall'account admin\n";
echo "\n";
echo "3. Fai login con l'utente test_viewer\n";
echo "\n";
echo "4. Verifica che vedi:\n";
echo "   ✅ Solo la voce 'Prenotazioni' nel menu laterale\n";
echo "   ✅ Il Manager delle prenotazioni funziona\n";
echo "   ❌ NON vedi Impostazioni, Chiusure, Report, etc.\n";
echo "\n";
echo "5. Per testare gli endpoint REST API, apri la console del browser e prova:\n";
echo "   fetch('/wp-json/fp-resv/v1/agenda?date=2025-10-15')\n";
echo "     .then(r => r.json())\n";
echo "     .then(console.log)\n";
echo "\n";

// 7. Codice PHP per creare utente di test
echo "7️⃣ CODICE PER CREARE UTENTE DI TEST\n";
echo "----------------------------------------------\n";
echo "Esegui questo codice per creare automaticamente un utente di test:\n\n";
echo "<?php\n";
echo "\$user_id = wp_insert_user([\n";
echo "    'user_login' => 'test_viewer',\n";
echo "    'user_email' => 'test@example.com',\n";
echo "    'user_pass'  => 'TestViewer123!',\n";
echo "    'role'       => 'fp_reservations_viewer',\n";
echo "    'first_name' => 'Test',\n";
echo "    'last_name'  => 'Viewer',\n";
echo "]);\n\n";
echo "if (is_wp_error(\$user_id)) {\n";
echo "    echo 'Errore: ' . \$user_id->get_error_message();\n";
echo "} else {\n";
echo "    echo 'Utente creato con ID: ' . \$user_id;\n";
echo "}\n";
echo "?>\n\n";

echo "==============================================\n";
echo "TEST COMPLETATO\n";
echo "==============================================\n";

