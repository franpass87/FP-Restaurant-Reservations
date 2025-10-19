/**
 * Componente per la gestione della legenda di disponibilità
 */

import { AVAILABILITY_STATES, AVAILABILITY_LABELS } from '../constants/availability-states.js';

/**
 * Crea e gestisce la legenda di disponibilità
 * @param {HTMLElement} legendElement - L'elemento DOM della legenda
 * @returns {Object} API per gestire la legenda
 */
export function createAvailabilityLegend(legendElement) {
    if (!legendElement) {
        return {
            show: () => {},
            hide: () => {},
            isVisible: () => false,
        };
    }

    /**
     * Mostra la legenda
     */
    function show() {
        legendElement.hidden = false;
        legendElement.removeAttribute('hidden');
    }

    /**
     * Nasconde la legenda
     */
    function hide() {
        legendElement.hidden = true;
        legendElement.setAttribute('hidden', '');
    }

    /**
     * Verifica se la legenda è visibile
     * @returns {boolean}
     */
    function isVisible() {
        return !legendElement.hidden;
    }

    /**
     * Aggiorna lo stato visivo degli item della legenda
     * @param {string} activeState - Lo stato attivo da evidenziare
     */
    function updateLegendState(activeState) {
        const items = legendElement.querySelectorAll('[class*="legend-item"]');
        items.forEach((item) => {
            const itemClasses = item.className;
            let itemState = '';
            
            if (itemClasses.includes('available')) {
                itemState = AVAILABILITY_STATES.AVAILABLE;
            } else if (itemClasses.includes('limited')) {
                itemState = AVAILABILITY_STATES.LIMITED;
            } else if (itemClasses.includes('full')) {
                itemState = AVAILABILITY_STATES.FULL;
            }

            // Evidenzia l'item corrispondente allo stato attivo
            if (itemState === activeState) {
                item.setAttribute('data-active', 'true');
            } else {
                item.removeAttribute('data-active');
            }
        });
    }

    return {
        show,
        hide,
        isVisible,
        updateLegendState,
    };
}
