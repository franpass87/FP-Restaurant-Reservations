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
            viewCalendar: document.getElementById('fp-view-calendar'),
            
            // States
            loadingState: document.getElementById('fp-loading-state'),
            errorState: document.getElementById('fp-error-state'),
            emptyState: document.getElementById('fp-empty-state'),
            errorMessage: document.getElementById('fp-error-message'),
            
            // Content containers
            timeline: document.getElementById('fp-timeline'),
            reservationsList: document.getElementById('fp-reservations-list'),
            calendarGrid: document.getElementById('fp-calendar-grid'),
            
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
        this.state.currentView = view;

        // Update buttons
        this.dom.viewBtns.forEach(btn => {
            if (btn.dataset.view === view) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });

        // Show/hide views
        this.dom.viewDay.style.display = view === 'day' ? 'block' : 'none';
        this.dom.viewList.style.display = view === 'list' ? 'block' : 'none';
        this.dom.viewCalendar.style.display = view === 'calendar' ? 'block' : 'none';

        // Render current view
        this.renderCurrentView();
    }

    // ============================================
    // DATA LOADING
    // ============================================

    async loadOverview() {
        try {
            const response = await fetch(`${this.config.restRoot}/agenda/overview`, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            console.log('[Manager] Overview response status:', response.status);

            if (!response.ok) {
                console.error('[Manager] Overview response not OK:', response.status, response.statusText);
                throw new Error(`Failed to load overview: ${response.status} ${response.statusText}`);
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
            console.error('[Manager] Error loading overview:', error);
            // Non mostrare errore all'utente per le stats, sono opzionali
        }
    }

    async loadReservations() {
        this.showLoading();

        try {
            const dateStr = this.formatDate(this.state.currentDate);
            const url = `${this.config.restRoot}/agenda?date=${dateStr}&range=day`;
            
            console.log('[Manager] Loading reservations from:', url);
            
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            console.log('[Manager] Reservations response status:', response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('[Manager] Reservations response error:', response.status, errorText);
                throw new Error(`Failed to load reservations: ${response.status} ${response.statusText}`);
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
            this.showError(error.message);
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
        const filtered = this.getFilteredReservations();

        if (filtered.length === 0) {
            this.showEmpty();
            return;
        }

        this.hideEmpty();

        switch (this.state.currentView) {
            case 'day':
                this.renderDayView(filtered);
                break;
            case 'list':
                this.renderListView(filtered);
                break;
            case 'calendar':
                this.renderCalendarView(filtered);
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

    renderCalendarView(reservations) {
        // Simplified calendar view - can be enhanced later
        this.dom.calendarGrid.innerHTML = '<div class="fp-calendar-placeholder">Vista calendario in fase di sviluppo</div>';
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

    openNewReservationModal() {
        alert('Funzione di creazione nuova prenotazione in fase di sviluppo');
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

    showEmpty() {
        this.dom.emptyState.style.display = 'flex';
        this.dom.viewDay.style.display = 'none';
        this.dom.viewList.style.display = 'none';
        this.dom.viewCalendar.style.display = 'none';
    }

    hideEmpty() {
        this.dom.emptyState.style.display = 'none';
    }

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

