<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class I18n
{
    public static function init(): void
    {
        // Carica il textdomain su plugins_loaded invece di init per evitare
        // caricamenti just-in-time che WordPress 6.7+ segnala come errori
        add_action('plugins_loaded', [self::class, 'loadTextDomain'], 0);
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
