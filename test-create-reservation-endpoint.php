<?php
/**
 * Script di test per verificare l'endpoint di creazione prenotazione
 * 
 * Uso: Aprire questo file dal browser mentre si è loggati come admin
 */

// Carica WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Verifica che l'utente sia loggato e abbia i permessi
if (!is_user_logged_in()) {
    wp_die('Devi essere loggato per usare questo script.');
}

if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi necessari.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Endpoint Creazione Prenotazione</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
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
            margin-top: 0;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .test-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #374151;
        }
        pre {
            background: #f9fafb;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #e5e7eb;
        }
        .success {
            color: #059669;
            background: #d1fae5;
            padding: 10px 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #dc2626;
            background: #fee2e2;
            padding: 10px 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            color: #d97706;
            background: #fef3c7;
            padding: 10px 15px;
            border-radius: 4px;
            margin: 10px 0;
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
        }
        button:hover {
            background: #1e40af;
        }
        #result {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test Endpoint Creazione Prenotazione</h1>
        
        <div class="test-section">
            <div class="test-title">Informazioni Sistema</div>
            <pre><?php
echo "REST URL: " . rest_url('fp-resv/v1/agenda/reservations') . "\n";
echo "User ID: " . get_current_user_id() . "\n";
echo "User Login: " . wp_get_current_user()->user_login . "\n";
echo "Can manage reservations: " . (current_user_can('manage_fp_reservations') ? 'YES' : 'NO') . "\n";
echo "Can manage options: " . (current_user_can('manage_options') ? 'YES' : 'NO') . "\n";
echo "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'TRUE' : 'FALSE') . "\n";
            ?></pre>
        </div>

        <div class="test-section">
            <div class="test-title">Test Creazione Prenotazione</div>
            
            <form id="testForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Data *</label>
                        <input type="date" name="date" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Ora *</label>
                        <input type="time" name="time" required value="19:30">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Numero Persone *</label>
                        <input type="number" name="party" required value="2" min="1">
                    </div>
                    <div class="form-group">
                        <label>Servizio</label>
                        <select name="meal">
                            <option value="">Nessuno</option>
                            <option value="lunch">Pranzo</option>
                            <option value="dinner" selected>Cena</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="first_name" required value="Mario">
                    </div>
                    <div class="form-group">
                        <label>Cognome *</label>
                        <input type="text" name="last_name" required value="Rossi">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required value="mario.rossi@example.com">
                    </div>
                    <div class="form-group">
                        <label>Telefono</label>
                        <input type="tel" name="phone" value="+39 123 456 7890">
                    </div>
                </div>

                <div class="form-group">
                    <label>Note</label>
                    <textarea name="notes" rows="3">Test prenotazione dal manager</textarea>
                </div>

                <div class="form-group">
                    <label>Allergie</label>
                    <textarea name="allergies" rows="2"></textarea>
                </div>

                <button type="submit">📤 Crea Prenotazione</button>
            </form>

            <div id="result"></div>
        </div>

        <div class="test-section">
            <div class="test-title">Log Errori PHP</div>
            <div id="phpLogs">
                <button onclick="loadPhpLogs()">🔄 Carica Log</button>
                <pre id="logContent"></pre>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('testForm');
        const result = document.getElementById('result');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            result.innerHTML = '<div class="warning">⏳ Invio richiesta in corso...</div>';

            const formData = new FormData(form);
            const data = {
                date: formData.get('date'),
                time: formData.get('time').substring(0, 5), // HH:MM
                party: parseInt(formData.get('party')),
                meal: formData.get('meal') || '',
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                email: formData.get('email'),
                phone: formData.get('phone') || '',
                notes: formData.get('notes') || '',
                allergies: formData.get('allergies') || '',
                status: 'confirmed',
                language: 'it',
                locale: 'it_IT',
            };

            console.log('📤 Invio dati:', data);

            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda/reservations'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>',
                    },
                    body: JSON.stringify(data),
                });

                console.log('📥 Response status:', response.status);
                console.log('📥 Response headers:', [...response.headers.entries()]);

                const responseText = await response.text();
                console.log('📥 Response text:', responseText);

                let html = `<div class="test-title">Risposta Server</div>`;
                html += `<pre><strong>Status:</strong> ${response.status} ${response.statusText}\n`;
                html += `<strong>Content-Type:</strong> ${response.headers.get('Content-Type') || 'N/A'}\n\n`;

                if (!responseText || responseText.trim() === '') {
                    html += `<strong class="error">⚠️ RISPOSTA VUOTA!</strong>\n`;
                    html += `Questo è il problema: il server non restituisce alcun contenuto.\n`;
                    html += `Verifica i log PHP qui sotto per vedere gli errori.`;
                    result.innerHTML = html + '</pre>';
                    return;
                }

                html += `<strong>Body:</strong>\n${responseText}</pre>`;

                try {
                    const jsonData = JSON.parse(responseText);
                    html += `<div class="success">✅ JSON valido!</div>`;
                    html += `<pre>${JSON.stringify(jsonData, null, 2)}</pre>`;
                    
                    if (jsonData.reservation && jsonData.reservation.id) {
                        html += `<div class="success">✅ Prenotazione creata con ID: ${jsonData.reservation.id}</div>`;
                    }
                } catch (e) {
                    html += `<div class="error">❌ Errore parsing JSON: ${e.message}</div>`;
                }

                result.innerHTML = html;

            } catch (error) {
                console.error('❌ Errore:', error);
                result.innerHTML = `
                    <div class="error">
                        <strong>❌ Errore di rete o JavaScript:</strong>
                        <pre>${error.message}\n${error.stack || ''}</pre>
                    </div>
                `;
            }
        });

        async function loadPhpLogs() {
            const logContent = document.getElementById('logContent');
            logContent.textContent = '⏳ Caricamento log...';

            try {
                // Prova a leggere i log recenti
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=fp_resv_get_logs&_wpnonce=<?php echo wp_create_nonce('fp_resv_logs'); ?>');
                const text = await response.text();
                logContent.textContent = text || 'Nessun log disponibile';
            } catch (e) {
                logContent.textContent = 'Impossibile caricare i log: ' + e.message;
            }
        }
    </script>
</body>
</html>

