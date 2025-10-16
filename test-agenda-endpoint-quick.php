<?php
/**
 * Test rapido endpoint /agenda per verificare la risposta
 */

require_once __DIR__ . '/../../../wp-load.php';

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

// Test diretto all'endpoint
$date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
$range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'month';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Endpoint /agenda</title>
    <style>
        body { font-family: sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f0f0f1; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1d4ed8; }
        pre { background: #f9fafb; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #e5e7eb; white-space: pre-wrap; word-wrap: break-word; }
        .success { background: #d1fae5; padding: 15px; border-radius: 6px; border-left: 4px solid #059669; margin: 20px 0; }
        .error { background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #dc2626; margin: 20px 0; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 6px; border-left: 4px solid #d97706; margin: 20px 0; }
        .info { background: #e0e7ff; padding: 15px; border-radius: 6px; border-left: 4px solid #4f46e5; margin: 20px 0; }
        button { background: #1d4ed8; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; margin: 5px; }
        button:hover { background: #1e40af; }
        input[type="date"] { padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; }
        select { padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; }
        .controls { margin: 20px 0; padding: 20px; background: #f9fafb; border-radius: 6px; }
        .controls label { display: inline-block; margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test Endpoint /agenda</h1>
        
        <div class="controls">
            <label>
                <strong>Data:</strong>
                <input type="date" id="dateInput" value="<?php echo esc_attr($date); ?>">
            </label>
            
            <label>
                <strong>Range:</strong>
                <select id="rangeInput">
                    <option value="day" <?php selected($range, 'day'); ?>>Day</option>
                    <option value="week" <?php selected($range, 'week'); ?>>Week</option>
                    <option value="month" <?php selected($range, 'month'); ?>>Month</option>
                </select>
            </label>
            
            <button onclick="testEndpoint()">🧪 Testa Endpoint</button>
            <button onclick="checkDatabase()">🗄️ Verifica Database</button>
        </div>
        
        <div id="result"></div>
        
        <h2>📊 Info Database</h2>
        <div id="dbInfo">
            <?php
            global $wpdb;
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fp_resv_reservations");
            $today = date('Y-m-d');
            $future = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fp_resv_reservations WHERE date >= %s",
                $today
            ));
            
            echo "<div class='info'>";
            echo "<strong>Totale prenotazioni nel database:</strong> " . number_format($total) . "<br>";
            echo "<strong>Prenotazioni future (da oggi):</strong> " . number_format($future) . "<br>";
            echo "<strong>Tabella:</strong> {$wpdb->prefix}fp_resv_reservations";
            echo "</div>";
            
            if ($total === '0' || $total === 0) {
                echo "<div class='warning'>";
                echo "<strong>⚠️ Database VUOTO!</strong><br>";
                echo "Non ci sono prenotazioni nel database. Questo è normale se:<br>";
                echo "• È una nuova installazione<br>";
                echo "• Hai appena installato il plugin<br>";
                echo "• Hai cancellato tutte le prenotazioni";
                echo "</div>";
            }
            ?>
        </div>
    </div>
    
    <script>
        const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
        
        async function testEndpoint() {
            const result = document.getElementById('result');
            const date = document.getElementById('dateInput').value;
            const range = document.getElementById('rangeInput').value;
            
            result.innerHTML = '<div class="info">⏳ Test in corso...</div>';
            
            const url = '<?php echo rest_url('fp-resv/v1/agenda'); ?>?' + new URLSearchParams({ date, range });
            
            console.log('🌐 URL:', url);
            
            try {
                const startTime = performance.now();
                const response = await fetch(url, {
                    headers: { 'X-WP-Nonce': nonce }
                });
                const endTime = performance.now();
                const duration = Math.round(endTime - startTime);
                
                console.log('📥 Status:', response.status);
                console.log('📥 Headers:', [...response.headers.entries()]);
                
                const text = await response.text();
                console.log('📥 Response length:', text.length);
                console.log('📥 Response:', text.substring(0, 500));
                
                let html = '<h2>📡 Risposta Endpoint</h2>';
                html += '<div class="' + (response.ok ? 'success' : 'error') + '">';
                html += '<strong>Status:</strong> ' + response.status + ' ' + response.statusText + '<br>';
                html += '<strong>Tempo:</strong> ' + duration + 'ms<br>';
                html += '<strong>Lunghezza:</strong> ' + text.length + ' bytes';
                html += '</div>';
                
                if (!text || text.trim() === '') {
                    html += '<div class="error">';
                    html += '<strong>❌ RISPOSTA VUOTA!</strong><br>';
                    html += 'L\'endpoint non ha restituito dati. Possibili cause:<br>';
                    html += '• Errore PHP fatale (controlla debug.log)<br>';
                    html += '• Output buffering problems<br>';
                    html += '• Cache non pulita';
                    html += '</div>';
                } else {
                    try {
                        const data = JSON.parse(text);
                        html += '<div class="success">';
                        html += '<strong>✅ JSON valido!</strong><br>';
                        html += '<strong>Struttura:</strong><br>';
                        html += '• meta: ' + (data.meta ? '✅' : '❌') + '<br>';
                        html += '• stats: ' + (data.stats ? '✅' : '❌') + '<br>';
                        html += '• reservations: ' + (data.reservations ? '✅ (' + data.reservations.length + ' elementi)' : '❌') + '<br>';
                        html += '• data: ' + (data.data ? '✅' : '❌');
                        html += '</div>';
                        
                        if (data.reservations && data.reservations.length > 0) {
                            html += '<div class="success">';
                            html += '<strong>🎉 Trovate ' + data.reservations.length + ' prenotazioni!</strong>';
                            html += '</div>';
                        } else {
                            html += '<div class="warning">';
                            html += '<strong>⚠️ Nessuna prenotazione nel periodo selezionato</strong><br>';
                            html += 'Range: ' + (data.meta ? data.meta.start_date + ' → ' + data.meta.end_date : 'N/A');
                            html += '</div>';
                        }
                        
                        html += '<h3>📄 Risposta Completa</h3>';
                        html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    } catch (e) {
                        html += '<div class="error">';
                        html += '<strong>❌ JSON non valido!</strong><br>' + e.message;
                        html += '</div>';
                        html += '<h3>📄 Risposta Raw</h3>';
                        html += '<pre>' + text.substring(0, 2000) + (text.length > 2000 ? '...' : '') + '</pre>';
                    }
                }
                
                result.innerHTML = html;
                
            } catch (error) {
                console.error('❌ Errore:', error);
                result.innerHTML = '<div class="error"><strong>❌ Errore:</strong> ' + error.message + '</div>';
            }
        }
        
        async function checkDatabase() {
            const result = document.getElementById('result');
            result.innerHTML = '<div class="info">⏳ Verifica database...</div>';
            
            try {
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=fp_resv_check_db&_wpnonce=' + nonce);
                const text = await response.text();
                
                result.innerHTML = '<h2>🗄️ Verifica Database</h2><pre>' + text + '</pre>';
            } catch (error) {
                // Fallback: mostra info già caricata
                result.innerHTML = '<div class="warning">Vedi info database sopra</div>';
            }
        }
        
        // Auto-test al caricamento
        window.addEventListener('load', () => {
            testEndpoint();
        });
    </script>
</body>
</html>

