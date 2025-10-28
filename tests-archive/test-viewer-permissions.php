<?php
/**
 * Test per verificare che il Viewer NON possa modificare prenotazioni
 */

require_once __DIR__ . '/../../../wp-load.php';

if (!is_user_logged_in()) {
    wp_die('Devi essere loggato');
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Verifica capabilities
$can_manage = current_user_can('manage_fp_reservations');
$can_view = current_user_can('view_fp_reservations_manager');
$is_admin = current_user_can('manage_options');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Viewer Permissions</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f0f0f1; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1d4ed8; }
        .info { background: #e0e7ff; padding: 15px; border-radius: 6px; border-left: 4px solid #4f46e5; margin: 20px 0; }
        .success { background: #d1fae5; padding: 15px; border-radius: 6px; border-left: 4px solid #059669; margin: 20px 0; }
        .error { background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #dc2626; margin: 20px 0; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 6px; border-left: 4px solid #d97706; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .yes { color: #059669; font-weight: bold; }
        .no { color: #dc2626; font-weight: bold; }
        button { background: #1d4ed8; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; margin: 5px; }
        button:hover { background: #1e40af; }
        button.danger { background: #dc2626; }
        button.danger:hover { background: #b91c1c; }
        #result { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test Permessi Viewer</h1>
        
        <div class="info">
            <strong>Utente Corrente:</strong> <?php echo esc_html($current_user->user_login); ?> (ID: <?php echo $user_id; ?>)<br>
            <strong>Ruoli:</strong> <?php echo implode(', ', $current_user->roles); ?>
        </div>
        
        <h2>📋 Capabilities</h2>
        <table>
            <thead>
                <tr>
                    <th>Capability</th>
                    <th>Stato</th>
                    <th>Descrizione</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>manage_options</code></td>
                    <td class="<?php echo $is_admin ? 'yes' : 'no'; ?>">
                        <?php echo $is_admin ? '✅ SÌ' : '❌ NO'; ?>
                    </td>
                    <td>Administrator - Può fare tutto</td>
                </tr>
                <tr>
                    <td><code>manage_fp_reservations</code></td>
                    <td class="<?php echo $can_manage ? 'yes' : 'no'; ?>">
                        <?php echo $can_manage ? '✅ SÌ' : '❌ NO'; ?>
                    </td>
                    <td>Restaurant Manager - Può creare/modificare</td>
                </tr>
                <tr>
                    <td><code>view_fp_reservations_manager</code></td>
                    <td class="<?php echo $can_view ? 'yes' : 'no'; ?>">
                        <?php echo $can_view ? '✅ SÌ' : '❌ NO'; ?>
                    </td>
                    <td>Reservations Viewer - Può SOLO visualizzare</td>
                </tr>
            </tbody>
        </table>
        
        <?php if ($can_view && !$can_manage && !$is_admin): ?>
            <div class="warning">
                <strong>⚠️ Sei un VIEWER</strong><br>
                Dovresti poter VEDERE le prenotazioni ma NON modificarle.
            </div>
        <?php elseif ($can_manage || $is_admin): ?>
            <div class="success">
                <strong>✅ Hai i permessi di GESTIONE</strong><br>
                Puoi creare, modificare ed eliminare prenotazioni.
            </div>
        <?php else: ?>
            <div class="error">
                <strong>❌ Nessun permesso</strong><br>
                Non hai accesso al sistema di prenotazioni.
            </div>
        <?php endif; ?>
        
        <h2>🧪 Test Pratici</h2>
        <p>Clicca sui pulsanti per testare gli endpoint REST:</p>
        
        <button onclick="testRead()">📖 Test LETTURA (GET /agenda)</button>
        <button onclick="testCreate()">➕ Test CREAZIONE (POST /agenda/reservations)</button>
        <button class="danger" onclick="testUpdate()">✏️ Test MODIFICA (PUT /agenda/reservations/1)</button>
        <button class="danger" onclick="testDelete()">🗑️ Test ELIMINAZIONE (DELETE /agenda/reservations/999999)</button>
        
        <div id="result"></div>
    </div>
    
    <script>
        const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
        
        async function testRead() {
            showResult('⏳ Test lettura in corso...', 'info');
            
            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda'); ?>?date=<?php echo date('Y-m-d'); ?>', {
                    headers: { 'X-WP-Nonce': nonce }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showResult('✅ LETTURA: CONSENTITA<br>Hai accesso all\'endpoint di lettura.', 'success');
                } else {
                    showResult('❌ LETTURA: NEGATA<br>' + (data.message || 'Errore sconosciuto'), 'error');
                }
            } catch (e) {
                showResult('❌ Errore: ' + e.message, 'error');
            }
        }
        
        async function testCreate() {
            showResult('⏳ Test creazione in corso...', 'info');
            
            const testData = {
                date: '<?php echo date('Y-m-d', strtotime('+1 day')); ?>',
                time: '19:30',
                party: 2,
                first_name: 'Test',
                last_name: 'Viewer',
                email: 'test.viewer@example.com',
                phone: '+39 123 456 7890',
                status: 'confirmed',
                meal: 'dinner'
            };
            
            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda/reservations'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify(testData)
                });
                
                const data = await response.json();
                
                if (response.status === 403) {
                    showResult('✅ CREAZIONE: BLOCCATA (Come atteso per Viewer)<br>Messaggio: ' + (data.message || 'Permesso negato'), 'success');
                } else if (response.ok) {
                    showResult('⚠️ CREAZIONE: CONSENTITA<br>Attenzione: Il Viewer può creare prenotazioni! Questo potrebbe essere un problema di sicurezza.', 'error');
                } else {
                    showResult('ℹ️ Status: ' + response.status + '<br>' + (data.message || 'Errore'), 'warning');
                }
            } catch (e) {
                showResult('❌ Errore: ' + e.message, 'error');
            }
        }
        
        async function testUpdate() {
            showResult('⏳ Test modifica in corso...', 'info');
            
            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda/reservations/1'); ?>', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ party: 3 })
                });
                
                const data = await response.json();
                
                if (response.status === 403) {
                    showResult('✅ MODIFICA: BLOCCATA (Come atteso per Viewer)<br>Messaggio: ' + (data.message || 'Permesso negato'), 'success');
                } else if (response.ok) {
                    showResult('⚠️ MODIFICA: CONSENTITA<br>Attenzione: Il Viewer può modificare prenotazioni!', 'error');
                } else {
                    showResult('ℹ️ Status: ' + response.status + '<br>' + (data.message || 'Errore'), 'warning');
                }
            } catch (e) {
                showResult('❌ Errore: ' + e.message, 'error');
            }
        }
        
        async function testDelete() {
            showResult('⏳ Test eliminazione in corso...', 'info');
            
            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda/reservations/999999'); ?>', {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': nonce }
                });
                
                const data = await response.json();
                
                if (response.status === 403) {
                    showResult('✅ ELIMINAZIONE: BLOCCATA (Come atteso per Viewer)<br>Messaggio: ' + (data.message || 'Permesso negato'), 'success');
                } else if (response.status === 404) {
                    showResult('⚠️ ELIMINAZIONE: Prenotazione non trovata (ma endpoint accessibile!)', 'error');
                } else if (response.ok) {
                    showResult('⚠️ ELIMINAZIONE: CONSENTITA<br>Attenzione: Il Viewer può eliminare prenotazioni!', 'error');
                } else {
                    showResult('ℹ️ Status: ' + response.status + '<br>' + (data.message || 'Errore'), 'warning');
                }
            } catch (e) {
                showResult('❌ Errore: ' + e.message, 'error');
            }
        }
        
        function showResult(message, type) {
            const result = document.getElementById('result');
            const className = type === 'success' ? 'success' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info';
            result.innerHTML = '<div class="' + className + '">' + message + '</div>';
        }
    </script>
</body>
</html>

