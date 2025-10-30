(function () {
    const root = document.querySelector('[data-fp-resv-closures]');
    if (!root) {
        return;
    }

    const settings = window.fpResvClosuresSettings || {};
    const ajaxUrl = settings.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = settings.nonce || '';
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

    const ajaxRequest = (action, data = {}) => {
        console.log('[FP Closures AJAX]', action, data);
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);
        
        for (const [key, value] of Object.entries(data)) {
            if (value !== null && value !== undefined) {
                formData.append(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
            }
        }
        
        return fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        }).then((response) => {
            console.log('[FP Closures AJAX Response]', response.status);
            return response.json();
        }).then((result) => {
            console.log('[FP Closures AJAX Result]', result);
            
            // WordPress AJAX ritorna {success: true/false, data: {...}}
            if (result && result.success === false) {
                const message = result.data && result.data.message ? result.data.message : 'Errore AJAX';
                throw new Error(message);
            }
            
            // Ritorna i dati (result.data contiene il payload)
            return result && result.data ? result.data : result;
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
        console.log('[FP Closures] renderList() - items count:', state.items.length);
        list.innerHTML = '';
        if (state.items.length === 0) {
            console.log('[FP Closures] Nessun item da visualizzare - mostro empty state');
            emptyState.hidden = false;
            return;
        }
        console.log('[FP Closures] Rendering', state.items.length, 'items');
        emptyState.hidden = true;
        state.items.forEach((item, index) => {
            console.log('[FP Closures] Rendering item', index, ':', item);
            if (!item || !item.id) {
                console.warn('[FP Closures] Item', index, 'non valido (manca id)');
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
        console.log('[FP Closures] loadClosures() chiamato');
        setLoading(true);
        ajaxRequest('fp_resv_closures_list', { include_inactive: 1 })
            .then((payload) => {
                console.log('[FP Closures] loadClosures payload ricevuto:', payload);
                const items = Array.isArray(payload && payload.items)
                    ? payload.items
                    : Array.isArray(payload)
                        ? payload
                        : [];
                console.log('[FP Closures] Items estratti:', items);
                console.log('[FP Closures] Numero items:', items.length);
                state.items = items;
                state.error = '';
                render();
            })
            .catch((error) => {
                console.error('[FP Closures] loadClosures error:', error);
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
        console.log('[FP Closures] Form submit triggered');
        
        if (!startField || !endField || !typeField) {
            console.error('[FP Closures] Missing required fields');
            return;
        }
        const startValue = startField.value;
        const endValue = endField.value;
        if (!startValue || !endValue) {
            console.error('[FP Closures] Missing start or end values');
            form.reportValidity();
            return;
        }
        const startDate = new Date(startValue);
        const endDate = new Date(endValue);
        if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate <= startDate) {
            console.error('[FP Closures] Invalid dates');
            endField.setCustomValidity("La fine deve essere successiva all'inizio.");
            form.reportValidity();
            endField.setCustomValidity('');
            return;
        }
        
        // Formatta le date mantenendo il timezone locale con offset
        const formatLocalDateTime = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            
            // Calcola offset timezone (es: +01:00 o +02:00)
            const offset = -date.getTimezoneOffset();
            const offsetHours = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0');
            const offsetMinutes = String(Math.abs(offset) % 60).padStart(2, '0');
            const offsetSign = offset >= 0 ? '+' : '-';
            
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds} ${offsetSign}${offsetHours}:${offsetMinutes}`;
        };
        
        const payload = {
            scope: 'restaurant',
            type: typeField.value,
            start_at: formatLocalDateTime(startDate),
            end_at: formatLocalDateTime(endDate),
            note: noteField ? noteField.value.trim() : '',
        };
        if (payload.type === 'capacity_reduction' && percentField) {
            const percent = Number.parseInt(percentField.value, 10);
            if (Number.isNaN(percent)) {
                console.error('[FP Closures] Invalid percent value');
                percentField.focus();
                return;
            }
            payload.capacity_percent = percent;
        }
        
        console.log('[FP Closures] Sending payload:', payload);
        setLoading(true);
        ajaxRequest('fp_resv_closures_create', payload)
            .then((response) => {
                console.log('[FP Closures] Success response:', response);
                state.error = '';
                toggleForm(false);
                loadClosures();
            })
            .catch((error) => {
                console.error('[FP Closures] Error:', error);
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
            ajaxRequest('fp_resv_closures_delete', { id: id })
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
