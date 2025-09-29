(function () {
    'use strict';

    /**
     * @param {HTMLElement} root
     * @returns {Record<string, any>}
     */
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

    /**
     * @param {string} name
     * @param {Record<string, any>} payload
     */
    function pushDataLayerEvent(name, payload) {
        if (!name) {
            return;
        }

        /** @type {Record<string, any>} */
        const event = Object.assign({ event: name }, payload || {});
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(event);

        if (window.fpResvTracking && typeof window.fpResvTracking.dispatch === 'function') {
            window.fpResvTracking.dispatch(event);
        }

        return event;
    }

    /**
     * @param {HTMLElement} element
     * @param {string} attribute
     * @returns {HTMLElement | null}
     */
    function closestWithAttribute(element, attribute) {
        if (!element) {
            return null;
        }

        if (typeof element.closest === 'function') {
            return element.closest('[' + attribute + ']');
        }

        var parent = element;
        while (parent) {
            if (parent.hasAttribute(attribute)) {
                return parent;
            }
            parent = parent.parentElement;
        }

        return null;
    }

    /**
     * @param {HTMLElement} element
     * @returns {HTMLElement | null}
     */
    function closestSection(element) {
        return closestWithAttribute(element, 'data-fp-resv-section');
    }

    /**
     * @param {HTMLElement} element
     * @param {string} attribute
     * @returns {Record<string, any>}
     */
    function parseJsonAttribute(element, attribute) {
        if (!element) {
            return {};
        }

        var raw = element.getAttribute(attribute);
        if (!raw) {
            return {};
        }

        try {
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        } catch (error) {
            if (window.console && window.console.warn) {
                console.warn('[fp-resv] Impossibile analizzare l\'attributo', attribute, error);
            }
        }

        return {};
    }

    /**
     * @param {HTMLElement} element
     * @param {boolean} disabled
     */
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

    /**
     * @param {string | number | null | undefined} value
     * @returns {number | null}
     */
    function toNumber(value) {
        if (value === null || value === undefined) {
            return null;
        }

        if (typeof value === 'number') {
            return isFinite(value) ? value : null;
        }

        const normalized = String(value).replace(',', '.');
        const parsed = parseFloat(normalized);

        return isNaN(parsed) ? null : parsed;
    }

    function OnePageForm(root) {
        this.root = root;
        this.dataset = parseDataset(root);
        this.events = (this.dataset && this.dataset.events) || {};
        this.form = root.querySelector('[data-fp-resv-form]');
        this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll('[data-fp-resv-section]')) : [];
        this.progress = this.form ? this.form.querySelector('[data-fp-resv-progress]') : null;
        this.submitButton = this.form ? this.form.querySelector('[data-fp-resv-submit]') : null;
        this.submitHint = this.form ? this.form.querySelector('[data-fp-resv-submit-hint]') : null;
        this.mealButtons = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-meal]'));
        this.mealNotice = root.querySelector('[data-fp-resv-meal-notice]');
        this.hiddenMeal = this.form ? this.form.querySelector('input[name="fp_resv_meal"]') : null;
        this.hiddenPrice = this.form ? this.form.querySelector('input[name="fp_resv_price_per_person"]') : null;
        this.summaryTargets = Array.prototype.slice.call(root.querySelectorAll('[data-fp-resv-summary]'));
        this.state = {
            started: false,
            formValidEmitted: false,
            sectionStates: {},
            unlocked: {},
            initialHint: this.submitHint ? this.submitHint.textContent : '',
        };

        this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this);

        if (!this.form || this.sections.length === 0) {
            return;
        }

        this.bind();
        this.initializeSections();
        this.initializeMeals();
        this.syncConsentState();
        this.updateSubmitState();
        this.updateSummary();
    }

    OnePageForm.prototype.bind = function bind() {
        this.form.addEventListener('input', this.handleFormInput.bind(this), true);
        this.form.addEventListener('change', this.handleFormInput.bind(this), true);
        this.form.addEventListener('focusin', this.handleFirstInteraction.bind(this));
        this.form.addEventListener('submit', this.handleSubmit.bind(this));
        this.root.addEventListener('click', this.handleDelegatedTrackingEvent);

        var confirmedHandler = this.handleReservationConfirmed.bind(this);
        document.addEventListener('fp-resv:reservation:confirmed', confirmedHandler);
        window.addEventListener('fp-resv:reservation:confirmed', confirmedHandler);
    };

    OnePageForm.prototype.initializeSections = function initializeSections() {
        var _this = this;
        this.sections.forEach(function (section, index) {
            var key = section.getAttribute('data-step') || String(index);
            _this.state.sectionStates[key] = index === 0 ? 'active' : 'locked';
            if (index === 0) {
                _this.dispatchSectionUnlocked(key);
            }
            _this.updateSectionAttributes(section, _this.state.sectionStates[key]);
        });

        this.updateProgressIndicators();
    };

    OnePageForm.prototype.initializeMeals = function initializeMeals() {
        var _this = this;
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
    };

    OnePageForm.prototype.handleFormInput = function handleFormInput(event) {
        var target = event.target;
        if (!target) {
            return;
        }

        this.handleFirstInteraction();
        this.updateSummary();

        var section = closestSection(target);
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

        if (this.isConsentField(target)) {
            this.syncConsentState();
        }

        this.updateSubmitState();
    };

    OnePageForm.prototype.handleFirstInteraction = function handleFirstInteraction() {
        if (this.state.started) {
            return;
        }

        var eventName = this.events.start || 'reservation_start';
        pushDataLayerEvent(eventName, { source: 'form' });
        this.state.started = true;
    };

    OnePageForm.prototype.handleMealSelection = function handleMealSelection(button) {
        this.mealButtons.forEach(function (item) {
            item.removeAttribute('data-active');
            item.setAttribute('aria-pressed', 'false');
        });

        button.setAttribute('data-active', 'true');
        button.setAttribute('aria-pressed', 'true');
        this.applyMealSelection(button);

        var mealEvent = this.events.meal_selected || 'meal_selected';
        pushDataLayerEvent(mealEvent, {
            meal_type: button.getAttribute('data-fp-resv-meal') || '',
            meal_label: button.getAttribute('data-meal-label') || '',
        });
    };

    OnePageForm.prototype.applyMealSelection = function applyMealSelection(button) {
        var key = button.getAttribute('data-fp-resv-meal') || '';
        if (this.hiddenMeal) {
            this.hiddenMeal.value = key;
        }

        var price = toNumber(button.getAttribute('data-meal-price'));
        if (this.hiddenPrice) {
            this.hiddenPrice.value = price !== null ? String(price) : '';
        }

        var notice = button.getAttribute('data-meal-notice');
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
    };

    OnePageForm.prototype.ensureSectionActive = function ensureSectionActive(section) {
        var key = section.getAttribute('data-step') || '';
        if (this.state.sectionStates[key] === 'locked') {
            this.state.sectionStates[key] = 'active';
            this.updateSectionAttributes(section, 'active');
            this.dispatchSectionUnlocked(key);
            this.scrollIntoView(section);
        }
    };

    OnePageForm.prototype.completeSection = function completeSection(section, advance) {
        var key = section.getAttribute('data-step') || '';
        if (this.state.sectionStates[key] === 'completed') {
            return;
        }

        this.state.sectionStates[key] = 'completed';
        this.updateSectionAttributes(section, 'completed');
        this.updateProgressIndicators();

        if (!advance) {
            return;
        }

        var currentIndex = this.sections.indexOf(section);
        if (currentIndex === -1) {
            return;
        }

        var nextSection = this.sections[currentIndex + 1];
        if (!nextSection) {
            return;
        }

        var nextKey = nextSection.getAttribute('data-step') || String(currentIndex + 1);
        if (this.state.sectionStates[nextKey] !== 'completed') {
            this.state.sectionStates[nextKey] = 'active';
            this.updateSectionAttributes(nextSection, 'active');
            this.dispatchSectionUnlocked(nextKey);
            this.scrollIntoView(nextSection);
        }
    };

    OnePageForm.prototype.updateSectionAttributes = function updateSectionAttributes(section, state) {
        var key = section.getAttribute('data-step') || '';
        this.state.sectionStates[key] = state;
        section.setAttribute('data-state', state);
        section.setAttribute('aria-hidden', state === 'locked' ? 'true' : 'false');
        section.setAttribute('aria-expanded', state === 'active' ? 'true' : 'false');

        this.updateProgressIndicators();
    };

    OnePageForm.prototype.updateProgressIndicators = function updateProgressIndicators() {
        if (!this.progress) {
            return;
        }

        var _this = this;
        var items = this.progress.querySelectorAll('[data-step]');
        Array.prototype.forEach.call(items, function (item) {
            var key = item.getAttribute('data-step') || '';
            var state = _this.state.sectionStates[key] || 'locked';
            item.setAttribute('data-state', state);
            if (state === 'active') {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
            if (state === 'completed') {
                item.setAttribute('data-completed', 'true');
            } else {
                item.removeAttribute('data-completed');
            }
        });
    };

    OnePageForm.prototype.isSectionValid = function isSectionValid(section) {
        var fields = section.querySelectorAll('[data-fp-resv-field]');
        if (fields.length === 0) {
            return true;
        }

        var valid = true;
        Array.prototype.forEach.call(fields, function (field) {
            if (typeof field.checkValidity === 'function') {
                if (!field.checkValidity()) {
                    valid = false;
                }
            }
        });

        return valid;
    };

    OnePageForm.prototype.updateSubmitState = function updateSubmitState() {
        if (!this.submitButton) {
            return;
        }

        var isValid = this.form.checkValidity();
        setAriaDisabled(this.submitButton, !isValid);

        if (!isValid) {
            var tooltip = this.submitButton.getAttribute('data-disabled-tooltip');
            if (this.submitHint) {
                this.submitHint.textContent = tooltip || this.state.initialHint || '';
            }
            return;
        }

        if (this.submitHint) {
            this.submitHint.textContent = this.state.initialHint || '';
        }

        if (!this.state.formValidEmitted) {
            var eventName = this.events.form_valid || 'form_valid';
            pushDataLayerEvent(eventName, { timestamp: Date.now() });
            this.state.formValidEmitted = true;
        }
    };

    OnePageForm.prototype.updateSummary = function updateSummary() {
        if (this.summaryTargets.length === 0) {
            return;
        }

        var date = this.form.querySelector('[data-fp-resv-field="date"]');
        var time = this.form.querySelector('[data-fp-resv-field="time"]');
        var party = this.form.querySelector('[data-fp-resv-field="party"]');
        var firstName = this.form.querySelector('[data-fp-resv-field="first_name"]');
        var lastName = this.form.querySelector('[data-fp-resv-field="last_name"]');
        var email = this.form.querySelector('[data-fp-resv-field="email"]');
        var phone = this.form.querySelector('[data-fp-resv-field="phone"]');
        var notes = this.form.querySelector('[data-fp-resv-field="notes"]');

        var nameValue = '';
        if (firstName && firstName.value) {
            nameValue = firstName.value.trim();
        }
        if (lastName && lastName.value) {
            nameValue = (nameValue + ' ' + lastName.value.trim()).trim();
        }

        var contactValue = '';
        if (email && email.value) {
            contactValue = email.value.trim();
        }
        if (phone && phone.value) {
            contactValue = contactValue !== '' ? contactValue + ' / ' + phone.value.trim() : phone.value.trim();
        }

        this.summaryTargets.forEach(function (target) {
            var key = target.getAttribute('data-fp-resv-summary');
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
    };

    OnePageForm.prototype.handleSubmit = function handleSubmit(event) {
        if (!this.form.checkValidity()) {
            event.preventDefault();
            this.form.reportValidity();
            this.updateSubmitState();
            return false;
        }

        this.handleFirstInteraction();
        var eventName = this.events.submit || 'reservation_submit';
        pushDataLayerEvent(eventName, {
            trigger: 'click',
            party_size: (function () {
                var partyField = /** @type {HTMLInputElement | null} */ (this.form.querySelector('[data-fp-resv-field="party"]'));
                if (!partyField) {
                    return null;
                }
                var value = parseInt(partyField.value, 10);
                return isNaN(value) ? null : value;
            }).call(this),
            meal_type: this.hiddenMeal ? this.hiddenMeal.value : '',
        });

        return true;
    };

    OnePageForm.prototype.handleDelegatedTrackingEvent = function handleDelegatedTrackingEvent(event) {
        var target = /** @type {HTMLElement | null} */ (event.target instanceof HTMLElement ? event.target : null);
        if (!target) {
            return;
        }

        var element = closestWithAttribute(target, 'data-fp-resv-event');
        if (!element) {
            return;
        }

        var name = element.getAttribute('data-fp-resv-event');
        if (!name) {
            return;
        }

        var payload = parseJsonAttribute(element, 'data-fp-resv-payload');
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
            var label = element.getAttribute('data-fp-resv-label') || element.getAttribute('aria-label') || element.textContent || '';
            if (label) {
                payload.label = label.trim();
            }
        }

        pushDataLayerEvent(name, payload);
    };

    OnePageForm.prototype.handleReservationConfirmed = function handleReservationConfirmed(event) {
        if (!event || !event.detail) {
            return;
        }

        var detail = event.detail || {};
        var eventName = this.events.confirmed || 'reservation_confirmed';
        pushDataLayerEvent(eventName, detail);

        if (detail && detail.purchase && detail.purchase.value && detail.purchase.value_is_estimated) {
            pushDataLayerEvent(this.events.purchase || 'purchase', detail.purchase);
        }
    };

    OnePageForm.prototype.dispatchSectionUnlocked = function dispatchSectionUnlocked(key) {
        if (this.state.unlocked[key]) {
            return;
        }

        this.state.unlocked[key] = true;
        var eventName = this.events.section_unlocked || 'section_unlocked';
        pushDataLayerEvent(eventName, { section: key });
    };

    OnePageForm.prototype.scrollIntoView = function scrollIntoView(section) {
        if (typeof section.scrollIntoView !== 'function') {
            return;
        }

        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    OnePageForm.prototype.isConsentField = function isConsentField(element) {
        if (!element || !element.getAttribute) {
            return false;
        }

        var key = element.getAttribute('data-fp-resv-field') || '';
        return key === 'consent' || key === 'marketing_consent' || key === 'profiling_consent';
    };

    OnePageForm.prototype.syncConsentState = function syncConsentState() {
        var tracking = window.fpResvTracking;
        if (!tracking || typeof tracking.updateConsent !== 'function') {
            return;
        }

        var updates = {};
        var changed = false;

        var policy = /** @type {HTMLInputElement | null} */ (this.form.querySelector('[data-fp-resv-field="consent"]'));
        if (policy) {
            updates.analytics = policy.checked ? 'granted' : 'denied';
            updates.clarity = policy.checked ? 'granted' : 'denied';
            changed = true;
        }

        var marketing = /** @type {HTMLInputElement | null} */ (this.form.querySelector('[data-fp-resv-field="marketing_consent"]'));
        if (marketing) {
            updates.ads = marketing.checked ? 'granted' : 'denied';
            changed = true;
        }

        var profiling = /** @type {HTMLInputElement | null} */ (this.form.querySelector('[data-fp-resv-field="profiling_consent"]'));
        if (profiling) {
            updates.personalization = profiling.checked ? 'granted' : 'denied';
            changed = true;
        }

        if (!changed) {
            return;
        }

        tracking.updateConsent(updates);
    };

    document.addEventListener('DOMContentLoaded', function () {
        var widgets = document.querySelectorAll('[data-fp-resv]');
        Array.prototype.forEach.call(widgets, function (widget) {
            new OnePageForm(widget);
        });
    });

    document.addEventListener('fp-resv:tracking:push', function (event) {
        if (!event || !event.detail) {
            return;
        }

        var detail = event.detail;
        var name = detail && (detail.event || detail.name);
        if (!name) {
            return;
        }

        var payload = detail.payload || detail.data || {};
        pushDataLayerEvent(name, payload && typeof payload === 'object' ? payload : {});
    });
})();
