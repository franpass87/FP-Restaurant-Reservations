<?php
/**
 * Setup Test Availability
 * Configura date e orari disponibili fittizi per i test
 * Access via: http://fp-development.local/wp-content/plugins/FP-Restaurant-Reservations/setup-test-availability.php
 */

// Load WordPress
$wp_load = dirname(dirname(__DIR__)) . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}

if (!file_exists($wp_load)) {
    die('WordPress not found. Looking for: ' . $wp_load);
}

require_once $wp_load;

// Simula un utente admin
$admin_users = get_users(['role' => 'administrator', 'number' => 1]);
if (!empty($admin_users)) {
    wp_set_current_user($admin_users[0]->ID);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Test Availability</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
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
        button {
            background: #2271b1;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background: #135e96;
        }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="container">
        <h1>⚙️ Setup Test Availability</h1>
        <p>Configura date e orari disponibili fittizi per i test delle prenotazioni.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_availability'])) {
            try {
                // Configurazione Meal Plan con orari disponibili
                $mealPlanConfig = [
                    [
                        'key' => 'dinner',
                        'label' => 'Cena',
                        'active' => true,
                        'availability' => [
                            'hours_definition' => "mon=19:00-23:00\ntue=19:00-23:00\nwed=19:00-23:00\nthu=19:00-23:00\nfri=19:00-23:00\nsat=19:00-23:00\nsun=19:00-23:00",
                            'slot_interval' => 15,
                            'turnover' => 120,
                            'buffer' => 0,
                            'max_parallel' => 10,
                            'capacity' => 50
                        ]
                    ],
                    [
                        'key' => 'lunch',
                        'label' => 'Pranzo',
                        'availability' => [
                            'hours_definition' => "mon=12:00-15:00\ntue=12:00-15:00\nwed=12:00-15:00\nthu=12:00-15:00\nfri=12:00-15:00\nsat=12:00-15:00\nsun=12:00-15:00",
                            'slot_interval' => 15,
                            'turnover' => 90,
                            'buffer' => 0,
                            'max_parallel' => 10,
                            'capacity' => 50
                        ]
                    ],
                    [
                        'key' => 'pranzo-domenicale',
                        'label' => 'Pranzo Domenicale',
                        'availability' => [
                            'hours_definition' => "sun=12:00-16:00",
                            'slot_interval' => 15,
                            'turnover' => 120,
                            'buffer' => 0,
                            'max_parallel' => 10,
                            'capacity' => 50
                        ]
                    ],
                    [
                        'key' => 'cena-weekend',
                        'label' => 'Cena Weekend',
                        'availability' => [
                            'hours_definition' => "fri=19:00-23:30\nsat=19:00-23:30\nsun=19:00-23:30",
                            'slot_interval' => 15,
                            'turnover' => 120,
                            'buffer' => 0,
                            'max_parallel' => 10,
                            'capacity' => 50
                        ]
                    ]
                ];
                
                // Salva il meal plan
                $mealPlanJson = json_encode($mealPlanConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                update_option('fp_resv_meal_plan', $mealPlanJson);
                
                // Configura anche le impostazioni generali se necessario
                $settings = get_option('fp_resv_settings', []);
                if (!is_array($settings)) {
                    $settings = [];
                }
                
                // Imposta giorni di anticipo minimo e massimo
                $settings['min_advance_days'] = 0;
                $settings['max_advance_days'] = 90;
                $settings['booking_enabled'] = true;
                
                update_option('fp_resv_settings', $settings);
                
                echo '<div class="result success">';
                echo '<h3>✅ Configurazione completata con successo!</h3>';
                echo '<p><strong>Meal Plan configurato:</strong></p>';
                echo '<pre>' . esc_html($mealPlanJson) . '</pre>';
                echo '<p><strong>Orari disponibili:</strong></p>';
                echo '<ul>';
                echo '<li><strong>Cena:</strong> Tutti i giorni 19:00-23:00</li>';
                echo '<li><strong>Pranzo:</strong> Tutti i giorni 12:00-15:00</li>';
                echo '<li><strong>Pranzo Domenicale:</strong> Domenica 12:00-16:00</li>';
                echo '<li><strong>Cena Weekend:</strong> Venerdì-Sabato-Domenica 19:00-23:30</li>';
                echo '</ul>';
                echo '<p><strong>Impostazioni:</strong></p>';
                echo '<ul>';
                echo '<li>Giorni di anticipo minimo: 0</li>';
                echo '<li>Giorni di anticipo massimo: 90</li>';
                echo '<li>Prenotazioni abilitate: Sì</li>';
                echo '</ul>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="result error">';
                echo '<h3>❌ Errore nella configurazione</h3>';
                echo '<p>' . esc_html($e->getMessage()) . '</p>';
                echo '<p><small>File: ' . esc_html($e->getFile()) . ':' . esc_html($e->getLine()) . '</small></p>';
                echo '</div>';
            }
        }
        
        // Mostra configurazione attuale
        $currentMealPlan = get_option('fp_resv_meal_plan', '');
        $currentSettings = get_option('fp_resv_settings', []);
        ?>
        
        <div class="info" style="padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <strong>ℹ️ Configurazione attuale:</strong>
            <?php if (!empty($currentMealPlan)): ?>
                <p>Meal Plan già configurato.</p>
            <?php else: ?>
                <p>Nessun Meal Plan configurato. Clicca il pulsante per configurare.</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="">
            <button type="submit" name="setup_availability">Configura Disponibilità Test</button>
        </form>
        
        <?php if (!empty($currentMealPlan)): ?>
            <div style="margin-top: 20px;">
                <h3>Meal Plan Attuale:</h3>
                <pre><?php echo esc_html($currentMealPlan); ?></pre>
            </div>
        <?php endif; ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>








