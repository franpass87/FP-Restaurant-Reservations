<?php
/**
 * Test Script: Debug Shortcode Form Visibility
 * 
 * Questo script verifica perché il form non si visualizza nelle pagine con shortcode.
 * 
 * COME USARE:
 * 1. Carica questo file nella root del plugin WordPress
 * 2. Vai su: http://tuosito.local/wp-content/plugins/fp-restaurant-reservations/test-shortcode-debug.php
 * 3. Oppure esegui da terminale: php test-shortcode-debug.php
 */

// Bootstrap WordPress
$wp_load_path = dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('❌ Impossibile trovare wp-load.php. Assicurati che lo script sia nella root del plugin.');
}

echo "=== TEST DEBUG SHORTCODE FORM ===\n\n";

// Test 1: Verifica che il plugin sia attivo
echo "✓ Test 1: Plugin attivo\n";
if (!class_exists('FP\\Resv\\Core\\Plugin')) {
    die("❌ ERRORE: Plugin non attivo o classe Plugin non trovata\n");
}
echo "  ✓ Classe Plugin trovata\n";
echo "  ✓ Versione: " . FP\Resv\Core\Plugin::VERSION . "\n\n";

// Test 2: Verifica che lo shortcode sia registrato
echo "✓ Test 2: Verifica shortcode registrato\n";
global $shortcode_tags;
if (!isset($shortcode_tags['fp_reservations'])) {
    echo "  ❌ ERRORE: Shortcode 'fp_reservations' NON registrato!\n";
    echo "  → Shortcode disponibili: " . implode(', ', array_keys($shortcode_tags)) . "\n\n";
    die("  → Il plugin non ha registrato lo shortcode correttamente\n");
}
echo "  ✓ Shortcode 'fp_reservations' è registrato\n";
$callback = $shortcode_tags['fp_reservations'];
if (is_array($callback)) {
    echo "  ✓ Callback: " . get_class($callback[0]) . "::" . $callback[1] . "\n\n";
} else {
    echo "  ✓ Callback: " . (is_string($callback) ? $callback : 'function') . "\n\n";
}

// Test 3: Esegui lo shortcode e verifica l'output
echo "✓ Test 3: Esegui lo shortcode e verifica output\n";
ob_start();
$output = do_shortcode('[fp_reservations]');
$buffered = ob_get_clean();

if ($buffered) {
    echo "  ⚠ Warning: Output buffered durante l'esecuzione:\n";
    echo "  " . substr($buffered, 0, 200) . (strlen($buffered) > 200 ? '...' : '') . "\n\n";
}

echo "  Lunghezza output: " . strlen($output) . " bytes\n";

if (empty(trim($output))) {
    echo "  ❌ ERRORE: Lo shortcode non ha prodotto output!\n";
    echo "  → Controlla i log PHP per errori\n\n";
} else {
    echo "  ✓ Lo shortcode ha prodotto output\n";
    
    // Verifica che ci siano gli elementi chiave nel markup
    $hasWidget = strpos($output, 'fp-resv-widget') !== false;
    $hasForm = strpos($output, 'fp-resv-widget__form') !== false;
    $hasDataAttr = strpos($output, 'data-fp-resv-app') !== false;
    $hasStyle = strpos($output, '<style') !== false;
    
    echo "  " . ($hasWidget ? "✓" : "❌") . " Contiene classe 'fp-resv-widget'\n";
    echo "  " . ($hasForm ? "✓" : "❌") . " Contiene form con classe 'fp-resv-widget__form'\n";
    echo "  " . ($hasDataAttr ? "✓" : "❌") . " Contiene attributo 'data-fp-resv-app'\n";
    echo "  " . ($hasStyle ? "✓" : "❌") . " Contiene tag <style> per forzare visibilità\n\n";
    
    if (!$hasWidget || !$hasForm || !$hasDataAttr) {
        echo "  ⚠ WARNING: Il markup sembra incompleto\n\n";
    }
}

// Test 4: Verifica che gli asset siano registrati
echo "✓ Test 4: Verifica asset CSS/JS registrati\n";

// Simula wp_enqueue_scripts
do_action('wp_enqueue_scripts');

global $wp_styles, $wp_scripts;

