<?php
/**
 * Diagnostic tool for form visibility issues
 * 
 * This script helps diagnose why the reservation form is not appearing on a page.
 * 
 * Usage:
 * 1. Upload this file to your WordPress root or tools directory
 * 2. Access it via browser: https://yoursite.com/wp-content/plugins/fp-restaurant-reservations/tools/diagnose-form-visibility.php
 * 3. Or run via WP-CLI: wp eval-file tools/diagnose-form-visibility.php
 */

// Ensure WordPress environment is loaded
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        __DIR__ . '/../../../../wp-load.php',  // From plugin tools dir
        __DIR__ . '/../../../wp-load.php',     // From plugin root
        __DIR__ . '/../../wp-load.php',        // Alternative
        __DIR__ . '/../wp-load.php',           // Alternative
    ];
    
    $loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        die('Error: Could not load WordPress. Please run this script from WordPress root or use WP-CLI.');
    }
}

// Check if we're in CLI mode
$is_cli = defined('WP_CLI') && WP_CLI;

if (!$is_cli) {
    // Security check for web access
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this diagnostic tool.');
    }
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FP Restaurant Reservations - Form Visibility Diagnostics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #2271b1; }
        h2 { color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px; margin-top: 30px; }
        .status { padding: 15px; margin: 10px 0; border-radius: 4px; }
        .status.ok { background: #d4edda; border-left: 4px solid #28a745; }
        .status.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .status.error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        .icon-ok { color: #28a745; }
        .icon-error { color: #dc3545; }
        .icon-warning { color: #ffc107; }
    </style>
</head>
<body>';
    echo '<h1>🔍 FP Restaurant Reservations - Form Visibility Diagnostics</h1>';
}

/**
 * Output a message
 */
function diagnostic_output($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        if (defined('WP_CLI') && WP_CLI) {
            switch ($type) {
                case 'error':
                    WP_CLI::error($message, false);
                    break;
                case 'warning':
                    WP_CLI::warning($message);
                    break;
                case 'success':
                    WP_CLI::success($message);
                    break;
                default:
                    WP_CLI::log($message);
            }
        } else {
            echo $message . "\n";
        }
    } else {
        $class_map = [
            'error' => 'error',
            'warning' => 'warning',
            'success' => 'ok',
            'ok' => 'ok',
        ];
        $class = $class_map[$type] ?? 'info';
        echo '<div class="status ' . esc_attr($class) . '">' . esc_html($message) . '</div>';
    }
}

// Start diagnostics
diagnostic_output('=== FP Restaurant Reservations - Form Visibility Diagnostics ===', 'info');
diagnostic_output('Date: ' . current_time('mysql'), 'info');

// 1. Check plugin is active
diagnostic_output('', 'info');
diagnostic_output('1. Checking if plugin is active...', 'info');

if (!class_exists('FP\Resv\Core\Plugin')) {
    diagnostic_output('❌ ERROR: Plugin is not active or not loaded correctly', 'error');
    if (!$is_cli) {
        echo '</body></html>';
    }
    exit(1);
}

diagnostic_output('✅ Plugin is active', 'success');

// 2. Check if shortcode is registered
diagnostic_output('', 'info');
diagnostic_output('2. Checking shortcode registration...', 'info');

if (!shortcode_exists('fp_reservations')) {
    diagnostic_output('❌ ERROR: Shortcode [fp_reservations] is not registered', 'error');
} else {
    diagnostic_output('✅ Shortcode [fp_reservations] is registered', 'success');
}

// 3. Check template file exists
diagnostic_output('', 'info');
diagnostic_output('3. Checking template files...', 'info');

$plugin_dir = defined('FP\Resv\Core\Plugin::$dir') ? constant('FP\Resv\Core\Plugin::$dir') : WP_PLUGIN_DIR . '/fp-restaurant-reservations/';
$template_path = $plugin_dir . 'templates/frontend/form.php';

if (!file_exists($template_path)) {
    diagnostic_output('❌ ERROR: Template file not found at: ' . $template_path, 'error');
} else {
    diagnostic_output('✅ Template file exists', 'success');
    diagnostic_output('   Path: ' . $template_path, 'info');
}

// 4. Check JavaScript files
diagnostic_output('', 'info');
diagnostic_output('4. Checking JavaScript files...', 'info');

$js_files = [
    'ESM Module' => $plugin_dir . 'assets/dist/fe/onepage.esm.js',
    'IIFE Legacy' => $plugin_dir . 'assets/dist/fe/onepage.iife.js',
    'Fallback' => $plugin_dir . 'assets/js/fe/form-app-fallback.js',
];

$js_found = 0;
foreach ($js_files as $label => $path) {
    if (file_exists($path)) {
        diagnostic_output('✅ ' . $label . ' exists', 'success');
        $js_found++;
    } else {
        diagnostic_output('⚠️  ' . $label . ' not found', 'warning');
    }
}

if ($js_found === 0) {
    diagnostic_output('❌ ERROR: No JavaScript files found!', 'error');
}

// 5. Check CSS file
diagnostic_output('', 'info');
diagnostic_output('5. Checking CSS files...', 'info');

$css_path = $plugin_dir . 'assets/css/form.css';
if (file_exists($css_path)) {
    diagnostic_output('✅ CSS file exists', 'success');
} else {
    diagnostic_output('❌ ERROR: CSS file not found at: ' . $css_path, 'error');
}

// 6. Test shortcode rendering
diagnostic_output('', 'info');
diagnostic_output('6. Testing shortcode rendering...', 'info');

if (class_exists('FP\Resv\Frontend\Shortcodes')) {
    try {
        $test_output = \FP\Resv\Frontend\Shortcodes::render([]);
        
        if (empty($test_output)) {
            diagnostic_output('❌ ERROR: Shortcode returns empty output', 'error');
        } elseif (strlen($test_output) < 100) {
            diagnostic_output('⚠️  WARNING: Shortcode output is very short (' . strlen($test_output) . ' chars)', 'warning');
            diagnostic_output('   Output: ' . substr($test_output, 0, 200), 'info');
        } else {
            diagnostic_output('✅ Shortcode renders successfully (' . strlen($test_output) . ' chars)', 'success');
            
            // Check if output contains critical elements
            $has_form = strpos($test_output, 'data-fp-resv-form') !== false;
            $has_widget = strpos($test_output, 'data-fp-resv') !== false;
            $has_sections = strpos($test_output, 'data-fp-resv-section') !== false;
            
            if ($has_form && $has_widget && $has_sections) {
                diagnostic_output('✅ Output contains all critical elements', 'success');
            } else {
                diagnostic_output('⚠️  WARNING: Output may be missing critical elements:', 'warning');
                diagnostic_output('   - Form element: ' . ($has_form ? 'Yes' : 'No'), 'info');
                diagnostic_output('   - Widget wrapper: ' . ($has_widget ? 'Yes' : 'No'), 'info');
                diagnostic_output('   - Form sections: ' . ($has_sections ? 'Yes' : 'No'), 'info');
            }
        }
    } catch (\Throwable $e) {
        diagnostic_output('❌ ERROR: Exception during shortcode render: ' . $e->getMessage(), 'error');
        diagnostic_output('   Stack trace: ' . $e->getTraceAsString(), 'info');
    }
}

// 7. Check for filters that might prevent loading
diagnostic_output('', 'info');
diagnostic_output('7. Checking filters...', 'info');

global $wp_filter;
if (isset($wp_filter['fp_resv_frontend_should_enqueue'])) {
    diagnostic_output('⚠️  WARNING: Filter "fp_resv_frontend_should_enqueue" is active', 'warning');
    diagnostic_output('   This filter may prevent assets from loading', 'info');
} else {
    diagnostic_output('✅ No blocking filters detected', 'success');
}

// 8. Check pages with shortcode
diagnostic_output('', 'info');
diagnostic_output('8. Searching for pages with shortcode...', 'info');

$pages_with_shortcode = get_posts([
    'post_type' => 'page',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    's' => '[fp_reservations',
]);

if (empty($pages_with_shortcode)) {
    diagnostic_output('⚠️  WARNING: No published pages found with [fp_reservations] shortcode', 'warning');
    diagnostic_output('   Make sure to add the shortcode to a page:', 'info');
    diagnostic_output('   [fp_reservations]', 'info');
} else {
    diagnostic_output('✅ Found ' . count($pages_with_shortcode) . ' page(s) with shortcode:', 'success');
    foreach ($pages_with_shortcode as $page) {
        diagnostic_output('   - ' . $page->post_title . ' (' . get_permalink($page->ID) . ')', 'info');
    }
}

// 9. Summary and recommendations
diagnostic_output('', 'info');
diagnostic_output('=== SUMMARY AND RECOMMENDATIONS ===', 'info');
diagnostic_output('', 'info');

if ($js_found === 0) {
    diagnostic_output('❌ CRITICAL: JavaScript files are missing. Run the build process:', 'error');
    diagnostic_output('   npm install && npm run build', 'info');
}

if (empty($pages_with_shortcode)) {
    diagnostic_output('⚠️  ACTION REQUIRED: Add the shortcode to a page:', 'warning');
    diagnostic_output('   1. Go to Pages > Add New', 'info');
    diagnostic_output('   2. Add this shortcode to the content: [fp_reservations]', 'info');
    diagnostic_output('   3. Publish the page', 'info');
}

diagnostic_output('', 'info');
diagnostic_output('For the specific page "ristorante-vinci-toscana":', 'info');
diagnostic_output('1. Verify the page exists and is published', 'info');
diagnostic_output('2. Check that the shortcode [fp_reservations] is present in the content', 'info');
diagnostic_output('3. Clear all caches (WordPress, browser, CDN)', 'info');
diagnostic_output('4. Check browser console for JavaScript errors', 'info');

if (!$is_cli) {
    echo '</body></html>';
}
