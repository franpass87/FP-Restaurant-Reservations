<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use WP_Post;
use function add_action;
use function add_filter;
use function apply_filters;
use function current_user_can;
use function defined;
use function do_shortcode;
use function error_log;
use function file_exists;
use function function_exists;
use function get_post;
use function has_block;
use function has_shortcode;
use function in_the_loop;
use function is_admin;
use function is_embed;
use function is_main_query;
use function is_singular;
use function str_contains;
use function strpos;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

final class WidgetController
{
    private const HANDLE_MODULE = 'fp-resv-onepage-module';
    private const HANDLE_LEGACY = 'fp-resv-onepage';

    public function boot(): void
    {
        // Shortcodes are now registered in Plugin::onPluginsLoaded() via init hook
        add_action('init', [Gutenberg::class, 'register']);

        Elementor::register();
        
        // Force WPBakery to process our shortcode in text blocks
        add_filter('the_content', [$this, 'forceWPBakeryShortcodeProcessing'], 1);
        
        // WPBakery specific hooks - prevent HTML escaping for our shortcode
        add_filter('vc_shortcode_content', [$this, 'forceWPBakeryShortcodeProcessing'], 1);
        add_filter('vc_raw_html_content', [$this, 'forceWPBakeryShortcodeProcessing'], 1);
        add_filter('wpb_js_composer_shortcode_content', [$this, 'preventWPBakeryEscape'], 10, 2);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('script_loader_tag', [$this, 'filterScriptTag'], 10, 3);
        
        // Force shortcode execution if content contains it but theme doesn't process it
        add_filter('the_content', [$this, 'forceShortcodeExecution'], 999);
        
        // Add debug hook to track shortcode execution
        add_action('wp_footer', [$this, 'debugShortcodeExecution'], 999);
    }
    
    /**
     * Force shortcode execution even if theme doesn't call the_content() properly
     */
    public function forceShortcodeExecution(string $content): string
    {
        // Only process if we're in the main query and not in admin
        if (is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        // If content contains shortcode but it hasn't been processed, force it
        if (strpos($content, '[fp_reservations') !== false && strpos($content, 'fp-resv-widget') === false) {
            error_log('[FP-RESV] Forcing shortcode execution in content filter');
            $content = do_shortcode($content);
        }
        
        return $content;
    }
    
    /**
     * Debug: Check if shortcode was executed and output debug info
     */
    public function debugShortcodeExecution(): void
    {
        global $post;
        
        // Only run on singular pages
        if (!is_singular() || !$post) {
            return;
        }
        
        // Check if content has shortcode
        $hasShortcode = strpos($post->post_content, '[fp_reservations') !== false;
        
        if ($hasShortcode) {
            error_log('[FP-RESV] DEBUG: Page "' . $post->post_title . '" (ID: ' . $post->ID . ') contains shortcode');
            error_log('[FP-RESV] DEBUG: Post type: ' . $post->post_type);
            error_log('[FP-RESV] DEBUG: Checking if form was rendered...');
        }
    }

    public function enqueueAssets(): void
    {
        if (!$this->shouldEnqueueAssets()) {
            error_log('[FP-RESV-ASSETS] shouldEnqueueAssets() returned FALSE - assets NOT loaded');
            return;
        }

        $version = Plugin::assetVersion();
        error_log('[FP-RESV-ASSETS] Enqueuing assets with version: ' . $version);

        wp_register_style(
            'fp-resv-form',
            Plugin::$url . 'assets/css/form.css',
            [],
            $version,
            'all'
        );

        wp_enqueue_style('fp-resv-form');
        
        // Inline critical CSS per evitare FOUC e conflitti con WPBakery
        $inlineCss = '
        /* Main widget container */
        .fp-resv-widget { 
            display: block !important; 
            visibility: visible !important; 
            opacity: 1 !important;
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            clear: both !important;
            position: relative !important;
            float: none !important;
            text-align: left !important;
        }
        .fp-resv-widget *,
        .fp-resv-widget *::before,
        .fp-resv-widget *::after {
            box-sizing: border-box !important;
        }
        /* Reset buttons and form elements */
        .fp-resv-widget button,
        .fp-resv-widget input,
        .fp-resv-widget select,
        .fp-resv-widget textarea {
            font-family: inherit !important;
            margin: 0 !important;
        }
        .fp-resv-widget button {
            text-align: center !important;
        }
        /* Reset WPBakery/VC styles */
        .vc_row .fp-resv-widget,
        .vc_column_container .fp-resv-widget,
        .wpb_text_column .fp-resv-widget,
        .wpb_wrapper .fp-resv-widget {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        ';
        wp_add_inline_style('fp-resv-form', $inlineCss);

        $modulePath = Plugin::$dir . 'assets/dist/fe/onepage.esm.js';
        $legacyPath = Plugin::$dir . 'assets/dist/fe/onepage.iife.js';

        $moduleUrl = Plugin::$url . 'assets/dist/fe/onepage.esm.js';
        $legacyUrl = Plugin::$url . 'assets/dist/fe/onepage.iife.js';

        $moduleExists = file_exists($modulePath);
        $legacyExists = file_exists($legacyPath);

        // Load both module and legacy scripts for proper browser support
        // Modern browsers load the module version, older browsers load the legacy version
        error_log('[FP-RESV-ASSETS] Module exists: ' . ($moduleExists ? 'YES' : 'NO'));
        error_log('[FP-RESV-ASSETS] Legacy exists: ' . ($legacyExists ? 'YES' : 'NO'));
        
        if ($moduleExists && $legacyExists) {
            error_log('[FP-RESV-ASSETS] Enqueueing BOTH module and legacy scripts');
            // Register and enqueue ES module version for modern browsers
            wp_register_script(
                self::HANDLE_MODULE,
                $moduleUrl,
                [],
                $version,
                true // Load in footer
            );
            wp_enqueue_script(self::HANDLE_MODULE);

            // Register and enqueue legacy version for older browsers
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                [],
                $version,
                true // Load in footer
            );
            wp_enqueue_script(self::HANDLE_LEGACY);
        } elseif ($legacyExists) {
            // If only legacy exists, use it without nomodule attribute
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                [],
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
                [],
                $version,
                true
            );
            wp_enqueue_script(self::HANDLE_MODULE);
        }
    }

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

