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


