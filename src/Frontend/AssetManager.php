<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use WP_Post;
use function apply_filters;
use function error_log;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function get_post;
use function get_post_meta;
use function has_block;
use function has_shortcode;
use function is_admin;
use function is_embed;
use function is_singular;
use function str_contains;
use function str_replace;
use function strpos;
use function wp_add_inline_style;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

/**
 * Gestisce la registrazione e l'enqueue degli asset frontend.
 * Estratto da WidgetController per migliorare la manutenibilità.
 */
final class AssetManager
{
    private const HANDLE_MODULE = 'fp-resv-onepage-module';
    private const HANDLE_LEGACY = 'fp-resv-onepage';

    /**
     * Enqueue tutti gli asset necessari per il form.
     */
    public function enqueue(): void
    {
        if (!$this->shouldEnqueue()) {
            error_log('[FP-RESV-ASSETS] shouldEnqueue() returned FALSE - assets NOT loaded');
            return;
        }

        $version = Plugin::assetVersion();
        error_log('[FP-RESV-ASSETS] Enqueuing assets with version: ' . $version);

        $this->enqueueStyles($version);
        $this->enqueueScripts($version);
    }

    /**
     * Filtra il tag script per aggiungere type="module" o nomodule.
     */
    public function filterScriptTag(string $tag, string $handle, string $src): string
    {
        unset($src);

        if ($handle === self::HANDLE_MODULE) {
            if (str_contains($tag, 'type=')) {
                return $tag;
            }
            return str_replace('<script ', '<script type="module" ', $tag);
        }

        if ($handle === self::HANDLE_LEGACY) {
            if (str_contains($tag, 'nomodule')) {
                return $tag;
            }
            return str_replace('<script ', '<script nomodule ', $tag);
        }

        return $tag;
    }

