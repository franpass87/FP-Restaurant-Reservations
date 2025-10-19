import { formatDebugMessage } from './debug.js';
import { normalizeAvailabilityState, AVAILABILITY_STATES } from './constants/availability-states.js';
import { createSlotsRenderer, summarizeSlots as calculateSlotsSummary } from './components/slots-renderer.js';
import { createAvailabilityLegend } from './components/availability-legend.js';

const DEBOUNCE_MS = 400;
const CACHE_TTL_MS = 60000;
const MAX_RETRY_ATTEMPTS = 3;
const BASE_RETRY_DELAY_MS = 600;

function buildUrl(base, params) {
    let url;

    try {
        url = new URL(base, window.location.origin);
    } catch (error) {
        const root = window.location.origin.replace(/\/$/, '');
        const normalized = base.startsWith('/') ? root + base : root + '/' + base;
        url = new URL(normalized, window.location.origin);
    }

    url.searchParams.set('date', params.date);
    url.searchParams.set('party', String(params.party));
    if (params.meal) {
        url.searchParams.set('meal', params.meal);
    }

    return url.toString();
}

export function createAvailabilityController(options) {
    const root = options.root;
    const statusEl = root.querySelector('[data-fp-resv-slots-status]');
    const listEl = root.querySelector('[data-fp-resv-slots-list]');
    const emptyEl = root.querySelector('[data-fp-resv-slots-empty]');
    const boundaryEl = root.querySelector('[data-fp-resv-slots-boundary]');
    const retryButton = boundaryEl ? boundaryEl.querySelector('[data-fp-resv-slots-retry]') : null;
    const legendEl = root.querySelector('[data-fp-resv-slots-legend]');

    const cache = new Map();
    let debounceId = null;
    let lastParams = null;
    let currentSelection = null;
    let activeRequestToken = 0;

    // Inizializza i componenti
    const slotsRenderer = createSlotsRenderer({
        listElement: listEl,
        skeletonCount: options.skeletonCount || 4,
    });

    const legend = createAvailabilityLegend(legendEl);

    // Usa la funzione importata per normalizzare lo stato
    const normalizeSlotStatus = normalizeAvailabilityState;

    // Usa la funzione importata per riassumere gli slot
    const summarizeSlots = calculateSlotsSummary;

    function notifyAvailability(params, summary) {
        if (typeof options.onAvailabilitySummary !== 'function') {
            return;
        }

        try {
            options.onAvailabilitySummary(summary, params || lastParams || {});
        } catch (error) {
            // noop
        }
    }

    if (retryButton) {
        retryButton.addEventListener('click', () => {
            if (lastParams) {
                request(lastParams, 0);
            }
        });
    }

    function setStatus(message, state) {
        const normalizedState = typeof state === 'string'
            ? state
            : (state ? 'loading' : 'idle');
        const text = typeof message === 'string' ? message : '';
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.setAttribute('data-state', normalizedState);
        }
        const isLoading = normalizedState === 'loading';
        root.setAttribute('data-loading', isLoading ? 'true' : 'false');
        if (listEl) {
            listEl.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }
    }

    function showSkeleton() {
        slotsRenderer.showSkeleton();
    }

    function showEmpty(params) {
        if (emptyEl) {
            emptyEl.hidden = false;
        }

        const hasParams = params && typeof params === 'object';
        const mealValue = hasParams && typeof params.meal === 'string' ? params.meal.trim() : '';
        const dateValue = hasParams && typeof params.date === 'string' ? params.date.trim() : '';
        const partyValue = hasParams && typeof params.party !== 'undefined'
            ? String(params.party).trim()
            : '';
        const requiresMeal = hasParams && Boolean(params.requiresMeal);

        const hasMeal = mealValue !== '';
        const hasDate = dateValue !== '';
        const hasParty = partyValue !== '' && partyValue !== '0';
        const readyForAvailability = hasDate && hasParty && (!requiresMeal || hasMeal);

        const message = (() => {
            if (requiresMeal && !hasMeal) {
                return (options.strings && options.strings.selectMeal) || '';
            }

            if (!readyForAvailability) {
                return '';
            }

            return (options.strings && options.strings.slotsEmpty) || '';
        })();

        setStatus(message, 'idle');

        if (listEl) {
            clearChildren(listEl);
        }

        // Non usare 'full' automaticamente quando non ci sono slot
        // Usa 'unavailable' per distinguere da 'full' (prenotato)
        const state = readyForAvailability ? 'unavailable' : 'unknown';
        notifyAvailability(params, { state, slots: 0 });
    }

    function hideEmpty() {
        if (emptyEl) {
            emptyEl.hidden = true;
        }
    }

    function hideBoundary() {
        if (boundaryEl) {
            boundaryEl.hidden = true;
        }
    }

    function showBoundary(message) {
        const fallback = (options.strings && options.strings.slotsError)
            || (options.strings && options.strings.submitError)
            || 'Impossibile aggiornare la disponibilità. Riprova.';

        if (boundaryEl) {
            const text = boundaryEl.querySelector('[data-fp-resv-slots-boundary-message]');
            if (text) {
                text.textContent = message || fallback;
            }
            boundaryEl.hidden = false;
        }

        setStatus(message || fallback, 'error');
        notifyAvailability(lastParams, { state: 'error', slots: 0 });
    }

    function selectSlot(slot, button) {
        currentSelection = slot;
        slotsRenderer.updateSelection(slot);
        
        if (typeof options.onSlotSelected === 'function') {
            options.onSlotSelected(slot);
        }
    }

    function clearSelectionState() {
        currentSelection = null;
        slotsRenderer.updateSelection(null);
    }

    function renderSlots(payload, params, requestToken) {
        if (requestToken && requestToken !== activeRequestToken) {
            return;
        }

        if (params && lastParams && params !== lastParams) {
            return;
        }

        hideBoundary();
        hideEmpty();
        if (!listEl) {
            return;
        }

        const slots = payload && Array.isArray(payload.slots) ? payload.slots : [];
        if (slots.length === 0) {
            showEmpty(params);
            return;
        }

        // Usa il renderer per mostrare gli slot
        slotsRenderer.renderSlots(slots, currentSelection, selectSlot);

        // Mostra la legenda se ci sono slot
        legend.show();

        setStatus((options.strings && options.strings.slotsUpdated) || '', false);
        const hasAvailabilityFlag = Boolean(
            payload
            && (
                (typeof payload.has_availability !== 'undefined' && payload.has_availability)
                || (payload.meta && payload.meta.has_availability)
            )
        );
        
        const summary = summarizeSlots(slots, hasAvailabilityFlag);
        notifyAvailability(params, summary);
        
        // Aggiorna la legenda per evidenziare lo stato corrente
        if (summary && summary.state) {
            legend.updateLegendState(summary.state);
        }
    }

    function request(params, attempt) {
        lastParams = params;
        if (!params || !params.date || !params.party) {
            showEmpty(params);
            return;
        }

        const requestToken = ++activeRequestToken;
        const cacheKey = JSON.stringify([params.date, params.meal, params.party]);
        const cached = cache.get(cacheKey);
        if (cached && Date.now() - cached.timestamp < CACHE_TTL_MS && attempt === 0) {
            renderSlots(cached.payload, params, requestToken);
            return;
        }

        hideBoundary();
        hideEmpty();
        showSkeleton();
        setStatus((options.strings && options.strings.updatingSlots) || 'Aggiornamento disponibilità…', 'loading');
        notifyAvailability(params, { state: 'loading', slots: 0 });

        const url = buildUrl(options.endpoint, params);
        const start = performance.now();

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => response.json()
                .catch(() => ({}))
                .then((payload) => {
                    if (!response.ok) {
                        const error = new Error('availability_error');
                        error.status = response.status;
                        error.payload = payload;
                        const retryAfter = response.headers.get('Retry-After');
                        if (retryAfter) {
                            const parsedRetry = Number.parseInt(retryAfter, 10);
                            if (Number.isFinite(parsedRetry)) {
                                error.retryAfter = parsedRetry;
                            }
                        }
                        throw error;
                    }

                    return payload;
                }))
            .then((payload) => {
                if (requestToken !== activeRequestToken) {
                    return;
                }

                const latency = performance.now() - start;
                if (typeof options.onLatency === 'function') {
                    options.onLatency(latency);
                }
                cache.set(cacheKey, { payload, timestamp: Date.now() });
                renderSlots(payload, params, requestToken);
            })
            .catch((error) => {
                if (requestToken !== activeRequestToken) {
                    return;
                }

                const latency = performance.now() - start;
                if (typeof options.onLatency === 'function') {
                    options.onLatency(latency);
                }

                const payloadData = error && error.payload && typeof error.payload === 'object'
                    ? error.payload.data || {}
                    : {};
                const status = typeof error.status === 'number'
                    ? error.status
                    : (payloadData && typeof payloadData.status === 'number' ? payloadData.status : 0);
                let retryAfterSeconds = 0;
                if (error && typeof error.retryAfter === 'number' && Number.isFinite(error.retryAfter)) {
                    retryAfterSeconds = error.retryAfter;
                } else if (payloadData && typeof payloadData.retry_after !== 'undefined') {
                    const parsed = Number.parseInt(payloadData.retry_after, 10);
                    if (Number.isFinite(parsed)) {
                        retryAfterSeconds = parsed;
                    }
                }

                const shouldRetry = (() => {
                    if (attempt >= MAX_RETRY_ATTEMPTS - 1) {
                        return false;
                    }
                    if (status === 429) {
                        return true;
                    }
                    if (status >= 500 && status < 600) {
                        return true;
                    }
                    return status === 0;
                })();

                if (shouldRetry) {
                    const nextAttempt = attempt + 1;
                    if (typeof options.onRetry === 'function') {
                        options.onRetry(nextAttempt);
                    }
                    const delay = retryAfterSeconds > 0
                        ? Math.max(retryAfterSeconds * 1000, BASE_RETRY_DELAY_MS)
                        : BASE_RETRY_DELAY_MS * Math.pow(2, attempt);
                    window.setTimeout(() => request(params, nextAttempt), delay);
                    return;
                }

                const rawMessage = (error && error.payload && (error.payload.message || error.payload.code))
                    || (payloadData && payloadData.message)
                    || (options.strings && options.strings.slotsError)
                    || (options.strings && options.strings.submitError)
                    || 'Impossibile aggiornare la disponibilità. Riprova.';
                const debugSource = (error && error.payload) || payloadData || null;
                const message = formatDebugMessage(rawMessage, debugSource);
                showBoundary(message);
            });
    }

    return {
        schedule(params, scheduleOptions = {}) {
            if (debounceId) {
                window.clearTimeout(debounceId);
            }

            const normalizedOptions = scheduleOptions && typeof scheduleOptions === 'object'
                ? scheduleOptions
                : {};
            const effective = params || (typeof options.getParams === 'function' ? options.getParams() : null);
            const requiresMeal = Boolean(effective && effective.requiresMeal);
            if (!effective || !effective.date || !effective.party || (requiresMeal && !effective.meal)) {
                lastParams = effective;
                showEmpty(effective || {});
                return;
            }

            if (normalizedOptions.immediate) {
                request(effective, 0);
                return;
            }

            debounceId = window.setTimeout(() => {
                request(effective, 0);
            }, DEBOUNCE_MS);
        },
        revalidate() {
            if (!lastParams) {
                return;
            }

            const key = JSON.stringify([lastParams.date, lastParams.meal, lastParams.party]);
            cache.delete(key);
            request(lastParams, 0);
        },
        getSelection() {
            return currentSelection;
        },
        clearSelection() {
            clearSelectionState();
        },
    };
}
