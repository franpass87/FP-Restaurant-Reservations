/**
 * FP Reservations Manager - Stile The Fork
 * Gestione moderna delle prenotazioni ristorante
 */

class ReservationManager {
    constructor() {
        // Configurazione
        this.config = {
            restRoot: this.normalizeRestRoot(
                window.fpResvManagerSettings?.restRoot || '/wp-json/fp-resv/v1'
            ),
            nonce: window.fpResvManagerSettings?.nonce || '',
            strings: window.fpResvManagerSettings?.strings || {},
            meals: window.fpResvManagerSettings?.meals || [],
        };

        // State management
        this.state = {
            currentDate: new Date(),
            currentView: 'month', // Inizia con vista mese per vedere più prenotazioni
            filters: {
                service: '',
                status: '',
                search: '',
            },
            reservations: [],
            overview: null,
            loading: false,
            error: null,
            availableDaysCache: {},
            availableDaysLoading: false,
        };

        // Cache DOM
        this.dom = {};
        
        // Inizializza
        this.init();
    }

    async init() {
        // Verifica che la configurazione sia presente
        if (!this.config.restRoot || !this.config.nonce) {
            console.error('[Manager] Configuration missing! Check that fpResvManagerSettings is loaded.');
            this.showError('Configurazione mancante. Ricarica la pagina.');
            return;
        }
        
        // Cache elementi DOM
        this.cacheDom();
        
        // Popola il filtro dei servizi con i meals configurati
        this.populateServiceFilter();
        
        // Setup event listeners
        this.bindEvents();
        
        // Imposta data corrente
        this.updateDatePicker();
        
        // Carica dati iniziali
        await this.loadOverview();
        await this.loadReservations();
    }

    cacheDom() {
        this.dom = {
            // Stats
            statsToday: document.querySelectorAll('[data-stat^="today-"]'),
            statsConfirmed: document.querySelectorAll('[data-stat^="confirmed-"]'),
            statsWeek: document.querySelectorAll('[data-stat^="week-"]'),
            statsMonth: document.querySelectorAll('[data-stat^="month-"]'),
            
            // Toolbar
            datePicker: document.getElementById('fp-manager-date'),
            serviceFilter: document.querySelector('[data-role="service-filter"]'),
            statusFilter: document.querySelector('[data-role="status-filter"]'),
            searchInput: document.querySelector('[data-role="search-input"]'),
            viewBtns: document.querySelectorAll('[data-action="set-view"]'),
            
            // Views
            viewDay: document.getElementById('fp-view-day'),
            viewWeek: document.getElementById('fp-view-week'),
            viewMonth: document.getElementById('fp-view-month'),
            viewList: document.getElementById('fp-view-list'),
            
            // States
            loadingState: document.getElementById('fp-loading-state'),
            errorState: document.getElementById('fp-error-state'),
            emptyState: document.getElementById('fp-empty-state'),
            errorMessage: document.getElementById('fp-error-message'),
            
            // Content containers
            timeline: document.getElementById('fp-timeline'),
            weekCalendar: document.getElementById('fp-week-calendar'),
            monthCalendar: document.getElementById('fp-month-calendar'),
            reservationsList: document.getElementById('fp-reservations-list'),
            
            // Modal
            modal: document.getElementById('fp-reservation-modal'),
            modalTitle: document.getElementById('fp-modal-title'),
            modalBody: document.getElementById('fp-modal-body'),
        };
    }

    populateServiceFilter() {
        if (!this.dom.serviceFilter) {
            return;
        }

        // Mantieni l'opzione "Tutti i servizi" che è già presente nel template
        // Aggiungi le opzioni dei meals configurati
        if (this.config.meals && this.config.meals.length > 0) {
            this.config.meals.forEach(meal => {
                const option = document.createElement('option');
                option.value = meal.key || '';
                option.textContent = meal.label || meal.key || '';
                this.dom.serviceFilter.appendChild(option);
            });
        }
    }

