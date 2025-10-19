/**
 * Componente per il rendering degli slot orari
 */

import { normalizeAvailabilityState, AVAILABILITY_STATES } from '../constants/availability-states.js';

/**
 * Rimuove tutti i figli da un nodo DOM
 * @param {HTMLElement} node - Il nodo da pulire
 */
function clearChildren(node) {
    while (node.firstChild) {
        node.removeChild(node.firstChild);
    }
}

/**
 * Crea un elemento scheletro per il loading
 * @returns {HTMLElement}
 */
function createSkeletonElement() {
    const item = document.createElement('li');
    const placeholder = document.createElement('span');
    placeholder.className = 'fp-skeleton';
    item.appendChild(placeholder);
    return item;
}

/**
 * Crea un bottone per uno slot orario
 * @param {Object} slot - I dati dello slot
 * @param {string} slot.label - L'etichetta dello slot (es. "12:00")
 * @param {string} slot.start - L'orario di inizio
 * @param {string} slot.status - Lo stato di disponibilità
 * @param {boolean} isSelected - Se lo slot è selezionato
 * @param {Function} onClick - Callback per il click
 * @returns {HTMLElement}
 */
function createSlotButton(slot, isSelected, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = slot.label || '';
    button.dataset.slot = slot.start || '';
    button.dataset.slotStatus = slot.status || '';
    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    
    // Aggiungi classe CSS basata sullo stato
    const normalizedStatus = normalizeAvailabilityState(slot.status);
    if (normalizedStatus) {
        button.classList.add(`fp-slot-button--${normalizedStatus}`);
    }
    
    button.addEventListener('click', onClick);
    return button;
}

/**
 * Riassume gli slot per determinare lo stato generale di disponibilità
 * @param {Array<Object>} slots - Array di slot
 * @param {boolean} hasAvailabilityFlag - Flag di disponibilità
 * @returns {Object} Oggetto con state e slots count
 */
export function summarizeSlots(slots, hasAvailabilityFlag) {
    const safeSlots = Array.isArray(slots) ? slots : [];
    const slotCount = safeSlots.length;
    
    // Se non ci sono slot E hasAvailabilityFlag è false, 
    // significa che la configurazione non prevede orari (non è "full")
    if (slotCount === 0) {
        // Se hasAvailabilityFlag è esplicitamente false, lo schedule è vuoto
        if (hasAvailabilityFlag === false) {
            return { state: AVAILABILITY_STATES.UNAVAILABLE, slots: 0 };
        }
        // Altrimenti è veramente pieno
        return { state: AVAILABILITY_STATES.FULL, slots: 0 };
    }

    const statuses = safeSlots
        .map((slot) => normalizeAvailabilityState(slot && slot.status))
        .filter((status) => status !== '');

    const hasLimited = statuses.some((status) => status === AVAILABILITY_STATES.LIMITED);
    if (hasLimited) {
        return { state: AVAILABILITY_STATES.LIMITED, slots: slotCount };
    }

    const hasAvailable = statuses.some((status) => status === AVAILABILITY_STATES.AVAILABLE);
    if (hasAvailable) {
        return { state: AVAILABILITY_STATES.AVAILABLE, slots: slotCount };
    }

    if (hasAvailabilityFlag) {
        return { state: AVAILABILITY_STATES.AVAILABLE, slots: slotCount };
    }

    if (statuses.length === 0) {
        return { state: AVAILABILITY_STATES.AVAILABLE, slots: slotCount };
    }

    return { state: AVAILABILITY_STATES.FULL, slots: slotCount };
}

/**
 * Crea il renderer per gli slot orari
 * @param {Object} options - Opzioni di configurazione
 * @param {HTMLElement} options.listElement - L'elemento contenitore degli slot
 * @param {number} options.skeletonCount - Numero di skeleton items da mostrare
 * @returns {Object} API per il rendering degli slot
 */
export function createSlotsRenderer(options) {
    const { listElement, skeletonCount = 4 } = options;

    if (!listElement) {
        return {
            renderSlots: () => {},
            showSkeleton: () => {},
            clear: () => {},
        };
    }

    /**
     * Mostra lo skeleton loader
     */
    function showSkeleton() {
        clearChildren(listElement);
        for (let index = 0; index < skeletonCount; index += 1) {
            const skeleton = createSkeletonElement();
            listElement.appendChild(skeleton);
        }
    }

    /**
     * Pulisce il contenitore degli slot
     */
    function clear() {
        clearChildren(listElement);
    }

    /**
     * Renderizza gli slot orari
     * @param {Array<Object>} slots - Array di slot da renderizzare
     * @param {Object|null} currentSelection - Lo slot attualmente selezionato
     * @param {Function} onSlotClick - Callback per il click su uno slot
     */
    function renderSlots(slots, currentSelection, onSlotClick) {
        clearChildren(listElement);

        if (!Array.isArray(slots) || slots.length === 0) {
            return;
        }

        slots.forEach((slot) => {
            const isSelected = currentSelection && currentSelection.start === slot.start;
            const item = document.createElement('li');
            const button = createSlotButton(
                slot,
                isSelected,
                () => onSlotClick(slot, button)
            );
            item.appendChild(button);
            listElement.appendChild(item);
        });
    }

    /**
     * Aggiorna lo stato di selezione dei bottoni
     * @param {Object|null} selectedSlot - Lo slot selezionato
     */
    function updateSelection(selectedSlot) {
        const buttons = listElement.querySelectorAll('button[data-slot]');
        buttons.forEach((button) => {
            const isSelected = selectedSlot && button.dataset.slot === selectedSlot.start;
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
    }

    return {
        renderSlots,
        showSkeleton,
        clear,
        updateSelection,
    };
}
