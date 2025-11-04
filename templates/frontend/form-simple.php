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

<!-- CSS CRITICO STATICO - SEMPRE CARICATO SUBITO -->
<style id="fp-resv-ultra-critical-css" type="text/css">
/* RIMUOVI <br> inseriti da wpautop/WPBakery nei label */
#fp-resv-default label br,
.fp-resv-simple label br,
label br {
    display: none !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 0 !important;
}

/* ASTERISCHI INLINE - SPECIFICITÀ NUCLEARE (funziona con qualsiasi ID) */
html body #fp-resv-default abbr.fp-required,
html body #fp-resv-default .fp-required,
html body .fp-resv-simple abbr.fp-required,
html body .fp-resv-simple .fp-required,
html body abbr.fp-required,
html body .fp-required,
#fp-resv-default abbr.fp-required,
.fp-resv-simple abbr.fp-required,
abbr.fp-required,
.fp-required {
    display: inline !important;
    white-space: nowrap !important;
    overflow: visible !important;
    color: #dc2626 !important;
    text-decoration: none !important;
    font-weight: bold !important;
    margin-left: 2px !important;
    margin-right: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    padding: 0 !important;
    float: none !important;
    position: relative !important;
    vertical-align: baseline !important;
    line-height: inherit !important;
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
html body .fp-required::after,
abbr.fp-required::before,
abbr.fp-required::after {
    content: none !important;
    display: none !important;
}

/* CHECKBOX WRAPPER - ALLINEAMENTO (funziona con qualsiasi ID) */
html body #fp-resv-default .fp-checkbox-wrapper,
html body .fp-resv-simple .fp-checkbox-wrapper,
html body .fp-checkbox-wrapper,
#fp-resv-default .fp-checkbox-wrapper,
.fp-resv-simple .fp-checkbox-wrapper,
.fp-checkbox-wrapper {
    display: flex !important;
    flex-direction: row !important;
    align-items: flex-start !important;
    gap: 10px !important;
    margin-bottom: 8px !important;
}

/* CHECKBOX LABEL */
html body #fp-resv-default .fp-checkbox-wrapper label,
html body .fp-resv-simple .fp-checkbox-wrapper label,
html body .fp-checkbox-wrapper label,
#fp-resv-default .fp-checkbox-wrapper label,
.fp-resv-simple .fp-checkbox-wrapper label,
.fp-checkbox-wrapper label {
    cursor: pointer !important;
    display: block !important;
    flex: 1 !important;
    overflow: visible !important;
    line-height: 1.5 !important;
    margin-bottom: 0 !important;
    font-weight: 400 !important;
    color: #374151 !important;
    padding-top: 1px !important;
}

/* CHECKBOX INPUT - VISIBILITÀ */
html body #fp-resv-default input[type="checkbox"].fp-checkbox,
html body .fp-resv-simple input[type="checkbox"].fp-checkbox,
html body input[type="checkbox"].fp-checkbox,
#fp-resv-default input[type="checkbox"].fp-checkbox,
.fp-resv-simple input[type="checkbox"].fp-checkbox,
input[type="checkbox"].fp-checkbox {
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    padding: 0 !important;
    margin: 0 !important;
    opacity: 1 !important;
    visibility: visible !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    border: 2px solid #d1d5db !important;
    border-radius: 4px !important;
    background: #ffffff !important;
    position: relative !important;
    vertical-align: middle !important;
    z-index: 1 !important;
}

</style>

<?php 
// Inietta CSS con JavaScript per bypassare WPBakery/Salient
$cssPath = dirname(dirname(__DIR__)) . '/assets/css/form-simple-inline.css';
if (file_exists($cssPath)) {
    $css = file_get_contents($cssPath);
    // Escape per JavaScript
    $cssEscaped = str_replace('</style>', '<\\/style>', $css);
    $cssJson = json_encode($cssEscaped);
    ?>
<script>
(function() {
    // === STEP 1: CSS CRITICO (SEMPRE, anche se CSS principale già caricato) ===
    if (!document.getElementById('fp-resv-critical-css')) {
        var criticalCss = `
/* ASTERISCHI INLINE - SPECIFICITÀ NUCLEARE */
html body .fp-resv-simple abbr.fp-required,
html body abbr.fp-required,
abbr.fp-required,
.fp-required {
    display: inline !important;
    white-space: nowrap !important;
    overflow: visible !important;
    color: #dc2626 !important;
    margin-left: 2px !important;
    margin-right: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    padding: 0 !important;
    float: none !important;
    position: relative !important;
    vertical-align: baseline !important;
    line-height: inherit !important;
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    min-height: 0 !important;
}

/* CHECKBOX WRAPPER */
html body .fp-checkbox-wrapper,
.fp-checkbox-wrapper {
    display: flex !important;
    flex-direction: row !important;
    align-items: flex-start !important;
    gap: 10px !important;
}

html body .fp-checkbox-wrapper label,
.fp-checkbox-wrapper label {
    display: block !important;
    flex: 1 !important;
    overflow: visible !important;
    line-height: 1.5 !important;
}

/* CHECKBOX INPUT */
html body input[type="checkbox"].fp-checkbox,
input[type="checkbox"].fp-checkbox {
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    opacity: 1 !important;
    visibility: visible !important;
    display: inline-block !important;
    -webkit-appearance: none !important;
    appearance: none !important;
    border: 2px solid #d1d5db !important;
    background: #ffffff !important;
    flex-shrink: 0 !important;
}
`;
    
    var criticalStyle = document.createElement('style');
    criticalStyle.id = 'fp-resv-critical-css';
    criticalStyle.type = 'text/css';
    if (criticalStyle.styleSheet) {
        criticalStyle.styleSheet.cssText = criticalCss;
    } else {
        criticalStyle.appendChild(document.createTextNode(criticalCss));
    }
        document.head.appendChild(criticalStyle);
        console.log('[FP-RESV] ✅ CSS CRITICO caricato (asterischi + checkbox)');
    }
    
    // === STEP 2: CSS COMPLETO (solo se non già caricato) ===
    if (document.getElementById('fp-resv-simple-inline-style')) {
        console.log('[FP-RESV] ℹ️ CSS completo già caricato, skip');
        return;
    }
    
    var css = <?php echo $cssJson; ?>;
    var style = document.createElement('style');
    style.id = 'fp-resv-simple-inline-style';
    style.type = 'text/css';
    if (style.styleSheet) {
        style.styleSheet.cssText = css;
    } else {
        style.appendChild(document.createTextNode(css));
    }
    document.head.appendChild(style);
    console.log('[FP-RESV] ✅ CSS completo iniettato (' + css.length + ' caratteri)');
})();
</script>
<?php } ?>

<div id="<?php echo esc_attr($formId); ?>" class="fp-resv-simple">
    <!-- Header con titolo e bottone PDF -->
    <div class="fp-resv-header">
        <div class="fp-resv-header__titles">
            <h2>Prenota il Tuo Tavolo</h2>
        </div>
        <?php 
        $pdfUrl = $context['pdf_url'] ?? '';
        $pdfLabel = $strings['pdf_label'] ?? __('Scopri il Menu', 'fp-restaurant-reservations');
        if ($pdfUrl !== '') : 
        ?>
            <a 
                class="fp-btn-pdf" 
                href="<?php echo esc_url($pdfUrl); ?>" 
                target="_blank" 
                rel="noopener"
                aria-label="<?php echo esc_attr($pdfLabel); ?>"
            >
                <span class="fp-btn-pdf__icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                </span>
                <span class="fp-btn-pdf__label"><?php echo esc_html($pdfLabel); ?></span>
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Notice Container inline -->
    <div id="fp-notice-container" class="fp-notice-container" role="alert" aria-live="assertive">
        <!-- Le notice verranno inserite qui dinamicamente -->
    </div>
    
    <!-- Progress indicator con accessibilità migliorata -->
    <div class="fp-progress" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="4" aria-label="Progresso prenotazione: Step 1 di 4">
        <div class="fp-progress-step active" data-step="1" aria-current="step">
            <span class="screen-reader-text">Step 1: </span>1
        </div>
        <div class="fp-progress-step" data-step="2">
            <span class="screen-reader-text">Step 2: </span>2
        </div>
        <div class="fp-progress-step" data-step="3">
            <span class="screen-reader-text">Step 3: </span>3
        </div>
        <div class="fp-progress-step" data-step="4">
            <span class="screen-reader-text">Step 4: </span>4
        </div>
    </div>
    
    <!-- Announcement region for step changes -->
    <div role="status" aria-live="polite" aria-atomic="true" class="screen-reader-text" data-fp-step-announcer>
        Step 1 di 4: Scegli il Servizio
    </div>

    <!-- Steps Container -->
    <div class="fp-steps-container">
        <!-- Step 1: Servizio -->
        <div class="fp-step active" data-step="1">
        <h3>1. Scegli il Servizio</h3>
        
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <!-- DEBUG: Meals Data -->
        <div class="fp-debug-block">
            <strong>🔍 DEBUG MEALS:</strong>
            <pre class="fp-debug-pre"><?php echo htmlspecialchars(print_r($meals, true)); ?></pre>
        </div>
        <?php endif; ?>
        
        <div class="fp-field">
            <div class="fp-meals" id="meal-buttons">
                <?php if (!empty($meals) && is_array($meals)): ?>
                    <?php foreach ($meals as $meal): ?>
                        <?php if (is_array($meal) && isset($meal['key']) && isset($meal['label'])): ?>
                            <button 
                                type="button" 
                                class="fp-meal-btn" 
                                data-meal="<?php echo esc_attr($meal['key']); ?>"
                                data-meal-notice="<?php echo esc_attr($meal['notice'] ?? ''); ?>"
                                data-meal-hint="<?php echo esc_attr($meal['hint'] ?? ''); ?>"
                            >
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
                        <span class="fp-meal-btn__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 6v6l4 2"></path>
                            </svg>
                        </span>
                        <span class="fp-meal-btn__label">Pranzo</span>
                    </button>
                    <button type="button" class="fp-meal-btn" data-meal="cena">
                        <span class="fp-meal-btn__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                            </svg>
                        </span>
                        <span class="fp-meal-btn__label">Cena</span>
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Messaggio del pasto selezionato -->
            <div id="meal-notice" class="fp-meal-notice" role="status" aria-live="polite" hidden>
                <!-- Il messaggio verrà inserito qui dinamicamente -->
            </div>
        </div>
    </div>

    <!-- Step 2: Data, Persone e Orario -->
    <div class="fp-step" data-step="2">
        <h3>2. Scegli Data, Persone e Orario</h3>
        <div class="fp-section-divider"></div>
        
        <!-- Data -->
        <div class="fp-field">
            <label for="reservation-date">
                Data
                <abbr class="fp-required" title="Obbligatorio" aria-label="Campo obbligatorio" style="display:inline!important;white-space:nowrap!important;margin-left:2px!important;color:#dc2626!important;text-decoration:none!important;float:none!important;overflow:visible!important;">*</abbr>
            </label>
            <input type="text" id="reservation-date" name="date" required placeholder="Seleziona una data" readonly aria-describedby="date-hint date-loading">
            <div id="date-loading" class="fp-loading-message" role="status" aria-live="polite" aria-busy="true" hidden>
                <span class="fp-loading-message__spinner" aria-hidden="true">
                    <svg class="fp-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" opacity="0.25"></circle>
                        <path d="M4 12a8 8 0 018-8" opacity="0.75"></path>
                    </svg>
                </span>
                <span class="fp-loading-message__text">Caricamento date disponibili...</span>
            </div>
            <div id="date-info" class="fp-info-message" role="status" hidden>
                <span class="fp-info-message__icon" aria-hidden="true">ℹ️</span>
                <span class="fp-info-message__text">Seleziona una data disponibile per il servizio scelto</span>
            </div>
        </div>
        
        <!-- Persone -->
        <div class="fp-field">
            <label for="party-size">Persone</label>
            <div class="fp-party-selector" role="group" aria-labelledby="party-size-label">
                <button type="button" class="fp-btn-minus" id="party-minus" aria-label="Diminuisci numero persone">−</button>
                <div class="fp-party-display">
                    <span id="party-count" aria-live="polite">2</span>
                    <span id="party-label">persone</span>
                </div>
                <button type="button" class="fp-btn-plus" id="party-plus" aria-label="Aumenta numero persone">+</button>
            </div>
            <input type="hidden" id="party-size" name="party" value="2" required aria-describedby="party-hint">
            <small class="fp-hint" id="party-hint">Numero di persone per la prenotazione</small>
        </div>
        
        <!-- Orari -->
        <div class="fp-field">
            <label for="time-slots">Orario</label>
            <div id="time-loading" class="fp-loading-message" role="status" aria-live="polite" hidden>
                <span class="fp-loading-message__text">⏳ Caricamento orari disponibili...</span>
            </div>
            <div id="time-slots" class="fp-time-slots" role="radiogroup" aria-label="Seleziona orario" aria-describedby="time-hint">
                <!-- Gli orari verranno caricati dinamicamente -->
            </div>
            <div id="time-info" class="fp-info-message" role="status" hidden>
                <span class="fp-info-message__text">Seleziona un orario per continuare</span>
            </div>
        </div>
    </div>

    <!-- Step 3: Dettagli -->
    <div class="fp-step" data-step="3">
        <h3>3. I Tuoi Dettagli</h3>
        <div class="fp-section-divider"></div>
        <div class="fp-field">
            <label for="customer-first-name">
                Nome
                <abbr class="fp-required" title="Obbligatorio" aria-label="Campo obbligatorio" style="display:inline!important;white-space:nowrap!important;margin-left:2px!important;color:#dc2626!important;text-decoration:none!important;float:none!important;overflow:visible!important;">*</abbr>
            </label>
            <input 
                type="text" 
                id="customer-first-name" 
                name="fp_resv_first_name" 
                required 
                autocomplete="given-name"
                aria-describedby="first-name-simple-hint first-name-simple-error"
                aria-invalid="false"
            >
            <small class="fp-error" id="first-name-simple-error" role="alert" aria-live="polite" hidden></small>
        </div>
        <div class="fp-field">
            <label for="customer-last-name">
                Cognome
                <abbr class="fp-required" title="Obbligatorio" aria-label="Campo obbligatorio" style="display:inline!important;white-space:nowrap!important;margin-left:2px!important;color:#dc2626!important;text-decoration:none!important;float:none!important;overflow:visible!important;">*</abbr>
            </label>
            <input 
                type="text" 
                id="customer-last-name" 
                name="fp_resv_last_name" 
                required 
                autocomplete="family-name"
                aria-describedby="last-name-simple-hint last-name-simple-error"
                aria-invalid="false"
            >
            <small class="fp-error" id="last-name-simple-error" role="alert" aria-live="polite" hidden></small>
        </div>
        <div class="fp-field">
            <label for="customer-email">
                Email
                <abbr class="fp-required" title="Obbligatorio" aria-label="Campo obbligatorio" style="display:inline!important;white-space:nowrap!important;margin-left:2px!important;color:#dc2626!important;text-decoration:none!important;float:none!important;overflow:visible!important;">*</abbr>
            </label>
            <input 
                type="email" 
                id="customer-email" 
                name="fp_resv_email" 
                required 
                autocomplete="email"
                aria-describedby="email-simple-hint email-simple-error"
                aria-invalid="false"
            >
            <small class="fp-error" id="email-simple-error" role="alert" aria-live="polite" hidden></small>
            <small class="fp-hint" id="email-simple-hint">Riceverai la conferma via email</small>
        </div>
        <div class="fp-field">
            <label for="customer-phone">
                Telefono
                <abbr class="fp-required" title="Obbligatorio" aria-label="Campo obbligatorio" style="display:inline!important;white-space:nowrap!important;margin-left:2px!important;color:#dc2626!important;text-decoration:none!important;float:none!important;overflow:visible!important;">*</abbr>
            </label>
            <div class="fp-phone-input-group">
                <select name="fp_resv_phone_prefix" class="fp-input fp-input--phone-prefix" aria-label="Prefisso telefonico" autocomplete="tel-country-code">
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
                <input 
                    type="tel" 
                    id="customer-phone" 
                    name="fp_resv_phone" 
                    required 
                    autocomplete="tel" 
                    placeholder="123 456 7890"
                    class="fp-input fp-input--phone-number"
                    inputmode="tel"
                    aria-describedby="phone-simple-hint phone-simple-error"
                    aria-invalid="false"
                >
            </div>
            <small class="fp-error" id="phone-simple-error" role="alert" aria-live="polite" hidden></small>
            <small class="fp-hint" id="phone-simple-hint">Inserisci il tuo numero di cellulare</small>
        </div>
        <div class="fp-field">
            <label for="occasion">Occasione (opzionale)</label>
            <select id="occasion" name="fp_resv_occasion" autocomplete="off" aria-describedby="occasion-hint">
                <option value="">Seleziona un'occasione</option>
                <option value="birthday">Compleanno</option>
                <option value="anniversary">Anniversario</option>
                <option value="business">Business</option>
                <option value="celebration">Celebrazione</option>
                <option value="date">Appuntamento</option>
                <option value="family">Famiglia</option>
                <option value="other">Altro</option>
            </select>
            <small class="fp-hint" id="occasion-hint">Ci aiuta a preparare al meglio la tua esperienza</small>
        </div>
        <div class="fp-field">
            <label for="notes">Note Speciali (opzionale)</label>
            <textarea id="notes" name="fp_resv_notes" rows="4" placeholder="Es. preferenza per un tavolo particolare, orario flessibile, ecc." autocomplete="off" aria-describedby="notes-hint"></textarea>
            <small class="fp-hint" id="notes-hint">Indicaci eventuali preferenze o richieste speciali</small>
        </div>
        <div class="fp-field">
            <label for="allergies">Allergie/Intolleranze (opzionale)</label>
            <textarea id="allergies" name="fp_resv_allergies" rows="4" placeholder="Indica eventuali allergie o intolleranze alimentari" autocomplete="off" aria-describedby="allergies-hint"></textarea>
            <small class="fp-hint" id="allergies-hint">Aiutaci a servirti al meglio comunicandoci intolleranze o allergie</small>
        </div>
        
        <!-- Extras -->
        <div class="fp-field">
            <fieldset class="fp-fieldset">
                <legend>Servizi Aggiuntivi</legend>
                <div class="fp-extras-group">
                    <div class="fp-checkbox-wrapper" style="display:flex!important;flex-direction:row!important;align-items:flex-start!important;gap:10px!important;">
                        <input type="checkbox" id="wheelchair-table" name="fp_resv_wheelchair_table" value="1" class="fp-checkbox" style="width:20px!important;height:20px!important;opacity:1!important;visibility:visible!important;display:inline-block!important;flex-shrink:0!important;">
                        <label for="wheelchair-table">Tavolo accessibile per sedia a rotelle</label>
                    </div>
                    <div class="fp-checkbox-wrapper" style="display:flex!important;flex-direction:row!important;align-items:flex-start!important;gap:10px!important;">
                        <input type="checkbox" id="pets-allowed" name="fp_resv_pets" value="1" class="fp-checkbox" style="width:20px!important;height:20px!important;opacity:1!important;visibility:visible!important;display:inline-block!important;flex-shrink:0!important;">
                        <label for="pets-allowed">Accompagnato da animale domestico</label>
                    </div>
                    <div class="fp-number-wrapper">
                        <label for="high-chair-count">Seggioloni:</label>
                        <input type="number" id="high-chair-count" name="fp_resv_high_chair_count" value="0" min="0" max="10" class="fp-input-number" autocomplete="off">
                    </div>
                </div>
            </fieldset>
        </div>
        
        <!-- Privacy -->
        <div class="fp-field">
            <fieldset class="fp-fieldset">
                <legend class="screen-reader-text">Consensi Privacy</legend>
                <div class="fp-checkbox-wrapper" style="display:flex!important;flex-direction:row!important;align-items:flex-start!important;gap:10px!important;">
                    <input type="checkbox" id="privacy-consent" name="fp_resv_consent" required class="fp-checkbox" aria-describedby="privacy-consent-text" style="width:20px!important;height:20px!important;opacity:1!important;visibility:visible!important;display:inline-block!important;flex-shrink:0!important;">
                    <label for="privacy-consent" id="privacy-consent-text">
                        Accetto la <a href="<?php echo esc_url($context['privacy']['policy_url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a> e il trattamento dei miei dati personali
                        <abbr class="fp-required" title="Obbligatorio" aria-label="Campo obbligatorio" style="display:inline!important;white-space:nowrap!important;margin-left:2px!important;color:#dc2626!important;text-decoration:none!important;float:none!important;overflow:visible!important;">*</abbr>
                    </label>
                </div>
                <div class="fp-checkbox-wrapper" style="display:flex!important;flex-direction:row!important;align-items:flex-start!important;gap:10px!important;">
                    <input type="checkbox" id="marketing-consent" name="fp_resv_marketing_consent" value="1" class="fp-checkbox" aria-describedby="marketing-consent-text" style="width:20px!important;height:20px!important;opacity:1!important;visibility:visible!important;display:inline-block!important;flex-shrink:0!important;">
                    <label for="marketing-consent" id="marketing-consent-text">Acconsento al trattamento dei dati per comunicazioni marketing (opzionale)</label>
                </div>
            </fieldset>
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
                <div class="fp-summary-item" id="summary-occasion-row" hidden>
                    <span class="fp-summary-label">Occasione:</span>
                    <span class="fp-summary-value" id="summary-occasion">-</span>
                </div>
                <div class="fp-summary-item" id="summary-notes-row" hidden>
                    <span class="fp-summary-label">Note:</span>
                    <span class="fp-summary-value" id="summary-notes">-</span>
                </div>
                <div class="fp-summary-item" id="summary-allergies-row" hidden>
                    <span class="fp-summary-label">Allergie:</span>
                    <span class="fp-summary-value" id="summary-allergies">-</span>
                </div>
            </div>
            
            <div class="fp-summary-section" id="summary-extras-row" hidden>
                <h4>🔧 Servizi Aggiuntivi</h4>
                <div class="fp-summary-item" id="summary-wheelchair-row" hidden>
                    <span class="fp-summary-label">Tavolo accessibile:</span>
                    <span class="fp-summary-value">Sì</span>
                </div>
                <div class="fp-summary-item" id="summary-pets-row" hidden>
                    <span class="fp-summary-label">Animale domestico:</span>
                    <span class="fp-summary-value">Sì</span>
                </div>
                <div class="fp-summary-item" id="summary-highchair-row" hidden>
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
        <button type="button" class="fp-btn fp-btn-secondary" id="prev-btn" hidden aria-label="Torna al passaggio precedente">← Indietro</button>
        <button type="button" class="fp-btn fp-btn-primary" id="next-btn" aria-label="Procedi al passaggio successivo">Avanti →</button>
        <button type="button" class="fp-btn fp-btn-primary" id="submit-btn" hidden aria-label="Conferma e invia prenotazione">Prenota</button>
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
        
        // Scroll automatico verso il notice per renderlo visibile
        // Aggiungi un piccolo delay per permettere al DOM di aggiornarsi
        setTimeout(() => {
            if (this.container && typeof this.container.scrollIntoView === 'function') {
                this.container.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center',
                    inline: 'nearest'
                });
            }
        }, 100);
        
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

