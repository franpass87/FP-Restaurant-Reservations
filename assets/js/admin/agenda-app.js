/**
 * FP Reservations - Agenda Moderna (Stile The Fork)
 * Completamente rifatta da zero - Ottobre 2025
 * 
 * Architettura:
 * - Classe ES6 moderna con state management
 * - Fetch API nativa (zero dipendenze)
 * - Performance ottimizzate con cache DOM
 * - Error handling robusto
 * - UI reattiva e fluida
 */

class ModernAgenda {
    constructor() {
        // Configurazione base
        this.config = {
            restRoot: window.fpResvAgendaSettings?.restRoot || '/wp-json/fp-resv/v1',
            nonce: window.fpResvAgendaSettings?.nonce || '',
        };
        
        // State centralizzato - single source of truth
        this.state = {
            currentDate: new Date(),
            currentView: 'day',
            selectedService: null,
            reservations: [],
            meta: null,
            stats: null,
            loading: false,
            error: null,
        };
        
        // Cache DOM elements
        this.dom = {};
        
        // Bind methods
        this.handleClick = this.handleClick.bind(this);
        
        // Avvia l'applicazione
        this.init();
    }
    
    // ============================================
    // INIZIALIZZAZIONE
    // ============================================
    
    async init() {
        console.log('[Agenda] 🚀 Inizializzazione nuova agenda...');
        
        // Verifica configurazione
        if (!this.config.nonce) {
            console.error('[Agenda] ❌ Configurazione mancante!');
            this.showError('Errore di configurazione. Ricarica la pagina.');
            return;
        }
        
        // Cache elementi DOM
        this.cacheDOM();
        
        // Verifica elementi essenziali
        if (!this.dom.datePicker || !this.dom.container) {
            console.error('[Agenda] ❌ Elementi DOM non trovati!');
            this.showError('Errore di rendering. Ricarica la pagina.');
            return;
        }
        
        // Setup event listeners
        this.setupEvents();
        
        // Imposta data iniziale
        this.dom.datePicker.valueAsDate = this.state.currentDate;
        
        // Carica dati iniziali
        await this.loadData();
        
        console.log('[Agenda] ✅ Inizializzazione completata');
    }
    
    cacheDOM() {
        this.dom = {
            // Controlli
            datePicker: document.querySelector('[data-role="date-picker"]'),
            serviceFilter: document.querySelector('[data-role="service-filter"]'),
            viewButtons: document.querySelectorAll('[data-action="set-view"]'),
            
            // Contenitori viste
            container: document.querySelector('.fp-resv-agenda__container'),
            timelineView: document.querySelector('[data-role="timeline"]'),
            weekView: document.querySelector('[data-role="week-view"]'),
            monthView: document.querySelector('[data-role="month-view"]'),
            listView: document.querySelector('[data-role="list-view"]'),
            
            // Stati UI
            loadingEl: document.querySelector('[data-role="loading"]'),
            emptyEl: document.querySelector('[data-role="empty"]'),
            
            // Summary
            summaryDate: document.querySelector('.fp-resv-agenda__summary-date'),
            summaryStats: document.querySelector('.fp-resv-agenda__summary-stats'),
        };
    }
    
    setupEvents() {
        // Event delegation per click
        document.addEventListener('click', this.handleClick);
        
        // Cambio data
        this.dom.datePicker?.addEventListener('change', () => {
            this.state.currentDate = this.dom.datePicker.valueAsDate || new Date();
            this.loadData();
        });
        
        // Filtro servizio
        this.dom.serviceFilter?.addEventListener('change', () => {
            this.state.selectedService = this.dom.serviceFilter.value || null;
            this.loadData();
        });
    }
    
    handleClick(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        
        const action = btn.dataset.action;
        
        // Router delle azioni
        switch (action) {
            case 'prev-period':
                this.navigatePeriod(-1);
                break;
            case 'next-period':
                this.navigatePeriod(1);
                break;
            case 'today':
                this.goToToday();
                break;
            case 'set-view':
                const view = btn.dataset.view;
                if (view) this.changeView(view);
                break;
            case 'new-reservation':
                this.openNewReservationModal();
                break;
            case 'close-modal':
                this.closeModal(e.target.closest('[data-modal]'));
                break;
            case 'submit-reservation':
                this.createReservation();
                break;
            default:
                // Gestisci azioni dinamiche
                if (action.startsWith('view-reservation-')) {
                    const id = parseInt(action.replace('view-reservation-', ''), 10);
                    this.viewReservation(id);
                }
        }
    }
    
