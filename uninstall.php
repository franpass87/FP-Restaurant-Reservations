<?php
/**
 * Uninstall script per FP Restaurant Reservations
 * 
 * Questo file viene eseguito quando il plugin viene DISINSTALLATO (non disattivato).
 * Per impostazione predefinita, MANTIENE tutti i dati nel database per evitare
 * perdite accidentali di configurazioni e prenotazioni.
 * 
 * L'utente può scegliere di eliminare i dati dalle impostazioni del plugin.
 * 
 * @package FP\Resv
 */

declare(strict_types=1);

// Se il file viene chiamato direttamente, blocca l'esecuzione
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verifica se l'utente ha scelto di eliminare i dati alla disinstallazione
$keepData = get_option('fp_resv_keep_data_on_uninstall', '1'); // Default: mantieni i dati

// Se l'opzione è '1' (mantieni i dati), non fare nulla
if ($keepData === '1') {
    // Log per debug (se WP_DEBUG è attivo)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[FP Restaurant Reservations] Disinstallazione: i dati sono stati mantenuti nel database come richiesto.');
    }
    return;
}

// Se arriviamo qui, l'utente ha esplicitamente richiesto di eliminare tutti i dati
global $wpdb;

// Elimina tutte le tabelle del plugin
$tables = [
    $wpdb->prefix . 'fp_resv_reservations',
    $wpdb->prefix . 'fp_resv_customers',
    $wpdb->prefix . 'fp_resv_payments',
    $wpdb->prefix . 'fp_resv_tables',
    $wpdb->prefix . 'fp_resv_closures',
    $wpdb->prefix . 'fp_resv_brevo_contacts',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Elimina tutte le opzioni del plugin
$optionKeys = [
    'fp_resv_general',
    'fp_resv_business',
    'fp_resv_availability',
    'fp_resv_payments',
    'fp_resv_brevo',
    'fp_resv_calendar',
    'fp_resv_language',
    'fp_resv_notifications',
    'fp_resv_tracking',
    'fp_resv_style',
    'fp_resv_db_version',
    'fp_resv_last_upgrade',
    'fp_resv_current_version',
    'fp_resv_keep_data_on_uninstall',
];

foreach ($optionKeys as $key) {
    delete_option($key);
}

// Elimina tutti i transient del plugin
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_') . '%fp_resv%',
        $wpdb->esc_like('_transient_timeout_') . '%fp_resv%'
    )
);

// Elimina i ruoli personalizzati
remove_role('fp_reservation_manager');

// Elimina i post type custom (eventi)
$events = get_posts([
    'post_type'   => 'fp_resv_event',
    'numberposts' => -1,
    'post_status' => 'any',
]);

foreach ($events as $event) {
    wp_delete_post($event->ID, true);
}

// Log per debug
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[FP Restaurant Reservations] Disinstallazione completata: tutti i dati sono stati eliminati dal database.');
}
