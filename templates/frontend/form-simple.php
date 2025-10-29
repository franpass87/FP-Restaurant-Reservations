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
    if (document.getElementById('fp-resv-simple-inline-style')) return;
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
    console.log('[FP-Resv] CSS iniettato via JavaScript nel <head>');
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
                <span class="fp-btn-pdf__icon">📄</span>
                <span class="fp-btn-pdf__label"><?php echo esc_html($pdfLabel); ?></span>
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Notice Container inline -->
    <div id="fp-notice-container" class="fp-notice-container" style="position: relative; z-index: 10001;">
        <!-- Le notice verranno inserite qui dinamicamente -->
    </div>
    
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
                        🍽️ Pranzo
                    </button>
                    <button type="button" class="fp-meal-btn" data-meal="cena">
                        🌙 Cena
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Messaggio del pasto selezionato -->
            <div id="meal-notice" class="fp-meal-notice" style="display: none;">
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

<script type="text/javascript" src="<?php echo esc_url(plugins_url('assets/js/form-simple.js', dirname(__FILE__, 2))); ?>"></script>
