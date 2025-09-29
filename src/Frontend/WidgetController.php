<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use function add_action;
use function wp_enqueue_style;

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
    }
}
