<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use FP\Resv\Kernel\LegacyBridge;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use Throwable;
use function apply_filters;
use function defined;
use function do_action;
use function esc_html;
use function file_exists;
use function has_filter;
use function implode;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function remove_filter;
use function add_filter;
use function shortcode_atts;
use function str_replace;
use function trim;

/**
 * Gestisce il rendering degli shortcode del form di prenotazione.
 * Estratto da Shortcodes per migliorare la manutenibilità.
 */
final class ShortcodeRenderer
{
    /**
     * Render del form di prenotazione principale.
     *
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string
    {
        $debugMarker = $this->getDebugMarker();
        $removedFilters = $this->removeContentFilters();
        
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

            $context = $this->buildContext($atts);
            $output = $this->renderTemplate($context);
            
            $this->restoreContentFilters($removedFilters);
            
            return $debugMarker . $this->cleanOutput($output);
        } catch (Throwable $e) {
            $this->restoreContentFilters($removedFilters);
            
            $errorMsg = 'Errore nel rendering del form: ' . $e->getMessage();
            $errorMsg .= '<br>File: ' . basename($e->getFile()) . ' Linea: ' . $e->getLine();
            return $this->renderError($errorMsg);
        }
    }

    /**
     * Render del form per Gutenberg block.
     *
     * @param array<string, mixed> $attributes
     */
    public function renderBlock(array $attributes = []): string
    {
        $atts = [
            'location' => isset($attributes['location']) ? (string) $attributes['location'] : 'default',
            'lang'     => isset($attributes['language']) ? (string) $attributes['language'] : '',
            'form_id'  => isset($attributes['formId']) ? (string) $attributes['formId'] : '',
        ];

        return $this->render($atts);
    }

    /**
     * Render messaggio di errore.
     */
    public function renderError(string $message): string
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $html = '<div class="fp-resv-error" style="background: #fee; border: 2px solid #c33; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: sans-serif; max-width: 800px;">';
            $html .= '<h3 style="margin: 0 0 10px; color: #c33;">⚠️ FP Restaurant Reservations - Errore</h3>';
            $html .= '<p style="margin: 0;"><strong>Messaggio:</strong> ' . esc_html($message) . '</p>';
            $html .= '<p style="margin: 10px 0 0; font-size: 0.9em; color: #666;">Controlla i log PHP per maggiori dettagli. Per nascondere questo messaggio, disattiva WP_DEBUG.</p>';
            $html .= '</div>';
            return $html;
        }
        
        return '<!-- FP-RESV Error: ' . esc_html($message) . ' -->';
    }

    /**
     * Ottiene il marker di debug se WP_DEBUG è attivo.
     */
    private function getDebugMarker(): string
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return '<!-- FP-RESV Shortcode render() START -->' . "\n";
        }

        return '';
    }

    /**
     * Rimuove temporaneamente i filtri che potrebbero rompere l'HTML.
     *
     * @return array<int, string>
     */
    private function removeContentFilters(): array
    {
        $removedFilters = [];
        $filtersToRemove = ['wpautop', 'wptexturize', 'convert_chars'];
        
        foreach ($filtersToRemove as $filter) {
            if (has_filter('the_content', $filter)) {
                remove_filter('the_content', $filter);
                $removedFilters[] = $filter;
            }
        }
        
        return $removedFilters;
    }

    /**
     * Ripristina i filtri rimossi.
     *
     * @param array<int, string> $filters
     */
    private function restoreContentFilters(array $filters): void
    {
        foreach ($filters as $filter) {
            if ($filter === 'wpautop') {
                add_filter('the_content', 'wpautop');
            } elseif ($filter === 'wptexturize') {
                add_filter('the_content', 'wptexturize');
            } elseif ($filter === 'convert_chars') {
                add_filter('the_content', 'convert_chars');
            }
        }
    }

    /**
     * Costruisce il context per il template.
     *
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function buildContext(array $atts): array
    {
        $container = LegacyBridge::getContainer();
        if (!$container) {
            throw new \RuntimeException('Container non disponibile');
        }

        $options = $container->get(Options::class);
        if (!$options instanceof Options) {
            $options = new Options();
            $container->register(Options::class, $options);
        }

        $language = $container->get(Language::class);
        if (!$language instanceof Language) {
            $language = new Language($options);
            $container->register(Language::class, $language);
        }

        $phonePrefixProcessor = new PhonePrefixProcessor();
        $availableDaysExtractor = new AvailableDaysExtractor();
        $contextBuilder = new FormContext($options, $language, $phonePrefixProcessor, $availableDaysExtractor, [
            'location' => (string) $atts['location'],
            'lang'     => (string) $atts['lang'],
            'form_id'  => (string) $atts['form_id'],
        ]);

        $context = $contextBuilder->toArray();

        if (empty($context['config']) || empty($context['strings'])) {
            throw new \RuntimeException('Context incompleto');
        }

        /**
         * @param array<string, mixed> $context
         * @param array<string, mixed> $atts
         */
        return apply_filters('fp_resv_frontend_form_context', $context, $atts);
    }

    /**
     * Renderizza il template con il context fornito.
     *
     * @param array<string, mixed> $context
     */
    private function renderTemplate(array $context): string
    {
        $template = Plugin::$dir . 'templates/frontend/form.php';
        if (!file_exists($template)) {
            throw new \RuntimeException('Template non trovato: ' . $template);
        }

        // Fire tracking hook before rendering (form is about to be shown to the user)
        do_action('fp_resv_form_rendered', $context);

        ob_start();
        /** @var array<string, mixed> $context */
        include $template;
        $output = (string) ob_get_clean();

        if (empty(trim($output))) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debugMsg = '<div style="background:#fff3cd;border:2px solid #ffc107;padding:15px;margin:20px 0;border-radius:8px;font-family:sans-serif;">';
                $debugMsg .= '<strong>⚠️ FP Restaurant Reservations - Debug</strong><br>';
                $debugMsg .= 'Il template non ha prodotto output. Controlla i log PHP per maggiori dettagli.<br>';
                $debugMsg .= '<small>Context keys: ' . esc_html(implode(', ', array_keys($context))) . '</small>';
                $debugMsg .= '</div>';
                return $debugMsg;
            }
            
            throw new \RuntimeException('Il form non ha prodotto output');
        }

        return $output;
    }

    /**
     * Pulisce l'output rimuovendo tag indesiderati aggiunti da WordPress.
     */
    private function cleanOutput(string $output): string
    {
        // Rimuovi <p> e <br> aggiunti da wpautop
        $output = preg_replace('/<p>\s*<!--/', '<!--', $output);
        $output = preg_replace('/-->\s*<\/p>/', '-->', $output);
        $output = preg_replace('/-->\s*<br\s*\/?>/', '-->', $output);
        $output = preg_replace('/<p>\s*<\/p>/', '', $output);
        $output = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/', '', $output);
        
        // Rimuovi tutti i <br />
        $output = str_replace('<br />', '', $output);
        $output = str_replace('<br/>', '', $output);
        $output = str_replace('<br>', '', $output);
        $output = str_replace('<p></p>', '', $output);
        
        // Rimuovi <p> che wrappano i div del form
        $output = preg_replace('/<p>(\s*<div[^>]*class="[^"]*fp-resv[^"]*"[^>]*>)/', '$1', $output);
        $output = preg_replace('/(<\/div>\s*)<\/p>/', '$1', $output);
        
        // Rimuovi spazi multipli tra tag
        $output = preg_replace('/>\s+</', '><', $output);
        
        return $output;
    }
}















