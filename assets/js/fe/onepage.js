
import { applyMask, buildPayload, isValidLocal, normalizeCountryCode } from './phone.js';

let availabilityModulePromise = null;
const idleCallback = typeof window !== 'undefined' && typeof window.requestIdleCallback === 'function'
    ? (callback) => window.requestIdleCallback(callback)
    : (callback) => window.setTimeout(() => callback(Date.now()), 1);

function loadAvailabilityModule() {
    if (!availabilityModulePromise) {
        availabilityModulePromise = import('./availability.js');
    }

    return availabilityModulePromise;
}

function parseDataset(root) {
    const raw = root.getAttribute('data-fp-resv');
    if (!raw) {
        return {};
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        if (window.console && window.console.warn) {
            console.warn('[fp-resv] Impossibile analizzare il dataset del widget', error);
        }
    }

    return {};
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
    if (!element) {
        return {};
    }

    const raw = element.getAttribute(attribute);
    if (!raw) {
        return {};
    }

    try {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
            return parsed;
        }
    } catch (error) {
        if (window.console && window.console.warn) {
            console.warn("[fp-resv] Impossibile analizzare l'attributo", attribute, error);
        }
    }

    return {};
}

function setAriaDisabled(element, disabled) {
    if (!element) {
        return;
    }

    if (disabled) {
        element.setAttribute('aria-disabled', 'true');
        element.setAttribute('disabled', 'disabled');
    } else {
        element.removeAttribute('disabled');
        element.setAttribute('aria-disabled', 'false');
    }
}

function toNumber(value) {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const normalized = String(value).replace(',', '.');
    const parsed = parseFloat(normalized);

    return Number.isNaN(parsed) ? null : parsed;
}

function resolveEndpoint(endpoint, fallback) {
    if (endpoint && typeof endpoint === 'string') {
        try {
            return new URL(endpoint, window.location.origin).toString();
        } catch (error) {
            return endpoint;
        }
    }

    if (window.wpApiSettings && window.wpApiSettings.root) {
        const root = window.wpApiSettings.root.replace(/\/$/, '');
        return root + fallback;
    }

    return fallback;
}

function firstFocusable(section) {
    if (!section) {
        return null;
    }

    const selectors = 'input:not([type=\"hidden\"]), select, textarea, button, [tabindex=\"0\"]';
    return section.querySelector(selectors);
}

const STEP_ORDER = ['date', 'party', 'slots', 'details', 'confirm'];

