#!/usr/bin/env php
<?php
/**
 * Debug Availability - Verifica dettagliata della disponibilità
 * 
 * Questo script verifica la disponibilità per una specifica data/meal
 * e mostra tutti i dettagli per capire perché mostra "Completamente prenotato"
 * 
 * Usage:
 *   php tools/debug-availability.php [date] [party] [meal]
 *   
 * Examples:
 *   php tools/debug-availability.php 2025-10-10 2 cena
 *   php tools/debug-availability.php today 4 pranzo
 */

// Bootstrap WordPress if needed
if (!defined('ABSPATH')) {
    // Try to find wp-load.php
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
        echo "❌ Errore: impossibile trovare wp-load.php\n";
        echo "Esegui questo script dalla root di WordPress o usa WP-CLI:\n";
        echo "  wp eval-file tools/debug-availability.php\n";
        exit(1);
    }
    
    require_once $wpLoad;
}

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  🔍 DEBUG DISPONIBILITÀ - Diagnostica Dettagliata\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

// Parse arguments
$date = $argv[1] ?? 'today';
$party = (int) ($argv[2] ?? 2);
$mealKey = $argv[3] ?? '';

// Normalize date
if ($date === 'today') {
    $date = date('Y-m-d');
} elseif ($date === 'tomorrow') {
    $date = date('Y-m-d', strtotime('+1 day'));
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo "❌ Formato data non valido. Usa: YYYY-MM-DD, 'today', o 'tomorrow'\n";
    exit(1);
}

echo "📅 Parametri Richiesta:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Data:     {$date} (" . date('D', strtotime($date)) . ")\n";
echo "Persone:  {$party}\n";
echo "Meal:     " . ($mealKey !== '' ? $mealKey : '(default)') . "\n";
echo "\n";

// Check if plugin is active
if (!class_exists('FP\Resv\Domain\Reservations\Availability')) {
    echo "❌ Plugin non caricato o classe Availability non trovata!\n";
    exit(1);
}

// Get instances
global $wpdb;
$options = new \FP\Resv\Domain\Settings\Options($wpdb);
$availability = new \FP\Resv\Domain\Reservations\Availability($options, $wpdb);

// Check 1: General Settings
echo "📋 Check 1: Configurazione Generale\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$generalSchedule = $options->getField('fp_resv_general', 'service_hours_definition', '');
$slotInterval = $options->getField('fp_resv_general', 'slot_interval_minutes', '15');
$turnover = $options->getField('fp_resv_general', 'table_turnover_minutes', '120');
$buffer = $options->getField('fp_resv_general', 'buffer_before_minutes', '15');
$maxParallel = $options->getField('fp_resv_general', 'max_parallel_parties', '8');

echo "Orari servizio:\n";
if ($generalSchedule === '') {
    echo "  ⚠️  NON CONFIGURATI (verrà usato default)\n";
} else {
    $lines = explode("\n", $generalSchedule);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            echo "  {$line}\n";
        }
    }
}
echo "\n";
echo "Slot interval:    {$slotInterval} min\n";
echo "Turnover:         {$turnover} min\n";
echo "Buffer:           {$buffer} min\n";
echo "Max parallele:    {$maxParallel}\n";
echo "\n";

// Check 2: Meal Plan
echo "📋 Check 2: Meal Plan\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mealsDefinition = $options->getField('fp_resv_general', 'frontend_meals', '');
if ($mealsDefinition === '') {
    echo "⚠️  Nessun meal configurato!\n";
    echo "   Il sistema userà gli orari di servizio generali.\n";
} else {
    echo "Meal configurati:\n";
    $mealPlan = \FP\Resv\Domain\Settings\MealPlan::parse($mealsDefinition);
    $indexedMeals = \FP\Resv\Domain\Settings\MealPlan::indexByKey($mealPlan);
    
    foreach ($indexedMeals as $key => $meal) {
        echo "\n  {$key}:\n";
        echo "    Label:         {$meal['label']}\n";
        
        if (!empty($meal['hours_definition'])) {
            echo "    Orari custom:  SÌ\n";
            $mealLines = explode("\n", $meal['hours_definition']);
            foreach ($mealLines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    echo "      {$line}\n";
                }
            }
        } else {
            echo "    Orari custom:  NO (usa orari generali)\n";
        }
        
        if (!empty($meal['capacity'])) {
            echo "    Capacità:      {$meal['capacity']}\n";
        }
    }
    
    echo "\n";
    if ($mealKey !== '' && !isset($indexedMeals[$mealKey])) {
        echo "⚠️  ATTENZIONE: Il meal '{$mealKey}' richiesto NON ESISTE!\n";
        echo "   Meal disponibili: " . implode(', ', array_keys($indexedMeals)) . "\n";
    }
}
echo "\n";

