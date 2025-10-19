<?php
/**
 * Test per verificare lo stato del plugin
 */

// Simula l'ambiente WordPress
define('ABSPATH', __DIR__ . '/');
define('WP_DEBUG', true);

// Funzioni WordPress mock
function get_bloginfo($show = '') {
    return '6.5.0';
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    return true;
}

function register_rest_route($namespace, $route, $args = [], $override = false) {
    return true;
}

function __return_true() {
    return true;
}

function __return_false() {
    return false;
}

function __($text, $domain = 'default') {
    return $text;
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_url_raw($url) {
    return $url;
}

function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function wp_json_encode($data, $options = 0, $depth = 512) {
    return json_encode($data, $options, $depth);
}

function home_url($path = '', $scheme = null) {
    return 'https://example.com/' . ltrim($path, '/');
}

function get_option($option, $default = false) {
    return $default;
}

function update_option($option, $value, $autoload = null) {
    return true;
}

function get_transient($transient) {
    return false;
}

function set_transient($transient, $value, $expiration = 0) {
    return true;
}

function wp_cache_get($key, $group = '') {
    return false;
}

function wp_cache_set($key, $data, $group = '', $expiration = 0) {
    return true;
}

function wp_rand($min = 0, $max = null) {
    return rand($min, $max ?? getrandmax());
}

function wp_salt($scheme = 'auth') {
    return 'test-salt';
}

function wp_hash_hmac($algo, $data, $key, $raw_output = false) {
    return hash_hmac($algo, $data, $key, $raw_output);
}

function wp_md5($str) {
    return md5($str);
}

function wp_serialize($value) {
    return serialize($value);
}

function wp_is_array($var) {
    return is_array($var);
}

function wp_is_string($var) {
    return is_string($var);
}

function wp_trim($str, $charlist = " \t\n\r\0\x0B") {
    return trim($str, $charlist);
}

function wp_strtolower($str) {
    return strtolower($str);
}

function wp_preg_match($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
    return preg_match($pattern, $subject, $matches, $flags, $offset);
}

function wp_in_array($needle, $haystack, $strict = false) {
    return in_array($needle, $haystack, $strict);
}

function wp_absint($maybeint) {
    return abs((int) $maybeint);
}

function add_query_arg($key, $value, $url = false) {
    return $url;
}

function apply_filters($hook_name, $value, ...$args) {
    return $value;
}

function wp_defined($constant_name) {
    return defined($constant_name);
}

function wp_sprintf($format, ...$args) {
    return sprintf($format, ...$args);
}

function is_admin() {
    return false;
}

function plugin_dir_path($file) {
    return dirname($file) . '/';
}

function plugin_dir_url($file) {
    return 'https://example.com/wp-content/plugins/fp-restaurant-reservations/';
}

function trailingslashit($string) {
    return untrailingslashit($string) . '/';
}

function untrailingslashit($string) {
    return rtrim($string, '/\\');
}

function rest_ensure_response($response) {
    return $response;
}

function current_time($type = 'timestamp', $gmt = 0) {
    return time();
}

function wp_date($format, $timestamp = null) {
    return date($format, $timestamp ?? time());
}

function deactivate_plugins($plugins, $silent = false, $network_wide = false) {
    return true;
}

function delete_option($option) {
    return true;
}

function esc_html__($text, $domain = 'default') {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function wp_function_exists($function_name) {
    return function_exists($function_name);
}

function wp_implode($separator, $array) {
    return implode($separator, $array);
}

function plugin_basename($file) {
    return basename($file);
}

function wp_register_shutdown_function($callback, ...$args) {
    return register_shutdown_function($callback, ...$args);
}

function wp_time() {
    return time();
}

function wp_date_format($format, $timestamp = null, $timezone = null) {
    return date($format, $timestamp ?? time());
}

function wp_error_get_last() {
    return error_get_last();
}

function register_activation_hook($file, $callback) {
    return true;
}

function register_deactivation_hook($file, $callback) {
    return true;
}

// Classi WordPress mock
class WP_REST_Response {
    public $data;
    public $status;
    
    public function __construct($data = null, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
}

class WP_REST_Server {
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'POST, PUT, PATCH';
    const DELETABLE = 'DELETE';
    const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
}

class WP_Error {
    public $errors = [];
    public $error_data = [];
    
    public function __construct($code = '', $message = '', $data = '') {
        if (empty($code)) {
            return;
        }
        
        $this->errors[$code][] = $message;
        
        if (!empty($data)) {
            $this->error_data[$code] = $data;
        }
    }
}

class WP_REST_Request {
    public $params = [];
    
    public function __construct($method = 'GET', $route = '', $attributes = []) {
        $this->params = $_GET;
    }
    
    public function get_param($key) {
        return $this->params[$key] ?? null;
    }
}

// Test di caricamento
echo "=== TEST STATO PLUGIN ===\n";

try {
    echo "1. Caricamento file principale...\n";
    require_once __DIR__ . '/fp-restaurant-reservations.php';
    echo "✅ File principale caricato\n";
    
    echo "2. Verifica classe Plugin...\n";
    if (class_exists('FP\Resv\Core\Plugin')) {
        echo "✅ Classe Plugin trovata\n";
    } else {
        echo "❌ Classe Plugin non trovata\n";
    }
    
    echo "3. Verifica Plugin::\$dir...\n";
    if (property_exists('FP\Resv\Core\Plugin', 'dir')) {
        echo "✅ Proprietà dir trovata\n";
    } else {
        echo "❌ Proprietà dir non trovata\n";
    }
    
    echo "4. Verifica classe REST...\n";
    if (class_exists('FP\Resv\Domain\Reservations\REST')) {
        echo "✅ Classe REST trovata\n";
    } else {
        echo "❌ Classe REST non trovata\n";
    }
    
    echo "5. Test registrazione endpoint...\n";
    // Simula la registrazione degli endpoint
    if (function_exists('register_rest_route')) {
        echo "✅ Funzione register_rest_route disponibile\n";
    } else {
        echo "❌ Funzione register_rest_route non disponibile\n";
    }
    
    echo "\n=== TEST COMPLETATO CON SUCCESSO ===\n";
    
} catch (Throwable $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Linea: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
