/**
 * FP Reservations - Form App (Rebuilt from scratch)
 * Applicazione form completamente ricostruita con logiche semplificate
 * 
 * @version 4.0.0
 */

import { FormState } from './components/form-state.js';
import { FormValidation } from './components/form-validation.js';
import { FormNavigation } from './components/form-navigation.js';
import { applyMask } from './phone.js';
import { STEP_ORDER } from './constants.js';

/**
 * Parsing del dataset JSON
 */
function parseDataset(root) {
    try {
        const raw = root.getAttribute('data-fp-resv');
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        console.error('[FP-RESV] Errore parsing dataset:', e);
        return {};
    }
}

/**
 * Helper per trovare la sezione più vicina
 */
function closestSection(element) {
    if (!element) return null;
    return element.closest('[data-fp-resv-section]');
}

/**
 * Helper per disabilitare/abilitare pulsanti
 */
function setAriaDisabled(button, disabled) {
    if (!button) return;
    button.disabled = disabled;
    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
}

/**
 * Push eventi al dataLayer
 */
function pushDataLayerEvent(eventName, data) {
    if (typeof window !== 'undefined' && window.dataLayer) {
        window.dataLayer.push({
            event: eventName,
            ...data
        });
    }
}

/**
 * Classe principale del form
 */
export class FormApp {
    constructor(root) {
        this.root = root;
        this.dataset = parseDataset(root);
        this.config = this.dataset.config || {};
        this.strings = this.dataset.strings || {};
        this.messages = this.strings.messages || {};
        this.events = this.dataset.events || {};

        // Elementi DOM
        this.form = root.querySelector('[data-fp-resv-form]');
        if (!this.form) return;

        this.sections = Array.from(this.form.querySelectorAll('[data-fp-resv-section]'));
        this.progress = this.form.querySelector('[data-fp-resv-progress]');
        this.progressItems = this.progress ? Array.from(this.progress.querySelectorAll('[data-step]')) : [];
        
        this.submitButton = this.form.querySelector('[data-fp-resv-submit]');
        this.submitLabel = this.submitButton?.querySelector('[data-fp-resv-submit-label]') || this.submitButton;
        this.submitSpinner = this.submitButton?.querySelector('[data-fp-resv-submit-spinner]');
        this.submitHint = this.form.querySelector('[data-fp-resv-submit-hint]');
        
        this.successAlert = this.form.querySelector('[data-fp-resv-success]');
        this.errorAlert = this.form.querySelector('[data-fp-resv-error]');
        this.errorMessage = this.form.querySelector('[data-fp-resv-error-message]');
        this.errorRetry = this.form.querySelector('[data-fp-resv-error-retry]');

        // Campi speciali
        this.dateField = this.form.querySelector('[data-fp-resv-field="date"]');
        this.partyField = this.form.querySelector('[data-fp-resv-field="party"]');
        this.phoneField = this.form.querySelector('[data-fp-resv-field="phone"]');
        this.availabilityRoot = this.form.querySelector('[data-fp-resv-slots]');

        // Hidden fields
        this.hiddenMeal = this.form.querySelector('input[name="fp_resv_meal"]');
        this.hiddenPhoneE164 = this.form.querySelector('input[name="fp_resv_phone_e164"]');
        this.hiddenPhoneCc = this.form.querySelector('input[name="fp_resv_phone_cc"]');
        this.hiddenPhoneLocal = this.form.querySelector('input[name="fp_resv_phone_local"]');

        // Testi
        this.copy = {
            ctaDisabled: this.messages.cta_complete_fields || 'Completa i campi richiesti',
            ctaEnabled: this.strings.actions?.submit || 'Prenota ora',
            ctaSending: this.messages.cta_sending || 'Invio…',
            invalidPhone: this.messages.msg_invalid_phone || 'Numero di telefono non valido',
            invalidEmail: this.messages.msg_invalid_email || 'Email non valida',
            submitError: this.messages.msg_submit_error || 'Errore durante l\'invio',
            submitSuccess: this.messages.msg_submit_success || 'Prenotazione inviata con successo',
            dateRequired: this.messages.date_required || 'Seleziona una data per continuare',
            slotRequired: this.messages.slot_required || 'Seleziona un orario per continuare',
        };

        // Inizializza componenti
        this.state = new FormState();
        this.validation = new FormValidation(
            this.form,
            this.phoneField,
            this.getPhoneCountryCode(),
            this.copy
        );

        const dynamicOrder = this.sections.map(s => s.getAttribute('data-step')).filter(Boolean);
        this.stepOrder = Array.from(new Set([...STEP_ORDER, ...dynamicOrder]));

        this.navigation = new FormNavigation(
            this.sections,
            this.stepOrder,
            this.state.getState(),
            this.updateSectionAttributes.bind(this),
            this.updateProgressIndicators.bind(this),
            this.updateSubmitState.bind(this),
            this.root,
            this.form,
            this.copy
        );

        // Inizializza tutto
        this.bind();
        this.initializeSections();
        this.initializePhoneField();
        this.initializeDateField();
        this.initializeAvailability();
        this.updateSubmitState();
    }

