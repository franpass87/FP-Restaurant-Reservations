/**
 * Costanti per gli stati di disponibilità degli orari
 */

export const AVAILABILITY_STATES = {
    AVAILABLE: 'available',
    LIMITED: 'limited',
    FULL: 'full',
    UNAVAILABLE: 'unavailable',
    LOADING: 'loading',
    ERROR: 'error',
    UNKNOWN: 'unknown',
};

/**
 * Mappatura tra stati e descrizioni
 */
export const AVAILABILITY_LABELS = {
    [AVAILABILITY_STATES.AVAILABLE]: 'Disponibile',
    [AVAILABILITY_STATES.LIMITED]: 'Posti limitati',
    [AVAILABILITY_STATES.FULL]: 'Tutto prenotato',
    [AVAILABILITY_STATES.UNAVAILABLE]: 'Non disponibile',
    [AVAILABILITY_STATES.LOADING]: 'Caricamento...',
    [AVAILABILITY_STATES.ERROR]: 'Errore',
};

/**
 * Stati validi per la disponibilità
 */
export const VALID_AVAILABILITY_STATES = [
    AVAILABILITY_STATES.AVAILABLE,
    AVAILABILITY_STATES.LIMITED,
    AVAILABILITY_STATES.FULL,
    AVAILABILITY_STATES.UNAVAILABLE,
];

/**
 * Normalizza uno stato di disponibilità
 * @param {string} status - Lo stato da normalizzare
 * @returns {string} Lo stato normalizzato
 */
export function normalizeAvailabilityState(status) {
    if (typeof status !== 'string') {
        return '';
    }

    const normalized = status.trim().toLowerCase();
    if (normalized === '') {
        return '';
    }

    const stripDiacritics = (value) => {
        if (typeof value.normalize === 'function') {
            return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return value;
    };

    const ascii = stripDiacritics(normalized);
    const matchesStart = (candidates) => candidates.some((candidate) => ascii.startsWith(candidate));
    const matchesAnywhere = (candidates) => candidates.some((candidate) => ascii.includes(candidate));

    if (matchesStart(['available', 'open', 'disponibil', 'disponible', 'liber', 'libre', 'apert', 'abiert'])) {
        return AVAILABILITY_STATES.AVAILABLE;
    }

    if (normalized === 'waitlist' || normalized === 'busy') {
        return AVAILABILITY_STATES.LIMITED;
    }

    if (
        matchesStart(['limited', 'limit', 'limitat', 'limite', 'cupos limit', 'attesa'])
        || matchesAnywhere(['pochi posti', 'quasi pien', 'lista attesa', 'few spots', 'casi llen'])
    ) {
        return AVAILABILITY_STATES.LIMITED;
    }

    if (matchesStart(['full', 'complet', 'esaurit', 'soldout', 'sold out', 'agotad', 'chius', 'plen'])) {
        return AVAILABILITY_STATES.FULL;
    }

    return normalized;
}
