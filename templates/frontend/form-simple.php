<?php
/**
 * Form Semplice - Senza Complessità
 * Solo HTML, CSS e JavaScript essenziale
 */

if (!isset($context) || !is_array($context)) {
    return;
}

$config = $context['config'] ?? [];
$strings = $context['strings'] ?? [];
$meals = $context['meals'] ?? [];

$formId = $config['formId'] ?? 'fp-resv-simple';
?>

<!-- Form Prenotazioni - Caricato: <?php echo date('H:i:s'); ?> -->
<div id="<?php echo esc_attr($formId); ?>" class="fp-resv-simple">
    <style>
        .fp-resv-simple {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px 32px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid rgba(209, 213, 219, 0.2);
            position: relative;
            overflow: hidden;
            /* Assicuriamo che gli elementi interattivi funzionino */
            pointer-events: auto;
        }
        
        .fp-resv-simple::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #374151 0%, #6b7280 25%, #9ca3af 50%, #6b7280 75%, #374151 100%);
            border-radius: 20px 20px 0 0;
        }
        
        .fp-resv-simple h2 {
            color: #111827;
            margin-bottom: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.3px;
            line-height: 1.2;
            position: relative;
            padding-bottom: 12px;
        }
        
        .fp-resv-simple h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #374151 0%, #6b7280 100%);
            border-radius: 2px;
        }
        
        .fp-step {
            display: none;
            padding: 20px;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            visibility: hidden;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid rgba(209, 213, 219, 0.3);
            margin-bottom: 20px;
        }
        
        .fp-step::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #374151 0%, #6b7280 50%, #374151 100%);
            border-radius: 16px 16px 0 0;
        }
        
        .fp-step.active {
            display: block;
            opacity: 1;
            transform: translateX(0);
            position: relative;
            visibility: visible;
        }
        
        /* Fallback: se JavaScript non funziona, mostra almeno il primo step */
        .fp-resv-simple .fp-step:first-child {
            display: block;
            opacity: 1;
            transform: translateX(0);
            position: relative;
            visibility: visible;
        }
        
        /* Solo se JavaScript funziona, nascondi il primo step di default */
        .fp-resv-simple .fp-step:first-child:not(.active) {
            display: none;
            opacity: 0;
            transform: translateX(20px);
            visibility: hidden;
        }
        
        .fp-step.prev {
            opacity: 0;
            transform: translateX(-20px);
        }
        
        .fp-steps-container {
            position: relative;
            min-height: 200px;
            background: #f9fafb;
            border-radius: 16px;
            padding: 20px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
        }
        
        .fp-step h3 {
            color: #111827;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.2px;
            position: relative;
            padding-bottom: 12px;
        }
        
        .fp-step h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #374151 0%, #6b7280 100%);
            border-radius: 2px;
        }
        
        .fp-field {
            margin-bottom: 16px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            border: 1px solid rgba(209, 213, 219, 0.2);
            transition: all 0.2s ease;
        }
        
        .fp-field:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(209, 213, 219, 0.4);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .fp-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            letter-spacing: 0.2px;
        }
        
        /* Separatori tra sezioni */
        .fp-section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #d1d5db 50%, transparent 100%);
            margin: 24px 0;
            position: relative;
        }
        
        .fp-section-divider::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 8px;
            height: 5px;
            background: #6b7280;
            border-radius: 2px;
        }
        
        /* Notice Inline System */
        .fp-notice-container {
            margin: 16px 0;
            min-height: 0;
        }
        
        .fp-notice {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInDown 0.3s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .fp-notice::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .fp-notice--success {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-color: #bbf7d0;
            color: #166534;
        }
        
        .fp-notice--success::before {
            background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
        }
        
        .fp-notice--error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: #fecaca;
            color: #dc2626;
        }
        
        .fp-notice--error::before {
            background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%);
        }
        
        .fp-notice--warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #fed7aa;
            color: #d97706;
        }
        
        .fp-notice--warning::before {
            background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
        }
        
        .fp-notice--info {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #bfdbfe;
            color: #2563eb;
        }
        
        .fp-notice--info::before {
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .fp-notice__icon {
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .fp-notice__content {
            flex: 1;
        }
        
        .fp-notice__close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
            flex-shrink: 0;
        }
        
        .fp-notice__close:hover {
            opacity: 1;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        .fp-notice--closing {
            animation: slideOutUp 0.3s ease-in forwards;
        }
        
        .fp-field input,
        .fp-field select,
        .fp-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #ffffff;
            color: #374151;
            transition: all 0.2s ease;
            font-family: inherit;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        
        .fp-field input:focus,
        .fp-field select:focus,
        .fp-field textarea:focus {
            outline: none;
            border-color: #374151;
            box-shadow: 0 0 0 3px rgba(55, 65, 81, 0.1), 0 4px 12px 0 rgba(55, 65, 81, 0.15);
            transform: translateY(-1px);
        }
        
        .fp-field input::placeholder,
        .fp-field textarea::placeholder {
            color: #9ca3af;
        }
        
        .fp-meals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 8px;
            margin-top: 6px;
        }
        
        .fp-meal-btn {
            padding: 12px 16px;
            border: 1.5px solid #d1d5db;
            background: #ffffff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-align: center;
            color: #374151;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);
        }
        
        .fp-meal-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(55, 65, 81, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .fp-meal-btn:hover {
            border-color: #374151;
            background: #f9fafb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(55, 65, 81, 0.12);
        }
        
        .fp-meal-btn:hover::before {
            left: 100%;
        }
        
        .fp-meal-btn.selected {
            background: #374151;
            color: #ffffff;
            border-color: #374151;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(55, 65, 81, 0.2);
        }
        
        .fp-buttons {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            margin-top: 20px;
            padding: 16px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .fp-btn {
            padding: 12px 20px;
            border: 1.5px solid;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }
        
        .fp-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .fp-btn:hover::before {
            left: 100%;
        }
        
        .fp-btn-primary {
            background: #374151;
            color: #ffffff;
            border-color: #374151;
            box-shadow: 0 2px 8px rgba(55, 65, 81, 0.15);
            font-weight: 600;
            letter-spacing: 0.025em;
        }
        
        .fp-btn-primary:hover {
            background: #1f2937;
            border-color: #1f2937;
            box-shadow: 0 4px 12px rgba(55, 65, 81, 0.25);
            transform: translateY(-1px);
        }
        
        .fp-btn-secondary {
            background: #ffffff;
            color: #374151;
            border-color: #d1d5db;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            font-weight: 500;
        }
        
        .fp-btn-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        
        .fp-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        /* Assicuriamo che tutti gli elementi interattivi del form funzionino */
        .fp-resv-simple button,
        .fp-resv-simple input,
        .fp-resv-simple select,
        .fp-resv-simple textarea,
        .fp-resv-simple a {
            cursor: pointer !important;
            pointer-events: auto !important;
            /* Fallback per garantire che siano sempre cliccabili */
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
        
        .fp-resv-simple button:disabled,
        .fp-resv-simple input:disabled,
        .fp-resv-simple select:disabled,
        .fp-resv-simple textarea:disabled {
            cursor: not-allowed !important;
            pointer-events: auto !important;
        }
        
        /* Fallback: assicuriamo che il form sia sempre visibile e funzionante */
        .fp-resv-simple {
            /* Forza la visibilità del form senza interferire con il resto della pagina */
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            /* Assicuriamo che il form non interferisca con il layout della pagina */
            position: relative !important;
            z-index: auto !important;
        }
        
        /* Assicuriamo che il contenuto della pagina sia sempre visibile */
        body > *:not(.fp-resv-simple),
        .container-wrap > *:not(.fp-resv-simple),
        .main-content > *:not(.fp-resv-simple) {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .fp-progress {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
            position: relative;
        }
        
        .fp-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 2px;
            background: #d1d5db;
            z-index: 1;
        }
        
        .fp-progress-step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 6px;
            font-weight: 600;
            color: #999999;
            font-size: 12px;
            border: 2px solid #e8e8e8;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }
        
        .fp-progress-step.active {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .fp-progress-step.completed {
            background: #333333;
            color: #ffffff;
            border-color: #333333;
        }
        
        /* Checkbox styling - massima specificità per evitare conflitti */
        #fp-resv-default .fp-field input[type="checkbox"],
        .fp-resv-simple .fp-field input[type="checkbox"] {
            width: 16px !important;
            height: 16px !important;
            margin: 0 !important;
            margin-right: 10px !important;
            transform: none !important;
            cursor: pointer !important;
            accent-color: #374151 !important;
            flex-shrink: 0 !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            border: 2px solid #d1d5db !important;
            border-radius: 6px !important;
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%) !important;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08) !important;
        }
        
        #fp-resv-default .fp-field input[type="checkbox"]:checked,
        .fp-resv-simple .fp-field input[type="checkbox"]:checked {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%) !important;
            border-color: #374151 !important;
        }
        
        .fp-field label {
            display: flex !important;
            align-items: flex-start !important;
            gap: 0 !important;
            cursor: pointer !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            margin-bottom: 0 !important;
            color: #1f2937 !important;
        }
        
        /* Testo checkbox e label in nero */
        .fp-field label span,
        .fp-field label > span {
            color: #1f2937 !important;
        }
        
        /* Link privacy in blu ma testo normale nero */
        .fp-field label span a,
        .fp-field label a {
            color: #2563eb !important;
            text-decoration: underline !important;
        }
        .fp-field label span a:hover,
        .fp-field label a:hover {
            color: #1d4ed8 !important;
        }
        
        /* Stile specifico per i checkbox dei servizi aggiuntivi */
        .fp-field div label {
            display: flex !important;
            align-items: flex-start !important;
            gap: 0 !important;
            margin-bottom: 8px !important;
        }
        
        .fp-field div label input[type="checkbox"] {
            margin-right: 10px !important;
            margin-top: 2px !important;
        }
        
        /* Allineamento specifico per checkbox */
        #fp-resv-default .fp-field input[type="checkbox"],
        .fp-resv-simple .fp-field input[type="checkbox"] {
            margin-top: 2px !important;
            align-self: flex-start !important;
        }
        
        /* Allineamento sezione Servizi Aggiuntivi */
        .fp-resv-simple .fp-field div[style*="display: flex"][style*="flex-direction: column"] {
            align-items: flex-start !important;
        }
        
        #fp-resv-default .fp-field div[style*="display: flex"][style*="flex-direction: column"] {
            align-items: flex-start !important;
        }
        
        /* Spaziatura link Privacy Policy */
        .fp-resv-simple .fp-field a {
            margin: 0 4px !important;
            display: inline !important;
        }
        
        #fp-resv-default .fp-field a {
            margin: 0 2px !important;
            display: inline !important;
        }
        
        /* Input date - permette click su tutta l'area */
        .fp-field input[type="date"] {
            cursor: pointer;
            position: relative;
            z-index: 1;
        }
        
        .fp-field input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            color: transparent;
            background: transparent;
            cursor: pointer;
        }
        
        .fp-field label[style*="display: flex"] {
            align-items: flex-start;
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
            line-height: 1.5;
        }
        
        /* Phone prefix styling - ALTA SPECIFICITÀ per sovrascrivere tema */
        .fp-resv-simple .fp-field div[style*="display: flex"] {
            align-items: flex-start !important;
        }
        
        .fp-resv-simple .fp-field div[style*="display: flex"] select {
            width: 140px !important;
            margin-right: 8px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
        }
        
        .fp-resv-simple .fp-field div[style*="display: flex"] input {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        /* Override specifico per il campo telefono */
        .fp-resv-simple select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
        }
        
        /* Override con specificità MASSIMA per il tema Salient */
        body .fp-resv-simple .fp-field div[style*="display: flex"] select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
            min-width: 140px !important;
        }
        
        /* Override ancora più specifico per il tema Salient */
        html body .fp-resv-simple .fp-field div[style*="display: flex"] select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
            min-width: 140px !important;
        }
        
        /* Override con ID per massima specificità */
        #fp-resv-default .fp-field div[style*="display: flex"] select[name="fp_resv_phone_prefix"] {
            width: 140px !important;
            flex-shrink: 0 !important;
            max-width: 140px !important;
            min-width: 140px !important;
        }
        
        /* Time slots styling */
        .fp-time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        
        .fp-time-slot {
            padding: 12px 14px;
            border: 1.5px solid #e8e8e8;
            background: #ffffff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-align: center;
            color: #2c2c2c;
            position: relative;
            overflow: hidden;
        }
        
        .fp-time-slot::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0,0,0,0.05), transparent);
            transition: left 0.5s ease;
        }
        
        .fp-time-slot:hover {
            border-color: #000000;
            background: #fafafa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .fp-time-slot:hover::before {
            left: 100%;
        }
        
        .fp-time-slot.selected {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .fp-time-slot.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #f8f8f8;
            border-color: #e0e0e0;
        }
        
        /* Party Selector */
        .fp-party-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin: 16px 0;
            padding: 16px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
        }
        
        .fp-btn-minus, .fp-btn-plus {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid #d1d5db;
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            color: #374151;
            font-size: 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);
        }
        
        .fp-btn-minus:hover, .fp-btn-plus:hover {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: #ffffff;
            border-color: #374151;
            box-shadow: 0 4px 14px 0 rgba(55, 65, 81, 0.25);
            transform: translateY(-1px);
        }
        
        .fp-btn-minus:disabled, .fp-btn-plus:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #d1d5db;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        
        .fp-party-display {
            text-align: center;
            min-width: 120px;
        }
        
        .fp-party-display #party-count {
            display: block;
            font-size: 28px;
            font-weight: bold;
            color: #111827;
            line-height: 1;
        }
        
        .fp-party-display #party-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Summary Step */
        .fp-summary {
            background: #f8f8f8;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid #e8e8e8;
        }
        
        .fp-summary-section {
            margin-bottom: 25px;
        }
        
        .fp-summary-section:last-child {
            margin-bottom: 0;
        }
        
        .fp-summary-section h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            border-bottom: 2px solid #e8e8e8;
            padding-bottom: 8px;
        }
        
        .fp-summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .fp-summary-item:last-child {
            border-bottom: none;
        }
        
        .fp-summary-label {
            font-weight: 500;
            color: #666;
            font-size: 14px;
        }
        
        .fp-summary-value {
            font-weight: 600;
            color: #000000;
            font-size: 14px;
            text-align: right;
            max-width: 60%;
            word-wrap: break-word;
        }
        
        .fp-summary-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .fp-summary-note p {
            margin: 0;
            font-size: 14px;
            color: #856404;
            line-height: 1.5;
        }
        
        /* Loading states */
        #date-loading, #time-loading {
            text-align: center;
            padding: 20px;
            color: #666666;
            font-size: 14px;
            font-style: italic;
        }
        
        #date-info, #time-info {
            font-size: 13px;
            color: #666666;
            margin-top: 8px;
            padding: 8px 12px;
            background: #f8f8f8;
            border-radius: 6px;
            border-left: 3px solid #000000;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            .fp-resv-simple {
                margin: 16px 12px;
                padding: 20px 16px;
            }
            
            .fp-resv-simple h2 {
                font-size: 18px;
                margin-bottom: 16px;
            }
            
            .fp-step {
                padding: 12px 0;
            }
            
            .fp-step h3 {
                font-size: 15px;
                margin-bottom: 12px;
            }
            
            .fp-field {
                margin-bottom: 12px;
            }
            
            .fp-meals {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            
            .fp-meal-btn {
                padding: 10px 14px;
                font-size: 12px;
            }
            
            .fp-buttons {
                flex-direction: column;
                gap: 8px;
                margin-top: 16px;
                padding-top: 12px;
            }
            
            .fp-btn {
                padding: 10px 16px;
                font-size: 12px;
            }
            
            .fp-progress {
                margin-bottom: 16px;
                padding: 12px 0;
            }
            
            .fp-progress-step {
                width: 28px;
                height: 28px;
                margin: 0 3px;
                font-size: 11px;
            }
            
            .fp-progress::before {
                width: 70%;
            }
            
            .fp-time-slots {
                grid-template-columns: repeat(auto-fit, minmax(75px, 1fr));
                gap: 6px;
            }
            
            .fp-time-slot {
                padding: 12px 14px;
                font-size: 12px;
                min-height: 44px;
            }
            
            /* Phone prefix responsive */
            .fp-field div[style*="display: flex"] select {
                width: 120px;
                font-size: 12px;
                padding: 10px 6px;
            }
            
            .fp-field div[style*="display: flex"] input {
                font-size: 12px;
                padding: 10px 12px;
            }
        }
    </style>

    <h2>Prenota il Tuo Tavolo</h2>
    
    <!-- Area Notice Inline -->
    <div class="fp-notice-container" id="fp-notice-container">
        <!-- I notice verranno inseriti qui dinamicamente -->
    </div>
    
    <!-- Progress Bar -->
    <div class="fp-progress">
        <div class="fp-progress-step active" data-step="1">1</div>
        <div class="fp-progress-step" data-step="2">2</div>
        <div class="fp-progress-step" data-step="3">3</div>
        <div class="fp-progress-step" data-step="4">4</div>
    </div>

    <!-- Steps Container -->
    <div class="fp-steps-container">
        <!-- Step 1: Servizio -->
        <div class="fp-step active" data-step="1">
        <h3>1. Scegli il Servizio</h3>
        <div class="fp-field">
            <div class="fp-meals" id="meal-buttons">
                <?php if (!empty($meals) && is_array($meals)): ?>
                    <?php foreach ($meals as $meal): ?>
                        <?php if (is_array($meal) && isset($meal['key']) && isset($meal['label'])): ?>
                            <button type="button" class="fp-meal-btn" data-meal="<?php echo esc_attr($meal['key']); ?>">
                                <?php if (!empty($meal['icon'])): ?>
                                    <?php echo wp_strip_all_tags($meal['icon']); ?>
                                <?php endif; ?>
                                <?php echo esc_html($meal['label']); ?>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback se non ci sono meal configurati -->
                    <button type="button" class="fp-meal-btn" data-meal="pranzo">
                        🍽️ Pranzo
                    </button>
                    <button type="button" class="fp-meal-btn" data-meal="cena">
                        🌙 Cena
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Step 2: Data, Persone e Orario -->
    <div class="fp-step" data-step="2">
        <h3>2. Scegli Data, Persone e Orario</h3>
        <div class="fp-section-divider"></div>
        
        <!-- Data -->
        <div class="fp-field">
            <label for="reservation-date">Data *</label>
            <input type="date" id="reservation-date" name="date" required>
            <div id="date-loading" style="display: none; margin-top: 8px; font-size: 13px; color: #666;">
                ⏳ Caricamento date disponibili...
            </div>
            <div id="date-info" style="display: none; margin-top: 8px; font-size: 13px; color: #333;">
                📅 Seleziona una data disponibile per il servizio scelto
            </div>
        </div>
        
        <!-- Persone -->
        <div class="fp-field">
            <label>Persone</label>
            <div class="fp-party-selector">
                <button type="button" class="fp-btn-minus" id="party-minus">−</button>
                <div class="fp-party-display">
                    <span id="party-count">2</span>
                    <span id="party-label">persone</span>
                </div>
                <button type="button" class="fp-btn-plus" id="party-plus">+</button>
            </div>
            <input type="hidden" id="party-size" name="party" value="2" required>
        </div>
        
        <!-- Orari -->
        <div class="fp-field">
            <label>Orario</label>
            <div id="time-loading" style="display: none; text-align: center; padding: 20px; color: #666;">
                ⏳ Caricamento orari disponibili...
            </div>
            <div id="time-slots" class="fp-time-slots">
                <!-- Gli orari verranno caricati dinamicamente -->
            </div>
            <div id="time-info" style="display: none; font-size: 12px; color: #666; margin-top: 10px;">
                Seleziona un orario per continuare
            </div>
        </div>
    </div>

    <!-- Step 3: Dettagli -->
    <div class="fp-step" data-step="3">
        <h3>3. I Tuoi Dettagli</h3>
        <div class="fp-section-divider"></div>
        <div class="fp-field">
            <label for="customer-first-name">Nome *</label>
            <input type="text" id="customer-first-name" name="fp_resv_first_name" required autocomplete="given-name">
        </div>
        <div class="fp-field">
            <label for="customer-last-name">Cognome *</label>
            <input type="text" id="customer-last-name" name="fp_resv_last_name" required autocomplete="family-name">
        </div>
        <div class="fp-field">
            <label for="customer-email">Email *</label>
            <input type="email" id="customer-email" name="fp_resv_email" required autocomplete="email">
        </div>
        <div class="fp-field">
            <label for="customer-phone">Telefono *</label>
            <div style="display: flex; gap: 8px; align-items: stretch;">
                <select name="fp_resv_phone_prefix" style="width: 140px !important; padding: 12px 8px; border: 1.5px solid #d1d5db; border-radius: 12px; font-size: 13px; background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%); color: #374151; transition: all 0.2s ease; font-family: inherit; flex-shrink: 0 !important; max-width: 140px !important; min-width: 140px !important; box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);">
                    <?php 
                    $phonePrefixes = $config['phone_prefixes'] ?? [];
                    $defaultPhoneCode = $config['defaults']['phone_country_code'] ?? '39';
                    
                    if (!empty($phonePrefixes) && is_array($phonePrefixes)): 
                        foreach ($phonePrefixes as $prefix): 
                            if (is_array($prefix) && isset($prefix['value']) && isset($prefix['label'])): 
                                $selected = ($prefix['value'] == $defaultPhoneCode) ? 'selected' : '';
                                $label = $prefix['label'] ?? '';
                                // Estrai il nome del paese dal label (formato: "+39 · Italia")
                                $country = $label;
                                if (strpos($label, ' · ') !== false) {
                                    $parts = explode(' · ', $label, 2);
                                    $country = trim($parts[1] ?? $label);
                                }
                    ?>
                        <option value="<?php echo esc_attr($prefix['value']); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php 
                            endif;
                        endforeach;
                    else: 
                        // Fallback se non ci sono phone prefixes configurati
                    ?>
                        <option value="39" selected>🇮🇹 +39 · Italia</option>
                        <option value="44">🇬🇧 +44 · Regno Unito</option>
                        <option value="33">🇫🇷 +33 · Francia</option>
                        <option value="49">🇩🇪 +49 · Germania</option>
                        <option value="34">🇪🇸 +34 · Spagna</option>
                        <option value="1">🇺🇸 +1 · Stati Uniti</option>
                    <?php endif; ?>
                </select>
                <input type="tel" id="customer-phone" name="fp_resv_phone" required autocomplete="tel" placeholder="123 456 7890" style="flex: 1 !important; min-width: 0 !important; padding: 12px 14px; border: 1.5px solid #d1d5db; border-radius: 12px; font-size: 14px; background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%); color: #374151; transition: all 0.2s ease; font-family: inherit; box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);">
            </div>
        </div>
        <div class="fp-field">
            <label for="occasion">Occasione (opzionale)</label>
            <select id="occasion" name="fp_resv_occasion">
                <option value="">Seleziona un'occasione</option>
                <option value="birthday">Compleanno</option>
                <option value="anniversary">Anniversario</option>
                <option value="business">Business</option>
                <option value="celebration">Celebrazione</option>
                <option value="date">Appuntamento</option>
                <option value="family">Famiglia</option>
                <option value="other">Altro</option>
            </select>
        </div>
        <div class="fp-field">
            <label for="notes">Note Speciali (opzionale)</label>
            <textarea id="notes" name="fp_resv_notes" rows="3" placeholder="Es. preferenza per un tavolo particolare, orario flessibile, ecc." style="width: 100%; padding: 12px 14px; border: 1.5px solid #e8e8e8; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #ffffff; color: #000000; transition: all 0.2s ease; font-family: inherit; resize: vertical;"></textarea>
        </div>
        <div class="fp-field">
            <label for="allergies">Allergie/Intolleranze (opzionale)</label>
            <textarea id="allergies" name="fp_resv_allergies" rows="3" placeholder="Indica eventuali allergie o intolleranze alimentari" style="width: 100%; padding: 12px 14px; border: 1.5px solid #e8e8e8; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #ffffff; color: #000000; transition: all 0.2s ease; font-family: inherit; resize: vertical;"></textarea>
        </div>
        
        <!-- Extras -->
        <div class="fp-field">
            <label>Servizi Aggiuntivi</label>
            <div style="display: flex; flex-direction: column; gap: 12px; align-items: flex-start;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="fp_resv_wheelchair_table" value="1" style="width: 16px; height: 16px; margin: 0; cursor: pointer; flex-shrink: 0;">
                    <span style="color: #1f2937;">Tavolo accessibile per sedia a rotelle</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="fp_resv_pets" value="1" style="width: 16px; height: 16px; margin: 0; cursor: pointer; flex-shrink: 0;">
                    <span style="color: #1f2937;">Accompagnato da animale domestico</span>
                </label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="high-chair-count">Seggioloni:</label>
                    <input type="number" id="high-chair-count" name="fp_resv_high_chair_count" value="0" min="0" max="10" style="width: 70px; padding: 8px 10px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 13px; background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%); color: #374151; transition: all 0.2s ease; font-family: inherit; text-align: center; box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);">
                </div>
            </div>
        </div>
        
        <!-- Privacy -->
        <div class="fp-field">
            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="fp_resv_consent" required style="width: 16px; height: 16px; margin: 0; margin-top: 2px; cursor: pointer; flex-shrink: 0;">
                <span style="color: #1f2937;">Accetto la <a href="#" target="_blank" style="color: #2563eb; text-decoration: underline;">Privacy Policy</a> e il trattamento dei miei dati personali *</span>
            </label>
        </div>
        <div class="fp-field">
            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="fp_resv_marketing_consent" value="1" style="width: 16px; height: 16px; margin: 0; margin-top: 2px; cursor: pointer; flex-shrink: 0;">
                <span style="color: #1f2937;">Acconsento al trattamento dei dati per comunicazioni marketing (opzionale)</span>
            </label>
        </div>
    </div>

    <!-- Step 4: Riepilogo -->
    <div class="fp-step" data-step="4">
        <h3>4. Riepilogo Prenotazione</h3>
        <div class="fp-section-divider"></div>
        <div class="fp-summary">
            <div class="fp-summary-section">
                <h4>📅 Quando</h4>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Servizio:</span>
                    <span class="fp-summary-value" id="summary-meal">-</span>
                </div>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Data:</span>
                    <span class="fp-summary-value" id="summary-date">-</span>
                </div>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Orario:</span>
                    <span class="fp-summary-value" id="summary-time">-</span>
                </div>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Persone:</span>
                    <span class="fp-summary-value" id="summary-party">-</span>
                </div>
            </div>
            
            <div class="fp-summary-section">
                <h4>👤 Dettagli Personali</h4>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Nome:</span>
                    <span class="fp-summary-value" id="summary-name">-</span>
                </div>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Email:</span>
                    <span class="fp-summary-value" id="summary-email">-</span>
                </div>
                <div class="fp-summary-item">
                    <span class="fp-summary-label">Telefono:</span>
                    <span class="fp-summary-value" id="summary-phone">-</span>
                </div>
                <div class="fp-summary-item" id="summary-occasion-row" style="display: none;">
                    <span class="fp-summary-label">Occasione:</span>
                    <span class="fp-summary-value" id="summary-occasion">-</span>
                </div>
                <div class="fp-summary-item" id="summary-notes-row" style="display: none;">
                    <span class="fp-summary-label">Note:</span>
                    <span class="fp-summary-value" id="summary-notes">-</span>
                </div>
                <div class="fp-summary-item" id="summary-allergies-row" style="display: none;">
                    <span class="fp-summary-label">Allergie:</span>
                    <span class="fp-summary-value" id="summary-allergies">-</span>
                </div>
            </div>
            
            <div class="fp-summary-section" id="summary-extras-row" style="display: none;">
                <h4>🔧 Servizi Aggiuntivi</h4>
                <div class="fp-summary-item" id="summary-wheelchair-row" style="display: none;">
                    <span class="fp-summary-label">Tavolo accessibile:</span>
                    <span class="fp-summary-value">Sì</span>
                </div>
                <div class="fp-summary-item" id="summary-pets-row" style="display: none;">
                    <span class="fp-summary-label">Animale domestico:</span>
                    <span class="fp-summary-value">Sì</span>
                </div>
                <div class="fp-summary-item" id="summary-highchair-row" style="display: none;">
                    <span class="fp-summary-label">Seggioloni:</span>
                    <span class="fp-summary-value" id="summary-highchair">-</span>
                </div>
            </div>
        </div>
        
        <div class="fp-summary-note">
            <p>📝 <strong>Verifica attentamente tutti i dati</strong> prima di confermare la prenotazione. Una volta inviata, riceverai una email di conferma.</p>
        </div>
    </div>
    </div> <!-- End fp-steps-container -->

    <!-- Hidden Fields -->
    <input type="hidden" name="fp_resv_meal" value="">
    <input type="hidden" name="fp_resv_date" value="">
    <input type="hidden" name="fp_resv_party" value="">
    <input type="hidden" name="fp_resv_time" value="">
    <input type="hidden" name="fp_resv_slot_start" value="">
    <input type="hidden" name="fp_resv_location" value="<?php echo esc_attr($config['location'] ?? 'default'); ?>">
    <input type="hidden" name="fp_resv_locale" value="<?php echo esc_attr($config['locale'] ?? 'it_IT'); ?>">
    <input type="hidden" name="fp_resv_language" value="<?php echo esc_attr($config['language'] ?? 'it'); ?>">
    <input type="hidden" name="fp_resv_currency" value="<?php echo esc_attr($config['defaults']['currency'] ?? 'EUR'); ?>">
    <input type="hidden" name="fp_resv_phone_e164" value="">
    <input type="hidden" name="fp_resv_phone_cc" value="<?php echo esc_attr($config['defaults']['phone_country_code'] ?? '39'); ?>">
    <input type="hidden" name="fp_resv_phone_local" value="">
    <input type="hidden" name="fp_resv_hp" value="" autocomplete="off">
    <input type="hidden" name="fp_resv_policy_version" value="<?php echo esc_attr($context['privacy']['policy_version'] ?? '1.0'); ?>">
    <input type="hidden" name="fp_resv_price_per_person" value="">

    <!-- Buttons -->
    <div class="fp-buttons">
        <button type="button" class="fp-btn fp-btn-secondary" id="prev-btn" style="display: none;">← Indietro</button>
        <button type="button" class="fp-btn fp-btn-primary" id="next-btn">Avanti →</button>
        <button type="button" class="fp-btn fp-btn-primary" id="submit-btn" style="display: none;">Prenota</button>
    </div>