    /**
     * Binding eventi
     */
    bind() {
        this.form.addEventListener('input', this.handleFormInput.bind(this));
        this.form.addEventListener('change', this.handleFormInput.bind(this));
        this.form.addEventListener('blur', this.handleFieldBlur.bind(this), true);
        this.form.addEventListener('click', this.handleNavClick.bind(this));
        this.form.addEventListener('submit', this.handleSubmit.bind(this));

        if (this.errorRetry) {
            this.errorRetry.addEventListener('click', this.handleRetrySubmit.bind(this));
        }
    }

    /**
     * Inizializza le sezioni
     */
    initializeSections() {
        this.sections.forEach((section, index) => {
            const key = section.getAttribute('data-step') || String(index);
            this.state.updateSectionState(key, index === 0 ? 'active' : 'locked');
            this.updateSectionAttributes(section, this.state.getSectionState(key), { silent: true });
        });
        this.updateProgressIndicators();
    }

    /**
     * Inizializza il campo telefono
     */
    initializePhoneField() {
        if (this.phoneField) {
            applyMask(this.phoneField, this.getPhoneCountryCode());
        }
        if (this.hiddenPhoneCc && !this.hiddenPhoneCc.value) {
            this.hiddenPhoneCc.value = this.getPhoneCountryCode();
        }
    }

    /**
     * Inizializza il campo data
     */
    initializeDateField() {
        if (!this.dateField) return;

        const today = this.formatLocalDate(new Date()); // Timezone locale!
        this.dateField.setAttribute('min', today);
    }

    /**
     * Formatta data nel timezone locale (NON UTC!)
     */
    formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Inizializza il sistema di slot disponibili
     */
    initializeAvailability() {
        if (!this.availabilityRoot) return;

        this.availabilityRoot.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-slot]');
            if (!button) return;

            event.preventDefault();

            const slot = button.getAttribute('data-slot') || '';
            const timeField = this.form.querySelector('[data-fp-resv-field="time"]');
            const slotStartField = this.form.querySelector('input[name="fp_resv_slot_start"]');

            if (timeField) timeField.value = button.textContent || '';
            if (slotStartField) slotStartField.value = slot;

            // Aggiorna UI
            const buttons = this.availabilityRoot.querySelectorAll('button[data-slot]');
            buttons.forEach(btn => btn.setAttribute('aria-pressed', 'false'));
            button.setAttribute('aria-pressed', 'true');

