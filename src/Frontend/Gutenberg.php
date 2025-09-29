<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use function register_block_type;
use function wp_register_script;
use function function_exists;

final class Gutenberg
{
    public static function register(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'fp-resv-block-form',
            Plugin::$url . 'assets/js/admin/block-reservation.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-i18n',
                'wp-block-editor',
                'wp-server-side-render',
            ],
            Plugin::VERSION,
            true
        );

        register_block_type('fp-restaurant-reservations/form', [
            'api_version'     => 2,
            'editor_script'   => 'fp-resv-block-form',
            'render_callback' => [Shortcodes::class, 'renderBlock'],
            'attributes'      => [
                'location' => [
                    'type'    => 'string',
                    'default' => 'default',
                ],
                'language' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'formId'   => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ],
            'supports'       => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
            ],
        ]);
    }
}
