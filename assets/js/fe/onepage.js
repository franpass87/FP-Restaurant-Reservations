import { applyMask, buildPayload, isValidLocal, normalizeCountryCode } from './phone.js';
import { formatDebugMessage } from './debug.js';
import { parseDataset, parseJsonAttribute, toNumber } from './utils/data.js';
import { closestWithAttribute, firstFocusable } from './utils/dom.js';
import { setAriaDisabled } from './utils/a11y.js';
import { resolveEndpoint, safeJson } from './utils/net.js';
import { pushDataLayerEvent } from './tracking/dataLayer.js';
import { STEP_ORDER, idleCallback, loadAvailabilityModule } from './constants.js';

function closestSection(element) {
    return closestWithAttribute(element, 'data-fp-resv-section');
}

class FormApp {
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
        this.hiddenMeal = this.form ? this.form.querySelector('input[name=\"fp_resv_meal\"]') : null;
        this.hiddenPrice = this.form ? this.form.querySelector('input[name=\"fp_resv_price_per_person\"]') : null;
        this.hiddenSlot = this.form ? this.form.querySelector('input[name=\"fp_resv_slot_start\"]') : null;
        this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field=\"date\"]') : null;
        this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field=\"party\"]') : null;
        this.summaryTargets = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-summary]'));
        this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field=\"phone\"]') : null;
        this.phonePrefixField = this.form ? this.form.querySelector('[data-fp-resv-field=\"phone_prefix\"]') : null;
        this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name=\"fp_resv_phone_e164\"]') : null;
        this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name=\"fp_resv_phone_cc\"]') : null;
        this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name=\"fp_resv_phone_local\"]') : null;
        this.availabilityRoot = this.form ? this.form.querySelector('[data-fp-resv-slots]') : null;
        this.availabilityIndicator = this.form ? this.form.querySelector('[data-fp-resv-availability-indicator]') : null;
        this.slotsLegend = this.form ? this.form.querySelector('[data-fp-resv-slots-legend]') : null;

        this.state = {
            started: false,
            formValidEmitted: false,
            sectionStates: {},
            unlocked: {},
            initialHint: this.submitHint ? this.submitHint.textContent : '',
            hintOverride: '',
            ctaEnabled: false,
            sending: false,
            pendingAvailability: false,
            pendingAvailabilityOptions: null,
            lastAvailabilityParams: null,
            mealAvailability: {},
            touchedFields: {},
        };

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

    getStepOrderIndex(target) {
        const key = target && target.getAttribute ? target.getAttribute('data-step') || '' : String(target || '');
        const normalized = typeof key === 'string' ? key : '';
        const index = this.stepOrder.indexOf(normalized);
        return index === -1 ? this.stepOrder.length + 1 : index;
    }

    initializeSections() {
        const _this = this;
        this.sections.forEach(function (section, index) {
            const key = section.getAttribute('data-step') || String(index);
            // Only first section is active, all others are locked and hidden
            _this.state.sectionStates[key] = index === 0 ? 'active' : 'locked';
            if (index === 0) {
                _this.dispatchSectionUnlocked(key);
            }
            // Force visibility update for all sections
            _this.updateSectionAttributes(section, _this.state.sectionStates[key], { silent: true });
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

    updatePhoneCountryFromPrefix() {
        if (!this.phonePrefixField) {
            return;
        }

        const code = normalizeCountryCode(this.phonePrefixField.value);
        let targetCode = code;

        if (targetCode === '') {
            if (this.phoneCountryCode) {
                const normalizedState = normalizeCountryCode(this.phoneCountryCode);
                if (normalizedState) {
                    targetCode = normalizedState;
                }
            }
        }

        if (targetCode === '' && this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
            const normalizedHidden = normalizeCountryCode(this.hiddenPhoneCc.value);
            if (normalizedHidden) {
                targetCode = normalizedHidden;
            }
        }

        if (targetCode === '') {
            const defaults = (this.config && this.config.defaults) || {};
            if (defaults.phone_country_code) {
                const normalizedDefaults = normalizeCountryCode(defaults.phone_country_code);
                if (normalizedDefaults) {
                    targetCode = normalizedDefaults;
                }
            }
        }

        if (targetCode === '') {
            targetCode = '39';
        }

        if (this.hiddenPhoneCc) {
            this.hiddenPhoneCc.value = targetCode;
        }
        if (code !== '') {
            this.phoneCountryCode = code;
        }

        if (this.phoneField) {
            applyMask(this.phoneField, targetCode);
        }
    }

    initializeDateField() {
        if (!this.dateField) {
            return;
        }

        // Imposta la data minima a oggi per impedire la selezione di date passate
        const today = new Date().toISOString().split('T')[0];
        this.dateField.setAttribute('min', today);

        // Ottieni i giorni disponibili dalla configurazione (inizialmente tutti i giorni aggregati)
        this.currentAvailableDays = this.config && this.config.available_days ? this.config.available_days : [];

        // Aggiungi validazione per date passate e giorni disponibili
        this.dateField.addEventListener('change', (event) => {
            const selectedDate = event.target.value;
            
            if (selectedDate && selectedDate < today) {
                event.target.setCustomValidity('Non è possibile prenotare per giorni passati.');
                event.target.setAttribute('aria-invalid', 'true');
                return;
            }

            // Se ci sono giorni disponibili configurati, valida la selezione
            if (this.currentAvailableDays.length > 0 && selectedDate) {
                const date = new Date(selectedDate);
                const dayOfWeek = date.getDay().toString(); // 0 = domenica, 1 = lunedì, ecc.

                if (!this.currentAvailableDays.includes(dayOfWeek)) {
                    const dayNames = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
                    const availableDayNames = this.currentAvailableDays.map(d => dayNames[parseInt(d)]).join(', ');
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
            // Porta il focus sull'input
            if (typeof this.dateField.focus === 'function') {
                this.dateField.focus();
            }

            // Apri il picker nativo se disponibile
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

        const schedule = (options = {}) => {
            const normalizedOptions = options && typeof options === 'object'
                ? { ...options }
                : {};

            if (!this.availabilityController) {
                this.state.pendingAvailability = true;
                this.state.pendingAvailabilityOptions = normalizedOptions;
                return;
            }

            this.scheduleAvailabilityUpdate(normalizedOptions);
        };

        idleCallback(() => {
            loadAvailabilityModule()
                .then((module) => {
                    if (!module || typeof module.createAvailabilityController !== 'function' || !this.availabilityRoot) {
                        return;
                    }

                    this.availabilityController = module.createAvailabilityController({
                        root: this.availabilityRoot,
                        endpoint: this.getAvailabilityEndpoint(),
                        strings: this.copy,
                        getParams: () => this.collectAvailabilityParams(),
                        onSlotSelected: (slot) => this.handleSlotSelected(slot),
                        onLatency: (ms) => this.handleAvailabilityLatency(ms),
                        onRetry: (attempt) => this.handleAvailabilityRetry(attempt),
                        onAvailabilitySummary: (summary, params) => this.handleMealAvailabilitySummary(summary, params),
                    });

                    if (this.state.pendingAvailability) {
                        this.state.pendingAvailability = false;
                        const pendingOptions = this.state.pendingAvailabilityOptions || {};
                        this.state.pendingAvailabilityOptions = null;
                        this.scheduleAvailabilityUpdate(pendingOptions);
                    }
                })
                .catch(() => {
                    // noop
                });
        });

        schedule();
    }

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

        this.ensureSectionActive(section);
        this.updateSectionAttributes(section, 'active');

        if (fieldKey) {
            target.dataset.fpResvLastValue = currentValue;
        }

        if (fieldKey === 'date' || fieldKey === 'party' || fieldKey === 'slots' || fieldKey === 'time') {
            if ((fieldKey === 'date' || fieldKey === 'party') && valueChanged) {
                this.clearSlotSelection({ schedule: false });
                // Reset meal availability cache quando cambiano parametri critici
                this.state.mealAvailability = {};
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
        this.state.touchedFields[fieldKey] = true;

        if (fieldKey === 'phone' && this.phoneField) {
            this.validatePhoneField();
        }

        if (fieldKey === 'email' && target instanceof HTMLInputElement) {
            this.validateEmailField(target);
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
            this.navigateToPrevious(section);
        } else if (direction === 'next') {
            this.navigateToNext(section);
        }
    }

    handleProgressClick(event) {
        if (!this.progress) {
            return;
        }

        const target = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('[data-step]')
            : null;

        if (!target || !this.progress.contains(target)) {
            return;
        }

        const key = target.getAttribute('data-step') || '';
        if (!key) {
            return;
        }

        const state = this.state.sectionStates[key];
        if (!state || state === 'locked') {
            return;
        }

        event.preventDefault();
        this.activateSectionByKey(key);
    }

    handleProgressKeydown(event) {
        if (!this.progress) {
            return;
        }

        if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Spacebar' && event.key !== 'Space') {
            return;
        }

        const target = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('[data-step]')
            : null;

        if (!target || !this.progress.contains(target)) {
            return;
        }

        const key = target.getAttribute('data-step') || '';
        if (!key) {
            return;
        }

        const state = this.state.sectionStates[key];
        if (!state || state === 'locked') {
            return;
        }

        event.preventDefault();
        this.activateSectionByKey(key);
    }

    activateSectionByKey(key) {
        const targetSection = this.sections.find(function (section) {
            return (section.getAttribute('data-step') || '') === key;
        });

        if (!targetSection) {
            return;
        }

        let reachedTarget = false;

        this.sections.forEach((section) => {
            const sectionKey = section.getAttribute('data-step') || '';
            if (sectionKey === key) {
                reachedTarget = true;
                this.updateSectionAttributes(section, 'active', { silent: true });
                this.dispatchSectionUnlocked(sectionKey);
            } else if (!reachedTarget) {
                const previousState = this.state.sectionStates[sectionKey];
                const state = previousState === 'locked' ? 'locked' : 'completed';
                this.updateSectionAttributes(section, state, { silent: true });
            } else {
                this.updateSectionAttributes(section, 'locked', { silent: true });
            }
        });

        this.updateProgressIndicators();
        // Non fare scroll automatico per evitare salti anchor
        requestAnimationFrame(() => {
            const focusTarget = targetSection.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
            if (focusTarget && typeof focusTarget.focus === 'function') {
                focusTarget.focus({ preventScroll: true });
            }
        });
        this.updateSubmitState();
    }

    handleRetrySubmit(event) {
        event.preventDefault();
        this.clearError();
        
        // Ripristina il pulsante Riprova al suo stato originale
        if (this.errorRetry) {
            this.errorRetry.textContent = this.messages.retry_button || 'Riprova';
            this.errorRetry.onclick = null;
        }
        
        this.focusFirstInvalid();
        this.updateSubmitState();
    }

    handleMealSelection(button) {
        this.mealButtons.forEach(function (item) {
            item.removeAttribute('data-active');
            item.setAttribute('aria-pressed', 'false');
        });

        button.setAttribute('data-active', 'true');
        button.setAttribute('aria-pressed', 'true');
        const mealKey = button.getAttribute('data-fp-resv-meal') || '';
        const storedState = this.state.mealAvailability ? this.state.mealAvailability[mealKey] : '';
        this.applyMealAvailabilityIndicator(mealKey, storedState);
        if (storedState === 'full') {
            const defaultNotice = button.getAttribute('data-meal-default-notice') || '';
            const notice = this.copy.mealFullNotice || defaultNotice;
            if (notice !== '') {
                button.setAttribute('data-meal-notice', notice);
            }
        }

        this.applyMealSelection(button);
        this.applyMealAvailabilityNotice(mealKey, storedState, { skipSlotReset: true });

        const mealEvent = this.events.meal_selected || 'meal_selected';
        pushDataLayerEvent(mealEvent, {
            meal_type: button.getAttribute('data-fp-resv-meal') || '',
            meal_label: button.getAttribute('data-meal-label') || '',
        });

        // Aggiorna i giorni disponibili in base al meal selezionato
        this.updateAvailableDaysForMeal(mealKey);

        // Schedula sempre l'aggiornamento della disponibilità, anche se lo stato cached è 'full'
        // perché i parametri (date, party) potrebbero essere cambiati
        this.scheduleAvailabilityUpdate({ immediate: true });
    }

    updateAvailableDaysForMeal(mealKey) {
        if (!this.dateField || !mealKey) {
            return;
        }

        // Trova il meal selezionato dall'array dei meals
        const meals = this.config && this.config.meals ? this.config.meals : [];
        const selectedMeal = meals.find(meal => meal.key === mealKey);

        // Se il meal ha giorni disponibili specifici, usali
        if (selectedMeal && selectedMeal.available_days && selectedMeal.available_days.length > 0) {
            this.currentAvailableDays = selectedMeal.available_days;
        } else {
            // Altrimenti usa i giorni disponibili globali
            this.currentAvailableDays = this.config && this.config.available_days ? this.config.available_days : [];
        }

        // Valida la data attualmente selezionata (se presente)
        const currentDate = this.dateField.value;
        if (currentDate && this.currentAvailableDays.length > 0) {
            const date = new Date(currentDate);
            const dayOfWeek = date.getDay().toString();

            // Se il giorno non è più disponibile con il nuovo meal, resetta il campo
            if (!this.currentAvailableDays.includes(dayOfWeek)) {
                const dayNames = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
                const availableDayNames = this.currentAvailableDays.map(d => dayNames[parseInt(d)]).join(', ');
                
                // Mostra un messaggio informativo
                if (window.console && window.console.warn) {
                    console.warn(`[FP-RESV] La data selezionata non è disponibile per questo servizio. Giorni disponibili: ${availableDayNames}.`);
                }
                
                // Resetta il campo data
                this.dateField.value = '';
                this.dateField.setCustomValidity('');
                this.dateField.setAttribute('aria-invalid', 'false');
                
                // Resetta anche gli slot se presenti
                if (this.availabilityController && typeof this.availabilityController.clear === 'function') {
                    this.availabilityController.clear();
                }
            }
        }
    }

    updateMealNoticeFromButton(button, overrideText) {
        if (!this.mealNotice) {
            return;
        }

        const source = typeof overrideText === 'string'
            ? overrideText
            : (button ? button.getAttribute('data-meal-notice') || '' : '');
        const text = source ? source.trim() : '';

        const target = this.mealNoticeText || this.mealNotice;

        if (text !== '' && target) {
            target.textContent = text;
            this.mealNotice.hidden = false;
        } else if (target) {
            target.textContent = '';
            this.mealNotice.hidden = true;
        }
    }

    applyMealAvailabilityNotice(mealKey, state, options = {}) {
        const button = this.mealButtons.find((item) => (item.getAttribute('data-fp-resv-meal') || '') === mealKey);
        if (!button) {
            return;
        }

        const defaultNotice = button.getAttribute('data-meal-default-notice') || '';
        const normalizedState = typeof state === 'string' ? state : '';

        if (normalizedState === 'full') {
            const notice = this.copy.mealFullNotice || defaultNotice;
            if (notice !== '') {
                button.setAttribute('data-meal-notice', notice);
            } else if (defaultNotice === '') {
                button.removeAttribute('data-meal-notice');
            }

            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('data-meal-unavailable', 'true');

            if (button.hasAttribute('data-active')) {
                if (options.skipSlotReset !== true) {
                    this.clearSlotSelection({ schedule: false });
                }
                this.updateMealNoticeFromButton(button);
            }

            return;
        }

        if (normalizedState === 'unavailable') {
            const unavailableNotice = 'Orari di servizio non configurati per questa data.';
            button.setAttribute('data-meal-notice', unavailableNotice);

            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('data-meal-unavailable', 'true');

            if (button.hasAttribute('data-active')) {
                if (options.skipSlotReset !== true) {
                    this.clearSlotSelection({ schedule: false });
                }
                this.updateMealNoticeFromButton(button);
            }

            return;
        }

        button.removeAttribute('aria-disabled');
        button.removeAttribute('data-meal-unavailable');

        if (defaultNotice !== '') {
            button.setAttribute('data-meal-notice', defaultNotice);
        } else if (button.hasAttribute('data-meal-notice')) {
            button.removeAttribute('data-meal-notice');
        }

        if (button.hasAttribute('data-active')) {
            this.updateMealNoticeFromButton(button);
        }
    }

    applyMealSelection(button) {
        const key = button.getAttribute('data-fp-resv-meal') || '';
        if (this.hiddenMeal) {
            this.hiddenMeal.value = key;
        }

        const price = toNumber(button.getAttribute('data-meal-price'));
        if (this.hiddenPrice) {
            this.hiddenPrice.value = price !== null ? String(price) : '';
        }

        this.clearSlotSelection({ schedule: false });
        this.updateMealNoticeFromButton(button);

        this.updateSubmitState();
    }

    clearSlotSelection(options = {}) {
        if (this.hiddenSlot) {
            this.hiddenSlot.value = '';
        }

        const timeField = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
        if (timeField) {
            timeField.value = '';
            timeField.removeAttribute('data-slot-start');
        }

        if (this.availabilityController && typeof this.availabilityController.clearSelection === 'function') {
            this.availabilityController.clearSelection();
        }

        if (this.availabilityRoot) {
            const selectedButtons = this.availabilityRoot.querySelectorAll('button[data-slot][aria-pressed="true"]');
            Array.prototype.forEach.call(selectedButtons, (button) => {
                button.setAttribute('aria-pressed', 'false');
            });
        }

        const slotsSection = this.sections.find((section) => (section.getAttribute('data-step') || '') === 'slots');
        if (slotsSection) {
            const slotsKey = slotsSection.getAttribute('data-step') || '';
            const previousState = this.state.sectionStates[slotsKey] || 'locked';

            this.updateSectionAttributes(slotsSection, 'locked', { silent: true });

            const slotsIndex = this.sections.indexOf(slotsSection);
            if (slotsIndex !== -1) {
                for (let index = slotsIndex + 1; index < this.sections.length; index += 1) {
                    const section = this.sections[index];
                    this.updateSectionAttributes(section, 'locked', { silent: true });
                }
            }

            this.updateProgressIndicators();

            if ((options.forceRewind && slotsKey) || previousState === 'completed' || previousState === 'active') {
                this.activateSectionByKey(slotsKey);
            }
        }

        if (options.schedule !== false) {
            this.scheduleAvailabilityUpdate();
        }

        this.updateSummary();
        this.updateSubmitState();
    }

    ensureSectionActive(section) {
        const key = section.getAttribute('data-step') || '';
        if (this.state.sectionStates[key] === 'locked') {
            this.state.sectionStates[key] = 'active';
            this.updateSectionAttributes(section, 'active');
            this.dispatchSectionUnlocked(key);
            // Non fare scroll automatico per evitare salti anchor
        }
    }

    completeSection(section, advance) {
        const key = section.getAttribute('data-step') || '';
        if (this.state.sectionStates[key] === 'completed') {
            return;
        }

        this.state.sectionStates[key] = 'completed';
        this.updateSectionAttributes(section, 'completed');
        this.updateProgressIndicators();

        if (!advance) {
            return;
        }

        const currentIndex = this.sections.indexOf(section);
        if (currentIndex === -1) {
            return;
        }

        const nextSection = this.sections[currentIndex + 1];
        if (!nextSection) {
            return;
        }

        const nextKey = nextSection.getAttribute('data-step') || String(currentIndex + 1);
        if (this.state.sectionStates[nextKey] !== 'completed') {
            this.state.sectionStates[nextKey] = 'active';
            this.updateSectionAttributes(nextSection, 'active');
            this.dispatchSectionUnlocked(nextKey);
            // Scroll all'inizio del widget quando si avanza allo step successivo
            this.scrollIntoView(nextSection);
        }
    }

    navigateToPrevious(section) {
        const index = this.sections.indexOf(section);
        if (index <= 0) {
            return;
        }

        const previousSection = this.sections[index - 1];
        if (!previousSection) {
            return;
        }

        const previousKey = previousSection.getAttribute('data-step') || '';
        if (!previousKey) {
            return;
        }

        this.activateSectionByKey(previousKey);
    }

    navigateToNext(section) {
        const stepKey = section.getAttribute('data-step') || '';
        
        // Per lo step date, verifica che sia stata selezionata una data
        if (stepKey === 'date') {
            const dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null;
            
            // Se non è stata selezionata una data, mostra un messaggio e BLOCCA la navigazione
            if (!dateField || dateField.value.trim() === '') {
                const dateSection = this.sections.find((s) => (s.getAttribute('data-step') || '') === 'date');
                if (dateSection) {
                    const statusEl = dateSection.querySelector('[data-fp-resv-date-status]');
                    if (statusEl) {
                        statusEl.textContent = this.copy.dateRequired || 'Seleziona una data per continuare.';
                        statusEl.style.color = '#dc2626';
                        statusEl.setAttribute('data-state', 'error');
                        statusEl.hidden = false;
                        statusEl.removeAttribute('hidden');
                        
                        setTimeout(() => {
                            statusEl.textContent = '';
                            statusEl.style.color = '';
                            statusEl.removeAttribute('data-state');
                            statusEl.hidden = true;
                            statusEl.setAttribute('hidden', '');
                        }, 3000);
                    }
                }
                // BLOCCA la navigazione - non procedere
                return;
            }
            
            this.completeSection(section, true);
            return;
        }
        
        // Permetti la navigazione per lo step party senza validazione rigorosa
        if (stepKey === 'party') {
            this.completeSection(section, true);
            return;
        }
        
        // Per lo step slots, controlla se ci sono slot disponibili e BLOCCA se non selezionati
        if (stepKey === 'slots') {
            const timeField = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
            const slotStartField = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null;
            
            // Se non ci sono slot selezionati, mostra un messaggio e BLOCCA la navigazione
            if (!timeField || timeField.value.trim() === '' || !slotStartField || slotStartField.value.trim() === '') {
                const slotsSection = this.sections.find((s) => (s.getAttribute('data-step') || '') === 'slots');
                if (slotsSection) {
                    const statusEl = slotsSection.querySelector('[data-fp-resv-slots-status]');
                    if (statusEl) {
                        statusEl.textContent = this.copy.slotRequired || 'Seleziona un orario per continuare.';
                        statusEl.style.color = '#dc2626';
                        statusEl.setAttribute('data-state', 'error');
                        statusEl.hidden = false;
                        statusEl.removeAttribute('hidden');
                        
                        setTimeout(() => {
                            statusEl.textContent = '';
                            statusEl.style.color = '';
                            statusEl.removeAttribute('data-state');
                            statusEl.hidden = true;
                            statusEl.setAttribute('hidden', '');
                        }, 3000);
                    }
                }
                // BLOCCA la navigazione - non procedere
                return;
            }
        }
        
        if (!this.isSectionValid(section)) {
            const invalid = this.findFirstInvalid(section);
            if (invalid) {
                if (typeof invalid.reportValidity === 'function') {
                    invalid.reportValidity();
                }
                if (typeof invalid.focus === 'function') {
                    invalid.focus({ preventScroll: false });
                }
            }
            return;
        }

        this.completeSection(section, true);
    }

    dispatchSectionUnlocked(key) {
        if (this.state.unlocked[key]) {
            return;
        }

        this.state.unlocked[key] = true;
        const eventName = this.events.section_unlocked || 'section_unlocked';
        pushDataLayerEvent(eventName, { section: key });
    }

    updateSectionAttributes(section, state, options = {}) {
        const key = section.getAttribute('data-step') || '';
        const silent = options && options.silent === true;
        console.log(`[FP-RESV] updateSectionAttributes: step=${key}, state=${state}, silent=${silent}`);
        
        this.state.sectionStates[key] = state;
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
            console.log(`[FP-RESV] Step ${key} made visible`);
        } else {
            section.hidden = true;
            section.setAttribute('hidden', '');
            section.setAttribute('inert', '');
            section.style.display = 'none';
            section.style.visibility = 'hidden';
            section.style.opacity = '0';
            console.log(`[FP-RESV] Step ${key} hidden`);
        }

        if (!silent) {
            this.updateProgressIndicators();
        }

        this.updateStickyCtaVisibility();
    }

    updateProgressIndicators() {
        if (!this.progress) {
            return;
        }

        const _this = this;
        const items = (this.progressItems && this.progressItems.length)
            ? this.progressItems
            : Array.prototype.slice.call(this.progress.querySelectorAll('[data-step]'));
        let progressValue = 0;
        const total = items.length || 1;

        Array.prototype.forEach.call(items, function (item, index) {
            const key = item.getAttribute('data-step') || '';
            const state = _this.state.sectionStates[key] || 'locked';
            item.setAttribute('data-state', state);
            item.setAttribute('data-progress-state', state === 'completed' ? 'done' : state);
            const labelEl = item.querySelector('.fp-progress__label');
            if (labelEl) {
                if (state === 'active') {
                    labelEl.removeAttribute('aria-hidden');
                } else {
                    labelEl.setAttribute('aria-hidden', 'true');
                }
            }
            const isLocked = state === 'locked';
            item.tabIndex = isLocked ? -1 : 0;
            if (isLocked) {
                item.setAttribute('aria-disabled', 'true');
            } else {
                item.removeAttribute('aria-disabled');
            }
            if (state === 'active') {
                item.setAttribute('aria-current', 'step');
                progressValue = Math.max(progressValue, index + 0.5);
            } else {
                item.removeAttribute('aria-current');
            }
            if (state === 'completed') {
                item.setAttribute('data-completed', 'true');
                progressValue = Math.max(progressValue, index + 1);
            } else {
                item.removeAttribute('data-completed');
            }
        });

        const percentage = Math.min(100, Math.max(0, Math.round((progressValue / total) * 100)));
        this.progress.style.setProperty('--fp-progress-fill', percentage + '%');
    }

    isSectionValid(section) {
        const fields = section.querySelectorAll('[data-fp-resv-field]');
        if (fields.length === 0) {
            return true;
        }

        // Se siamo nello step "slots" richiediamo esplicitamente che sia selezionato un orario
        const stepKey = section.getAttribute('data-step') || '';
        if (stepKey === 'slots') {
            const timeField = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
            const slotStartField = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null;
            
            // Verifica che sia stato selezionato un orario (sia nel campo time che nel campo slot_start)
            const hasTimeSelection = timeField && timeField.value.trim() !== '';
            const hasSlotSelection = slotStartField && slotStartField.value.trim() !== '';
            
            if (!hasTimeSelection || !hasSlotSelection) {
                return false;
            }
        }

        let valid = true;
        Array.prototype.forEach.call(fields, function (field) {
            if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                valid = false;
            }
        });

        return valid;
    }

    updateSubmitState() {
        if (!this.submitButton) {
            return;
        }

        const isValid = this.form.checkValidity();
        if (this.state.sending) {
            this.setSubmitButtonState(false, 'sending');
        } else {
            this.setSubmitButtonState(isValid, null);
        }

        if (this.submitHint) {
            const hint = this.state.hintOverride || (isValid ? this.state.initialHint : this.copy.ctaDisabled);
            this.submitHint.textContent = hint;
        }

        if (isValid && !this.state.formValidEmitted) {
            const eventName = this.events.form_valid || 'form_valid';
            pushDataLayerEvent(eventName, { timestamp: Date.now() });
            this.state.formValidEmitted = true;
        }
    }

    updateInlineErrors() {
        if (!this.form) {
            return;
        }

        const map = {
            first_name: this.form.querySelector('[data-fp-resv-field="first_name"]'),
            last_name: this.form.querySelector('[data-fp-resv-field="last_name"]'),
            email: this.form.querySelector('[data-fp-resv-field="email"]'),
            phone: this.form.querySelector('[data-fp-resv-field="phone"]'),
            consent: this.form.querySelector('[data-fp-resv-field="consent"]'),
        };

        const messages = {
            first_name: this.strings?.messages?.required_first_name || 'Inserisci il nome',
            last_name: this.strings?.messages?.required_last_name || 'Inserisci il cognome',
            email: this.copy.invalidEmail,
            phone: this.copy.invalidPhone,
            consent: this.strings?.messages?.required_consent || 'Accetta la privacy per procedere',
        };

        Object.keys(map).forEach((key) => {
            const field = map[key];
            const errorEl = this.form.querySelector(`[data-fp-resv-error="${key}"]`);
            if (!errorEl) {
                return;
            }

            // Non mostrare errori per i campi consent finché non sono stati toccati
            if (key === 'consent' && !this.state.touchedFields[key]) {
                errorEl.textContent = '';
                errorEl.hidden = true;
                return;
            }

            let visible = false;
            let text = '';
            if (field && typeof field.checkValidity === 'function' && !field.checkValidity()) {
                visible = true;
                text = messages[key] || '';
            }

            if (key === 'email' && field && field.value && field.value.trim() !== '' && field.checkValidity()) {
                visible = false;
                text = '';
            }

            if (key === 'phone' && this.phoneField) {
                const payload = buildPayload(this.phoneField, this.getPhoneCountryCode());
                if (payload.local && !isValidLocal(payload.local)) {
                    visible = true;
                    text = this.copy.invalidPhone;
                }
            }

            // Nascondi il messaggio di errore per il campo consent se è valido
            if (key === 'consent' && field && field.checked) {
                visible = false;
                text = '';
            }

            if (visible) {
                errorEl.textContent = text;
                errorEl.hidden = false;
                field && field.setAttribute && field.setAttribute('aria-invalid', 'true');
            } else {
                errorEl.textContent = '';
                errorEl.hidden = true;
                field && field.removeAttribute && field.removeAttribute('aria-invalid');
            }
        });
    }

    getActiveSectionKey() {
        for (let index = 0; index < this.sections.length; index += 1) {
            const section = this.sections[index];
            const key = section.getAttribute('data-step') || '';
            if (key !== '' && this.state.sectionStates[key] === 'active') {
                return key;
            }
        }

        return '';
    }

    getLastSectionKey() {
        if (this.sections.length === 0) {
            return '';
        }

        const lastSection = this.sections[this.sections.length - 1];
        return lastSection.getAttribute('data-step') || '';
    }

    updateStickyCtaVisibility() {
        if (!this.stickyCta) {
            return;
        }

        const lastKey = this.getLastSectionKey();
        if (lastKey === '') {
            this.stickyCta.hidden = false;
            this.stickyCta.removeAttribute('hidden');
            this.stickyCta.removeAttribute('aria-hidden');
            this.stickyCta.removeAttribute('inert');
            if (this.stickyCta.style && typeof this.stickyCta.style.removeProperty === 'function') {
                this.stickyCta.style.removeProperty('display');
            }
            return;
        }

        const activeKey = this.getActiveSectionKey();
        const shouldShow = activeKey === lastKey;

        if (shouldShow) {
            this.stickyCta.hidden = false;
            this.stickyCta.removeAttribute('hidden');
            this.stickyCta.removeAttribute('aria-hidden');
            this.stickyCta.removeAttribute('inert');
            if (this.stickyCta.style && typeof this.stickyCta.style.removeProperty === 'function') {
                this.stickyCta.style.removeProperty('display');
            }
        } else {
            this.stickyCta.hidden = true;
            this.stickyCta.setAttribute('hidden', '');
            this.stickyCta.setAttribute('aria-hidden', 'true');
            this.stickyCta.setAttribute('inert', '');
            if (this.stickyCta.style && typeof this.stickyCta.style.setProperty === 'function') {
                this.stickyCta.style.setProperty('display', 'none', 'important');
            }
        }
    }

    setSubmitButtonState(enabled, mode) {
        if (!this.submitButton) {
            return;
        }

        const effectiveEnabled = mode === 'sending' ? false : Boolean(enabled);
        const previousState = this.state.ctaEnabled;
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

        if (previousState !== effectiveEnabled && mode !== 'sending') {
            pushDataLayerEvent('cta_state_change', { enabled: effectiveEnabled });
        }

        this.state.ctaEnabled = effectiveEnabled;
    }

    updateSummary() {
        if (this.summaryTargets.length === 0) {
            return;
        }

        const date = this.form.querySelector('[data-fp-resv-field="date"]');
        const time = this.form.querySelector('[data-fp-resv-field="time"]');
        const party = this.form.querySelector('[data-fp-resv-field="party"]');
        const firstName = this.form.querySelector('[data-fp-resv-field="first_name"]');
        const lastName = this.form.querySelector('[data-fp-resv-field="last_name"]');
        const email = this.form.querySelector('[data-fp-resv-field="email"]');
        const phone = this.form.querySelector('[data-fp-resv-field="phone"]');
        const notes = this.form.querySelector('[data-fp-resv-field="notes"]');
        const extraHighChairs = this.form.querySelector('[data-fp-resv-field="high_chair_count"]');
        const extraWheelchair = this.form.querySelector('[data-fp-resv-field="wheelchair_table"]');
        const extraPets = this.form.querySelector('[data-fp-resv-field="pets"]');

        let nameValue = '';
        if (firstName && firstName.value) {
            nameValue = firstName.value.trim();
        }
        if (lastName && lastName.value) {
            nameValue = (nameValue + ' ' + lastName.value.trim()).trim();
        }

        let contactValue = '';
        if (email && email.value) {
            contactValue = email.value.trim();
        }
        if (phone && phone.value) {
            const prefixCode = this.getPhoneCountryCode();
            const prefixDisplay = prefixCode ? '+' + prefixCode + ' ' : '';
            const phoneDisplay = prefixDisplay + phone.value.trim();
            contactValue = contactValue !== '' ? contactValue + ' / ' + phoneDisplay : phoneDisplay;
        }

        const extras = [];
        if (extraHighChairs && typeof extraHighChairs.value === 'string' && parseInt(extraHighChairs.value, 10) > 0) {
            extras.push('Seggioloni: ' + parseInt(extraHighChairs.value, 10));
        }
        if (extraWheelchair && 'checked' in extraWheelchair && extraWheelchair.checked) {
            extras.push('Tavolo accessibile per sedia a rotelle');
        }
        if (extraPets && 'checked' in extraPets && extraPets.checked) {
            extras.push('Animali domestici');
        }
        const extrasText = extras.join('; ');

        this.summaryTargets.forEach(function (target) {
            const key = target.getAttribute('data-fp-resv-summary');
            switch (key) {
                case 'date':
                    target.textContent = date && date.value ? date.value : '';
                    break;
                case 'time':
                    target.textContent = time && time.value ? time.value : '';
                    break;
                case 'party':
                    target.textContent = party && party.value ? party.value : '';
                    break;
                case 'name':
                    target.textContent = nameValue;
                    break;
                case 'contact':
                    target.textContent = contactValue;
                    break;
                case 'notes':
                    target.textContent = notes && notes.value ? notes.value : '';
                    break;
                case 'extras':
                    target.textContent = extrasText;
                    break;
            }
        });
    }

    async handleSubmit(event) {
        event.preventDefault();

        // Protezione contro doppio submit
        if (this.state.sending) {
            return false;
        }

        // Segna tutti i campi come toccati quando si tenta di inviare
        this.state.touchedFields.consent = true;

        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            this.focusFirstInvalid();
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
        this.state.sending = true;
        this.updateSubmitState();
        this.clearError();

        const payload = this.serializeForm();
        
        // Genera un ID univoco per questa richiesta (idempotency key)
        // Se c'è un retry, userà lo stesso ID per evitare duplicati
        if (!this.state.requestId) {
            this.state.requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        payload.request_id = this.state.requestId;
        
        const endpoint = this.getReservationEndpoint();
        const start = performance.now();
        let latency = 0;

        // DEBUG: Log del payload inviato
        console.log('[FP-RESV] Payload inviato:', payload);
        console.log('[FP-RESV] Endpoint:', endpoint);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            latency = Math.round(performance.now() - start);
            pushDataLayerEvent('ui_latency', { op: 'submit', ms: latency });

            if (!response.ok) {
                const errorPayload = await safeJson(response);
                
                // DEBUG: Log dell'errore ricevuto
                console.error('[FP-RESV] Errore API:', {
                    status: response.status,
                    statusText: response.statusText,
                    errorPayload: errorPayload,
                });
                
                // Se errore 403 (nonce invalido), prova a rigenerare il nonce e riprova
                if (response.status === 403 && !this.state.nonceRetried) {
                    // Aspetta 500ms per dare tempo ai cookie di essere impostati correttamente
                    await new Promise(resolve => setTimeout(resolve, 500));
                    
                    const freshNonce = await this.refreshNonce();
                    if (freshNonce) {
                        this.state.nonceRetried = true;
                        payload.fp_resv_nonce = freshNonce;
                        
                        // Aspetta altri 200ms prima di riprovare
                        await new Promise(resolve => setTimeout(resolve, 200));
                        
                        // Riprova con il nonce fresco
                        const retryResponse = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                            credentials: 'same-origin',
                        });
                        
                        if (retryResponse.ok) {
                            const data = await retryResponse.json();
                            this.handleSubmitSuccess(data);
                            this.state.nonceRetried = false;
                            return false;
                        } else {
                            // Se anche il retry fallisce, aggiungi info sul cookie al messaggio di errore
                            const retryError = await safeJson(retryResponse);
                            if (retryError && retryError.message) {
                                retryError.message = retryError.message + ' Se hai appena accettato i cookie, riprova tra qualche secondo.';
                            }
                            throw Object.assign(new Error(retryError.message || this.copy.submitError), {
                                status: retryResponse.status,
                                payload: retryError,
                            });
                        }
                    }
                }
                
                const message = (errorPayload && errorPayload.message) || this.copy.submitError;
                throw Object.assign(new Error(message), {
                    status: response.status,
                    payload: errorPayload,
                });
            }

        const data = await response.json();
        this.handleSubmitSuccess(data);
        // Reset request_id dopo successo
        this.state.requestId = null;
    } catch (error) {
            if (!latency) {
                latency = Math.round(performance.now() - start);
                pushDataLayerEvent('ui_latency', { op: 'submit', ms: latency });
            }

            this.handleSubmitError(error, latency);
        } finally {
            this.state.sending = false;
            this.updateSubmitState();
        }

        return false;
    }

    handleSubmitSuccess(data) {
        this.clearError();
        const message = (data && data.message) || this.copy.submitSuccess;
        if (this.successAlert) {
            this.successAlert.textContent = message;
            this.successAlert.hidden = false;
            if (typeof this.successAlert.focus === 'function') {
                this.successAlert.focus();
            }
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

        if (data && Array.isArray(data.tracking)) {
            data.tracking.forEach((entry) => {
                if (entry && entry.event) {
                    pushDataLayerEvent(entry.event, entry);
                }
            });
        }
    }

    handleSubmitError(error, latency) {
        const status = error && typeof error.status === 'number' ? error.status : 'unknown';
        const message = (error && error.message) || this.copy.submitError;
        const debugSource = error && typeof error === 'object' ? error.payload || null : null;
        let finalMessage = formatDebugMessage(message, debugSource);

        // Se l'errore è 403 (nonce invalido), mostra il pulsante per ricaricare la pagina
        if (status === 403) {
            // Aggiungi un pulsante per ricaricare la pagina
            if (this.errorAlert && this.errorRetry) {
                this.errorRetry.textContent = this.messages.reload_button || 'Ricarica pagina';
                this.errorRetry.onclick = (event) => {
                    event.preventDefault();
                    window.location.reload();
                };
            }
        }

        if (this.errorAlert && this.errorMessage) {
            this.errorMessage.textContent = finalMessage;
            this.errorAlert.hidden = false;
            
            // Scroll automatico verso l'alert di errore
            requestAnimationFrame(() => {
                if (typeof this.errorAlert.scrollIntoView === 'function') {
                    this.errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                // Focus sull'alert per l'accessibilità
                if (typeof this.errorAlert.focus === 'function') {
                    this.errorAlert.setAttribute('tabindex', '-1');
                    this.errorAlert.focus({ preventScroll: true });
                }
            });
        }

        this.state.hintOverride = finalMessage;
        this.updateSubmitState();

        const eventName = this.events.submit_error || 'submit_error';
        pushDataLayerEvent(eventName, { code: status, latency });
    }

    clearError() {
        if (this.errorAlert) {
            this.errorAlert.hidden = true;
        }
        
        // Ripristina il pulsante Riprova al suo stato originale
        if (this.errorRetry) {
            this.errorRetry.textContent = this.messages.retry_button || 'Riprova';
            this.errorRetry.onclick = null;
        }
        
        this.state.hintOverride = '';
    }

    serializeForm() {
        const formData = new FormData(this.form);
        const payload = {};
        formData.forEach((value, key) => {
            if (typeof value === 'string') {
                payload[key] = value;
            }
        });

        if (this.phoneField) {
            const phoneData = buildPayload(this.phoneField, this.getPhoneCountryCode());
            if (phoneData.e164) {
                payload.fp_resv_phone = phoneData.e164;
            }
            if (phoneData.country) {
                payload.fp_resv_phone_cc = phoneData.country;
            }
            if (phoneData.local) {
                payload.fp_resv_phone_local = phoneData.local;
            }
        }

        if (this.phonePrefixField && this.phonePrefixField.value && !payload.fp_resv_phone_cc) {
            const normalizedPrefix = normalizeCountryCode(this.phonePrefixField.value);
            if (normalizedPrefix) {
                payload.fp_resv_phone_cc = normalizedPrefix;
            }
        }

        return payload;
    }

    async refreshNonce() {
        try {
            const nonceEndpoint = this.getReservationEndpoint().replace('/reservations', '/nonce');
            const response = await fetch(nonceEndpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                // Aggiorna anche il campo nascosto nel form
                const nonceField = this.form.querySelector('input[name="fp_resv_nonce"]');
                if (nonceField && data.nonce) {
                    nonceField.value = data.nonce;
                }
                return data.nonce || null;
            }
        } catch (error) {
            if (window.console && window.console.warn) {
                console.warn('[fp-resv] Impossibile rigenerare il nonce', error);
            }
        }
        return null;
    }

    preparePhonePayload() {
        if (!this.phoneField) {
            return;
        }

        const phoneData = buildPayload(this.phoneField, this.getPhoneCountryCode());
        if (this.hiddenPhoneE164) {
            this.hiddenPhoneE164.value = phoneData.e164;
        }
        if (this.hiddenPhoneCc) {
            this.hiddenPhoneCc.value = phoneData.country;
        }
        if (this.hiddenPhoneLocal) {
            this.hiddenPhoneLocal.value = phoneData.local;
        }
    }

    validatePhoneField() {
        if (!this.phoneField) {
            return;
        }

        const payload = buildPayload(this.phoneField, this.getPhoneCountryCode());
        if (payload.local === '') {
            this.phoneField.setCustomValidity('');
            this.phoneField.removeAttribute('aria-invalid');
            return;
        }

        if (!isValidLocal(payload.local)) {
            this.phoneField.setCustomValidity(this.copy.invalidPhone);
            this.phoneField.setAttribute('aria-invalid', 'true');
            this.state.hintOverride = this.copy.invalidPhone;
            this.updateSubmitState();
            pushDataLayerEvent('phone_validation_error', { field: 'phone' });
            pushDataLayerEvent('ui_validation_error', { field: 'phone' });
        } else {
            this.phoneField.setCustomValidity('');
            this.phoneField.setAttribute('aria-invalid', 'false');
            if (this.state.hintOverride === this.copy.invalidPhone) {
                this.state.hintOverride = '';
                this.updateSubmitState();
            }
        }
    }

    validateEmailField(field) {
        // Normalizza e pulisci eventuale stato di errore precedente
        if (typeof field.value === 'string') {
            const trimmed = field.value.trim();
            if (trimmed !== field.value) {
                field.value = trimmed;
            }
        }

        if (field.value.trim() === '') {
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            return;
        }

        // Rimuove eventuali errori custom prima del controllo nativo,
        // altrimenti checkValidity() fallisce sempre
        field.setCustomValidity('');

        if (!field.checkValidity()) {
            field.setCustomValidity(this.copy.invalidEmail);
            field.setAttribute('aria-invalid', 'true');
            this.state.hintOverride = this.copy.invalidEmail;
            this.updateSubmitState();
            pushDataLayerEvent('ui_validation_error', { field: 'email' });
        } else {
            field.setCustomValidity('');
            field.setAttribute('aria-invalid', 'false');
            if (this.state.hintOverride === this.copy.invalidEmail) {
                this.state.hintOverride = '';
                this.updateSubmitState();
            }
        }
    }

    focusFirstInvalid() {
        const invalid = this.form.querySelector('[data-fp-resv-field]:invalid, [required]:invalid');
        if (invalid && typeof invalid.focus === 'function') {
            invalid.focus();
        }
    }

    findFirstInvalid(section) {
        if (!section) {
            return null;
        }

        return section.querySelector('[data-fp-resv-field]:invalid, [required]:invalid');
    }

    collectAvailabilityParams() {
        const meal = this.hiddenMeal ? this.hiddenMeal.value : '';
        const dateValue = this.dateField && this.dateField.value ? this.dateField.value : '';
        const partyValue = this.partyField && this.partyField.value ? this.partyField.value : '';
        return {
            date: dateValue,
            party: partyValue,
            meal,
            requiresMeal: this.mealButtons.length > 0,
        };
    }

    scheduleAvailabilityUpdate(options = {}) {
        const normalizedOptions = options && typeof options === 'object'
            ? { ...options }
            : {};

        if (!this.availabilityController || typeof this.availabilityController.schedule !== 'function') {
            this.state.pendingAvailability = true;
            this.state.pendingAvailabilityOptions = normalizedOptions;
            return;
        }

        const params = this.collectAvailabilityParams();
        this.state.lastAvailabilityParams = params;
        this.state.pendingAvailabilityOptions = null;
        this.availabilityController.schedule(params, normalizedOptions);
    }

    applyMealAvailabilityIndicator(meal, state) {
        if (!meal) {
            return;
        }

        const button = this.mealButtons.find((item) => (item.getAttribute('data-fp-resv-meal') || '') === meal);
        if (!button) {
            return;
        }

        const validStates = ['available', 'limited', 'full', 'unavailable'];
        const normalized = state ? String(state).toLowerCase() : '';

        // Non mostriamo più i colori sui bottoni, solo lo stato di disabilitazione
        button.removeAttribute('data-availability-state');

        // Se è completamente prenotato o non disponibile, disabilitiamo il bottone
        if (normalized === 'full' || normalized === 'unavailable') {
            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('data-meal-unavailable', 'true');
        } else if (validStates.indexOf(normalized) !== -1) {
            button.removeAttribute('aria-disabled');
            button.removeAttribute('data-meal-unavailable');
        }
    }

    handleMealAvailabilitySummary(summary, params) {
        if (!params || !params.meal) {
            return;
        }

        const normalized = summary && summary.state ? String(summary.state).toLowerCase() : '';
        const validStates = ['available', 'limited', 'full', 'unavailable'];
        const mealKey = params.meal;

        if (!this.state.mealAvailability) {
            this.state.mealAvailability = {};
        }

        if (validStates.indexOf(normalized) === -1) {
            delete this.state.mealAvailability[mealKey];
            this.applyMealAvailabilityIndicator(mealKey, '');
            this.applyMealAvailabilityNotice(mealKey, '', { skipSlotReset: true });
            return;
        }

        this.state.mealAvailability[mealKey] = normalized;
        this.applyMealAvailabilityIndicator(mealKey, normalized);
        this.applyMealAvailabilityNotice(mealKey, normalized);

        // Mostra la legenda nello step slot quando ci sono informazioni sulla disponibilità
        if (this.slotsLegend && this.slotsLegend.hidden) {
            this.slotsLegend.hidden = false;
            this.slotsLegend.removeAttribute('hidden');
        }

        if (this.availabilityIndicator) {
            let label = '';
            if (summary && typeof summary === 'object') {
                const slots = typeof summary.slots === 'number' ? summary.slots : 0;
                if (normalized === 'available') {
                    label = `Disponibile (${slots})`;
                } else if (normalized === 'limited') {
                    label = `Disponibilità limitata (${slots})`;
                } else if (normalized === 'full') {
                    label = 'Completamente prenotato';
                } else if (normalized === 'unavailable') {
                    label = 'Non disponibile per questa data';
                }
            }
            this.availabilityIndicator.textContent = label;
            this.availabilityIndicator.hidden = label === '';
            this.availabilityIndicator.setAttribute('data-state', normalized || '');
        }
    }

    handleSlotSelected(slot) {
        this.handleFirstInteraction();
        const timeField = this.form.querySelector('[data-fp-resv-field=\"time\"]');
        if (timeField) {
            timeField.value = slot && slot.label ? slot.label : '';
            if (slot && slot.start) {
                timeField.setAttribute('data-slot-start', slot.start);
            }
            try {
                timeField.dispatchEvent(new Event('input', { bubbles: true }));
            } catch (error) {
                // noop
            }
        }

        if (this.hiddenSlot) {
            this.hiddenSlot.value = slot && slot.start ? slot.start : '';
        }

        const slotsSection = this.sections.find((section) => (section.getAttribute('data-step') || '') === 'slots');
        if (slotsSection) {
            const slotsKey = slotsSection.getAttribute('data-step') || '';
            this.ensureSectionActive(slotsSection);
            if (this.state.sectionStates[slotsKey] !== 'active') {
                this.updateSectionAttributes(slotsSection, 'active');
            }
        }

        this.updateSummary();
        this.updateSubmitState();
    }

    handleAvailabilityLatency(ms) {
        pushDataLayerEvent('ui_latency', { op: 'availability', ms: Math.round(ms) });
    }

    handleAvailabilityRetry(attempt) {
        pushDataLayerEvent('availability_retry', { attempt });
    }

    handleWindowFocus() {
        if (this.availabilityController && typeof this.availabilityController.revalidate === 'function') {
            this.availabilityController.revalidate();
        }
    }

    handleFirstInteraction() {
        if (this.state.started) {
            return;
        }

        const eventName = this.events.start || 'reservation_start';
        pushDataLayerEvent(eventName, { source: 'form' });
        this.state.started = true;
    }

    handleDelegatedTrackingEvent(event) {
        const target = event.target instanceof HTMLElement ? event.target : null;
        if (!target) {
            return;
        }

        const element = closestWithAttribute(target, 'data-fp-resv-event');
        if (!element) {
            return;
        }

        const name = element.getAttribute('data-fp-resv-event');
        if (!name) {
            return;
        }

        let payload = parseJsonAttribute(element, 'data-fp-resv-payload');
        if (!payload || typeof payload !== 'object') {
            payload = {};
        }

        if (!payload.trigger) {
            payload.trigger = event.type || 'click';
        }

        if (!payload.href && element instanceof HTMLAnchorElement && element.href) {
            payload.href = element.href;
        }

        if (!payload.label) {
            const label = element.getAttribute('data-fp-resv-label') || element.getAttribute('aria-label') || element.textContent || '';
            if (label) {
                payload.label = label.trim();
            }
        }

        pushDataLayerEvent(name, payload);
    }

    handleReservationConfirmed(event) {
        if (!event || !event.detail) {
            return;
        }

        const detail = event.detail || {};
        const eventName = this.events.confirmed || 'reservation_confirmed';
        pushDataLayerEvent(eventName, detail);

        if (detail && detail.purchase && detail.purchase.value && detail.purchase.value_is_estimated) {
            pushDataLayerEvent(this.events.purchase || 'purchase', detail.purchase);
        }
    }

    scrollIntoView(section) {
        // Scroll all'inizio del widget quando si cambia step
        const target = this.root || section;
        if (!target || typeof target.scrollIntoView !== 'function') {
            return;
        }

        requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    isConsentField(element) {
        if (!element || !element.getAttribute) {
            return false;
        }

        const key = element.getAttribute('data-fp-resv-field') || '';
        return key === 'consent' || key === 'marketing_consent' || key === 'profiling_consent';
    }

    syncConsentState() {
        const tracking = window.fpResvTracking;
        if (!tracking || typeof tracking.updateConsent !== 'function') {
            return;
        }

        const updates = {};
        let changed = false;

        const policy = this.form.querySelector('[data-fp-resv-field=\"consent\"]');
        if (policy && 'checked' in policy) {
            updates.analytics = policy.checked ? 'granted' : 'denied';
            updates.clarity = policy.checked ? 'granted' : 'denied';
            changed = true;
        }

        const marketing = this.form.querySelector('[data-fp-resv-field=\"marketing_consent\"]');
        if (marketing && 'checked' in marketing) {
            updates.ads = marketing.checked ? 'granted' : 'denied';
            changed = true;
        }

        const profiling = this.form.querySelector('[data-fp-resv-field=\"profiling_consent\"]');
        if (profiling && 'checked' in profiling) {
            updates.personalization = profiling.checked ? 'granted' : 'denied';
            changed = true;
        }

        if (!changed) {
            return;
        }

        tracking.updateConsent(updates);
    }

    getPhoneCountryCode() {
        if (this.phonePrefixField && this.phonePrefixField.value) {
            const fromPrefix = normalizeCountryCode(this.phonePrefixField.value);
            if (fromPrefix) {
                return fromPrefix;
            }
        }

        if (this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
            const fromHidden = normalizeCountryCode(this.hiddenPhoneCc.value);
            if (fromHidden) {
                return fromHidden;
            }
        }

        if (this.phoneCountryCode) {
            const fromState = normalizeCountryCode(this.phoneCountryCode);
            if (fromState) {
                return fromState;
            }
        }

        const defaults = (this.config && this.config.defaults) || {};
        if (defaults.phone_country_code) {
            const fromDefaults = normalizeCountryCode(defaults.phone_country_code);
            if (fromDefaults) {
                return fromDefaults;
            }
        }

        return '39';
    }

    getReservationEndpoint() {
        const endpoints = this.config.endpoints || {};
        return resolveEndpoint(endpoints.reservations, '/wp-json/fp-resv/v1/reservations');
    }

    getAvailabilityEndpoint() {
        const endpoints = this.config.endpoints || {};
        return resolveEndpoint(endpoints.availability, '/wp-json/fp-resv/v1/availability');
    }

    loadExternalScript(url, globalGetter, markerAttr) {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            return Promise.resolve(null);
        }

        if (typeof globalGetter === 'function') {
            const existing = globalGetter();
            if (existing) {
                return Promise.resolve(existing);
            }
        }

        return new Promise((resolve) => {
            const resolveWithGlobal = () => {
                if (typeof globalGetter === 'function') {
                    const value = globalGetter();
                    resolve(value || null);
                    return;
                }

                resolve(null);
            };

            let script = document.querySelector(`script[src="${url}"]`);
            if (!script && markerAttr) {
                script = document.querySelector(`script[${markerAttr}]`);
            }

            if (script) {
                if (typeof globalGetter === 'function') {
                    const immediate = globalGetter();
                    if (immediate) {
                        resolve(immediate);
                        return;
                    }
                }

                script.addEventListener('load', resolveWithGlobal, { once: true });
                script.addEventListener('error', () => resolve(null), { once: true });
                return;
            }

            script = document.createElement('script');
            script.src = url;
            script.async = true;
            if (markerAttr) {
                script.setAttribute(markerAttr, '1');
            }

            script.onload = resolveWithGlobal;
            script.onerror = () => resolve(null);

            const target = document.head || document.body || document.documentElement;
            if (!target) {
                resolve(null);
                return;
            }

            target.appendChild(script);
        });
    }

    loadStripeIfNeeded() {
        const feature = this.integrations && (this.integrations.stripe || this.integrations.payments_stripe);
        if (!feature || (typeof feature === 'object' && feature.enabled === false)) {
            return Promise.resolve(null);
        }

        if (typeof window !== 'undefined' && window.Stripe) {
            return Promise.resolve(window.Stripe);
        }

        if (!this.stripePromise) {
            this.stripePromise = this.loadExternalScript(
                'https://js.stripe.com/v3/',
                () => (typeof window !== 'undefined' ? window.Stripe : null),
                'data-fp-resv-stripe'
            );
        }

        return this.stripePromise;
    }

    loadGoogleCalendarIfNeeded() {
        const feature = this.integrations && (this.integrations.googleCalendar || this.integrations.calendar_google);
        if (!feature || (typeof feature === 'object' && feature.enabled === false)) {
            return Promise.resolve(null);
        }

        if (typeof window !== 'undefined' && window.gapi) {
            return Promise.resolve(window.gapi);
        }

        if (!this.googlePromise) {
            this.googlePromise = this.loadExternalScript(
                'https://apis.google.com/js/api.js',
                () => (typeof window !== 'undefined' ? window.gapi : null),
                'data-fp-resv-google-api'
            );
        }

        return this.googlePromise;
    }
}

if (typeof window !== 'undefined') {
    window.FPResv = window.FPResv || {};
    window.FPResv.FormApp = FormApp;
    window.fpResvApp = window.FPResv; // Alias per compatibilità
}

export { FormApp };

// Il bootstrap è stato spostato in assets/js/fe/init.js