// Verifica CSS
$cssRegistered = isset($wp_styles->registered['fp-resv-form']);
echo "  " . ($cssRegistered ? "✓" : "❌") . " CSS 'fp-resv-form' " . ($cssRegistered ? "registrato" : "NON registrato") . "\n";
if ($cssRegistered) {
    $cssEnqueued = in_array('fp-resv-form', $wp_styles->queue, true);
    echo "  " . ($cssEnqueued ? "✓" : "⚠") . " CSS 'fp-resv-form' " . ($cssEnqueued ? "in coda" : "non in coda") . "\n";
    if ($cssRegistered) {
        echo "  → URL CSS: " . $wp_styles->registered['fp-resv-form']->src . "\n";
        echo "  → Versione: " . $wp_styles->registered['fp-resv-form']->ver . "\n";
        
        // Verifica che il file CSS esista fisicamente
        $cssPath = str_replace(plugins_url('', FP\Resv\Core\Plugin::$file), FP\Resv\Core\Plugin::$dir, $wp_styles->registered['fp-resv-form']->src);
        $cssPath = preg_replace('/\?.*$/', '', $cssPath); // Rimuovi query string
        if (file_exists($cssPath)) {
            echo "  ✓ File CSS esiste fisicamente (" . filesize($cssPath) . " bytes)\n";
        } else {
            echo "  ❌ File CSS NON esiste: " . $cssPath . "\n";
        }
    }
}

// Verifica JavaScript
$jsModuleRegistered = isset($wp_scripts->registered['fp-resv-onepage-module']);
$jsLegacyRegistered = isset($wp_scripts->registered['fp-resv-onepage']);

echo "  " . ($jsModuleRegistered ? "✓" : "❌") . " JS 'fp-resv-onepage-module' " . ($jsModuleRegistered ? "registrato" : "NON registrato") . "\n";
echo "  " . ($jsLegacyRegistered ? "✓" : "❌") . " JS 'fp-resv-onepage' " . ($jsLegacyRegistered ? "registrato" : "NON registrato") . "\n";

if ($jsModuleRegistered) {
    echo "  → URL JS Module: " . $wp_scripts->registered['fp-resv-onepage-module']->src . "\n";
    echo "  → Versione: " . $wp_scripts->registered['fp-resv-onepage-module']->ver . "\n";
    
    // Verifica che il file JS esista fisicamente
    $jsPath = str_replace(plugins_url('', FP\Resv\Core\Plugin::$file), FP\Resv\Core\Plugin::$dir, $wp_scripts->registered['fp-resv-onepage-module']->src);
    $jsPath = preg_replace('/\?.*$/', '', $jsPath); // Rimuovi query string
    if (file_exists($jsPath)) {
        echo "  ✓ File JS Module esiste fisicamente (" . round(filesize($jsPath) / 1024, 2) . " KB)\n";
    } else {
        echo "  ❌ File JS Module NON esiste: " . $jsPath . "\n";
    }
}

if ($jsLegacyRegistered) {
    echo "  → URL JS Legacy: " . $wp_scripts->registered['fp-resv-onepage']->src . "\n";
    $jsLegacyPath = str_replace(plugins_url('', FP\Resv\Core\Plugin::$file), FP\Resv\Core\Plugin::$dir, $wp_scripts->registered['fp-resv-onepage']->src);
    $jsLegacyPath = preg_replace('/\?.*$/', '', $jsLegacyPath);
    if (file_exists($jsLegacyPath)) {
        echo "  ✓ File JS Legacy esiste fisicamente (" . round(filesize($jsLegacyPath) / 1024, 2) . " KB)\n";
    } else {
        echo "  ❌ File JS Legacy NON esiste: " . $jsLegacyPath . "\n";
    }
}

echo "\n";

// Test 5: Verifica i file template
echo "✓ Test 5: Verifica template file\n";
$templatePath = FP\Resv\Core\Plugin::$dir . 'templates/frontend/form.php';
if (file_exists($templatePath)) {
    echo "  ✓ Template form.php esiste (" . round(filesize($templatePath) / 1024, 2) . " KB)\n";
} else {
    echo "  ❌ Template form.php NON esiste: " . $templatePath . "\n";
}

echo "\n";

// Test 6: Mostra i primi 500 caratteri dell'output per debug
if (!empty($output)) {
    echo "✓ Test 6: Primi 500 caratteri dell'output HTML:\n";
    echo "================================================================================\n";
    echo substr($output, 0, 500) . "\n";
    if (strlen($output) > 500) {
        echo "... [output troncato, totale " . strlen($output) . " bytes]\n";
    }
    echo "================================================================================\n\n";
}

