#!/usr/bin/env php
<?php
/**
 * Test veloce per debuggare la disponibilità
 * 
 * Usage: php tools/test-availability-debug.php
 */

// Bootstrap WordPress
$wpLoad = null;
$currentDir = __DIR__;

for ($i = 0; $i < 10; $i++) {
    $wpLoadPath = $currentDir . '/wp-load.php';
    if (file_exists($wpLoadPath)) {
        $wpLoad = $wpLoadPath;
        break;
    }
    $currentDir = dirname($currentDir);
}

if ($wpLoad === null) {
    echo "❌ Impossibile trovare wp-load.php\n";
    exit(1);
}

require_once $wpLoad;

echo "\n🔍 TEST RAPIDO DISPONIBILITÀ\n";
echo "══════════════════════════════════════════\n\n";

// Test con parametri di default
global $wpdb;
$options = new \FP\Resv\Domain\Settings\Options($wpdb);
$availability = new \FP\Resv\Domain\Reservations\Availability($options, $wpdb);

// Prendi i prossimi 3 giorni
$dates = [
    date('Y-m-d'),
    date('Y-m-d', strtotime('+1 day')),
    date('Y-m-d', strtotime('+2 day')),
];

echo "📅 Testing date: " . implode(', ', $dates) . "\n\n";

// Prendi i meal configurati
$mealsDefinition = $options->getField('fp_resv_general', 'frontend_meals', '');
if ($mealsDefinition === '') {
    echo "⚠️  Nessun meal configurato - testing con meal vuoto\n";
    $meals = [''];
} else {
    $mealPlan = \FP\Resv\Domain\Settings\MealPlan::parse($mealsDefinition);
    $indexedMeals = \FP\Resv\Domain\Settings\MealPlan::indexByKey($mealPlan);
    $meals = array_keys($indexedMeals);
    echo "✅ Meal configurati: " . implode(', ', $meals) . "\n";
}

echo "\n";

// Test per ogni combinazione
$parties = [2, 4];

foreach ($dates as $date) {
    echo "═══════════════════════════════════════════\n";
    echo "📅 Data: {$date} (" . date('D', strtotime($date)) . ")\n";
    echo "═══════════════════════════════════════════\n\n";
    
    foreach ($meals as $meal) {
        foreach ($parties as $party) {
            $mealLabel = $meal !== '' ? $meal : '(default)';
            echo "  🍽️  Meal: {$mealLabel} | 👥 Party: {$party}\n";
            
            try {
                $criteria = [
                    'date' => $date,
                    'party' => $party,
                ];
                
                if ($meal !== '') {
                    $criteria['meal'] = $meal;
                }
                
                $result = $availability->findSlots($criteria);
                
                $hasAvail = $result['meta']['has_availability'] ?? false;
                $slotCount = count($result['slots'] ?? []);
                
                // Conta slot per status
                $statuses = [];
                foreach ($result['slots'] ?? [] as $slot) {
                    $status = $slot['status'] ?? 'unknown';
                    if (!isset($statuses[$status])) {
                        $statuses[$status] = 0;
                    }
                    $statuses[$status]++;
                }
                
                $icon = $hasAvail ? '✅' : '❌';
                echo "     {$icon} Slots: {$slotCount} | Has availability: " . ($hasAvail ? 'YES' : 'NO') . "\n";
                
                if (!empty($statuses)) {
                    echo "     Status: ";
                    $parts = [];
                    foreach ($statuses as $status => $count) {
                        $parts[] = "{$status}={$count}";
                    }
                    echo implode(', ', $parts) . "\n";
                }
                
                if (isset($result['meta']['reason'])) {
                    echo "     ⚠️  Reason: {$result['meta']['reason']}\n";
                }
                
                // Se tutti i slot sono 'full', mostra il primo per debug
                if (!$hasAvail && $slotCount > 0 && isset($result['slots'][0])) {
                    $firstSlot = $result['slots'][0];
                    echo "     📊 First slot: {$firstSlot['label']} | Status: {$firstSlot['status']} | Capacity: {$firstSlot['available_capacity']}\n";
                    if (!empty($firstSlot['reasons'])) {
                        echo "     📝 Reasons: " . implode('; ', $firstSlot['reasons']) . "\n";
                    }
                }
                
            } catch (Exception $e) {
                echo "     ❌ ERROR: {$e->getMessage()}\n";
            }
            
            echo "\n";
        }
    }
}

echo "══════════════════════════════════════════\n";
echo "✅ Test completato\n\n";
