<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use WP_Post;
use function add_action;
use function add_filter;
use function apply_filters;
use function file_exists;
use function function_exists;
use function get_post;
use function has_block;
use function has_shortcode;
use function is_admin;
use function is_embed;
use function is_singular;
use function str_contains;
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
        add_action('init', [Shortcodes::class, 'register']);
        add_action('init', [Gutenberg::class, 'register']);

        Elementor::register();
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('script_loader_tag', [$this, 'filterScriptTag'], 10, 3);
    }

    public function enqueueAssets(): void
    {
        if (!$this->shouldEnqueueAssets()) {
            return;
        }

        $version = Plugin::assetVersion();

        wp_register_style(
            'fp-resv-form',
            Plugin::$url . 'assets/css/form.css',
            [],
            $version
        );

        wp_enqueue_style('fp-resv-form');

        $modulePath = Plugin::$dir . 'assets/dist/fe/onepage.esm.js';
        $legacyPath = Plugin::$dir . 'assets/dist/fe/onepage.iife.js';

        $moduleUrl = Plugin::$url . 'assets/dist/fe/onepage.esm.js';
        $legacyUrl = Plugin::$url . 'assets/dist/fe/onepage.iife.js';

        $moduleExists = file_exists($modulePath);
        $legacyExists = file_exists($legacyPath);

        // AGGRESSIVE FIX: Force JavaScript loading with multiple fallbacks
        if ($legacyExists) {
            // Try multiple loading strategies
            wp_register_script(
                self::HANDLE_LEGACY,
                $legacyUrl,
                [],
                $version, // Use same version as CSS for consistent cache busting
                false // Load in header, not footer
            );

            wp_enqueue_script(self::HANDLE_LEGACY);
            
            // Add aggressive inline initialization with multiple fallbacks
            add_action('wp_footer', function() use ($legacyUrl, $version) {
                echo '<script>
                console.log("[FP-RESV] AGGRESSIVE initialization starting...");
                
                // Strategy 1: Direct script injection if wp_enqueue fails
                function injectScriptDirectly() {
                    const script = document.createElement("script");
                    script.src = "' . esc_js($legacyUrl) . '?v=' . esc_js($version) . '";
                    script.onload = function() {
                        console.log("[FP-RESV] Script loaded directly, initializing...");
                        initializeFPResv();
                    };
                    script.onerror = function() {
                        console.error("[FP-RESV] Direct script injection failed");
                        // Fallback to development version
                        const devScript = document.createElement("script");
                        devScript.src = "' . esc_js(Plugin::$url . 'assets/js/fe/form-app-fallback.js') . '?v=' . esc_js($version) . '";
                        devScript.onload = function() {
                            console.log("[FP-RESV] Development script loaded");
                            initializeFPResv();
                        };
                        document.head.appendChild(devScript);
                    };
                    document.head.appendChild(script);
                }
                
                function initializeFPResv() {
                    console.log("[FP-RESV] Attempting initialization...");
                    
                    if (window.FPResv && window.FPResv.FormApp) {
                        console.log("[FP-RESV] SUCCESS: FPResv found, initializing widgets");
                        const widgets = document.querySelectorAll("[data-fp-resv]");
                        console.log("[FP-RESV] Found widgets:", widgets.length);
                        
                        widgets.forEach(function(widget) {
                            try {
                                new window.FPResv.FormApp(widget);
                                console.log("[FP-RESV] Widget initialized:", widget.id || "unnamed");
                            } catch (error) {
                                console.error("[FP-RESV] Error initializing widget:", error);
                            }
                        });
                    } else {
                        console.log("[FP-RESV] FPResv not available, trying direct injection...");
                        injectScriptDirectly();
                    }
                }
                
                // Start with immediate check
                setTimeout(function() {
                    if (window.FPResv && window.FPResv.FormApp) {
                        initializeFPResv();
                    } else {
                        injectScriptDirectly();
                    }
                }, 100);
                </script>';
            });
        } else {
            // Fall back to development module
            $moduleUrl = Plugin::$url . 'assets/js/fe/form-app-fallback.js';
            
            wp_register_script(
                self::HANDLE_MODULE,
                $moduleUrl,
                [],
                $version,
                false // Load in header, not footer
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
        if (is_admin() || is_embed()) {
            return false;
        }

        $post = null;
        $shouldEnqueue = false;

        // Check for shortcode or block in singular posts/pages
        if (is_singular()) {
            $post = get_post();
            if ($post instanceof WP_Post) {
                if (has_shortcode($post->post_content, 'fp_reservations')) {
                    $shouldEnqueue = true;
                }

                if (function_exists('has_block') && has_block('fp-restaurant-reservations/form', $post)) {
                    $shouldEnqueue = true;
                }
            }
        }

        // Check for shortcode in other contexts (homepage, archives, etc.)
        // Only if we're in the main query and it's safe to access
        if (!$shouldEnqueue && function_exists('is_main_query') && !is_admin()) {
            global $wp_query;
            
            // Safety checks before accessing $wp_query
            if (isset($wp_query) && is_object($wp_query) && isset($wp_query->posts) && is_array($wp_query->posts) && count($wp_query->posts) > 0) {
                // Limit to first 10 posts to avoid performance issues
                $posts_to_check = array_slice($wp_query->posts, 0, 10);
                
                foreach ($posts_to_check as $queried_post) {
                    if (!($queried_post instanceof WP_Post)) {
                        continue;
                    }
                    
                    if (has_shortcode($queried_post->post_content, 'fp_reservations')) {
                        $shouldEnqueue = true;
                        break;
                    }
                    
                    if (function_exists('has_block') && has_block('fp-restaurant-reservations/form', $queried_post)) {
                        $shouldEnqueue = true;
                        break;
                    }
                }
            }
        }

        /**
         * Allow third parties to control whether the frontend assets should load.
         *
         * @param bool          $shouldEnqueue Current decision.
         * @param WP_Post|null  $post          The resolved post object, when available.
         */
        $shouldEnqueue = (bool) apply_filters('fp_resv_frontend_should_enqueue', $shouldEnqueue, $post);

        return $shouldEnqueue;
    }
}