// Check 3: Reservations for the date
echo "📋 Check 3: Prenotazioni Esistenti\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$sql = $wpdb->prepare(
    "SELECT id, time, party, status, table_id, room_id FROM {$wpdb->prefix}fp_reservations WHERE date = %s ORDER BY time",
    $date
);
$reservations = $wpdb->get_results($sql, ARRAY_A);

if (empty($reservations)) {
    echo "✅ Nessuna prenotazione per questa data\n";
    echo "   Il sistema dovrebbe mostrare DISPONIBILE!\n";
} else {
    echo "Prenotazioni trovate: " . count($reservations) . "\n\n";
    foreach ($reservations as $resv) {
        $statusIcon = in_array($resv['status'], ['pending', 'confirmed', 'seated']) ? '🔴' : '⚪';
        echo "  {$statusIcon} #{$resv['id']} - {$resv['time']} - {$resv['party']} persone";
        echo " - {$resv['status']}";
        if ($resv['table_id']) {
            echo " - Tavolo #{$resv['table_id']}";
        }
        echo "\n";
    }
    
    $activeCount = count(array_filter($reservations, function($r) {
        return in_array($r['status'], ['pending', 'confirmed', 'seated']);
    }));
    
    echo "\n";
    echo "Prenotazioni attive: {$activeCount}\n";
    
    if ($activeCount >= (int) $maxParallel) {
        echo "⚠️  Limite max parallele raggiunto! ({$maxParallel})\n";
    }
}
echo "\n";

// Check 4: Rooms & Tables
echo "📋 Check 4: Sale e Tavoli\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$rooms = $wpdb->get_results(
    "SELECT id, name, capacity, active FROM {$wpdb->prefix}fp_rooms",
    ARRAY_A
);
$tables = $wpdb->get_results(
    "SELECT id, room_id, seats_min, seats_std, seats_max, active FROM {$wpdb->prefix}fp_tables WHERE active = 1",
    ARRAY_A
);

echo "Sale configurate: " . count($rooms) . "\n";
foreach ($rooms as $room) {
    $activeIcon = $room['active'] ? '✅' : '❌';
    echo "  {$activeIcon} Sala #{$room['id']}: {$room['name']} (capacità: {$room['capacity']})\n";
}

echo "\n";
echo "Tavoli attivi: " . count($tables) . "\n";
$totalCapacity = 0;
foreach ($tables as $table) {
    $capacity = max($table['seats_max'], $table['seats_std'], $table['seats_min']);
    $totalCapacity += $capacity;
    echo "  Tavolo #{$table['id']} - Sala #{$table['room_id']} - {$table['seats_min']}-{$table['seats_std']}-{$table['seats_max']} posti\n";
}
echo "\n";
echo "Capacità totale tavoli: {$totalCapacity} persone\n";
echo "\n";

// Check 5: Closures
echo "📋 Check 5: Chiusure Programmate\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$closures = $wpdb->get_results($wpdb->prepare(
    "SELECT id, scope, type, start_at, end_at, recurrence_json 
     FROM {$wpdb->prefix}fp_closures 
     WHERE active = 1 
     AND ((start_at <= %s AND end_at >= %s) OR recurrence_json IS NOT NULL AND recurrence_json <> '')",
    $date . ' 23:59:59',
    $date . ' 00:00:00'
), ARRAY_A);

if (empty($closures)) {
    echo "✅ Nessuna chiusura programmata per questa data\n";
} else {
    echo "⚠️  Chiusure trovate: " . count($closures) . "\n\n";
    foreach ($closures as $closure) {
        echo "  #{$closure['id']} - {$closure['scope']} - {$closure['type']}\n";
        echo "    Da:   {$closure['start_at']}\n";
        echo "    A:    {$closure['end_at']}\n";
        if ($closure['recurrence_json']) {
            echo "    Ricorrenza: {$closure['recurrence_json']}\n";
        }
    }
}
echo "\n";

