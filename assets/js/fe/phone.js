/**
 * Utility helpers for phone normalization and masking.
 */

const DIGIT_REGEX = /\D+/g;

/**
 * @param {string | null | undefined} value
 * @returns {string}
 */
export function toDigits(value) {
    if (!value) {
        return '';
    }

    return String(value).replace(DIGIT_REGEX, '');
}

/**
 * @param {string | null | undefined} cc
 * @returns {string}
 */
export function normalizeCountryCode(cc) {
    const digits = toDigits(cc);

    return digits === '' ? '' : digits.replace(/^0+/, '');
}

/**
 * @param {string | null | undefined} local
 * @returns {string}
 */
export function normalizeLocal(local) {
    return toDigits(local);
}

/**
 * @param {string | null | undefined} cc
 * @param {string | null | undefined} local
 * @returns {string}
 */
export function composeE164(cc, local) {
    const country = normalizeCountryCode(cc);
    const localDigits = normalizeLocal(local);

    if (country === '' || localDigits === '') {
        return '';
    }

    return '+' + country + localDigits;
}

/**
 * Validate the local portion length (without country code).
 *
 * @param {string | null | undefined} local
 * @returns {boolean}
 */
export function isValidLocal(local) {
    const digits = normalizeLocal(local);

    return digits.length >= 6 && digits.length <= 15;
}

/**
 * @param {string | null | undefined} value
 * @returns {{ masked: string, digits: string }}
 */
export function maskLocal(value) {
    const digits = normalizeLocal(value);
    if (digits === '') {
        return { masked: '', digits: '' };
    }

    const pattern = [3, 4];
    const groups = [];
    let index = 0;
    let patternIndex = 0;

    while (index < digits.length) {
        const remaining = digits.length - index;
        let size = pattern[patternIndex % pattern.length];
        if (remaining <= 4) {
            size = remaining;
        }

        groups.push(digits.slice(index, index + size));
        index += size;
        patternIndex += 1;
    }

    return { masked: groups.join(' '), digits };
}

/**
 * @param {HTMLInputElement} input
 * @param {string} countryCode
 */
export function applyMask(input, countryCode) {
    const original = input.value;
    const { masked } = maskLocal(original);
    const selection = input.selectionStart;
    input.value = masked;

    if (selection !== null) {
        const offset = masked.length - original.length;
        const newPos = Math.max(0, selection + offset);
        input.setSelectionRange(newPos, newPos);
    }

    input.setAttribute('data-phone-local', normalizeLocal(input.value));
    input.setAttribute('data-phone-cc', normalizeCountryCode(countryCode));
}

/**
 * @param {HTMLInputElement} input
 * @param {string} countryCode
 * @returns {{ e164: string, local: string, country: string }}
 */
export function buildPayload(input, countryCode) {
    const local = normalizeLocal(input.value);
    const country = normalizeCountryCode(countryCode);

    return {
        e164: composeE164(country, local),
        local,
        country,
    };
}
