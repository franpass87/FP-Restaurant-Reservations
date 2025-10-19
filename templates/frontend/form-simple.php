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

<!-- FORM SEMPLICE ATTIVO: <?php echo date('H:i:s'); ?> -->
        <div id="<?php echo esc_attr($formId); ?>" class="fp-resv-simple">
            <!-- FORM SEMPLICE ATTIVO: <?php echo date('H:i:s'); ?> -->
            <style>
        .fp-resv-simple {
            max-width: 480px;
            margin: 0 auto;
            padding: 16px 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
            position: relative;
        }
        
        .fp-resv-simple::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #000000 0%, #333333 50%, #000000 100%);
            border-radius: 12px 12px 0 0;
        }
        
        .fp-resv-simple h2 {
            color: #000000;
            margin-bottom: 12px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }
        
        .fp-step {
            display: none;
            padding: 8px 0;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            visibility: hidden;
        }
        
        .fp-step.active {
            display: block;
            opacity: 1;
            transform: translateX(0);
            position: relative;
            visibility: visible;
        }
        
        .fp-step.prev {
            opacity: 0;
            transform: translateX(-20px);
        }
        
        .fp-steps-container {
            position: relative;
            min-height: 200px;
        }
        
        .fp-step h3 {
            color: #000000;
            margin-bottom: 16px;
            font-size: 16px;
            font-weight: 500;
            letter-spacing: -0.2px;
            position: relative;
            padding-bottom: 8px;
        }
        
        .fp-step h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background: #000000;
            border-radius: 1px;
        }
        
        .fp-field {
            margin-bottom: 16px;
        }
        
        .fp-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c2c2c;
            font-size: 13px;
            letter-spacing: 0.2px;
        }
        
        .fp-field input,
        .fp-field select,
        .fp-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 12px;
            font-size: 14px;
            box-sizing: border-box;
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            color: #374151;
            transition: all 0.2s ease;
            font-family: inherit;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);
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
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            border-radius: 12px;
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
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px 0 rgba(55, 65, 81, 0.15);
        }
        
        .fp-meal-btn:hover::before {
            left: 100%;
        }
        
        .fp-meal-btn.selected {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: #ffffff;
            border-color: #374151;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px 0 rgba(55, 65, 81, 0.25);
        }
        
        .fp-buttons {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
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
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: #ffffff;
            border-color: #374151;
            box-shadow: 0 4px 14px 0 rgba(55, 65, 81, 0.25);
            font-weight: 600;
            letter-spacing: 0.025em;
        }
        
        .fp-btn-primary:hover {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border-color: #1f2937;
            box-shadow: 0 6px 20px 0 rgba(55, 65, 81, 0.35);
            transform: translateY(-1px);
        }
        
        .fp-btn-secondary {
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            color: #374151;
            border-color: #d1d5db;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);
            font-weight: 500;
        }
        
        .fp-btn-secondary:hover {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            border-color: #9ca3af;
            box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.12);
            transform: translateY(-1px);
        }
        
        .fp-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
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
            background: #f0f0f0;
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
            width: 18px !important;
            height: 18px !important;
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
            align-items: center !important;
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
            gap: 20px;
            margin: 20px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 12px;
            border: 2px solid #e8e8e8;
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
            font-size: 36px;
            font-weight: bold;
            color: #000000;
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
                margin: 12px;
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
                padding: 10px 12px;
                font-size: 12px;
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
        
        <!-- Data -->
        <div class="fp-field">
            <label for="reservation-date">Data della prenotazione *</label>
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
            <label>Numero di persone</label>
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
            <label>Orario preferito</label>
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
            <div style="display: flex; gap: 8px; align-items: center;">
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
                    <input type="checkbox" name="fp_resv_wheelchair_table" value="1" style="width: 18px; height: 18px; margin: 0; cursor: pointer;">
                    <span>Tavolo accessibile per sedia a rotelle</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="fp_resv_pets" value="1" style="width: 18px; height: 18px; margin: 0; cursor: pointer;">
                    <span>Accompagnato da animale domestico</span>
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
                <input type="checkbox" name="fp_resv_consent" required style="width: 18px; height: 18px; margin: 0; margin-top: 2px; cursor: pointer; flex-shrink: 0;">
                <span>Accetto la <a href="#" target="_blank">Privacy Policy</a> e il trattamento dei miei dati personali *</span>
            </label>
        </div>
        <div class="fp-field">
            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="fp_resv_marketing_consent" value="1" style="width: 18px; height: 18px; margin: 0; margin-top: 2px; cursor: pointer; flex-shrink: 0;">
                <span>Acconsento al trattamento dei dati per comunicazioni marketing (opzionale)</span>
            </label>
        </div>
    </div>

    <!-- Step 4: Riepilogo -->
    <div class="fp-step" data-step="4">
        <h3>4. Riepilogo Prenotazione</h3>
        <div class="fp-summary">
            <div class="fp-summary-section">
                <h4>📅 Dettagli Prenotazione</h4>
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

<script type="text/javascript" src="<?php echo esc_url(plugins_url('assets/js/form-simple.js', dirname(__FILE__, 2))); ?>"></script>