// Check 6: Try to get slots
echo "📋 Check 6: Calcolo Disponibilità\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $criteria = [
        'date' => $date,
        'party' => $party,
    ];
    
    if ($mealKey !== '') {
        $criteria['meal'] = $mealKey;
    }
    
    // Enable debug logging
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    
    $result = $availability->findSlots($criteria);
    
    echo "Richiesta completata!\n\n";
    echo "Data:     {$result['date']}\n";
    echo "Timezone: {$result['timezone']}\n";
    echo "Criteri:  " . json_encode($result['criteria']) . "\n";
    echo "\n";
    
    if (isset($result['meta']['has_availability'])) {
        $hasAvail = $result['meta']['has_availability'];
        $icon = $hasAvail ? '✅' : '❌';
        echo "{$icon} Ha disponibilità: " . ($hasAvail ? 'SÌ' : 'NO') . "\n";
    }
    
    if (isset($result['meta']['reason'])) {
        echo "⚠️  Motivo: {$result['meta']['reason']}\n";
    }
    
    if (isset($result['meta']['debug'])) {
        echo "\n📊 Debug info:\n";
        foreach ($result['meta']['debug'] as $key => $value) {
            echo "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    }
    
    echo "\n";
    echo "Slot trovati: " . count($result['slots']) . "\n";
    
    if (count($result['slots']) === 0) {
        echo "\n❌ PROBLEMA IDENTIFICATO!\n";
        echo "   Nessuno slot disponibile per questa data.\n";
        echo "\n";
        echo "🔍 Possibili cause:\n";
        echo "   1. Orari di servizio non configurati per questo giorno\n";
        echo "   2. Meal plan ha orari vuoti per questo giorno\n";
        echo "   3. Tutte le chiusure bloccano gli slot\n";
        echo "\n";
    } else {
        // Group by status
        $byStatus = [];
        foreach ($result['slots'] as $slot) {
            $status = $slot['status'];
            if (!isset($byStatus[$status])) {
                $byStatus[$status] = [];
            }
            $byStatus[$status][] = $slot;
        }
        
        echo "\n";
        foreach ($byStatus as $status => $slots) {
            $icon = match ($status) {
                'available' => '✅',
                'limited' => '⚠️',
                'full' => '❌',
                'blocked' => '🚫',
                default => '❓'
            };
            
            echo "{$icon} {$status}: " . count($slots) . " slot\n";
            
            // Show first few slots of each type
            $showCount = min(3, count($slots));
            for ($i = 0; $i < $showCount; $i++) {
                $slot = $slots[$i];
                echo "    {$slot['label']} - Capacità: {$slot['available_capacity']}";
                if (!empty($slot['reasons'])) {
                    echo " - " . implode('; ', $slot['reasons']);
                }
                echo "\n";
            }
            
            if (count($slots) > $showCount) {
                echo "    ... e altri " . (count($slots) - $showCount) . " slot\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Errore durante il calcolo:\n";
    echo "   {$e->getMessage()}\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  📊 RIEPILOGO E RACCOMANDAZIONI\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

// Summary
$issues = [];

if ($generalSchedule === '' && $mealsDefinition === '') {
    $issues[] = "Nessun orario di servizio configurato!";
}

if ($mealKey !== '' && isset($indexedMeals) && !isset($indexedMeals[$mealKey])) {
    $issues[] = "Il meal '{$mealKey}' non esiste nella configurazione";
}

if (isset($result) && count($result['slots']) === 0) {
    $issues[] = "Nessuno slot disponibile per questa data/meal";
}

if (empty($issues)) {
    echo "✅ Configurazione sembra corretta!\n";
    echo "\n";
    echo "Se il problema persiste nel frontend:\n";
    echo "1. Verifica la cache del browser (Ctrl+Shift+R)\n";
    echo "2. Controlla la console JavaScript per errori\n";
    echo "3. Verifica che i parametri inviati al backend siano corretti\n";
} else {
    echo "⚠️  Problemi identificati:\n\n";
    foreach ($issues as $i => $issue) {
        echo "  " . ($i + 1) . ". {$issue}\n";
    }
    echo "\n";
    echo "🔧 AZIONI RACCOMANDATE:\n\n";
    
    if ($generalSchedule === '' && $mealsDefinition === '') {
        echo "  1. Configura gli orari di servizio in:\n";
        echo "     WordPress Admin > Prenotazioni > Impostazioni > Orari servizio\n";
        echo "\n";
    }
    
    if ($mealKey !== '' && isset($indexedMeals) && !isset($indexedMeals[$mealKey])) {
        echo "  2. Verifica il Meal Plan in:\n";
        echo "     WordPress Admin > Prenotazioni > Impostazioni > Meal Plan\n";
        echo "     Oppure rimuovi il parametro meal dalla richiesta\n";
        echo "\n";
    }
    
    if (isset($result) && count($result['slots']) === 0 && isset($result['meta']['debug']['message'])) {
        echo "  3. {$result['meta']['debug']['message']}\n";
        echo "\n";
    }
}

echo "════════════════════════════════════════════════════════════════\n";
echo "\n";
