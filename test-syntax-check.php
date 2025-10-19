<?php
/**
 * Test sintassi delle classi
 */

echo "=== TEST SINTASSI CLASSI ===\n";

// Test 1: Verifica sintassi MealPlanService
echo "Test 1: Verifica sintassi MealPlanService...\n";
$output = shell_exec('php -l src/Domain/Reservations/MealPlanService.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ MealPlanService: Sintassi OK\n";
} else {
    echo "❌ MealPlanService: " . $output . "\n";
}

// Test 2: Verifica sintassi AvailabilityService
echo "Test 2: Verifica sintassi AvailabilityService...\n";
$output = shell_exec('php -l src/Domain/Reservations/AvailabilityService.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ AvailabilityService: Sintassi OK\n";
} else {
    echo "❌ AvailabilityService: " . $output . "\n";
}

// Test 3: Verifica sintassi MealPlan
echo "Test 3: Verifica sintassi MealPlan...\n";
$output = shell_exec('php -l src/Domain/Settings/MealPlan.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ MealPlan: Sintassi OK\n";
} else {
    echo "❌ MealPlan: " . $output . "\n";
}

// Test 4: Verifica sintassi REST
echo "Test 4: Verifica sintassi REST...\n";
$output = shell_exec('php -l src/Domain/Reservations/REST.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ REST: Sintassi OK\n";
} else {
    echo "❌ REST: " . $output . "\n";
}

// Test 5: Verifica sintassi Availability
echo "Test 5: Verifica sintassi Availability...\n";
$output = shell_exec('php -l src/Domain/Reservations/Availability.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ Availability: Sintassi OK\n";
} else {
    echo "❌ Availability: " . $output . "\n";
}

echo "\n=== TEST COMPLETATI ===\n";
?>