</div>

<script>
// Sistema Notice Inline - DEVE essere caricato PRIMA del form-simple.js
class NoticeManager {
    constructor() {
        this.container = document.getElementById('fp-notice-container');
        this.notices = new Map();
        this.init();
    }
    
    init() {
        // Se il container non esiste ancora, aspetta che il DOM sia pronto
        if (!this.container) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    this.container = document.getElementById('fp-notice-container');
                    this.overrideExistingNotifications();
                });
            } else {
                // DOM già pronto, riprova a trovare il container
                this.container = document.getElementById('fp-notice-container');
                this.overrideExistingNotifications();
            }
            return;
        }
        
        // Override delle funzioni di notifica esistenti
        this.overrideExistingNotifications();
    }
    
    overrideExistingNotifications() {
        // Override per alert/notify esistenti
        const originalAlert = window.alert;
        const originalNotify = window.notify;
        
        // Intercetta alert
        window.alert = (message) => {
            this.show('info', message, 5000);
        };
        
        // Intercetta notify se esiste
        if (typeof window.notify === 'function') {
            window.notify = (message, level = 'info') => {
                this.show(level, message, 5000);
            };
        }
        
        // Intercetta console.error per errori JavaScript
        const originalConsoleError = console.error;
        console.error = (...args) => {
            originalConsoleError.apply(console, args);
            const message = args.join(' ');
            if (message.includes('Error') || message.includes('error')) {
                this.show('error', 'Si è verificato un errore. Riprova.', 5000);
            }
        };
    }
    
    show(type, message, duration = 5000) {
        // Se il container non esiste, riprova a trovarlo
        if (!this.container) {
            this.container = document.getElementById('fp-notice-container');
        }
        
        // Se ancora non esiste, usa alert come fallback
        if (!this.container) {
            alert(message);
            return null;
        }
        
        const id = Date.now() + Math.random();
        const notice = this.createNotice(id, type, message);
        
        this.container.appendChild(notice);
        this.notices.set(id, notice);
        
        // Auto-remove dopo la durata specificata
        if (duration > 0) {
            setTimeout(() => {
                this.remove(id);
            }, duration);
        }
        
        return id;
    }
    
    createNotice(id, type, message) {
        const notice = document.createElement('div');
        notice.className = `fp-notice fp-notice--${type}`;
        notice.setAttribute('data-notice-id', id);
        
        const icon = this.getIcon(type);
        const closeButton = this.createCloseButton(id);
        
        notice.innerHTML = `
            <span class="fp-notice__icon">${icon}</span>
            <div class="fp-notice__content">${message}</div>
        `;
        
        notice.appendChild(closeButton);
        
        return notice;
    }
    
    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }
    
    createCloseButton(id) {
        const button = document.createElement('button');
        button.className = 'fp-notice__close';
        button.innerHTML = '×';
        button.setAttribute('aria-label', 'Chiudi notifica');
        
        button.addEventListener('click', () => {
            this.remove(id);
        });
        
        return button;
    }
    
    remove(id) {
        const notice = this.notices.get(id);
        if (!notice) return;
        
        notice.classList.add('fp-notice--closing');
        
        setTimeout(() => {
            if (notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
            this.notices.delete(id);
        }, 300);
    }
    
    clear() {
        this.notices.forEach((notice, id) => {
            this.remove(id);
        });
    }
    
    // Metodi di convenienza
    success(message, duration = 5000) {
        return this.show('success', message, duration);
    }
    
    error(message, duration = 8000) {
        return this.show('error', message, duration);
    }
    
    warning(message, duration = 6000) {
        return this.show('warning', message, duration);
    }
    
    info(message, duration = 5000) {
        return this.show('info', message, duration);
    }
}

