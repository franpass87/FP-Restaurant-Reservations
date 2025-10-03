(function () {
    const root = document.querySelector('[data-fp-resv-closures]');
    if (!root) {
        return;
    }

    const settings = window.fpResvClosuresSettings || {};
    const restRoot = (settings.restRoot || '/wp-json/fp-resv/v1').replace(/\/$/, '');
    const statsNodes = {
        active: document.querySelector('[data-role="closures-active"]'),
        capacity: document.querySelector('[data-role="closures-capacity"]'),
        next: document.querySelector('[data-role="closures-next"]'),
    };

    const defaults = {
        headline: 'Chiusure programmate',
        description: 'Gestisci chiusure e periodi speciali.',
        createCta: 'Nuova chiusura',
        empty: 'Nessuna chiusura programmata nel periodo selezionato.',
        formTitle: 'Nuova chiusura',
        startLabel: 'Inizio',
        endLabel: 'Fine',
        typeLabel: 'Tipologia',
        scopeLabel: 'Ambito',
        noteLabel: 'Nota (facoltativa)',
        scopeRestaurant: 'Ristorante intero',
        typeFull: 'Chiusura totale',
        typeCapacity: 'Riduzione capienza',
        typeSpecial: 'Orari speciali',
        percentLabel: 'Capienza disponibile (%)',
        save: 'Salva chiusura',
        cancel: 'Annulla',
        delete: 'Elimina',
        confirmDelete: 'Eliminare definitivamente questa chiusura?',
    };

    const strings = { ...defaults, ...(settings.strings || {}) };

    const state = {
        items: Array.isArray(settings.preview && settings.preview.events) ? settings.preview.events : [],
        loading: false,
        error: '',
        formOpen: false,
    };

    const request = (path, options = {}) => {
        const url = typeof path === 'string' && path.startsWith('http') ? path : `${restRoot}${path}`;
        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': settings.nonce || '',
            },
            credentials: 'same-origin',
        };
        if (options.data) {
            config.body = JSON.stringify(options.data);
        }
        return fetch(url, config).then((response) => {
            if (!response.ok) {
                return response
                    .json()
                    .catch(() => ({}))
                    .then((payload) => {
                        const message = payload && payload.message ? payload.message : 'Richiesta non riuscita';
                        const error = new Error(message);
                        error.status = response.status;
                        throw error;
                    });
            }
            if (response.status === 204) {
                return null;
            }
            const contentLength = response.headers.get('content-length');
            if (contentLength === '0') {
                return null;
            }
            return response
                .text()
                .then((text) => {
                    if (!text) {
                        return null;
                    }
                    const contentType = response.headers.get('content-type') || '';
                    if (!contentType.includes('json')) {
                        const error = new Error(text.trim() || 'Risposta non valida.');
                        error.status = response.status;
                        throw error;
                    }
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        const parseError = error instanceof Error ? error : new Error('Risposta non valida.');
                        parseError.status = response.status;
                        throw parseError;
                    }
                });
        });
    };

    const app = document.createElement('div');
    app.className = 'fp-resv-closures-app__shell';
    root.innerHTML = '';
    root.appendChild(app);

    const toolbar = document.createElement('div');
    toolbar.className = 'fp-resv-closures-app__toolbar';
    const toolbarTitle = document.createElement('h2');
    toolbarTitle.textContent = strings.headline;
    const toolbarDesc = document.createElement('p');
    toolbarDesc.textContent = strings.description;
    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'button button-primary';
    toggleButton.textContent = strings.createCta;
    toolbar.appendChild(toolbarTitle);
    toolbar.appendChild(toolbarDesc);
    toolbar.appendChild(toggleButton);

    const form = document.createElement('form');
    form.className = 'fp-resv-closures-form';
    form.hidden = true;
    form.innerHTML = `
        <header><h3>${strings.formTitle}</h3></header>
        <div class="fp-resv-closures-form__grid">
            <label class="fp-resv-closures-form__field">
                <span>${strings.startLabel}</span>
                <input type="datetime-local" name="start" required>
            </label>
            <label class="fp-resv-closures-form__field">
                <span>${strings.endLabel}</span>
                <input type="datetime-local" name="end" required>
            </label>
            <label class="fp-resv-closures-form__field">
                <span>${strings.typeLabel}</span>
                <select name="type">
                    <option value="full">${strings.typeFull}</option>
                    <option value="capacity_reduction">${strings.typeCapacity}</option>
                    <option value="special_hours">${strings.typeSpecial}</option>
                </select>
            </label>
            <label class="fp-resv-closures-form__field">
                <span>${strings.scopeLabel}</span>
                <select name="scope">
                    <option value="restaurant">${strings.scopeRestaurant}</option>
                </select>
            </label>
            <label class="fp-resv-closures-form__field fp-resv-closures-form__field--percent" hidden>
                <span>${strings.percentLabel}</span>
                <input type="number" name="percent" min="0" max="100" step="5" placeholder="50">
            </label>
            <label class="fp-resv-closures-form__field fp-resv-closures-form__field--wide">
                <span>${strings.noteLabel}</span>
                <textarea name="note" rows="2"></textarea>
            </label>
        </div>
        <div class="fp-resv-closures-form__actions">
            <button type="button" class="button" data-form-cancel>${strings.cancel}</button>
            <button type="submit" class="button button-primary">${strings.save}</button>
        </div>
    `;

    const typeField = form.querySelector('[name="type"]');
    const startField = form.querySelector('[name="start"]');
    const endField = form.querySelector('[name="end"]');
    const noteField = form.querySelector('[name="note"]');
    const percentWrapper = form.querySelector('.fp-resv-closures-form__field--percent');
    const percentField = form.querySelector('[name="percent"]');

    const list = document.createElement('div');
    list.className = 'fp-resv-closures-list';
    const emptyState = document.createElement('p');
    emptyState.className = 'fp-resv-closures-empty';
    emptyState.textContent = strings.empty;
    emptyState.hidden = true;
    const errorBox = document.createElement('div');
    errorBox.className = 'fp-resv-closures-error';
    errorBox.hidden = true;

    app.appendChild(toolbar);
    app.appendChild(form);
    app.appendChild(errorBox);
    app.appendChild(list);
    app.appendChild(emptyState);

    const setLoading = (loading) => {
        state.loading = loading;
        root.dataset.state = loading ? 'loading' : '';
    };

    const formatDateTime = (iso) => {
        if (!iso) {
            return '';
        }
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleString();
    };

    const formatType = (type) => {
        switch (type) {
            case 'capacity_reduction':
                return strings.typeCapacity;
            case 'special_hours':
                return strings.typeSpecial;
            case 'full':
            default:
                return strings.typeFull;
        }
    };

    const formatScope = (scope) => {
        switch (scope) {
            case 'restaurant':
            default:
                return strings.scopeRestaurant;
        }
    };

    const updateStats = () => {
        const activeClosures = state.items.filter((item) => item && item.type === 'full' && item.active);
        const capacityClosures = state.items.filter((item) => item && item.type === 'capacity_reduction' && item.active);
        const upcoming = state.items
            .map((item) => ({ item, ts: item && item.end_at ? Date.parse(item.end_at) : NaN }))
            .filter((entry) => entry.item && entry.item.active && Number.isFinite(entry.ts) && entry.ts > Date.now())
            .sort((a, b) => a.ts - b.ts);

        if (statsNodes.active) {
            statsNodes.active.textContent = String(activeClosures.length);
        }
        if (statsNodes.capacity) {
            const reductionCount = capacityClosures.length;
            const percentValues = capacityClosures
                .map((item) => (item && item.capacity_override && item.capacity_override.percent) || null)
                .filter((value) => Number.isFinite(value));
            if (percentValues.length) {
                const min = Math.min(...percentValues);
                const max = Math.max(...percentValues);
                statsNodes.capacity.textContent = `${reductionCount} • ${min}%-${max}%`;
            } else {
                statsNodes.capacity.textContent = String(reductionCount);
            }
        }
        if (statsNodes.next) {
            statsNodes.next.textContent = upcoming.length ? formatDateTime(upcoming[0].item.end_at) : '—';
        }
    };

    const renderList = () => {
        list.innerHTML = '';
        if (state.items.length === 0) {
            emptyState.hidden = false;
            return;
        }
        emptyState.hidden = true;
        state.items.forEach((item) => {
            if (!item || !item.id) {
                return;
            }
            const card = document.createElement('article');
            card.className = 'fp-resv-closure-card';
            if (!item.active) {
                card.classList.add('is-inactive');
            }

            const header = document.createElement('header');
            header.className = 'fp-resv-closure-card__header';
            const typeBadge = document.createElement('span');
            typeBadge.className = 'fp-resv-closure-card__type';
            typeBadge.textContent = formatType(item.type);
            header.appendChild(typeBadge);

            const actions = document.createElement('div');
            actions.className = 'fp-resv-closure-card__actions';
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'button-link';
            deleteBtn.textContent = strings.delete;
            deleteBtn.dataset.action = 'delete-closure';
            deleteBtn.dataset.id = String(item.id);
            actions.appendChild(deleteBtn);
            header.appendChild(actions);
            card.appendChild(header);

            const range = document.createElement('p');
            range.className = 'fp-resv-closure-card__range';
            range.textContent = `${formatDateTime(item.start_at)} → ${formatDateTime(item.end_at)}`;
            card.appendChild(range);

            const scopeLine = document.createElement('p');
            scopeLine.className = 'fp-resv-closure-card__meta';
            scopeLine.textContent = `${strings.scopeLabel}: ${formatScope(item.scope)}`;
            card.appendChild(scopeLine);

            if (item.capacity_override && Number.isFinite(item.capacity_override.percent)) {
                const capLine = document.createElement('p');
                capLine.className = 'fp-resv-closure-card__meta';
                capLine.textContent = `${strings.percentLabel}: ${item.capacity_override.percent}%`;
                card.appendChild(capLine);
            }

            if (item.note) {
                const note = document.createElement('p');
                note.className = 'fp-resv-closure-card__note';
                note.textContent = item.note;
                card.appendChild(note);
            }

            list.appendChild(card);
        });
    };

    const renderError = () => {
        if (state.error) {
            errorBox.textContent = state.error;
            errorBox.hidden = false;
        } else {
            errorBox.textContent = '';
            errorBox.hidden = true;
        }
    };

    const render = () => {
        renderList();
        renderError();
        updateStats();
    };

    const loadClosures = () => {
        setLoading(true);
        request('/closures?include_inactive=1')
            .then((payload) => {
                state.items = Array.isArray(payload.items) ? payload.items : [];
                state.error = '';
                render();
            })
            .catch((error) => {
                state.error = error && error.message ? error.message : 'Impossibile caricare le chiusure.';
                renderError();
            })
            .finally(() => {
                setLoading(false);
            });
    };

    const resetForm = () => {
        form.reset();
        if (percentWrapper) {
            percentWrapper.hidden = true;
        }
    };

    const toggleForm = (open) => {
        state.formOpen = open;
        form.hidden = !open;
        toggleButton.textContent = open ? strings.cancel : strings.createCta;
        if (open && startField) {
            startField.focus();
        }
        if (!open) {
            resetForm();
        }
    };

    toggleButton.addEventListener('click', () => {
        toggleForm(!state.formOpen);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!startField || !endField || !typeField) {
            return;
        }
        const startValue = startField.value;
        const endValue = endField.value;
        if (!startValue || !endValue) {
            form.reportValidity();
            return;
        }
        const startDate = new Date(startValue);
        const endDate = new Date(endValue);
        if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate <= startDate) {
            endField.setCustomValidity("La fine deve essere successiva all'inizio.");
            form.reportValidity();
            endField.setCustomValidity('');
            return;
        }
        const payload = {
            scope: 'restaurant',
            type: typeField.value,
            start_at: startDate.toISOString(),
            end_at: endDate.toISOString(),
            note: noteField ? noteField.value.trim() : '',
            active: true,
        };
        if (payload.type === 'capacity_reduction' && percentField) {
            const percent = Number.parseInt(percentField.value, 10);
            if (Number.isNaN(percent)) {
                percentField.focus();
                return;
            }
            payload.capacity_override = { percent };
        }
        setLoading(true);
        request('/closures', { method: 'POST', data: payload })
            .then(() => {
                state.error = '';
                toggleForm(false);
                loadClosures();
            })
            .catch((error) => {
                state.error = error && error.message ? error.message : 'Impossibile creare la chiusura.';
                renderError();
            })
            .finally(() => {
                setLoading(false);
            });
    });

    const cancelButton = form.querySelector('[data-form-cancel]');
    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            toggleForm(false);
        });
    }

    if (typeField && percentWrapper) {
        typeField.addEventListener('change', () => {
            const showPercent = typeField.value === 'capacity_reduction';
            percentWrapper.hidden = !showPercent;
            if (!showPercent && percentField) {
                percentField.value = '';
            }
        });
    }

    list.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        if (target.dataset.action === 'delete-closure') {
            const id = Number.parseInt(target.dataset.id || '0', 10);
            if (!id) {
                return;
            }
            if (!window.confirm(strings.confirmDelete)) {
                return;
            }
            setLoading(true);
            request(`/closures/${id}`, { method: 'DELETE' })
                .then(() => {
                    state.items = state.items.filter((item) => item.id !== id);
                    render();
                })
                .catch((error) => {
                    state.error = error && error.message ? error.message : 'Impossibile eliminare la chiusura.';
                    renderError();
                })
                .finally(() => {
                    setLoading(false);
                });
        }
    });

    render();
    loadClosures();
})();
