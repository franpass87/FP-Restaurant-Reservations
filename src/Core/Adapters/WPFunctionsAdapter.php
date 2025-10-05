<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

use function add_option;
use function apply_filters;
use function current_time;
use function delete_option;
use function delete_transient;
use function do_action;
use function get_option;
use function get_transient;
use function set_transient;
use function update_option;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_incr;
use function wp_cache_set;
use function wp_salt;

final class WPFunctionsAdapter implements WordPressAdapter
{
    public function getCurrentTime(string $type = 'mysql'): string
    {
        return current_time($type);
    }

    public function getSalt(string $scheme): string
    {
        return wp_salt($scheme);
    }

    public function getTransient(string $key): mixed
    {
        return get_transient($key);
    }

    public function setTransient(string $key, mixed $value, int $expiration): bool
    {
        return set_transient($key, $value, $expiration);
    }

    public function deleteTransient(string $key): bool
    {
        return delete_transient($key);
    }

    public function cacheGet(string $key, string $group = ''): mixed
    {
        return wp_cache_get($key, $group);
    }

    public function cacheSet(string $key, mixed $value, string $group = '', int $expiration = 0): bool
    {
        return wp_cache_set($key, $value, $group, $expiration);
    }

    public function cacheDelete(string $key, string $group = ''): bool
    {
        return wp_cache_delete($key, $group);
    }

    public function cacheIncr(string $key, int $offset = 1, string $group = ''): int|false
    {
        if (function_exists('wp_cache_incr')) {
            return wp_cache_incr($key, $offset, $group);
        }

        return false;
    }

    public function getOption(string $option, mixed $default = false): mixed
    {
        return get_option($option, $default);
    }

    public function updateOption(string $option, mixed $value, bool $autoload = null): bool
    {
        return update_option($option, $value, $autoload);
    }

    public function addOption(string $option, mixed $value, string $deprecated = '', bool $autoload = true): bool
    {
        return add_option($option, $value, $deprecated, $autoload);
    }

    public function deleteOption(string $option): bool
    {
        return delete_option($option);
    }

    public function doAction(string $hook_name, mixed ...$args): void
    {
        do_action($hook_name, ...$args);
    }

    public function applyFilters(string $hook_name, mixed $value, mixed ...$args): mixed
    {
        return apply_filters($hook_name, $value, ...$args);
    }
}