    bindEvents() {
        // Date navigation
        document.querySelectorAll('[data-action="prev-day"]').forEach(btn => {
            btn.addEventListener('click', () => this.navigateDate(-1));
        });
        
        document.querySelectorAll('[data-action="next-day"]').forEach(btn => {
            btn.addEventListener('click', () => this.navigateDate(1));
        });
        
        document.querySelectorAll('[data-action="today"]').forEach(btn => {
            btn.addEventListener('click', () => this.goToToday());
        });

        // Date picker
        if (this.dom.datePicker) {
            this.dom.datePicker.addEventListener('change', (e) => {
                this.setDate(new Date(e.target.value));
            });
        }

        // View switcher
        this.dom.viewBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = btn.dataset.view;
                this.setView(view);
            });
        });

        // Filters
        if (this.dom.serviceFilter) {
            this.dom.serviceFilter.addEventListener('change', (e) => {
                this.state.filters.service = e.target.value;
                this.filterReservations();
            });
        }

        if (this.dom.statusFilter) {
            this.dom.statusFilter.addEventListener('change', (e) => {
                this.state.filters.status = e.target.value;
                this.filterReservations();
            });
        }

        if (this.dom.searchInput) {
            // Debounce search
            let searchTimeout;
            this.dom.searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.state.filters.search = e.target.value.toLowerCase();
                    this.filterReservations();
                }, 300);
            });
        }

        // Actions
        document.querySelectorAll('[data-action="new-reservation"]').forEach(btn => {
            btn.addEventListener('click', () => this.openNewReservationModal());
        });

        document.querySelectorAll('[data-action="export"]').forEach(btn => {
            btn.addEventListener('click', () => this.exportReservations());
        });

        document.querySelectorAll('[data-action="retry"]').forEach(btn => {
            btn.addEventListener('click', () => this.loadReservations());
        });

        document.querySelectorAll('[data-action="close-modal"]').forEach(btn => {
            btn.addEventListener('click', () => this.closeModal());
        });

        // Click fuori dal modal per chiudere
        if (this.dom.modal) {
            this.dom.modal.addEventListener('click', (e) => {
                if (e.target === this.dom.modal || e.target.classList.contains('fp-modal__backdrop')) {
                    this.closeModal();
                }
            });
        }

        // ESC per chiudere modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.dom.modal.style.display !== 'none') {
                this.closeModal();
            }
        });
    }

    // ============================================
    // DATE NAVIGATION
    // ============================================

    navigateDate(days) {
        const newDate = new Date(this.state.currentDate);
        newDate.setDate(newDate.getDate() + days);
        this.setDate(newDate);
    }

    goToToday() {
        this.setDate(new Date());
    }

    setDate(date) {
        this.state.currentDate = date;
        this.updateDatePicker();
        this.loadReservations();
    }

    updateDatePicker() {
        if (this.dom.datePicker) {
            this.dom.datePicker.value = this.formatDate(this.state.currentDate);
        }
    }

    /**
     * Formatta data nel timezone locale (NON UTC!)
     * CRITICO: toISOString() converte sempre in UTC e può causare shift di giorno
     */
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // ============================================
    // VIEW MANAGEMENT
    // ============================================

    setView(view) {
        const previousView = this.state.currentView;
        this.state.currentView = view;

        // Update buttons
        this.dom.viewBtns.forEach(btn => {
            if (btn.dataset.view === view) {
                btn.classList.add('is-active');
                btn.setAttribute('aria-pressed', 'true');
            } else {
                btn.classList.remove('is-active');
                btn.setAttribute('aria-pressed', 'false');
            }
        });

        // Se passiamo tra viste che richiedono range diversi, ricarica i dati
        const viewsRequiringReload = ['week', 'month'];
        const previousNeedsRange = viewsRequiringReload.includes(previousView);
        const currentNeedsRange = viewsRequiringReload.includes(view);
        
        if (previousNeedsRange !== currentNeedsRange || (previousNeedsRange && currentNeedsRange && previousView !== view)) {
            this.loadReservations();
        } else {
            // Altrimenti, semplicemente re-render
            this.renderCurrentView();
        }
    }

    // ============================================
    // DATA LOADING
    // ============================================

    async loadOverview() {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch(this.buildRestUrl('agenda/overview'), {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
                signal: controller.signal,
            });
            
            clearTimeout(timeoutId);

            if (!response.ok) {
                return;
            }

            const text = await response.text();
            if (!text || text.trim() === '') {
                return;
            }

            const data = JSON.parse(text);
            this.state.overview = data;
            this.renderStats();
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('[Manager] Error loading overview:', error);
            }
        }
    }

    async loadReservations() {
        this.showLoading();

        try {
            const dateStr = this.formatDate(this.state.currentDate);
            
            // Determina il range in base alla vista corrente
            let range = 'day';
            let startDate = dateStr;
            
            if (this.state.currentView === 'week') {
                // Per la vista settimana, carica 7 giorni partendo dal lunedì
                const currentDate = this.state.currentDate;
                const dayOfWeek = currentDate.getDay();
                // Converti domenica (0) a 7 per calcolo corretto
                const daysSinceMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
                
                const monday = new Date(currentDate);
                monday.setDate(currentDate.getDate() - daysSinceMonday);
                
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                
                startDate = this.formatDate(monday);
                range = 'week';
            } else if (this.state.currentView === 'month') {
                // Per la vista mese, carica tutto il mese
                const currentDate = this.state.currentDate;
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                
                const firstDay = new Date(year, month, 1);
                
                startDate = this.formatDate(firstDay);
                range = 'month';
            }
            
            const params = new URLSearchParams({
                date: startDate,
                range,
            });

            const url = this.buildRestUrl(`agenda?${params.toString()}`);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);
            
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
                signal: controller.signal,
            });
            
            clearTimeout(timeoutId);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('[Manager] Reservations response error:', response.status, errorText);
                throw new Error(`Errore ${response.status}: ${response.statusText}`);
            }

            const text = await response.text();
            if (!text || text.trim() === '') {
                this.state.reservations = [];
                this.state.error = null;
                this.hideLoading();
                this.renderCurrentView();
                return;
            }

            const data = JSON.parse(text);
            this.state.reservations = data.reservations || [];
            this.state.error = null;
            
            // Log per debugging (rimovere dopo verifica)
            if (this.state.reservations.length === 0) {
                console.warn('[Manager] No reservations found. Response:', {
                    meta: data.meta,
                    stats: data.stats,
                    reservationsCount: data.reservations ? data.reservations.length : 0
                });
            }

            this.hideLoading();
            this.renderCurrentView();
        } catch (error) {
            console.error('[Manager] Error loading reservations:', error);
            this.hideLoading();
            
            if (error.name === 'AbortError') {
                this.showError('Timeout: il server impiega troppo tempo a rispondere.');
            } else {
                this.showError(error.message || 'Errore nel caricamento delle prenotazioni');
            }
        }
    }

    async loadAvailableDaysForManager(meal = null) {
        if (this.state.availableDaysLoading) {
            return;
        }

        this.state.availableDaysLoading = true;

        const today = new Date();
        const future = new Date();
        future.setDate(future.getDate() + 90); // 90 giorni nel futuro

        const from = this.formatDate(today);
        const to = this.formatDate(future);

        const params = new URLSearchParams({
            from,
            to,
        });

        if (meal) {
            params.set('meal', meal);
        }

        const url = this.buildRestUrl(`available-days?${params.toString()}`);

        try {
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            if (!response.ok) {
                throw new Error('Errore nel caricamento dei giorni disponibili');
            }

            const data = await response.json();
            
            if (data && data.days) {
                this.state.availableDaysCache = data.days;
            }
        } catch (error) {
            console.warn('[Manager] Errore nel caricamento dei giorni disponibili:', error);
        } finally {
            this.state.availableDaysLoading = false;
        }
    }

    // ============================================
    // RENDERING
    // ============================================

    getFilteredReservations() {
        let filtered = [...this.state.reservations];
        
        // Filtra per servizio (meal) - usa il campo meal dalla prenotazione
        // Se meal è NULL, mostra la prenotazione in tutti i filtri (considera come "non specificato")
        if (this.state.filters.service) {
            filtered = filtered.filter(r => {
                // Se meal è null/undefined, non filtrare (mostra sempre)
                if (!r.meal) return true;
                return r.meal === this.state.filters.service;
            });
        }
        
        // Filtra per stato
        if (this.state.filters.status) {
            filtered = filtered.filter(r => r.status === this.state.filters.status);
        }
        
        // Filtra per ricerca - usa customer object
        if (this.state.filters.search) {
            const searchLower = this.state.filters.search.toLowerCase();
            filtered = filtered.filter(r => {
                const name = `${r.customer.first_name || ''} ${r.customer.last_name || ''}`.toLowerCase();
                const email = (r.customer.email || '').toLowerCase();
                const phone = (r.customer.phone || '').toLowerCase();
                return name.includes(searchLower) || email.includes(searchLower) || phone.includes(searchLower);
            });
        }
        
        return filtered;
    }

    renderStats() {
        if (!this.state.overview) return;

        const { today, week, month } = this.state.overview;

        // Today
        this.updateStat('today-count', today.stats.total_reservations);
        this.updateStat('today-guests', today.stats.total_guests);

        // Confirmed
        const confirmedToday = today.stats.by_status?.confirmed || 0;
        this.updateStat('confirmed-count', confirmedToday);
        this.updateStat('confirmed-percentage', `${today.stats.confirmed_percentage}%`);

        // Week
        this.updateStat('week-count', week.stats.total_reservations);
        this.updateStat('week-guests', week.stats.total_guests);

        // Month
        this.updateStat('month-count', month.stats.total_reservations);
        this.updateStat('month-guests', month.stats.total_guests);
    }

    updateStat(name, value) {
        document.querySelectorAll(`[data-stat="${name}"]`).forEach(el => {
            el.textContent = value;
        });
    }

    renderCurrentView() {
        const filtered = this.getFilteredReservations();

        // Nascondi tutti gli stati
        if (this.dom.loadingState) this.dom.loadingState.style.display = 'none';
        if (this.dom.errorState) this.dom.errorState.style.display = 'none';
        if (this.dom.emptyState) this.dom.emptyState.style.display = 'none';

        if (filtered.length === 0) {
            // Mostra empty state
            if (this.dom.emptyState) this.dom.emptyState.style.display = 'flex';
            
            // Nascondi tutte le viste
            if (this.dom.viewDay) this.dom.viewDay.style.display = 'none';
            if (this.dom.viewWeek) this.dom.viewWeek.style.display = 'none';
            if (this.dom.viewMonth) this.dom.viewMonth.style.display = 'none';
            if (this.dom.viewList) this.dom.viewList.style.display = 'none';
            return;
        }
        
        // Nascondi empty state
        if (this.dom.emptyState) this.dom.emptyState.style.display = 'none';

        // Mostra la vista corrente e nascondi le altre
        if (this.dom.viewDay) this.dom.viewDay.style.display = this.state.currentView === 'day' ? 'block' : 'none';
        if (this.dom.viewWeek) this.dom.viewWeek.style.display = this.state.currentView === 'week' ? 'block' : 'none';
        if (this.dom.viewMonth) this.dom.viewMonth.style.display = this.state.currentView === 'month' ? 'block' : 'none';
        if (this.dom.viewList) this.dom.viewList.style.display = this.state.currentView === 'list' ? 'block' : 'none';
        
        // Render della vista attiva
        switch (this.state.currentView) {
            case 'day':
                this.renderDayView(filtered);
                break;
            case 'week':
                this.renderWeekView();
                break;
            case 'month':
                this.renderMonthView();
                break;
            case 'list':
                this.renderListView(filtered);
                break;
        }
    }

    renderDayView(reservations) {
        // Raggruppa per fascia oraria
        const slots = this.groupByTimeSlot(reservations);
        
        let html = '<div class="fp-timeline-grid">';
        
        if (slots.length === 0) {
            html += '<div class="fp-timeline-empty">Nessuna prenotazione</div>';
        } else {
            slots.forEach(slot => {
                html += this.renderTimeSlot(slot);
            });
        }
        
        html += '</div>';
        
        this.dom.timeline.innerHTML = html;
        
        // Bind eventi alle card
        this.bindReservationCards();
    }

    renderTimeSlot(slot) {
        const statusColors = {
            confirmed: '#10b981',
            pending: '#f59e0b',
            visited: '#3b82f6',
            no_show: '#ef4444',
            cancelled: '#6b7280',
        };

        let html = `
            <div class="fp-timeline-slot">
                <div class="fp-timeline-slot__time">${slot.time}</div>
                <div class="fp-timeline-slot__reservations">
        `;

        slot.reservations.forEach(resv => {
            const statusColor = statusColors[resv.status] || '#6b7280';
            const guestName = `${resv.customer.first_name} ${resv.customer.last_name}`.trim() || resv.customer.email;
            
            html += `
                <div class="fp-reservation-card" data-id="${resv.id}" data-action="view-reservation">
                    <div class="fp-reservation-card__header">
                        <div class="fp-reservation-card__status" style="background-color: ${statusColor}"></div>
                        <div class="fp-reservation-card__time">${resv.time}</div>
                        <div class="fp-reservation-card__party">
                            <span class="dashicons dashicons-groups"></span>
                            ${resv.party}
                        </div>
                    </div>
                    <div class="fp-reservation-card__body">
                        <div class="fp-reservation-card__name">${this.escapeHtml(guestName)}</div>
                        ${resv.customer.phone ? `<div class="fp-reservation-card__phone">${this.escapeHtml(resv.customer.phone)}</div>` : ''}
                        ${resv.notes ? `<div class="fp-reservation-card__notes">${this.escapeHtml(resv.notes)}</div>` : ''}
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        return html;
    }

    renderListView(reservations) {
        let html = '<div class="fp-list-grid">';

        reservations.forEach(resv => {
            html += this.renderListCard(resv);
        });

        html += '</div>';

        this.dom.reservationsList.innerHTML = html;
        this.bindReservationCards();
    }

    renderListCard(resv) {
        const statusLabels = {
            confirmed: 'Confermato',
            pending: 'In attesa',
            visited: 'Visitato',
            no_show: 'No-show',
            cancelled: 'Cancellato',
        };

        const statusClasses = {
            confirmed: 'status--confirmed',
            pending: 'status--pending',
            visited: 'status--visited',
            no_show: 'status--no-show',
            cancelled: 'status--cancelled',
        };

        const guestName = `${resv.customer.first_name} ${resv.customer.last_name}`.trim() || resv.customer.email;

        return `
            <div class="fp-list-card" data-id="${resv.id}" data-action="view-reservation">
                <div class="fp-list-card__main">
                    <div class="fp-list-card__avatar">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="fp-list-card__info">
                        <div class="fp-list-card__name">${this.escapeHtml(guestName)}</div>
                        <div class="fp-list-card__meta">
                            <span>${resv.date}</span>
                            <span>•</span>
                            <span>${resv.time}</span>
                            <span>•</span>
                            <span><span class="dashicons dashicons-groups"></span> ${resv.party}</span>
                        </div>
                        ${resv.customer.phone ? `<div class="fp-list-card__contact">${this.escapeHtml(resv.customer.phone)}</div>` : ''}
                        ${resv.customer.email ? `<div class="fp-list-card__contact">${this.escapeHtml(resv.customer.email)}</div>` : ''}
                    </div>
                </div>
                <div class="fp-list-card__status">
                    <span class="fp-status-badge ${statusClasses[resv.status] || ''}">${statusLabels[resv.status] || resv.status}</span>
                </div>
            </div>
        `;
    }

    renderMonthView() {
        const currentDate = this.state.currentDate;
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Get first day of month and last day
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        
        // Get day of week for first day (0 = Sunday, 1 = Monday, etc)
        let startDay = firstDay.getDay();
        // Convert to Monday = 0
        startDay = startDay === 0 ? 6 : startDay - 1;
        
        // Month name
        const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                           'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        const monthName = monthNames[month];
        
        // Raggruppa prenotazioni per data
        const reservationsByDate = {};
        this.state.reservations.forEach(resv => {
            const date = resv.date;
            if (!reservationsByDate[date]) {
                reservationsByDate[date] = [];
            }
            reservationsByDate[date].push(resv);
        });
        
        // Header
        let html = `
            <div class="fp-month-header">
                <h2>${monthName} ${year}</h2>
                <div class="fp-month-nav">
                    <button type="button" class="fp-btn-icon" data-action="prev-month" title="Mese precedente">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <button type="button" class="fp-btn fp-btn--secondary" data-action="this-month">
                        Questo Mese
                    </button>
                    <button type="button" class="fp-btn-icon" data-action="next-month" title="Mese successivo">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>

            <div class="fp-calendar-grid">
                <div class="fp-calendar-header">
                    <div class="fp-calendar-day-name">Lun</div>
                    <div class="fp-calendar-day-name">Mar</div>
                    <div class="fp-calendar-day-name">Mer</div>
                    <div class="fp-calendar-day-name">Gio</div>
                    <div class="fp-calendar-day-name">Ven</div>
                    <div class="fp-calendar-day-name">Sab</div>
                    <div class="fp-calendar-day-name">Dom</div>
                </div>
                <div class="fp-calendar-days">
        `;
        
        // Empty cells before first day
        for (let i = 0; i < startDay; i++) {
            html += '<div class="fp-calendar-day fp-calendar-day--empty"></div>';
        }
        
        // Days of month
        const today = new Date();
        const todayStr = this.formatDate(today); // Timezone locale!
        
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dateStr = this.formatDate(date); // Timezone locale!
            const reservations = reservationsByDate[dateStr] || [];
            const count = reservations.length;
            const guests = reservations.reduce((sum, r) => sum + (r.party || 0), 0);
            
            const isToday = dateStr === todayStr;
            const isSelected = dateStr === this.formatDate(this.state.currentDate);
            
            let dayClass = 'fp-calendar-day';
            if (isToday) dayClass += ' fp-calendar-day--today';
            if (isSelected) dayClass += ' fp-calendar-day--selected';
            if (count > 0) dayClass += ' fp-calendar-day--has-reservations';
            
            html += `
                <div class="${dayClass}" data-date="${dateStr}" data-action="select-day">
                    <div class="fp-calendar-day__number">${day}</div>
                    ${count > 0 ? `
                        <div class="fp-calendar-day__info">
                            <div class="fp-calendar-day__count">${count} pren.</div>
                            <div class="fp-calendar-day__guests">${guests} coperti</div>
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        // Empty cells after last day to complete the grid
        const totalCells = startDay + daysInMonth;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 0; i < remainingCells; i++) {
            html += '<div class="fp-calendar-day fp-calendar-day--empty"></div>';
        }
        
        html += `
                </div>
            </div>
        `;
        
        this.dom.monthCalendar.innerHTML = html;
        
        // Bind eventi
        this.bindMonthViewEvents();
    }

    bindMonthViewEvents() {
        // Click su giorno
        this.dom.monthCalendar.querySelectorAll('[data-action="select-day"]').forEach(day => {
            day.addEventListener('click', () => {
                const dateStr = day.dataset.date;
                this.setDate(new Date(dateStr + 'T12:00:00'));
                this.setView('day'); // Torna alla vista giorno
            });
        });

        // Navigazione mese
        this.dom.monthCalendar.querySelector('[data-action="prev-month"]')?.addEventListener('click', () => {
            this.navigateMonth(-1);
        });

        this.dom.monthCalendar.querySelector('[data-action="next-month"]')?.addEventListener('click', () => {
            this.navigateMonth(1);
        });

        this.dom.monthCalendar.querySelector('[data-action="this-month"]')?.addEventListener('click', () => {
            this.setDate(new Date());
        });
    }

    navigateMonth(months) {
        const newDate = new Date(this.state.currentDate);
        newDate.setMonth(newDate.getMonth() + months);
        this.state.currentDate = newDate;
        this.updateDatePicker();
        
        // Ricarica le prenotazioni per il nuovo mese
        this.loadReservations();
    }

    renderWeekView() {
        const currentDate = this.state.currentDate;
        const dayOfWeek = currentDate.getDay();
        const daysSinceMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
        
        // Calcola lunedì e domenica della settimana
        const monday = new Date(currentDate);
        monday.setDate(currentDate.getDate() - daysSinceMonday);
        
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        
        // Raggruppa prenotazioni per data
        const reservationsByDate = {};
        this.state.reservations.forEach(resv => {
            const date = resv.date;
            if (!reservationsByDate[date]) {
                reservationsByDate[date] = [];
            }
            reservationsByDate[date].push(resv);
        });
        
        // Nome giorni - Usa wp.i18n se disponibile, altrimenti fallback inglese
        const __ = (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) ? wp.i18n.__ : (text) => text;
        const dayNames = [
            __('Mon', 'fp-restaurant-reservations'),
            __('Tue', 'fp-restaurant-reservations'),
            __('Wed', 'fp-restaurant-reservations'),
            __('Thu', 'fp-restaurant-reservations'),
            __('Fri', 'fp-restaurant-reservations'),
            __('Sat', 'fp-restaurant-reservations'),
            __('Sun', 'fp-restaurant-reservations')
        ];
        
        // Header con navigazione settimana
        const mondayStr = this.formatItalianDate(monday);
        const sundayStr = this.formatItalianDate(sunday);
        
        let html = `
            <div class="fp-week-header">
                <h2>${__('Week', 'fp-restaurant-reservations')} ${mondayStr} - ${sundayStr}</h2>
                <div class="fp-week-nav">
                    <button type="button" class="fp-btn-icon" data-action="prev-week" title="${__('Previous week', 'fp-restaurant-reservations')}">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <button type="button" class="fp-btn fp-btn--secondary" data-action="this-week">
                        ${__('This Week', 'fp-restaurant-reservations')}
                    </button>
                    <button type="button" class="fp-btn-icon" data-action="next-week" title="${__('Next week', 'fp-restaurant-reservations')}">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>

            <div class="fp-week-grid">
        `;
        
        // Giorni della settimana
        const today = new Date();
        const todayStr = this.formatDate(today); // Timezone locale!
        
        for (let i = 0; i < 7; i++) {
            const date = new Date(monday);
            date.setDate(monday.getDate() + i);
            const dateStr = this.formatDate(date); // Timezone locale!
            const reservations = reservationsByDate[dateStr] || [];
            const dayNumber = date.getDate();
            
            const isToday = dateStr === todayStr;
            const isSelected = dateStr === this.formatDate(this.state.currentDate);
            
            let dayClass = 'fp-week-day';
            if (isToday) dayClass += ' fp-week-day--today';
            if (isSelected) dayClass += ' fp-week-day--selected';
            if (reservations.length > 0) dayClass += ' fp-week-day--has-reservations';
            
            // Calcola stats
            const totalGuests = reservations.reduce((sum, r) => sum + (r.party || 0), 0);
            const byMeal = {};
            reservations.forEach(r => {
                const meal = r.meal || 'other';
                byMeal[meal] = (byMeal[meal] || 0) + 1;
            });
            
            html += `
                <div class="${dayClass}" data-date="${dateStr}">
                    <div class="fp-week-day__header">
                        <div class="fp-week-day__name">${dayNames[i]}</div>
                        <div class="fp-week-day__number">${dayNumber}</div>
                    </div>
                    ${reservations.length > 0 ? `
                        <div class="fp-week-day__stats">
                            <div class="fp-week-day__count">${reservations.length} pren.</div>
                            <div class="fp-week-day__guests">${totalGuests} coperti</div>
                        </div>
                        <div class="fp-week-day__reservations">
                            ${reservations.slice(0, 3).map(resv => {
                                const statusColors = {
                                    confirmed: '#10b981',
                                    pending: '#f59e0b',
                                    visited: '#3b82f6',
                                    no_show: '#ef4444',
                                    cancelled: '#6b7280',
                                };
                                const statusColor = statusColors[resv.status] || '#6b7280';
                                const guestName = `${resv.customer.first_name} ${resv.customer.last_name}`.trim() || resv.customer.email;
                                
                                return `
                                    <div class="fp-week-reservation" data-id="${resv.id}" data-action="view-reservation">
                                        <div class="fp-week-reservation__time">${resv.time}</div>
                                        <div class="fp-week-reservation__name">${this.escapeHtml(guestName)}</div>
                                        <div class="fp-week-reservation__party" style="border-left: 3px solid ${statusColor}">
                                            ${resv.party}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                            ${reservations.length > 3 ? `
                                <div class="fp-week-day__more">
                                    +${reservations.length - 3} altre
                                </div>
                            ` : ''}
                        </div>
                    ` : `
                        <div class="fp-week-day__empty">Nessuna prenotazione</div>
                    `}
                </div>
            `;
        }
        
        html += `
            </div>
        `;
        
        this.dom.weekCalendar.innerHTML = html;
        
        // Bind eventi
        this.bindWeekViewEvents();
    }

    bindWeekViewEvents() {
        // Click su giorno per vedere dettagli
        this.dom.weekCalendar.querySelectorAll('.fp-week-day').forEach(day => {
            day.addEventListener('click', (e) => {
                // Se ha cliccato su una prenotazione, non cambiare giorno
                if (e.target.closest('[data-action="view-reservation"]')) {
                    return;
                }
                const dateStr = day.dataset.date;
                this.setDate(new Date(dateStr + 'T12:00:00'));
                this.setView('day'); // Passa alla vista giorno
            });
        });

        // Click sulle prenotazioni
        this.dom.weekCalendar.querySelectorAll('[data-action="view-reservation"]').forEach(card => {
            card.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = parseInt(card.dataset.id, 10);
                this.openReservationModal(id);
            });
        });

        // Navigazione settimana
        this.dom.weekCalendar.querySelector('[data-action="prev-week"]')?.addEventListener('click', () => {
            this.navigateWeek(-1);
        });

        this.dom.weekCalendar.querySelector('[data-action="next-week"]')?.addEventListener('click', () => {
            this.navigateWeek(1);
        });

        this.dom.weekCalendar.querySelector('[data-action="this-week"]')?.addEventListener('click', () => {
            this.setDate(new Date());
        });
    }

    navigateWeek(weeks) {
        const newDate = new Date(this.state.currentDate);
        newDate.setDate(newDate.getDate() + (weeks * 7));
        this.state.currentDate = newDate;
        this.updateDatePicker();
        
        // Ricarica le prenotazioni per la nuova settimana
        this.loadReservations();
    }

    formatItalianDate(date) {
        const day = date.getDate();
        const month = date.getMonth() + 1;
        return `${day}/${month}`;
    }

    // ============================================
    // FILTERING
    // ============================================

    filterReservations() {
        this.renderCurrentView();
    }

    groupByTimeSlot(reservations) {
        const slots = {};

        reservations.forEach(resv => {
            const time = resv.time;
            if (!slots[time]) {
                slots[time] = {
                    time,
                    reservations: [],
                };
            }
            slots[time].reservations.push(resv);
        });

        return Object.values(slots).sort((a, b) => a.time.localeCompare(b.time));
    }

    // ============================================
    // MODAL & ACTIONS
    // ============================================

    bindReservationCards() {
        document.querySelectorAll('[data-action="view-reservation"]').forEach(card => {
            card.addEventListener('click', () => {
                const id = parseInt(card.dataset.id, 10);
                this.openReservationModal(id);
            });
        });
    }

    openReservationModal(id) {
        const resv = this.state.reservations.find(r => r.id === id);
        if (!resv) return;

        const guestName = `${resv.customer.first_name} ${resv.customer.last_name}`.trim() || resv.customer.email;

        this.dom.modalTitle.textContent = guestName;
        this.dom.modalBody.innerHTML = this.renderReservationDetails(resv);
        this.dom.modal.style.display = 'flex';

        // Bind modal actions
        this.bindModalActions(resv);
    }

    renderReservationDetails(resv) {
        const statusLabels = {
            confirmed: 'Confermato',
            pending: 'In attesa',
            visited: 'Visitato',
            no_show: 'No-show',
            cancelled: 'Cancellato',
        };

        return `
            <div class="fp-reservation-details">
                <div class="fp-detail-group">
                    <label>Data e Ora</label>
                    <div class="fp-detail-value">${resv.date} - ${resv.time}</div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Numero Coperti</label>
                    <div class="fp-detail-value">${resv.party}</div>
                </div>
                
                ${resv.meal ? `
                <div class="fp-detail-group">
                    <label>Servizio</label>
                    <div class="fp-detail-value">${this.escapeHtml(resv.meal.charAt(0).toUpperCase() + resv.meal.slice(1))}</div>
                </div>
                ` : ''}
                
                <div class="fp-detail-group">
                    <label>Stato</label>
                    <div class="fp-detail-value">
                        <select class="fp-detail-select" data-field="status">
                            ${Object.entries(statusLabels).map(([value, label]) => 
                                `<option value="${value}" ${resv.status === value ? 'selected' : ''}>${label}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Cliente</label>
                    <div class="fp-detail-value">
                        <div class="fp-form-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;">Nome</label>
                                <input type="text" class="fp-form-control" data-field="first_name" 
                                       value="${this.escapeHtml(resv.customer.first_name || '')}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;">Cognome</label>
                                <input type="text" class="fp-form-control" data-field="last_name" 
                                       value="${this.escapeHtml(resv.customer.last_name || '')}" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;">Email</label>
                            <input type="email" class="fp-form-control" data-field="email" 
                                   value="${this.escapeHtml(resv.customer.email || '')}" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #666;">Telefono</label>
                            <input type="tel" class="fp-form-control" data-field="phone" 
                                   value="${this.escapeHtml(resv.customer.phone || '')}" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>
                
                ${resv.notes ? `
                <div class="fp-detail-group">
                    <label>Note</label>
                    <div class="fp-detail-value">${this.escapeHtml(resv.notes)}</div>
                </div>
                ` : ''}
                
                ${resv.allergies ? `
                <div class="fp-detail-group">
                    <label>Allergie</label>
                    <div class="fp-detail-value fp-detail-value--alert">${this.escapeHtml(resv.allergies)}</div>
                </div>
                ` : ''}
                
                <div class="fp-modal-actions">
                    <button type="button" class="fp-btn fp-btn--primary" data-modal-action="save">
                        Salva Modifiche
                    </button>
                    <button type="button" class="fp-btn fp-btn--secondary" data-modal-action="cancel">
                        Annulla
                    </button>
                    <button type="button" class="fp-btn fp-btn--danger" data-modal-action="delete">
                        Elimina
                    </button>
                </div>
            </div>
        `;
    }

    bindModalActions(resv) {
        this.dom.modalBody.querySelector('[data-modal-action="save"]')?.addEventListener('click', async () => {
            try {
                await this.saveReservation(resv);
            } catch (error) {
                console.error('Error saving reservation:', error);
            }
        });

        this.dom.modalBody.querySelector('[data-modal-action="cancel"]')?.addEventListener('click', () => {
            this.closeModal();
        });

        this.dom.modalBody.querySelector('[data-modal-action="delete"]')?.addEventListener('click', async () => {
            if (confirm('Sei sicuro di voler eliminare questa prenotazione?')) {
                try {
                    await this.deleteReservation(resv.id);
                } catch (error) {
                    console.error('Error deleting reservation:', error);
                }
            }
        });
    }

    async saveReservation(resv) {
        const statusField = this.dom.modalBody.querySelector('[data-field="status"]');
        if (!statusField) {
            console.error('Status field not found');
            return;
        }
        const status = statusField.value;
        const firstName = this.dom.modalBody.querySelector('[data-field="first_name"]')?.value;
        const lastName = this.dom.modalBody.querySelector('[data-field="last_name"]')?.value;
        const email = this.dom.modalBody.querySelector('[data-field="email"]')?.value;
        const phone = this.dom.modalBody.querySelector('[data-field="phone"]')?.value;

        const updates = { status };
        if (firstName !== undefined) updates.first_name = firstName.trim();
        if (lastName !== undefined) updates.last_name = lastName.trim();
        if (email !== undefined) updates.email = email.trim();
        if (phone !== undefined) updates.phone = phone.trim();

        try {
            const response = await fetch(this.buildRestUrl(`agenda/reservations/${resv.id}`), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify(updates),
            });

            if (!response.ok) throw new Error('Failed to update reservation');

            this.closeModal();
            await this.loadReservations();
            await this.loadOverview();
        } catch (error) {
            console.error('[Manager] Error saving reservation:', error);
            alert('Errore nel salvataggio della prenotazione');
        }
    }

    async deleteReservation(id) {
        try {
            const response = await fetch(this.buildRestUrl(`agenda/reservations/${id}`), {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Errore nell\'eliminazione della prenotazione');
            }

            this.closeModal();
            await this.loadReservations();
            await this.loadOverview();
            
            // Mostra messaggio di successo
            alert('Prenotazione eliminata con successo');
        } catch (error) {
            alert('Errore nell\'eliminazione della prenotazione: ' + error.message);
        }
    }

    async openNewReservationModal() {
        // Reset dei dati della nuova prenotazione per evitare che vengano riutilizzati dati vecchi
        this.newReservationData = {};
        
        // Carica il modal con il form di selezione meal/date/party
        this.dom.modalTitle.textContent = 'Nuova Prenotazione';
        this.dom.modalBody.innerHTML = this.renderNewReservationStep1();
        this.dom.modal.style.display = 'flex';

        // Bind eventi step 1
        this.bindNewReservationStep1();
    }

    renderNewReservationStep1() {
        const today = this.formatDate(new Date()); // Timezone locale!
        
        // Genera opzioni meal dinamicamente
        let mealOptions = '<option value="">Seleziona servizio...</option>';
        
        if (this.config.meals && this.config.meals.length > 0) {
            this.config.meals.forEach(meal => {
                const key = meal.key || '';
                const label = meal.label || key;
                mealOptions += `<option value="${this.escapeHtml(key)}">${this.escapeHtml(label)}</option>`;
            });
        } else {
            // Fallback se non ci sono meal configurati
            mealOptions += `
                <option value="lunch">Pranzo</option>
                <option value="dinner">Cena</option>
            `;
        }
        
        return `
            <div class="fp-new-reservation">
                <div class="fp-step-indicator">
                    <div class="fp-step is-active">1. Dettagli</div>
                    <div class="fp-step">2. Orario</div>
                    <div class="fp-step">3. Cliente</div>
                </div>

                <form id="fp-new-reservation-form-step1">
                    <div class="fp-form-group">
                        <label for="new-meal">Servizio *</label>
                        <select id="new-meal" class="fp-form-control" required>
                            ${mealOptions}
                        </select>
                    </div>

                    <div class="fp-form-group">
                        <label for="new-date">Data *</label>
                        <input type="date" id="new-date" class="fp-form-control" min="${today}" required />
                    </div>

                    <div class="fp-form-group">
                        <label for="new-party">Numero Coperti *</label>
                        <input type="number" id="new-party" class="fp-form-control" min="1" max="20" value="2" required />
                    </div>

                    <div class="fp-form-actions">
                        <button type="button" class="fp-btn fp-btn--secondary" data-action="cancel-new">
                            Annulla
                        </button>
                        <button type="submit" class="fp-btn fp-btn--primary">
                            Avanti →
                        </button>
                    </div>
                </form>
            </div>
        `;
    }

    bindNewReservationStep1() {
        const form = document.getElementById('fp-new-reservation-form-step1');
        if (!form) return;

        const mealSelect = document.getElementById('new-meal');
        const dateInput = document.getElementById('new-date');

        // Carica i giorni disponibili quando cambia il meal
        if (mealSelect) {
            mealSelect.addEventListener('change', () => {
                const selectedMeal = mealSelect.value;
                if (selectedMeal) {
                    this.loadAvailableDaysForManager(selectedMeal);
                }
            });
        }

        // Valida la data quando cambia
        if (dateInput) {
            dateInput.addEventListener('change', () => {
                const selectedDate = dateInput.value;
                const selectedMeal = mealSelect ? mealSelect.value : '';
                
                // Se la cache sta ancora caricando, avvisa l'utente
                if (selectedDate && selectedMeal && Object.keys(this.state.availableDaysCache).length === 0 && this.state.availableDaysLoading) {
                    alert('Caricamento giorni disponibili in corso. Attendere...');
                    dateInput.value = '';
                    return;
                }
                
                if (selectedDate && selectedMeal && this.state.availableDaysCache[selectedDate] !== undefined) {
                    const dayInfo = this.state.availableDaysCache[selectedDate];
                    
                    // Determina se il giorno è disponibile
                    let isAvailable = false;
                    if (dayInfo.meals) {
                        // Formato con tutti i meals
                        isAvailable = dayInfo.meals[selectedMeal] === true;
                    } else {
                        // Formato filtrato per singolo meal
                        isAvailable = dayInfo.available === true;
                    }
                    
                    // Controlla se il giorno è disponibile per il meal selezionato
                    if (!isAvailable) {
                        alert('Questo servizio non è disponibile nel giorno selezionato. Scegli un altro giorno.');
                        dateInput.value = '';
                        return;
                    }
                } else if (selectedDate && !selectedMeal) {
                    alert('Seleziona prima un servizio.');
                    dateInput.value = '';
                }
            });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const meal = document.getElementById('new-meal').value;
            const date = document.getElementById('new-date').value;
            const party = parseInt(document.getElementById('new-party').value, 10);

            if (!meal || !date || !party) {
                alert('Compila tutti i campi obbligatori');
                return;
            }

            // Valida finale la disponibilità
            if (this.state.availableDaysCache[date] !== undefined) {
                const dayInfo = this.state.availableDaysCache[date];
                
                // Determina se il giorno è disponibile
                let isAvailable = false;
                if (dayInfo.meals) {
                    // Formato con tutti i meals
                    isAvailable = dayInfo.meals[meal] === true;
                } else {
                    // Formato filtrato per singolo meal
                    isAvailable = dayInfo.available === true;
                }
                
                if (!isAvailable) {
                    alert('Questo servizio non è disponibile nel giorno selezionato.');
                    return;
                }
            }

            // Salva i dati e passa allo step 2
            this.newReservationData = { meal, date, party };
            try {
                await this.showNewReservationStep2();
            } catch (error) {
                console.error('Error loading step 2:', error);
                alert('Errore nel caricamento degli slot disponibili');
            }
        });

        this.dom.modalBody.querySelector('[data-action="cancel-new"]')?.addEventListener('click', () => {
            this.closeModal();
        });
    }

    async showNewReservationStep2() {
        const { meal, date, party } = this.newReservationData;

        // Mostra loading
        this.dom.modalBody.innerHTML = '<div class="fp-modal-loading"><div class="fp-spinner"></div><p>Caricamento slot disponibili...</p></div>';

        try {
            // Chiama endpoint availability
            const availabilityParams = new URLSearchParams({
                date,
                party,
                meal,
            });

            const url = this.buildRestUrl(`availability?${availabilityParams.toString()}`);
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Errore nel caricamento degli slot');
            }

            const data = await response.json();
            const slots = data.slots || [];

            if (slots.length === 0) {
                this.dom.modalBody.innerHTML = `
                    <div class="fp-empty-state">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <h3>Nessuno slot disponibile</h3>
                        <p>Non ci sono slot disponibili per la data e i coperti selezionati.</p>
                        <button type="button" class="fp-btn fp-btn--primary" onclick="window.fpResvManager.openNewReservationModal()">
                            ← Torna Indietro
                        </button>
                    </div>
                `;
                return;
            }

            // Mostra slot disponibili
            this.dom.modalBody.innerHTML = this.renderNewReservationStep2(slots);
            this.bindNewReservationStep2();
        } catch (error) {
            console.error('[Manager] Error loading slots:', error);
            this.dom.modalBody.innerHTML = `
                <div class="fp-error-state">
                    <span class="dashicons dashicons-warning"></span>
                    <h3>Errore</h3>
                    <p>${error.message}</p>
                    <button type="button" class="fp-btn fp-btn--primary" onclick="window.fpResvManager.openNewReservationModal()">
                        ← Torna Indietro
                    </button>
                </div>
            `;
        }
    }

    renderNewReservationStep2(slots) {
        const { meal, date, party } = this.newReservationData;
        
        // Trova il label del meal selezionato
        let mealLabel = meal;
        if (this.config.meals && this.config.meals.length > 0) {
            const mealConfig = this.config.meals.find(m => m.key === meal);
            if (mealConfig) {
                mealLabel = mealConfig.label || meal;
            }
        }
        
        // Filtra solo slot available
        const availableSlots = slots.filter(slot => slot.status === 'available');

        let slotsHtml = '';
        if (availableSlots.length === 0) {
            slotsHtml = '<p class="fp-no-slots">Nessuno slot disponibile per questi criteri.</p>';
        } else {
            slotsHtml = availableSlots.map(slot => {
                const time = slot.label || slot.start; // label è già in formato HH:MM
                const capacity = slot.available_capacity || 0;
                return `
                    <label class="fp-slot-option">
                        <input type="radio" name="slot" value="${slot.start}" required />
                        <span class="fp-slot-time">${this.escapeHtml(time)}</span>
                        <span class="fp-slot-capacity">${capacity} posti</span>
                    </label>
                `;
            }).join('');
        }

        return `
            <div class="fp-new-reservation">
                <div class="fp-step-indicator">
                    <div class="fp-step is-complete">1. Dettagli</div>
                    <div class="fp-step is-active">2. Orario</div>
                    <div class="fp-step">3. Cliente</div>
                </div>

                <div class="fp-selection-summary">
                    <strong>Servizio:</strong> ${this.escapeHtml(mealLabel)} | 
                    <strong>Data:</strong> ${date} | 
                    <strong>Coperti:</strong> ${party}
                </div>

                <form id="fp-new-reservation-form-step2">
                    <div class="fp-form-group">
                        <label>Seleziona Orario *</label>
                        <div class="fp-slots-grid">
                            ${slotsHtml}
                        </div>
                    </div>

                    <div class="fp-form-actions">
                        <button type="button" class="fp-btn fp-btn--secondary" data-action="back-step1">
                            ← Indietro
                        </button>
                        <button type="submit" class="fp-btn fp-btn--primary" ${availableSlots.length === 0 ? 'disabled' : ''}>
                            Avanti →
                        </button>
                    </div>
                </form>
            </div>
        `;
    }

    bindNewReservationStep2() {
        const form = document.getElementById('fp-new-reservation-form-step2');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const selectedSlot = form.querySelector('input[name="slot"]:checked');
            if (!selectedSlot) {
                alert('Seleziona un orario');
                return;
            }

            this.newReservationData.time = selectedSlot.value;
            this.showNewReservationStep3();
        });

        this.dom.modalBody.querySelector('[data-action="back-step1"]')?.addEventListener('click', () => {
            this.openNewReservationModal();
        });
    }

    showNewReservationStep3() {
        // Assicurati che i campi del form siano vuoti quando si mostra lo step 3
        this.dom.modalBody.innerHTML = this.renderNewReservationStep3();
        this.bindNewReservationStep3();
        
        // Reset esplicito dei campi del form per evitare che vengano riutilizzati dati vecchi
        const firstNameField = document.getElementById('new-first-name');
        const lastNameField = document.getElementById('new-last-name');
        const emailField = document.getElementById('new-email');
        const phoneField = document.getElementById('new-phone');
        const notesField = document.getElementById('new-notes');
        const allergiesField = document.getElementById('new-allergies');
        
        if (firstNameField) firstNameField.value = '';
        if (lastNameField) lastNameField.value = '';
        if (emailField) emailField.value = '';
        if (phoneField) phoneField.value = '';
        if (notesField) notesField.value = '';
        if (allergiesField) allergiesField.value = '';
    }

    renderNewReservationStep3() {
        const { meal, date, time, party } = this.newReservationData;
        // time è in formato ISO (2025-10-12T19:00:00+00:00), estraiamo solo l'ora
        const timeFormatted = time.includes('T') 
            ? time.split('T')[1].substring(0, 5) 
            : time.substring(0, 5);

        // Trova il label del meal selezionato
        let mealLabel = meal;
        if (this.config.meals && this.config.meals.length > 0) {
            const mealConfig = this.config.meals.find(m => m.key === meal);
            if (mealConfig) {
                mealLabel = mealConfig.label || meal;
            }
        }

        return `
            <div class="fp-new-reservation">
                <div class="fp-step-indicator">
                    <div class="fp-step is-complete">1. Dettagli</div>
                    <div class="fp-step is-complete">2. Orario</div>
                    <div class="fp-step is-active">3. Cliente</div>
                </div>

                <div class="fp-selection-summary">
                    <strong>Servizio:</strong> ${this.escapeHtml(mealLabel)} | 
                    <strong>Data:</strong> ${date} ${timeFormatted} | 
                    <strong>Coperti:</strong> ${party}
                </div>

                <form id="fp-new-reservation-form-step3">
                    <div class="fp-form-row">
                        <div class="fp-form-group">
                            <label for="new-first-name">Nome</label>
                            <input type="text" id="new-first-name" class="fp-form-control" />
                        </div>
                        <div class="fp-form-group">
                            <label for="new-last-name">Cognome</label>
                            <input type="text" id="new-last-name" class="fp-form-control" />
                        </div>
                    </div>

                    <div class="fp-form-group">
                        <label for="new-email">Email</label>
                        <input type="email" id="new-email" class="fp-form-control" />
                    </div>

                    <div class="fp-form-group">
                        <label for="new-phone">Telefono</label>
                        <input type="tel" id="new-phone" class="fp-form-control" placeholder="+39 ..." />
                    </div>

                    <div class="fp-form-group">
                        <label for="new-notes">Note</label>
                        <textarea id="new-notes" class="fp-form-control" rows="3" placeholder="Note aggiuntive..."></textarea>
                    </div>

                    <div class="fp-form-group">
                        <label for="new-allergies">Allergie/Intolleranze</label>
                        <textarea id="new-allergies" class="fp-form-control" rows="2" placeholder="Eventuali allergie..."></textarea>
                    </div>

                    <div class="fp-form-actions">
                        <button type="button" class="fp-btn fp-btn--secondary" data-action="back-step2">
                            ← Indietro
                        </button>
                        <button type="submit" class="fp-btn fp-btn--primary">
                            <span class="dashicons dashicons-yes"></span>
                            Crea Prenotazione
                        </button>
                    </div>
                </form>
            </div>
        `;
    }

    bindNewReservationStep3() {
        const form = document.getElementById('fp-new-reservation-form-step3');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await this.createNewReservation();
            } catch (error) {
                console.error('Error creating reservation:', error);
            }
        });

        this.dom.modalBody.querySelector('[data-action="back-step2"]')?.addEventListener('click', async () => {
            try {
                await this.showNewReservationStep2();
            } catch (error) {
                console.error('Error going back to step 2:', error);
            }
        });
    }

    async createNewReservation() {
        const { meal, date, time, party } = this.newReservationData;
        
        // Estrai solo l'ora (HH:MM) dal formato ISO se necessario
        let timeFormatted = time;
        if (time.includes('T')) {
            timeFormatted = time.split('T')[1].substring(0, 5);
        }
        
        const formData = {
            date,
            time: timeFormatted,  // Usa solo HH:MM
            party,
            first_name: document.getElementById('new-first-name').value,
            last_name: document.getElementById('new-last-name').value,
            email: document.getElementById('new-email').value,
            phone: document.getElementById('new-phone').value,
            notes: document.getElementById('new-notes').value,
            allergies: document.getElementById('new-allergies').value,
            status: 'confirmed',
            meal,
        };

        // Mostra loading
        this.dom.modalBody.innerHTML = '<div class="fp-modal-loading"><div class="fp-spinner"></div><p>Creazione prenotazione in corso...</p></div>';

        try {
            // USA l'endpoint ADMIN con nonce admin
            const response = await fetch(this.buildRestUrl('agenda/reservations'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce, // Nonce REST admin
                },
                body: JSON.stringify(formData),
            });

            console.log('[Manager] Response status:', response.status);
            console.log('[Manager] Response headers:', response.headers);
            
            if (!response.ok) {
                let errorMessage = 'Errore nella creazione della prenotazione';
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorMessage;
                    console.error('[Manager] Error data:', errorData);
                } catch (e) {
                    console.error('[Manager] Could not parse error response:', e);
                    const text = await response.text();
                    console.error('[Manager] Raw error response:', text);
                }
                throw new Error(errorMessage);
            }

            // Prova a leggere la risposta come testo prima
            const responseText = await response.text();
            console.log('[Manager] Raw response:', responseText);
            
            if (!responseText || responseText.trim() === '') {
                throw new Error('Risposta vuota dal server');
            }
            
            // Ora prova a parsare il JSON
            let result;
            try {
                result = JSON.parse(responseText);
                console.log('[Manager] Parsed result:', result);
            } catch (e) {
                console.error('[Manager] JSON parse error:', e);
                console.error('[Manager] Response was:', responseText.substring(0, 500));
                throw new Error('Risposta non valida dal server: ' + e.message);
            }
            
            // Success!
            this.dom.modalBody.innerHTML = `
                <div class="fp-success-state">
                    <span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 48px;"></span>
                    <h3>Prenotazione Creata!</h3>
                    <p>La prenotazione è stata creata con successo.</p>
                    <button type="button" class="fp-btn fp-btn--primary" onclick="window.fpResvManager.closeModal(); window.fpResvManager.loadReservations(); window.fpResvManager.loadOverview();">
                        Chiudi
                    </button>
                </div>
            `;
        } catch (error) {
            console.error('[Manager] Error creating reservation:', error);
            this.dom.modalBody.innerHTML = `
                <div class="fp-error-state">
                    <span class="dashicons dashicons-warning"></span>
                    <h3>Errore</h3>
                    <p>${error.message}</p>
                    <button type="button" class="fp-btn fp-btn--primary" onclick="window.fpResvManager.showNewReservationStep3()">
                        ← Riprova
                    </button>
                </div>
            `;
        }
    }

    closeModal() {
        this.dom.modal.style.display = 'none';
    }

    // ============================================
    // EXPORT
    // ============================================

    exportReservations() {
        const reservations = this.getFilteredReservations();
        
        if (reservations.length === 0) {
            alert('Nessuna prenotazione da esportare');
            return;
        }

        // Prepara i dati CSV
        const csvHeaders = [
            'ID',
            'Data',
            'Ora',
            'Stato',
            'Coperti',
            'Servizio',
            'Nome',
            'Cognome',
            'Email',
            'Telefono',
            'Note',
            'Allergie'
        ];

        const csvRows = reservations.map(resv => [
            resv.id,
            resv.date,
            resv.time,
            resv.status,
            resv.party,
            resv.meal || '',
            this.escapeCsv(resv.customer.first_name),
            this.escapeCsv(resv.customer.last_name),
            this.escapeCsv(resv.customer.email),
            this.escapeCsv(resv.customer.phone),
            this.escapeCsv(resv.notes),
            this.escapeCsv(resv.allergies)
        ]);

        // Crea il contenuto CSV
        let csvContent = csvHeaders.join(',') + '\n';
        csvRows.forEach(row => {
            csvContent += row.join(',') + '\n';
        });

        // Crea il file e scaricalo
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        const today = new Date();
        const dateStr = this.formatDate(today); // Timezone locale!
        const filename = `prenotazioni_${dateStr}.csv`;
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        URL.revokeObjectURL(url);
    }

    escapeCsv(value) {
        if (!value) return '';
        const str = String(value);
        // Escape virgolette e wrappa in virgolette se contiene virgole, newline o virgolette
        if (str.includes(',') || str.includes('\n') || str.includes('"')) {
            return '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
    }

    // ============================================
    // UI STATE
    // ============================================

    showLoading() {
        if (this.dom.loadingState) this.dom.loadingState.style.display = 'flex';
        if (this.dom.errorState) this.dom.errorState.style.display = 'none';
        if (this.dom.emptyState) this.dom.emptyState.style.display = 'none';
        if (this.dom.viewDay) this.dom.viewDay.style.display = 'none';
        if (this.dom.viewWeek) this.dom.viewWeek.style.display = 'none';
        if (this.dom.viewMonth) this.dom.viewMonth.style.display = 'none';
        if (this.dom.viewList) this.dom.viewList.style.display = 'none';
    }

    hideLoading() {
        if (this.dom.loadingState) this.dom.loadingState.style.display = 'none';
    }

    showError(message) {
        if (this.dom.loadingState) this.dom.loadingState.style.display = 'none';
        if (this.dom.errorState) this.dom.errorState.style.display = 'flex';
        if (this.dom.errorMessage) this.dom.errorMessage.textContent = message;
    }

    // Nota: showEmpty e hideEmpty ora sono gestiti direttamente in renderCurrentView

    // ============================================
    // UTILITIES
    // ============================================

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    normalizeRestRoot(restRoot) {
        if (!restRoot) {
            return '';
        }

        const normalized = String(restRoot).trim();
        if (normalized === '') {
            return '';
        }

        return normalized.replace(/\/+$/, '');
    }

    buildRestUrl(path = '') {
        const base = this.config?.restRoot || '';
        if (base === '') {
            return path;
        }

        if (!path) {
            return base;
        }

        const stringPath = String(path);
        const [rawPath, query = ''] = stringPath.split('?');
        const sanitizedPath = rawPath.replace(/^\/+/, '');
        const url = sanitizedPath ? `${base}/${sanitizedPath}` : base;

        return query !== '' ? `${url}?${query}` : url;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('fp-resv-manager')) {
        window.fpResvManager = new ReservationManager();
    }
});

