<?php
/**
 * Endpoint standalone completamente indipendente
 * Accessibile via: /available-days-standalone.php?from=2025-10-25&to=2025-11-25&meal=cena
 */

// Header per JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Parametri
    $from = $_GET['from'] ?? date('Y-m-d');
    $to = $_GET['to'] ?? date('Y-m-d', strtotime('+3 months'));
    $meal = $_GET['meal'] ?? null;

    // Genera date disponibili con logica semplificata
    $availableDays = generateAvailableDays($from, $to, $meal);

    // Risposta JSON
    echo json_encode([
        'success' => true,
        'days' => $availableDays,
        'from' => $from,
        'to' => $to,
        'meal' => $meal,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

/**
 * Genera date disponibili con logica semplificata
 */
function generateAvailableDays(string $from, string $to, ?string $meal): array
{
    $availableDays = [];
    $startDate = strtotime($from);
    $endDate = strtotime($to);
    
    // Schedule di default
    $defaultSchedule = [
        'pranzo' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'], // No domenica
        'cena' => ['tue', 'wed', 'thu', 'fri', 'sat', 'sun'],   // No lunedì
    ];
    
    // Genera date
    for ($timestamp = $startDate; $timestamp <= $endDate; $timestamp += 86400) {
        $dateKey = date('Y-m-d', $timestamp);
        $dayName = strtolower(date('D', $timestamp)); // mon, tue, wed, etc.
        
        if ($meal && isset($defaultSchedule[$meal])) {
            // Pasto specifico
            $isAvailable = in_array($dayName, $defaultSchedule[$meal], true);
            $availableDays[$dateKey] = [
                'available' => $isAvailable,
                'meal' => $meal,
            ];
        } else {
            // Tutti i pasti
            $pranzoAvailable = in_array($dayName, $defaultSchedule['pranzo'], true);
            $cenaAvailable = in_array($dayName, $defaultSchedule['cena'], true);
            
            $availableDays[$dateKey] = [
                'available' => $pranzoAvailable || $cenaAvailable,
                'meals' => [
                    'pranzo' => $pranzoAvailable,
                    'cena' => $cenaAvailable,
                ],
            ];
        }
    }
    
    return $availableDays;
}
?>
