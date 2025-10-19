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

<div id="<?php echo esc_attr($formId); ?>" class="fp-resv-simple">
    <style>
        .fp-resv-simple {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #e5e5e5;
        }
        
        .fp-resv-simple h2 {
            color: #000000;
            margin-bottom: 20px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .fp-step {
            display: none;
            padding: 15px 0;
        }
        
        .fp-step.active {
            display: block;
        }
        
        .fp-step h3 {
            color: #000000;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 8px;
        }
        
        .fp-field {
            margin-bottom: 18px;
        }
        
        .fp-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333333;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .fp-field input,
        .fp-field select,
        .fp-field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
            transition: all 0.3s ease;
        }
        
        .fp-field input:focus,
        .fp-field select:focus,
        .fp-field textarea:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }
        
        .fp-field input::placeholder,
        .fp-field textarea::placeholder {
            color: #999999;
        }
        
        .fp-meals {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .fp-meal-btn {
            flex: 1;
            min-width: 100px;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
            color: #333333;
        }
        
        .fp-meal-btn:hover {
            border-color: #666666;
            background: #f8f8f8;
        }
        
        .fp-meal-btn.selected {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }
        
        .fp-buttons {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .fp-btn {
            padding: 10px 20px;
            border: 2px solid;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .fp-btn-primary {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }
        
        .fp-btn-primary:hover {
            background: #333333;
            border-color: #333333;
        }
        
        .fp-btn-secondary {
            background: #ffffff;
            color: #000000;
            border-color: #e0e0e0;
        }
        
        .fp-btn-secondary:hover {
            background: #f8f8f8;
            border-color: #666666;
        }
        
        .fp-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .fp-progress {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
            padding: 15px 0;
        }
        
        .fp-progress-step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 6px;
            font-weight: 700;
            color: #999999;
            font-size: 12px;
            border: 2px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .fp-progress-step.active {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }
        
        .fp-progress-step.completed {
            background: #666666;
            color: #ffffff;
            border-color: #666666;
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
        
        /* Responsive */
        @media (max-width: 640px) {
            .fp-resv-simple {
                margin: 10px;
                padding: 15px;
            }
            
            .fp-meals {
                flex-direction: column;
            }
            
            .fp-meal-btn {
                min-width: auto;
            }
            
            .fp-buttons {
                flex-direction: column;
            }
            
            .fp-progress-step {
                width: 28px;
                height: 28px;
                margin: 0 3px;
            }
        }
    </style>

    <h2>🍽️ Prenota il Tuo Tavolo</h2>
    
    <!-- Progress Bar -->
    <div class="fp-progress">
        <div class="fp-progress-step active" data-step="1">1</div>
        <div class="fp-progress-step" data-step="2">2</div>
        <div class="fp-progress-step" data-step="3">3</div>
        <div class="fp-progress-step" data-step="4">4</div>
    </div>

    <!-- Step 1: Servizio -->
    <div class="fp-step active" data-step="1">
        <h3>1. Scegli il Servizio</h3>
        <div class="fp-field">
            <div class="fp-meals">
                <button type="button" class="fp-meal-btn" data-meal="pranzo">
                    🍽️ Pranzo
                </button>
                <button type="button" class="fp-meal-btn" data-meal="aperitivo">
                    🥂 Aperitivo
                </button>
                <button type="button" class="fp-meal-btn" data-meal="cena">
                    🌙 Cena
                </button>
            </div>
        </div>
    </div>

    <!-- Step 2: Data -->
    <div class="fp-step" data-step="2">
        <h3>2. Scegli la Data</h3>
        <div class="fp-field">
            <label for="reservation-date">Data</label>
            <input type="date" id="reservation-date" name="date" required>
        </div>
    </div>

    <!-- Step 3: Persone -->
    <div class="fp-step" data-step="3">
        <h3>3. Quante Persone?</h3>
        <div class="fp-field">
            <label for="party-size">Numero di persone</label>
            <select id="party-size" name="party" required>
                <option value="">Seleziona...</option>
                <option value="1">1 persona</option>
                <option value="2">2 persone</option>
                <option value="3">3 persone</option>
                <option value="4">4 persone</option>
                <option value="5">5 persone</option>
                <option value="6">6 persone</option>
                <option value="7">7 persone</option>
                <option value="8">8 persone</option>
            </select>
        </div>
    </div>

    <!-- Step 4: Dettagli -->
    <div class="fp-step" data-step="4">
        <h3>4. I Tuoi Dettagli</h3>
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
            <div style="display: flex; gap: 10px;">
                <select name="fp_resv_phone_prefix" style="width: 120px; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px;">
                    <option value="39" selected>🇮🇹 +39 (IT)</option>
                    <option value="44">🇬🇧 +44 (EN)</option>
                    <option value="33">🇫🇷 +33 (EN)</option>
                    <option value="49">🇩🇪 +49 (EN)</option>
                    <option value="34">🇪🇸 +34 (EN)</option>
                    <option value="41">🇨🇭 +41 (EN)</option>
                    <option value="43">🇦🇹 +43 (EN)</option>
                    <option value="32">🇧🇪 +32 (EN)</option>
                    <option value="31">🇳🇱 +31 (EN)</option>
                    <option value="45">🇩🇰 +45 (EN)</option>
                    <option value="46">🇸🇪 +46 (EN)</option>
                    <option value="47">🇳🇴 +47 (EN)</option>
                    <option value="358">🇫🇮 +358 (EN)</option>
                    <option value="1">🇺🇸 +1 (EN)</option>
                    <option value="86">🇨🇳 +86 (EN)</option>
                    <option value="81">🇯🇵 +81 (EN)</option>
                    <option value="82">🇰🇷 +82 (EN)</option>
                    <option value="91">🇮🇳 +91 (EN)</option>
                    <option value="55">🇧🇷 +55 (EN)</option>
                    <option value="61">🇦🇺 +61 (EN)</option>
                    <option value="27">🇿🇦 +27 (EN)</option>
                </select>
                <input type="tel" id="customer-phone" name="fp_resv_phone" required autocomplete="tel" placeholder="123 456 7890" style="flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px;">
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
            <textarea id="notes" name="fp_resv_notes" rows="3" placeholder="Es. preferenza per un tavolo particolare, orario flessibile, ecc." style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; box-sizing: border-box;"></textarea>
        </div>
        <div class="fp-field">
            <label for="allergies">Allergie/Intolleranze (opzionale)</label>
            <textarea id="allergies" name="fp_resv_allergies" rows="3" placeholder="Indica eventuali allergie o intolleranze alimentari" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; box-sizing: border-box;"></textarea>
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
                    <input type="number" id="high-chair-count" name="fp_resv_high_chair_count" value="0" min="0" max="10" style="width: 80px; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
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
    <input type="hidden" name="fp_resv_location" value="default">
    <input type="hidden" name="fp_resv_locale" value="it_IT">
    <input type="hidden" name="fp_resv_language" value="it">
    <input type="hidden" name="fp_resv_currency" value="EUR">
    <input type="hidden" name="fp_resv_phone_e164" value="">
    <input type="hidden" name="fp_resv_phone_cc" value="39">
    <input type="hidden" name="fp_resv_phone_local" value="">
    <input type="hidden" name="fp_resv_hp" value="" autocomplete="off">
    <input type="hidden" name="fp_resv_policy_version" value="1.0">
    <input type="hidden" name="fp_resv_price_per_person" value="0">

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
    const totalSteps = 4;
    
    const steps = form.querySelectorAll('.fp-step');
    const progressSteps = form.querySelectorAll('.fp-progress-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-btn');
    
    // Meal selection
    const mealBtns = form.querySelectorAll('.fp-meal-btn');
    let selectedMeal = null;
    
    mealBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            mealBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            selectedMeal = this.dataset.meal;
        });
    });
    
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
            alert('Prenotazione inviata! (Questo è un demo)\n\nDati raccolti:\n- Servizio: ' + formData.meal + '\n- Data: ' + formData.date + '\n- Persone: ' + formData.party + '\n- Nome: ' + formData.firstName + ' ' + formData.lastName + '\n- Email: ' + formData.email + '\n- Telefono: ' + formData.phone);
        } else {
            alert('Per favore completa tutti i campi richiesti.');
        }
    });
    
    // Set minimum date to today
    const dateInput = document.getElementById('reservation-date');
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
});
</script>
