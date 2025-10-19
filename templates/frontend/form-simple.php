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
    <style>
        .fp-resv-simple {
            max-width: 480px;
            margin: 0 auto;
            padding: 24px 20px;
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
            margin-bottom: 20px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }
        
        .fp-step {
            display: none;
            padding: 16px 0;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .fp-step.active {
            display: block;
            opacity: 1;
            transform: translateX(0);
        }
        
        .fp-step.prev {
            opacity: 0;
            transform: translateX(-20px);
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
            border: 1.5px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .fp-field input:focus,
        .fp-field select:focus,
        .fp-field textarea:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        
        .fp-field input::placeholder,
        .fp-field textarea::placeholder {
            color: #999999;
        }
        
        .fp-meals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 8px;
            margin-top: 6px;
        }
        
        .fp-meal-btn {
            padding: 12px 16px;
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
        
        .fp-meal-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0,0,0,0.05), transparent);
            transition: left 0.5s ease;
        }
        
        .fp-meal-btn:hover {
            border-color: #000000;
            background: #fafafa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .fp-meal-btn:hover::before {
            left: 100%;
        }
        
        .fp-meal-btn.selected {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
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
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }
        
        .fp-btn-primary:hover {
            background: #1a1a1a;
            border-color: #1a1a1a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .fp-btn-secondary {
            background: #ffffff;
            color: #2c2c2c;
            border-color: #e8e8e8;
        }
        
        .fp-btn-secondary:hover {
            background: #f8f8f8;
            border-color: #000000;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .fp-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .fp-progress {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            padding: 16px 0;
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
        
        /* Checkbox styling */
        .fp-field input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            transform: scale(1.2);
        }
        
        .fp-field label[style*="display: flex"] {
            align-items: flex-start;
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
            line-height: 1.5;
        }
        
        /* Phone prefix styling */
        .fp-field div[style*="display: flex"] {
            align-items: center;
        }
        
        .fp-field div[style*="display: flex"] select {
            width: 120px;
            margin-right: 8px;
        }
        
        .fp-field div[style*="display: flex"] input {
            flex: 1;
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
        }
    </style>

    <h2>Prenota il Tuo Tavolo</h2>
    
    <!-- Progress Bar -->
    <div class="fp-progress">
        <div class="fp-progress-step active" data-step="1">1</div>
        <div class="fp-progress-step" data-step="2">2</div>
        <div class="fp-progress-step" data-step="3">3</div>
        <div class="fp-progress-step" data-step="4">4</div>
        <div class="fp-progress-step" data-step="5">5</div>
    </div>

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

    <!-- Step 2: Data -->
    <div class="fp-step" data-step="2">
        <h3>2. Scegli la Data</h3>
        <div class="fp-field">
            <label for="reservation-date">Data</label>
            <input type="date" id="reservation-date" name="date" required>
            <div id="date-loading" style="display: none; margin-top: 8px; font-size: 13px; color: #666;">
                ⏳ Caricamento date disponibili...
            </div>
            <div id="date-info" style="display: none; margin-top: 8px; font-size: 13px; color: #333;">
                📅 Seleziona una data disponibile per il servizio scelto
            </div>
        </div>
    </div>

    <!-- Step 3: Persone -->
    <div class="fp-step" data-step="3">
        <h3>3. Quante Persone?</h3>
        <div class="fp-field">
            <label for="party-size">Numero di persone</label>
            <select id="party-size" name="party" required>
                <option value="">Seleziona...</option>
                <?php 
                $defaultPartySize = $config['defaults']['partySize'] ?? 2;
                $maxPartySize = 20; // Potrebbe essere configurato nel backend
                for ($i = 1; $i <= $maxPartySize; $i++): 
                ?>
                    <option value="<?php echo $i; ?>" <?php echo $i == $defaultPartySize ? 'selected' : ''; ?>>
                        <?php echo $i; ?> <?php echo $i == 1 ? 'persona' : 'persone'; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <!-- Step 4: Orari -->
    <div class="fp-step" data-step="4">
        <h3>4. Scegli l'Orario</h3>
        <div class="fp-field">
            <div id="time-slots-container">
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
    </div>

    <!-- Step 5: Dettagli -->
    <div class="fp-step" data-step="5">
        <h3>5. I Tuoi Dettagli</h3>
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
            <div style="display: flex; gap: 12px; align-items: center;">
                <select name="fp_resv_phone_prefix" style="width: 120px; padding: 12px 14px; border: 1.5px solid #e8e8e8; border-radius: 8px; font-size: 14px; background: #ffffff; color: #000000; transition: all 0.2s ease; font-family: inherit;">
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
                <input type="tel" id="customer-phone" name="fp_resv_phone" required autocomplete="tel" placeholder="123 456 7890" style="flex: 1; padding: 12px 14px; border: 1.5px solid #e8e8e8; border-radius: 8px; font-size: 14px; background: #ffffff; color: #000000; transition: all 0.2s ease; font-family: inherit;">
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
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="fp_resv_wheelchair_table" value="1">
                    <span>Tavolo accessibile per sedia a rotelle</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="fp_resv_pets" value="1">
                    <span>Accompagnato da animale domestico</span>
                </label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="high-chair-count">Seggioloni:</label>
                    <input type="number" id="high-chair-count" name="fp_resv_high_chair_count" value="0" min="0" max="10" style="width: 70px; padding: 8px 10px; border: 1.5px solid #e8e8e8; border-radius: 6px; font-size: 13px; background: #ffffff; color: #000000; transition: all 0.2s ease; font-family: inherit; text-align: center;">
                </div>
            </div>
        </div>
        
        <!-- Privacy -->
        <div class="fp-field">
            <label style="display: flex; align-items: flex-start; gap: 8px;">
                <input type="checkbox" name="fp_resv_consent" required style="margin-top: 4px;">
                <span>Accetto la <a href="#" target="_blank">Privacy Policy</a> e il trattamento dei miei dati personali *</span>
            </label>
        </div>
        <div class="fp-field">
            <label style="display: flex; align-items: flex-start; gap: 8px;">
                <input type="checkbox" name="fp_resv_marketing_consent" value="1" style="margin-top: 4px;">
                <span>Acconsento al trattamento dei dati per comunicazioni marketing (opzionale)</span>
            </label>
        </div>
    </div>

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
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('<?php echo esc_js($formId); ?>');
    let currentStep = 1;
    const totalSteps = 5;
    
    const steps = form.querySelectorAll('.fp-step');
    const progressSteps = form.querySelectorAll('.fp-progress-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-btn');
    
    // Meal selection - dynamic
    let mealBtns = form.querySelectorAll('.fp-meal-btn');
    let selectedMeal = null;
    let selectedTime = null;

    function setupMealButtons() {
        mealBtns = form.querySelectorAll('.fp-meal-btn');
        mealBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                mealBtns.forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                selectedMeal = this.dataset.meal;
                
                // Load available dates for selected meal
                loadAvailableDates(selectedMeal);
            });
        });
    }
    
    // Initialize meal buttons
    setupMealButtons();
    
    // Navigation
    function showStep(step) {
        steps.forEach(s => s.classList.remove('active'));
        progressSteps.forEach(p => p.classList.remove('active', 'completed'));
        
        const currentStepEl = form.querySelector(`[data-step="${step}"]`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }
        
        // Update progress
        for (let i = 1; i <= step; i++) {
            const progressStep = form.querySelector(`[data-step="${i}"]`);
            if (progressStep) {
                if (i < step) {
                    progressStep.classList.add('completed');
                } else if (i === step) {
                    progressStep.classList.add('active');
                }
            }
        }
        
        // Update buttons
        prevBtn.style.display = step > 1 ? 'block' : 'none';
        nextBtn.style.display = step < totalSteps ? 'block' : 'none';
        submitBtn.style.display = step === totalSteps ? 'block' : 'none';
    }
    
    function validateStep(step) {
        switch(step) {
            case 1:
                return selectedMeal !== null;
            case 2:
                const date = document.getElementById('reservation-date').value;
                return date !== '';
            case 3:
                const party = document.getElementById('party-size').value;
                return party !== '';
            case 4:
                return selectedTime !== null;
            case 5:
                const firstName = document.getElementById('customer-first-name').value;
                const lastName = document.getElementById('customer-last-name').value;
                const email = document.getElementById('customer-email').value;
                const phone = document.getElementById('customer-phone').value;
                const consent = document.querySelector('input[name="fp_resv_consent"]').checked;
                return firstName !== '' && lastName !== '' && email !== '' && phone !== '' && consent;
        }
        return true;
    }
    
    nextBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            currentStep++;
            showStep(currentStep);
        } else {
            alert('Per favore completa tutti i campi richiesti.');
        }
    });
    
    prevBtn.addEventListener('click', function() {
        currentStep--;
        showStep(currentStep);
    });
    
    submitBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            // Get phone data
            const phonePrefix = document.querySelector('select[name="fp_resv_phone_prefix"]').value;
            const phoneNumber = document.getElementById('customer-phone').value;
            const fullPhone = '+' + phonePrefix + ' ' + phoneNumber;
            
            // Update hidden fields
            document.querySelector('input[name="fp_resv_meal"]').value = selectedMeal;
            document.querySelector('input[name="fp_resv_date"]').value = document.getElementById('reservation-date').value;
            document.querySelector('input[name="fp_resv_party"]').value = document.getElementById('party-size').value;
            document.querySelector('input[name="fp_resv_phone_cc"]').value = phonePrefix;
            document.querySelector('input[name="fp_resv_phone_local"]').value = phoneNumber;
            document.querySelector('input[name="fp_resv_phone_e164"]').value = fullPhone;
            
            const formData = {
                meal: selectedMeal,
                date: document.getElementById('reservation-date').value,
                time: selectedTime,
                party: document.getElementById('party-size').value,
                firstName: document.getElementById('customer-first-name').value,
                lastName: document.getElementById('customer-last-name').value,
                email: document.getElementById('customer-email').value,
                phone: fullPhone,
                phonePrefix: phonePrefix,
                phoneNumber: phoneNumber,
                occasion: document.getElementById('occasion').value,
                notes: document.getElementById('notes').value,
                allergies: document.getElementById('allergies').value,
                wheelchairTable: document.querySelector('input[name="fp_resv_wheelchair_table"]').checked,
                pets: document.querySelector('input[name="fp_resv_pets"]').checked,
                highChairCount: document.getElementById('high-chair-count').value,
                consent: document.querySelector('input[name="fp_resv_consent"]').checked,
                marketingConsent: document.querySelector('input[name="fp_resv_marketing_consent"]').checked
            };
            
            // Submit form
            console.log('Form data completo:', formData);
            alert('Prenotazione inviata! (Questo è un demo)\n\nDati raccolti:\n- Servizio: ' + formData.meal + '\n- Data: ' + formData.date + '\n- Orario: ' + formData.time + '\n- Persone: ' + formData.party + '\n- Nome: ' + formData.firstName + ' ' + formData.lastName + '\n- Email: ' + formData.email + '\n- Telefono: ' + formData.phone);
        } else {
            alert('Per favore completa tutti i campi richiesti.');
        }
    });
    
    // Set minimum date to today and load available dates
    const dateInput = document.getElementById('reservation-date');
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    // Store available dates globally
    let availableDates = [];
    
    // Load available dates when meal is selected
    function loadAvailableDates(meal) {
        if (!meal) return;
        
        // Show loading indicator
        const loadingEl = document.getElementById('date-loading');
        const infoEl = document.getElementById('date-info');
        loadingEl.style.display = 'block';
        infoEl.style.display = 'none';
        
        const from = today;
        const to = new Date();
        to.setMonth(to.getMonth() + 3); // 3 months ahead
        const toDate = to.toISOString().split('T')[0];
        
        fetch(`/wp-json/fp-resv/v1/available-days?from=${from}&to=${toDate}&meal=${meal}`)
            .then(response => response.json())
            .then(data => {
                // Hide loading indicator
                loadingEl.style.display = 'none';
                
                if (data && data.days) {
                    // Store available dates
                    availableDates = Object.keys(data.days).filter(date => {
                        return data.days[date] && data.days[date].available;
                    });
                    
                    // Show info if dates are available
                    if (availableDates.length > 0) {
                        infoEl.style.display = 'block';
                    }
                    
                    // Update date input with available dates info
                    updateDateInput();
                    
                    console.log('Date disponibili per', meal, ':', availableDates);
                } else {
                    // No data available, allow all dates
                    availableDates = [];
                    infoEl.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Errore nel caricamento date disponibili:', error);
                // Hide loading indicator
                loadingEl.style.display = 'none';
                // In case of error, allow all dates
                availableDates = [];
                infoEl.style.display = 'none';
            });
    }
    
    // Update date input with availability info
    function updateDateInput() {
        // Remove existing event listeners
        const newDateInput = dateInput.cloneNode(true);
        dateInput.parentNode.replaceChild(newDateInput, dateInput);
        
        // Add new event listener
        newDateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (selectedDate && availableDates.length > 0 && !availableDates.includes(selectedDate)) {
                alert('Questa data non è disponibile per il servizio selezionato. Scegli un\'altra data.');
                this.value = '';
                return;
            }
            
            // If date is valid, proceed to next step
            if (selectedDate && validateStep(2)) {
                currentStep++;
                showStep(currentStep);
            }
        });
        
        // Update reference
        dateInput = newDateInput;
    }
    
    // Load available dates when meal is selected
    mealBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const meal = this.dataset.meal;
            loadAvailableDates(meal);
        });
    });
    
    // Load available time slots when date is selected
    function loadAvailableTimeSlots(meal, date, party) {
        if (!meal || !date || !party) return;
        
        const loadingEl = document.getElementById('time-loading');
        const slotsEl = document.getElementById('time-slots');
        const infoEl = document.getElementById('time-info');
        
        loadingEl.style.display = 'block';
        slotsEl.innerHTML = '';
        infoEl.style.display = 'none';
        
        fetch(`/wp-json/fp-resv/v1/available-slots?meal=${meal}&date=${date}&party=${party}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data && data.slots && data.slots.length > 0) {
                    slotsEl.innerHTML = '';
                    data.slots.forEach(slot => {
                        const slotBtn = document.createElement('button');
                        slotBtn.type = 'button';
                        slotBtn.className = 'fp-time-slot';
                        slotBtn.textContent = slot.time;
                        slotBtn.dataset.time = slot.time;
                        slotBtn.dataset.slotStart = slot.slot_start;
                        
                        if (slot.available) {
                            slotBtn.addEventListener('click', function() {
                                document.querySelectorAll('.fp-time-slot').forEach(s => s.classList.remove('selected'));
                                this.classList.add('selected');
                                selectedTime = this.dataset.time;
                                
                                // Update hidden fields
                                document.querySelector('input[name="fp_resv_time"]').value = this.dataset.time;
                                document.querySelector('input[name="fp_resv_slot_start"]').value = this.dataset.slotStart;
                                
                                // Auto-advance to next step
                                if (validateStep(4)) {
                                    currentStep++;
                                    showStep(currentStep);
                                }
                            });
                        } else {
                            slotBtn.classList.add('disabled');
                            slotBtn.disabled = true;
                        }
                        
                        slotsEl.appendChild(slotBtn);
                    });
                    
                    infoEl.style.display = 'block';
                } else {
                    slotsEl.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Nessun orario disponibile per questa data</p>';
                }
            })
            .catch(error => {
                console.error('Errore nel caricamento orari:', error);
                loadingEl.style.display = 'none';
                slotsEl.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Errore nel caricamento degli orari</p>';
            });
    }
    
    // Load time slots when date and party are selected
    document.getElementById('reservation-date').addEventListener('change', function() {
        const date = this.value;
        const party = document.getElementById('party-size').value;
        if (date && party && selectedMeal) {
            loadAvailableTimeSlots(selectedMeal, date, party);
        }
    });
    
    document.getElementById('party-size').addEventListener('change', function() {
        const party = this.value;
        const date = document.getElementById('reservation-date').value;
        if (date && party && selectedMeal) {
            loadAvailableTimeSlots(selectedMeal, date, party);
        }
    });
});
</script>
