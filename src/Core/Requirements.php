<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function __;
use function add_action;
use function esc_html__;
use function esc_html;
use function error_log;
use function defined;
use function extension_loaded;
use function is_admin;
use function sprintf;
use function version_compare;

final class Requirements
{
    private const MIN_PHP = '8.1';
    private const MIN_WORDPRESS = '6.5';

    /**
     * Validate runtime requirements before booting the plugin.
     */
    public static function validate(): bool
    {
        $issues = [];

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $issues[] = sprintf(
                /* translators: 1: Detected PHP version, 2: Minimum supported version. */
                __('Versione PHP rilevata: %1$s. È richiesta la versione %2$s o superiore.', 'fp-restaurant-reservations'),
                PHP_VERSION,
                self::MIN_PHP
            );
        }

        global $wp_version;
        if (isset($wp_version) && version_compare($wp_version, self::MIN_WORDPRESS, '<')) {
            $issues[] = sprintf(
                /* translators: 1: Detected WordPress version, 2: Minimum supported version. */
                __('Versione di WordPress rilevata: %1$s. È richiesta la versione %2$s o superiore.', 'fp-restaurant-reservations'),
                $wp_version,
                self::MIN_WORDPRESS
            );
        }

        foreach (self::requiredExtensions() as $extension => $label) {
            if (!extension_loaded($extension)) {
                $issues[] = sprintf(
                    /* translators: %s: PHP extension name. */
                    __('Estensione PHP mancante: %s.', 'fp-restaurant-reservations'),
                    $label
                );
            }
        }

        if ($issues === []) {
            return true;
        }

        if (defined('WP_CLI') && WP_CLI) {
            foreach ($issues as $issue) {
                \WP_CLI::warning($issue);
            }
        }

        if (is_admin()) {
            add_action('admin_notices', static function () use ($issues): void {
                echo '<div class="notice notice-error"><p>' . esc_html__(
                    'FP Restaurant Reservations non può essere attivato perché l\'ambiente non soddisfa i requisiti minimi:',
                    'fp-restaurant-reservations'
                ) . '</p><ul>';

                foreach ($issues as $issue) {
                    echo '<li>' . esc_html($issue) . '</li>';
                }

                echo '</ul></div>';
            });
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            foreach ($issues as $issue) {
                error_log('[FP Restaurant Reservations] ' . $issue); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private static function requiredExtensions(): array
    {
        return [
            // 'curl'     => 'cURL', // Opzionale - solo per integrazioni esterne
            'json'     => 'JSON',
            'mbstring' => 'mbstring',
            'ctype'    => 'ctype',
        ];
    }
}

