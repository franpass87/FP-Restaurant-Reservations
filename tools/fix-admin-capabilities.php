<?php
/**
 * Script per riparare le capabilities degli amministratori
 * 
 * Questo script verifica e ripara la capability 'manage_fp_reservations'
 * per il ruolo administrator, garantendo l'accesso completo al menu.
 * 
 * Uso: wp eval-file tools/fix-admin-capabilities.php
 */

declare(strict_types=1);

// Verifica che siamo in ambiente WordPress
if (!defined('ABSPATH')) {
    echo "Questo script deve essere eseguito in ambiente WordPress.\n";
    echo "Uso: wp eval-file tools/fix-admin-capabilities.php\n";
    exit(1);
}

// Capability da verificare/aggiungere
const MANAGE_RESERVATIONS = 'manage_fp_reservations';

echo "=== Fix Capabilities Amministratore ===\n\n";

// Ottieni il ruolo administrator
$adminRole = get_role('administrator');

if ($adminRole === null) {
    echo "ERRORE: Ruolo 'administrator' non trovato!\n";
    exit(1);
}

echo "Ruolo administrator trovato.\n";

// Verifica se la capability è già presente
if ($adminRole->has_cap(MANAGE_RESERVATIONS)) {
    echo "✓ La capability '" . MANAGE_RESERVATIONS . "' è già presente.\n";
} else {
    echo "✗ La capability '" . MANAGE_RESERVATIONS . "' NON è presente.\n";
    echo "  Aggiunta in corso...\n";
    
    // Aggiungi la capability
    $adminRole->add_cap(MANAGE_RESERVATIONS);
    
    // Verifica nuovamente
    $adminRole = get_role('administrator');
    if ($adminRole && $adminRole->has_cap(MANAGE_RESERVATIONS)) {
        echo "✓ Capability aggiunta con successo!\n";
    } else {
        echo "✗ ERRORE: Impossibile aggiungere la capability.\n";
        exit(1);
    }
}

echo "\n=== Verifica Completa ===\n";
echo "Tutte le capabilities dell'administrator:\n";

if (isset($adminRole->capabilities) && is_array($adminRole->capabilities)) {
    $reservationCaps = array_filter(
        $adminRole->capabilities,
        function($key) {
            return strpos($key, 'fp_') === 0 || strpos($key, 'manage') === 0;
        },
        ARRAY_FILTER_USE_KEY
    );
    
    if (!empty($reservationCaps)) {
        foreach ($reservationCaps as $cap => $value) {
            echo "  - $cap: " . ($value ? 'SI' : 'NO') . "\n";
        }
    }
}

echo "\n=== Operazione Completata ===\n";
echo "Gli amministratori ora hanno accesso completo al menu FP Reservations.\n";
