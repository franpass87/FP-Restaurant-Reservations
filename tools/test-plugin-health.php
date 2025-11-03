<?php
/**
 * Script di test: Verifica salute plugin FP Restaurant Reservations
 * 
 * Questo script verifica che il plugin sia caricato correttamente
 * e che tutti i componenti principali funzionino.
 */

declare(strict_types=1);

// Richiede WordPress
if (!defined('ABSPATH')) {
    // Prova a trovare wp-load.php risalendo le directory
    $wpLoadPath = __DIR__ . '/../../../../wp-load.php';
    if (!file_exists($wpLoadPath)) {
        // Fallback per junction/symlink
        $wpLoadPath = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
    }
    if (file_exists($wpLoadPath)) {
        require_once $wpLoadPath;
    } else {
        die("ERRORE: Impossibile trovare wp-load.php\nEsegui questo script dalla directory WordPress o caricalo in una pagina admin.\n");
    }
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST SALUTE PLUGIN FP RESTAURANT RESERVATIONS ===\n\n";

$allOk = true;

// 1. Verifica Plugin Attivo
echo "1️⃣ PLUGIN ATTIVO\n";
if (class_exists('FP\Resv\Core\Plugin')) {
    echo "   ✅ Classe Plugin caricata\n";
    echo "   Versione: " . FP\Resv\Core\Plugin::VERSION . "\n";
    echo "   File: " . FP\Resv\Core\Plugin::$file . "\n";
} else {
    echo "   ❌ Classe Plugin NON caricata!\n";
    $allOk = false;
}
echo "\n";

// 2. Verifica Autoload
echo "2️⃣ AUTOLOAD COMPOSER\n";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "   ✅ vendor/autoload.php presente\n";
} else {
    echo "   ❌ vendor/autoload.php MANCANTE!\n";
    $allOk = false;
}
echo "\n";

// 3. Verifica Timezone
echo "3️⃣ TIMEZONE\n";
echo "   WP Timezone: " . wp_timezone_string() . "\n";
echo "   Ora locale: " . current_time('Y-m-d H:i:s') . "\n";
echo "   Ora UTC: " . gmdate('Y-m-d H:i:s') . "\n";

if (wp_timezone_string() === 'Europe/Rome') {
    echo "   ✅ Timezone corretto (Europe/Rome)\n";
} else {
    echo "   ⚠️  Timezone: " . wp_timezone_string() . " (dovrebbe essere Europe/Rome)\n";
}
echo "\n";

// 4. Verifica ServiceContainer
echo "4️⃣ SERVICE CONTAINER\n";
try {
    $container = FP\Resv\Core\ServiceContainer::getInstance();
    echo "   ✅ ServiceContainer caricato\n";
    
    // Verifica servizi chiave
    $services = [
        'settings.options' => 'Options',
        'reservations.repository' => 'Repository',
        'reservations.service' => 'Service',
        'reservations.rest' => 'REST API',
    ];
    
    foreach ($services as $key => $name) {
        if ($container->has($key)) {
            echo "   ✅ $name registrato\n";
        } else {
            echo "   ❌ $name NON registrato\n";
            $allOk = false;
        }
    }
} catch (Exception $e) {
    echo "   ❌ Errore: " . $e->getMessage() . "\n";
    $allOk = false;
}
echo "\n";

// 5. Verifica Database Tables
echo "5️⃣ DATABASE TABLES\n";
global $wpdb;
$tables = [
    'fp_reservations' => $wpdb->prefix . 'fp_reservations',
    'fp_resv_customers' => $wpdb->prefix . 'fp_resv_customers',
    'fp_resv_payments' => $wpdb->prefix . 'fp_resv_payments',
];

foreach ($tables as $name => $tableName) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName;
    if ($exists) {
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tableName");
        echo "   ✅ $name ($count record)\n";
    } else {
        echo "   ❌ $name NON ESISTE\n";
        $allOk = false;
    }
}
echo "\n";

// 6. Verifica REST Routes
echo "6️⃣ REST API ROUTES\n";
$routes = rest_get_server()->get_routes();
$fpResvRoutes = array_filter(array_keys($routes), function($route) {
    return strpos($route, '/fp-resv/') !== false;
});

