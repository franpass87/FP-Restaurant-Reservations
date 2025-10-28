<?php
/**
 * Script rapido per verificare prenotazioni nel database
 */

require_once __DIR__ . '/../../../wp-load.php';

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

global $wpdb;

// Conta prenotazioni
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fp_resv_reservations");

// Prendi ultime 10
$reservations = $wpdb->get_results("
    SELECT r.*, c.email, c.first_name, c.last_name 
    FROM {$wpdb->prefix}fp_resv_reservations r
    LEFT JOIN {$wpdb->prefix}fp_resv_customers c ON r.customer_id = c.id
    ORDER BY r.created_at DESC
    LIMIT 10
", ARRAY_A);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Database</title>
    <style>
        body { font-family: sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f0f0f1; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1d4ed8; }
        .count { font-size: 48px; font-weight: bold; color: #059669; text-align: center; margin: 30px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .empty { text-align: center; padding: 60px 20px; color: #6b7280; }
        .success { background: #d1fae5; padding: 15px; border-radius: 6px; border-left: 4px solid #059669; color: #065f46; margin: 20px 0; }
        .error { background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #dc2626; color: #991b1b; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Check Database Prenotazioni</h1>
        
        <div class="count">
            <?php echo number_format($count); ?> Prenotazioni
        </div>
        
        <?php if ($count > 0): ?>
            <div class="success">
                <strong>✅ Ci SONO prenotazioni nel database!</strong><br>
                Se non le vedi nel Manager, il problema è con l'endpoint REST che le carica.
            </div>
            
            <h2>Ultime 10 Prenotazioni</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Ora</th>
                        <th>Coperti</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo $r['date']; ?></td>
                        <td><?php echo substr($r['time'], 0, 5); ?></td>
                        <td><?php echo $r['party']; ?></td>
                        <td><?php echo esc_html($r['first_name'] . ' ' . $r['last_name']); ?></td>
                        <td><?php echo esc_html($r['email']); ?></td>
                        <td>
                            <span class="status status-<?php echo $r['status']; ?>">
                                <?php echo $r['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="error">
                <strong>⚠️ Database VUOTO!</strong><br>
                Non ci sono prenotazioni nel database. Questo è normale se:
                <ul>
                    <li>È una nuova installazione</li>
                    <li>Hai appena installato il plugin</li>
                    <li>Hai cancellato tutte le prenotazioni</li>
                </ul>
            </div>
            
            <div class="empty">
                <p style="font-size: 64px;">📭</p>
                <p style="font-size: 24px; font-weight: 500;">Nessuna Prenotazione</p>
                <p>Prova a crearne una di test!</p>
            </div>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        
        <h2>🔍 Test Endpoint REST</h2>
        <button onclick="testAgendaEndpoint()" style="background: #1d4ed8; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
            Testa Endpoint /agenda
        </button>
        
        <div id="testResult" style="margin-top: 20px;"></div>
    </div>
    
    <script>
        async function testAgendaEndpoint() {
            const result = document.getElementById('testResult');
            result.innerHTML = '<div style="background: #fef3c7; padding: 15px; border-radius: 6px; border-left: 4px solid #d97706; color: #92400e;">⏳ Test in corso...</div>';
            
            const today = new Date().toISOString().split('T')[0];
            
            try {
                const response = await fetch('<?php echo rest_url('fp-resv/v1/agenda'); ?>?date=' + today, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                    }
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                const responseText = await response.text();
                console.log('Response text length:', responseText.length);
                console.log('Response text:', responseText);
                
                let html = '';
                
                if (!responseText || responseText.trim() === '') {
                    html = '<div style="background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #dc2626; color: #991b1b;">';
                    html += '<strong>❌ RISPOSTA VUOTA!</strong><br>';
                    html += 'Status: ' + response.status + '<br>';
                    html += 'Anche l\'endpoint /agenda ha lo stesso problema!<br><br>';
                    html += '<strong>🔧 CAUSA PROBABILE:</strong><br>';
                    html += '• La cache PHP non è stata pulita<br>';
                    html += '• C\'è un errore PHP fatale che blocca tutto<br>';
                    html += '• Un plugin/tema sta intercettando le risposte REST<br><br>';
                    html += '<strong>👉 SOLUZIONE IMMEDIATA:</strong><br>';
                    html += '1. Vai su Plugin → Disattiva "FP Restaurant Reservations"<br>';
                    html += '2. Aspetta 3 secondi<br>';
                    html += '3. Riattiva "FP Restaurant Reservations"<br>';
                    html += '4. Ricarica COMPLETAMENTE questa pagina (CTRL+F5)';
                    html += '</div>';
                } else {
                    try {
                        const data = JSON.parse(responseText);
                        html = '<div style="background: #d1fae5; padding: 15px; border-radius: 6px; border-left: 4px solid #059669; color: #065f46;">';
                        html += '<strong>✅ Endpoint funziona!</strong><br>';
                        html += 'Status: ' + response.status + '<br>';
                        html += 'Prenotazioni ricevute: ' + (data.reservations ? data.reservations.length : 0) + '<br>';
                        html += 'Range: ' + (data.meta ? data.meta.start_date + ' → ' + data.meta.end_date : 'N/A');
                        html += '</div>';
                        html += '<pre style="background: #f9fafb; padding: 15px; border-radius: 4px; overflow-x: auto; margin-top: 10px;">' + JSON.stringify(data, null, 2).substring(0, 1000) + '...</pre>';
                    } catch (e) {
                        html = '<div style="background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #dc2626; color: #991b1b;">';
                        html += '<strong>❌ JSON non valido!</strong><br>' + e.message;
                        html += '</div>';
                        html += '<pre style="background: #f9fafb; padding: 15px; border-radius: 4px; overflow-x: auto; margin-top: 10px;">' + responseText.substring(0, 500) + '</pre>';
                    }
                }
                
                result.innerHTML = html;
                
            } catch (error) {
                result.innerHTML = '<div style="background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #dc2626; color: #991b1b;"><strong>❌ Errore:</strong> ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>

