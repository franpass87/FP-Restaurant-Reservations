<?php
/**
 * Check Shortcode Logs - Verifica i log del plugin
 */

// Bootstrap WordPress
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Shortcode Logs</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .section {
            background: #252526;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #007acc;
        }
        .success { border-left-color: #4ec9b0; }
        .error { border-left-color: #f48771; }
        .warning { border-left-color: #dcdcaa; }
        .log-line {
            padding: 5px 0;
            border-bottom: 1px solid #333;
        }
        .log-error { color: #f48771; }
        .log-warning { color: #dcdcaa; }
        .log-info { color: #4ec9b0; }
        code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 3px;
            color: #ce9178;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007acc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a9e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 FP Restaurant Reservations - Diagnostica Shortcode</h1>
        
        <?php
        // Check 1: Plugin Status
        echo '<div class="section ' . (class_exists('FP\\Resv\\Core\\Plugin') ? 'success' : 'error') . '">';
        echo '<h2>1. Status Plugin</h2>';
        if (class_exists('FP\\Resv\\Core\\Plugin')) {
            echo '<p>✓ Plugin attivo</p>';
            echo '<p>Versione: <code>' . FP\Resv\Core\Plugin::VERSION . '</code></p>';
            echo '<p>Directory: <code>' . FP\Resv\Core\Plugin::$dir . '</code></p>';
        } else {
            echo '<p>✗ Plugin NON attivo o non caricato</p>';
        }
        echo '</div>';
        
        // Check 2: Shortcode Registration
        global $shortcode_tags;
        echo '<div class="section ' . (isset($shortcode_tags['fp_reservations']) ? 'success' : 'error') . '">';
        echo '<h2>2. Registrazione Shortcode</h2>';
        if (isset($shortcode_tags['fp_reservations'])) {
            echo '<p>✓ Shortcode <code>[fp_reservations]</code> registrato</p>';
            $callback = $shortcode_tags['fp_reservations'];
            if (is_array($callback)) {
                echo '<p>Callback: <code>' . get_class($callback[0]) . '::' . $callback[1] . '</code></p>';
            }
        } else {
            echo '<p>✗ Shortcode <code>[fp_reservations]</code> NON registrato</p>';
            echo '<p>Shortcode disponibili: ' . implode(', ', array_keys($shortcode_tags)) . '</p>';
        }
        echo '</div>';
        
        // Check 3: Options/Settings
        echo '<div class="section">';
        echo '<h2>3. Configurazione Plugin</h2>';
        
        if (class_exists('FP\\Resv\\Core\\ServiceContainer')) {
            try {
                $container = FP\Resv\Core\ServiceContainer::getInstance();
                $options = $container->get(FP\Resv\Domain\Settings\Options::class);
                
                if ($options) {
                    echo '<p>✓ ServiceContainer e Options disponibili</p>';
                    
                    // Check essential settings
                    $general = $options->getGroup('fp_resv_general', []);
                    $restaurantName = $general['restaurant_name'] ?? '';
                    $meals = $general['frontend_meals'] ?? '';
                    
                    echo '<p>Nome Ristorante: <code>' . ($restaurantName ?: '(non configurato)') . '</code></p>';
                    echo '<p>Servizi configurati: ' . (empty($meals) ? '<span class="log-warning">⚠ NESSUNO</span>' : '<span class="log-info">✓</span>') . '</p>';
                    
                    if (empty($restaurantName) || empty($meals)) {
                        echo '<div class="warning" style="margin-top: 15px; padding: 10px; background: #3c3c00; border-radius: 5px;">';
                        echo '<strong>⚠️ ATTENZIONE:</strong> Le impostazioni essenziali non sono configurate!<br>';
                        echo 'Vai in <strong>FP Reservations → Impostazioni</strong> e configura:';
                        echo '<ul>';
                        echo '<li>Nome del ristorante</li>';
                        echo '<li>Servizi (pranzo/cena)</li>';
                        echo '<li>Orari di apertura</li>';
                        echo '</ul>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="log-error">✗ Options non disponibile</p>';
                }
            } catch (Exception $e) {
                echo '<p class="log-error">✗ Errore: ' . esc_html($e->getMessage()) . '</p>';
            }
        }
        echo '</div>';
        
        // Check 4: Test Shortcode Execution
        echo '<div class="section">';
        echo '<h2>4. Test Esecuzione Shortcode</h2>';
        echo '<p>Eseguo il shortcode per verificare l\'output...</p>';
        
        // Enable error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        
        // Capture any output
        ob_start();
        $startTime = microtime(true);
        
        try {
            $output = do_shortcode('[fp_reservations]');
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $buffered = ob_get_clean();
            
            if ($buffered) {
                echo '<div class="warning" style="margin: 10px 0; padding: 10px; background: #3c3c00;">';
                echo '<strong>⚠️ Output buffered (possibile errore PHP):</strong><br>';
                echo '<pre style="white-space: pre-wrap; max-height: 200px; overflow-y: auto;">' . esc_html($buffered) . '</pre>';
                echo '</div>';
            }
            
            echo '<p>Tempo di esecuzione: <code>' . $executionTime . 'ms</code></p>';
            echo '<p>Lunghezza output: <code>' . strlen($output) . '</code> bytes</p>';
            
            if (empty(trim($output))) {
                echo '<p class="log-error">✗ Lo shortcode NON ha prodotto output!</p>';
                echo '<div class="error" style="margin-top: 15px; padding: 10px; background: #3c0000; border-radius: 5px;">';
                echo '<strong>❌ PROBLEMA IDENTIFICATO:</strong><br>';
                echo 'Lo shortcode viene eseguito ma non produce HTML. Possibili cause:';
                echo '<ul>';
                echo '<li>Il template non trova i dati necessari (context vuoto)</li>';
                echo '<li>Errore nel FormContext builder</li>';
                echo '<li>Impostazioni del plugin incomplete</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<p class="log-info">✓ Lo shortcode ha prodotto output</p>';
                
                // Check for key elements
                $hasWidget = strpos($output, 'fp-resv-widget') !== false;
                $hasForm = strpos($output, 'fp-resv-widget__form') !== false;
                $hasDataAttr = strpos($output, 'data-fp-resv-app') !== false;
                
                echo '<p>' . ($hasWidget ? '✓' : '✗') . ' Contiene classe <code>fp-resv-widget</code></p>';
                echo '<p>' . ($hasForm ? '✓' : '✗') . ' Contiene form</p>';
                echo '<p>' . ($hasDataAttr ? '✓' : '✗') . ' Contiene attributo <code>data-fp-resv-app</code></p>';
                
                if ($hasWidget && $hasForm && $hasDataAttr) {
                    echo '<div class="success" style="margin-top: 15px; padding: 10px; background: #003c00; border-radius: 5px;">';
                    echo '<strong>✅ IL FORM È CORRETTO!</strong><br>';
                    echo 'Il problema potrebbe essere nel caricamento della pagina o nella cache.';
                    echo '</div>';
                }
                
                // Show first 1000 chars of output
                echo '<details style="margin-top: 15px;">';
                echo '<summary style="cursor: pointer; color: #4ec9b0;">Mostra output HTML (primi 1000 caratteri)</summary>';
                echo '<pre style="background: #1e1e1e; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 400px;">';
                echo esc_html(substr($output, 0, 1000));
                if (strlen($output) > 1000) {
                    echo "\n\n... [troncato, totale " . strlen($output) . " bytes]";
                }
                echo '</pre>';
                echo '</details>';
            }
        } catch (Exception $e) {
            ob_end_clean();
            echo '<p class="log-error">✗ Errore durante l\'esecuzione: ' . esc_html($e->getMessage()) . '</p>';
            echo '<pre style="background: #3c0000; padding: 10px; border-radius: 5px;">' . esc_html($e->getTraceAsString()) . '</pre>';
        }
        echo '</div>';
        
        // Check 5: PHP Error Log
        echo '<div class="section">';
        echo '<h2>5. Log PHP Recenti</h2>';
        
        $logFile = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $lines = explode("\n", $logContent);
            $fpResvLogs = [];
            
            foreach ($lines as $line) {
                if (stripos($line, '[FP-RESV]') !== false || stripos($line, 'FP\\Resv') !== false) {
                    $fpResvLogs[] = $line;
                }
            }
            
            if (count($fpResvLogs) > 0) {
                echo '<p>Trovate <code>' . count($fpResvLogs) . '</code> righe di log</p>';
                echo '<p>Ultime 20 righe:</p>';
                echo '<div style="background: #1e1e1e; padding: 10px; border-radius: 5px; max-height: 400px; overflow-y: auto;">';
                
                $lastLogs = array_slice($fpResvLogs, -20);
                foreach ($lastLogs as $log) {
                    $class = 'log-line';
                    if (stripos($log, 'error') !== false || stripos($log, 'critical') !== false) {
                        $class .= ' log-error';
                    } elseif (stripos($log, 'warning') !== false) {
                        $class .= ' log-warning';
                    } elseif (stripos($log, 'rendering') !== false || stripos($log, 'correttamente') !== false) {
                        $class .= ' log-info';
                    }
                    
                    echo '<div class="' . $class . '">' . esc_html($log) . '</div>';
                }
                echo '</div>';
            } else {
                echo '<p class="log-warning">⚠ Nessun log trovato con [FP-RESV]</p>';
                echo '<p>Questo potrebbe significare che lo shortcode non viene mai eseguito.</p>';
            }
        } else {
            echo '<p class="log-warning">⚠ File debug.log non trovato</p>';
            echo '<p>Path atteso: <code>' . $logFile . '</code></p>';
        }
        echo '</div>';
        
        // Actions
        echo '<div class="section">';
        echo '<h2>6. Azioni Consigliate</h2>';
        
        if (!isset($shortcode_tags['fp_reservations'])) {
            echo '<p class="log-error">→ Il plugin non è completamente caricato. Riattivalo.</p>';
        } elseif (empty(trim($output ?? ''))) {
            echo '<p class="log-error">→ Configura le impostazioni del plugin (nome ristorante, servizi, orari)</p>';
            echo '<a href="' . admin_url('admin.php?page=fp-resv-settings') . '" class="btn">Vai alle Impostazioni</a>';
        } else {
            echo '<p class="log-info">→ Il form sembra funzionare. Controlla la cache o i conflitti CSS/JS.</p>';
            echo '<a href="' . home_url() . '" class="btn">Torna al sito</a>';
        }
        
        echo '<a href="?refresh=' . time() . '" class="btn">Aggiorna Diagnostica</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