if (count($fpResvRoutes) > 0) {
    echo "   ✅ " . count($fpResvRoutes) . " endpoint REST registrati:\n";
    foreach (array_slice($fpResvRoutes, 0, 5) as $route) {
        echo "   - $route\n";
    }
    if (count($fpResvRoutes) > 5) {
        echo "   - ... e altri " . (count($fpResvRoutes) - 5) . "\n";
    }
} else {
    echo "   ❌ Nessun endpoint REST registrato!\n";
    $allOk = false;
}
echo "\n";

// 7. Verifica Files Modificati (Timezone Fix)
echo "7️⃣ FILES TIMEZONE FIX\n";
$modifiedFiles = [
    'src/Core/Plugin.php',
    'src/Domain/Reservations/AdminREST.php',
    'src/Domain/Reservations/REST.php',
    'src/Domain/Reservations/Service.php',
    'src/Domain/Reservations/Repository.php',
    'src/Frontend/Shortcodes.php',
];

foreach ($modifiedFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        // Verifica che non usi più gmdate() o date() (tranne casi specifici)
        $content = file_get_contents($fullPath);
        
        // Conta usi problematici
        $gmdateCount = substr_count($content, 'gmdate(');
        $dateCount = substr_count($content, 'date(');
        $wpDateCount = substr_count($content, 'wp_date(');
        $currentTimeCount = substr_count($content, 'current_time(');
        
        // File OK se usa principalmente wp_date/current_time
        $status = ($wpDateCount + $currentTimeCount) > ($gmdateCount + $dateCount) ? '✅' : '⚠️';
        echo "   $status $file\n";
        
        if (WP_DEBUG && ($gmdateCount > 0 || $dateCount > 0)) {
            echo "      (gmdate: $gmdateCount, date: $dateCount, wp_date: $wpDateCount, current_time: $currentTimeCount)\n";
        }
    } else {
        echo "   ❌ $file NON TROVATO\n";
        $allOk = false;
    }
}
echo "\n";

// 8. Test Availability API
echo "8️⃣ TEST API AVAILABILITY\n";
try {
    $today = current_time('Y-m-d');
    $request = new WP_REST_Request('GET', '/fp-resv/v1/availability');
    $request->set_param('date', $today);
    $request->set_param('party', 2);
    
    $container = FP\Resv\Core\ServiceContainer::getInstance();
    if ($container->has('reservations.rest')) {
        $restController = $container->get('reservations.rest');
        $response = $restController->handleAvailability($request);
        
        if (is_wp_error($response)) {
            echo "   ⚠️  API Error: " . $response->get_error_message() . "\n";
        } else {
            $data = $response->get_data();
            echo "   ✅ API risponde correttamente\n";
            echo "   Data: " . ($data['date'] ?? 'N/A') . "\n";
            echo "   Timezone: " . ($data['timezone'] ?? 'N/A') . "\n";
            echo "   Slot: " . (isset($data['slots']) ? count($data['slots']) : 0) . "\n";
            
            if (isset($data['timezone']) && $data['timezone'] === 'Europe/Rome') {
                echo "   ✅ Timezone corretto nella risposta API\n";
            } else {
                echo "   ⚠️  Timezone nella risposta: " . ($data['timezone'] ?? 'N/A') . "\n";
            }
        }
    } else {
        echo "   ❌ REST Controller non disponibile\n";
        $allOk = false;
    }
} catch (Exception $e) {
    echo "   ❌ Errore test API: " . $e->getMessage() . "\n";
    $allOk = false;
}
echo "\n";

// 9. Riepilogo
echo "=" . str_repeat("=", 50) . "\n";
if ($allOk) {
    echo "✅ TUTTI I TEST SUPERATI!\n";
    echo "\nIl plugin FP Restaurant Reservations è completamente funzionante.\n";
    echo "Versione: " . FP\Resv\Core\Plugin::VERSION . "\n";
    echo "Timezone: " . wp_timezone_string() . "\n";
} else {
    echo "⚠️  ALCUNI TEST HANNO FALLITO\n";
    echo "\nControlla gli errori sopra riportati.\n";
}
echo "=" . str_repeat("=", 50) . "\n";

