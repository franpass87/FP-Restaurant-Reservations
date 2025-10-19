<?php
/**
 * Endpoint per le date disponibili - Versione robusta
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

$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d', strtotime('+1 year'));
$meal = $_GET['meal'] ?? null;

// Prova a usare il backend WordPress, se fallisce usa dati mock
$availableDays = [];

try {
    // Prova diversi percorsi per wp-load.php
    $wpLoadPaths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php',
        dirname(__FILE__) . '/../../../wp-load.php',
        dirname(__FILE__) . '/../../../../wp-load.php',
    ];
    
    $wpLoaded = false;
    foreach ($wpLoadPaths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wpLoaded = true;
            break;
        }
    }
    
    if ($wpLoaded && class_exists('\FP\Resv\Domain\Reservations\Availability')) {
        // Usa il backend WordPress
        $availability = new \FP\Resv\Domain\Reservations\Availability(
            new \FP\Resv\Domain\Settings\Options(),
            $GLOBALS['wpdb']
        );
        
        $availableDays = $availability->findAvailableDaysForAllMeals($from, $to);
    } else {
        throw new Exception('Backend WordPress non disponibile');
    }
    
} catch (Exception $e) {
    // Fallback: usa dati mock basati sullo schedule del backend
    $startDate = new DateTime($from);
    $endDate = new DateTime($to);
    $current = clone $startDate;
    
    while ($current <= $endDate) {
        $dateStr = $current->format('Y-m-d');
        $dayOfWeek = $current->format('N'); // 1=Lunedì, 7=Domenica
        
        $meals = [];
        
        // Schedule del backend (DEFAULT_SCHEDULE):
        // mon-fri: solo cena (19:00-23:00/23:30)
        // sat: pranzo (12:30-15:00) + cena (19:00-23:30)
        // sun: solo pranzo (12:30-15:00)
        
        if ($dayOfWeek == 6) { // Sabato
            $meals['pranzo'] = true;
            $meals['cena'] = true;
        } elseif ($dayOfWeek == 7) { // Domenica
            $meals['pranzo'] = true;
        } else { // Lunedì-Venerdì
            $meals['cena'] = true;
        }
        
        if (!empty($meals)) {
            $availableDays[$dateStr] = [
                'available' => true,
                'meals' => $meals
            ];
        }
        
        $current->add(new DateInterval('P1D'));
    }
}

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
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