// Verifica inizializzazione (solo log console)
setTimeout(() => {
    if (window.fpNoticeManager) {
        console.log('✅ Notice Manager inizializzato correttamente con auto-scroll');
    } else {
        console.error('❌ Notice Manager non inizializzato');
    }
}, 100);

// Fallback SICURO: assicuriamo che il form sia sempre visibile (SOLO il form, non tocchiamo niente altro)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Form fallback: inizializzazione');
    
    // Trova il form
    const form = document.getElementById('fp-resv-default') || document.querySelector('.fp-resv-simple');
    if (!form) {
        console.error('Form non trovato');
        return;
    }
    
    // Assicurati che il form sia visibile (SOLO il form)
    form.style.display = 'block';
    form.style.visibility = 'visible';
    form.style.opacity = '1';
    
    // Assicurati che almeno il primo step sia visibile
    const firstStep = form.querySelector('.fp-step:first-child');
    if (firstStep) {
        firstStep.style.display = 'block';
        firstStep.style.visibility = 'visible';
        firstStep.style.opacity = '1';
        firstStep.classList.add('active');
    }
    
    console.log('Form fallback: completato');
});

// Esempi di utilizzo (da rimuovere in produzione)
// window.fpNoticeManager.success('Prenotazione completata con successo!');
// window.fpNoticeManager.error('Errore durante l\'invio della prenotazione');
// window.fpNoticeManager.warning('Attenzione: alcuni campi sono obbligatori');
// window.fpNoticeManager.info('Informazione: il ristorante è chiuso il lunedì');
</script>

