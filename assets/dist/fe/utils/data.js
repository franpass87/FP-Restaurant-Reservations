export function parseDataset(root) {
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

export function parseJsonAttribute(element, attribute) {
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

export function toNumber(value) {
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


