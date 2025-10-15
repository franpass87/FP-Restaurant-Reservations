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

function _closestSection(element) {
    return closestWithAttribute(element, 'data-fp-resv-section');
}

function _parseJsonAttribute(element, attribute) {
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

function _firstFocusable(section) {
    if (!section) {
        return null;
    }

    const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    return section.querySelector(selector);
}

function _scrollIntoView(element) {
    if (!element || typeof element.scrollIntoView !== 'function') {
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Funzioni di utilità
function _toNumber(value) {
    if (typeof value === 'number') {
        return value;
    }
    if (typeof value === 'string') {
        const parsed = parseFloat(value);
        return Number.isNaN(parsed) ? 0 : parsed;
    }
    return 0;
}

function _safeJson(response) {
    if (!response || typeof response.json !== 'function') {
        return Promise.resolve(null);
    }

    return response.json().catch(() => null);
}

function _resolveEndpoint(endpoint, fallback) {
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

        // Protezione: previene submit multipli se già in corso
        if (this.state.isSending()) {
            console.warn('[FP Resv] Submit già in corso, richiesta ignorata');
            return false;
        }

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

function ensureWidgetVisibility(widget) {
    if (!widget) {
        return;
    }
    
    // Force visibility with inline styles as a fallback
    widget.style.display = 'block';
    widget.style.visibility = 'visible';
    widget.style.opacity = '1';
    widget.style.position = 'relative';
    widget.style.width = '100%';
    widget.style.height = 'auto';
    
    // Ensure parent containers don't hide the widget
    var parent = widget.parentElement;
    var depth = 0;
    while (parent && depth < 5) {
        var display = window.getComputedStyle(parent).display;
        if (display === 'none') {
            console.warn('[FP-RESV] Found hidden parent element, making visible:', parent);
            parent.style.display = 'block';
        }
        parent = parent.parentElement;
        depth++;
    }
    
    console.log('[FP-RESV] Widget visibility ensured:', widget.id || 'unnamed');
}

// Track already initialized widgets to avoid double initialization
var initializedWidgets = [];

function isWidgetInitialized(widget) {
    for (var i = 0; i < initializedWidgets.length; i++) {
        if (initializedWidgets[i] === widget) {
            return true;
        }
    }
    return false;
}

function initializeFPResv() {
    console.log('[FP-RESV] Plugin v0.1.11 loaded - Fallback form functionality active');
    console.log('[FP-RESV] Current readyState:', document.readyState);
    console.log('[FP-RESV] Body innerHTML length:', document.body ? document.body.innerHTML.length : 0);
    
    const widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
    console.log('[FP-RESV] Found widgets:', widgets.length);

    if (widgets.length === 0) {
        console.warn('[FP-RESV] No widgets found on page. Expected shortcode [fp_reservations] or Gutenberg block.');
        console.log('[FP-RESV] Searching for potential widget containers...');
        
        // Debug: check for common WordPress content areas
        var content = document.querySelector('.entry-content, .post-content, .page-content, main, article');
        if (content) {
            console.log('[FP-RESV] Found content container:', content.className || 'unnamed');
            console.log('[FP-RESV] Content container innerHTML length:', content.innerHTML.length);
            
            // Check if there's any fp-resv related content
            if (content.innerHTML.indexOf('fp-resv') !== -1) {
                console.log('[FP-RESV] Found fp-resv string in content, but no valid widget element');
            }
        } else {
            console.log('[FP-RESV] No standard content container found');
        }
        
        return;
    }

    Array.prototype.forEach.call(widgets, function (widget) {
        // Skip if already initialized
        if (isWidgetInitialized(widget)) {
            console.log('[FP-RESV] Widget already initialized, skipping:', widget.id || 'unnamed');
            return;
        }
        
        try {
            // Mark as initialized
            initializedWidgets.push(widget);
            
            // Ensure widget is visible first
            ensureWidgetVisibility(widget);
            
            // Initialize the widget
            new FormApp(widget);
            console.log('[FP-RESV] Widget initialized successfully:', widget.id || 'unnamed');
        } catch (error) {
            console.error('[FP-RESV] Error initializing widget:', error);
            // Remove from initialized array on error so it can be retried
            var index = initializedWidgets.indexOf(widget);
            if (index > -1) {
                initializedWidgets.splice(index, 1);
            }
        }
    });
}

// Auto-check visibility every second for the first 10 seconds
function autoCheckVisibility() {
    var checks = 0;
    var maxChecks = 10;
    
    var interval = setInterval(function() {
        checks++;
        
        var widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
        var hasHiddenWidget = false;
        
        Array.prototype.forEach.call(widgets, function(widget) {
            var computed = window.getComputedStyle(widget);
            if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
                console.warn('[FP-RESV] Widget became hidden, forcing visibility again:', widget.id || 'unnamed');
                ensureWidgetVisibility(widget);
                hasHiddenWidget = true;
            }
        });
        
        if (checks >= maxChecks || !hasHiddenWidget) {
            clearInterval(interval);
            if (checks >= maxChecks) {
                console.log('[FP-RESV] Visibility auto-check completed after ' + checks + ' checks');
            }
        }
    }, 1000);
}

// Set up MutationObserver to detect widgets added dynamically
function setupWidgetObserver() {
    if (typeof MutationObserver === 'undefined') {
        console.warn('[FP-RESV] MutationObserver not supported, dynamic widgets won\'t be detected');
        return;
    }
    
    var observer = new MutationObserver(function(mutations) {
        var hasNewWidgets = false;
        
        for (var i = 0; i < mutations.length; i++) {
            var mutation = mutations[i];
            if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];
                    if (node.nodeType === 1) { // Element node
                        // Check if the node itself is a widget
                        var isWidget = false;
                        if (node.matches) {
                            isWidget = node.matches('[data-fp-resv]') || node.matches('.fp-resv-widget') || node.matches('[data-fp-resv-app]');
                        } else if (node.getAttribute) {
                            // Fallback for older browsers
                            isWidget = node.getAttribute('data-fp-resv') !== null || 
                                      node.getAttribute('data-fp-resv-app') !== null ||
                                      (node.className && node.className.indexOf('fp-resv-widget') !== -1);
                        }
                        
                        if (isWidget) {
                            hasNewWidgets = true;
                        }
                        // Check if the node contains a widget
                        else if (node.querySelector) {
                            var widget = node.querySelector('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
                            if (widget) {
                                hasNewWidgets = true;
                            }
                        }
                    }
                }
            }
        }
        
        if (hasNewWidgets) {
            console.log('[FP-RESV] New widget(s) detected in DOM, initializing...');
            initializeFPResv();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    console.log('[FP-RESV] MutationObserver set up to detect dynamic widgets');
}

// Retry initialization with increasing delays
function retryInitialization() {
    var delays = [500, 1000, 2000, 3000]; // Retry after 0.5s, 1s, 2s, 3s
    
    for (var i = 0; i < delays.length; i++) {
        (function(delay) {
            setTimeout(function() {
                var currentWidgetCount = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]').length;
                if (currentWidgetCount > initializedWidgets.length) {
                    console.log('[FP-RESV] Retry: Found ' + currentWidgetCount + ' widgets, ' + initializedWidgets.length + ' initialized');
                    initializeFPResv();
                }
            }, delay);
        })(delays[i]);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeFPResv();
        // Start auto-check after initialization
        setTimeout(autoCheckVisibility, 500);
        // Set up observer for dynamic widgets
        setupWidgetObserver();
        // Retry in case widgets load late
        retryInitialization();
    });
} else {
    initializeFPResv();
    // Start auto-check after initialization
    setTimeout(autoCheckVisibility, 500);
    // Set up observer for dynamic widgets
    setupWidgetObserver();
    // Retry in case widgets load late
    retryInitialization();
}

// WPBakery Page Builder compatibility
// WPBakery loads content asynchronously, so we need to re-check after it's done
if (typeof window.vc_js !== 'undefined' || document.querySelector('[data-vc-full-width]') || document.querySelector('.vc_row')) {
    console.log('[FP-RESV] WPBakery detected - adding compatibility listeners');
    
    // Listen for WPBakery-specific events
    document.addEventListener('vc-full-content-loaded', function() {
        console.log('[FP-RESV] WPBakery vc-full-content-loaded event - re-initializing...');
        setTimeout(initializeFPResv, 100);
    });
    
    // Additional check after window load (WPBakery often loads late)
    window.addEventListener('load', function() {
        setTimeout(function() {
            var currentWidgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
            if (currentWidgets.length > initializedWidgets.length) {
                console.log('[FP-RESV] WPBakery late load - found new widgets, initializing...');
                initializeFPResv();
            }
        }, 1000);
    });
    
    // Extended retry for WPBakery (up to 10 seconds)
    var wpbDelays = [1500, 3000, 5000, 10000];
    for (var i = 0; i < wpbDelays.length; i++) {
        (function(delay) {
            setTimeout(function() {
                var currentWidgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
                if (currentWidgets.length > initializedWidgets.length) {
                    console.log('[FP-RESV] WPBakery extended retry (' + delay + 'ms) - initializing...');
                    initializeFPResv();
                }
            }, delay);
        })(wpbDelays[i]);
    }
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
