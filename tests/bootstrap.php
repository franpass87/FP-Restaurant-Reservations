<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($rootDir): void {
    if (str_starts_with($class, 'FP\\Resv\\')) {
        $relative = substr($class, strlen('FP\\Resv\\'));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $path     = $rootDir . '/src/' . $relative . '.php';

        if (is_readable($path)) {
            require_once $path;
        }
    }
});

if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';
        public int $insert_id = 0;
        public string $last_error = '';
    }
}

require_once __DIR__ . '/Support/FakeWpdb.php';

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

FP\Resv\Core\Plugin::$dir = $rootDir . '/';
FP\Resv\Core\Plugin::$file = $rootDir . '/fp-restaurant-reservations.php';
FP\Resv\Core\Plugin::$url  = 'https://example.test/wp-content/plugins/fp-restaurant-reservations/';

$GLOBALS['__wp_tests_options']    = [];
$GLOBALS['__wp_tests_transients'] = [];
$GLOBALS['__wp_tests_mail_log']   = [];
$GLOBALS['__wp_tests_actions']    = [];
$GLOBALS['__wp_tests_hooks']      = [];

function get_option(string $name, mixed $default = null): mixed
{
    return $GLOBALS['__wp_tests_options'][$name] ?? $default;
}

function update_option(string $name, mixed $value): bool
{
    $GLOBALS['__wp_tests_options'][$name] = $value;

    return true;
}

function wp_parse_args(mixed $args, array $defaults = []): array
{
    if (!is_array($args)) {
        $args = [];
    }

    return array_merge($defaults, $args);
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_textarea_field(string $value): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $value);

    return trim(strip_tags($normalized));
}

function sanitize_email(string $email): string
{
    return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
}

function sanitize_key(string $key): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
}

function sanitize_file_name(string $name): string
{
    return preg_replace('/[^A-Za-z0-9\-\.]+/', '-', $name) ?? '';
}

function absint(mixed $value): int
{
    return (int) abs((int) $value);
}

function esc_url_raw(string $url): string
{
    return $url;
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url(string $url): string
{
    return filter_var($url, FILTER_SANITIZE_URL) ?: '';
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return $text;
}

function add_query_arg(array $args, string $url): string
{
    $parsed = parse_url($url) ?: [];
    $query  = [];
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }

    $query = array_merge($query, $args);
    $base  = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'example.test');
    if (!empty($parsed['path'])) {
        $base .= $parsed['path'];
    }

    return $base . '?' . http_build_query($query);
}

function home_url(string $path = '/', ?string $scheme = null): string
{
    $base = 'https://example.test';

    return $base . ($path === '/' ? '/' : '/' . ltrim($path, '/'));
}

function trailingslashit(string $value): string
{
    return rtrim($value, '/') . '/';
}

function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
{
    try {
        return json_encode($data, JSON_THROW_ON_ERROR | $options, $depth);
    } catch (Throwable) {
        return false;
    }
}

function wp_salt(string $scheme = 'auth'): string
{
    return 'testsalt-' . $scheme;
}

function current_time(string $type): string
{
    return match ($type) {
        'mysql' => '2024-05-01 19:00:00',
        default => '2024-05-01 19:00:00',
    };
}

function wp_strip_all_tags(string $text): string
{
    return strip_tags($text);
}

function wp_autop(string $text): string
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return '';
    }

    $paragraphs = preg_split("/\n\s*\n/", $trimmed) ?: [$trimmed];

    return implode("\n\n", array_map(
        static function (string $paragraph): string {
            return '<p>' . str_replace("\n", "<br />\n", trim($paragraph)) . '</p>';
        },
        $paragraphs
    ));
}

function wp_kses_allowed_html(string $context): array
{
    return [];
}

function wp_kses(string $text, array $allowedHtml = []): string
{
    return $text;
}

function wp_kses_post(string $text): string
{
    return $text;
}

function wp_mail(string $to, string $subject, string $message, array $headers = [], array $attachments = []): bool
{
    $GLOBALS['__wp_tests_mail_log'][] = compact('to', 'subject', 'message', 'headers', 'attachments');

    return true;
}

function as_schedule_single_action(int $timestamp, string $hook, array $args = [], array $options = []): void
{
    $GLOBALS['__wp_tests_actions'][] = ['schedule', $timestamp, $hook, $args, $options];
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    return $value;
}

function do_action(string $hook, mixed ...$args): void
{
    $GLOBALS['__wp_tests_actions'][] = [$hook, $args];

    if (!isset($GLOBALS['__wp_tests_hooks'][$hook])) {
        return;
    }

    ksort($GLOBALS['__wp_tests_hooks'][$hook]);

    foreach ($GLOBALS['__wp_tests_hooks'][$hook] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $callable     = $callback['callback'];
            $acceptedArgs = $callback['accepted_args'];
            $arguments    = $acceptedArgs > 0
                ? array_slice($args, 0, $acceptedArgs)
                : [];

            $callable(...$arguments);
        }
    }
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['__wp_tests_actions'][] = ['add_action', $hook, $priority];

    $GLOBALS['__wp_tests_hooks'][$hook][$priority][] = [
        'callback'      => $callback,
        'accepted_args' => $acceptedArgs,
    ];
}

