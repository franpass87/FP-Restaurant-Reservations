<?php
/**
 * Test debug endpoint available-days
 */

// Carica WordPress
require_once('../../../wp-load.php');

echo "=== TEST ENDPOINT DEBUG ===\n";

try {
    // Test 1: Verifica che le classi esistano
    echo "Test 1: Verifica classi...\n";
    
    if (!class_exists('FP\Resv\Domain\Settings\Options')) {
        echo "❌ Classe Options non trovata\n";
        exit;
    }
    echo "✅ Classe Options trovata\n";
    
    if (!class_exists('FP\Resv\Domain\Reservations\MealPlanService')) {
        echo "❌ Classe MealPlanService non trovata\n";
        exit;
    }
    echo "✅ Classe MealPlanService trovata\n";
    
    if (!class_exists('FP\Resv\Domain\Reservations\AvailabilityService')) {
        echo "❌ Classe AvailabilityService non trovata\n";
        exit;
    }
    echo "✅ Classe AvailabilityService trovata\n";
    
    // Test 2: Verifica istanziazione
    echo "\nTest 2: Verifica istanziazione...\n";
    
    $options = new \FP\Resv\Domain\Settings\Options();
    echo "✅ Options istanziato\n";
    
    $mealPlanService = new \FP\Resv\Domain\Reservations\MealPlanService($options);
    echo "✅ MealPlanService istanziato\n";
    
    $availabilityService = new \FP\Resv\Domain\Reservations\AvailabilityService($mealPlanService);
    echo "✅ AvailabilityService istanziato\n";
    
    // Test 3: Verifica metodo getMeals
    echo "\nTest 3: Verifica metodo getMeals...\n";
    
    $meals = $mealPlanService->getMeals();
    echo "✅ getMeals() eseguito: " . count($meals) . " meal trovati\n";
    
    // Test 4: Verifica metodo findAvailableDaysForAllMeals
    echo "\nTest 4: Verifica metodo findAvailableDaysForAllMeals...\n";
    
    $availableDays = $availabilityService->findAvailableDaysForAllMeals('2025-01-20', '2025-01-25');
    echo "✅ findAvailableDaysForAllMeals() eseguito: " . count($availableDays) . " giorni trovati\n";
    
    // Test 5: Verifica metodo findAvailableDaysForMeal
    echo "\nTest 5: Verifica metodo findAvailableDaysForMeal...\n";
    
    $pranzoDays = $availabilityService->findAvailableDaysForMeal('2025-01-20', '2025-01-25', 'pranzo');
    echo "✅ findAvailableDaysForMeal() eseguito: " . count($pranzoDays) . " giorni trovati per pranzo\n";
    
    // Test 6: Simula endpoint REST
    echo "\nTest 6: Simula endpoint REST...\n";
    
    $request = new WP_REST_Request('GET', '/fp-resv/v1/available-days');
    $request->set_param('from', '2025-01-20');
    $request->set_param('to', '2025-01-25');
    $request->set_param('meal', 'pranzo');
    
    $rest = new \FP\Resv\Domain\Reservations\REST(
        new \FP\Resv\Domain\Reservations\Availability($options, $GLOBALS['wpdb']),
        new \FP\Resv\Domain\Reservations\Service(),
        new \FP\Resv\Domain\Reservations\Repository()
    );
    
    $response = $rest->handleAvailableDays($request);
    
    if (is_wp_error($response)) {
        echo "❌ Errore endpoint: " . $response->get_error_message() . "\n";
    } else {
        echo "✅ Endpoint funziona: Status " . $response->get_status() . "\n";
        $data = $response->get_data();
        echo "   Date disponibili: " . count($data['days']) . "\n";
    }
    
    echo "\n=== TUTTI I TEST COMPLETATI ===\n";
    
} catch (Exception $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Linea: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>