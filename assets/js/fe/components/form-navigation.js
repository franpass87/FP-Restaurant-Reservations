/**
 * Form Navigation - Rebuilt from scratch
 * Gestione della navigazione tra gli step del form
 */

export class FormNavigation {
    constructor(sections, stepOrder, state, updateSectionFn, updateProgressFn, updateSubmitFn, widgetRoot, form, copy) {
        this.sections = sections;
        this.stepOrder = stepOrder;
        this.state = state;
        this.updateSectionFn = updateSectionFn;
        this.updateProgressFn = updateProgressFn;
        this.updateSubmitFn = updateSubmitFn;
        this.widgetRoot = widgetRoot;
        this.form = form;
        this.copy = copy || {};
    }

    /**
     * Ottieni l'indice di uno step
     */
    getStepOrderIndex(target) {
        const key = target && target.getAttribute 
            ? target.getAttribute('data-step') || '' 
            : String(target || '');
        const index = this.stepOrder.indexOf(key);
        return index === -1 ? this.stepOrder.length : index;
    }

    /**
     * Attiva una sezione se è bloccata
     */
    ensureSectionActive(section) {
        const key = section.getAttribute('data-step') || '';
        if (this.state.sectionStates[key] === 'locked') {
            this.state.sectionStates[key] = 'active';
            this.updateSectionFn(section, 'active');
        }
    }

    /**
     * Completa una sezione e avanza alla successiva
     */
    completeSection(section, advance) {
        const key = section.getAttribute('data-step') || '';
        
        if (this.state.sectionStates[key] === 'completed') {
            return;
        }

        this.state.sectionStates[key] = 'completed';
        this.updateSectionFn(section, 'completed');
        this.updateProgressFn();

        if (!advance) return;

        const currentIndex = this.sections.indexOf(section);
        if (currentIndex === -1) return;

        const nextSection = this.sections[currentIndex + 1];
        if (!nextSection) return;

        const nextKey = nextSection.getAttribute('data-step') || '';
        if (this.state.sectionStates[nextKey] !== 'completed') {
            this.state.sectionStates[nextKey] = 'active';
            this.updateSectionFn(nextSection, 'active');
            this.scrollToSection(nextSection);
        }
    }

    /**
     * Naviga allo step precedente
     */
    navigateToPrevious(section) {
        const index = this.sections.indexOf(section);
        if (index <= 0) return;

        const prevSection = this.sections[index - 1];
        if (!prevSection) return;

        const prevKey = prevSection.getAttribute('data-step') || '';
        this.activateSectionByKey(prevKey);
    }

    /**
     * Naviga allo step successivo (con validazione)
     */
    navigateToNext(section, validation) {
        const stepKey = section.getAttribute('data-step') || '';

        // Validazione speciale per lo step date
        if (stepKey === 'date') {
            const dateField = this.form?.querySelector('[data-fp-resv-field="date"]');
            if (!dateField || !dateField.value.trim()) {
                this.showStepError(section, this.copy.dateRequired || 'Seleziona una data');
                return;
            }
        }

        // Validazione speciale per lo step slots
        if (stepKey === 'slots') {
            const timeField = this.form?.querySelector('[data-fp-resv-field="time"]');
            if (!timeField || !timeField.value.trim()) {
                this.showStepError(section, this.copy.slotRequired || 'Seleziona un orario');
                return;
            }
        }

        // Validazione generale
        if (validation && !validation.isSectionValid(section)) {
            const invalid = validation.findFirstInvalid(section);
            if (invalid) {
                if (invalid.reportValidity) invalid.reportValidity();
                if (invalid.focus) invalid.focus();
            }
            return;
        }

        this.completeSection(section, true);
    }

    /**
     * Attiva una sezione specifica per chiave
     */
    activateSectionByKey(key) {
        const targetSection = this.sections.find((s) => {
            return (s.getAttribute('data-step') || '') === key;
        });

        if (!targetSection) return;

        let reachedTarget = false;

        this.sections.forEach((section) => {
            const sectionKey = section.getAttribute('data-step') || '';
            
            if (sectionKey === key) {
                reachedTarget = true;
                this.updateSectionFn(section, 'active', { silent: true });
            } else if (!reachedTarget) {
                const previousState = this.state.sectionStates[sectionKey];
                const state = previousState === 'locked' ? 'locked' : 'completed';
                this.updateSectionFn(section, state, { silent: true });
            } else {
                this.updateSectionFn(section, 'locked', { silent: true });
            }
        });

        this.updateProgressFn();
        this.scrollToSection(targetSection);
        this.updateSubmitFn();
    }

    /**
     * Scroll alla sezione
     */
    scrollToSection(section) {
        const target = this.widgetRoot || section;
        if (!target || typeof target.scrollIntoView !== 'function') return;

        requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    /**
     * Mostra un errore temporaneo nello step
     */
    showStepError(section, message) {
        const statusEl = section.querySelector('[data-fp-resv-date-status], [data-fp-resv-slots-status]');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.color = '#dc2626';
        statusEl.hidden = false;

        setTimeout(() => {
            statusEl.textContent = '';
            statusEl.style.color = '';
            statusEl.hidden = true;
        }, 3000);
    }
}
