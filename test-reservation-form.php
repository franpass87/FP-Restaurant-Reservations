<?php
/**
 * Test page for reservation form
 * Access via: http://fp-development.local/wp-content/plugins/FP-Restaurant-Reservations/test-reservation-form.php
 */

// Load WordPress - resolve real path to handle junctions/symlinks
$plugin_dir = realpath(__DIR__);
if ($plugin_dir === false) {
    $plugin_dir = __DIR__;
}
$wp_load = dirname(dirname(dirname($plugin_dir))) . '/wp-load.php';

// If still not found, try absolute path based on workspace
if (!file_exists($wp_load)) {
    $wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
}

if (!file_exists($wp_load)) {
    die('WordPress not found. Plugin dir: ' . $plugin_dir . ', Looking for: ' . $wp_load);
}

require_once $wp_load;

// Check if user is logged in (optional, for testing)
// if (!is_user_logged_in()) {
//     wp_die('Please log in to test the form');
// }

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Form Prenotazione</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="container">
        <h1>Test Form Prenotazione</h1>
        <p>Questa è una pagina di test per il form di prenotazione frontend.</p>
        
        <?php
        // Render the shortcode
        if (shortcode_exists('fp_reservations')) {
            echo do_shortcode('[fp_reservations]');
        } else {
            echo '<p style="color: red;">Errore: Lo shortcode [fp_reservations] non è registrato.</p>';
        }
        ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

