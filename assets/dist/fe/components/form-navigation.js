/**
 * Gestione della navigazione del form
 */

import { closestSection, firstFocusable } from '../utils/dom-helpers.js';

export class FormNavigation {
    constructor(sections, stepOrder, state, updateSectionAttributes, updateProgressIndicators, updateSubmitState, widgetRoot, form = null, copy = {}) {
        this.sections = sections;
        this.stepOrder = stepOrder;
        this.state = state;
        this.updateSectionAttributes = updateSectionAttributes;
        this.updateProgressIndicators = updateProgressIndicators;
        this.updateSubmitState = updateSubmitState;
        this.widgetRoot = widgetRoot;
        this.form = form;
        this.copy = copy;
    }

    getStepOrderIndex(target) {
        const key = target && target.getAttribute ? target.getAttribute('data-step') || '' : String(target || '');
        const normalized = typeof key === 'string' ? key : '';
        const index = this.stepOrder.indexOf(normalized);
        return index === -1 ? this.stepOrder.length + 1 : index;
    }

    ensureSectionActive(section) {
        const key = section.getAttribute('data-step') || '';
        if (this.state.sectionStates[key] === 'locked') {
            this.state.sectionStates[key] = 'active';
            this.updateSectionAttributes(section, 'active');
            this.dispatchSectionUnlocked(key);
            // Non fare scroll automatico quando si attiva una sezione tramite interazione con i campi
            // this.scrollIntoView(section);
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

        // Scroll all'inizio del widget quando si torna indietro
        this.activateSectionByKey(previousKey);
    }

    navigateToNext(section, formValidation) {
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
        
        if (!formValidation.isSectionValid(section)) {
            const invalid = formValidation.findFirstInvalid(section);
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

    dispatchSectionUnlocked(key) {
        if (this.state.unlocked[key]) {
            return;
        }

        this.state.unlocked[key] = true;
        const eventName = this.events.section_unlocked || 'section_unlocked';
        // Qui dovresti chiamare pushDataLayerEvent se disponibile
        // pushDataLayerEvent(eventName, { section: key });
    }

    scrollIntoView(section) {
        // Scroll all'inizio del widget quando si cambia step
        const target = this.widgetRoot || section;
        if (!target || typeof target.scrollIntoView !== 'function') {
            return;
        }

        requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
}
