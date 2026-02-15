<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function add_action;
use function add_filter;
use function error_log;
use function is_singular;
use function strpos;

final class WidgetController
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly CriticalCssManager $cssManager,
        private readonly PageBuilderCompatibility $pageBuilderCompat,
        private readonly ContentFilter $contentFilter
    ) {
    }

    public function boot(): void
    {
        // Shortcodes are now registered in Plugin::onPluginsLoaded() via init hook
        add_action('init', [Gutenberg::class, 'register']);
        Elementor::register();
        
        // WPBakery compatibility
        add_filter('the_content', [$this->pageBuilderCompat, 'forceWPBakeryShortcodeProcessing'], 1);
        add_filter('vc_shortcode_content', [$this->pageBuilderCompat, 'forceWPBakeryShortcodeProcessing'], 1);
        add_filter('vc_raw_html_content', [$this->pageBuilderCompat, 'forceWPBakeryShortcodeProcessing'], 1);
        add_filter('wpb_js_composer_shortcode_content', [$this->pageBuilderCompat, 'preventWPBakeryEscape'], 10, 2);
        
        // Asset management (solo CSS — JS caricato dal template form-simple.php)
        add_action('wp_enqueue_scripts', [$this->assetManager, 'enqueue'], 999);
        add_action('wp_head', [$this->cssManager, 'render'], 9999);
        
        // Content filtering
        add_filter('the_content', [$this->contentFilter, 'forceShortcodeExecution'], 999);
        
        // Debug hook
        add_action('wp_footer', [$this, 'debugShortcodeExecution'], 999);
    }
    
    /**
     * Debug: Check if shortcode was executed and output debug info
     */
    public function debugShortcodeExecution(): void
    {
        global $post;
        
        if (!is_singular() || !$post) {
            return;
        }
        
        $hasShortcode = strpos($post->post_content, '[fp_reservations') !== false;
        
        if ($hasShortcode) {
            error_log('[FP-RESV] DEBUG: Page "' . $post->post_title . '" (ID: ' . $post->ID . ') contains shortcode');
            error_log('[FP-RESV] DEBUG: Post type: ' . $post->post_type);
            error_log('[FP-RESV] DEBUG: Checking if form was rendered...');
        }
    }
}