            this.updateSubmitState();
        });
    }

    /**
     * Gestione input del form
     */
    handleFormInput(event) {
        const target = event.target;
        if (!target) return;

        this.handleFirstInteraction();

        if (target === this.phoneField) {
            applyMask(this.phoneField, this.getPhoneCountryCode());
        }

        const section = closestSection(target);
        if (section) {
            this.navigation.ensureSectionActive(section);
        }

        this.updateSubmitState();
        this.updateInlineErrors();
    }

    /**
     * Gestione blur (campo perde focus)
     */
    handleFieldBlur(event) {
        const target = event.target;
        if (!target) return;

        const fieldKey = target.getAttribute('data-fp-resv-field');
        if (!fieldKey) return;

        this.state.markFieldAsTouched(fieldKey);

        if (fieldKey === 'phone') {
            this.validation.validatePhoneField();
        }

        if (fieldKey === 'email') {
            this.validation.validateEmailField(target);
        }

        this.updateInlineErrors();
    }

    /**
     * Gestione click su bottoni di navigazione
     */
    handleNavClick(event) {
        const trigger = event.target.closest('[data-fp-resv-nav]');
        if (!trigger) return;

        const section = trigger.closest('[data-fp-resv-section]');
        if (!section) return;

        event.preventDefault();
        this.handleFirstInteraction();

        const direction = trigger.getAttribute('data-fp-resv-nav');
        
        if (direction === 'prev') {
            this.navigation.navigateToPrevious(section);
        } else if (direction === 'next') {
            this.navigation.navigateToNext(section, this.validation);
        }
    }

    /**
     * Gestione submit del form
     */
    handleSubmit(event) {
        event.preventDefault();

        if (this.state.isSending()) {
            return false;
        }

        this.state.markFieldAsTouched('consent');

        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            this.updateInlineErrors();
            return false;
        }

        this.state.setSending(true);
        this.updateSubmitState();
        this.clearError();

        const payload = this.serializeForm();
        const endpoint = this.form.action;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || this.copy.submitError);
                });
            }
            return response.json();
        })
        .then(data => {
            this.handleSubmitSuccess(data);
        })
        .catch(error => {
            this.handleSubmitError(error);
        })
        .finally(() => {
            this.state.setSending(false);
            this.updateSubmitState();
        });

        return false;
    }

    /**
     * Gestione retry submit
     */
    handleRetrySubmit(event) {
        event.preventDefault();
        this.clearError();
        this.updateSubmitState();
    }

    /**
     * Submit riuscito
     */
    handleSubmitSuccess(data) {
        this.clearError();
        
        const message = data?.message || this.copy.submitSuccess;
        if (this.successAlert) {
            this.successAlert.textContent = message;
            this.successAlert.hidden = false;
        }

        // Disabilita il form
        if (this.form) {
            this.form.setAttribute('data-state', 'submitted');
            const inputs = this.form.querySelectorAll('input, select, textarea, button');
            inputs.forEach(el => el.disabled = true);
        }
    }

    /**
     * Submit fallito
     */
    handleSubmitError(error) {
        const message = error?.message || this.copy.submitError;
        
        if (this.errorAlert && this.errorMessage) {
            this.errorMessage.textContent = message;
            this.errorAlert.hidden = false;
        }

        this.state.setHintOverride(message);
        this.updateSubmitState();
    }

    /**
     * Pulisce l'errore
     */
    clearError() {
        if (this.errorAlert) {
            this.errorAlert.hidden = true;
        }
        this.state.setHintOverride('');
    }

    /**
     * Prima interazione con il form
     */
    handleFirstInteraction() {
        if (this.state.isStarted()) return;

        const eventName = this.events.start || 'reservation_start';
        pushDataLayerEvent(eventName, { source: 'form' });
        this.state.setStarted(true);
    }

    /**
     * Aggiorna gli attributi di una sezione
     */
    updateSectionAttributes(section, state, options = {}) {
        const key = section.getAttribute('data-step') || '';
        
        this.state.updateSectionState(key, state);
        section.setAttribute('data-state', state);

        const isActive = state === 'active';
        section.hidden = !isActive;
        section.setAttribute('aria-expanded', isActive ? 'true' : 'false');

        if (!options.silent) {
            this.updateProgressIndicators();
        }
    }

    /**
     * Aggiorna gli indicatori di progresso
     */
    updateProgressIndicators() {
        if (!this.progress || this.progressItems.length === 0) return;

        this.progressItems.forEach(item => {
            const key = item.getAttribute('data-step') || '';
            const state = this.state.getSectionState(key);
            item.setAttribute('data-state', state);
        });
    }

    /**
     * Aggiorna lo stato del pulsante submit
     */
    updateSubmitState() {
        if (!this.submitButton) return;

        const isValid = this.form.checkValidity();

        if (this.state.isSending()) {
            this.setSubmitButtonState(false, 'sending');
        } else {
            this.setSubmitButtonState(isValid, null);
        }

        if (this.submitHint) {
            const hint = this.state.getHintOverride() || 
                (isValid ? '' : this.copy.ctaDisabled);
            this.submitHint.textContent = hint;
        }
    }

    /**
     * Imposta lo stato del pulsante submit
     */
    setSubmitButtonState(enabled, mode) {
        if (!this.submitButton) return;

        const effectiveEnabled = mode === 'sending' ? false : Boolean(enabled);
        setAriaDisabled(this.submitButton, !effectiveEnabled);

        if (this.submitLabel) {
            if (mode === 'sending') {
                this.submitLabel.textContent = this.copy.ctaSending;
            } else if (effectiveEnabled) {
                this.submitLabel.textContent = this.copy.ctaEnabled;
            } else {
                this.submitLabel.textContent = this.copy.ctaDisabled;
            }
        }

        if (this.submitSpinner) {
            this.submitSpinner.hidden = mode !== 'sending';
        }
    }

    /**
     * Aggiorna i messaggi di errore inline
     */
    updateInlineErrors() {
        this.validation.updateInlineErrors(
            this.state.getState().touchedFields,
            this.strings
        );
    }

    /**
     * Serializza il form
     */
    serializeForm() {
        const formData = new FormData(this.form);
        const payload = {};
        
        formData.forEach((value, key) => {
            payload[key] = value;
        });

        // Genera request_id per idempotenza
        if (!payload.request_id) {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 15);
            payload.request_id = `req_${timestamp}_${random}`;
        }

        return payload;
    }

    /**
     * Ottieni il country code del telefono
     */
    getPhoneCountryCode() {
        return this.config.defaults?.phone_country_code || '39';
    }
}

// Inizializzazione globale
if (typeof window !== 'undefined') {
    window.FPResv = window.FPResv || {};
    window.FPResv.FormApp = FormApp;

    function initializeFPResv() {
        console.log('[FP-RESV] Form v4.0.0 - Rebuilt from scratch in The Fork style');
        
        const widgets = document.querySelectorAll('[data-fp-resv-app]');
        console.log('[FP-RESV] Found widgets:', widgets.length);
        
        widgets.forEach((widget) => {
            try {
                new FormApp(widget);
                console.log('[FP-RESV] Widget initialized:', widget.id || 'unnamed');
            } catch (error) {
                console.error('[FP-RESV] Error initializing widget:', error);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFPResv);
    } else {
        initializeFPResv();
    }
}
