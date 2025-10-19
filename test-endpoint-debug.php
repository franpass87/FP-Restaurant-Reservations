<?php
/**
 * Test diretto dell'endpoint available-days
 */

// Simula una richiesta REST
$_GET['from'] = '2025-10-19';
$_GET['to'] = '2026-01-19';
$_GET['meal'] = 'pranzo';

// Test diretto del metodo
try {
    // Simula la classe REST
    class MockREST {
        public function handleAvailableDays() {
            // Dati mock
            $availableDays = [
                '2025-10-19' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
                '2025-10-20' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
                '2025-10-21' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
            ];
            
            $meal = $_GET['meal'] ?? null;
            
            // Se è specificato un meal, filtra solo per quel meal
            if (is_string($meal) && $meal !== '') {
                $filteredDays = [];
                foreach ($availableDays as $date => $info) {
                    $mealAvailable = isset($info['meals'][$meal]) && $info['meals'][$meal];
                    $filteredDays[$date] = [
                        'available' => $mealAvailable,
                        'meal' => $meal,
                    ];
                }
                $availableDays = $filteredDays;
            }
            
            return [
                'days' => $availableDays,
                'from' => $_GET['from'],
                'to' => $_GET['to'],
                'meal' => $meal ?? null,
            ];
        }
    }
    
    $mock = new MockREST();
    $result = $mock->handleAvailableDays();
    
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
