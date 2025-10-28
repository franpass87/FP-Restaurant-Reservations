<?php
/**
 * Test diretto dello shortcode FP Restaurant Reservations
 * 
 * ISTRUZIONI:
 * 1. Carica questo file nella root del sito WordPress
 * 2. Vai su: https://tuosito.com/test-shortcode-direct.php
 * 3. Controlla i log e l'output
 */

// Carica WordPress
require_once __DIR__ . '/wp-load.php';

// Forza debug
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', true);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Shortcode FP Reservations</title>
    <?php wp_head(); ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
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
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Shortcode FP Restaurant Reservations</h1>
    
    <div class="test-section">
        <h2>1️⃣ Verifica Plugin Attivo</h2>
        <?php
        $plugin_active = is_plugin_active('fp-restaurant-reservations/fp-restaurant-reservations.php');
        if ($plugin_active) {
            echo '<div class="test-result success">✅ Plugin ATTIVO</div>';
        } else {
            echo '<div class="test-result error">❌ Plugin NON ATTIVO</div>';
            echo '<p><strong>Azione richiesta:</strong> Attiva il plugin FP Restaurant Reservations</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>2️⃣ Verifica Shortcode Registrato</h2>
        <?php
        global $shortcode_tags;
        if (isset($shortcode_tags['fp_reservations'])) {
            echo '<div class="test-result success">✅ Shortcode [fp_reservations] REGISTRATO</div>';
            echo '<pre>';
            echo 'Callback: ';
            if (is_array($shortcode_tags['fp_reservations'])) {
                echo get_class($shortcode_tags['fp_reservations'][0]) . '::' . $shortcode_tags['fp_reservations'][1];
            } else {
                var_dump($shortcode_tags['fp_reservations']);
            }
            echo '</pre>';
        } else {
            echo '<div class="test-result error">❌ Shortcode [fp_reservations] NON REGISTRATO</div>';
            echo '<div class="test-result info">Shortcodes disponibili: ' . implode(', ', array_keys($shortcode_tags)) . '</div>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>3️⃣ Test Esecuzione Shortcode</h2>
        <p><strong>Eseguiamo do_shortcode('[fp_reservations]')...</strong></p>
        <?php
        error_log('==========================================');
        error_log('[TEST-SHORTCODE] Inizio test esecuzione shortcode');
        error_log('==========================================');
        
        ob_start();
        $output = do_shortcode('[fp_reservations]');
        $buffer = ob_get_clean();
        
        error_log('[TEST-SHORTCODE] Output buffer length: ' . strlen($buffer));
        error_log('[TEST-SHORTCODE] Shortcode output length: ' . strlen($output));
        error_log('[TEST-SHORTCODE] Output inizio: ' . substr($output, 0, 200));
        
        if (!empty($output)) {
            echo '<div class="test-result success">✅ Shortcode ESEGUITO - Output generato (' . strlen($output) . ' caratteri)</div>';
            
            // Verifica presenza elementi chiave
            $has_widget = strpos($output, 'fp-resv-widget') !== false;
            $has_data_attr = strpos($output, 'data-fp-resv') !== false;
            $has_app_attr = strpos($output, 'data-fp-resv-app') !== false;
            
            echo '<div class="test-result info">';
            echo '• Classe .fp-resv-widget: ' . ($has_widget ? '✅' : '❌') . '<br>';
            echo '• Attributo data-fp-resv: ' . ($has_data_attr ? '✅' : '❌') . '<br>';
            echo '• Attributo data-fp-resv-app: ' . ($has_app_attr ? '✅' : '❌') . '<br>';
            echo '</div>';
            
            if (!$has_widget || !$has_data_attr || !$has_app_attr) {
                echo '<div class="test-result error">⚠️ PROBLEMA: Il template non contiene tutti gli attributi necessari per il JavaScript!</div>';
            }
        } else {
            echo '<div class="test-result error">❌ Shortcode NON produce output (stringa vuota)</div>';
            echo '<p><strong>Possibili cause:</strong></p>';
            echo '<ul>';
            echo '<li>Errore PHP durante l\'esecuzione</li>';
            echo '<li>Template non trovato</li>';
            echo '<li>ServiceContainer non disponibile</li>';
            echo '<li>Errore nella classe Shortcodes::render()</li>';
            echo '</ul>';
            echo '<p><strong>Controlla:</strong> wp-content/debug.log per messaggi [FP-RESV]</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>4️⃣ Output HTML del Form</h2>
        <div style="border: 3px solid #007bff; padding: 20px; background: #f8f9fa;">
            <?php echo $output; ?>
        </div>
    </div>

    <div class="test-section">
        <h2>5️⃣ Analisi DOM tramite JavaScript</h2>
        <div id="js-test-results"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const results = document.getElementById('js-test-results');
                
                // Cerca widget
                const widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
                
                let html = '<div class="test-result ' + (widgets.length > 0 ? 'success' : 'error') + '">';
                html += widgets.length > 0 ? '✅' : '❌';
                html += ' JavaScript trova ' + widgets.length + ' widget(s)</div>';
                
                if (widgets.length > 0) {
                    html += '<div class="test-result info">';
                    html += '<strong>Widget trovati:</strong><br>';
                    widgets.forEach(function(widget, index) {
                        html += (index + 1) + '. ID: ' + (widget.id || 'N/A') + '<br>';
                        html += '   - Classe: ' + widget.className + '<br>';
                        html += '   - data-fp-resv presente: ' + (widget.hasAttribute('data-fp-resv') ? 'Sì' : 'No') + '<br>';
                        html += '   - data-fp-resv-app presente: ' + (widget.hasAttribute('data-fp-resv-app') ? 'Sì' : 'No') + '<br>';
                    });
                    html += '</div>';
                } else {
                    html += '<div class="test-result error">';
                    html += '<strong>PROBLEMA:</strong> JavaScript non trova il widget!<br>';
                    html += 'Il form è presente nel DOM ma non ha gli attributi corretti.';
                    html += '</div>';
                }
                
                results.innerHTML = html;
            });
        </script>
    </div>

    <div class="test-section">
        <h2>6️⃣ Log PHP Recenti</h2>
        <?php
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $log_lines = explode("\n", $log_content);
            $fp_resv_logs = array_filter($log_lines, function($line) {
                return strpos($line, '[FP-RESV]') !== false || strpos($line, '[TEST-SHORTCODE]') !== false;
            });
            $recent_logs = array_slice($fp_resv_logs, -20);
            
            if (!empty($recent_logs)) {
                echo '<div class="test-result info">';
                echo '<strong>Ultimi 20 log [FP-RESV]:</strong>';
                echo '<pre>' . esc_html(implode("\n", $recent_logs)) . '</pre>';
                echo '</div>';
            } else {
                echo '<div class="test-result info">Nessun log [FP-RESV] trovato nel debug.log</div>';
            }
        } else {
            echo '<div class="test-result info">File debug.log non trovato. Attiva WP_DEBUG_LOG in wp-config.php</div>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>📋 Riepilogo</h2>
        <div id="summary"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const successCount = document.querySelectorAll('.test-result.success').length;
                const errorCount = document.querySelectorAll('.test-result.error').length;
                
                const summary = document.getElementById('summary');
                let html = '<div class="test-result ' + (errorCount === 0 ? 'success' : 'error') + '">';
                html += '<strong>Risultati:</strong><br>';
                html += '✅ Test passati: ' + successCount + '<br>';
                html += '❌ Test falliti: ' + errorCount + '<br><br>';
                
                if (errorCount === 0) {
                    html += '<strong style="color: green;">🎉 TUTTO OK! Il form dovrebbe funzionare.</strong>';
                } else {
                    html += '<strong style="color: red;">⚠️ Ci sono problemi da risolvere.</strong><br>';
                    html += 'Controlla i test falliti sopra e segui le azioni consigliate.';
                }
                html += '</div>';
                
                summary.innerHTML = html;
            });
        </script>
    </div>

    <?php wp_footer(); ?>
</body>
</html>

