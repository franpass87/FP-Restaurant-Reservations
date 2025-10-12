<?php
/**
 * MIGRAZIONE AUTOMATICA - Apri questo file nel browser (loggato come admin)
 */

$wp_load_paths = [__DIR__ . '/../../../wp-load.php', __DIR__ . '/../../../../wp-load.php'];
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    die('Accesso negato');
}

global $wpdb;
$table = $wpdb->prefix . 'fp_reservations';
$columns = $wpdb->get_col("DESCRIBE $table", 0);
$hasMeal = in_array('meal', $columns);

if (!$hasMeal) {
    delete_option('fp_resv_db_version');
    \FP\Resv\Core\Migrations::run();
    $hasMeal = in_array('meal', $wpdb->get_col("DESCRIBE $table", 0));
}

header('Location: ' . admin_url('admin.php?page=fp-resv-manager'));
exit;