function has_action(string $hook, mixed $callback = false): int|false
{
    if (!isset($GLOBALS['__wp_tests_hooks'][$hook])) {
        return false;
    }

    if ($callback === false) {
        $priorities = array_keys($GLOBALS['__wp_tests_hooks'][$hook]);
        sort($priorities);

        return $priorities[0] ?? false;
    }

    foreach ($GLOBALS['__wp_tests_hooks'][$hook] as $priority => $callbacks) {
        foreach ($callbacks as $stored) {
            if ($stored['callback'] === $callback) {
                return $priority;
            }
        }
    }

    return false;
}

function register_rest_route(string $namespace, string $route, array $args = []): void
{
    $GLOBALS['__wp_tests_actions'][] = ['register_rest_route', $namespace, $route, $args];
}

function wp_verify_nonce(string $nonce, string $action): bool
{
    return $nonce === 'valid-nonce' && $action === 'fp_resv_submit';
}

function rest_ensure_response(mixed $data): WP_REST_Response
{
    if ($data instanceof WP_REST_Response) {
        return $data;
    }

    return new WP_REST_Response($data);
}

function get_bloginfo(string $show): string
{
    return match ($show) {
        'name' => 'FP Restaurant',
        'admin_email' => 'restaurant@example.test',
        default => 'FP Restaurant',
    };
}

function determine_locale(): string
{
    return 'it_IT';
}

function get_locale(): string
{
    return 'it_IT';
}

function __(
    string $text,
    string $domain = 'default'
): string {
    return $text;
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $text, string $domain = 'default'): void
    {
        echo esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = 'default'): void
    {
        echo esc_html(__($text, $domain));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return $key ?? '';
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        $base = 'https://example.test/wp-admin/';

        return $base . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(string $key, string $value, ?string $url = null): string
    {
        $url = $url ?? 'https://example.test/';
        $parts = parse_url($url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query[$key] = $value;
        $parts['query'] = http_build_query($query);

        $scheme   = $parts['scheme'] ?? 'https';
        $host     = $parts['host'] ?? 'example.test';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '/';
        $queryStr = $parts['query'] ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $port . $path . $queryStr;
    }
}

function wp_nonce_field(string $action, string $name): void
{
    // no-op for tests
}

function get_transient(string $key): mixed
{
    return $GLOBALS['__wp_tests_transients'][$key]['value'] ?? false;
}

function set_transient(string $key, mixed $value, int $expiration): bool
{
    $GLOBALS['__wp_tests_transients'][$key] = [
        'value'      => $value,
        'expiration' => time() + $expiration,
    ];

    return true;
}

function delete_transient(string $key): void
{
    unset($GLOBALS['__wp_tests_transients'][$key]);
}

class WP_Error
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $data = []
    ) {
    }
}

class WP_REST_Response
{
    private int $status = 200;

    public function __construct(private mixed $data)
    {
    }

    public function set_status(int $status): void
    {
        $this->status = $status;
    }

    public function get_status(): int
    {
        return $this->status;
    }

    public function get_data(): mixed
    {
        return $this->data;
    }
}

class WP_REST_Request
{
    /** @var array<string, mixed> */
    private array $params;

    /** @var array<string, string> */
    private array $headers = [];

    /** @param array<string, mixed> $params */
    public function __construct(array $params = [], array $headers = [])
    {
        $this->params = $params;
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
    }

    public function get_param(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    public function get_json_params(): ?array
    {
        return $this->params;
    }

    public function get_header(string $key): ?string
    {
        $normalized = strtolower($key);

        return $this->headers[$normalized] ?? null;
    }
}

class WP_REST_Server
{
    public const READABLE = 'GET';
    public const CREATABLE = 'POST';
}

if (!function_exists('pll_current_language')) {
    function pll_current_language(?string $type = null): ?string
    {
        return null;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(string $value): string
    {
        return stripslashes($value);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return false;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return false;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return false;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        return 'valid-nonce';
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get(string $key, string $group = ''): mixed
    {
        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set(string $key, mixed $value, string $group = '', int $expire = 0): bool
    {
        return true;
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int
    {
        return rand($min, $max);
    }
}

if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr(string $key, int $offset = 1, string $group = ''): int|false
    {
        return false; // Force fallback to optimistic lock
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add(string $key, mixed $value, string $group = '', int $expire = 0): bool
    {
        return true;
    }
}

