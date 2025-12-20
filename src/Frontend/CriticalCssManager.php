<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function error_log;

/**
 * Gestisce il CSS critico inline per override del tema.
 * Estratto da WidgetController per migliorare la manutenibilità.
 */
final class CriticalCssManager
{
    /**
     * Aggiunge CSS critico inline con priorità alta.
     */
    public function render(): void
    {
        error_log('[FP-RESV] CriticalCssManager - Caricando CSS critico inline');
        
        echo '<style id="fp-resv-critical-css" type="text/css">' . "\n";
        echo $this->getCriticalCss();
        echo '</style>' . "\n";
    }

    /**
     * Genera il CSS critico.
     */
    private function getCriticalCss(): string
    {
        return <<<CSS
        /* NASCONDI paragrafi vuoti e BR creati da wpautop/WPBakery */
        .fp-resv-simple p:empty,
        .fp-resv-simple > p:empty,
        .fp-steps-container > p:empty,
        .fp-step > p:empty,
        .wpb_wrapper .fp-resv-simple p:empty,
        .wpb_text_column p:empty,
        .fp-resv-simple label br,
        #fp-resv-default label br,
        .fp-field label br,
        label br {
            display: none !important;
            margin: 0 !important;
            padding: 0 !important;
            height: 0 !important;
            line-height: 0 !important;
        }
        
        /* FORZA il tag <style> del form a essere NASCOSTO */
        html body .wpb_wrapper style,
        html body .wpb_text_column style,
        html body .wpb_content_element style,
        html body .vc_column style,
        .fp-resv-simple style {
            display: none !important;
            visibility: hidden !important;
            position: absolute !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
        }
        
        /* Override tema Salient - SPECIFICITÀ MASSIMA */
        html body .fp-resv-simple select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
            min-width: 140px !important;
        }
        
        html body .fp-resv-simple .fp-field div[style*="display: flex"] select {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
        }
        
        html body .fp-resv-simple .fp-field div[style*="display: flex"] input {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        /* Override con ID per massima specificità */
        #fp-resv-default select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
            min-width: 140px !important;
        }
        
        #fp-resv-default .fp-field div[style*="display: flex"] select {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
        }
        
        #fp-resv-default .fp-field div[style*="display: flex"] input {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        /* Allineamento checkbox */
        html body .fp-resv-simple .fp-field label {
            align-items: flex-start !important;
        }
        
        html body .fp-resv-simple .fp-field input[type="checkbox"] {
            margin-top: 2px !important;
            align-self: flex-start !important;
        }
        
        /* Allineamento sezione Servizi Aggiuntivi */
        html body .fp-resv-simple .fp-field div[style*="display: flex"][style*="flex-direction: column"] {
            align-items: flex-start !important;
        }
        
        #fp-resv-default .fp-field div[style*="display: flex"][style*="flex-direction: column"] {
            align-items: flex-start !important;
        }
        
        /* Spaziatura link Privacy Policy */
        html body .fp-resv-simple .fp-field a {
            margin: 0 4px !important;
            display: inline !important;
        }
        
        #fp-resv-default .fp-field a {
            margin: 0 2px !important;
            display: inline !important;
        }
        
        /* FIX OVERLAY: Disabilita overlay invisibili */
        .wpb_wrapper:has(.fp-resv-simple)::before,
        .wpb_wrapper:has(.fp-resv-simple)::after,
        .wpb_text_column:has(.fp-resv-simple)::before,
        .wpb_text_column:has(.fp-resv-simple)::after {
            pointer-events: none !important;
            z-index: -1 !important;
        }
        
        /* Assicura che il form sia sempre accessibile */
        #fp-resv-default,
        .fp-resv-simple {
            position: relative !important;
            z-index: 100 !important;
            pointer-events: auto !important;
        }
        
        /* FIX STEP VISIBILITÀ: Nascondi step non attivi */
        html body #fp-resv-default .fp-step:not(.active),
        html body .fp-resv-simple .fp-step:not(.active) {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            position: absolute !important;
            pointer-events: none !important;
        }
        
        /* Mostra solo step attivo */
        html body #fp-resv-default .fp-step.active,
        html body .fp-resv-simple .fp-step.active {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            position: relative !important;
            pointer-events: auto !important;
        }
        
        /* FIX POINTER-EVENTS + CURSOR */
        html body #fp-resv-default button,
        html body #fp-resv-default .fp-meal-btn,
        html body #fp-resv-default .fp-btn,
        html body #fp-resv-default .fp-time-slot,
        html body #fp-resv-default .fp-btn-minus,
        html body #fp-resv-default .fp-btn-plus,
        html body .fp-resv-simple button,
        html body .fp-resv-simple .fp-meal-btn,
        html body .fp-resv-simple .fp-btn,
        html body .fp-resv-simple .fp-time-slot,
        html body .fp-resv-simple .fp-btn-minus,
        html body .fp-resv-simple .fp-btn-plus,
        html body .fp-resv-simple input,
        html body .fp-resv-simple select,
        html body .fp-resv-simple textarea,
        html body .fp-resv-simple a {
            pointer-events: auto !important;
            cursor: pointer !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            touch-action: manipulation !important;
        }
        
        /* ASTERISCHI INLINE - SPECIFICITÀ NUCLEARE */
        html body #fp-resv-default .fp-field label abbr.fp-required,
        html body .fp-resv-simple .fp-checkbox-wrapper label abbr.fp-required,
        html body .fp-resv-simple .fp-field label abbr.fp-required,
        html body .fp-checkbox-wrapper label abbr.fp-required,
        html body .fp-field label abbr.fp-required,
        html body label abbr.fp-required,
        html body abbr.fp-required,
        html body .fp-required,
        #fp-resv-default abbr.fp-required,
        .fp-resv-simple abbr.fp-required {
            display: inline !important;
            color: #dc2626 !important;
            text-decoration: none !important;
            font-weight: bold !important;
            cursor: help !important;
            margin-left: 2px !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding: 0 !important;
            white-space: nowrap !important;
            float: none !important;
            position: relative !important;
            vertical-align: baseline !important;
            line-height: inherit !important;
            overflow: visible !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            max-height: none !important;
        }
        
        /* Reset pseudo-elementi asterischi */
        html body abbr.fp-required::before,
        html body abbr.fp-required::after,
        html body .fp-required::before,
        html body .fp-required::after {
            content: none !important;
            display: none !important;
        }
CSS;
    }
}















