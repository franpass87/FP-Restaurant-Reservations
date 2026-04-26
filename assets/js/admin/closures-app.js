(function () {
    // Production-ready: removed debug agent logs
    window.fpResvInitClosuresApp = function fpResvInitClosuresApp() {
        const root = document.querySelector('[data-fp-resv-closures]:not([data-fp-resv-closures-mounted])');
        if (!root) {
            return;
        }
        root.setAttribute('data-fp-resv-closures-mounted', '1');

    const settings = window.fpResvClosuresSettings || {};

    const normalizeAjaxUrl = (rawUrl) => {
        const fallback = '/wp-admin/admin-ajax.php';

        if (!rawUrl || typeof rawUrl !== 'string') {
            return window.location.origin + fallback;
        }

        try {
            const parsed = new URL(rawUrl, window.location.origin);
            parsed.protocol = window.location.protocol;
            parsed.host = window.location.host;
            return parsed.toString();
        } catch (error) {
            if (rawUrl.startsWith('/')) {
                return window.location.origin + rawUrl;
            }

            return window.location.origin + fallback;
        }
    };

    const ajaxUrl = normalizeAjaxUrl(window.ajaxurl || settings.ajaxUrl || '/wp-admin/admin-ajax.php');
    const nonce = settings.nonce || '';
    const statsNodes = {
        active: document.querySelector('[data-role="closures-active"]'),
        capacity: document.querySelector('[data-role="closures-capacity"]'),
        next: document.querySelector('[data-role="closures-next"]'),
    };

    const defaults = {
        headline: 'Planner operativo: chiusure e aperture',
        description: 'Gestisci chiusure, periodi speciali e aperture straordinarie.',
        createCta: 'Nuovo evento operativo',
        empty: 'Nessuna chiusura o apertura programmata nel periodo selezionato.',
        emptyFiltered: 'Nessun risultato con i filtri attuali.',
        formTitle: 'Configura nuovo evento operativo',
        searchLabel: 'Cerca evento',
        modeLabel: 'Cosa vuoi fare?',
        modeDay: 'Chiudi giorno intero',
        modeSlot: 'Chiudi fascia oraria',
        modeAdvanced: 'Avanzato (riduzione/apertura speciale)',
        dateLabel: 'Data',
        dateEndLabel: 'Data fine (per più giorni)',
        startLabel: 'Inizio',
        endLabel: 'Fine',
        timeFromLabel: 'Dalle ore',
        timeToLabel: 'Alle ore',
        typeLabel: 'Tipologia',
        scopeLabel: 'Ambito',
        noteLabel: 'Nota (facoltativa)',
        scopeRestaurant: 'Ristorante intero',
        typeFull: 'Chiusura totale',
        typeCapacity: 'Riduzione capienza',
        typeSpecial: 'Orari speciali',
        typeSpecialOpening: 'Apertura speciale',
        percentLabel: 'Capienza disponibile (%)',
        labelPlaceholder: 'Nome servizio (es. Brunch di Natale)',
        capacityLabel: 'Capacità massima',
        slotsLabel: 'Fasce orarie',
        addSlotCta: 'Aggiungi fascia',
        slotStartLabel: 'Dalle',
        slotEndLabel: 'Alle',
        save: 'Salva',
        cancel: 'Annulla',
        delete: 'Elimina',
        clearFilters: 'Reset filtri',
        confirmDelete: 'Eliminare definitivamente?',
        edit: 'Modifica',
        formTitleEdit: 'Modifica evento operativo',
        searchPlaceholder: 'Cerca per nota, tipo o ambito...',
        filterLabel: 'Filtro',
        filterAll: 'Tutte',
        filterActive: 'Attive',
        filterUpcoming: 'Future',
        filterExpired: 'Scadute',
        filterSpecial: 'Aperture speciali',
        sortLabel: 'Ordina',
        sortNearest: 'Più vicine',
        sortLatest: 'Più lontane',
        statusActive: 'Attiva',
        statusUpcoming: 'Futura',
        statusExpired: 'Scaduta',
    };

    const strings = { ...defaults, ...(settings.strings || {}) };

    const state = {
        items: Array.isArray(settings.preview && settings.preview.events) ? settings.preview.events : [],
        loading: false,
        error: '',
        formOpen: false,
        search: '',
        filter: 'all',
        sort: 'nearest',
        editingId: null,
        editingMealKey: '',
    };

    const ajaxRequest = (action, data = {}) => {
        // Production-ready: removed debug agent logs and console.log statements
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);
        
        for (const [key, value] of Object.entries(data)) {
            if (value !== null && value !== undefined) {
                formData.append(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
            }
        }
        
        // Add cache-busting timestamp to prevent browser/CDN caching
        const cacheBustUrl = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + '_t=' + Date.now();
        
        return fetch(cacheBustUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store', // Prevent browser caching
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        }).then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.json();
        }).then((result) => {
            // WordPress AJAX ritorna {success: true/false, data: {...}}
            if (result && result.success === false) {
                const message = result.data && result.data.message ? result.data.message : 'Errore AJAX';
                throw new Error(message);
            }
            
            // Ritorna i dati (result.data contiene il payload)
            return result && result.data ? result.data : result;
        }).catch((error) => {
            throw error;
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

    const controls = document.createElement('div');
    controls.className = 'fp-resv-closures-app__controls';
    controls.innerHTML = `
        <label class="fp-resv-closures-app__control fp-resv-closures-app__control--search">
            <span>${strings.searchLabel}</span>
            <input type="search" data-role="closures-search" placeholder="${strings.searchPlaceholder}">
        </label>
        <label class="fp-resv-closures-app__control">
            <span>${strings.filterLabel}</span>
            <select data-role="closures-filter">
                <option value="all">${strings.filterAll}</option>
                <option value="active">${strings.filterActive}</option>
                <option value="upcoming">${strings.filterUpcoming}</option>
                <option value="expired">${strings.filterExpired}</option>
                <option value="special_opening">${strings.filterSpecial}</option>
            </select>
        </label>
        <label class="fp-resv-closures-app__control">
            <span>${strings.sortLabel}</span>
            <select data-role="closures-sort">
                <option value="nearest">${strings.sortNearest}</option>
                <option value="latest">${strings.sortLatest}</option>
            </select>
        </label>
        <button type="button" class="button fp-resv-closures-app__reset" data-role="closures-reset">
            ${strings.clearFilters}
        </button>
    `;
    const searchInput = controls.querySelector('[data-role="closures-search"]');
    const filterSelect = controls.querySelector('[data-role="closures-filter"]');
    const sortSelect = controls.querySelector('[data-role="closures-sort"]');
    const resetButton = controls.querySelector('[data-role="closures-reset"]');

    const form = document.createElement('form');
    form.className = 'fp-resv-closures-form';
    form.hidden = true;
    form.innerHTML = `
        <header><h3>${strings.formTitle}</h3></header>
        <div class="fp-resv-closures-form__grid">
            <label class="fp-resv-closures-form__field fp-resv-closures-form__field--wide">
                <span>${strings.modeLabel}</span>
                <select name="closure_mode">
                    <option value="day">${strings.modeDay}</option>
                    <option value="slot">${strings.modeSlot}</option>
                    <option value="advanced">${strings.modeAdvanced}</option>
                </select>
            </label>

            <!-- Modalità GIORNO: solo date picker -->
            <div class="fp-resv-closures-form__mode-day">
                <label class="fp-resv-closures-form__field">
                    <span>${strings.dateLabel}</span>
                    <input type="date" name="day_date" required>
                </label>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.dateEndLabel}</span>
                    <input type="date" name="day_date_end">
                </label>
            </div>

            <!-- Modalità SLOT: data + ora inizio/fine -->
            <div class="fp-resv-closures-form__mode-slot" hidden>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.dateLabel}</span>
                    <input type="date" name="slot_date" required>
                </label>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.timeFromLabel}</span>
                    <input type="time" name="slot_time_from" required>
                </label>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.timeToLabel}</span>
                    <input type="time" name="slot_time_to" required>
                </label>
            </div>

            <!-- Modalità AVANZATA: form originale completo -->
            <div class="fp-resv-closures-form__mode-advanced" hidden>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.startLabel}</span>
                    <input type="datetime-local" name="start">
                </label>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.endLabel}</span>
                    <input type="datetime-local" name="end">
                </label>
                <label class="fp-resv-closures-form__field">
                    <span>${strings.typeLabel}</span>
                    <select name="type">
                        <option value="full">${strings.typeFull}</option>
                        <option value="capacity_reduction">${strings.typeCapacity}</option>
                        <option value="special_hours">${strings.typeSpecial}</option>
                        <option value="special_opening">${strings.typeSpecialOpening}</option>
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
                <div class="fp-resv-closures-form__special-opening" hidden>
                    <label class="fp-resv-closures-form__field">
                        <span>${strings.labelPlaceholder.split(' (')[0]}</span>
                        <input type="text" name="special_label" placeholder="${strings.labelPlaceholder}">
                    </label>
                    <label class="fp-resv-closures-form__field">
                        <span>${strings.capacityLabel}</span>
                        <input type="number" name="special_capacity" min="1" max="500" value="40">
                    </label>
                    <div class="fp-resv-closures-form__slots">
                        <span class="fp-resv-closures-form__slots-label">${strings.slotsLabel}</span>
                        <div class="fp-resv-closures-form__slots-list"></div>
                        <button type="button" class="button button-secondary fp-resv-closures-form__add-slot">${strings.addSlotCta}</button>
                    </div>
                </div>
            </div>

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

    const formTitleH3 = form.querySelector('header h3');
    const modeField = form.querySelector('[name="closure_mode"]');
    const modeDayWrapper = form.querySelector('.fp-resv-closures-form__mode-day');
    const modeSlotWrapper = form.querySelector('.fp-resv-closures-form__mode-slot');
    const modeAdvancedWrapper = form.querySelector('.fp-resv-closures-form__mode-advanced');
    const typeField = form.querySelector('[name="type"]');
    const startField = form.querySelector('[name="start"]');
    const endField = form.querySelector('[name="end"]');
    const noteField = form.querySelector('[name="note"]');
    const percentWrapper = form.querySelector('.fp-resv-closures-form__field--percent');
    const percentField = form.querySelector('[name="percent"]');
    const specialOpeningWrapper = form.querySelector('.fp-resv-closures-form__special-opening');
    const specialLabelField = form.querySelector('[name="special_label"]');
    const specialCapacityField = form.querySelector('[name="special_capacity"]');
    const slotsList = form.querySelector('.fp-resv-closures-form__slots-list');
    const addSlotButton = form.querySelector('.fp-resv-closures-form__add-slot');

    const switchMode = (mode) => {
        if (modeDayWrapper) modeDayWrapper.hidden = mode !== 'day';
        if (modeSlotWrapper) modeSlotWrapper.hidden = mode !== 'slot';
        if (modeAdvancedWrapper) modeAdvancedWrapper.hidden = mode !== 'advanced';

        // Gestisci required sui campi per evitare validazione su campi nascosti
        form.querySelectorAll('.fp-resv-closures-form__mode-day input').forEach(i => { i.required = mode === 'day' && !i.name.includes('end'); });
        form.querySelectorAll('.fp-resv-closures-form__mode-slot input').forEach(i => { i.required = mode === 'slot'; });
        if (startField) startField.required = mode === 'advanced';
        if (endField) endField.required = mode === 'advanced';
    };

    if (modeField) {
        modeField.addEventListener('change', () => switchMode(modeField.value));
        switchMode(modeField.value);
    }
    
    // Helper to create a slot row
    const createSlotRow = (startValue = '', endValue = '') => {
        const row = document.createElement('div');
        row.className = 'fp-resv-closures-form__slot-row';
        row.innerHTML = `
            <label>
                <span>${strings.slotStartLabel}</span>
                <input type="time" name="slot_start[]" value="${startValue}" required>
            </label>
            <label>
                <span>${strings.slotEndLabel}</span>
                <input type="time" name="slot_end[]" value="${endValue}" required>
            </label>
            <button type="button" class="button-link fp-resv-closures-form__remove-slot">&times;</button>
        `;
        row.querySelector('.fp-resv-closures-form__remove-slot').addEventListener('click', () => {
            row.remove();
        });
        return row;
    };
    
    // Add slot button handler
    if (addSlotButton && slotsList) {
        addSlotButton.addEventListener('click', () => {
            slotsList.appendChild(createSlotRow());
        });
    }

    const toDateTimeLocal = (value) => {
        if (!value || typeof value !== 'string') {
            return '';
        }
        const t = value.trim();
        if (t === '') {
            return '';
        }
        const normalized = t.includes('T') ? t : t.replace(' ', 'T');
        const ts = Date.parse(normalized);
        if (!Number.isFinite(ts)) {
            return '';
        }
        const d = new Date(ts);
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    };

    const clearFormFields = () => {
        form.reset();
        if (percentWrapper) {
            percentWrapper.hidden = true;
        }
        if (specialOpeningWrapper) {
            specialOpeningWrapper.hidden = true;
        }
        if (slotsList) {
            slotsList.innerHTML = '';
        }
        if (modeField) {
            modeField.value = 'day';
            switchMode('day');
        }
    };

    const resetForm = () => {
        state.editingId = null;
        state.editingMealKey = '';
        if (formTitleH3) {
            formTitleH3.textContent = strings.formTitle;
        }
        clearFormFields();
    };

    const openFormForEdit = (item) => {
        if (!item) {
            return;
        }
        const co = item.capacity_override;
        state.editingId = item.id;
        state.editingMealKey = (item.type === 'special_opening' && co && co.meal_key) ? String(co.meal_key) : '';
        if (formTitleH3) {
            formTitleH3.textContent = strings.formTitleEdit || 'Modifica evento operativo';
        }
        clearFormFields();
        if (modeField) {
            modeField.value = 'advanced';
            switchMode('advanced');
        }
        if (typeField) {
            typeField.value = item.type;
            typeField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (startField) {
            startField.value = toDateTimeLocal(String(item.start_at || ''));
        }
        if (endField) {
            endField.value = toDateTimeLocal(String(item.end_at || ''));
        }
        if (noteField) {
            noteField.value = item.note || '';
        }
        if (item.type === 'capacity_reduction' && percentField && co && co.percent != null) {
            percentField.value = String(co.percent);
        }
        if (item.type === 'special_opening' && co) {
            if (specialLabelField) {
                specialLabelField.value = co.label || '';
            }
            if (specialCapacityField) {
                specialCapacityField.value = String(co.capacity != null ? co.capacity : 40);
            }
            if (slotsList) {
                slotsList.innerHTML = '';
                const sl = co.slots;
                if (Array.isArray(sl) && sl.length > 0) {
                    sl.forEach((s) => {
                        if (s && s.start && s.end) {
                            const a = String(s.start).length >= 5 ? String(s.start).substring(0, 5) : String(s.start);
                            const b = String(s.end).length >= 5 ? String(s.end).substring(0, 5) : String(s.end);
                            slotsList.appendChild(createSlotRow(a, b));
                        }
                    });
                }
                if (slotsList.children.length === 0) {
                    slotsList.appendChild(createSlotRow('12:00', '15:00'));
                }
            }
        }
        state.formOpen = true;
        form.hidden = false;
        if (toggleButton) {
            toggleButton.textContent = strings.cancel;
        }
        if (startField) {
            startField.focus();
        }
    };

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
    app.appendChild(controls);
    app.appendChild(form);
    app.appendChild(errorBox);
    app.appendChild(list);
    app.appendChild(emptyState);

    const setLoading = (loading) => {
        state.loading = loading;
        root.dataset.state = loading ? 'loading' : '';
    };

    const ROME_TIMEZONE = 'Europe/Rome';
    const DISPLAY_LOCALE = 'it-IT';

    const formatDateTime = (iso) => {
        if (!iso) {
            return '';
        }
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return new Intl.DateTimeFormat(DISPLAY_LOCALE, {
            timeZone: ROME_TIMEZONE,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(date);
    };

    const parseBackendDateTime = (value) => {
        if (!value || typeof value !== 'string') {
            return NaN;
        }
        const raw = value.trim();
        if (raw === '') {
            return NaN;
        }
        // Supporta sia ISO 8601 sia formato WP "YYYY-MM-DD HH:mm:ss".
        const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
        const ts = Date.parse(normalized);
        if (Number.isFinite(ts)) {
            return ts;
        }
        return Date.parse(normalized + 'Z');
    };

    const normalizeDateTimeLocalInput = (value) => {
        if (!value || typeof value !== 'string') {
            return '';
        }
        const trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }
        if (trimmed.includes('T')) {
            return `${trimmed.replace('T', ' ')}:00`;
        }
        return trimmed;
    };

    const formatType = (type) => {
        switch (type) {
            case 'capacity_reduction':
                return strings.typeCapacity;
            case 'special_hours':
                return strings.typeSpecial;
            case 'special_opening':
                return strings.typeSpecialOpening;
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

    const getItemStatus = (item) => {
        if (!item || !item.active) {
            return 'expired';
        }
        const now = Date.now();
        const startTs = parseBackendDateTime(item.start_at);
        const endTs = parseBackendDateTime(item.end_at);

        if (Number.isFinite(endTs) && endTs < now) {
            return 'expired';
        }
        if (Number.isFinite(startTs) && startTs > now) {
            return 'upcoming';
        }
        return 'active';
    };

    const getVisibleItems = () => {
        const query = (state.search || '').trim().toLowerCase();
        let filtered = state.items.filter((item) => item && item.id);

        if (query) {
            filtered = filtered.filter((item) => {
                const haystack = [
                    item.note || '',
                    item.type || '',
                    item.scope || '',
                    item.capacity_override && item.capacity_override.label ? item.capacity_override.label : '',
                ].join(' ').toLowerCase();
                return haystack.includes(query);
            });
        }

        if (state.filter !== 'all') {
            filtered = filtered.filter((item) => {
                if (state.filter === 'special_opening') {
                    return item.type === 'special_opening';
                }
                return getItemStatus(item) === state.filter;
            });
        }

        filtered.sort((a, b) => {
            const aTs = parseBackendDateTime(a.start_at);
            const bTs = parseBackendDateTime(b.start_at);
            const aVal = Number.isFinite(aTs) ? aTs : 0;
            const bVal = Number.isFinite(bTs) ? bTs : 0;
            return state.sort === 'latest' ? bVal - aVal : aVal - bVal;
        });

        return filtered;
    };

    const updateStats = () => {
        const activeClosures = state.items.filter((item) => item && item.type === 'full' && item.active);
        const capacityClosures = state.items.filter((item) => item && item.type === 'capacity_reduction' && item.active);
        const upcoming = state.items
            .map((item) => ({ item, ts: item && item.end_at ? parseBackendDateTime(item.end_at) : NaN }))
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
        const visibleItems = getVisibleItems();
        if (visibleItems.length === 0) {
            const hasActiveFilters = state.search.trim() !== '' || state.filter !== 'all' || state.sort !== 'nearest';
            emptyState.textContent = hasActiveFilters ? strings.emptyFiltered : strings.empty;
            emptyState.hidden = false;
            return;
        }
        emptyState.hidden = true;
        visibleItems.forEach((item) => {
            if (!item || !item.id) {
                // Item non valido (manca id) - skip silently in production
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

            const statusBadge = document.createElement('span');
            const status = getItemStatus(item);
            statusBadge.className = `fp-resv-closure-card__status fp-resv-closure-card__status--${status}`;
            statusBadge.textContent = status === 'active'
                ? strings.statusActive
                : status === 'upcoming'
                    ? strings.statusUpcoming
                    : strings.statusExpired;
            header.appendChild(statusBadge);

            const actions = document.createElement('div');
            actions.className = 'fp-resv-closure-card__actions';
            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'button-link';
            editBtn.textContent = strings.edit || 'Modifica';
            editBtn.dataset.action = 'edit-closure';
            editBtn.dataset.id = String(item.id);
            actions.appendChild(editBtn);
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
            
            // Special opening details
            if (item.type === 'special_opening' && item.capacity_override) {
                if (item.capacity_override.label) {
                    const labelLine = document.createElement('p');
                    labelLine.className = 'fp-resv-closure-card__meta fp-resv-closure-card__meta--highlight';
                    labelLine.textContent = `🎉 ${item.capacity_override.label}`;
                    card.appendChild(labelLine);
                }
                
                if (Number.isFinite(item.capacity_override.capacity)) {
                    const capLine = document.createElement('p');
                    capLine.className = 'fp-resv-closure-card__meta';
                    capLine.textContent = `${strings.capacityLabel}: ${item.capacity_override.capacity}`;
                    card.appendChild(capLine);
                }
                
                if (Array.isArray(item.capacity_override.slots) && item.capacity_override.slots.length > 0) {
                    const slotsLine = document.createElement('p');
                    slotsLine.className = 'fp-resv-closure-card__meta';
                    const slotsText = item.capacity_override.slots
                        .map(s => `${s.start || ''}-${s.end || ''}`)
                        .filter(s => s !== '-')
                        .join(', ');
                    slotsLine.textContent = `${strings.slotsLabel}: ${slotsText}`;
                    card.appendChild(slotsLine);
                }
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
        // Production-ready: removed debug logs
        setLoading(true);
        ajaxRequest('fp_resv_closures_list', { include_inactive: 0 })
            .then((payload) => {
                const items = Array.isArray(payload && payload.items)
                    ? payload.items
                    : Array.isArray(payload)
                        ? payload
                        : [];
                state.items = items;
                state.error = '';
                render();
            })
            .catch((error) => {
                // Only log critical errors in production
                if (window.fpResvClosuresSettings && window.fpResvClosuresSettings.debug) {
                    console.error('[FP Closures] loadClosures error:', error);
                }
                state.error = error && error.message ? error.message : 'Impossibile caricare le chiusure.';
                renderError();
            })
            .finally(() => {
                setLoading(false);
            });
    };

    const toggleForm = (open) => {
        state.formOpen = open;
        form.hidden = !open;
        toggleButton.textContent = open ? strings.cancel : strings.createCta;
        if (open) {
            const dayInput = form.querySelector('[name="day_date"]');
            if (dayInput) dayInput.focus();
        }
        if (!open) {
            resetForm();
        }
    };

    toggleButton.addEventListener('click', () => {
        if (state.formOpen) {
            toggleForm(false);
        } else {
            state.editingId = null;
            state.editingMealKey = '';
            if (formTitleH3) {
                formTitleH3.textContent = strings.formTitle;
            }
            clearFormFields();
            toggleForm(true);
        }
    });

    const buildPayloadFromMode = () => {
        const mode = modeField ? modeField.value : 'advanced';
        const debug = window.fpResvClosuresSettings && window.fpResvClosuresSettings.debug;

        if (mode === 'day') {
            const dateVal = form.querySelector('[name="day_date"]')?.value;
            const dateEndVal = form.querySelector('[name="day_date_end"]')?.value;
            if (!dateVal) { form.reportValidity(); return null; }
            const endDate = dateEndVal || dateVal;
            if (endDate < dateVal) {
                state.error = 'La data fine non può essere anteriore alla data inizio.';
                renderError();
                return null;
            }
            return {
                scope: 'restaurant',
                type: 'full',
                start_at: `${dateVal} 00:00:00`,
                end_at: `${endDate} 23:59:59`,
                note: noteField ? noteField.value.trim() : '',
            };
        }

        if (mode === 'slot') {
            const dateVal = form.querySelector('[name="slot_date"]')?.value;
            const timeFrom = form.querySelector('[name="slot_time_from"]')?.value;
            const timeTo = form.querySelector('[name="slot_time_to"]')?.value;
            if (!dateVal || !timeFrom || !timeTo) { form.reportValidity(); return null; }
            if (timeTo <= timeFrom) {
                state.error = "L'ora di fine deve essere successiva all'ora di inizio.";
                renderError();
                return null;
            }
            return {
                scope: 'restaurant',
                type: 'full',
                start_at: `${dateVal} ${timeFrom}:00`,
                end_at: `${dateVal} ${timeTo}:00`,
                note: noteField ? noteField.value.trim() : '',
            };
        }

        // mode === 'advanced'
        if (!startField || !endField || !typeField) { return null; }
        const startValue = startField.value;
        const endValue = endField.value;
        if (!startValue || !endValue) { form.reportValidity(); return null; }
        const normalizedStart = normalizeDateTimeLocalInput(startValue);
        const normalizedEnd = normalizeDateTimeLocalInput(endValue);
        if (!normalizedStart || !normalizedEnd || normalizedEnd <= normalizedStart) {
            endField.setCustomValidity("La fine deve essere successiva all'inizio.");
            form.reportValidity();
            endField.setCustomValidity('');
            return null;
        }

        const payload = {
            scope: 'restaurant',
            type: typeField.value,
            start_at: normalizedStart,
            end_at: normalizedEnd,
            note: noteField ? noteField.value.trim() : '',
        };

        if (payload.type === 'capacity_reduction' && percentField) {
            const percent = Number.parseInt(percentField.value, 10);
            if (Number.isNaN(percent)) { percentField.focus(); return null; }
            payload.capacity_percent = percent;
        }

        if (payload.type === 'special_opening') {
            payload.label = specialLabelField ? specialLabelField.value.trim() : '';
            payload.capacity = specialCapacityField ? Number.parseInt(specialCapacityField.value, 10) : 40;
            if (!payload.label) {
                if (specialLabelField) specialLabelField.focus();
                return null;
            }
            const slots = [];
            if (slotsList) {
                const startInputs = slotsList.querySelectorAll('[name="slot_start[]"]');
                const endInputs = slotsList.querySelectorAll('[name="slot_end[]"]');
                startInputs.forEach((si, idx) => {
                    const ei = endInputs[idx];
                    if (si && ei && si.value && ei.value) {
                        slots.push({ start: si.value, end: ei.value });
                    }
                });
            }
            if (slots.length === 0) {
                if (debug) console.error('[FP Closures] Special opening requires at least one slot');
                return null;
            }
            payload.special_hours = slots;
        }

        return payload;
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const payload = buildPayloadFromMode();
        if (!payload) return;

        if (state.editingId) {
            payload.id = state.editingId;
            if (payload.type === 'special_opening' && state.editingMealKey) {
                payload.meal_key = state.editingMealKey;
            }
        }
        const action = state.editingId ? 'fp_resv_closures_update' : 'fp_resv_closures_create';
        setLoading(true);
        ajaxRequest(action, payload)
            .then(() => {
                state.error = '';
                toggleForm(false);
                loadClosures();
            })
            .catch((error) => {
                state.error = error && error.message
                    ? error.message
                    : (state.editingId ? 'Impossibile aggiornare l\'evento operativo.' : 'Impossibile creare la chiusura.');
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

    if (typeField) {
        typeField.addEventListener('change', () => {
            const type = typeField.value;
            const showPercent = type === 'capacity_reduction';
            const showSpecialOpening = type === 'special_opening';
            
            if (percentWrapper) {
                percentWrapper.hidden = !showPercent;
                if (!showPercent && percentField) {
                    percentField.value = '';
                }
            }
            
            if (specialOpeningWrapper) {
                specialOpeningWrapper.hidden = !showSpecialOpening;
                if (showSpecialOpening && slotsList && slotsList.children.length === 0) {
                    slotsList.appendChild(createSlotRow('12:00', '15:00'));
                }
                if (!showSpecialOpening) {
                    if (specialLabelField) specialLabelField.value = '';
                    if (specialCapacityField) specialCapacityField.value = '40';
                    if (slotsList) slotsList.innerHTML = '';
                }
            }
        });
    }

    // Imposta la data di default per le modalità giorno/slot
    const today = new Date();
    const todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
    const dayDateInput = form.querySelector('[name="day_date"]');
    const slotDateInput = form.querySelector('[name="slot_date"]');
    if (dayDateInput) dayDateInput.value = todayStr;
    if (slotDateInput) slotDateInput.value = todayStr;

    list.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        if (target.dataset.action === 'edit-closure') {
            const id = Number.parseInt(target.dataset.id || '0', 10);
            if (!id) {
                return;
            }
            const item = state.items.find((row) => row && row.id === id);
            if (item) {
                openFormForEdit(item);
            }
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

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            state.search = searchInput.value || '';
            renderList();
        });
    }
    if (filterSelect) {
        filterSelect.addEventListener('change', () => {
            state.filter = filterSelect.value || 'all';
            renderList();
        });
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            state.sort = sortSelect.value || 'nearest';
            renderList();
        });
    }
    if (resetButton) {
        resetButton.addEventListener('click', () => {
            state.search = '';
            state.filter = 'all';
            state.sort = 'nearest';
            if (searchInput) {
                searchInput.value = '';
            }
            if (filterSelect) {
                filterSelect.value = 'all';
            }
            if (sortSelect) {
                sortSelect.value = 'nearest';
            }
            renderList();
        });
    }

    render();
    loadClosures();
    };

    /**
     * Avvio automatico solo se il mount non è dentro un pannello nascosto (es. tab Manager).
     */
    function fpResvClosuresTryAutoBoot() {
        const root = document.querySelector('[data-fp-resv-closures]');
        if (!root) {
            return;
        }
        let el = root.parentElement;
        while (el) {
            if (el.hasAttribute('hidden') || el.getAttribute('aria-hidden') === 'true') {
                return;
            }
            el = el.parentElement;
        }
        window.fpResvInitClosuresApp();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fpResvClosuresTryAutoBoot);
    } else {
        fpResvClosuresTryAutoBoot();
    }
})();
