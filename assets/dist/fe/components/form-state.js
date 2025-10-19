/**
 * Form State Management - Rebuilt from scratch
 * Gestione dello stato del form in modo semplice e chiaro
 */

export class FormState {
    constructor() {
        this.state = {
            started: false,
            sending: false,
            ctaEnabled: false,
            sectionStates: {},
            touchedFields: {},
            hintOverride: '',
        };
    }

    getState() {
        return this.state;
    }

    setStarted(value) {
        this.state.started = Boolean(value);
    }

    isStarted() {
        return this.state.started;
    }

    setSending(value) {
        this.state.sending = Boolean(value);
    }

    isSending() {
        return this.state.sending;
    }

    setCtaEnabled(value) {
        this.state.ctaEnabled = Boolean(value);
    }

    isCtaEnabled() {
        return this.state.ctaEnabled;
    }

    updateSectionState(key, state) {
        this.state.sectionStates[key] = state;
    }

    getSectionState(key) {
        return this.state.sectionStates[key] || 'locked';
    }

    markFieldAsTouched(key) {
        this.state.touchedFields[key] = true;
    }

    isFieldTouched(key) {
        return Boolean(this.state.touchedFields[key]);
    }

    setHintOverride(text) {
        this.state.hintOverride = String(text || '');
    }

    getHintOverride() {
        return this.state.hintOverride;
    }
}
