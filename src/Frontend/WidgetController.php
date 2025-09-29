<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use function add_action;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_script_add_data;

final class WidgetController
{
    public function boot(): void
    {
        add_action('init', [Shortcodes::class, 'register']);
        add_action('init', [Gutenberg::class, 'register']);

        Elementor::register();
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_style(
            'fp-resv-form',
            Plugin::$url . 'assets/css/form.css',
            [],
            Plugin::VERSION
        );

        wp_enqueue_script(
            'fp-resv-onepage',
            Plugin::$url . 'assets/js/fe/onepage.js',
            [],
            Plugin::VERSION,
            true
        );

        if (function_exists('wp_script_add_data')) {
            wp_script_add_data('fp-resv-onepage', 'type', 'module');
        }
    }
}
