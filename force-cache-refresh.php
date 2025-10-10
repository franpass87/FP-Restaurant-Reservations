<?php
/**
 * Script temporaneo per forzare il refresh della cache degli asset
 * 
 * ISTRUZIONI:
 * 1. Carica questo file nella root di WordPress sul tuo server (dove si trova wp-config.php)
 * 2. Visita: https://tuosito.com/force-cache-refresh.php (devi essere loggato come admin)
 * 3. Elimina questo file dopo l'uso per sicurezza
 * 
 * NOTA: NON committare questo file nel repository!
 */

// Carica WordPress
require_once __DIR__ . '/wp-load.php';

// Verifica che l'utente sia un amministratore
if (!current_user_can('manage_options')) {
    wp_die('Permesso negato. Devi essere un amministratore per eseguire questo script.');
}

// Forza il refresh degli asset
if (class_exists('FP\Resv\Core\Plugin')) {
    \FP\Resv\Core\Plugin::forceRefreshAssets();
    
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Cache Aggiornata</title>';
    echo '<style>body{font-family:sans-serif;max-width:800px;margin:50px auto;padding:20px;}';
    echo 'h1{color:#46b450;}ol{line-height:1.8;}code{background:#f0f0f0;padding:2px 6px;}</style></head><body>';
    echo '<h1>✅ Cache Aggiornata con Successo!</h1>';
    echo '<p>La cache degli asset è stata aggiornata. Il timestamp corrente è: <code>' . time() . '</code></p>';
    echo '<p><strong>Prossimi passi:</strong></p>';
    echo '<ol>';
    echo '<li>Vai all\'<a href="' . admin_url('admin.php?page=fp-resv-agenda') . '">Agenda</a></li>';
    echo '<li>Fai un hard refresh del browser:<ul>';
    echo '<li>Windows: <code>Ctrl + Shift + R</code> o <code>Ctrl + F5</code></li>';
    echo '<li>Mac: <code>Cmd + Shift + R</code></li></ul></li>';
    echo '<li>Verifica che l\'agenda si carichi correttamente</li>';
    echo '<li><strong>IMPORTANTE:</strong> Elimina questo file (<code>force-cache-refresh.php</code>) dal server per sicurezza!</li>';
    echo '</ol>';
    echo '<hr>';
    echo '<p><small>Per verificare che gli asset siano aggiornati, apri Developer Tools (F12), vai alla tab Network e controlla il parametro "ver" negli URL degli script.</small></p>';
    echo '</body></html>';
} else {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Errore</title></head><body>';
    echo '<h1>❌ Errore</h1>';
    echo '<p>La classe Plugin non è stata trovata. Assicurati che il plugin FP Restaurant Reservations sia attivo.</p>';
    echo '</body></html>';
}
