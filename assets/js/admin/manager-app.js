/**
 * FP Reservations Manager - Stile The Fork
 * Gestione moderna delle prenotazioni ristorante
 */

class ReservationManager {
    constructor() {
        // Configurazione
        this.config = {
            restRoot: window.fpResvManagerSettings?.restRoot || '/wp-json/fp-resv/v1',
            nonce: window.fpResvManagerSettings?.nonce || '',
            strings: window.fpResvManagerSettings?.strings || {},
            meals: window.fpResvManagerSettings?.meals || [],
        };

        // State management
        this.state = {
            currentDate: new Date(),
            currentView: 'day',
            filters: {
                service: '',
                status: '',
                search: '',
            },
            reservations: [],
            overview: null,
            loading: false,
            error: null,
        };

        // Cache DOM
        this.dom = {};
        
        // Inizializza
        this.init();
    }

    async init() {
        console.log('[Manager] 🚀 Inizializzazione...');
        console.log('[Manager] Config:', this.config);
        console.log('[Manager] REST Root:', this.config.restRoot);
        console.log('[Manager] Nonce:', this.config.nonce ? 'Present' : 'MISSING');
        
        // Verifica che la configurazione sia presente
        if (!this.config.restRoot || !this.config.nonce) {
            console.error('[Manager] ❌ Configuration missing! Check that fpResvManagerSettings is loaded.');
            this.showError('Configurazione mancante. Ricarica la pagina.');
            return;
        }
        
        // Cache elementi DOM
        this.cacheDom();
        
        // Setup event listeners
        this.bindEvents();
        
        // Imposta data corrente
        this.updateDatePicker();
        
        // Carica dati iniziali
        await this.loadOverview();
        await this.loadReservations();
        
        console.log('[Manager] ✅ Inizializzazione completata');
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
            viewList: document.getElementById('fp-view-list'),
            viewMonth: document.getElementById('fp-view-month'),
            
            // States
            loadingState: document.getElementById('fp-loading-state'),
            errorState: document.getElementById('fp-error-state'),
            emptyState: document.getElementById('fp-empty-state'),
            errorMessage: document.getElementById('fp-error-message'),
            
            // Content containers
            timeline: document.getElementById('fp-timeline'),
            reservationsList: document.getElementById('fp-reservations-list'),
            monthCalendar: document.getElementById('fp-month-calendar'),
            
            // Modal
            modal: document.getElementById('fp-reservation-modal'),
            modalTitle: document.getElementById('fp-modal-title'),
            modalBody: document.getElementById('fp-modal-body'),
        };
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

    formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    // ============================================
    // VIEW MANAGEMENT
    // ============================================

