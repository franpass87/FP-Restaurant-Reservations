<?php
/**
 * Test Manager Endpoints
 * Script per verificare che gli endpoint REST siano accessibili
 * 
 * Uso: wp-admin/admin.php?page=fp-resv-manager&test-endpoints=1
 */

// Verifica se siamo in un contesto WordPress
if (!defined('ABSPATH')) {
    die('Accesso diretto non consentito');
}

// Verifica se il test è richiesto
if (!isset($_GET['test-endpoints'])) {
    return;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Permessi insufficienti');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Manager Endpoints</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        pre { background: #252526; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .test { margin: 20px 0; padding: 15px; background: #252526; border-radius: 5px; }
        .label { color: #9cdcfe; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🧪 Test Manager Endpoints</h1>
    <p>Verifica completa degli endpoint REST API per il Manager Prenotazioni</p>

    <?php
    $rest_root = rest_url('fp-resv/v1');
    $nonce = wp_create_nonce('wp_rest');
    
    echo '<div class="test">';
    echo '<h2>📋 Configurazione</h2>';
    echo '<p><span class="label">REST Root:</span> ' . esc_html($rest_root) . '</p>';
    echo '<p><span class="label">Nonce:</span> ' . esc_html(substr($nonce, 0, 20)) . '...</p>';
    echo '<p><span class="label">User ID:</span> ' . get_current_user_id() . '</p>';
    echo '<p><span class="label">Can manage:</span> ' . (current_user_can('manage_fp_reservations') ? '✅ Yes' : '❌ No') . '</p>';
    echo '</div>';

    // Test endpoints
    $endpoints = [
        'Overview' => '/agenda/overview',
        'Agenda' => '/agenda?date=2025-10-12&range=day',
        'Stats' => '/agenda/stats?date=2025-10-12&range=day',
        'Arrivals' => '/reservations/arrivals?range=today',
    ];

    foreach ($endpoints as $name => $path) {
        echo '<div class="test">';
        echo '<h2>🔍 Test: ' . esc_html($name) . '</h2>';
        echo '<p><span class="label">Endpoint:</span> ' . esc_html($path) . '</p>';
        
        $url = $rest_root . $path;
        
        // Fai la richiesta
        $response = wp_remote_get($url, [
            'headers' => [
                'X-WP-Nonce' => $nonce,
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            echo '<p class="error">❌ Errore: ' . esc_html($response->get_error_message()) . '</p>';
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);
            
            echo '<p><span class="label">Status:</span> ';
            if ($status === 200) {
                echo '<span class="success">✅ ' . $status . ' OK</span>';
            } else {
                echo '<span class="error">❌ ' . $status . '</span>';
            }
            echo '</p>';
            
            echo '<p><span class="label">Content-Type:</span> ' . esc_html($headers['content-type'] ?? 'N/A') . '</p>';
            echo '<p><span class="label">Body Length:</span> ' . strlen($body) . ' bytes</p>';
            
            if ($status === 200) {
                // Prova a parsare come JSON
                $json = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo '<p class="success">✅ JSON valido</p>';
                    echo '<details>';
                    echo '<summary><span class="label">Preview (clicca per espandere)</span></summary>';
                    echo '<pre>' . esc_html(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                    echo '</details>';
                } else {
                    echo '<p class="error">❌ JSON non valido: ' . json_last_error_msg() . '</p>';
                    echo '<p><span class="label">Body preview:</span></p>';
                    echo '<pre>' . esc_html(substr($body, 0, 500)) . '</pre>';
                }
            } else {
                echo '<p><span class="label">Error response:</span></p>';
                echo '<pre>' . esc_html(substr($body, 0, 1000)) . '</pre>';
            }
        }
        
        echo '</div>';
    }
    ?>

    <div class="test">
        <h2>🔗 Link Utili</h2>
        <p><a href="<?php echo admin_url('admin.php?page=fp-resv-manager'); ?>" style="color: #4ec9b0;">← Torna al Manager</a></p>
        <p><a href="<?php echo $rest_root; ?>" style="color: #4ec9b0;" target="_blank">Apri REST API Index</a></p>
    </div>

</body>
</html>
<?php
exit;
?>

