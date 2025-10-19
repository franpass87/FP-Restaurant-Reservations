/**
 * Form Validation - Rebuilt from scratch
 * Gestione della validazione in modo semplice e chiaro
 */

import { buildPayload, isValidLocal } from '../phone.js';

export class FormValidation {
    constructor(form, phoneField, phoneCountryCode, copy) {
        this.form = form;
        this.phoneField = phoneField;
        this.phoneCountryCode = phoneCountryCode;
        this.copy = copy || {};
    }

    /**
     * Verifica se una sezione è valida
     */
    isSectionValid(section) {
        if (!section) return false;

        const fields = section.querySelectorAll('[data-fp-resv-field]');
        if (fields.length === 0) return true;

        let valid = true;
        Array.prototype.forEach.call(fields, (field) => {
            if (field.checkValidity && !field.checkValidity()) {
                valid = false;
            }
        });

        return valid;
    }

    /**
     * Trova il primo campo invalido in una sezione
     */
    findFirstInvalid(section) {
        if (!section) return null;
        return section.querySelector('[data-fp-resv-field]:invalid, [required]:invalid');
    }

    /**
     * Focus sul primo campo invalido
     */
    focusFirstInvalid() {
        if (!this.form) return;
        
        const invalid = this.form.querySelector('[data-fp-resv-field]:invalid, [required]:invalid');
        if (invalid && typeof invalid.focus === 'function') {
            invalid.focus();
        }
    }

    /**
     * Valida il campo telefono
     */
    validatePhoneField() {
        if (!this.phoneField) return true;

        const payload = buildPayload(this.phoneField, this.phoneCountryCode);
        
        if (payload.local === '') {
            this.phoneField.setCustomValidity('');
            this.phoneField.removeAttribute('aria-invalid');
            return true;
        }

        if (!isValidLocal(payload.local)) {
            const message = this.copy.invalidPhone || 'Numero di telefono non valido';
            this.phoneField.setCustomValidity(message);
            this.phoneField.setAttribute('aria-invalid', 'true');
            return false;
        }

        this.phoneField.setCustomValidity('');
        this.phoneField.setAttribute('aria-invalid', 'false');
        return true;
    }

    /**
     * Valida il campo email
     */
    validateEmailField(field) {
        if (!field) return true;

        const value = (field.value || '').trim();
        
        if (value === '') {
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            return true;
        }

        field.setCustomValidity('');

        if (!field.checkValidity()) {
            const message = this.copy.invalidEmail || 'Email non valida';
            field.setCustomValidity(message);
            field.setAttribute('aria-invalid', 'true');
            return false;
        }

        field.setAttribute('aria-invalid', 'false');
        return true;
    }

    /**
     * Aggiorna i messaggi di errore inline
     */
    updateInlineErrors(touchedFields, strings) {
        if (!this.form) return;

        const fields = {
            first_name: this.form.querySelector('[data-fp-resv-field="first_name"]'),
            last_name: this.form.querySelector('[data-fp-resv-field="last_name"]'),
            email: this.form.querySelector('[data-fp-resv-field="email"]'),
            phone: this.form.querySelector('[data-fp-resv-field="phone"]'),
            consent: this.form.querySelector('[data-fp-resv-field="consent"]'),
        };

        const messages = strings?.messages || {};

        Object.keys(fields).forEach((key) => {
            const field = fields[key];
            const errorEl = this.form.querySelector(`[data-fp-resv-error="${key}"]`);
            
            if (!errorEl) return;

            // Non mostrare errori per consent finché non è stato toccato
            if (key === 'consent' && !touchedFields[key]) {
                errorEl.hidden = true;
                errorEl.textContent = '';
                return;
            }

            let showError = false;
            let errorText = '';

            if (field && field.checkValidity && !field.checkValidity()) {
                showError = true;
                errorText = messages[`required_${key}`] || field.validationMessage || 'Campo richiesto';
            }

            if (key === 'phone' && this.phoneField) {
                const payload = buildPayload(this.phoneField, this.phoneCountryCode);
                if (payload.local && !isValidLocal(payload.local)) {
                    showError = true;
                    errorText = this.copy.invalidPhone || 'Numero non valido';
                }
            }

            if (showError) {
                errorEl.textContent = errorText;
                errorEl.hidden = false;
                if (field) field.setAttribute('aria-invalid', 'true');
            } else {
                errorEl.textContent = '';
                errorEl.hidden = true;
                if (field) field.removeAttribute('aria-invalid');
            }
        });
    }
}
