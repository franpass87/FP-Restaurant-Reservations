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

        $version = Plugin::VERSION . '.' . filemtime(Plugin::$dir . 'assets/css/form.css');

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

        // Force use of IIFE version for better compatibility
        if ($legacyExists) {
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                [],
                Plugin::VERSION . '.' . filemtime($legacyPath),
                true
            );

            wp_enqueue_script(self::HANDLE_LEGACY);
        } elseif (!$moduleExists) {
            // Fall back to the development module if the build artefact is missing.
            $moduleUrl = Plugin::$url . 'assets/js/fe/onepage.js';
            
            wp_register_script(
                self::HANDLE_MODULE,
                $moduleUrl,
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
        if (is_admin() || is_embed()) {
            return false;
        }

        $post = null;
        $shouldEnqueue = false;

        if (is_singular()) {
            $post = get_post();
            if ($post instanceof WP_Post) {
                if (has_shortcode($post->post_content, 'fp_reservations')) {
                    $shouldEnqueue = true;
                }

                if (function_exists('has_block') && has_block('fp-restaurant-reservations/form', $post)) {
                    $shouldEnqueue = true;
                }
            }
        }

        /**
         * Allow third parties to control whether the frontend assets should load.
         *
         * @param bool          $shouldEnqueue Current decision.
         * @param WP_Post|null  $post          The resolved post object, when available.
         */
        $shouldEnqueue = (bool) apply_filters('fp_resv_frontend_should_enqueue', $shouldEnqueue, $post);

        return $shouldEnqueue;
    }
}
