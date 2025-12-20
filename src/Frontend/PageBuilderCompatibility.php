<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function class_exists;
use function do_shortcode;
use function error_log;
use function function_exists;
use function strpos;

/**
 * Gestisce la compatibilità con page builder (WPBakery, Elementor, etc.).
 * Estratto da WidgetController per migliorare la manutenibilità.
 */
final class PageBuilderCompatibility
{
    /**
     * Forza WPBakery a processare lo shortcode FP reservations.
     * WPBakery a volte non processa gli shortcode nei text blocks.
     */
    public function forceWPBakeryShortcodeProcessing(string $content): string
    {
        if (!$this->isWPBakeryActive()) {
            return $content;
        }
        
        if (strpos($content, '[fp_reservations') === false) {
            return $content;
        }
        
        error_log('[FP-RESV] WPBakery content filter - processing shortcode');
        
        return do_shortcode($content);
    }

    /**
     * Previene che WPBakery esegua escape HTML nell'output dello shortcode.
     */
    public function preventWPBakeryEscape(string $content, string $shortcodeTag): string
    {
        if (strpos($content, '[fp_reservations') !== false || strpos($content, 'fp-resv-widget') !== false) {
            error_log('[FP-RESV] WPBakery escape prevention - processing content');
            // WPBakery text blocks sometimes wrap content in esc_html, we prevent that
            return do_shortcode($content);
        }
        
        return $content;
    }

    /**
     * Verifica se WPBakery è attivo.
     */
    private function isWPBakeryActive(): bool
    {
        return class_exists('Vc_Manager') || function_exists('vc_is_page_editable');
    }
}















