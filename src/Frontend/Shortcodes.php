<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\ServiceContainer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use function add_shortcode;
use function apply_filters;
use function file_exists;
use function ob_get_clean;
use function ob_start;
use function shortcode_atts;

final class Shortcodes
{
    public static function register(): void
    {
        add_shortcode('fp_reservations', [self::class, 'render']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts = []): string
    {
        $atts = shortcode_atts(
            [
                'location' => 'default',
                'lang'     => '',
                'form_id'  => '',
            ],
            $atts,
            'fp_reservations'
        );

        $container = ServiceContainer::getInstance();
        $options   = $container->get(Options::class);
        if (!$options instanceof Options) {
            return '';
        }

        $language = $container->get(Language::class);
        if (!$language instanceof Language) {
            $language = new Language($options);
        }

        $contextBuilder = new FormContext($options, $language, [
            'location' => (string) $atts['location'],
            'lang'     => (string) $atts['lang'],
            'form_id'  => (string) $atts['form_id'],
        ]);

        $context = $contextBuilder->toArray();

        /**
         * Allow third parties to adjust the frontend context before rendering.
         *
         * @param array<string, mixed> $context
         * @param array<string, mixed> $atts
         */
        $context = apply_filters('fp_resv_frontend_form_context', $context, $atts);

        $template = Plugin::$dir . 'templates/frontend/form.php';
        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        /** @var array<string, mixed> $context */
        $context = $context;
        include $template;

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderBlock(array $attributes = [], string $content = ''): string
    {
        unset($content);

        $atts = [
            'location' => isset($attributes['location']) ? (string) $attributes['location'] : 'default',
            'lang'     => isset($attributes['language']) ? (string) $attributes['language'] : '',
            'form_id'  => isset($attributes['formId']) ? (string) $attributes['formId'] : '',
        ];

        return self::render($atts);
    }
}