    /**
     * Verifica se gli asset devono essere caricati.
     */
    private function shouldEnqueue(): bool
    {
        if (is_admin() || is_embed()) {
            return false;
        }

        $shouldEnqueue = false;
        
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                // Check for shortcode in post content
                if (has_shortcode($post->post_content, 'fp_reservations')) {
                    $shouldEnqueue = true;
                }
                
                // Check for Gutenberg block
                if (!$shouldEnqueue && function_exists('has_block') && has_block('fp-resv/reservations', $post)) {
                    $shouldEnqueue = true;
                }
                
                // Check for WPBakery/Elementor meta
                if (!$shouldEnqueue) {
                    $postMeta = get_post_meta($post->ID);
                    if ($postMeta) {
                        foreach ($postMeta as $key => $values) {
                            foreach ($values as $value) {
                                if (is_string($value) && strpos($value, '[fp_reservations') !== false) {
                                    $shouldEnqueue = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        /**
         * Allow third parties to control whether the frontend assets should load.
         *
         * @param bool          $shouldEnqueue Current decision based on shortcode/block detection.
         * @param WP_Post|null  $post          The current post object, when available.
         */
        $post = is_singular() ? get_post() : null;
        return (bool) apply_filters('fp_resv_frontend_should_enqueue', $shouldEnqueue, $post);
    }

    /**
     * Enqueue gli stili CSS.
     */
    private function enqueueStyles(string $version): void
    {
        // Enqueue Flatpickr CSS
        wp_register_style(
            'flatpickr',
            Plugin::$url . 'assets/vendor/flatpickr.min.css',
            [],
            '4.6.13',
            'all'
        );
        wp_enqueue_style('flatpickr');

        wp_register_style(
            'fp-resv-form',
            Plugin::$url . 'assets/css/form.css',
            ['flatpickr'],
            $version,
            'all'
        );
        wp_enqueue_style('fp-resv-form');
        
        // CSS del form-simple.php
        $cssPath = Plugin::$dir . 'assets/css/form-simple-inline.css';
        error_log('[FP-RESV] Tentativo caricamento CSS da: ' . $cssPath);
        
        if (file_exists($cssPath)) {
            $formSimpleCss = file_get_contents($cssPath);
            wp_add_inline_style('fp-resv-form', $formSimpleCss);
            error_log('[FP-RESV] CSS caricato! Lunghezza: ' . strlen($formSimpleCss) . ' caratteri');
        } else {
            error_log('[FP-RESV] ERRORE: File CSS NON trovato!');
        }
        
        // CSS inline per compatibilità WPBakery/Salient
        $inlineCss = '
        /* Isolation da WPBakery/Theme - SOLO regole essenziali */
        .vc_row .wpb_column .wpb_wrapper .fp-resv-widget,
        .vc_column_container .fp-resv-widget,
        .wpb_text_column .fp-resv-widget,
        .wpb_wrapper .fp-resv-widget,
        div.fp-resv-widget,
        #fp-resv-default {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            clear: both !important;
            position: relative !important;
        }
        /* Box-sizing universale */
        .fp-resv-widget,
        .fp-resv-widget *,
        .fp-resv-widget *::before,
        .fp-resv-widget *::after {
            box-sizing: border-box !important;
        }
        ';
        wp_add_inline_style('fp-resv-form', $inlineCss);
        
        // CSS aggiuntivo per sovrascrivere il tema Salient
        $overrideCss = '
        /* Override tema Salient per form semplice */
        .fp-resv-simple select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
            min-width: 140px !important;
        }
        
        .fp-resv-simple .fp-field div[style*="display: flex"] select {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
        }
        ';
        wp_add_inline_style('fp-resv-form', $overrideCss);
    }

    /**
     * Enqueue gli script JavaScript.
     */
    private function enqueueScripts(string $version): void
    {
        // Enqueue Flatpickr JS
        wp_register_script(
            'flatpickr',
            Plugin::$url . 'assets/vendor/flatpickr.min.js',
            [],
            '4.6.13',
            true
        );
        wp_enqueue_script('flatpickr');

        // Enqueue Flatpickr Italian locale
        wp_register_script(
            'flatpickr-it',
            Plugin::$url . 'assets/vendor/flatpickr-it.js',
            ['flatpickr'],
            '4.6.13',
            true
        );
        wp_enqueue_script('flatpickr-it');

        $modulePath = Plugin::$dir . 'assets/dist/fe/onepage.esm.js';
        $legacyPath = Plugin::$dir . 'assets/dist/fe/onepage.iife.js';

        $moduleUrl = Plugin::$url . 'assets/dist/fe/onepage.esm.js';
        $legacyUrl = Plugin::$url . 'assets/dist/fe/onepage.iife.js';

        $moduleExists = file_exists($modulePath);
        $legacyExists = file_exists($legacyPath);

        error_log('[FP-RESV-ASSETS] Module exists: ' . ($moduleExists ? 'YES' : 'NO'));
        error_log('[FP-RESV-ASSETS] Legacy exists: ' . ($legacyExists ? 'YES' : 'NO'));
        
        if ($moduleExists && $legacyExists) {
            error_log('[FP-RESV-ASSETS] Enqueueing BOTH module and legacy scripts');
            // Register and enqueue ES module version
            wp_register_script(
                self::HANDLE_MODULE,
                $moduleUrl,
                ['flatpickr', 'flatpickr-it'],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_MODULE);

            // Register and enqueue legacy version
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                ['flatpickr', 'flatpickr-it'],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_LEGACY);
        } elseif ($legacyExists) {
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                ['flatpickr', 'flatpickr-it'],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_LEGACY);
        } else {
            // Fall back to development version
            $fallbackUrl = Plugin::$url . 'assets/js/fe/form-app-fallback.js';
            
            wp_register_script(
                self::HANDLE_MODULE,
                $fallbackUrl,
                ['flatpickr', 'flatpickr-it'],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_MODULE);
        }
    }
}















