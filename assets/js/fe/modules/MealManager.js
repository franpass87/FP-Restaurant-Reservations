/**
 * Gestisce la selezione e disponibilità dei pasti/servizi
 */
export class MealManager {
    constructor(elements, state, config, copy) {
        this.mealButtons = elements.mealButtons || [];
        this.mealNotice = elements.mealNotice;
        this.mealNoticeText = elements.mealNoticeText;
        this.hiddenMeal = elements.hiddenMeal;
        this.hiddenPrice = elements.hiddenPrice;
        this.availabilityIndicator = elements.availabilityIndicator;
        
        this.state = state;
        this.config = config;
        this.copy = copy;
        
        this.currentAvailableDays = [];
        this.onMealSelected = null;
        this.onFirstInteraction = null;
    }

    initialize(onMealSelected, onFirstInteraction) {
        this.onMealSelected = onMealSelected;
        this.onFirstInteraction = onFirstInteraction;
        
        if (this.mealButtons.length === 0) {
            return;
        }

        this.mealButtons.forEach((button) => {
            if (!button.hasAttribute('data-meal-default-notice')) {
                const initialNotice = button.getAttribute('data-meal-notice') || '';
                if (initialNotice !== '') {
                    button.setAttribute('data-meal-default-notice', initialNotice);
                }
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (this.onFirstInteraction) {
                    this.onFirstInteraction();
                }
                this.handleSelection(button);
            });

            if (button.hasAttribute('data-active') && this.hiddenMeal) {
                this.applySelection(button);
            }
        });
    }

    handleSelection(button) {
        const mealKey = button.getAttribute('data-fp-resv-meal') || '';
        
        this.mealButtons.forEach((btn) => {
            btn.removeAttribute('data-active');
            btn.setAttribute('aria-pressed', 'false');
        });
        
        button.setAttribute('data-active', 'true');
        button.setAttribute('aria-pressed', 'true');
        
        const storedState = this.state.mealAvailability[mealKey] || '';
        
        this.applyAvailabilityIndicator(mealKey, storedState);
        
        pushDataLayerEvent('fp_resv:meal:selected', {
            mealKey,
            mealLabel: button.textContent.trim(),
        });
        
        this.applySelection(button);
        this.applyAvailabilityNotice(mealKey, storedState, { skipSlotReset: true });
        
        if (this.onMealSelected) {
            this.onMealSelected(mealKey);
        }
        
        this.updateAvailableDays(mealKey);
    }

    updateAvailableDays(mealKey) {
        if (!mealKey || !this.config || !this.config.meals) {
            this.currentAvailableDays = [];
            return;
        }

        const meal = this.config.meals.find((m) => m.key === mealKey);
        if (!meal || !meal.available_days || !Array.isArray(meal.available_days)) {
            this.currentAvailableDays = [];
            return;
        }

        this.currentAvailableDays = meal.available_days
            .map((day) => {
                const normalized = String(day).toLowerCase();
                const dayMap = {
                    mon: 1, monday: 1,
                    tue: 2, tuesday: 2,
                    wed: 3, wednesday: 3,
                    thu: 4, thursday: 4,
                    fri: 5, friday: 5,
                    sat: 6, saturday: 6,
                    sun: 0, sunday: 0,
                };
                return dayMap[normalized] !== undefined ? dayMap[normalized] : null;
            })
            .filter((day) => day !== null);
    }

    updateNoticeFromButton(button, overrideText) {
        if (!this.mealNoticeText) {
            return;
        }

        let notice = '';
        if (overrideText !== undefined) {
            notice = overrideText;
        } else {
            notice = button.getAttribute('data-meal-notice') || button.getAttribute('data-meal-default-notice') || '';
        }

        if (this.mealNoticeText.textContent !== notice) {
            this.mealNoticeText.textContent = notice;
        }
    }

    applyAvailabilityNotice(mealKey, availState, options = {}) {
        const normalized = String(availState || '').toLowerCase();
        const button = this.mealButtons.find((btn) => btn.getAttribute('data-fp-resv-meal') === mealKey);
        
        if (!button) {
            return;
        }

        const skipSlotReset = options && options.skipSlotReset;

        if (normalized === 'full' || normalized === 'booked' || normalized === 'closed' || normalized === 'unavailable') {
            const fullNotice = this.copy.mealFullNotice || 'Nessuna disponibilità per questo servizio. Scegli un altro giorno.';
            this.updateNoticeFromButton(button, fullNotice);

            if (!skipSlotReset && options.clearSlotSelection) {
                options.clearSlotSelection({ skipAvailabilityUpdate: true });
            }

            if (options.focusDateField && options.dateField) {
                setTimeout(() => {
                    if (typeof options.dateField.focus === 'function') {
                        options.dateField.focus();
                    }
                    if (typeof options.dateField.showPicker === 'function') {
                        try {
                            options.dateField.showPicker();
                        } catch (err) {
                            // Ignora errori per browser che non supportano showPicker
                        }
                    }
                }, 100);
            }
        } else if (normalized === 'available') {
            this.updateNoticeFromButton(button);
        } else {
            this.updateNoticeFromButton(button);
        }
    }

    applySelection(button) {
        const mealKey = button.getAttribute('data-fp-resv-meal') || '';
        const mealPrice = button.getAttribute('data-meal-price') || '';

        if (this.hiddenMeal) {
            this.hiddenMeal.value = mealKey;
        }

        if (this.hiddenPrice) {
            this.hiddenPrice.value = mealPrice;
        }

        this.updateNoticeFromButton(button);
    }

    applyAvailabilityIndicator(meal, availState) {
        if (!this.availabilityIndicator) {
            return;
        }

        const normalized = String(availState || '').toLowerCase();
        const states = ['available', 'limited', 'full'];

        states.forEach((state) => {
            if (normalized === state) {
                this.availabilityIndicator.setAttribute('data-state', state);
            }
        });

        if (!states.includes(normalized)) {
            this.availabilityIndicator.removeAttribute('data-state');
        }
    }

    handleAvailabilitySummary(summary, params) {
        if (!summary || typeof summary !== 'object') {
            return;
        }

        const mealKey = params && params.meal ? String(params.meal) : '';

        if (mealKey === '') {
            return;
        }

        let normalized = '';
        if (summary.all_booked === true) {
            normalized = 'full';
        } else if (summary.has_availability === true) {
            if (summary.limited === true) {
                normalized = 'limited';
            } else {
                normalized = 'available';
            }
        }

        this.state.mealAvailability[mealKey] = normalized;

        this.applyAvailabilityIndicator(mealKey, normalized);
        this.applyAvailabilityNotice(mealKey, normalized);
    }

    getCurrentAvailableDays() {
        return this.currentAvailableDays;
    }
}

// Import necessario per pushDataLayerEvent
import { pushDataLayerEvent } from '../tracking/dataLayer.js';
