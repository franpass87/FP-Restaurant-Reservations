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
        // Uso !important solo su proprietà essenziali, mantenendo gli stili interni del form
        $inlineCss = '
        /* Isola il widget da WPBakery/Theme */
        .vc_row .wpb_column .wpb_wrapper .fp-resv-widget,
        .vc_column_container .fp-resv-widget,
        .wpb_text_column .fp-resv-widget,
        .wpb_wrapper .fp-resv-widget,
        div.fp-resv-widget,
        #fp-resv-default {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            max-width: 100% !important;
            width: 100% !important;
            margin: 20px auto !important;
            padding: 0 !important;
            clear: both !important;
            position: relative !important;
            float: none !important;
        }
        /* Box-sizing per tutti */
        .fp-resv-widget,
        .fp-resv-widget *,
        .fp-resv-widget *::before,
        .fp-resv-widget *::after {
            box-sizing: border-box !important;
        }
        /* Forza layout corretto dei pulsanti di navigazione */
        .fp-resv-widget .fp-resv-step__footer,
        #fp-resv-default .fp-resv-step__footer {
            display: flex !important;
            flex-wrap: wrap !important;
            justify-content: flex-end !important;
            gap: 0.75rem !important;
            margin-top: 2rem !important;
        }
        /* Pulsante Indietro a sinistra */
        .fp-resv-widget .fp-resv-step__footer [data-fp-resv-nav="prev"],
        #fp-resv-default .fp-resv-step__footer [data-fp-resv-nav="prev"] {
            margin-right: auto !important;
        }
        /* Sticky CTA bar */
        .fp-resv-widget .fp-resv-sticky-cta,
        #fp-resv-default .fp-resv-sticky-cta {
            position: sticky !important;
            bottom: -0.5rem !important;
            padding: 0.85rem clamp(1rem, 4vw, 1.25rem) !important;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.95)) !important;
            backdrop-filter: blur(10px) !important;
            border-radius: 12px !important;
            border: 1px solid rgba(148, 163, 184, 0.2) !important;
            box-shadow: 0 -14px 34px rgba(15, 23, 42, 0.12) !important;
            z-index: 11 !important;
            margin-top: 1rem !important;
        }
        /* Azioni dentro sticky bar */
        .fp-resv-widget .fp-resv-widget__actions,
        #fp-resv-default .fp-resv-widget__actions {
            display: grid !important;
            gap: 0.65rem !important;
            align-items: flex-start !important;
        }
        /* SPACING CORRETTI - Forza margini e padding generosi */
        .fp-resv-widget,
        #fp-resv-default {
            padding: clamp(1.5rem, 4vw, 2.5rem) !important;
        }
        /* Mobile: padding 0 */
        @media (max-width: 640px) {
            .fp-resv-widget,
            #fp-resv-default {
                padding: 0px !important;
            }
            /* RIDUCI SPAZI VERTICALI SU MOBILE */
            .fp-resv-widget .fp-resv-step,
            #fp-resv-default .fp-resv-step {
                padding: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
            .fp-resv-widget .fp-section,
            #fp-resv-default .fp-section {
                margin-bottom: 0.75rem !important;
                padding: 0.75rem !important;
            }
            .fp-resv-widget .fp-resv-field,
            .fp-resv-widget .fp-field,
            #fp-resv-default .fp-resv-field,
            #fp-resv-default .fp-field {
                margin-bottom: 0.75rem !important;
            }
            .fp-resv-widget .fp-resv-step__header,
            #fp-resv-default .fp-resv-step__header {
                margin-bottom: 0.75rem !important;
            }
            .fp-resv-widget .fp-hint,
            .fp-resv-widget .fp-resv-meal-notice,
            #fp-resv-default .fp-hint,
            #fp-resv-default .fp-resv-meal-notice {
                margin-top: 0.5rem !important;
                margin-bottom: 0.5rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            .fp-resv-widget .fp-topbar,
            #fp-resv-default .fp-topbar {
                padding: 1rem !important;
                margin-bottom: 0.75rem !important;
            }
            .fp-resv-widget .fp-resv-widget__headline,
            #fp-resv-default .fp-resv-widget__headline {
                margin-bottom: 0.25rem !important;
            }
            .fp-resv-widget .fp-resv-step__footer,
            #fp-resv-default .fp-resv-step__footer {
                margin-top: 1rem !important;
                gap: 0.5rem !important;
            }
            .fp-resv-widget .fp-resv-sticky-cta,
            #fp-resv-default .fp-resv-sticky-cta {
                padding: 0.5rem 1rem !important;
                margin-top: 0.5rem !important;
            }
        }
        .fp-resv-widget .fp-resv-step,
        #fp-resv-default .fp-resv-step {
            padding: 2rem clamp(1.5rem, 5vw, 2.5rem) !important;
            margin-bottom: 1.5rem !important;
        }
        /* Spacing tra sezioni - PADDING 20PX */
        .fp-resv-widget .fp-section,
        #fp-resv-default .fp-section {
            margin-bottom: 1.5rem !important;
            padding: 20px !important;
        }
        /* Spacing tra campi del form */
        .fp-resv-widget .fp-resv-field,
        .fp-resv-widget .fp-field,
        #fp-resv-default .fp-resv-field,
        #fp-resv-default .fp-field {
            margin-bottom: 1.25rem !important;
        }
        /* Notice/hint text - più padding e margini */
        .fp-resv-widget .fp-hint,
        .fp-resv-widget .fp-resv-meal-notice,
        #fp-resv-default .fp-hint,
        #fp-resv-default .fp-resv-meal-notice {
            margin-top: 0.75rem !important;
            margin-bottom: 1rem !important;
            padding: 0.85rem 1rem !important;
            line-height: 1.6 !important;
        }
        /* Header/Topbar - più spazio */
        .fp-resv-widget .fp-topbar,
        #fp-resv-default .fp-topbar {
            padding: 1.5rem clamp(1.5rem, 5vw, 2.5rem) !important;
            margin-bottom: 1.5rem !important;
        }
        /* Titoli nel form - più margini */
        .fp-resv-widget .fp-resv-widget__headline,
        #fp-resv-default .fp-resv-widget__headline {
            margin-bottom: 0.5rem !important;
        }
        .fp-resv-widget .fp-resv-widget__subheadline,
        #fp-resv-default .fp-resv-widget__subheadline {
            margin-bottom: 0 !important;
        }
        /* Pulsanti servizio (Pranzo, Cena, ecc) */
        .fp-resv-widget .fp-meal-pill,
        #fp-resv-default .fp-meal-pill {
            margin: 0.5rem !important;
            padding: 0.85rem 1.25rem !important;
        }
        /* Titoli sezioni */
        .fp-resv-widget h2,
        .fp-resv-widget h3,
        #fp-resv-default h2,
        #fp-resv-default h3 {
            margin-top: 0 !important;
            margin-bottom: 1rem !important;
        }
        /* Paragrafi descrittivi */
        .fp-resv-widget p,
        #fp-resv-default p {
            margin-top: 0 !important;
            margin-bottom: 0.75rem !important;
            line-height: 1.6 !important;
        }
        /* Input, select, textarea - MIGLIOrata visibilità */
        .fp-resv-widget input:not([type="checkbox"]):not([type="radio"]),
        .fp-resv-widget select,
        .fp-resv-widget textarea,
        #fp-resv-default input:not([type="checkbox"]):not([type="radio"]),
        #fp-resv-default select,
        #fp-resv-default textarea {
            margin-top: 0.25rem !important;
            padding: 0.85rem 1rem !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 0.5rem !important;
            background: #ffffff !important;
            color: #1e293b !important;
            font-size: 1rem !important;
            line-height: 1.5 !important;
            width: 100% !important;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08) !important;
            transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
        }
        /* Input focus - bordo blu */
        .fp-resv-widget input:not([type="checkbox"]):not([type="radio"]):focus,
        .fp-resv-widget select:focus,
        .fp-resv-widget textarea:focus,
        #fp-resv-default input:not([type="checkbox"]):not([type="radio"]):focus,
        #fp-resv-default select:focus,
        #fp-resv-default textarea:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 1px 3px rgba(15, 23, 42, 0.08) !important;
        }
        /* Input placeholder - più visibile */
        .fp-resv-widget input::placeholder,
        .fp-resv-widget textarea::placeholder,
        #fp-resv-default input::placeholder,
        #fp-resv-default textarea::placeholder {
            color: #94a3b8 !important;
            opacity: 1 !important;
        }
        /* Campo data - ancora più visibile */
        .fp-resv-widget input[type="date"],
        .fp-resv-widget input[data-fp-resv-field="date"],
        #fp-resv-default input[type="date"],
        #fp-resv-default input[data-fp-resv-field="date"] {
            font-size: 1.05rem !important;
            padding: 1rem !important;
            border-width: 2px !important;
            min-height: 3rem !important;
            cursor: pointer !important;
        }
        /* Campi numerici (party, ecc) - più visibili */
        .fp-resv-widget input[type="number"],
        .fp-resv-widget input[data-fp-resv-field="party"],
        #fp-resv-default input[type="number"],
        #fp-resv-default input[data-fp-resv-field="party"] {
            font-size: 1.1rem !important;
            font-weight: 600 !important;
            text-align: center !important;
            min-height: 3rem !important;
        }
        /* Campi email e telefono */
        .fp-resv-widget input[type="email"],
        .fp-resv-widget input[type="tel"],
        .fp-resv-widget input[data-fp-resv-field="phone"],
        #fp-resv-default input[type="email"],
        #fp-resv-default input[type="tel"],
        #fp-resv-default input[data-fp-resv-field="phone"] {
            font-size: 1.05rem !important;
            min-height: 3rem !important;
        }
        /* Labels - più evidenti */
        .fp-resv-widget label,
        #fp-resv-default label {
            display: block !important;
            margin-bottom: 0.5rem !important;
            color: #334155 !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
        }
        /* Progress bar spacing e z-index */
        .fp-resv-widget .fp-resv-progress,
        .fp-resv-widget .fp-progress,
        #fp-resv-default .fp-resv-progress,
        #fp-resv-default .fp-progress {
            margin-bottom: 2.5rem !important;
            margin-top: 1.5rem !important;
            padding: 1.5rem clamp(1rem, 3vw, 2rem) !important;
            position: relative !important;
            z-index: 1 !important;
            background: transparent !important;
        }
        /* Mobile: rimuovi margin top e bottom */
        @media (max-width: 640px) {
            .fp-resv-widget .fp-resv-progress,
            .fp-resv-widget .fp-progress,
            #fp-resv-default .fp-resv-progress,
            #fp-resv-default .fp-progress {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                padding: 0.75rem 1rem !important;
            }
        }
        /* Progress bar pseudo-elementi - più chiari e meno invadenti */
        .fp-resv-widget .fp-progress::before,
        #fp-resv-default .fp-progress::before {
            z-index: 0 !important;
            pointer-events: none !important;
            opacity: 0.25 !important;
            background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.2) 16%, rgba(148, 163, 184, 0.2) 84%, transparent) !important;
        }
        .fp-resv-widget .fp-progress::after,
        #fp-resv-default .fp-progress::after {
            z-index: 0 !important;
            pointer-events: none !important;
            opacity: 0.8 !important;
        }
        /* Progress bar items - bianco pulito */
        .fp-resv-widget .fp-progress__item,
        #fp-resv-default .fp-progress__item {
            z-index: 10 !important;
            position: relative !important;
            background: #ffffff !important;
            border: 2px solid #cbd5e1 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            color: #475569 !important;
            font-weight: 600 !important;
            padding: 0.65rem 1.1rem !important;
            min-height: 2.75rem !important;
            min-width: 2.75rem !important;
        }
        /* Progress bar items - mobile a tutta larghezza */
        @media (max-width: 640px) {
            .fp-resv-widget .fp-progress__item,
            #fp-resv-default .fp-progress__item {
                min-width: 100% !important;
            }
        }
        /* Progress bar items - stato attivo BIANCO CON TESTO NERO */
        .fp-resv-widget .fp-progress__item[aria-current="step"],
        #fp-resv-default .fp-progress__item[aria-current="step"] {
            background: #ffffff !important;
            border-color: #000000 !important;
            color: #000000 !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
            font-weight: 700 !important;
        }
        /* MASSIMA PRIORITÀ: Forza NERO su item attivo */
        .fp-resv-widget .fp-progress__item[aria-current="step"],
        .fp-resv-widget .fp-progress__item[aria-current="step"]::before,
        .fp-resv-widget .fp-progress__item[aria-current="step"]::after,
        .fp-resv-widget .fp-progress__item[aria-current="step"] *,
        .fp-resv-widget .fp-progress__item[aria-current="step"] span,
        .fp-resv-widget .fp-progress__item[aria-current="step"] strong,
        .fp-resv-widget .fp-progress__item[aria-current="step"] em,
        .fp-resv-widget .fp-progress__item[aria-current="step"] div,
        #fp-resv-default .fp-progress__item[aria-current="step"],
        #fp-resv-default .fp-progress__item[aria-current="step"]::before,
        #fp-resv-default .fp-progress__item[aria-current="step"]::after,
        #fp-resv-default .fp-progress__item[aria-current="step"] *,
        #fp-resv-default .fp-progress__item[aria-current="step"] span,
        #fp-resv-default .fp-progress__item[aria-current="step"] strong,
        #fp-resv-default .fp-progress__item[aria-current="step"] em,
        #fp-resv-default .fp-progress__item[aria-current="step"] div {
            color: black !important;
            background: transparent !important;
        }
        /* Testo negli item - eredita colore */
        .fp-resv-widget .fp-progress__item span,
        .fp-resv-widget .fp-progress__item,
        #fp-resv-default .fp-progress__item span,
        #fp-resv-default .fp-progress__item {
            font-size: 0.95rem !important;
            line-height: 1.3 !important;
        }
        /* Forza GRIGIO SCURO su item inattivi (bianchi) */
        .fp-resv-widget .fp-progress__item:not([aria-current="step"]),
        .fp-resv-widget .fp-progress__item:not([aria-current="step"]) *,
        .fp-resv-widget .fp-progress__item:not([aria-current="step"]) span,
        #fp-resv-default .fp-progress__item:not([aria-current="step"]),
        #fp-resv-default .fp-progress__item:not([aria-current="step"]) *,
        #fp-resv-default .fp-progress__item:not([aria-current="step"]) span {
            color: #475569 !important;
        }
        /* Progress bar items completati - mantieni verde */
        .fp-resv-widget .fp-progress__item[aria-disabled="false"]:not([aria-current="step"]),
        #fp-resv-default .fp-progress__item[aria-disabled="false"]:not([aria-current="step"]) {
            background: #f0fdf4 !important;
            border-color: #86efac !important;
            color: #166534 !important;
        }
        .fp-resv-widget .fp-progress__item[aria-disabled="false"]:not([aria-current="step"]) *,
        .fp-resv-widget .fp-progress__item[aria-disabled="false"]:not([aria-current="step"]) span,
        #fp-resv-default .fp-progress__item[aria-disabled="false"]:not([aria-current="step"]) *,
        #fp-resv-default .fp-progress__item[aria-disabled="false"]:not([aria-current="step"]) span {
            color: #166534 !important;
        }
        /* Assicura che contenuti del form non siano coperti dalla progress bar */
        .fp-resv-widget .fp-resv-step,
        #fp-resv-default .fp-resv-step {
            position: relative !important;
            z-index: 3 !important;
        }
        /* Pulsanti - sopra la progress bar e con più padding */
        .fp-resv-widget button,
        .fp-resv-widget .fp-btn,
        #fp-resv-default button,
        #fp-resv-default .fp-btn {
            position: relative !important;
            z-index: 10 !important;
            padding: 0.85rem 1.5rem !important;
            margin: 0.25rem !important;
        }
        /* STICKY BAR - Pulsanti navigazione con più spazio */
        .fp-resv-widget .fp-resv-nav,
        .fp-resv-widget .fp-resv-widget__actions,
        #fp-resv-default .fp-resv-nav,
        #fp-resv-default .fp-resv-widget__actions {
            position: relative !important;
            z-index: 100 !important;
            padding: 1.5rem clamp(1.5rem, 5vw, 2.5rem) !important;
            margin-top: 2rem !important;
        }
        /* Pulsanti navigazione - più grandi e visibili */
        .fp-resv-widget .fp-resv-nav button,
        .fp-resv-widget .fp-resv-widget__actions button,
        #fp-resv-default .fp-resv-nav button,
        #fp-resv-default .fp-resv-widget__actions button {
            min-height: 3rem !important;
            padding: 1rem 2rem !important;
            font-size: 1.05em !important;
        }
        /* CHECKBOX - Layout robusto per testi lunghi */
        .fp-resv-widget .fp-resv-field--checkbox,
        .fp-resv-widget .fp-field--checkbox,
        .fp-resv-widget label.fp-resv-field--checkbox,
        .fp-resv-widget label.fp-field--checkbox,
        #fp-resv-default .fp-resv-field--checkbox,
        #fp-resv-default .fp-field--checkbox,
        #fp-resv-default label.fp-resv-field--checkbox,
        #fp-resv-default label.fp-field--checkbox {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-start !important;
            gap: 0.85rem !important;
            margin-bottom: 1.25rem !important;
            width: 100% !important;
            max-width: 100% !important;
            flex-wrap: nowrap !important;
        }
        /* Checkbox input - sempre a sinistra, dimensione fissa */
        .fp-resv-widget .fp-checkbox,
        .fp-resv-widget input[type="checkbox"],
        .fp-resv-widget .fp-resv-field--checkbox input,
        .fp-resv-widget .fp-field--checkbox input,
        #fp-resv-default .fp-checkbox,
        #fp-resv-default input[type="checkbox"],
        #fp-resv-default .fp-resv-field--checkbox input,
        #fp-resv-default .fp-field--checkbox input {
            flex: 0 0 1.25rem !important;
            width: 1.25rem !important;
            height: 1.25rem !important;
            min-width: 1.25rem !important;
            max-width: 1.25rem !important;
            margin: 0.15rem 0 0 0 !important;
            padding: 0 !important;
            cursor: pointer !important;
            order: -1 !important;
        }
        /* Testo checkbox - prende tutto lo spazio rimanente */
        .fp-resv-widget .fp-resv-field--checkbox span,
        .fp-resv-widget .fp-field--checkbox span,
        .fp-resv-widget .fp-resv-field--checkbox > span,
        .fp-resv-widget .fp-field--checkbox > span,
        .fp-resv-widget .fp-resv-field--checkbox > label,
        .fp-resv-widget .fp-field--checkbox > label,
        #fp-resv-default .fp-resv-field--checkbox span,
        #fp-resv-default .fp-field--checkbox span,
        #fp-resv-default .fp-resv-field--checkbox > span,
        #fp-resv-default .fp-field--checkbox > span,
        #fp-resv-default .fp-resv-field--checkbox > label,
        #fp-resv-default .fp-field--checkbox > label {
            flex: 1 1 0% !important;
            min-width: 0 !important;
            line-height: 1.65 !important;
            margin: 0 !important;
            padding: 0 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            cursor: pointer !important;
            order: 1 !important;
        }
        /* Link dentro il testo */
        .fp-resv-widget .fp-resv-field--checkbox a,
        .fp-resv-widget .fp-field--checkbox a,
        #fp-resv-default .fp-resv-field--checkbox a,
        #fp-resv-default .fp-field--checkbox a {
            display: inline !important;
            word-wrap: break-word !important;
        }
        /* Consent checkbox - stessi fix robusti */
        .fp-resv-widget .fp-resv-field--consent,
        .fp-resv-widget label.fp-resv-field--consent,
        #fp-resv-default .fp-resv-field--consent,
        #fp-resv-default label.fp-resv-field--consent {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-start !important;
            gap: 0.85rem !important;
            margin-top: 1.5rem !important;
            margin-bottom: 1.5rem !important;
            width: 100% !important;
            flex-wrap: nowrap !important;
        }
        /* Checkbox del consent */
        .fp-resv-widget .fp-resv-field--consent input[type="checkbox"],
        #fp-resv-default .fp-resv-field--consent input[type="checkbox"] {
            flex: 0 0 1.25rem !important;
            width: 1.25rem !important;
            height: 1.25rem !important;
            min-width: 1.25rem !important;
            max-width: 1.25rem !important;
            margin: 0.15rem 0 0 0 !important;
            padding: 0 !important;
            order: -1 !important;
        }
        /* Testo del consent */
        .fp-resv-widget .fp-resv-consent__text,
        .fp-resv-widget .fp-resv-field--consent span,
        .fp-resv-widget .fp-resv-field--consent > span,
        #fp-resv-default .fp-resv-consent__text,
        #fp-resv-default .fp-resv-field--consent span,
        #fp-resv-default .fp-resv-field--consent > span {
            flex: 1 1 0% !important;
            min-width: 0 !important;
            line-height: 1.65 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            order: 1 !important;
        }
        /* Badge OBBLIGATORIO/OPZIONALE */
        .fp-resv-widget .fp-resv-field--checkbox .fp-badge,
        .fp-resv-widget .fp-field--checkbox .fp-badge,
        #fp-resv-default .fp-resv-field--checkbox .fp-badge,
        #fp-resv-default .fp-field--checkbox .fp-badge {
            display: inline-block !important;
            margin-left: 0.5rem !important;
            white-space: nowrap !important;
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
