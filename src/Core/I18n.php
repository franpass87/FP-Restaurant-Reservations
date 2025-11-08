<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class I18n
{
    public static function init(): void
    {
        add_action('init', [self::class, 'loadTextDomain']);
    }

    public static function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'fp-restaurant-reservations',
            false,
            dirname(plugin_basename(Plugin::$file)) . '/languages'
        );
    }
}
