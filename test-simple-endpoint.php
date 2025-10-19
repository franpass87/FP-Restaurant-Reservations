<?php
/**
 * Test semplice endpoint con dati mock
 */

// Simula una risposta semplice per testare
header('Content-Type: application/json');
http_response_code(200);

$mockData = [
    'days' => [
        '2025-01-20' => ['available' => true, 'meals' => ['pranzo' => true]],
        '2025-01-21' => ['available' => true, 'meals' => ['pranzo' => true]],
        '2025-01-22' => ['available' => false, 'meals' => ['pranzo' => false]],
        '2025-01-23' => ['available' => true, 'meals' => ['pranzo' => true]],
        '2025-01-24' => ['available' => true, 'meals' => ['pranzo' => true]],
    ],
    'from' => '2025-01-20',
    'to' => '2025-01-25',
    'meal' => 'pranzo'
];

echo json_encode($mockData);
exit;
?>
