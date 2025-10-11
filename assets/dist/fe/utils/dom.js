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

export function firstFocusable(section) {
    if (!section) {
        return null;
    }

    const selectors = 'input:not([type="hidden"]), select, textarea, button, [tabindex="0"]';
    return section.querySelector(selectors);
}


