<?php
/**
 * Test endpoint semplice per verificare che funzioni
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

try {
    // Parametri
    $from = $_GET['from'] ?? date('Y-m-d');
    $to = $_GET['to'] ?? date('Y-m-d', strtotime('+1 year'));
    $meal = $_GET['meal'] ?? null;

    // Genera date disponibili
    $availableDays = generateAvailableDays($from, $to, $meal);

    // Risposta JSON
    echo json_encode([
        'success' => true,
        'days' => $availableDays,
        'from' => $from,
        'to' => $to,
        'meal' => $meal,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function generateAvailableDays(string $from, string $to, ?string $meal): array
{
    $startDate = new DateTimeImmutable($from . ' 00:00:00');
    $endDate = new DateTimeImmutable($to . ' 23:59:59');
    
    // Schedule di default
    $defaultSchedule = [
        'pranzo' => [
            'mon' => true,
            'tue' => true,
            'wed' => true,
            'thu' => true,
            'fri' => true,
            'sat' => true,
            'sun' => false, // Pranzo non disponibile la domenica
        ],
        'cena' => [
            'mon' => false, // Cena non disponibile il lunedì
            'tue' => true,
            'wed' => true,
            'thu' => true,
            'fri' => true,
            'sat' => true,
            'sun' => true,
        ]
    ];

    $availableDays = [];
    $current = $startDate;

    while ($current <= $endDate) {
        $dateKey = $current->format('Y-m-d');
        $dayKey = strtolower($current->format('D')); // mon, tue, wed, etc.
        
        if ($meal && isset($defaultSchedule[$meal])) {
            // Controlla disponibilità per pasto specifico
            $isAvailable = $defaultSchedule[$meal][$dayKey] ?? false;
            $availableDays[$dateKey] = [
                'available' => $isAvailable,
                'meal' => $meal,
            ];
        } else {
            // Controlla disponibilità per tutti i pasti
            $hasAnyAvailability = false;
            $mealAvailability = [];
            
            foreach ($defaultSchedule as $mealKey => $schedule) {
                $isMealAvailable = $schedule[$dayKey] ?? false;
                $mealAvailability[$mealKey] = $isMealAvailable;
                
                if ($isMealAvailable) {
                    $hasAnyAvailability = true;
                }
            }
            
            $availableDays[$dateKey] = [
                'available' => $hasAnyAvailability,
                'meals' => $mealAvailability,
            ];
        }

        $current = $current->add(new DateInterval('P1D'));
    }

    return $availableDays;
}
?>
