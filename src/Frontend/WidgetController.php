<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use WP_Post;
use function add_action;
use function add_filter;
use function apply_filters;
use function file_exists;
use function function_exists;
use function get_post;
use function has_block;
use function has_shortcode;
use function is_admin;
use function is_embed;
use function is_singular;
use function str_contains;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

final class WidgetController
{
    private const HANDLE_MODULE = 'fp-resv-onepage-module';
    private const HANDLE_LEGACY = 'fp-resv-onepage';

    public function boot(): void
    {
        add_action('init', [Shortcodes::class, 'register']);
        add_action('init', [Gutenberg::class, 'register']);

        Elementor::register();
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('script_loader_tag', [$this, 'filterScriptTag'], 10, 3);
    }

    public function enqueueAssets(): void
    {
        if (!$this->shouldEnqueueAssets()) {
            return;
        }

        $version = Plugin::assetVersion();

        wp_register_style(
            'fp-resv-form',
            Plugin::$url . 'assets/css/form.css',
            [],
            $version
        );

        wp_enqueue_style('fp-resv-form');

        $modulePath = Plugin::$dir . 'assets/dist/fe/onepage.esm.js';
        $legacyPath = Plugin::$dir . 'assets/dist/fe/onepage.iife.js';

        $moduleUrl = Plugin::$url . 'assets/dist/fe/onepage.esm.js';
        $legacyUrl = Plugin::$url . 'assets/dist/fe/onepage.iife.js';

        $moduleExists = file_exists($modulePath);
        $legacyExists = file_exists($legacyPath);

        // Load both module and legacy scripts for proper browser support
        // Modern browsers load the module version, older browsers load the legacy version
        if ($moduleExists && $legacyExists) {
            // Register and enqueue ES module version for modern browsers
            wp_register_script(
                self::HANDLE_MODULE,
                $moduleUrl,
                [],
                $version,
                true // Load in footer
            );
            wp_enqueue_script(self::HANDLE_MODULE);

            // Register and enqueue legacy version for older browsers
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                [],
                $version,
                true // Load in footer
            );
            wp_enqueue_script(self::HANDLE_LEGACY);
        } elseif ($legacyExists) {
            // If only legacy exists, use it without nomodule attribute
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                [],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_LEGACY);
        } else {
            // Fall back to development version
            $fallbackUrl = Plugin::$url . 'assets/js/fe/form-app-fallback.js';
            
            wp_register_script(
                self::HANDLE_MODULE,
                $fallbackUrl,
                [],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_MODULE);
        }
    }

    public function filterScriptTag(string $tag, string $handle, string $src): string
    {
        unset($src);

        if ($handle === self::HANDLE_MODULE) {
            if (str_contains($tag, 'type=')) {
                return $tag;
            }

            return str_replace('<script ', '<script type="module" ', $tag);
        }

        if ($handle === self::HANDLE_LEGACY) {
            if (str_contains($tag, 'nomodule')) {
                return $tag;
            }

            return str_replace('<script ', '<script nomodule ', $tag);
        }

        return $tag;
    }

    private function shouldEnqueueAssets(): bool
    {
        // Never load in admin or embeds
        if (is_admin() || is_embed()) {
            return false;
        }

        // Always load assets in frontend by default
        // The JavaScript will check if there are widgets to initialize
        // This prevents white screen issues and ensures the shortcode always works
        $shouldEnqueue = true;

        /**
         * Allow third parties to control whether the frontend assets should load.
         *
         * @param bool          $shouldEnqueue Current decision (default: true in frontend).
         * @param WP_Post|null  $post          The current post object, when available.
         */
        $post = is_singular() ? get_post() : null;
        $shouldEnqueue = (bool) apply_filters('fp_resv_frontend_should_enqueue', $shouldEnqueue, $post);

        return $shouldEnqueue;
    }
}