    private function shouldEnqueueAssets(): bool
    {
        // Never load in admin or embeds
        if (is_admin() || is_embed()) {
            return false;
        }

        // Always load assets in frontend by default
        // The JavaScript will check if there are widgets to initialize
        // This prevents white screen issues and ensures the shortcode always works
        $shouldEnqueue = true;

        /**
         * Allow third parties to control whether the frontend assets should load.
         *
         * @param bool          $shouldEnqueue Current decision (default: true in frontend).
         * @param WP_Post|null  $post          The current post object, when available.
         */
        $post = is_singular() ? get_post() : null;
        $shouldEnqueue = (bool) apply_filters('fp_resv_frontend_should_enqueue', $shouldEnqueue, $post);

        return $shouldEnqueue;
    }

    /**
     * Force WPBakery to process FP reservations shortcode
     * WPBakery sometimes doesn't process shortcodes in text blocks
     */
    public function forceWPBakeryShortcodeProcessing(string $content): string
    {
        // Only process if WPBakery is active and content contains our shortcode
        if (!class_exists('Vc_Manager') && !function_exists('vc_is_page_editable')) {
            return $content;
        }
        
        if (strpos($content, '[fp_reservations') === false) {
            return $content;
        }
        
        error_log('[FP-RESV] WPBakery content filter - processing shortcode');
        
        // Process shortcode explicitly
        return do_shortcode($content);
    }

    /**
     * Prevent WPBakery from escaping HTML in our shortcode output
     */
    public function preventWPBakeryEscape(string $content, string $shortcodeTag): string
    {
        // Only for text blocks that might contain our shortcode
        if (strpos($content, '[fp_reservations') !== false || strpos($content, 'fp-resv-widget') !== false) {
            error_log('[FP-RESV] WPBakery escape prevention - processing content');
            // WPBakery text blocks sometimes wrap content in esc_html, we prevent that
            return do_shortcode($content);
        }
        return $content;
    }
}
