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
        error_log('[FP-RESV] Shortcode render() chiamato');
        try {
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
                error_log('[FP-RESV] CRITICAL: Options instance not available');
                return '<!-- FP-RESV: Options not available -->';
            }
            error_log('[FP-RESV] Options caricati correttamente');

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
            error_log('[FP-RESV] Context creato, keys: ' . implode(', ', array_keys($context)));

            /**
             * Allow third parties to adjust the frontend context before rendering.
             *
             * @param array<string, mixed> $context
             * @param array<string, mixed> $atts
             */
            $context = apply_filters('fp_resv_frontend_form_context', $context, $atts);

            $template = Plugin::$dir . 'templates/frontend/form.php';
            if (!file_exists($template)) {
                error_log('[FP-RESV] CRITICAL: Template not found at: ' . $template);
                return '<!-- FP-RESV: Template not found -->';
            }
            error_log('[FP-RESV] Template trovato: ' . $template);

            ob_start();
            /** @var array<string, mixed> $context */
            $context = $context;
            include $template;

            $output = (string) ob_get_clean();
            
            // Ensure output is not empty
            if (empty(trim($output))) {
                error_log('[FP-RESV] CRITICAL: Form rendering produced empty output');
                return '<!-- FP-RESV: Form rendering produced empty output. Check if context data is properly configured. -->';
            }

            error_log('[FP-RESV] Form renderizzato correttamente, lunghezza output: ' . strlen($output));
            return $output;
        } catch (\Throwable $e) {
            // Log error in development/debug mode
            error_log('[FP-RESV] Error rendering form: ' . $e->getMessage());
            error_log('[FP-RESV] Stack trace: ' . $e->getTraceAsString());
            
            // Return HTML comment to help debugging
            return '<!-- FP-RESV: Error rendering form: ' . esc_html($e->getMessage()) . ' -->';
        }
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
