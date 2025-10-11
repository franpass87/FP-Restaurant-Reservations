/**
 * Utility functions per manipolazione DOM
 */

export function closestWithAttribute(element, attribute) {
    if (!element) {
        return null;
    }

    if (typeof element.closest === 'function') {
        return element.closest('[' + attribute + ']');
    }

    let parent = element;
    while (parent) {
        if (parent.hasAttribute(attribute)) {
            return parent;
        }
        parent = parent.parentElement;
    }

    return null;
}

export function closestSection(element) {
    return closestWithAttribute(element, 'data-fp-resv-section');
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

export function setAriaDisabled(element, disabled) {
    if (!element) {
        return;
    }

    if (disabled) {
        element.setAttribute('aria-disabled', 'true');
        element.setAttribute('disabled', 'disabled');
    } else {
        element.removeAttribute('disabled');
        element.setAttribute('aria-disabled', 'false');
    }
}

export function firstFocusable(section) {
    if (!section) {
        return null;
    }

    const selectors = 'input:not([type="hidden"]), select, textarea, button, [tabindex="0"]';
    return section.querySelector(selectors);
}
