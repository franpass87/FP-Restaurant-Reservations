#!/usr/bin/env php
<?php
/**
 * Test per dimostrare il bug della capacità zero
 */

echo "\n";
echo "🧪 TEST DIMOSTRAZIONE BUG CAPACITÀ\n";
echo "════════════════════════════════════════════════════════\n\n";

// Simula la funzione PRIMA del fix
function aggregateRoomCapacities_BEFORE($rooms, $tables, $defaultRoomCap) {
    $capacities = [];
    
    foreach ($rooms as $room) {
        $capacities[$room['id']] = [
            'capacity'       => max($room['capacity'], $defaultRoomCap),
            'table_capacity' => 0,
        ];
    }

    foreach ($tables as $table) {
        $roomId = $table['room_id'];
        if (!isset($capacities[$roomId])) {
            $capacities[$roomId] = [
                'capacity'       => $defaultRoomCap,
                'table_capacity' => 0,
            ];
        }

        $capacities[$roomId]['table_capacity'] += $table['capacity'];
        $capacities[$roomId]['capacity'] = max(
            $capacities[$roomId]['capacity'],
            $capacities[$roomId]['table_capacity']
        );
    }

    // MANCA IL FIX!
    return $capacities;
}

// Simula la funzione DOPO il fix
function aggregateRoomCapacities_AFTER($rooms, $tables, $defaultRoomCap) {
    $capacities = [];
    
    foreach ($rooms as $room) {
        $capacities[$room['id']] = [
            'capacity'       => max($room['capacity'], $defaultRoomCap),
            'table_capacity' => 0,
        ];
    }

    foreach ($tables as $table) {
        $roomId = $table['room_id'];
        if (!isset($capacities[$roomId])) {
            $capacities[$roomId] = [
                'capacity'       => $defaultRoomCap,
                'table_capacity' => 0,
            ];
        }

        $capacities[$roomId]['table_capacity'] += $table['capacity'];
        $capacities[$roomId]['capacity'] = max(
            $capacities[$roomId]['capacity'],
            $capacities[$roomId]['table_capacity']
        );
    }

    // FIX APPLICATO
    if (empty($capacities)) {
        $capacities[0] = [
            'capacity'       => $defaultRoomCap,
            'table_capacity' => 0,
        ];
    }

    return $capacities;
}

function resolveCapacityForScope($roomCapacities, $roomId, $hasTables) {
    if ($roomId !== null) {
        $capacity = $roomCapacities[$roomId] ?? ['capacity' => 0, 'table_capacity' => 0];
        return $hasTables ? max($capacity['table_capacity'], 0) : max($capacity['capacity'], 0);
    }

    $total = 0;
    foreach ($roomCapacities as $capacity) {
        $total += $hasTables ? $capacity['table_capacity'] : $capacity['capacity'];
    }

    return $total;
}

// SCENARIO: Tavoli disabilitati (come l'utente)
$rooms = [];  // Nessuna sala configurata o attiva
$tables = []; // Nessun tavolo configurato o attivo
$defaultRoomCap = 40;
$roomId = null;
$hasTables = false;

echo "📋 SCENARIO UTENTE:\n";
echo "   - Sale (rooms): " . (empty($rooms) ? "❌ Nessuna" : count($rooms)) . "\n";
echo "   - Tavoli (tables): " . (empty($tables) ? "❌ Nessuno" : count($tables)) . "\n";
echo "   - Capacità default: {$defaultRoomCap}\n";
echo "\n";

// TEST PRIMA DEL FIX
echo "❌ PRIMA DEL FIX:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$capacitiesBefore = aggregateRoomCapacities_BEFORE($rooms, $tables, $defaultRoomCap);
echo "   aggregateRoomCapacities() → " . json_encode($capacitiesBefore) . "\n";

$baseCapacityBefore = resolveCapacityForScope($capacitiesBefore, $roomId, $hasTables);
echo "   resolveCapacityForScope() → {$baseCapacityBefore}\n";

if ($baseCapacityBefore <= 0) {
    echo "   determineStatus() → ❌ 'full' (perché capacity = 0)\n";
    echo "   RISULTATO: ❌ 'Completamente prenotato' (BUG!)\n";
} else {
    echo "   RISULTATO: ✅ Slots disponibili\n";
}

echo "\n";

// TEST DOPO IL FIX
echo "✅ DOPO IL FIX:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$capacitiesAfter = aggregateRoomCapacities_AFTER($rooms, $tables, $defaultRoomCap);
echo "   aggregateRoomCapacities() → " . json_encode($capacitiesAfter) . "\n";

$baseCapacityAfter = resolveCapacityForScope($capacitiesAfter, $roomId, $hasTables);
echo "   resolveCapacityForScope() → {$baseCapacityAfter}\n";

if ($baseCapacityAfter <= 0) {
    echo "   determineStatus() → ❌ 'full'\n";
    echo "   RISULTATO: ❌ Bug ancora presente\n";
} else {
    echo "   determineStatus() → ✅ 'available' (per party < {$baseCapacityAfter})\n";
    echo "   RISULTATO: ✅ Slots disponibili!\n";
}

echo "\n";
echo "════════════════════════════════════════════════════════\n";

// CONCLUSIONE
echo "\n📊 DIMOSTRAZIONE:\n\n";

echo "Quando tavoli sono disabilitati (o non configurati):\n\n";

echo "PRIMA:\n";
echo "  rooms = [] + tables = []\n";
echo "  → roomCapacities = [] (vuoto!)\n";
echo "  → baseCapacity = 0\n";
echo "  → ❌ TUTTI gli slot = 'full'\n";
echo "  → ❌ 'Completamente prenotato' SEMPRE\n\n";

echo "DOPO:\n";
echo "  rooms = [] + tables = []\n";
echo "  → roomCapacities = [0 => ['capacity' => 40, ...]]\n";
echo "  → baseCapacity = 40\n";
echo "  → ✅ Slots 'available', 'limited', etc.\n";
echo "  → ✅ Sistema funziona normalmente!\n\n";

echo "════════════════════════════════════════════════════════\n";
echo "✅ Il bug dipendeva dal fatto che senza tavoli/sale,\n";
echo "   la capacità era 0 invece di usare il default.\n";
echo "════════════════════════════════════════════════════════\n\n";
