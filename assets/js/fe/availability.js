
const DEBOUNCE_MS = 250;
const CACHE_TTL_MS = 120000;
const RETRY_DELAYS_MS = [500, 1000, 2000];

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

    if (retryButton) {
        retryButton.addEventListener('click', () => {
            if (lastParams) {
                request(lastParams, 0);
            }
        });
    }

    function setStatus(message, loading) {
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.setAttribute('data-state', loading ? 'loading' : 'idle');
        }
        root.setAttribute('data-loading', loading ? 'true' : 'false');
        if (listEl) {
            listEl.setAttribute('aria-busy', loading ? 'true' : 'false');
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

        const message = !params || !params.meal
            ? ((options.strings && options.strings.selectMeal) || '')
            : ((options.strings && options.strings.slotsEmpty) || '');
        setStatus(message, false);

        if (listEl) {
            clearChildren(listEl);
        }
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
        if (!boundaryEl) {
            return;
        }

        const text = boundaryEl.querySelector('[data-fp-resv-slots-boundary-message]');
        if (text) {
            text.textContent = message || (options.strings && options.strings.submitError) || 'We could not update available times. Please try again.';
        }
        boundaryEl.hidden = false;
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

    function renderSlots(payload, params) {
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
    }

    function request(params, attempt) {
        lastParams = params;
        if (!params || !params.date || !params.party) {
            showEmpty(params);
            return;
        }

        const cacheKey = JSON.stringify([params.date, params.meal, params.party]);
        const cached = cache.get(cacheKey);
        if (cached && Date.now() - cached.timestamp < CACHE_TTL_MS && attempt === 0) {
            renderSlots(cached.payload, params);
            return;
        }

        hideBoundary();
        showSkeleton();
        setStatus((options.strings && options.strings.updatingSlots) || 'Updating availability…', true);

        const url = buildUrl(options.endpoint, params);
        const start = performance.now();

        fetch(url, { credentials: 'same-origin' })
            .then((response) => {
                if (!response.ok) {
                    throw Object.assign(new Error('availability_error'), { status: response.status });
                }
                return response.json();
            })
            .then((payload) => {
                const latency = performance.now() - start;
                if (typeof options.onLatency === 'function') {
                    options.onLatency(latency);
                }
                cache.set(cacheKey, { payload, timestamp: Date.now() });
                renderSlots(payload, params);
            })
            .catch((error) => {
                const latency = performance.now() - start;
                if (typeof options.onLatency === 'function') {
                    options.onLatency(latency);
                }
                if (attempt < RETRY_DELAYS_MS.length) {
                    const nextAttempt = attempt + 1;
                    if (typeof options.onRetry === 'function') {
                        options.onRetry(nextAttempt);
                    }
                    window.setTimeout(() => request(params, nextAttempt), RETRY_DELAYS_MS[attempt]);
                    return;
                }

                setStatus('', false);
                showBoundary((options.strings && options.strings.submitError) || 'We could not update available times. Please try again.');
            });
    }

    return {
        schedule(params) {
            if (debounceId) {
                window.clearTimeout(debounceId);
            }

            const effective = params || (typeof options.getParams === 'function' ? options.getParams() : null);
            if (!effective || !effective.date || !effective.party) {
                lastParams = effective;
                showEmpty(effective || {});
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
    };
}
