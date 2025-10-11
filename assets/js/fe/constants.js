/**
 * Costanti condivise del modulo form
 */

export const STEP_ORDER = ['service', 'date', 'party', 'slots', 'details', 'confirm'];

export const idleCallback = typeof window !== 'undefined' && typeof window.requestIdleCallback === 'function'
    ? (callback) => window.requestIdleCallback(callback)
    : (callback) => window.setTimeout(() => callback(Date.now()), 1);

let availabilityModulePromise = null;

export function loadAvailabilityModule() {
    if (!availabilityModulePromise) {
        availabilityModulePromise = import('./availability.js');
    }

    return availabilityModulePromise;
}
