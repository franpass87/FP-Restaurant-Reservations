<?php
/**
 * Debug Meal Notice - Verifica configurazione messaggi pasti
 */

// Trova wp-load.php risalendo dalla cartella del plugin
$wp_load_path = __DIR__ . '/../../../wp-load.php';

// Se non esiste, prova un path alternativo (per junction su Windows)
if (!file_exists($wp_load_path)) {
    // Prova dalla root assoluta del workspace
    $possible_paths = [
        dirname(dirname(dirname(__DIR__))) . '/wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
        'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php',
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $wp_load_path = $path;
            break;
        }
    }
}

if (!file_exists($wp_load_path)) {
    die('<h1>Errore</h1><p>Impossibile trovare wp-load.php</p><p>Path provato: ' . htmlspecialchars($wp_load_path) . '</p><p>__DIR__: ' . htmlspecialchars(__DIR__) . '</p>');
}

require_once $wp_load_path;

if (!function_exists('get_option')) {
    die('WordPress non caricato correttamente');
}

echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n<title>Debug Meal Notice</title>\n";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}pre{background:#fff;padding:15px;border-radius:5px;border:1px solid #ddd;overflow:auto;}h2{color:#333;border-bottom:2px solid #333;padding-bottom:10px;}code{background:#f0f0f0;padding:2px 6px;border-radius:3px;}</style>\n";
echo "</head>\n<body>\n";

echo "<h1>🔍 Debug Meal Notice Configuration</h1>\n";

// Carica le opzioni del plugin (nome corretto)
$options = get_option('fp_resv_general', []);

echo "<h2>1. Raw Settings (fp_resv_general)</h2>\n";
echo "<pre>" . htmlspecialchars(print_r($options, true)) . "</pre>\n";

// Cerca il frontend_meals (non meal_plan)
if (isset($options['frontend_meals'])) {
    echo "<h2>2. Frontend Meals (Raw)</h2>\n";
    echo "<pre>" . htmlspecialchars(print_r($options['frontend_meals'], true)) . "</pre>\n";
    
    // Parse come JSON se è una stringa
    if (is_string($options['frontend_meals'])) {
        $mealPlan = json_decode($options['frontend_meals'], true);
        
        echo "<h2>3. Frontend Meals (Parsed JSON)</h2>\n";
        echo "<pre>" . htmlspecialchars(print_r($mealPlan, true)) . "</pre>\n";
        
        if (is_array($mealPlan)) {
            echo "<h2>4. Meals con Notice</h2>\n";
            echo "<table style='width:100%;border-collapse:collapse;background:#fff;'>\n";
            echo "<thead><tr style='background:#333;color:#fff;'><th style='padding:10px;text-align:left;'>Key</th><th style='padding:10px;text-align:left;'>Label</th><th style='padding:10px;text-align:left;'>Notice</th><th style='padding:10px;text-align:left;'>Hint</th></tr></thead>\n";
            echo "<tbody>\n";
            
            foreach ($mealPlan as $meal) {
                if (is_array($meal)) {
                    $key = $meal['key'] ?? '-';
                    $label = $meal['label'] ?? '-';
                    $notice = $meal['notice'] ?? '';
                    $hint = $meal['hint'] ?? '';
                    
                    $hasNotice = !empty($notice);
                    $rowStyle = $hasNotice ? "background:#d4edda;" : "background:#fff;";
                    
                    echo "<tr style='$rowStyle'>\n";
                    echo "<td style='padding:10px;border:1px solid #ddd;'><code>" . htmlspecialchars($key) . "</code></td>\n";
                    echo "<td style='padding:10px;border:1px solid #ddd;'>" . htmlspecialchars($label) . "</td>\n";
                    echo "<td style='padding:10px;border:1px solid #ddd;'>" . ($hasNotice ? "<strong>✅ " . htmlspecialchars($notice) . "</strong>" : "❌ Nessun messaggio") . "</td>\n";
                    echo "<td style='padding:10px;border:1px solid #ddd;'>" . ($hint ? htmlspecialchars($hint) : "-") . "</td>\n";
                    echo "</tr>\n";
                }
            }
            
            echo "</tbody>\n</table>\n";
        }
    }
} else {
    echo "<p style='color:red;'>⚠️ <strong>frontend_meals non trovato nelle opzioni!</strong></p>\n";
    echo "<p style='color:orange;'>📋 Vai in <strong>WordPress Admin → Impostazioni → FP Restaurant Reservations → Generali → Turni & disponibilità</strong> e configura i pasti.</p>\n";
}

// Verifica come viene passato al form
echo "<h2>5. Come viene parsato da MealPlan::parse()</h2>\n";

if (class_exists('FP\\Resv\\Domain\\Settings\\MealPlan')) {
    $rawValue = $options['frontend_meals'] ?? '';
    $parsed = \FP\Resv\Domain\Settings\MealPlan::parse($rawValue);
    
    echo "<pre>" . htmlspecialchars(print_r($parsed, true)) . "</pre>\n";
    
    echo "<h2>6. HTML Attributes che verrebbero generati</h2>\n";
    echo "<pre>\n";
    foreach ($parsed as $meal) {
        if (is_array($meal)) {
            $key = $meal['key'] ?? '-';
            $notice = $meal['notice'] ?? '';
            $hint = $meal['hint'] ?? '';
            
            echo "data-meal=\"" . htmlspecialchars($key) . "\"\n";
            echo "data-meal-notice=\"" . htmlspecialchars($notice) . "\"\n";
            echo "data-meal-hint=\"" . htmlspecialchars($hint) . "\"\n";
            echo "\n";
        }
    }
    echo "</pre>\n";
} else {
    echo "<p style='color:red;'>⚠️ Classe MealPlan non trovata!</p>\n";
}

echo "<h2>7. Istruzioni</h2>\n";
echo "<ol>\n";
echo "<li>Verifica nella <strong>Tabella al punto 4</strong> se i tuoi pasti hanno il campo <code>notice</code> compilato (riga verde con ✅)</li>\n";
echo "<li>Se non vedi il messaggio, vai in <strong>Impostazioni → FP Restaurant Reservations → Meal Plan</strong> e inserisci il messaggio</li>\n";
echo "<li>Apri la <strong>Console del browser</strong> nella pagina del form e cerca i log <code>=== DEBUG PASTI ===</code></li>\n";
echo "<li>Controlla che <code>hasNotice: true</code> per il pasto che vuoi testare</li>\n";
echo "</ol>\n";

echo "</body>\n</html>";

