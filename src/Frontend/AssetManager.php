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
use function strpos;
use function wp_add_inline_style;
use function wp_enqueue_style;
use function wp_register_style;

/**
 * Gestisce la registrazione e l'enqueue degli asset frontend (solo CSS).
 *
 * Gli script JS sono caricati direttamente dal template form-simple.php
 * tramite tag <script> inline (form-simple.js, flatpickr, ecc.).
 */
final class AssetManager
{
    /**
     * Enqueue gli stili CSS necessari per il form.
     */
    public function enqueue(): void
    {
        if (!$this->shouldEnqueue()) {
            return;
        }

        $this->enqueueStyles(Plugin::assetVersion());
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
                if (has_shortcode($post->post_content, 'fp_reservations')) {
                    $shouldEnqueue = true;
                }

                if (!$shouldEnqueue && function_exists('has_block') && has_block('fp-resv/reservations', $post)) {
                    $shouldEnqueue = true;
                }

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

        /** @var WP_Post|null $post */
        $post = is_singular() ? get_post() : null;
        return (bool) apply_filters('fp_resv_frontend_should_enqueue', $shouldEnqueue, $post);
    }

    /**
     * Enqueue gli stili CSS.
     */
    private function enqueueStyles(string $version): void
    {
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
        if (file_exists($cssPath)) {
            $formSimpleCss = file_get_contents($cssPath);
            wp_add_inline_style('fp-resv-form', $formSimpleCss);
        }

        // CSS inline per compatibilità WPBakery/Salient
        $inlineCss = '
        .vc_row .wpb_column .wpb_wrapper .fp-resv-widget,
        .vc_column_container .fp-resv-widget,
        .wpb_text_column .fp-resv-widget,
        .wpb_wrapper .fp-resv-widget,
        div.fp-resv-widget,
        .fp-resv-simple,
        #fp-resv-default {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            clear: both !important;
            position: relative !important;
        }
        .fp-resv-simple,
        .fp-resv-simple *,
        .fp-resv-simple *::before,
        .fp-resv-simple *::after {
            box-sizing: border-box !important;
        }
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
        wp_add_inline_style('fp-resv-form', $inlineCss);
    }
}
