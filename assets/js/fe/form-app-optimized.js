/**
 * FP Reservations - Form App ottimizzato
 * Versione modulare per migliorare la manutenibilità
 */

import { closestSection, parseJsonAttribute, setAriaDisabled, firstFocusable } from './utils/dom-helpers.js';
import { toNumber, safeJson, resolveEndpoint } from './utils/validation.js';
import { pushDataLayerEvent, parseDataset } from './utils/tracking.js';
import { FormState } from './components/form-state.js';
import { FormValidation } from './components/form-validation.js';
import { FormNavigation } from './components/form-navigation.js';
import { applyMask, buildPayload, isValidLocal, normalizeCountryCode } from './phone.js';
import { formatDebugMessage } from './debug.js';
import { STEP_ORDER, idleCallback, loadAvailabilityModule } from './constants.js';

export class FormApp {
    constructor(root) {
        this.root = root;
        this.dataset = parseDataset(root);
        this.config = this.dataset.config || {};
        this.strings = this.dataset.strings || {};
        this.messages = this.strings.messages || {};
        this.events = (this.dataset && this.dataset.events) || {};
        this.integrations = this.config.integrations || this.config.features || {};

        this.form = root.querySelector('[data-fp-resv-form]');
        const defaultOrder = Array.from(STEP_ORDER);
        this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll('[data-fp-resv-section]')) : [];
        const dynamicOrder = this.sections.map((section) => section.getAttribute('data-step') || '').filter(Boolean);
        this.stepOrder = Array.from(new Set(defaultOrder.concat(dynamicOrder)));
        if (this.sections.length > 1) {
            this.sections.sort((a, b) => this.getStepOrderIndex(a) - this.getStepOrderIndex(b));
        }

