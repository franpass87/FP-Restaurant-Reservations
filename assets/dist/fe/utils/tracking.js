/**
 * Utility functions per tracking e analytics
 */

export function pushDataLayerEvent(name, payload) {
    if (!name) {
        return null;
    }

    const event = Object.assign({ event: name }, payload || {});
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(event);

    if (window.fpResvTracking && typeof window.fpResvTracking.dispatch === 'function') {
        window.fpResvTracking.dispatch(event);
    }

    return event;
}

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
