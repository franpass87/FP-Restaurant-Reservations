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
        error_log('[FP-RESV-SHORTCODE] register() method called');
        add_shortcode('fp_reservations', [self::class, 'render']);
        error_log('[FP-RESV-SHORTCODE] add_shortcode("fp_reservations") executed');
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts = []): string
    {
        error_log('[FP-RESV] ========================================');
        error_log('[FP-RESV] Shortcode render() chiamato');
        error_log('[FP-RESV] Attributes: ' . print_r($atts, true));
        error_log('[FP-RESV] Current URL: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log('[FP-RESV] Is main query: ' . (function_exists('is_main_query') ? (is_main_query() ? 'YES' : 'NO') : 'N/A'));
        
        // Temporarily disable wpautop and other filters that might break HTML
        $removedFilters = [];
        $filtersToRemove = ['wpautop', 'wptexturize', 'convert_chars'];
        foreach ($filtersToRemove as $filter) {
            if (has_filter('the_content', $filter)) {
                remove_filter('the_content', $filter);
                $removedFilters[] = $filter;
            }
        }
        
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
            if (!$container) {
                error_log('[FP-RESV] CRITICAL: ServiceContainer not available');
                $errorMsg = 'ServiceContainer non disponibile. Il plugin potrebbe non essere completamente inizializzato.';
                return self::renderError($errorMsg);
            }
            error_log('[FP-RESV] ServiceContainer OK');
            
            $options = $container->get(Options::class);
            if (!$options instanceof Options) {
                error_log('[FP-RESV] WARNING: Options instance missing from container, creating fallback instance');
                $options = new Options();
                $container->register(Options::class, $options);
                $container->register('settings.options', $options);
            }
            error_log('[FP-RESV] Options caricati correttamente');

            $language = $container->get(Language::class);
            if (!$language instanceof Language) {
                error_log('[FP-RESV] Language not in container, creating new instance');
                $language = new Language($options);
                $container->register(Language::class, $language);
                $container->register('settings.language.runtime', $language);
            }

            error_log('[FP-RESV] Creating FormContext...');
            $contextBuilder = new FormContext($options, $language, [
                'location' => (string) $atts['location'],
                'lang'     => (string) $atts['lang'],
                'form_id'  => (string) $atts['form_id'],
            ]);

            error_log('[FP-RESV] Converting context to array...');
            $context = $contextBuilder->toArray();
            error_log('[FP-RESV] Context creato, keys: ' . implode(', ', array_keys($context)));
            
            // Verify context has required data
            if (empty($context['config']) || empty($context['strings'])) {
                error_log('[FP-RESV] CRITICAL: Context missing required data');
                error_log('[FP-RESV] Context dump: ' . print_r($context, true));
                return self::renderError('Context incompleto');
            }

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
                return self::renderError('Template non trovato: ' . $template);
            }
            error_log('[FP-RESV] Template trovato: ' . $template);

            error_log('[FP-RESV] Starting template rendering...');
            ob_start();
            /** @var array<string, mixed> $context */
            $context = $context;
            include $template;

            $output = (string) ob_get_clean();
            error_log('[FP-RESV] Template rendered, output length: ' . strlen($output));
            
            // Ensure output is not empty
            if (empty(trim($output))) {
                error_log('[FP-RESV] CRITICAL: Form rendering produced empty output');
                error_log('[FP-RESV] Context had ' . count($context) . ' keys');
                return self::renderError('Il form non ha prodotto output');
            }

            error_log('[FP-RESV] Form renderizzato correttamente, lunghezza output: ' . strlen($output));
            error_log('[FP-RESV] Output contiene fp-resv-widget: ' . (strpos($output, 'fp-resv-widget') !== false ? 'SI' : 'NO'));
            error_log('[FP-RESV] Output contiene data-fp-resv-app: ' . (strpos($output, 'data-fp-resv-app') !== false ? 'SI' : 'NO'));
            error_log('[FP-RESV] ========================================');
            
            // Re-add filters that were removed
            foreach ($removedFilters as $filter) {
                if ($filter === 'wpautop') {
                    add_filter('the_content', 'wpautop');
                } elseif ($filter === 'wptexturize') {
                    add_filter('the_content', 'wptexturize');
                } elseif ($filter === 'convert_chars') {
                    add_filter('the_content', 'convert_chars');
                }
            }
            
            return $output;
        } catch (\Throwable $e) {
            // Log error in development/debug mode
            error_log('[FP-RESV] ========================================');
            error_log('[FP-RESV] EXCEPTION CAUGHT!');
            error_log('[FP-RESV] Error rendering form: ' . $e->getMessage());
            error_log('[FP-RESV] Stack trace: ' . $e->getTraceAsString());
            error_log('[FP-RESV] File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            error_log('[FP-RESV] ========================================');
            
            // Re-add filters even on error
            foreach ($removedFilters as $filter) {
                if ($filter === 'wpautop') {
                    add_filter('the_content', 'wpautop');
                } elseif ($filter === 'wptexturize') {
                    add_filter('the_content', 'wptexturize');
                } elseif ($filter === 'convert_chars') {
                    add_filter('the_content', 'convert_chars');
                }
            }
            
            // Return visible error ALWAYS when there's an exception
            $errorMsg = 'Errore nel rendering del form: ' . $e->getMessage();
            $errorMsg .= '<br>File: ' . basename($e->getFile()) . ' Linea: ' . $e->getLine();
            return self::renderError($errorMsg);
        }
    }
    
    /**
     * Render error message with debug information
     */
    private static function renderError(string $message): string
    {
        error_log('[FP-RESV] Rendering error: ' . $message);
        
        // Show visible error only in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $html = '<div class="fp-resv-error" style="background: #fee; border: 2px solid #c33; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: sans-serif; max-width: 800px;">';
            $html .= '<h3 style="margin: 0 0 10px; color: #c33;">⚠️ FP Restaurant Reservations - Errore</h3>';
            $html .= '<p style="margin: 0;"><strong>Messaggio:</strong> ' . esc_html($message) . '</p>';
            $html .= '<p style="margin: 10px 0 0; font-size: 0.9em; color: #666;">Controlla i log PHP per maggiori dettagli. Per nascondere questo messaggio, disattiva WP_DEBUG.</p>';
            $html .= '</div>';
            return $html;
        }
        
        // In production, return HTML comment only
        return '<!-- FP-RESV Error: ' . esc_html($message) . ' -->';
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
