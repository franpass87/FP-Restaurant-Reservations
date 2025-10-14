/**
 * Gestione della validazione del form
 */

import { buildPayload, isValidLocal } from '../phone.js';

export class FormValidation {
    constructor(form, phoneField, phonePrefixField, phoneCountryCode, copy) {
        this.form = form;
        this.phoneField = phoneField;
        this.phonePrefixField = phonePrefixField;
        this.phoneCountryCode = phoneCountryCode;
        this.copy = copy;
    }

    isSectionValid(section) {
        const fields = section.querySelectorAll('[data-fp-resv-field]');
        if (fields.length === 0) {
            return true;
        }

        const stepKey = section.getAttribute('data-step') || '';
        
        // Se siamo nello step "date" richiediamo esplicitamente che sia selezionata una data
        if (stepKey === 'date') {
            const dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null;
            
            // Verifica che sia stata selezionata una data
            const hasDateSelection = dateField && dateField.value.trim() !== '';
            
            if (!hasDateSelection) {
                return false;
            }
        }
        
        // Se siamo nello step "slots" richiediamo esplicitamente che sia selezionato un orario
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

    findFirstInvalid(section) {
        if (!section) {
            return null;
        }

        return section.querySelector('[data-fp-resv-field]:invalid, [required]:invalid');
    }

    focusFirstInvalid() {
        const invalid = this.form.querySelector('[data-fp-resv-field]:invalid, [required]:invalid');
        if (invalid && typeof invalid.focus === 'function') {
            invalid.focus();
        }
    }

    validatePhoneField() {
        if (!this.phoneField) {
            return;
        }

        const payload = buildPayload(this.phoneField, this.phoneCountryCode);
        if (payload.local === '') {
            this.phoneField.setCustomValidity('');
            this.phoneField.removeAttribute('aria-invalid');
            return;
        }

        if (!isValidLocal(payload.local)) {
            this.phoneField.setCustomValidity(this.copy.invalidPhone);
            this.phoneField.setAttribute('aria-invalid', 'true');
            return false;
        } else {
            this.phoneField.setCustomValidity('');
            this.phoneField.setAttribute('aria-invalid', 'false');
            return true;
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
            return true;
        }

        // Rimuove eventuali errori custom prima del controllo nativo,
        // altrimenti checkValidity() fallisce sempre
        field.setCustomValidity('');

        if (!field.checkValidity()) {
            field.setCustomValidity(this.copy.invalidEmail);
            field.setAttribute('aria-invalid', 'true');
            return false;
        } else {
            field.setCustomValidity('');
            field.setAttribute('aria-invalid', 'false');
            return true;
        }
    }

    updateInlineErrors(touchedFields, strings) {
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
            first_name: strings?.messages?.required_first_name || 'Inserisci il nome',
            last_name: strings?.messages?.required_last_name || 'Inserisci il cognome',
            email: this.copy.invalidEmail,
            phone: this.copy.invalidPhone,
            consent: strings?.messages?.required_consent || 'Accetta la privacy per procedere',
        };

        Object.keys(map).forEach((key) => {
            const field = map[key];
            const errorEl = this.form.querySelector(`[data-fp-resv-error="${key}"]`);
            if (!errorEl) {
                return;
            }

            // Non mostrare errori per i campi consent finché non sono stati toccati
            if (key === 'consent' && !touchedFields[key]) {
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
                const payload = buildPayload(this.phoneField, this.phoneCountryCode);
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
}
