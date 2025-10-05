import { formatDebugMessage } from './debug.js';

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

function clearChildren(node) {
    while (node.firstChild) {
        node.removeChild(node.firstChild);
    }
}

export function createAvailabilityController(options) {
    const root = options.root;
    const statusEl = root.querySelector('[data-fp-resv-slots-status]');
    const listEl = root.querySelector('[data-fp-resv-slots-list]');
    const emptyEl = root.querySelector('[data-fp-resv-slots-empty]');
    const boundaryEl = root.querySelector('[data-fp-resv-slots-boundary]');
    const retryButton = boundaryEl ? boundaryEl.querySelector('[data-fp-resv-slots-retry]') : null;

    const cache = new Map();
    let debounceId = null;
    let lastParams = null;
    let currentSelection = null;
    let activeRequestToken = 0;

    function normalizeSlotStatus(status) {
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
            return 'available';
        }

        if (normalized === 'waitlist' || normalized === 'busy') {
            return 'limited';
        }

        if (
            matchesStart(['limited', 'limit', 'limitat', 'limite', 'cupos limit', 'attesa'])
            || matchesAnywhere(['pochi posti', 'quasi pien', 'lista attesa', 'few spots', 'casi llen'])
        ) {
            return 'limited';
        }

        if (matchesStart(['full', 'complet', 'esaurit', 'soldout', 'sold out', 'agotad', 'chius', 'plen'])) {
            return 'full';
        }

        return normalized;
    }

    function summarizeSlots(slots, hasAvailabilityFlag) {
        const safeSlots = Array.isArray(slots) ? slots : [];
        const slotCount = safeSlots.length;
        if (slotCount === 0) {
            return { state: 'full', slots: 0 };
        }

        const statuses = safeSlots
            .map((slot) => normalizeSlotStatus(slot && slot.status))
            .filter((status) => status !== '');

        const hasLimited = statuses.some((status) => status === 'limited');
        if (hasLimited) {
            return { state: 'limited', slots: slotCount };
        }

        const hasAvailable = statuses.some((status) => status === 'available');
        if (hasAvailable) {
            return { state: 'available', slots: slotCount };
        }

        if (hasAvailabilityFlag) {
            return { state: 'available', slots: slotCount };
        }

        if (statuses.length === 0) {
            return { state: 'available', slots: slotCount };
        }

        return { state: 'full', slots: slotCount };
    }

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
        if (!listEl) {
            return;
        }

        clearChildren(listEl);
        const count = options.skeletonCount || 4;
        for (let index = 0; index < count; index += 1) {
            const item = document.createElement('li');
            const placeholder = document.createElement('span');
            placeholder.className = 'fp-skeleton';
            item.appendChild(placeholder);
            listEl.appendChild(item);
        }
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

        const hasMeal = mealValue !== '';
        const hasDate = dateValue !== '';
        const hasParty = partyValue !== '' && partyValue !== '0';
        const readyForAvailability = hasMeal && hasDate && hasParty;

        const message = (() => {
            if (!hasMeal) {
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

        const state = readyForAvailability ? 'full' : 'unknown';
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
            || 'We could not update available times. Please try again.';

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
        const buttons = listEl ? listEl.querySelectorAll('button[data-slot]') : [];
        Array.prototype.forEach.call(buttons, (item) => {
            item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
        });

        currentSelection = slot;
        if (typeof options.onSlotSelected === 'function') {
            options.onSlotSelected(slot);
        }
    }

    function clearSelectionState() {
        currentSelection = null;
        if (!listEl) {
            return;
        }

        const buttons = listEl.querySelectorAll('button[data-slot]');
        Array.prototype.forEach.call(buttons, (button) => {
            button.setAttribute('aria-pressed', 'false');
        });
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

        clearChildren(listEl);
        const slots = payload && Array.isArray(payload.slots) ? payload.slots : [];
        if (slots.length === 0) {
            showEmpty(params);
            return;
        }

        slots.forEach((slot) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = slot.label || '';
            button.dataset.slot = slot.start || '';
            button.dataset.slotStatus = slot.status || '';
            button.setAttribute('aria-pressed', currentSelection && currentSelection.start === slot.start ? 'true' : 'false');
            button.addEventListener('click', () => selectSlot(slot, button));
            item.appendChild(button);
            listEl.appendChild(item);
        });

        setStatus((options.strings && options.strings.slotsUpdated) || '', false);
        const hasAvailabilityFlag = Boolean(
            payload
            && (
                (typeof payload.has_availability !== 'undefined' && payload.has_availability)
                || (payload.meta && payload.meta.has_availability)
            )
        );
        notifyAvailability(params, summarizeSlots(slots, hasAvailabilityFlag));
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
        showSkeleton();
        setStatus((options.strings && options.strings.updatingSlots) || 'Updating availability…', 'loading');
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
                    || 'We could not update available times. Please try again.';
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