    // ============================================
    // NAVIGAZIONE
    // ============================================
    
    navigatePeriod(direction) {
        const date = new Date(this.state.currentDate);
        
        switch (this.state.currentView) {
            case 'day':
                date.setDate(date.getDate() + direction);
                break;
            case 'week':
                date.setDate(date.getDate() + (direction * 7));
                break;
            case 'month':
                date.setMonth(date.getMonth() + direction);
                break;
            case 'list':
                date.setDate(date.getDate() + (direction * 7));
                break;
        }
        
        this.state.currentDate = date;
        this.dom.datePicker.valueAsDate = date;
        this.loadData();
    }
    
    goToToday() {
        this.state.currentDate = new Date();
        this.dom.datePicker.valueAsDate = this.state.currentDate;
        this.loadData();
    }
    
    changeView(view) {
        console.log('[Agenda] 📊 Cambio vista:', view);
        
        this.state.currentView = view;
        
        // Aggiorna pulsanti vista
        this.dom.viewButtons.forEach(btn => {
            const isActive = btn.dataset.view === view;
            btn.classList.toggle('button-primary', isActive);
            btn.classList.toggle('is-active', isActive);
        });
        
        // Ricarica dati
        this.loadData();
    }
    
    // ============================================
    // CARICAMENTO DATI
    // ============================================
    
    async loadData() {
        console.log('[Agenda] 📥 Caricamento dati...', {
            date: this.formatDate(this.state.currentDate),
            view: this.state.currentView,
        });
        
        this.state.loading = true;
        this.hideAllViews();
        
        try {
            // Calcola range date
            const { startDate, endDate } = this.getDateRange();
            
            // Costruisci parametri
            const params = new URLSearchParams({
                date: startDate,
            });
            
            // Aggiungi range per viste multiple giorni
            if (this.state.currentView === 'week') {
                params.append('range', 'week');
            } else if (this.state.currentView === 'month') {
                params.append('range', 'month');
            } else if (this.state.currentView === 'list') {
                params.append('range', 'week');
            }
            
            // Filtro servizio
            if (this.state.selectedService) {
                params.append('service', this.state.selectedService);
            }
            
            // Chiamata API
            const response = await this.api(`agenda?${params}`);
            
            // Gestisci risposta
            this.processResponse(response);
            
            // Renderizza
            this.render();
            
            console.log('[Agenda] ✅ Dati caricati:', this.state.reservations.length, 'prenotazioni');
            
        } catch (error) {
            console.error('[Agenda] ❌ Errore caricamento:', error);
            this.state.error = error.message;
            this.showError(error.message);
        } finally {
            this.state.loading = false;
        }
    }
    
    processResponse(data) {
        // Supporta sia vecchio formato (array) che nuovo formato (oggetto strutturato)
        if (Array.isArray(data)) {
            // Formato vecchio: array diretto
            this.state.reservations = data;
            this.state.meta = null;
            this.state.stats = null;
        } else if (data && typeof data === 'object') {
            // Formato nuovo: oggetto strutturato The Fork
            this.state.reservations = data.reservations || [];
            this.state.meta = data.meta || null;
            this.state.stats = data.stats || null;
            
            // Dati pre-organizzati per performance
            if (data.data) {
                this.state.organizedData = data.data;
            }
        } else {
            // Formato non valido
            this.state.reservations = [];
            this.state.meta = null;
            this.state.stats = null;
        }
        
        this.state.error = null;
    }
    
    getDateRange() {
        const start = new Date(this.state.currentDate);
        let end = new Date(this.state.currentDate);
        
        switch (this.state.currentView) {
            case 'week':
                // Lunedì - Domenica
                const dayOfWeek = start.getDay();
                const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
                start.setDate(start.getDate() + diff);
                end = new Date(start);
                end.setDate(end.getDate() + 6);
                break;
                
            case 'month':
                start.setDate(1);
                end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
                break;
                
            case 'list':
                end.setDate(end.getDate() + 6);
                break;
        }
        
        return {
            startDate: this.formatDate(start),
            endDate: this.formatDate(end),
        };
    }
    
