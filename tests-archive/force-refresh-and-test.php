<?php
/**
 * Script per forzare il refresh della cache PHP e testare l'endpoint
 * 
 * IMPORTANTE: Esegui questo script dal browser PRIMA di testare la creazione prenotazione
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Verifica permessi
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Accesso negato. Devi essere loggato come amministratore.');
}

// Forza il flush di tutte le cache
if (function_exists('opcache_reset')) {
    opcache_reset();
    $opcache = '✅ OPcache resettata';
} else {
    $opcache = '⚠️ OPcache non disponibile';
}

if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    $wp_cache = '✅ WP Cache pulita';
} else {
    $wp_cache = '⚠️ WP Cache non disponibile';
}

// Verifica che la classe sia caricata
$class_exists = class_exists('FP\Resv\Domain\Reservations\AdminREST');

// Verifica che l'endpoint sia registrato
$routes = rest_get_server()->get_routes();
$endpoint_exists = isset($routes['/fp-resv/v1/agenda/reservations']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Refresh & Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d4ed8;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .success {
            background: #d1fae5;
            border-color: #059669;
            color: #065f46;
        }
        .error {
            background: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        .warning {
            background: #fef3c7;
            border-color: #d97706;
            color: #92400e;
        }
        button {
            background: #1d4ed8;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
        }
        button:hover {
            background: #1e40af;
        }
        pre {
            background: #f9fafb;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #e5e7eb;
        }
        #testResult {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Force Refresh & Test</h1>
        
        <div class="status <?php echo $class_exists ? 'success' : 'error'; ?>">
            <strong>AdminREST Class:</strong> <?php echo $class_exists ? '✅ Caricata' : '❌ NON Trovata'; ?>
        </div>
        
        <div class="status <?php echo $endpoint_exists ? 'success' : 'error'; ?>">
            <strong>Endpoint Registrato:</strong> <?php echo $endpoint_exists ? '✅ Sì' : '❌ No'; ?>
        </div>
        
        <div class="status success">
            <strong>Cache PHP:</strong> <?php echo $opcache; ?>
        </div>
        
        <div class="status success">
            <strong>WordPress Cache:</strong> <?php echo $wp_cache; ?>
        </div>
        
        <?php if ($class_exists && $endpoint_exists): ?>
            <hr style="margin: 30px 0;">
            <h2>🧪 Test Endpoint Diretto</h2>
            <p>Questo test simula una richiesta POST all'endpoint e mostra la risposta.</p>
            
            <button onclick="testEndpoint()">🚀 Testa Creazione Prenotazione</button>
            
            <div id="testResult"></div>
        <?php else: ?>
            <div class="status error">
                <strong>⚠️ PROBLEMA RILEVATO!</strong><br>
                La classe o l'endpoint non sono disponibili. Prova a disattivare e riattivare il plugin.
            </div>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        <div class="status warning">
            <strong>📋 Prossimi Step:</strong><br>
            1. ✅ Cache pulita<br>
            2. 🔄 Ricarica completamente questa pagina (CTRL+F5 o CMD+R)<br>
            3. 🧪 Clicca "Testa Creazione Prenotazione" qui sopra<br>
            4. 📊 Poi riprova dal Manager normale
        </div>
    </div>
    
    <script>
        async function testEndpoint() {
            const result = document.getElementById('testResult');
            result.innerHTML = '<div class="status warning">⏳ Test in corso...</div>';
            
            const testData = {
                date: '<?php echo date('Y-m-d', strtotime('+1 day')); ?>',
                time: '19:30',
                party: 2,
                meal: 'dinner',
                first_name: 'Test',
                last_name: 'Debug',
                email: 'test.debug@example.com',
                phone: '+39 123 456 7890',
                notes: 'Test da force-refresh-and-test.php',
                status: 'confirmed',
                language: 'it',
                locale: 'it_IT'
            };
            
            console.log('📤 Invio test data:', testData);
            
            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda/reservations'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                    },
                    body: JSON.stringify(testData)
                });
                
                console.log('📥 Response status:', response.status);
                console.log('📥 Response headers:', [...response.headers.entries()]);
                
                // Verifica header custom
                const debugHeader = response.headers.get('X-FP-Resv-Debug');
                const idHeader = response.headers.get('X-FP-Resv-ID');
                
                const responseText = await response.text();
                console.log('📥 Response text:', responseText);
                
                let html = '<div class="status ' + (response.ok ? 'success' : 'error') + '">';
                html += '<strong>Status:</strong> ' + response.status + ' ' + response.statusText + '<br>';
                html += '<strong>Debug Header:</strong> ' + (debugHeader || 'NESSUNO') + '<br>';
                html += '<strong>ID Header:</strong> ' + (idHeader || 'NESSUNO') + '<br>';
                html += '</div>';
                
                if (!responseText || responseText.trim() === '') {
                    html += '<div class="status error">';
                    html += '<strong>❌ RISPOSTA VUOTA!</strong><br>';
                    html += 'Il server ha restituito status 200 ma il body è vuoto.<br><br>';
                    
                    if (!debugHeader) {
                        html += '⚠️ Gli header custom NON ci sono → Il nuovo codice NON è stato caricato!<br>';
                        html += '👉 SOLUZIONE: Disattiva e riattiva il plugin dal menu Plugin di WordPress.';
                    } else {
                        html += '✅ Gli header custom ci sono → Il codice è stato caricato<br>';
                        html += '⚠️ Ma il body è vuoto → C\'è un problema con l\'output buffering o un filtro WordPress.';
                    }
                    
                    html += '</div>';
                } else {
                    html += '<div class="status success">';
                    html += '<strong>✅ Risposta ricevuta!</strong><br>';
                    html += '<strong>Lunghezza:</strong> ' + responseText.length + ' bytes';
                    html += '</div>';
                    
                    try {
                        const jsonData = JSON.parse(responseText);
                        html += '<div class="status success">';
                        html += '<strong>✅ JSON valido!</strong><br>';
                        if (jsonData.reservation && jsonData.reservation.id) {
                            html += '<strong>🎉 Prenotazione creata con ID:</strong> ' + jsonData.reservation.id;
                        }
                        html += '</div>';
                        html += '<pre>' + JSON.stringify(jsonData, null, 2) + '</pre>';
                    } catch (e) {
                        html += '<div class="status error">';
                        html += '<strong>❌ JSON non valido:</strong> ' + e.message;
                        html += '</div>';
                        html += '<pre>' + responseText.substring(0, 500) + '</pre>';
                    }
                }
                
                result.innerHTML = html;
                
            } catch (error) {
                console.error('❌ Errore:', error);
                result.innerHTML = '<div class="status error"><strong>❌ Errore di rete:</strong> ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>

