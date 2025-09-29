<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function add_action;

final class WidgetController
{
    public function boot(): void
    {
        add_action('init', [Shortcodes::class, 'register']);
        add_action('init', [Gutenberg::class, 'register']);

        Elementor::register();
    }
}
