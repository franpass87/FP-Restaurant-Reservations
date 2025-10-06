/**
 * Gestione dello stato del form
 */

export class FormState {
    constructor() {
        this.state = {
            started: false,
            formValidEmitted: false,
            sectionStates: {},
            unlocked: {},
            initialHint: '',
            hintOverride: '',
            ctaEnabled: false,
            sending: false,
            pendingAvailability: false,
            pendingAvailabilityOptions: null,
            lastAvailabilityParams: null,
            mealAvailability: {},
            touchedFields: {},
        };
    }

    getState() {
        return this.state;
    }

    setState(newState) {
        this.state = { ...this.state, ...newState };
    }

    updateSectionState(sectionKey, state) {
        this.state.sectionStates[sectionKey] = state;
    }

    getSectionState(sectionKey) {
        return this.state.sectionStates[sectionKey] || 'locked';
    }

    markFieldAsTouched(fieldKey) {
        this.state.touchedFields[fieldKey] = true;
    }

    isFieldTouched(fieldKey) {
        return Boolean(this.state.touchedFields[fieldKey]);
    }

    setHintOverride(hint) {
        this.state.hintOverride = hint;
    }

    getHintOverride() {
        return this.state.hintOverride;
    }

    setSending(sending) {
        this.state.sending = sending;
    }

    isSending() {
        return this.state.sending;
    }

    setCtaEnabled(enabled) {
        this.state.ctaEnabled = enabled;
    }

    isCtaEnabled() {
        return this.state.ctaEnabled;
    }

    setStarted(started) {
        this.state.started = started;
    }

    isStarted() {
        return this.state.started;
    }

    setFormValidEmitted(emitted) {
        this.state.formValidEmitted = emitted;
    }

    isFormValidEmitted() {
        return this.state.formValidEmitted;
    }
}