<script>
// JAVASCRIPT FALLBACK: Forza pointer-events su tutti i bottoni (per sicurezza)
(function() {
    function forcePointerEvents() {
        console.log('FP-Resv: Forzando pointer-events su tutti i bottoni...');
        
        // Seleziona tutti i bottoni del form E dell'header
        const selectors = [
            '#fp-resv-default button',
            '#fp-resv-default .fp-meal-btn',
            '#fp-resv-default .fp-btn',
            '#fp-resv-default .fp-time-slot',
            '#fp-resv-default .fp-btn-minus',
            '#fp-resv-default .fp-btn-plus',
            '.fp-resv-simple button',
            '.fp-resv-simple .fp-meal-btn',
            '.fp-resv-simple .fp-btn',
            '.fp-resv-simple .fp-time-slot',
            '.fp-resv-simple .fp-btn-minus',
            '.fp-resv-simple .fp-btn-plus',
            '.fp-resv-simple input',
            '.fp-resv-simple select',
            '.fp-resv-simple textarea',
            '.fp-resv-simple a',
            // Header buttons (tutti gli elementi)
            '#header-outer button',
            '#header-outer .buttons a',
            '#header-outer nav a',
            '#header-outer .slide-out-widget-area-toggle',
            '#header-outer .slide-out-widget-area-toggle *',
            '#header-outer .slide-out-widget-area-toggle a',
            '#header-outer .slide-out-widget-area-toggle .lines-button',
            '#header-outer .slide-out-widget-area-toggle .lines',
            '#header-outer .mobile-search',
            '#header-outer .mobile-search *',
            '#header-outer .mobile-search a',
            '#header-outer .mobile-search span',
            '#header-outer .mobile-search .nectar-icon',
            '#header-outer #mobile-cart-link',
            '#header-outer .lines-button',
            '#header-outer .lines-button *',
            '#header-outer .lines',
            '#header-outer i',
            '#header-outer span',
            // Selettori ultra-specifici
            '#header-outer .col.span_9 .slide-out-widget-area-toggle',
            '#header-outer .col.span_9 .slide-out-widget-area-toggle a',
            '#header-outer .col.span_9 .mobile-search',
            '#header-outer .col.span_9 .mobile-search a'
        ];
        
        selectors.forEach(function(selector) {
            const elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
                // SKIP elementi nascosti (aria-hidden, screen-reader-text)
                if (element.getAttribute('aria-hidden') === 'true' || 
                    element.classList.contains('screen-reader-text')) {
                    element.style.setProperty('pointer-events', 'none', 'important');
                    return;
                }
                
                element.style.setProperty('pointer-events', 'auto', 'important');
                element.style.setProperty('cursor', 'pointer', 'important');
                element.style.setProperty('touch-action', 'manipulation', 'important');
            });
        });
        
        console.log('FP-Resv: pointer-events forzato su ' + document.querySelectorAll(selectors.join(', ')).length + ' elementi');
    }
    
    // Esegui immediatamente
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', forcePointerEvents);
    } else {
        forcePointerEvents();
    }
    
    // Ri-esegui dopo 500ms (per sicurezza, in caso il tema applichi pointer-events dopo)
    setTimeout(forcePointerEvents, 500);
    
    // Ri-esegui dopo 1000ms (per sicurezza massima)
    setTimeout(forcePointerEvents, 1000);
    
    // Ri-esegui dopo 2000ms (per temi che caricano JS tardivamente)
    setTimeout(forcePointerEvents, 2000);
})();
</script>

<!-- Flatpickr per calendario date con date disabilitate visivamente -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/vendor/flatpickr.min.css', dirname(__FILE__, 2))); ?>">
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/form.css', dirname(__FILE__, 2))); ?>">
<script src="<?php echo esc_url(plugins_url('assets/vendor/flatpickr.min.js', dirname(__FILE__, 2))); ?>"></script>
<script src="<?php echo esc_url(plugins_url('assets/vendor/flatpickr-it.js', dirname(__FILE__, 2))); ?>"></script>

<script type="text/javascript" src="<?php echo esc_url(plugins_url('assets/js/form-simple.js', dirname(__FILE__, 2))); ?>?ver=<?php echo time(); ?>"></script>