// Test 7: Verifica log di errore PHP (se possibile)
echo "✓ Test 7: Cerca errori nei log PHP\n";
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    $logFile = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $fpResvLogs = [];
        $lines = explode("\n", $logContent);
        foreach ($lines as $line) {
            if (stripos($line, '[FP-RESV]') !== false || stripos($line, 'fp-resv') !== false || stripos($line, 'FP\\Resv') !== false) {
                $fpResvLogs[] = $line;
            }
        }
        
        if (count($fpResvLogs) > 0) {
            echo "  → Trovate " . count($fpResvLogs) . " righe di log relative al plugin\n";
            echo "  → Ultime 10 righe:\n";
            $lastLogs = array_slice($fpResvLogs, -10);
            foreach ($lastLogs as $log) {
                echo "    " . substr($log, 0, 120) . (strlen($log) > 120 ? '...' : '') . "\n";
            }
        } else {
            echo "  ✓ Nessun errore trovato nei log\n";
        }
    } else {
        echo "  ⚠ File debug.log non trovato (WP_DEBUG_LOG non configurato?)\n";
    }
} else {
    echo "  ⚠ WP_DEBUG_LOG non attivo, impossibile verificare log\n";
}

echo "\n";

// Riepilogo finale
echo "=== RIEPILOGO ===\n\n";

$errors = [];
$warnings = [];

if (!isset($shortcode_tags['fp_reservations'])) {
    $errors[] = "Lo shortcode 'fp_reservations' NON è registrato";
}

if (empty(trim($output))) {
    $errors[] = "Lo shortcode non produce output HTML";
} else {
    if (strpos($output, 'fp-resv-widget') === false) {
        $errors[] = "L'output HTML non contiene la classe 'fp-resv-widget'";
    }
    if (strpos($output, 'data-fp-resv-app') === false) {
        $warnings[] = "L'output HTML non contiene l'attributo 'data-fp-resv-app'";
    }
}

if (!$cssRegistered) {
    $errors[] = "Il CSS non è registrato in WordPress";
} elseif (!file_exists($cssPath ?? '')) {
    $errors[] = "Il file CSS non esiste fisicamente sul server";
}

if (!$jsModuleRegistered && !$jsLegacyRegistered) {
    $errors[] = "Nessun file JavaScript è registrato";
} else {
    if ($jsModuleRegistered && !file_exists($jsPath ?? '')) {
        $errors[] = "Il file JavaScript module non esiste fisicamente";
    }
    if ($jsLegacyRegistered && !file_exists($jsLegacyPath ?? '')) {
        $errors[] = "Il file JavaScript legacy non esiste fisicamente";
    }
}

if (!file_exists($templatePath)) {
    $errors[] = "Il template form.php non esiste";
}

if (count($errors) > 0) {
    echo "❌ TROVATI " . count($errors) . " ERRORI:\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". " . $error . "\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠ TROVATI " . count($warnings) . " WARNING:\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". " . $warning . "\n";
    }
    echo "\n";
}

if (count($errors) === 0 && count($warnings) === 0) {
    echo "✅ TUTTO OK! Il plugin sembra configurato correttamente.\n\n";
    echo "SE IL FORM NON SI VEDE NELLA PAGINA, POTREBBE ESSERE:\n";
    echo "  1. Un problema di CSS che nasconde il form\n";
    echo "  2. Il JavaScript non si sta caricando\n";
    echo "  3. Un conflitto con il tema o altri plugin\n";
    echo "  4. Cache del browser o del server\n\n";
    echo "PROSSIMI PASSI:\n";
    echo "  1. Apri la pagina con lo shortcode\n";
    echo "  2. Apri la console del browser (F12)\n";
    echo "  3. Controlla se ci sono errori JavaScript\n";
    echo "  4. Cerca '[FP-RESV]' nei log della console\n";
    echo "  5. Nella tab 'Elementi', cerca 'fp-resv-widget' nel DOM\n";
    echo "  6. Verifica gli stili applicati all'elemento\n\n";
} else {
    echo "AZIONE RICHIESTA:\n";
    echo "  Correggi gli errori sopra elencati prima di procedere.\n\n";
}

echo "=== FINE TEST ===\n";