    // ============================================
    // RENDERING
    // ============================================
    
    render() {
        console.log('[Agenda] 🎨 Rendering vista:', this.state.currentView);
        
        // Aggiorna summary
        this.updateSummary();
        
        // Verifica se ci sono prenotazioni
        if (this.state.reservations.length === 0) {
            this.showEmpty();
            return;
        }
        
        // Nascondi empty state
        if (this.dom.emptyEl) {
            this.dom.emptyEl.hidden = true;
        }
        
        // Renderizza vista specifica
        switch (this.state.currentView) {
            case 'day':
                this.renderDayView();
                break;
            case 'week':
                this.renderWeekView();
                break;
            case 'month':
                this.renderMonthView();
                break;
            case 'list':
                this.renderListView();
                break;
        }
        
        console.log('[Agenda] ✅ Rendering completato');
    }
    
    renderDayView() {
        if (!this.dom.timelineView) return;
        
        // Mostra solo timeline
        this.hideAllViews();
        this.dom.timelineView.hidden = false;
        
        // Raggruppa per slot orari
        const slots = this.groupByTimeSlot(this.state.reservations);
        
        // Usa dati pre-organizzati se disponibili
        const slotsData = this.state.organizedData?.slots || 
                         Object.keys(slots).sort().map(time => ({
                             time,
                             reservations: slots[time],
                             total_guests: slots[time].reduce((sum, r) => sum + (r.party || 0), 0)
                         }));
        
        // Genera HTML
        const html = slotsData.length > 0 
            ? slotsData.map(slot => this.renderSlot(slot)).join('')
            : '<div class="fp-resv-timeline__empty">Nessuna prenotazione</div>';
        
        this.dom.timelineView.innerHTML = html;
    }
    
    renderSlot(slot) {
        const cards = slot.reservations.map(r => this.renderCard(r)).join('');
        
        return `
            <div class="fp-resv-timeline__slot">
                <div class="fp-resv-timeline__time">${slot.time}</div>
                <div class="fp-resv-timeline__reservations">${cards}</div>
            </div>
        `;
    }
    
