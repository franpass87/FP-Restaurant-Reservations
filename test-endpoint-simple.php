<?php
/**
 * Endpoint di test semplice per available-days
 */

// Imposta header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Dati mock per test
$availableDays = [
    '2025-10-19' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
    '2025-10-20' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
    '2025-10-21' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
    '2025-10-22' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
    '2025-10-23' => ['available' => true, 'meals' => ['pranzo' => true, 'cena' => true]],
];

$from = $_GET['from'] ?? '2025-10-19';
$to = $_GET['to'] ?? '2026-01-19';
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

$response = [
    'days' => $availableDays,
    'from' => $from,
    'to' => $to,
    'meal' => $meal ?? null,
    'debug' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
