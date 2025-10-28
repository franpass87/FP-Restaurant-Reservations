<?php
/**
 * Test script per verificare gli endpoint REST
 * Risolve i problemi di errore 500
 */

// Forza WP_DEBUG se non è già definito
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Carica WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
    dirname(__DIR__, 3) . '/wp-load.php',
    dirname(__DIR__, 4) . '/wp-load.php',
    dirname(__DIR__, 5) . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Errore: WordPress non trovato. Controlla il percorso del plugin.');
}

// Verifica che siamo in un ambiente WordPress
if (!function_exists('get_option')) {
    die('Errore: WordPress non caricato correttamente.');
}

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>🔧 Test Endpoint REST - FP Reservations</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; max-width: 1000px; margin: 0 auto; background: #f0f0f1; }
.box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
h1 { margin-top: 0; color: #1e1e1e; }
.success { color: #00a32a; font-weight: 600; }
.warning { color: #dba617; font-weight: 600; }
.error { color: #d63638; font-weight: 600; }
.info { background: #f0f6fc; padding: 15px; border-left: 4px solid #0071a1; margin: 15px 0; }
code { background: #f6f7f7; padding: 2px 6px; border-radius: 3px; font-family: Consolas, Monaco, monospace; }
.button { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 0 0; }
.button:hover { background: #135e96; }
.test-result { padding: 10px; margin: 10px 0; border-radius: 4px; }
.test-result.success { background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; }
.test-result.error { background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; }
.test-result.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
</style>
</head>';
echo '<body>';

echo '<div class="box">';
echo '<h1>🔧 Test Endpoint REST</h1>';

// Test 1: Verifica WP_DEBUG
echo '<h2>1. Verifica WP_DEBUG</h2>';
$wp_debug = defined('WP_DEBUG') && WP_DEBUG;
if ($wp_debug) {
    echo '<div class="test-result success">✅ WP_DEBUG è ATTIVO</div>';
} else {
    echo '<div class="test-result error">❌ WP_DEBUG è DISATTIVO</div>';
}

// Test 2: Verifica plugin attivo
echo '<h2>2. Verifica Plugin</h2>';
if (class_exists('FP\Resv\Core\Plugin')) {
    echo '<div class="test-result success">✅ Plugin FP Reservations caricato</div>';
} else {
    echo '<div class="test-result error">❌ Plugin FP Reservations NON caricato</div>';
}

// Test 3: Test endpoint nonce
echo '<h2>3. Test Endpoint Nonce</h2>';
$nonce_url = rest_url('fp-resv/v1/nonce');
echo '<p>URL: <code>' . esc_html($nonce_url) . '</code></p>';

$nonce_response = wp_remote_get($nonce_url);
if (is_wp_error($nonce_response)) {
    echo '<div class="test-result error">❌ Errore richiesta: ' . esc_html($nonce_response->get_error_message()) . '</div>';
} else {
    $nonce_code = wp_remote_retrieve_response_code($nonce_response);
    $nonce_body = wp_remote_retrieve_body($nonce_response);
    
    if ($nonce_code === 200) {
        $nonce_data = json_decode($nonce_body, true);
        if (isset($nonce_data['nonce'])) {
            echo '<div class="test-result success">✅ Endpoint nonce funziona - Nonce: <code>' . esc_html(substr($nonce_data['nonce'], 0, 10)) . '...</code></div>';
        } else {
            echo '<div class="test-result error">❌ Risposta nonce non valida: ' . esc_html($nonce_body) . '</div>';
        }
    } else {
        echo '<div class="test-result error">❌ Errore HTTP ' . $nonce_code . ': ' . esc_html($nonce_body) . '</div>';
    }
}

// Test 4: Test endpoint available-days
echo '<h2>4. Test Endpoint Available Days</h2>';
$from_date = date('Y-m-d');
$to_date = date('Y-m-d', strtotime('+7 days'));
$available_days_url = rest_url('fp-resv/v1/available-days?from=' . $from_date . '&to=' . $to_date . '&meal=pranzo');
echo '<p>URL: <code>' . esc_html($available_days_url) . '</code></p>';

$available_days_response = wp_remote_get($available_days_url);
if (is_wp_error($available_days_response)) {
    echo '<div class="test-result error">❌ Errore richiesta: ' . esc_html($available_days_response->get_error_message()) . '</div>';
} else {
    $available_days_code = wp_remote_retrieve_response_code($available_days_response);
    $available_days_body = wp_remote_retrieve_body($available_days_response);
    
    if ($available_days_code === 200) {
        $available_days_data = json_decode($available_days_body, true);
        if (isset($available_days_data['days'])) {
            $days_count = count($available_days_data['days']);
            echo '<div class="test-result success">✅ Endpoint available-days funziona - ' . $days_count . ' giorni restituiti</div>';
        } else {
            echo '<div class="test-result error">❌ Risposta available-days non valida: ' . esc_html($available_days_body) . '</div>';
        }
    } else {
        echo '<div class="test-result error">❌ Errore HTTP ' . $available_days_code . ': ' . esc_html($available_days_body) . '</div>';
    }
}

// Test 5: Verifica errori PHP
echo '<h2>5. Verifica Errori PHP</h2>';
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $log_content = file_get_contents($error_log);
    $recent_errors = array_slice(explode("\n", $log_content), -20);
    $fp_errors = array_filter($recent_errors, function($line) {
        return strpos($line, 'FP-RESV') !== false || strpos($line, 'fp-resv') !== false;
    });
    
    if (!empty($fp_errors)) {
        echo '<div class="test-result warning">⚠️ Errori recenti trovati:</div>';
        echo '<pre style="background: #f6f7f7; padding: 10px; border-radius: 4px; font-size: 12px;">';
        foreach (array_slice($fp_errors, -5) as $error) {
            echo esc_html($error) . "\n";
        }
        echo '</pre>';
    } else {
        echo '<div class="test-result success">✅ Nessun errore recente trovato</div>';
    }
} else {
    echo '<div class="test-result info">ℹ️ Log errori non disponibile</div>';
}

// Test 6: Verifica configurazione meal plan
echo '<h2>6. Verifica Meal Plan</h2>';
$meal_plan = get_option('fp_resv_general', []);
$frontend_meals = $meal_plan['frontend_meals'] ?? '';
if (!empty($frontend_meals)) {
    echo '<div class="test-result success">✅ Meal plan configurato: <code>' . esc_html(substr($frontend_meals, 0, 50)) . '...</code></div>';
} else {
    echo '<div class="test-result warning">⚠️ Meal plan non configurato - usando default</div>';
}

echo '<h2>✅ Test Completati</h2>';
echo '<div class="info">';
echo '<strong>Risultati:</strong><br>';
echo '1. Se tutti i test sono verdi, gli endpoint funzionano correttamente<br>';
echo '2. Se ci sono errori rossi, controlla i log per maggiori dettagli<br>';
echo '3. Gli errori JavaScript "Unexpected end of input" dovrebbero essere risolti<br>';
echo '</div>';

echo '<p><a href="javascript:location.reload()" class="button">🔄 Ricarica Test</a>';
echo '<a href="javascript:history.back()" class="button">← Torna Indietro</a></p>';

echo '</div>';
echo '</body>';
echo '</html>';