    renderCard(resv) {
        const statusLabels = {
            pending: 'In attesa',
            confirmed: 'Confermata',
            visited: 'Servita',
            no_show: 'No-show',
            cancelled: 'Annullata',
        };
        
        const customer = resv.customer || {};
        const name = this.getGuestName(resv);
        
        return `
            <div class="fp-resv-card" data-status="${resv.status}" data-action="view-reservation-${resv.id}">
                <div class="fp-resv-card__header">
                    <strong class="fp-resv-card__name">${this.escapeHtml(name)}</strong>
                    <span class="fp-resv-card__badge">${statusLabels[resv.status] || resv.status}</span>
                </div>
                <div class="fp-resv-card__body">
                    <div class="fp-resv-card__info">
                        <span class="dashicons dashicons-groups"></span>
                        <span>${resv.party} ${resv.party === 1 ? 'coperto' : 'coperti'}</span>
                    </div>
                    ${customer.phone ? `
                    <div class="fp-resv-card__info">
                        <span class="dashicons dashicons-phone"></span>
                        <span>${this.escapeHtml(customer.phone)}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    renderWeekView() {
        if (!this.dom.weekView) return;
        
        this.hideAllViews();
        this.dom.weekView.hidden = false;
        
        // Raggruppa per giorni
        const days = this.groupByDays(this.state.reservations, 7);
        
        const html = `
            <div class="fp-resv-week-grid">
                ${days.map(day => `
                    <div class="fp-resv-week-day">
                        <div class="fp-resv-week-day__header">
                            <div class="fp-resv-week-day__name">${this.getDayName(day.date)}</div>
                            <div class="fp-resv-week-day__number">${day.date.getDate()}</div>
                        </div>
                        <div class="fp-resv-week-day__body">
                            ${day.reservations.length > 0
                                ? day.reservations.map(r => `
                                    <div class="fp-resv-week-item" data-status="${r.status}" data-action="view-reservation-${r.id}">
                                        <div class="fp-resv-week-item__time">${this.formatTime(r.time)}</div>
                                        <div class="fp-resv-week-item__guest">${this.escapeHtml(this.getGuestName(r))}</div>
                                        <div class="fp-resv-week-item__party">${r.party} cop.</div>
                                    </div>
                                `).join('')
                                : '<div class="fp-resv-week-day__empty">Nessuna prenotazione</div>'
                            }
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        this.dom.weekView.innerHTML = html;
    }
    
    renderMonthView() {
        if (!this.dom.monthView) return;
        
        this.hideAllViews();
        this.dom.monthView.hidden = false;
        
        const year = this.state.currentDate.getFullYear();
        const month = this.state.currentDate.getMonth();
        
        // Calcola calendario
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        
        // Padding per iniziare lunedì
        const startDay = firstDay.getDay();
        const gridStart = startDay === 0 ? -6 : 1 - startDay;
        
        // Raggruppa prenotazioni per data
        const resvByDate = this.groupReservationsByDate();
        
        // Genera giorni
        const calendarDays = [];
        for (let i = gridStart; i <= daysInMonth; i++) {
            if (i < 1) {
                calendarDays.push({ date: null, reservations: [] });
            } else {
                const date = new Date(year, month, i);
                const dateStr = this.formatDate(date);
                const reservations = resvByDate[dateStr] || [];
                calendarDays.push({ date, dateStr, reservations });
            }
        }
        
        const html = `
            <div class="fp-resv-month-header">
                <h3>${this.getMonthYear(this.state.currentDate)}</h3>
            </div>
            <div class="fp-resv-month-calendar">
                <div class="fp-resv-month-weekdays">
                    <div>Lun</div><div>Mar</div><div>Mer</div><div>Gio</div><div>Ven</div><div>Sab</div><div>Dom</div>
                </div>
                <div class="fp-resv-month-grid">
                    ${calendarDays.map(day => {
                        if (!day.date) {
                            return '<div class="fp-resv-month-day fp-resv-month-day--empty"></div>';
                        }
                        const isToday = this.formatDate(day.date) === this.formatDate(new Date());
                        return `
                            <div class="fp-resv-month-day ${isToday ? 'fp-resv-month-day--today' : ''}">
                                <div class="fp-resv-month-day__number">${day.date.getDate()}</div>
                                ${day.reservations.length > 0 ? `
                                    <div class="fp-resv-month-day__count">${day.reservations.length}</div>
                                    <div class="fp-resv-month-day__items">
                                        ${day.reservations.slice(0, 2).map(r => `
                                            <div class="fp-resv-month-item" data-action="view-reservation-${r.id}">
                                                ${this.formatTime(r.time)} ${this.escapeHtml(this.getGuestName(r))}
                                            </div>
                                        `).join('')}
                                        ${day.reservations.length > 2 ? `
                                            <div class="fp-resv-month-more">+${day.reservations.length - 2}</div>
                                        ` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
        
        this.dom.monthView.innerHTML = html;
    }
    
    renderListView() {
        if (!this.dom.listView) return;
        
        this.hideAllViews();
        this.dom.listView.hidden = false;
        
        // Ordina per data/ora
        const sorted = [...this.state.reservations].sort((a, b) => {
            const dateTimeA = `${a.date} ${a.time}`;
            const dateTimeB = `${b.date} ${b.time}`;
            return dateTimeA.localeCompare(dateTimeB);
        });
        
        const statusLabels = {
            pending: 'In attesa',
            confirmed: 'Confermata',
            visited: 'Servita',
            no_show: 'No-show',
            cancelled: 'Annullata',
        };
        
        const html = `
            <table class="fp-resv-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Ora</th>
                        <th>Cliente</th>
                        <th>Coperti</th>
                        <th>Telefono</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    ${sorted.map(r => {
                        const customer = r.customer || {};
                        return `
                            <tr data-action="view-reservation-${r.id}" style="cursor: pointer;">
                                <td>${this.formatDateShort(r.date)}</td>
                                <td><strong>${this.formatTime(r.time)}</strong></td>
                                <td>${this.escapeHtml(this.getGuestName(r))}</td>
                                <td>${r.party}</td>
                                <td>${this.escapeHtml(customer.phone || '-')}</td>
                                <td><span class="fp-resv-badge fp-resv-badge--${r.status}">${statusLabels[r.status] || r.status}</span></td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
        
        this.dom.listView.innerHTML = html;
    }
    
    updateSummary() {
        if (this.dom.summaryDate) {
            this.dom.summaryDate.textContent = this.formatDateLong(this.state.currentDate);
        }
        
        if (this.dom.summaryStats) {
            // Usa stats pre-calcolate se disponibili
            const total = this.state.stats?.total_reservations || this.state.reservations.length;
            const confirmed = this.state.stats?.by_status?.confirmed || 
                            this.state.reservations.filter(r => r.status === 'confirmed').length;
            const guests = this.state.stats?.total_guests || 
                          this.state.reservations.reduce((sum, r) => sum + (r.party || 0), 0);
            
            this.dom.summaryStats.textContent = `${total} prenotazioni • ${confirmed} confermate • ${guests} coperti`;
        }
    }
    
    // ============================================
    // STATI UI
    // ============================================
    
    hideAllViews() {
        if (this.dom.timelineView) this.dom.timelineView.hidden = true;
        if (this.dom.weekView) this.dom.weekView.hidden = true;
        if (this.dom.monthView) this.dom.monthView.hidden = true;
        if (this.dom.listView) this.dom.listView.hidden = true;
        if (this.dom.emptyEl) this.dom.emptyEl.hidden = true;
        if (this.dom.loadingEl) this.dom.loadingEl.hidden = true;
    }
    
    showEmpty() {
        this.hideAllViews();
        if (this.dom.emptyEl) {
            this.dom.emptyEl.hidden = false;
        }
    }
    
    showError(message) {
        this.hideAllViews();
        if (this.dom.emptyEl) {
            this.dom.emptyEl.hidden = false;
            const p = this.dom.emptyEl.querySelector('p');
            if (p) {
                p.textContent = `Errore: ${message}`;
                p.style.color = '#d63638';
            }
        }
    }
    
    // ============================================
    // MODALI & AZIONI
    // ============================================
    
    openNewReservationModal() {
        const modal = document.querySelector('[data-modal="new-reservation"]');
        if (!modal) return;
        
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            const dateInput = form.querySelector('[name="date"]');
            const timeInput = form.querySelector('[name="time"]');
            if (dateInput) dateInput.value = this.formatDate(this.state.currentDate);
            if (timeInput) timeInput.value = '19:30';
        }
        
        this.openModal(modal);
    }
    
    async createReservation() {
        const form = document.querySelector('[data-form="new-reservation"]');
        if (!form || !form.checkValidity()) {
            form?.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Combina date e time
        data.slot_start = `${data.date} ${data.time}`;
        data.status = 'pending';
        
        try {
            await this.api('agenda/reservations', { method: 'POST', body: data });
            this.closeModal(document.querySelector('[data-modal="new-reservation"]'));
            this.showNotification('Prenotazione creata con successo!');
            await this.loadData();
        } catch (error) {
            alert(`Errore: ${error.message}`);
        }
    }
    
    viewReservation(id) {
        const resv = this.state.reservations.find(r => r.id === id);
        if (!resv) return;
        
        const modal = document.querySelector('[data-modal="reservation-details"]');
        if (!modal) return;
        
        const content = modal.querySelector('[data-role="details-content"]');
        if (content) {
            content.innerHTML = this.renderDetails(resv);
        }
        
        this.openModal(modal);
    }
    
    renderDetails(resv) {
        const customer = resv.customer || {};
        const name = this.getGuestName(resv);
        
        return `
            <div class="fp-resv-details">
                <div class="fp-resv-details__section">
                    <h3>Informazioni prenotazione</h3>
                    <dl class="fp-resv-details__grid">
                        <dt>Cliente</dt>
                        <dd>${this.escapeHtml(name)}</dd>
                        
                        <dt>Data e ora</dt>
                        <dd>${this.formatDateLong(new Date(resv.date))} - ${this.formatTime(resv.time)}</dd>
                        
                        <dt>Coperti</dt>
                        <dd>${resv.party}</dd>
                        
                        ${customer.email ? `
                        <dt>Email</dt>
                        <dd>${this.escapeHtml(customer.email)}</dd>
                        ` : ''}
                        
                        ${customer.phone ? `
                        <dt>Telefono</dt>
                        <dd>${this.escapeHtml(customer.phone)}</dd>
                        ` : ''}
                        
                        ${resv.notes ? `
                        <dt>Note</dt>
                        <dd>${this.escapeHtml(resv.notes)}</dd>
                        ` : ''}
                    </dl>
                </div>
            </div>
        `;
    }
    
    openModal(modal) {
        if (!modal) return;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    
    closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    
    showNotification(message) {
        const notice = document.createElement('div');
        notice.className = 'notice notice-success is-dismissible';
        notice.style.cssText = 'position: fixed; top: 32px; right: 20px; z-index: 999999; min-width: 300px;';
        notice.innerHTML = `<p><strong>${this.escapeHtml(message)}</strong></p>`;
        
        document.body.appendChild(notice);
        
        setTimeout(() => {
            notice.style.transition = 'opacity 0.3s';
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 300);
        }, 3000);
    }
    
    // ============================================
    // API
    // ============================================
    
    async api(endpoint, options = {}) {
        const url = `${this.config.restRoot}/${endpoint.replace(/^\//, '')}`;
        
        const config = {
            method: options.method || 'GET',
            headers: {
                'X-WP-Nonce': this.config.nonce,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };
        
        if (options.body) {
            config.body = JSON.stringify(options.body);
        }
        
        const response = await fetch(url, config);
        
        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'Errore di rete' }));
            throw new Error(error.message || `HTTP ${response.status}`);
        }
        
        if (response.status === 204) {
            return null;
        }
        
        const text = await response.text();
        
        // Risposta vuota = nessuna prenotazione
        if (!text || text.trim() === '') {
            return {
                meta: { range: 'day', start_date: this.formatDate(this.state.currentDate), end_date: this.formatDate(this.state.currentDate) },
                stats: { total_reservations: 0, total_guests: 0, by_status: {}, confirmed_percentage: 0 },
                data: { slots: [], timeline: [], days: [] },
                reservations: [],
            };
        }
        
        return JSON.parse(text);
    }
    
    // ============================================
    // UTILITY
    // ============================================
    
    groupByTimeSlot(reservations) {
        const slots = {};
        reservations.forEach(r => {
            const time = this.formatTime(r.time);
            if (!slots[time]) slots[time] = [];
            slots[time].push(r);
        });
        return slots;
    }
    
    groupByDays(reservations, numDays) {
        const { startDate } = this.getDateRange();
        const start = new Date(startDate);
        const days = [];
        
        for (let i = 0; i < numDays; i++) {
            const date = new Date(start);
            date.setDate(start.getDate() + i);
            const dateStr = this.formatDate(date);
            const dayReservations = reservations.filter(r => r.date === dateStr);
            days.push({ date, dateStr, reservations: dayReservations });
        }
        
        return days;
    }
    
    groupReservationsByDate() {
        const map = {};
        this.state.reservations.forEach(r => {
            if (!map[r.date]) map[r.date] = [];
            map[r.date].push(r);
        });
        return map;
    }
    
    formatDate(date) {
        const d = date instanceof Date ? date : new Date(date);
        return d.toISOString().split('T')[0];
    }
    
    formatDateLong(date) {
        const d = date instanceof Date ? date : new Date(date);
        return d.toLocaleDateString('it-IT', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    
    formatDateShort(dateStr) {
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
    }
    
    formatTime(timeStr) {
        if (!timeStr) return '';
        return timeStr.substring(0, 5);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    getGuestName(resv) {
        const customer = resv.customer || {};
        const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ');
        return name || customer.email || 'Cliente';
    }
    
    getDayName(date) {
        const days = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
        return days[date.getDay()];
    }
    
    getMonthYear(date) {
        const months = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
                       'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        return `${months[date.getMonth()]} ${date.getFullYear()}`;
    }
}

// Avvia l'applicazione
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new ModernAgenda());
} else {
    new ModernAgenda();
}