    setView(view) {
        console.log('[Manager] Switching view to:', view);
        
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

        // Se passiamo da/a vista mese, ricarica i dati con il range corretto
        if ((previousView === 'month' && view !== 'month') || (previousView !== 'month' && view === 'month')) {
            console.log('[Manager] View change requires data reload');
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
            console.log('[Manager] Loading overview from:', `${this.config.restRoot}/agenda/overview`);
            
            // Aggiungi timeout di 10 secondi
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch(`${this.config.restRoot}/agenda/overview`, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
                signal: controller.signal,
            });
            
            clearTimeout(timeoutId);

            console.log('[Manager] Overview response status:', response.status);

            if (!response.ok) {
                console.error('[Manager] Overview response not OK:', response.status, response.statusText);
                // Non bloccare il caricamento per overview
                return;
            }

            // Check if response has content
            const text = await response.text();
            console.log('[Manager] Overview response text length:', text.length);
            
            if (!text || text.trim() === '') {
                console.warn('[Manager] Overview response is empty');
                return;
            }

            // Parse JSON
            const data = JSON.parse(text);
            console.log('[Manager] Overview data loaded:', data);
            
            this.state.overview = data;
            this.renderStats();
        } catch (error) {
            if (error.name === 'AbortError') {
                console.warn('[Manager] Overview request timeout (10s)');
            } else {
                console.error('[Manager] Error loading overview:', error);
            }
            // Non mostrare errore all'utente per le stats, sono opzionali
        }
    }

    async loadReservations() {
        this.showLoading();

        try {
            const dateStr = this.formatDate(this.state.currentDate);
            
            // Determina il range in base alla vista corrente
            let range = 'day';
            let startDate = dateStr;
            let endDate = dateStr;
            
            if (this.state.currentView === 'month') {
                // Per la vista mese, carica tutto il mese
                const currentDate = this.state.currentDate;
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                
                startDate = this.formatDate(firstDay);
                endDate = this.formatDate(lastDay);
                range = 'month';
            }
            
            const url = `${this.config.restRoot}/agenda?date=${startDate}&range=${range}`;
            
            console.log('[Manager] Loading reservations from:', url);
            console.log('[Manager] Date range:', startDate, 'to', endDate);
            
            // Aggiungi timeout di 15 secondi
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);
            
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
                signal: controller.signal,
            });
            
            clearTimeout(timeoutId);

            console.log('[Manager] Reservations response status:', response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('[Manager] Reservations response error:', response.status, errorText);
                throw new Error(`Errore ${response.status}: ${response.statusText}`);
            }

            // Get response text first
            const text = await response.text();
            console.log('[Manager] Reservations response text length:', text.length);
            console.log('[Manager] Reservations response preview:', text.substring(0, 200));

            if (!text || text.trim() === '') {
                console.warn('[Manager] Reservations response is empty');
                this.state.reservations = [];
                this.state.error = null;
                this.hideLoading();
                this.renderCurrentView();
                return;
            }

            // Parse JSON
            const data = JSON.parse(text);
            console.log('[Manager] Reservations data loaded:', data);
            
            this.state.reservations = data.reservations || [];
            this.state.error = null;

            this.hideLoading();
            this.renderCurrentView();
        } catch (error) {
            console.error('[Manager] Error loading reservations:', error);
            this.hideLoading(); // IMPORTANTE: nascondi il loader anche in caso di errore
            
            if (error.name === 'AbortError') {
                this.showError('Timeout: il server impiega troppo tempo a rispondere.');
            } else {
                this.showError(error.message || 'Errore nel caricamento delle prenotazioni');
            }
        }
    }

    // ============================================
    // RENDERING
    // ============================================

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
        console.log('[Manager] Rendering view:', this.state.currentView);
        const filtered = this.getFilteredReservations();
        console.log('[Manager] Filtered reservations:', filtered.length);

        // Nascondi tutti gli stati
        this.dom.loadingState.style.display = 'none';
        this.dom.errorState.style.display = 'none';
        this.dom.emptyState.style.display = 'none';

        if (filtered.length === 0) {
            // Mostra empty state ma lascia visibile il container della vista corrente
            this.dom.emptyState.style.display = 'flex';
            
            // Nascondi tutte le viste
            this.dom.viewDay.style.display = 'none';
            this.dom.viewList.style.display = 'none';
            this.dom.viewMonth.style.display = 'none';
            return;
        }

        // Nascondi empty state
        this.dom.emptyState.style.display = 'none';

        // Mostra la vista corrente e nascondi le altre
        this.dom.viewDay.style.display = this.state.currentView === 'day' ? 'block' : 'none';
        this.dom.viewList.style.display = this.state.currentView === 'list' ? 'block' : 'none';
        this.dom.viewMonth.style.display = this.state.currentView === 'month' ? 'block' : 'none';

        // Render della vista attiva
        switch (this.state.currentView) {
            case 'day':
                this.renderDayView(filtered);
                break;
            case 'list':
                this.renderListView(filtered);
                break;
            case 'month':
                this.renderMonthView();
                break;
        }

        console.log('[Manager] View rendered successfully');
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
        const todayStr = today.toISOString().split('T')[0];
        
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dateStr = date.toISOString().split('T')[0];
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

    // ============================================
    // FILTERING
    // ============================================

    getFilteredReservations() {
        let filtered = [...this.state.reservations];

        // Filter by service
        if (this.state.filters.service) {
            filtered = filtered.filter(resv => {
                const hour = parseInt(resv.time.split(':')[0]);
                if (this.state.filters.service === 'lunch') {
                    return hour >= 12 && hour < 17;
                } else if (this.state.filters.service === 'dinner') {
                    return hour >= 19 && hour <= 23;
                }
                return true;
            });
        }

        // Filter by status
        if (this.state.filters.status) {
            filtered = filtered.filter(resv => resv.status === this.state.filters.status);
        }

        // Filter by search
        if (this.state.filters.search) {
            const search = this.state.filters.search;
            filtered = filtered.filter(resv => {
                const name = `${resv.customer.first_name} ${resv.customer.last_name}`.toLowerCase();
                const email = resv.customer.email.toLowerCase();
                const phone = resv.customer.phone.toLowerCase();
                return name.includes(search) || email.includes(search) || phone.includes(search);
            });
        }

        return filtered;
    }

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
                const id = parseInt(card.dataset.id);
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
                        <div>${this.escapeHtml(resv.customer.first_name)} ${this.escapeHtml(resv.customer.last_name)}</div>
                        ${resv.customer.email ? `<div class="fp-detail-meta">${this.escapeHtml(resv.customer.email)}</div>` : ''}
                        ${resv.customer.phone ? `<div class="fp-detail-meta">${this.escapeHtml(resv.customer.phone)}</div>` : ''}
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
            await this.saveReservation(resv);
        });

        this.dom.modalBody.querySelector('[data-modal-action="cancel"]')?.addEventListener('click', () => {
            this.closeModal();
        });

        this.dom.modalBody.querySelector('[data-modal-action="delete"]')?.addEventListener('click', async () => {
            if (confirm('Sei sicuro di voler eliminare questa prenotazione?')) {
                await this.deleteReservation(resv.id);
            }
        });
    }

    async saveReservation(resv) {
        const status = this.dom.modalBody.querySelector('[data-field="status"]').value;

        try {
            const response = await fetch(`${this.config.restRoot}/agenda/reservations/${resv.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({ status }),
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
        // TODO: Implement delete endpoint
        console.log('[Manager] Delete reservation:', id);
        alert('Funzione di eliminazione in fase di sviluppo');
    }

    async openNewReservationModal() {
        // Carica il modal con il form di selezione meal/date/party
        this.dom.modalTitle.textContent = 'Nuova Prenotazione';
        this.dom.modalBody.innerHTML = this.renderNewReservationStep1();
        this.dom.modal.style.display = 'flex';

        // Bind eventi step 1
        this.bindNewReservationStep1();
    }

    renderNewReservationStep1() {
        const today = new Date().toISOString().split('T')[0];
        
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

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const meal = document.getElementById('new-meal').value;
            const date = document.getElementById('new-date').value;
            const party = parseInt(document.getElementById('new-party').value);

            if (!meal || !date || !party) {
                alert('Compila tutti i campi obbligatori');
                return;
            }

            // Salva i dati e passa allo step 2
            this.newReservationData = { meal, date, party };
            await this.showNewReservationStep2();
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
            const url = `${this.config.restRoot}/availability?date=${date}&party=${party}&meal=${meal}`;
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
        this.dom.modalBody.innerHTML = this.renderNewReservationStep3();
        this.bindNewReservationStep3();
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
                            <label for="new-first-name">Nome *</label>
                            <input type="text" id="new-first-name" class="fp-form-control" required />
                        </div>
                        <div class="fp-form-group">
                            <label for="new-last-name">Cognome *</label>
                            <input type="text" id="new-last-name" class="fp-form-control" required />
                        </div>
                    </div>

                    <div class="fp-form-group">
                        <label for="new-email">Email *</label>
                        <input type="email" id="new-email" class="fp-form-control" required />
                    </div>

                    <div class="fp-form-group">
                        <label for="new-phone">Telefono *</label>
                        <input type="tel" id="new-phone" class="fp-form-control" placeholder="+39 ..." required />
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
            await this.createNewReservation();
        });

        this.dom.modalBody.querySelector('[data-action="back-step2"]')?.addEventListener('click', async () => {
            await this.showNewReservationStep2();
        });
    }

    async createNewReservation() {
        const { meal, date, time, party } = this.newReservationData;
        
        const formData = {
            date,
            time,
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
            const response = await fetch(`${this.config.restRoot}/reservations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify(formData),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Errore nella creazione della prenotazione');
            }

            const result = await response.json();
            
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
        alert('Funzione di export in fase di sviluppo');
    }

    // ============================================
    // UI STATE
    // ============================================

    showLoading() {
        this.dom.loadingState.style.display = 'flex';
        this.dom.errorState.style.display = 'none';
        this.dom.emptyState.style.display = 'none';
        this.dom.viewDay.style.display = 'none';
        this.dom.viewList.style.display = 'none';
        this.dom.viewCalendar.style.display = 'none';
    }

    hideLoading() {
        this.dom.loadingState.style.display = 'none';
    }

    showError(message) {
        this.dom.loadingState.style.display = 'none';
        this.dom.errorState.style.display = 'flex';
        this.dom.errorMessage.textContent = message;
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
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('fp-resv-manager')) {
        window.fpResvManager = new ReservationManager();
    }
});