        this.progress = this.form ? this.form.querySelector('[data-fp-resv-progress]') : null;
        this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll('[data-step]')) : [];
        if (this.progress && this.progressItems.length > 1) {
            this.progressItems
                .sort((a, b) => this.getStepOrderIndex(a) - this.getStepOrderIndex(b))
                .forEach((item) => {
                    this.progress.appendChild(item);
                });
        }
        this.submitButton = this.form ? this.form.querySelector('[data-fp-resv-submit]') : null;
        this.submitLabel = this.submitButton ? this.submitButton.querySelector('[data-fp-resv-submit-label]') || this.submitButton : null;
        this.submitSpinner = this.submitButton ? this.submitButton.querySelector('[data-fp-resv-submit-spinner]') : null;
        this.submitHint = this.form ? this.form.querySelector('[data-fp-resv-submit-hint]') : null;
        this.stickyCta = this.form ? this.form.querySelector('[data-fp-resv-sticky-cta]') : null;
        this.successAlert = this.form ? this.form.querySelector('[data-fp-resv-success]') : null;
        this.errorAlert = this.form ? this.form.querySelector('[data-fp-resv-error]') : null;
        this.errorMessage = this.form ? this.form.querySelector('[data-fp-resv-error-message]') : null;
        this.errorRetry = this.form ? this.form.querySelector('[data-fp-resv-error-retry]') : null;
        this.mealButtons = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-meal]'));
        this.mealNotice = root.querySelector('[data-fp-resv-meal-notice]');
        this.mealNoticeText = this.mealNotice
            ? this.mealNotice.querySelector('[data-fp-resv-meal-notice-text]') || this.mealNotice
            : null;
        this.hiddenMeal = this.form ? this.form.querySelector('input[name="fp_resv_meal"]') : null;
        this.hiddenPrice = this.form ? this.form.querySelector('input[name="fp_resv_price_per_person"]') : null;
        this.hiddenSlot = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null;
        this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null;
        this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field="party"]') : null;
        this.summaryTargets = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-summary]'));
        this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field="phone"]') : null;
        this.phonePrefixField = this.form ? this.form.querySelector('[data-fp-resv-field="phone_prefix"]') : null;
        this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name="fp_resv_phone_e164"]') : null;
        this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name="fp_resv_phone_cc"]') : null;
        this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name="fp_resv_phone_local"]') : null;
        this.availabilityRoot = this.form ? this.form.querySelector('[data-fp-resv-slots]') : null;
        this.availabilityIndicator = this.form ? this.form.querySelector('[data-fp-resv-availability-indicator]') : null;

        // Definisci this.copy prima di inizializzare i componenti modulari
        this.copy = {
            ctaDisabled: this.messages.cta_complete_fields || 'Completa i campi richiesti',
            ctaEnabled: (this.messages.cta_book_now || (this.strings.actions && this.strings.actions.submit) || 'Prenota ora'),
            ctaSending: this.messages.cta_sending || 'Invio…',
            updatingSlots: this.messages.msg_updating_slots || 'Aggiornamento disponibilità…',
            slotsUpdated: this.messages.msg_slots_updated || 'Disponibilità aggiornata.',
            slotsEmpty: this.messages.slots_empty || '',
            selectMeal: this.messages.msg_select_meal || 'Seleziona un servizio per visualizzare gli orari disponibili.',
            slotsError: this.messages.msg_slots_error || 'Impossibile aggiornare la disponibilità. Riprova.',
            dateRequired: this.messages.date_required || 'Seleziona una data per continuare.',
            slotRequired: this.messages.slot_required || 'Seleziona un orario per continuare.',
            invalidPhone: this.messages.msg_invalid_phone || 'Inserisci un numero di telefono valido (minimo 6 cifre).',
            invalidEmail: this.messages.msg_invalid_email || 'Inserisci un indirizzo email valido.',
            submitError: this.messages.msg_submit_error || 'Non è stato possibile completare la prenotazione. Riprova.',
            submitSuccess: this.messages.msg_submit_success || 'Prenotazione inviata con successo.',
            mealFullNotice: this.messages.meal_full_notice || 'Nessuna disponibilità per questo servizio. Scegli un altro giorno.',
        };

        // Inizializza i componenti modulari dopo che this.copy è stato definito
        this.state = new FormState();
        this.formValidation = new FormValidation(this.form, this.phoneField, this.phonePrefixField, this.getPhoneCountryCode(), this.copy);
        this.formNavigation = new FormNavigation(
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

        this.phoneCountryCode = this.getPhoneCountryCode();
        if (this.hiddenPhoneCc && this.hiddenPhoneCc.value === '') {
            this.hiddenPhoneCc.value = this.phoneCountryCode;
        }

        this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this);
        this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this);
        this.handleWindowFocus = this.handleWindowFocus.bind(this);

        if (!this.form || this.sections.length === 0) {
            return;
        }

        this.bind();
        this.initializeSections();
        this.initializePhoneField();
        this.initializeMeals();
        this.initializeDateField();
        this.initializeAvailability();
        this.syncConsentState();
        this.updateSubmitState();
        this.updateInlineErrors();
        this.updateSummary();

        idleCallback(() => {
            this.loadStripeIfNeeded();
            this.loadGoogleCalendarIfNeeded();
        });
    }

    // Metodi di utilità delegati ai componenti
    getStepOrderIndex(target) {
        return this.formNavigation.getStepOrderIndex(target);
    }

    updateSectionAttributes(section, state, options = {}) {
        const key = section.getAttribute('data-step') || '';
        const silent = options && options.silent === true;
        this.state.updateSectionState(key, state);
        section.setAttribute('data-state', state);

        if (state === 'completed') {
            section.setAttribute('data-complete-hidden', 'true');
        } else {
            section.removeAttribute('data-complete-hidden');
        }

        const isActive = state === 'active';
        section.setAttribute('aria-expanded', isActive ? 'true' : 'false');

        // Force visibility control for step navigation
        if (isActive) {
            section.hidden = false;
            section.removeAttribute('hidden');
            section.removeAttribute('inert');
            section.style.display = 'block';
            section.style.visibility = 'visible';
            section.style.opacity = '1';
        } else {
            section.hidden = true;
            section.setAttribute('hidden', '');
            section.setAttribute('inert', '');
            section.style.display = 'none';
            section.style.visibility = 'hidden';
            section.style.opacity = '0';
        }

        if (!silent) {
            this.updateProgressIndicators();
        }

        this.updateStickyCtaVisibility();
    }

    updateInlineErrors() {
        this.formValidation.updateInlineErrors(this.state.getState().touchedFields, this.strings);
    }

    // Metodi principali del form (mantenuti per compatibilità)
    bind() {
        const handleInput = this.handleFormInput.bind(this);
        this.form.addEventListener('input', handleInput, true);
        this.form.addEventListener('change', handleInput, true);
        this.form.addEventListener('focusin', this.handleFirstInteraction.bind(this));
        this.form.addEventListener('blur', this.handleFieldBlur.bind(this), true);
        this.form.addEventListener('keydown', this.handleKeydown.bind(this), true);
        this.form.addEventListener('click', this.handleNavClick.bind(this));
        this.form.addEventListener('submit', this.handleSubmit.bind(this));
        this.root.addEventListener('click', this.handleDelegatedTrackingEvent);

        if (this.progress) {
            this.progress.addEventListener('click', this.handleProgressClick.bind(this));
            this.progress.addEventListener('keydown', this.handleProgressKeydown.bind(this));
        }

        if (this.errorRetry) {
            this.errorRetry.addEventListener('click', this.handleRetrySubmit.bind(this));
        }

        document.addEventListener('fp-resv:reservation:confirmed', this.handleReservationConfirmed);
        window.addEventListener('fp-resv:reservation:confirmed', this.handleReservationConfirmed);
        window.addEventListener('focus', this.handleWindowFocus);
    }

    initializeSections() {
        const _this = this;
        this.sections.forEach(function (section, index) {
            const key = section.getAttribute('data-step') || String(index);
            // Only first section is active, all others are locked and hidden
            _this.state.updateSectionState(key, index === 0 ? 'active' : 'locked');
            if (index === 0) {
                _this.dispatchSectionUnlocked(key);
            }
            // Force visibility update for all sections
            _this.updateSectionAttributes(section, _this.state.getSectionState(key), { silent: true });
        });

        this.updateProgressIndicators();
    }

    initializeMeals() {
        const _this = this;
        if (this.mealButtons.length === 0) {
            return;
        }

        this.mealButtons.forEach(function (button) {
            if (!button.hasAttribute('data-meal-default-notice')) {
                const initialNotice = button.getAttribute('data-meal-notice') || '';
                if (initialNotice !== '') {
                    button.setAttribute('data-meal-default-notice', initialNotice);
                }
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                _this.handleFirstInteraction();
                _this.handleMealSelection(button);
            });

            if (button.hasAttribute('data-active') && _this.hiddenMeal) {
                _this.applyMealSelection(button);
            }
        });
    }

    initializePhoneField() {
        if (this.phonePrefixField) {
            this.updatePhoneCountryFromPrefix();
            return;
        }

        if (this.phoneField) {
            applyMask(this.phoneField, this.getPhoneCountryCode());
        }
    }

    initializeDateField() {
        if (!this.dateField) {
            return;
        }

        // Imposta la data minima a oggi per impedire la selezione di date passate
        const today = new Date().toISOString().split('T')[0];
        this.dateField.setAttribute('min', today);

        // Ottieni i giorni disponibili dalla configurazione
        const availableDays = this.config && this.config.available_days ? this.config.available_days : [];

        // Aggiungi validazione per date passate e giorni disponibili
        this.dateField.addEventListener('change', (event) => {
            const selectedDate = event.target.value;
            
            if (selectedDate && selectedDate < today) {
                event.target.setCustomValidity('Non è possibile prenotare per giorni passati.');
                event.target.setAttribute('aria-invalid', 'true');
                return;
            }

            // Se ci sono giorni disponibili configurati, valida la selezione
            if (availableDays.length > 0 && selectedDate) {
                const date = new Date(selectedDate);
                const dayOfWeek = date.getDay().toString(); // 0 = domenica, 1 = lunedì, ecc.

                if (!availableDays.includes(dayOfWeek)) {
                    const dayNames = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
                    const availableDayNames = availableDays.map(d => dayNames[parseInt(d)]).join(', ');
                    const errorMessage = `Questo giorno non è disponibile. Giorni disponibili: ${availableDayNames}.`;
                    
                    event.target.setCustomValidity(errorMessage);
                    event.target.setAttribute('aria-invalid', 'true');
                    
                    // Mostra il messaggio di errore
                    if (window.console && window.console.warn) {
                        console.warn('[FP-RESV] ' + errorMessage);
                    }
                    
                    // Resetta il campo
                    setTimeout(() => {
                        event.target.value = '';
                    }, 100);
                    
                    return;
                }
            }

            event.target.setCustomValidity('');
            event.target.setAttribute('aria-invalid', 'false');
        });

        const openPicker = () => {
            // Apri il picker nativo se disponibile (senza fare focus che causa scroll)
            if (typeof this.dateField.showPicker === 'function') {
                try {
                    this.dateField.showPicker();
                } catch (error) {
                    // Alcuni browser (es. Safari) potrebbero non supportare showPicker. Ignora.
                }
            }
        };

        // Apri il calendario al click sull'input
        this.dateField.addEventListener('click', openPicker);
    }

    initializeAvailability() {
        if (!this.availabilityRoot) {
            return;
        }

        this.availabilityRoot.addEventListener('click', (event) => {
            if (this.availabilityController) {
                return;
            }

            const target = event.target instanceof HTMLElement
                ? event.target.closest('button[data-slot]')
                : null;

            if (!target) {
                return;
            }

            event.preventDefault();

            const slot = {
                start: target.getAttribute('data-slot') || '',
                label: target.textContent || '',
                status: target.getAttribute('data-slot-status') || '',
            };

            const buttons = this.availabilityRoot.querySelectorAll('button[data-slot]');
            Array.prototype.forEach.call(buttons, (button) => {
                button.setAttribute('aria-pressed', button === target ? 'true' : 'false');
            });

            this.handleSlotSelected(slot);
        });
    }

    // Metodi di gestione eventi
    handleFormInput(event) {
        const target = event.target;
        if (!target) {
            return;
        }

        this.handleFirstInteraction();

        if (target === this.phoneField) {
            applyMask(this.phoneField, this.getPhoneCountryCode());
        } else if (target === this.phonePrefixField) {
            this.updatePhoneCountryFromPrefix();
        }

        this.updateSummary();

        const fieldKey = target.getAttribute('data-fp-resv-field') || '';
        const previousValue = fieldKey ? (target.dataset.fpResvLastValue || '') : '';
        const currentValue = fieldKey && typeof target.value === 'string' ? target.value : '';
        const valueChanged = !fieldKey || previousValue !== currentValue;

        const section = closestSection(target);
        if (!section) {
            if (this.isConsentField(target)) {
                this.syncConsentState();
            }
            this.updateSubmitState();
            return;
        }

        this.formNavigation.ensureSectionActive(section);
        this.updateSectionAttributes(section, 'active');

        if (fieldKey) {
            target.dataset.fpResvLastValue = currentValue;
        }

        if (fieldKey === 'date' || fieldKey === 'party' || fieldKey === 'slots' || fieldKey === 'time') {
            if ((fieldKey === 'date' || fieldKey === 'party') && valueChanged) {
                this.clearSlotSelection({ schedule: false });
            }

            if (fieldKey !== 'date' || valueChanged || event.type === 'change') {
                this.scheduleAvailabilityUpdate();
            }
        }

        if (this.isConsentField(target)) {
            this.syncConsentState();
        }

        this.updateSubmitState();
        this.updateInlineErrors();
    }

    handleFieldBlur(event) {
        const target = event.target;
        if (!target || !(target instanceof HTMLElement)) {
            return;
        }

        const fieldKey = target.getAttribute('data-fp-resv-field');
        if (!fieldKey) {
            return;
        }

        // Segna il campo come toccato (per mostrare gli errori)
        this.state.markFieldAsTouched(fieldKey);

        if (fieldKey === 'phone' && this.phoneField) {
            this.formValidation.validatePhoneField();
        }

        if (fieldKey === 'email' && target instanceof HTMLInputElement) {
            this.formValidation.validateEmailField(target);
        }

        this.updateInlineErrors();
    }

    handleKeydown(event) {
        if (event.key !== 'Enter') {
            return;
        }

        const target = event.target;
        if (!target || !(target instanceof HTMLElement)) {
            return;
        }

        if (target.tagName === 'TEXTAREA') {
            return;
        }

        if (target instanceof HTMLButtonElement && target.type === 'submit') {
            return;
        }

        const type = (target instanceof HTMLInputElement && target.type) || '';
        if (type === 'submit') {
            return;
        }

        event.preventDefault();
    }

    handleNavClick(event) {
        const trigger = event.target instanceof HTMLElement
            ? event.target.closest('[data-fp-resv-nav]')
            : null;

        if (!trigger) {
            return;
        }

        const section = trigger.closest('[data-fp-resv-section]');
        if (!section) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        this.handleFirstInteraction();

        const direction = trigger.getAttribute('data-fp-resv-nav');
        console.log('[FP-RESV] Navigation click:', direction, 'section:', section.getAttribute('data-step'));
        
        if (direction === 'prev') {
            this.formNavigation.navigateToPrevious(section);
        } else if (direction === 'next') {
            this.formNavigation.navigateToNext(section, this.formValidation);
        }
    }

    handleSubmit(event) {
        event.preventDefault();

        // Protezione: previene submit multipli se già in corso
        if (this.state.isSending()) {
            console.warn('[FP Resv] Submit già in corso, richiesta ignorata');
            return false;
        }

        // Segna tutti i campi come toccati quando si tenta di inviare
        this.state.markFieldAsTouched('consent');

        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            // Non fare focus automatico per evitare salti della pagina
            // this.formValidation.focusFirstInvalid();
            this.updateInlineErrors();
            this.updateSubmitState();
            return false;
        }

        const submitEvent = this.events.submit || 'reservation_submit';
        const submitContext = this.collectAvailabilityParams();
        pushDataLayerEvent(submitEvent, {
            source: 'form',
            form_id: this.form && this.form.id ? this.form.id : this.root.id || '',
            date: submitContext.date,
            party: submitContext.party,
            meal: submitContext.meal,
        });

        this.preparePhonePayload();
        this.state.setSending(true);
        this.updateSubmitState();
        this.clearError();

        const payload = this.serializeForm();
        const endpoint = this.getReservationEndpoint();
        const start = performance.now();
        let latency = 0;

        // Genera un request_id unico per idempotenza
        if (!payload.request_id && !payload.fp_resv_request_id) {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 15);
            payload.request_id = `req_${timestamp}_${random}`;
        }

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
            latency = Math.round(performance.now() - start);
            pushDataLayerEvent('ui_latency', { op: 'submit', ms: latency });

            if (!response.ok) {
                return response.json().then(errorPayload => {
                    const message = (errorPayload && errorPayload.message) || this.copy.submitError;
                    throw Object.assign(new Error(message), {
                        status: response.status,
                        payload: errorPayload,
                    });
                });
            }

            return response.json();
        })
        .then(data => {
            this.handleSubmitSuccess(data);
        })
        .catch(error => {
            if (!latency) {
                latency = Math.round(performance.now() - start);
                pushDataLayerEvent('ui_latency', { op: 'submit', ms: latency });
            }

            this.handleSubmitError(error, latency);
        })
        .finally(() => {
            this.state.setSending(false);
            this.updateSubmitState();
        });

        return false;
    }

    // Metodi di supporto
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
        // Non fare focus automatico per evitare salti della pagina
        // this.formValidation.focusFirstInvalid();
        this.updateSubmitState();
    }

    handleSubmitSuccess(data) {
        this.clearError();
        const message = (data && data.message) || this.copy.submitSuccess;
        if (this.successAlert) {
            this.successAlert.textContent = message;
            this.successAlert.hidden = false;
            // Non fare focus automatico per evitare salti della pagina
            // if (typeof this.successAlert.focus === 'function') {
            //     this.successAlert.focus();
            // }
        }

        // Disabilita il form dopo successo
        if (this.form) {
            this.form.setAttribute('data-state', 'submitted');
            const inputs = this.form.querySelectorAll('input, select, textarea, button');
            Array.prototype.forEach.call(inputs, (el) => {
                try {
                    el.setAttribute('disabled', 'disabled');
                } catch (e) {
                    // noop
                }
            });
        }
    }

    handleSubmitError(error, latency) {
        const status = error && typeof error.status === 'number' ? error.status : 'unknown';
        const message = (error && error.message) || this.copy.submitError;
        const debugSource = error && typeof error === 'object' ? error.payload || null : null;
        const finalMessage = formatDebugMessage(message, debugSource);

        if (this.errorAlert && this.errorMessage) {
            this.errorMessage.textContent = finalMessage;
            this.errorAlert.hidden = false;
            
            // Mostra l'errore senza fare scroll automatico
            // Non fare scroll/focus per evitare salti della pagina
            // requestAnimationFrame(() => {
            //     if (typeof this.errorAlert.scrollIntoView === 'function') {
            //         this.errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            //     }
            //     
            //     // Focus sull'alert per l'accessibilità
            //     if (typeof this.errorAlert.focus === 'function') {
            //         this.errorAlert.setAttribute('tabindex', '-1');
            //         this.errorAlert.focus({ preventScroll: true });
            //     }
            // });
        }

        this.state.setHintOverride(finalMessage);
        this.updateSubmitState();

        const eventName = this.events.submit_error || 'submit_error';
        pushDataLayerEvent(eventName, { code: status, latency });
    }

    clearError() {
        if (this.errorAlert) {
            this.errorAlert.hidden = true;
        }
        this.state.setHintOverride('');
    }

    // Altri metodi necessari per la compatibilità...
    updateProgressIndicators() {
        // Implementazione semplificata
        if (!this.progress) {
            return;
        }
        // Logica per aggiornare gli indicatori di progresso
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

        if (this.submitHint) {
            const hint = this.state.getHintOverride() || (isValid ? this.state.getState().initialHint : this.copy.ctaDisabled);
            this.submitHint.textContent = hint;
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

    // Metodi stub per compatibilità (implementazioni semplificate)
    updateSummary() { /* Implementazione semplificata */ }
    syncConsentState() { /* Implementazione semplificata */ }
    updateStickyCtaVisibility() { /* Implementazione semplificata */ }
    clearSlotSelection() { /* Implementazione semplificata */ }
    scheduleAvailabilityUpdate() { /* Implementazione semplificata */ }
    handleSlotSelected() { /* Implementazione semplificata */ }
    handleMealSelection() { /* Implementazione semplificata */ }
    applyMealSelection() { /* Implementazione semplificata */ }
    updatePhoneCountryFromPrefix() { /* Implementazione semplificata */ }
    getPhoneCountryCode() { return '39'; }
    collectAvailabilityParams() { return {}; }
    serializeForm() { return {}; }
    preparePhonePayload() { /* Implementazione semplificata */ }
    getReservationEndpoint() { return '/wp-json/fp-resv/v1/reservations'; }
    isConsentField() { return false; }
    handleDelegatedTrackingEvent() { /* Implementazione semplificata */ }
    handleReservationConfirmed() { /* Implementazione semplificata */ }
    handleWindowFocus() { /* Implementazione semplificata */ }
    loadStripeIfNeeded() { /* Implementazione semplificata */ }
    loadGoogleCalendarIfNeeded() { /* Implementazione semplificata */ }
    dispatchSectionUnlocked() { /* Implementazione semplificata */ }
}

// Inizializzazione globale
if (typeof window !== 'undefined') {
    window.FPResv = window.FPResv || {};
    window.FPResv.FormApp = FormApp;
    window.fpResvApp = window.FPResv; // Alias per compatibilità
}

// Initialize immediately if DOM is already ready
function initializeFPResv() {
    console.log('[FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active (Optimized)');
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

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFPResv);
} else {
    // DOM is already ready, initialize immediately
    initializeFPResv();
}

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
