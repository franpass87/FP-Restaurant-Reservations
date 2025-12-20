<?php
/**
 * Verifica e Configura Disponibilità Orari
 * Controlla la configurazione attuale e mostra come risolvere problemi
 */

// Load WordPress
$wp_load = dirname(dirname(__DIR__)) . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}

require_once $wp_load;

// Simula admin
$admin_users = get_users(['role' => 'administrator', 'number' => 1]);
if (!empty($admin_users)) {
    wp_set_current_user($admin_users[0]->ID);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Verifica Disponibilità Orari</title>
    <style>
        body { font-family: Arial; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; background: #d4edda; }
        .error { border-left: 4px solid #dc3545; background: #f8d7da; }
        .warning { border-left: 4px solid #ffc107; background: #fff3cd; }
        .info { border-left: 4px solid #17a2b8; background: #d1ecf1; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #2271b1; color: white; border: none; border-radius: 4px; }
        button:hover { background: #135e96; }
    </style>
</head>
<body>
    <h1>🔍 Verifica e Configura Disponibilità Orari</h1>
    
    <?php
    // Verifica configurazione attuale
    // Il plugin cerca in fp_resv_general -> frontend_meals
    $generalSettings = get_option('fp_resv_general', []);
    if (!is_array($generalSettings)) {
        $generalSettings = [];
    }
    $mealPlanDefinition = $generalSettings['frontend_meals'] ?? get_option('fp_resv_meal_plan_definition', '[]');
    $legacyGeneralSettings = get_option('fp_resv_general_settings', []);
    $mealPlans = json_decode($mealPlanDefinition, true) ?: [];
    
    echo '<div class="section">';
    echo '<h2>📋 Configurazione Attuale</h2>';
    
    if (empty($mealPlans)) {
        echo '<div class="error">';
        echo '<p><strong>❌ Nessun piano pasto configurato!</strong></p>';
        echo '</div>';
    } else {
        echo '<p><strong>Piani pasto trovati:</strong> ' . count($mealPlans) . '</p>';
        echo '<table>';
        echo '<tr><th>Key</th><th>Label</th><th>Attivo</th><th>Orari Configurati</th><th>Slot Interval</th><th>Capacità</th></tr>';
        
        foreach ($mealPlans as $plan) {
            $key = $plan['key'] ?? 'N/A';
            $label = $plan['label'] ?? 'N/A';
            $active = isset($plan['active']) && $plan['active'] ? '✅' : '❌';
            $availability = $plan['availability'] ?? [];
            $hoursDef = $availability['hours_definition'] ?? '';
            $slotInterval = $availability['slot_interval'] ?? 'N/A';
            $capacity = $availability['capacity'] ?? 'N/A';
            
            $hoursStatus = !empty($hoursDef) ? '✅ Configurato' : '❌ Non configurato';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($key) . '</strong></td>';
            echo '<td>' . esc_html($label) . '</td>';
            echo '<td>' . $active . '</td>';
            echo '<td>' . $hoursStatus . '</td>';
            echo '<td>' . esc_html($slotInterval) . ' min</td>';
            echo '<td>' . esc_html($capacity) . '</td>';
            echo '</tr>';
            
            if (!empty($hoursDef)) {
                echo '<tr><td colspan="6"><small><pre>' . esc_html($hoursDef) . '</pre></small></td></tr>';
            }
        }
        echo '</table>';
    }
    echo '</div>';
    
    // Verifica impostazioni generali
    echo '<div class="section">';
    echo '<h2>⚙️ Impostazioni Generali</h2>';
    echo '<table>';
    echo '<tr><th>Impostazione</th><th>Valore</th><th>Stato</th></tr>';
    
    $reservationsEnabled = $generalSettings['reservations_enabled'] ?? $legacyGeneralSettings['reservations_enabled'] ?? false;
    $minAdvance = $generalSettings['min_advance_days'] ?? $legacyGeneralSettings['min_advance_days'] ?? 'N/A';
    $maxAdvance = $generalSettings['max_advance_days'] ?? $legacyGeneralSettings['max_advance_days'] ?? 'N/A';
    
    echo '<tr>';
    echo '<td>Prenotazioni abilitate</td>';
    echo '<td>' . ($reservationsEnabled ? '✅ Sì' : '❌ No') . '</td>';
    echo '<td>' . ($reservationsEnabled ? '<span style="color:green">OK</span>' : '<span style="color:red">DA ABILITARE</span>') . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td>Giorni minimi anticipo</td>';
    echo '<td>' . esc_html($minAdvance) . '</td>';
    echo '<td>' . ($minAdvance === 0 || $minAdvance === '0' ? '<span style="color:green">OK</span>' : '<span style="color:orange">Potrebbe limitare</span>') . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td>Giorni massimi anticipo</td>';
    echo '<td>' . esc_html($maxAdvance) . '</td>';
    echo '<td>' . ($maxAdvance >= 30 ? '<span style="color:green">OK</span>' : '<span style="color:orange">Limitato</span>') . '</td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</div>';
    
    // Test disponibilità per una data specifica
    echo '<div class="section">';
    echo '<h2>🧪 Test Disponibilità</h2>';
    
    if (isset($_GET['test_date'])) {
        $testDate = sanitize_text_field($_GET['test_date']);
        $testMeal = sanitize_text_field($_GET['test_meal'] ?? 'dinner');
        
        echo '<div class="info">';
        echo '<p><strong>Test per:</strong> ' . esc_html($testDate) . ' - ' . esc_html($testMeal) . '</p>';
        
        // Cerca il piano pasto
        $testPlan = null;
        foreach ($mealPlans as $plan) {
            if (($plan['key'] ?? '') === $testMeal) {
                $testPlan = $plan;
                break;
            }
        }
        
        if ($testPlan) {
            $availability = $testPlan['availability'] ?? [];
            $hoursDef = $availability['hours_definition'] ?? '';
            
            if (!empty($hoursDef)) {
                // Parse hours definition
                $lines = explode("\n", $hoursDef);
                $dayOfWeek = strtolower(date('D', strtotime($testDate)));
                $dayMap = ['mon' => 'lun', 'tue' => 'mar', 'wed' => 'mer', 'thu' => 'gio', 'fri' => 'ven', 'sat' => 'sab', 'sun' => 'dom'];
                $dayKey = array_search($dayOfWeek, $dayMap);
                
                if ($dayKey) {
                    foreach ($lines as $line) {
                        if (strpos($line, $dayKey . '=') === 0) {
                            $timeRange = substr($line, strlen($dayKey) + 1);
                            echo '<p><strong>Orari disponibili:</strong> ' . esc_html($timeRange) . '</p>';
                            
                            // Calcola slot
                            $parts = explode('-', $timeRange);
                            if (count($parts) === 2) {
                                $start = trim($parts[0]);
                                $end = trim($parts[1]);
                                $interval = $availability['slot_interval'] ?? 15;
                                
                                echo '<p><strong>Slot interval:</strong> ' . $interval . ' minuti</p>';
                                echo '<p><strong>Slot disponibili:</strong></p>';
                                echo '<pre>';
                                
                                $startTime = strtotime($testDate . ' ' . $start);
                                $endTime = strtotime($testDate . ' ' . $end);
                                $current = $startTime;
                                $slots = [];
                                
                                while ($current < $endTime) {
                                    $slots[] = date('H:i', $current);
                                    $current += ($interval * 60);
                                }
                                
                                echo implode(', ', $slots);
                                echo '</pre>';
                                break;
                            }
                        }
                    }
                } else {
                    echo '<p style="color:red">❌ Giorno non trovato nella configurazione</p>';
                }
            } else {
                echo '<p style="color:red">❌ Nessuna definizione orari per questo piano pasto</p>';
            }
        } else {
            echo '<p style="color:red">❌ Piano pasto non trovato</p>';
        }
        
        echo '</div>';
    }
    
    echo '<form method="GET">';
    echo '<p><strong>Testa disponibilità per una data:</strong></p>';
    echo '<input type="date" name="test_date" value="' . date('Y-m-d', strtotime('+3 days')) . '" required> ';
    echo '<select name="test_meal">';
    foreach ($mealPlans as $plan) {
        $key = $plan['key'] ?? '';
        $label = $plan['label'] ?? $key;
        echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
    }
    echo '</select> ';
    echo '<button type="submit">Testa</button>';
    echo '</form>';
    echo '</div>';
    
    // Fix disponibilità se necessario
    if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
        echo '<div class="section">';
        echo '<h2>🔧 Applicazione Fix Disponibilità</h2>';
        
        // Configurazione completa con orari per tutti i giorni
        $fixedMealPlanDefinition = '[
            { 
                "key": "dinner", 
                "label": "Cena", 
                "active": true, 
                "availability": { 
                    "hours_definition": "mon=19:00-23:00\\ntue=19:00-23:00\\nwed=19:00-23:00\\nthu=19:00-23:00\\nfri=19:00-23:00\\nsat=19:00-23:00\\nsun=19:00-23:00", 
                    "slot_interval": 15, 
                    "turnover": 120, 
                    "buffer": 0, 
                    "max_parallel": 10, 
                    "capacity": 50 
                } 
            },
            { 
                "key": "lunch", 
                "label": "Pranzo", 
                "active": true,
                "availability": { 
                    "hours_definition": "mon=12:00-15:00\\ntue=12:00-15:00\\nwed=12:00-15:00\\nthu=12:00-15:00\\nfri=12:00-15:00\\nsat=12:00-15:00\\nsun=12:00-15:00", 
                    "slot_interval": 15, 
                    "turnover": 90, 
                    "buffer": 0, 
                    "max_parallel": 10, 
                    "capacity": 50 
                } 
            },
            { 
                "key": "pranzo-domenicale", 
                "label": "Pranzo Domenicale", 
                "active": true,
                "availability": { 
                    "hours_definition": "sun=12:00-16:00", 
                    "slot_interval": 15, 
                    "turnover": 120, 
                    "buffer": 0, 
                    "max_parallel": 10, 
                    "capacity": 50 
                } 
            },
            { 
                "key": "cena-weekend", 
                "label": "Cena Weekend", 
                "active": true,
                "availability": { 
                    "hours_definition": "fri=19:00-23:30\\nsat=19:00-23:30\\nsun=19:00-23:30", 
                    "slot_interval": 15, 
                    "turnover": 120, 
                    "buffer": 0, 
                    "max_parallel": 10, 
                    "capacity": 50 
                } 
            }
        ]';
        
        $fixedGeneralSettings = [
            'min_advance_days' => 0,
            'max_advance_days' => 90,
            'reservations_enabled' => true,
        ];
        
        // Salva il meal plan nella struttura corretta usata dal plugin
        // Il plugin cerca in fp_resv_general -> frontend_meals
        $generalSettings = get_option('fp_resv_general', []);
        if (!is_array($generalSettings)) {
            $generalSettings = [];
        }
        $generalSettings['frontend_meals'] = $fixedMealPlanDefinition;
        $generalSettings['min_advance_days'] = 0;
        $generalSettings['max_advance_days'] = 90;
        $generalSettings['reservations_enabled'] = true;
        
        update_option('fp_resv_general', $generalSettings);
        
        // Mantieni anche l'opzione legacy per compatibilità
        update_option('fp_resv_meal_plan_definition', $fixedMealPlanDefinition);
        update_option('fp_resv_general_settings', $fixedGeneralSettings);
        
        echo '<div class="success">';
        echo '<h3>✅ Configurazione applicata con successo!</h3>';
        echo '<p>Gli orari sono stati configurati per tutti i piani pasto.</p>';
        echo '<p><a href="?">Ricarica la pagina per vedere le modifiche</a></p>';
        echo '</div>';
        echo '</div>';
    }
    
    // Mostra problemi e soluzioni
    echo '<div class="section">';
    echo '<h2>🔍 Diagnostica Problemi</h2>';
    
    $problems = [];
    $solutions = [];
    
    if (empty($mealPlans)) {
        $problems[] = 'Nessun piano pasto configurato';
        $solutions[] = 'Eseguire il fix automatico o configurare manualmente dalla pagina admin del plugin';
    }
    
    foreach ($mealPlans as $plan) {
        $key = $plan['key'] ?? '';
        $availability = $plan['availability'] ?? [];
        $hoursDef = $availability['hours_definition'] ?? '';
        
        if (empty($hoursDef)) {
            $problems[] = "Piano pasto '{$key}' non ha orari configurati";
            $solutions[] = "Aggiungere hours_definition per '{$key}'";
        }
        
        if (!isset($plan['active']) || !$plan['active']) {
            $problems[] = "Piano pasto '{$key}' non è attivo";
            $solutions[] = "Attivare il piano pasto '{$key}'";
        }
    }
    
    if (!$reservationsEnabled) {
        $problems[] = 'Le prenotazioni sono disabilitate';
        $solutions[] = 'Abilitare le prenotazioni nelle impostazioni generali';
    }
    
    if (!empty($problems)) {
        echo '<div class="warning">';
        echo '<h3>⚠️ Problemi Rilevati:</h3>';
        echo '<ul>';
        foreach ($problems as $problem) {
            echo '<li>' . esc_html($problem) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        
        echo '<div class="info">';
        echo '<h3>💡 Soluzioni:</h3>';
        echo '<ul>';
        foreach ($solutions as $solution) {
            echo '<li>' . esc_html($solution) . '</li>';
        }
        echo '</ul>';
        echo '<p><a href="?fix=yes"><button>🔧 Applica Fix Automatico</button></a></p>';
        echo '</div>';
    } else {
        echo '<div class="success">';
        echo '<h3>✅ Nessun problema rilevato!</h3>';
        echo '<p>La configurazione sembra corretta. Se gli orari non appaiono in frontend, potrebbe essere un problema di API REST o cache.</p>';
        echo '</div>';
    }
    
    echo '</div>';
    ?>
</body>
</html>

