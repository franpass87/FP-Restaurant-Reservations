/**
 * Utility comuni per le dashboard admin.
 * Estratto per evitare duplicazioni tra diagnostics, reports, tables, ecc.
 */

/**
 * Formatta un numero intero con separatori di migliaia.
 * @param {number|string} value - Il valore da formattare
 * @returns {string} Il numero formattato
 */
export function formatNumber(value) {
    const number = Number(value) || 0;
    try {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(number);
    } catch (error) {
        return String(Math.round(number));
    }
}

/**
 * Formatta un numero decimale (max 2 decimali).
 * @param {number|string} value - Il valore da formattare
 * @returns {string} Il numero formattato
 */
export function formatDecimal(value) {
    const number = Number(value) || 0;
    try {
        return new Intl.NumberFormat(undefined, { 
            minimumFractionDigits: 0, 
            maximumFractionDigits: 2 
        }).format(number);
    } catch (error) {
        return number.toFixed(2);
    }
}

/**
 * Formatta un valore come valuta.
 * @param {number|string} value - Il valore da formattare
 * @param {string} currency - Codice ISO della valuta (default: 'EUR')
 * @returns {string} Il valore formattato come valuta
 */
export function formatCurrency(value, currency = 'EUR') {
    const number = Number(value) || 0;
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(number);
    } catch (error) {
        return currency + ' ' + number.toFixed(2);
    }
}

/**
 * Formatta una data nel formato locale.
 * @param {Date|string} date - La data da formattare
 * @param {object} options - Opzioni Intl.DateTimeFormat
 * @returns {string} La data formattata
 */
export function formatDate(date, options = {}) {
    const dateObj = date instanceof Date ? date : new Date(date);
    if (isNaN(dateObj.getTime())) {
        return String(date);
    }
    
    const defaultOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    };
    
    try {
        return new Intl.DateTimeFormat(undefined, { ...defaultOptions, ...options }).format(dateObj);
    } catch (error) {
        return dateObj.toLocaleDateString();
    }
}

/**
 * Formatta un orario.
 * @param {Date|string} time - L'orario da formattare
 * @returns {string} L'orario formattato
 */
export function formatTime(time) {
    if (typeof time === 'string' && /^\d{2}:\d{2}/.test(time)) {
        return time.substring(0, 5);
    }
    
    const dateObj = time instanceof Date ? time : new Date(time);
    if (isNaN(dateObj.getTime())) {
        return String(time);
    }
    
    try {
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(dateObj);
    } catch (error) {
        return dateObj.toLocaleTimeString();
    }
}

/**
 * Crea una funzione debounced.
 * @param {Function} func - La funzione da debounce
 * @param {number} wait - Millisecondi di attesa
 * @returns {Function} La funzione debounced
 */
export function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Escape HTML per prevenire XSS.
 * @param {string} text - Il testo da escape
 * @returns {string} Il testo escapato
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Crea un elemento HTML da una stringa.
 * @param {string} html - L'HTML da parsare
 * @returns {Element} L'elemento creato
 */
export function createElementFromHTML(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.firstChild;
}

/**
 * Gestisce lo stato di loading di elementi UI.
 * @param {boolean} isLoading - Se è in loading
 * @param {object} elements - Elementi da disabilitare/abilitare
 */
export function setLoadingState(isLoading, elements = {}) {
    const { loading, buttons = [] } = elements;
    
    if (loading) {
        loading.hidden = !isLoading;
    }
    
    buttons.forEach(button => {
        if (button) {
            button.disabled = isLoading;
        }
    });
}

/**
 * Annuncia un messaggio agli screen reader.
 * @param {string} message - Il messaggio da annunciare
 * @param {Element} liveRegion - L'elemento ARIA live region
 */
export function announceToScreenReader(message, liveRegion) {
    if (!liveRegion) {
        return;
    }
    
    liveRegion.textContent = '';
    setTimeout(() => {
        liveRegion.textContent = message;
    }, 100);
}

/**
 * Download di un file dal browser.
 * @param {string} content - Il contenuto del file
 * @param {string} filename - Il nome del file
 * @param {string} mimeType - Il MIME type
 */
export function downloadFile(content, filename, mimeType = 'text/plain') {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

/**
 * Ottiene parametri dalla query string.
 * @param {string} param - Il nome del parametro
 * @returns {string|null} Il valore del parametro
 */
export function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Aggiorna la query string senza ricaricare la pagina.
 * @param {object} params - I parametri da aggiornare
 */
export function updateQueryString(params) {
    const url = new URL(window.location);
    Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            url.searchParams.set(key, params[key]);
        } else {
            url.searchParams.delete(key);
        }
    });
    window.history.replaceState({}, '', url);
}
