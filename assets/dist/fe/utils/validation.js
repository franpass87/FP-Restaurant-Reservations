/**
 * Utility functions per validazione
 */

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

export function safeJson(response) {
    return response.text().then((text) => {
        if (!text) {
            return {};
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            return {};
        }
    });
}

export function resolveEndpoint(endpoint, fallback) {
    if (endpoint && typeof endpoint === 'string') {
        try {
            return new URL(endpoint, window.location.origin).toString();
        } catch (error) {
            return endpoint;
        }
    }

    if (window.wpApiSettings && window.wpApiSettings.root) {
        const root = window.wpApiSettings.root.replace(/\/$/, '');
        return root + fallback;
    }

    return fallback;
}
