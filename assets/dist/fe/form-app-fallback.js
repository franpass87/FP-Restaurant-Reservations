/**
 * FP Reservations - Form App Fallback
 * Versione compatibile per browser senza supporto ES6 modules
 */

// Polyfill per le funzioni mancanti
function closestWithAttribute(element, attribute) {
    if (!element) {
        return null;
    }

    if (typeof element.closest === 'function') {
        return element.closest('[' + attribute + ']');
    }

    let parent = element;
    while (parent) {
        if (parent.hasAttribute(attribute)) {
            return parent;
        }
        parent = parent.parentElement;
    }
    return null;
}

function closestSection(element) {
    return closestWithAttribute(element, 'data-fp-resv-section');
}

function parseJsonAttribute(element, attribute) {
    if (!element || !element.hasAttribute(attribute)) {
        return null;
    }

    const raw = element.getAttribute(attribute);
    if (!raw || raw === '') {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        console.warn("[fp-resv] Impossibile analizzare l'attributo", attribute, error);
        return null;
    }
}

function setAriaDisabled(element, disabled) {
    if (!element) {
        return;
    }

    if (disabled) {
        element.setAttribute('aria-disabled', 'true');
        element.setAttribute('disabled', 'disabled');
    } else {
        element.removeAttribute('aria-disabled');
        element.removeAttribute('disabled');
    }
}

function firstFocusable(section) {
    if (!section) {
        return null;
    }

    const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    return section.querySelector(selector);
}

