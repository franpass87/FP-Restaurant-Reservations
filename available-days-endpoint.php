<?php
/**
 * Endpoint standalone per testare le date disponibili
 * Accessibile via: /available-days-endpoint.php?from=2025-01-20&to=2025-01-25&meal=pranzo
 */

// Carica WordPress per accedere alle funzioni
require_once('wp-config.php');

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

    // Genera date disponibili usando i dati reali dal backend
    $availableDays = getAvailableDaysFromBackend($from, $to, $meal);

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
 * Genera date disponibili usando i dati reali dal backend
 */
function getAvailableDaysFromBackend(string $from, string $to, ?string $meal): array
{
    // Legge le impostazioni dei meal dal backend
    $mealsDefinition = get_option('fp_resv_general', []);
    $mealsDefinition = $mealsDefinition['frontend_meals'] ?? '';
    
    // Parse dei meal configurati
    $meals = parseMealPlan($mealsDefinition);
    $mealsByKey = indexMealsByKey($meals);
    
    // Se non ci sono meal configurati, usa default
    if (empty($mealsByKey)) {
        return getDefaultAvailableDays($from, $to, $meal);
    }
    
    $availableDays = [];
    $startDate = strtotime($from);
    $endDate = strtotime($to);
    
    // Genera date
    for ($timestamp = $startDate; $timestamp <= $endDate; $timestamp += 86400) {
        $dateKey = date('Y-m-d', $timestamp);
        $dayName = strtolower(date('D', $timestamp)); // mon, tue, wed, etc.
        
        if ($meal && isset($mealsByKey[$meal])) {
            // Pasto specifico
            $isAvailable = isMealAvailableOnDay($mealsByKey[$meal], $dayName);
            $availableDays[$dateKey] = [
                'available' => $isAvailable,
                'meal' => $meal,
            ];
        } else {
            // Tutti i pasti
            $mealAvailability = [];
            $hasAnyAvailability = false;
            
            foreach ($mealsByKey as $mealKey => $mealData) {
                $isMealAvailable = isMealAvailableOnDay($mealData, $dayName);
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
    }
    
    return $availableDays;
}

/**
 * Parse dei meal plan (versione semplificata)
 */
function parseMealPlan(string $definition): array
{
    if (trim($definition) === '') {
        return [];
    }
    
    $decoded = json_decode($definition, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    
    return [];
}

/**
 * Indexa i meal per chiave
 */
function indexMealsByKey(array $meals): array
{
    $indexed = [];
    foreach ($meals as $meal) {
        if (isset($meal['key']) && is_string($meal['key'])) {
            $indexed[$meal['key']] = $meal;
        }
    }
    return $indexed;
}

/**
 * Controlla se un meal è disponibile in un giorno specifico
 */
function isMealAvailableOnDay(array $mealData, string $dayName): bool
{
    // Se c'è una definizione orari specifica, la usa
    if (!empty($mealData['hours_definition'])) {
        $schedule = parseScheduleDefinition($mealData['hours_definition']);
        return !empty($schedule[$dayName]);
    }
    
    // Altrimenti usa la disponibilità per giorni della settimana
    if (isset($mealData['days_of_week']) && is_array($mealData['days_of_week'])) {
        return in_array($dayName, $mealData['days_of_week'], true);
    }
    
    // Default: disponibile tutti i giorni
    return true;
}

/**
 * Parse schedule definition (versione semplificata)
 */
function parseScheduleDefinition(string $definition): array
{
    $schedule = [];
    $lines = preg_split('/\n/', $definition) ?: [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '=')) {
            continue;
        }
        
        [$day, $ranges] = array_map('trim', explode('=', $line, 2));
        $day = strtolower($day);
        
        $segments = preg_split('/[|,]/', $ranges) ?: [];
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                continue;
            }
            
            $start = ((int) $matches[1] * 60) + (int) $matches[2];
            $end = ((int) $matches[3] * 60) + (int) $matches[4];
            if ($end <= $start) {
                continue;
            }
            
            $schedule[$day][] = [
                'start' => $start,
                'end' => $end,
            ];
        }
    }
    
    return $schedule;
}

/**
 * Fallback con schedule di default se non ci sono meal configurati
 */
function getDefaultAvailableDays(string $from, string $to, ?string $meal): array
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