function safeJson(response) {
    return response.text().then((text) => {
        if (!text) {
            return {};
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            return {};
        }
    });
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
        this.successAlert = this.form ? this.form.querySelector('[data-fp-resv-success]') : null;
        this.errorAlert = this.form ? this.form.querySelector('[data-fp-resv-error]') : null;
        this.errorMessage = this.form ? this.form.querySelector('[data-fp-resv-error-message]') : null;
        this.errorRetry = this.form ? this.form.querySelector('[data-fp-resv-error-retry]') : null;
        this.mealButtons = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-meal]'));
        this.mealNotice = root.querySelector('[data-fp-resv-meal-notice]');
        this.hiddenMeal = this.form ? this.form.querySelector('input[name=\"fp_resv_meal\"]') : null;
        this.hiddenPrice = this.form ? this.form.querySelector('input[name=\"fp_resv_price_per_person\"]') : null;
        this.hiddenSlot = this.form ? this.form.querySelector('input[name=\"fp_resv_slot_start\"]') : null;
        this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field=\"date\"]') : null;
        this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field=\"party\"]') : null;
        this.summaryTargets = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-summary]'));
        this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field=\"phone\"]') : null;
        this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name=\"fp_resv_phone_e164\"]') : null;
        this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name=\"fp_resv_phone_cc\"]') : null;
        this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name=\"fp_resv_phone_local\"]') : null;
        this.availabilityRoot = this.form ? this.form.querySelector('[data-fp-resv-slots]') : null;

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
            lastAvailabilityParams: null,
        };

        this.copy = {
            ctaDisabled: this.messages.cta_complete_fields || 'Complete required fields',
            ctaEnabled: (this.messages.cta_book_now || (this.strings.actions && this.strings.actions.submit) || 'Book now'),
            ctaSending: this.messages.cta_sending || 'Sending…',
            updatingSlots: this.messages.msg_updating_slots || 'Updating availability…',
            slotsUpdated: this.messages.msg_slots_updated || 'Availability updated.',
            slotsEmpty: this.messages.slots_empty || '',
            selectMeal: this.messages.msg_select_meal || 'Select a meal to view available times.',
            slotsError: this.messages.msg_slots_error || 'We could not update available times. Please try again.',
            invalidPhone: this.messages.msg_invalid_phone || 'Enter a valid phone number (minimum 6 digits).',
            invalidEmail: this.messages.msg_invalid_email || 'Enter a valid email address.',
            submitError: this.messages.msg_submit_error || 'We could not complete your reservation. Please try again.',
            submitSuccess: this.messages.msg_submit_success || 'Reservation sent successfully.',
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
        this.initializeMeals();
        this.initializeDateField();
        this.initializeAvailability();
        this.syncConsentState();
        this.updateSubmitState();
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
            _this.state.sectionStates[key] = index === 0 ? 'active' : 'locked';
            if (index === 0) {
                _this.dispatchSectionUnlocked(key);
            }
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

    initializeDateField() {
        if (!this.dateField) {
            return;
        }

        const openPicker = () => {
            if (typeof this.dateField.showPicker === 'function') {
                try {
                    this.dateField.showPicker();
                } catch (error) {
                    // Alcuni browser (es. Safari) potrebbero non supportare showPicker: ignora.
                }
            }
        };

        this.dateField.addEventListener('focus', openPicker);
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

        const schedule = () => {
            if (!this.availabilityController) {
                this.state.pendingAvailability = true;
                return;
            }

            this.scheduleAvailabilityUpdate();
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
                    });

                    if (this.state.pendingAvailability) {
                        this.state.pendingAvailability = false;
                        this.scheduleAvailabilityUpdate();
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
        }

        this.updateSummary();

        const section = closestSection(target);
        if (!section) {
            if (this.isConsentField(target)) {
                this.syncConsentState();
            }
            this.updateSubmitState();
            return;
        }

        this.ensureSectionActive(section);
        if (this.isSectionValid(section)) {
            this.completeSection(section, true);
        } else {
            this.updateSectionAttributes(section, 'active');
        }

        const fieldKey = target.getAttribute('data-fp-resv-field') || '';
        if (fieldKey === 'date' || fieldKey === 'party' || fieldKey === 'slots' || fieldKey === 'time') {
            if (fieldKey === 'date' || fieldKey === 'party') {
                this.clearSlotSelection({ schedule: false });
            }
            this.scheduleAvailabilityUpdate();
        }

        if (this.isConsentField(target)) {
            this.syncConsentState();
        }

        this.updateSubmitState();
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

        if (fieldKey === 'phone' && this.phoneField) {
            this.validatePhoneField();
        }

        if (fieldKey === 'email' && target instanceof HTMLInputElement) {
            this.validateEmailField(target);
        }
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
        this.handleFirstInteraction();

        const direction = trigger.getAttribute('data-fp-resv-nav');
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
        this.scrollIntoView(targetSection);
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
        this.applyMealSelection(button);

        const mealEvent = this.events.meal_selected || 'meal_selected';
        pushDataLayerEvent(mealEvent, {
            meal_type: button.getAttribute('data-fp-resv-meal') || '',
            meal_label: button.getAttribute('data-meal-label') || '',
        });

        this.scheduleAvailabilityUpdate();
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

        const notice = button.getAttribute('data-meal-notice');
        if (this.mealNotice) {
            if (notice && notice.trim() !== '') {
                this.mealNotice.textContent = notice;
                this.mealNotice.hidden = false;
            } else {
                this.mealNotice.textContent = '';
                this.mealNotice.hidden = true;
            }
        }

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
            this.scrollIntoView(section);
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
        this.state.sectionStates[key] = state;
        section.setAttribute('data-state', state);

        if (state === 'completed') {
            section.setAttribute('data-complete-hidden', 'true');
        } else {
            section.removeAttribute('data-complete-hidden');
        }

        const isActive = state === 'active';
        section.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        section.setAttribute('aria-expanded', isActive ? 'true' : 'false');

        if (isActive) {
            section.hidden = false;
            section.removeAttribute('hidden');
            section.removeAttribute('inert');
        } else {
            section.hidden = true;
            section.setAttribute('hidden', '');
            section.setAttribute('inert', '');
        }

        if (!silent) {
            this.updateProgressIndicators();
        }
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

        const date = this.form.querySelector('[data-fp-resv-field=\"date\"]');
        const time = this.form.querySelector('[data-fp-resv-field=\"time\"]');
        const party = this.form.querySelector('[data-fp-resv-field=\"party\"]');
        const firstName = this.form.querySelector('[data-fp-resv-field=\"first_name\"]');
        const lastName = this.form.querySelector('[data-fp-resv-field=\"last_name\"]');
        const email = this.form.querySelector('[data-fp-resv-field=\"email\"]');
        const phone = this.form.querySelector('[data-fp-resv-field=\"phone\"]');
        const notes = this.form.querySelector('[data-fp-resv-field=\"notes\"]');

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
            contactValue = contactValue !== '' ? contactValue + ' / ' + phone.value.trim() : phone.value.trim();
        }

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
            }
        });
    }

    async handleSubmit(event) {
        event.preventDefault();

        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            this.focusFirstInvalid();
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
        const endpoint = this.getReservationEndpoint();
        const start = performance.now();
        let latency = 0;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': payload.fp_resv_nonce || '',
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            latency = Math.round(performance.now() - start);
            pushDataLayerEvent('ui_latency', { op: 'submit', ms: latency });

            if (!response.ok) {
                const errorPayload = await safeJson(response);
                const message = (errorPayload && errorPayload.message) || this.copy.submitError;
                throw Object.assign(new Error(message), {
                    status: response.status,
                    payload: errorPayload,
                });
            }

            const data = await response.json();
            this.handleSubmitSuccess(data);
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

        if (this.errorAlert && this.errorMessage) {
            this.errorMessage.textContent = message;
            this.errorAlert.hidden = false;
        }

        this.state.hintOverride = message;
        this.updateSubmitState();

        const eventName = this.events.submit_error || 'submit_error';
        pushDataLayerEvent(eventName, { code: status, latency });
    }

    clearError() {
        if (this.errorAlert) {
            this.errorAlert.hidden = true;
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
        return payload;
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
        if (field.value.trim() === '') {
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            return;
        }

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
        };
    }

    scheduleAvailabilityUpdate() {
        if (!this.availabilityController) {
            this.state.pendingAvailability = true;
            return;
        }

        const params = this.collectAvailabilityParams();
        this.state.lastAvailabilityParams = params;
        if (this.availabilityController && typeof this.availabilityController.schedule === 'function') {
            this.availabilityController.schedule(params);
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
            this.ensureSectionActive(slotsSection);
            if (slot && slot.start) {
                this.completeSection(slotsSection, true);
            } else {
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
        if (typeof section.scrollIntoView === 'function') {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        const focusable = firstFocusable(section);
        if (focusable && typeof focusable.focus === 'function') {
            focusable.focus({ preventScroll: true });
        }
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
        if (this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
            return normalizeCountryCode(this.hiddenPhoneCc.value) || '39';
        }

        const defaults = (this.config && this.config.defaults) || {};
        if (defaults.phone_country_code) {
            return normalizeCountryCode(defaults.phone_country_code) || '39';
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

    loadStripeIfNeeded() {
        const feature = this.integrations && (this.integrations.stripe || this.integrations.payments_stripe);
        if (!feature || (typeof feature === 'object' && feature.enabled === false)) {
            return Promise.resolve(null);
        }

        if (!this.stripePromise) {
            this.stripePromise = import(/* webpackIgnore: true */ 'https://js.stripe.com/v3/').catch(() => null);
        }

        return this.stripePromise;
    }

    loadGoogleCalendarIfNeeded() {
        const feature = this.integrations && (this.integrations.googleCalendar || this.integrations.calendar_google);
        if (!feature || (typeof feature === 'object' && feature.enabled === false)) {
            return Promise.resolve(null);
        }

        if (!this.googlePromise) {
            this.googlePromise = import(/* webpackIgnore: true */ 'https://apis.google.com/js/api.js').catch(() => null);
        }

        return this.googlePromise;
    }
}

if (typeof window !== 'undefined') {
    window.FPResv = window.FPResv || {};
    window.FPResv.FormApp = FormApp;
}

document.addEventListener('DOMContentLoaded', function () {
    const widgets = document.querySelectorAll('[data-fp-resv]');
    Array.prototype.forEach.call(widgets, function (widget) {
        new FormApp(widget);
    });
});

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