function scrollIntoView(element) {
    if (!element || typeof element.scrollIntoView !== 'function') {
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Funzioni di utilità
function toNumber(value) {
    if (typeof value === 'number') {
        return value;
    }
    if (typeof value === 'string') {
        const parsed = parseFloat(value);
        return Number.isNaN(parsed) ? 0 : parsed;
    }
    return 0;
}

function safeJson(response) {
    if (!response || typeof response.json !== 'function') {
        return Promise.resolve(null);
    }

    return response.json().catch(() => null);
}

function resolveEndpoint(endpoint, fallback) {
    if (!endpoint || endpoint === '') {
        return fallback || '';
    }
    return endpoint;
}

function pushDataLayerEvent(name, payload) {
    if (!name) {
        return null;
    }

    const event = Object.assign({ event: name }, payload || {});
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(event);

    if (window.fpResvTracking && typeof window.fpResvTracking.dispatch === 'function') {
        window.fpResvTracking.dispatch(event);
    }

    return event;
}

function parseDataset(root) {
    if (!root || typeof root.dataset !== 'object') {
        return {};
    }

    try {
        const config = root.dataset.config ? JSON.parse(root.dataset.config) : {};
        const strings = root.dataset.strings ? JSON.parse(root.dataset.strings) : {};
        const events = root.dataset.events ? JSON.parse(root.dataset.events) : {};
        
        return { config, strings, events };
    } catch (error) {
        console.warn('[fp-resv] Impossibile analizzare il dataset del widget', error);
        return {};
    }
}

// Classe FormState semplificata
class FormState {
    constructor() {
        this.state = {
            started: false,
            formValidEmitted: false,
            sectionStates: {},
            touchedFields: {},
            sending: false,
            ctaEnabled: false,
            hintOverride: '',
            initialHint: ''
        };
    }

    getState() {
        return this.state;
    }

    isStarted() {
        return this.state.started;
    }

    setStarted(started) {
        this.state.started = Boolean(started);
    }

    isSending() {
        return this.state.sending;
    }

    setSending(sending) {
        this.state.sending = Boolean(sending);
    }

    getSectionState(key) {
        return this.state.sectionStates[key] || 'locked';
    }

    updateSectionState(key, state) {
        this.state.sectionStates[key] = state;
    }

    markFieldAsTouched(fieldKey) {
        this.state.touchedFields[fieldKey] = true;
    }

    setCtaEnabled(enabled) {
        this.state.ctaEnabled = Boolean(enabled);
    }

    setHintOverride(hint) {
        this.state.hintOverride = String(hint || '');
    }

    getHintOverride() {
        return this.state.hintOverride;
    }
}

// Classe FormValidation semplificata
class FormValidation {
    constructor(form, state, copy, getPhoneCountryCode) {
        this.form = form;
        this.state = state;
        this.copy = copy;
        this.getPhoneCountryCode = getPhoneCountryCode;
    }

    isSectionValid(section) {
        if (!section) {
            return false;
        }

        const inputs = section.querySelectorAll('input, select, textarea');
        for (let i = 0; i < inputs.length; i++) {
            const input = inputs[i];
            if (input.hasAttribute('required') && !input.value.trim()) {
                return false;
            }
        }

        return true;
    }

    updateInlineErrors() {
        // Implementazione semplificata
        console.log('[FP-RESV] Update inline errors called');
    }

    focusFirstInvalid() {
        const firstInvalid = this.form.querySelector(':invalid');
        if (firstInvalid) {
            firstInvalid.focus();
        }
    }

    validatePhoneField() {
        // Implementazione semplificata
        console.log('[FP-RESV] Phone validation called');
    }

    validateEmailField(field) {
        // Implementazione semplificata
        console.log('[FP-RESV] Email validation called');
    }
}

// Classe FormNavigation semplificata
class FormNavigation {
    constructor(form, sections, progress, progressItems, state, events, copy) {
        this.form = form;
        this.sections = sections;
        this.progress = progress;
        this.progressItems = progressItems;
        this.state = state;
        this.events = events;
        this.copy = copy;
    }

    ensureSectionActive(section) {
        if (!section) {
            return;
        }
        // Implementazione semplificata
        console.log('[FP-RESV] Ensure section active called');
    }

    navigateToPrevious(section) {
        console.log('[FP-RESV] Navigate to previous called');
    }

    navigateToNext(section, validation) {
        console.log('[FP-RESV] Navigate to next called');
    }
}

// Classe FormApp principale (versione semplificata)
class FormApp {
    constructor(root) {
        this.root = root;
        this.dataset = parseDataset(root);
        this.config = this.dataset.config || {};
        this.strings = this.dataset.strings || {};
        this.messages = this.strings.messages || {};
        this.events = this.dataset.events || {};
        this.integrations = this.config.integrations || this.config.features || {};

        this.form = root.querySelector('[data-fp-resv-form]');
        this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll('[data-fp-resv-section]')) : [];
        this.progress = this.form ? this.form.querySelector('[data-fp-resv-progress]') : null;
        this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll('[data-step]')) : [];

        // Elementi del form
        this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null;
        this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field="party"]') : null;
        this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field="phone"]') : null;
        this.phonePrefixField = this.form ? this.form.querySelector('[data-fp-resv-field="phone_prefix"]') : null;
        this.emailField = this.form ? this.form.querySelector('[data-fp-resv-field="email"]') : null;
        this.consentField = this.form ? this.form.querySelector('[data-fp-resv-field="consent"]') : null;
        this.submitButton = this.form ? this.form.querySelector('[data-fp-resv-submit]') : null;
        this.submitLabel = this.submitButton ? this.submitButton.querySelector('[data-fp-resv-submit-label]') : null;
        this.submitHint = this.submitButton ? this.submitButton.querySelector('[data-fp-resv-submit-hint]') : null;
        this.submitSpinner = this.submitButton ? this.submitButton.querySelector('[data-fp-resv-submit-spinner]') : null;

        // Alert e messaggi
        this.successAlert = this.form ? this.form.querySelector('[data-fp-resv-success]') : null;
        this.successMessage = this.form ? this.form.querySelector('[data-fp-resv-success-message]') : null;
        this.errorAlert = this.form ? this.form.querySelector('[data-fp-resv-error]') : null;
        this.errorMessage = this.form ? this.form.querySelector('[data-fp-resv-error-message]') : null;
        this.errorRetry = this.form ? this.form.querySelector('[data-fp-resv-error-retry]') : null;

        // Inizializza i componenti
        this.state = new FormState();
        this.formValidation = new FormValidation(this.form, this.state, this.copy, this.getPhoneCountryCode.bind(this));
        this.formNavigation = new FormNavigation(this.form, this.sections, this.progress, this.progressItems, this.state, this.events, this.copy);

        // Messaggi di copia
        this.copy = {
            ctaEnabled: this.messages.cta_enabled || 'Prenota',
            ctaDisabled: this.messages.cta_disabled || 'Compila tutti i campi',
            ctaSending: this.messages.cta_sending || 'Invio in corso...',
            submitSuccess: this.messages.msg_submit_success || 'Prenotazione completata con successo!',
            submitError: this.messages.msg_submit_error || 'Errore durante l\'invio. Riprova.',
            slotsError: this.messages.msg_slots_error || 'Impossibile aggiornare la disponibilità. Riprova.',
            invalidPhone: this.messages.msg_invalid_phone || 'Numero di telefono non valido',
            invalidEmail: this.messages.msg_invalid_email || 'Indirizzo email non valido'
        };

        this.initialize();
    }

    initialize() {
        this.initializeSections();
        this.initializeEventListeners();
        this.updateInlineErrors();
        this.updateSubmitState();
    }

    initializeSections() {
        const _this = this;
        this.sections.forEach(function (section, index) {
            const key = section.getAttribute('data-step') || String(index);
            _this.state.updateSectionState(key, index === 0 ? 'active' : 'locked');
            if (index === 0) {
                _this.dispatchSectionUnlocked(key);
            }
            _this.updateSectionAttributes(section, _this.state.getSectionState(key), { silent: true });
        });
        this.updateProgressIndicators();
    }

    initializeEventListeners() {
        if (!this.form) {
            return;
        }

        const handleInput = this.handleFormInput.bind(this);
        this.form.addEventListener('input', handleInput, true);
        this.form.addEventListener('change', handleInput, true);
        this.form.addEventListener('blur', this.handleFieldBlur.bind(this), true);
        this.form.addEventListener('keydown', this.handleKeydown.bind(this), true);
        this.form.addEventListener('click', this.handleNavClick.bind(this));
        this.form.addEventListener('submit', this.handleSubmit.bind(this));

        if (this.errorRetry) {
            this.errorRetry.addEventListener('click', this.handleRetrySubmit.bind(this));
        }
    }

    // Metodi stub per compatibilità
    handleFormInput(event) {
        console.log('[FP-RESV] Form input handled');
        this.handleFirstInteraction();
        this.updateSubmitState();
        this.updateInlineErrors();
    }

    handleFieldBlur(event) {
        console.log('[FP-RESV] Field blur handled');
        const target = event.target;
        if (target && target.getAttribute('data-fp-resv-field')) {
            this.state.markFieldAsTouched(target.getAttribute('data-fp-resv-field'));
        }
        this.updateInlineErrors();
    }

    handleKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    }

    handleNavClick(event) {
        const trigger = event.target.closest('[data-fp-resv-nav]');
        if (trigger) {
            event.preventDefault();
            this.handleFirstInteraction();
            console.log('[FP-RESV] Navigation clicked');
        }
    }

    handleSubmit(event) {
        event.preventDefault();
        this.state.markFieldAsTouched('consent');
        
        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            this.formValidation.focusFirstInvalid();
            this.updateInlineErrors();
            this.updateSubmitState();
            return false;
        }

        console.log('[FP-RESV] Form submitted');
        return false;
    }

    handleFirstInteraction() {
        if (this.state.isStarted()) {
            return;
        }
        const eventName = this.events.start || 'reservation_start';
        pushDataLayerEvent(eventName, { source: 'form' });
        this.state.setStarted(true);
    }

    handleRetrySubmit(event) {
        event.preventDefault();
        this.clearError();
        this.formValidation.focusFirstInvalid();
        this.updateSubmitState();
    }

    updateInlineErrors() {
        this.formValidation.updateInlineErrors();
    }

    updateSubmitState() {
        if (!this.submitButton) {
            return;
        }

        const isValid = this.form.checkValidity();
        if (this.state.isSending()) {
            this.setSubmitButtonState(false, 'sending');
        } else {
            this.setSubmitButtonState(isValid, null);
        }
    }

    setSubmitButtonState(enabled, mode) {
        if (!this.submitButton) {
            return;
        }

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

        this.state.setCtaEnabled(effectiveEnabled);
    }

    updateSectionAttributes(section, state, options = {}) {
        if (!section) {
            return;
        }

        section.setAttribute('data-state', state);
        section.hidden = state === 'locked';
    }

    updateProgressIndicators() {
        // Implementazione semplificata
        console.log('[FP-RESV] Progress indicators updated');
    }

    clearError() {
        if (this.errorAlert) {
            this.errorAlert.hidden = true;
        }
        this.state.setHintOverride('');
    }

    getPhoneCountryCode() {
        return '39';
    }

    dispatchSectionUnlocked(key) {
        console.log('[FP-RESV] Section unlocked:', key);
    }
}

// Inizializzazione globale
if (typeof window !== 'undefined') {
    window.FPResv = window.FPResv || {};
    window.FPResv.FormApp = FormApp;
    window.fpResvApp = window.FPResv;
}

function initializeFPResv() {
    console.log('[FP-RESV] Plugin v0.1.5 loaded - Fallback form functionality active');
    const widgets = document.querySelectorAll('[data-fp-resv]');
    console.log('[FP-RESV] Found widgets:', widgets.length);

    Array.prototype.forEach.call(widgets, function (widget) {
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

// Event listener per tracking
document.addEventListener('fp-resv:tracking:push', function (event) {
    if (!event || !event.detail) {
        return;
    }
    const detail = event.detail;
    const name = detail && (detail.event || detail.name);
    if (!name) {
        return;
    }
    const payload = detail.payload || detail.data || {};
    pushDataLayerEvent(name, payload && typeof payload === 'object' ? payload : {});
});