// Inizializza il sistema di notice immediatamente
window.fpNoticeManager = new NoticeManager();

// Test immediato per verificare che il sistema funzioni
setTimeout(() => {
    if (window.fpNoticeManager) {
        console.log('Notice Manager inizializzato correttamente');
        // Test con un notice di info
        window.fpNoticeManager.info('Sistema di notifiche attivo!', 3000);
    } else {
        console.error('Notice Manager non inizializzato');
    }
}, 1000);

// Fallback: assicuriamo che il form sia sempre visibile anche se JavaScript fallisce
document.addEventListener('DOMContentLoaded', function() {
    console.log('Fallback: verifico che il form sia visibile');
    
    // Trova il form
    const form = document.getElementById('fp-resv-default') || document.querySelector('.fp-resv-simple');
    if (!form) {
        console.error('Form non trovato nel fallback');
        return;
    }
    
    // Assicurati che il form sia visibile
    form.style.display = 'block';
    form.style.visibility = 'visible';
    form.style.opacity = '1';
    
    // Assicurati che almeno il primo step sia visibile
    const firstStep = form.querySelector('.fp-step:first-child');
    if (firstStep) {
        firstStep.style.display = 'block';
        firstStep.style.visibility = 'visible';
        firstStep.style.opacity = '1';
        firstStep.style.position = 'relative';
        firstStep.style.transform = 'translateX(0)';
        firstStep.classList.add('active');
        
        console.log('Fallback: primo step reso visibile');
    }
    
    // Assicurati che tutti i pulsanti siano cliccabili
    const buttons = form.querySelectorAll('button, input[type="button"], input[type="submit"]');
    buttons.forEach(btn => {
        btn.style.pointerEvents = 'auto';
        btn.style.cursor = 'pointer';
        btn.style.userSelect = 'none';
    });
    
    console.log('Fallback: form reso visibile e funzionante');
});

// Esempi di utilizzo (da rimuovere in produzione)
// window.fpNoticeManager.success('Prenotazione completata con successo!');
// window.fpNoticeManager.error('Errore durante l\'invio della prenotazione');
// window.fpNoticeManager.warning('Attenzione: alcuni campi sono obbligatori');
// window.fpNoticeManager.info('Informazione: il ristorante è chiuso il lunedì');
</script>

<script type="text/javascript" src="<?php echo esc_url(plugins_url('assets/js/form-simple.js', dirname(__FILE__, 2))); ?>"></script>
