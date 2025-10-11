/**
 * FP Reservations - Agenda Stile The Fork
 * Versione completamente rifatta da zero - Ottobre 2025
 */

class AgendaApp {
    constructor() {
        // Configurazione
        this.settings = window.fpResvAgendaSettings || {};
        this.restRoot = (this.settings.restRoot || '/wp-json/fp-resv/v1').replace(/\/$/, '');
        this.nonce = this.settings.nonce || '';
        
        // Stato dell'applicazione
        this.state = {
            currentDate: new Date(),
            currentView: 'day',
            currentService: '',
            reservations: [],
            loading: false,
            error: null
        };
        
        // Elementi DOM (saranno inizializzati in init)
        this.elements = {};
        
        // Inizializza l'app
        this.init();
    }
    
    /**
     * Inizializza l'applicazione
     */
    init() {
        console.log('[Agenda] Inizializzazione...', {
            settings: this.settings,
            restRoot: this.restRoot,
            hasNonce: !!this.nonce
        });
        
        // Verifica settings
        if (!this.restRoot || !this.nonce) {
            console.error('[Agenda] Errore: Configurazione mancante!', {
                hasRestRoot: !!this.restRoot,
                hasNonce: !!this.nonce,
                settings: this.settings
            });
            this.showError('Errore di configurazione. Ricarica la pagina.');
            return;
        }
        
        // Cache elementi DOM
        this.cacheElements();
        
        // Verifica che gli elementi esistano
        if (!this.elements.datePicker) {
            console.error('[Agenda] Errore: Elementi DOM non trovati!');
            this.showError('Errore: Elementi pagina non trovati. Ricarica la pagina.');
            return;
        }
        
        console.log('[Agenda] Elementi DOM caricati:', {
            datePicker: !!this.elements.datePicker,
            serviceFilter: !!this.elements.serviceFilter,
            timeline: !!this.elements.timelineEl,
            viewButtons: this.elements.viewButtons.length
        });
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Imposta data iniziale
        this.elements.datePicker.value = this.formatDate(this.state.currentDate);
        
        // Inizializza vista di default (day) con pulsante attivo
        this.setView('day');
        
        console.log('[Agenda] Inizializzazione completata');
    }
    
    /**
     * Cache elementi DOM per performance
     */
    cacheElements() {
        this.elements = {
            datePicker: document.querySelector('[data-role="date-picker"]'),
            serviceFilter: document.querySelector('[data-role="service-filter"]'),
            summaryEl: document.querySelector('[data-role="summary"]'),
            loadingEl: document.querySelector('[data-role="loading"]'),
            emptyEl: document.querySelector('[data-role="empty"]'),
            timelineEl: document.querySelector('[data-role="timeline"]'),
            weekViewEl: document.querySelector('[data-role="week-view"]'),
            monthViewEl: document.querySelector('[data-role="month-view"]'),
            listViewEl: document.querySelector('[data-role="list-view"]'),
            viewButtons: document.querySelectorAll('[data-action="set-view"]')
        };
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Event delegation per i click
        document.addEventListener('click', (e) => this.handleClick(e));
        
        // Cambio data
        this.elements.datePicker?.addEventListener('change', () => {
            const [year, month, day] = this.elements.datePicker.value.split('-').map(Number);
            this.state.currentDate = new Date(year, month - 1, day);
            this.loadReservations();
        });
        
        // Cambio servizio
        this.elements.serviceFilter?.addEventListener('change', () => {
            this.state.currentService = this.elements.serviceFilter.value || '';
            this.loadReservations();
        });
    }
    
    /**
     * Handler principale per i click
     */
    handleClick(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.getAttribute('data-action');
        
        switch (action) {
            case 'prev-period':
                this.navigatePeriod(-1);
                break;
            case 'today':
                this.navigateToToday();
                break;
            case 'next-period':
                this.navigatePeriod(1);
                break;
            case 'set-view':
                const view = target.getAttribute('data-view');
                if (view) this.setView(view);
                break;
            case 'new-reservation':
                this.openNewReservationModal();
                break;
            case 'close-modal':
                this.closeModal('[data-modal="new-reservation"]');
                break;
            case 'submit-reservation':
                this.submitReservation();
                break;
            case 'close-details':
                this.closeModal('[data-modal="reservation-details"]');
                break;
            case 'confirm-reservation':
                this.updateReservationStatus('confirmed');
                break;
            default:
                // Gestisci quick party
                if (target.hasAttribute('data-quickparty')) {
                    const party = target.getAttribute('data-quickparty');
                    const input = document.querySelector('[data-field="party"]');
                    if (input) input.value = party;
                }
                // Gestisci view reservation
                else if (action.startsWith('view-reservation-')) {
                    const id = parseInt(action.replace('view-reservation-', ''));
                    this.viewReservationDetails(id);
                }
        }
    }
    
    /**
     * Naviga tra periodi (avanti/indietro)
     */
    navigatePeriod(offset) {
        const date = new Date(this.state.currentDate);
        
        switch (this.state.currentView) {
            case 'day':
                date.setDate(date.getDate() + offset);
                break;
            case 'week':
                date.setDate(date.getDate() + (offset * 7));
                break;
            case 'month':
                date.setMonth(date.getMonth() + offset);
                break;
            case 'list':
                date.setDate(date.getDate() + (offset * 7));
                break;
        }
        
        this.state.currentDate = date;
        this.elements.datePicker.value = this.formatDate(date);
        this.loadReservations();
    }
    
    /**
     * Torna ad oggi
     */
    navigateToToday() {
        this.state.currentDate = new Date();
        this.elements.datePicker.value = this.formatDate(this.state.currentDate);
        this.loadReservations();
    }
    
    /**
     * Cambia vista (giorno/settimana/mese/lista)
     */
    setView(view) {
        console.log('[Agenda] Cambio vista:', view);
        
        this.state.currentView = view;
        
        // Aggiorna pulsanti
        this.elements.viewButtons.forEach(btn => {
            const isActive = btn.getAttribute('data-view') === view;
            btn.classList.toggle('button-primary', isActive);
            btn.classList.toggle('is-active', isActive);
            if (!isActive) {
                btn.classList.add('button');
            }
        });
        
        // Ricarica dati
        this.loadReservations();
    }
    
    /**
     * Carica prenotazioni dal server
     */
    async loadReservations() {
        console.log('[Agenda] Caricamento prenotazioni...', {
            date: this.state.currentDate,
            view: this.state.currentView,
            service: this.state.currentService
        });
        
        // Nascondi loading per evitare caricamento infinito
        // Mostriamo direttamente i dati o empty state
        this.hideLoading();
        
        const { startDate, endDate } = this.getDateRange();
        const params = new URLSearchParams({
            date: startDate,
            ...(this.state.currentView === 'week' && { range: 'week' }),
            ...(this.state.currentView === 'month' && { range: 'month' }),
            ...(this.state.currentView === 'list' && { range: 'week' }),
            ...(this.state.currentService && { service: this.state.currentService })
        });
        
        console.log('[Agenda] Parametri richiesta:', params.toString());
        
        try {
            const data = await this.apiRequest(`agenda?${params}`);
            
            // Log dettagliato della risposta per debugging
            console.log('[Agenda] ✓ Risposta ricevuta');
            console.log('[Agenda] Tipo risposta:', typeof data);
            console.log('[Agenda] È array?', Array.isArray(data));
            console.log('[Agenda] È null?', data === null);
            console.log('[Agenda] È undefined?', data === undefined);
            
            // Log struttura oggetto se è un oggetto
            if (data && typeof data === 'object' && !Array.isArray(data)) {
                console.log('[Agenda] Chiavi oggetto:', Object.keys(data));
                console.log('[Agenda] Ha reservations?', 'reservations' in data);
                console.log('[Agenda] Ha data?', 'data' in data);
                console.log('[Agenda] Ha meta?', 'meta' in data);
                console.log('[Agenda] Ha stats?', 'stats' in data);
            }
            
            // Log completo (può essere grande)
            console.log('[Agenda] Risposta completa:', data);
            
            // Gestisci diversi formati di risposta
            let reservations = [];
            
            if (Array.isArray(data)) {
                // Risposta diretta come array (vecchia API)
                console.log('[Agenda] ✓ Formato: Array diretto');
                reservations = data;
            } else if (data && typeof data === 'object') {
                console.log('[Agenda] ✓ Formato: Oggetto strutturato');
                
                // Nuova struttura The Fork - salva meta, stats e data se disponibili
                if (data.meta) {
                    this.state.meta = data.meta;
                    console.log('[Agenda] Meta ricevuti dal backend:', data.meta);
                }
                if (data.stats) {
                    this.state.stats = data.stats;
                    console.log('[Agenda] Stats ricevuti dal backend:', data.stats);
                }
                if (data.data) {
                    this.state.organizedData = data.data;
                    console.log('[Agenda] Dati organizzati ricevuti dal backend:', data.data);
                }
                
                // Estrai array di prenotazioni
                if (Array.isArray(data.reservations)) {
                    console.log('[Agenda] ✓ Trovato data.reservations (length: ' + data.reservations.length + ')');
                    reservations = data.reservations;
                } else if (Array.isArray(data.data)) {
                    console.log('[Agenda] ✓ Trovato data.data come array (length: ' + data.data.length + ')');
                    reservations = data.data;
                } else if (Array.isArray(data.items)) {
                    console.log('[Agenda] ✓ Trovato data.items (length: ' + data.items.length + ')');
                    reservations = data.items;
                } else {
                    console.error('[Agenda] ✗ ERRORE: Risposta è un oggetto ma senza array di prenotazioni');
                    console.error('[Agenda] Struttura ricevuta:', JSON.stringify(data, null, 2));
                    throw new Error('Risposta API non valida: oggetto senza array di prenotazioni');
                }
            } else if (data === null || data === undefined) {
                // Risposta vuota - consideriamo come array vuoto
                console.warn('[Agenda] ⚠ Risposta API vuota, assumo nessuna prenotazione');
                reservations = [];
            } else {
                console.error('[Agenda] ✗ ERRORE: Tipo di dato non supportato');
                console.error('[Agenda] Tipo ricevuto:', typeof data);
                console.error('[Agenda] Valore:', data);
                throw new Error(`Risposta API non valida: ricevuto ${typeof data} invece di array o oggetto`);
            }
            
            this.state.reservations = reservations;
            this.state.error = null;
            
            console.log(`[Agenda] ✓ Caricate ${reservations.length} prenotazioni con successo`);
            
            // Renderizza
            this.render();
            
            return reservations;
            
        } catch (error) {
            console.error('[Agenda] ✗ Errore nel caricamento:', error);
            this.state.error = error.message;
            this.state.reservations = [];
            this.showError(error.message);
            throw error;
        }
    }
    
    /**
     * Calcola range date in base alla vista
     */
    getDateRange() {
        const start = new Date(this.state.currentDate);
        let end = new Date(this.state.currentDate);
        
        switch (this.state.currentView) {
            case 'week':
                // Inizia da lunedì
                const day = start.getDay();
                const diff = day === 0 ? -6 : 1 - day;
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
            endDate: this.formatDate(end)
        };
    }
    
    /**
     * Renderizza la vista corrente
     */
    render() {
        console.log('[Agenda] Rendering vista:', this.state.currentView, 'con', this.state.reservations.length, 'prenotazioni');
        
        this.hideLoading();
        
        if (this.state.reservations.length === 0) {
            this.showEmpty();
            return;
        }
        
        // Nascondi empty state
        if (this.elements.emptyEl) {
            this.elements.emptyEl.hidden = true;
        }
        
        // Renderizza vista corrente
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
            default:
                console.warn('[Agenda] Vista non riconosciuta:', this.state.currentView);
                this.renderDayView();
        }
        
        // Aggiorna summary
        this.updateSummary();
        
        console.log('[Agenda] ✓ Rendering completato');
    }
    
    /**
     * Renderizza vista giornaliera
     */
    renderDayView() {
        if (!this.elements.timelineEl) return;
        
        // Mostra solo timeline, nascondi altre viste
        this.elements.timelineEl.hidden = false;
        if (this.elements.weekViewEl) this.elements.weekViewEl.hidden = true;
        if (this.elements.monthViewEl) this.elements.monthViewEl.hidden = true;
        if (this.elements.listViewEl) this.elements.listViewEl.hidden = true;
        
        // Usa slot pre-organizzati dal backend se disponibili, altrimenti raggruppa client-side
        let slotsData;
        if (this.state.organizedData?.slots) {
            // Dati dal backend (The Fork style)
            slotsData = this.state.organizedData.slots;
            console.log('[Agenda] Usando slots dal backend:', slotsData.length);
        } else if (this.state.organizedData?.timeline) {
            // Alias alternativo
            slotsData = this.state.organizedData.timeline;
            console.log('[Agenda] Usando timeline dal backend:', slotsData.length);
        } else {
            // Fallback: raggruppa client-side
            const groupedSlots = this.groupByTimeSlot(this.state.reservations);
            slotsData = Object.keys(groupedSlots).sort().map(time => ({
                time: time,
                reservations: groupedSlots[time],
                total_guests: groupedSlots[time].reduce((sum, r) => sum + (r.party || 0), 0)
            }));
            console.log('[Agenda] Raggruppando slots client-side');
        }
        
        // Genera HTML
        const html = slotsData.map(slot => {
            const time = slot.time;
            const reservations = slot.reservations || [];
            const totalGuests = slot.total_guests || reservations.reduce((sum, r) => sum + (r.party || 0), 0);
            
            return `
                <div class="fp-resv-timeline__slot">
                    <div class="fp-resv-timeline__time">${time}</div>
                    <div class="fp-resv-timeline__reservations">
                        ${reservations.map(r => this.renderReservationCard(r)).join('')}
                    </div>
                </div>
            `;
        }).join('');
        
        this.elements.timelineEl.innerHTML = html;
    }
    
    /**
     * Renderizza vista settimanale
     */
    renderWeekView() {
        if (!this.elements.weekViewEl) return;
        
        // Mostra solo week view
        if (this.elements.timelineEl) this.elements.timelineEl.hidden = true;
        this.elements.weekViewEl.hidden = false;
        if (this.elements.monthViewEl) this.elements.monthViewEl.hidden = true;
        if (this.elements.listViewEl) this.elements.listViewEl.hidden = true;
        
        let days = [];
        
        // Usa giorni pre-organizzati dal backend se disponibili, altrimenti organizza client-side
        if (this.state.organizedData?.days && Array.isArray(this.state.organizedData.days)) {
            // Dati dal backend (The Fork style)
            days = this.state.organizedData.days.map(dayData => ({
                date: new Date(dayData.date),
                dateStr: dayData.date,
                reservations: dayData.reservations || []
            }));
            console.log('[Agenda] Usando giorni dal backend per vista settimanale:', days.length);
        } else {
            // Fallback: organizza client-side
            const { startDate } = this.getDateRange();
            const weekStart = new Date(startDate);
            
            for (let i = 0; i < 7; i++) {
                const day = new Date(weekStart);
                day.setDate(weekStart.getDate() + i);
                const dayStr = this.formatDate(day);
                const dayReservations = this.state.reservations.filter(r => r.date === dayStr);
                
                days.push({
                    date: day,
                    dateStr: dayStr,
                    reservations: dayReservations
                });
            }
            console.log('[Agenda] Organizzando giorni client-side per vista settimanale');
        }
        
        const html = `
            <div class="fp-resv-week__grid">
                ${days.map(day => `
                    <div class="fp-resv-week__day">
                        <div class="fp-resv-week__header">
                            <div class="fp-resv-week__day-name">${this.getDayName(day.date)}</div>
                            <div class="fp-resv-week__day-number">${day.date.getDate()}</div>
                        </div>
                        <div class="fp-resv-week__content">
                            ${day.reservations.length ? 
                                day.reservations.map(r => `
                                    <div class="fp-resv-week__item" data-status="${r.status}" data-action="view-reservation-${r.id}">
                                        <div class="fp-resv-week__time">${this.formatTime(r.time)}</div>
                                        <div class="fp-resv-week__guest">${this.escapeHtml(this.getGuestName(r))}</div>
                                        <div class="fp-resv-week__party">${r.party} ${r.party === 1 ? 'coperto' : 'coperti'}</div>
                                    </div>
                                `).join('') :
                                '<div class="fp-resv-week__empty">Nessuna prenotazione</div>'
                            }
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        this.elements.weekViewEl.innerHTML = html;
    }
    
    /**
     * Renderizza vista mensile
     */
    renderMonthView() {
        if (!this.elements.monthViewEl) return;
        
        // Mostra solo month view
        if (this.elements.timelineEl) this.elements.timelineEl.hidden = true;
        if (this.elements.weekViewEl) this.elements.weekViewEl.hidden = true;
        this.elements.monthViewEl.hidden = false;
        if (this.elements.listViewEl) this.elements.listViewEl.hidden = true;
        
        const year = this.state.currentDate.getFullYear();
        const month = this.state.currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        
        // Calcola griglia calendario
        const startDay = firstDay.getDay();
        const daysInMonth = lastDay.getDate();
        const gridStart = startDay === 0 ? -6 : 1 - startDay;
        
        // Crea mappa dei giorni dal backend se disponibili
        let daysMap = new Map();
        if (this.state.organizedData?.days && Array.isArray(this.state.organizedData.days)) {
            this.state.organizedData.days.forEach(dayData => {
                daysMap.set(dayData.date, dayData.reservations || []);
            });
            console.log('[Agenda] Usando giorni dal backend per vista mensile:', daysMap.size);
        }
        
        const days = [];
        for (let i = gridStart; i <= daysInMonth; i++) {
            if (i < 1) {
                days.push({ date: null, reservations: [] });
            } else {
                const day = new Date(year, month, i);
                const dayStr = this.formatDate(day);
                
                // Usa dati dal backend se disponibili, altrimenti filtra client-side
                const dayReservations = daysMap.has(dayStr) 
                    ? daysMap.get(dayStr)
                    : this.state.reservations.filter(r => r.date === dayStr);
                
                days.push({ date: day, dateStr: dayStr, reservations: dayReservations });
            }
        }
        
        if (!daysMap.size) {
            console.log('[Agenda] Organizzando giorni client-side per vista mensile');
        }
        
        const html = `
            <div class="fp-resv-month__header">
                <div class="fp-resv-month__title">${this.getMonthYear(this.state.currentDate)}</div>
            </div>
            <div class="fp-resv-month__calendar">
                <div class="fp-resv-month__weekdays">
                    <div>Lun</div>
                    <div>Mar</div>
                    <div>Mer</div>
                    <div>Gio</div>
                    <div>Ven</div>
                    <div>Sab</div>
                    <div>Dom</div>
                </div>
                <div class="fp-resv-month__grid">
                    ${days.map(day => {
                        if (!day.date) {
                            return '<div class="fp-resv-month__day fp-resv-month__day--empty"></div>';
                        }
                        const isToday = this.formatDate(day.date) === this.formatDate(new Date());
                        return `
                            <div class="fp-resv-month__day ${isToday ? 'fp-resv-month__day--today' : ''}">
                                <div class="fp-resv-month__day-number">${day.date.getDate()}</div>
                                ${day.reservations.length ? `
                                    <div class="fp-resv-month__count">${day.reservations.length}</div>
                                    <div class="fp-resv-month__items">
                                        ${day.reservations.slice(0, 3).map(r => `
                                            <div class="fp-resv-month__item" data-status="${r.status}" data-action="view-reservation-${r.id}">
                                                ${this.formatTime(r.time)} ${this.escapeHtml(this.getGuestName(r))}
                                            </div>
                                        `).join('')}
                                        ${day.reservations.length > 3 ? `<div class="fp-resv-month__more">+${day.reservations.length - 3}</div>` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
        
        this.elements.monthViewEl.innerHTML = html;
    }
    
    /**
     * Renderizza vista lista
     */
    renderListView() {
        if (!this.elements.listViewEl) return;
        
        // Mostra solo list view
        if (this.elements.timelineEl) this.elements.timelineEl.hidden = true;
        if (this.elements.weekViewEl) this.elements.weekViewEl.hidden = true;
        if (this.elements.monthViewEl) this.elements.monthViewEl.hidden = true;
        this.elements.listViewEl.hidden = false;
        
        // Ordina prenotazioni
        const sorted = [...this.state.reservations].sort((a, b) => {
            const dateA = a.date + ' ' + (a.time || '00:00');
            const dateB = b.date + ' ' + (b.time || '00:00');
            return dateA.localeCompare(dateB);
        });
        
        const statusLabels = {
            'pending': 'In attesa',
            'confirmed': 'Confermata',
            'visited': 'Servita',
            'no_show': 'No-show',
            'cancelled': 'Annullata'
        };
        
        const html = `
            <div class="fp-resv-list__table-wrapper">
                <table class="fp-resv-list__table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Ora</th>
                            <th>Cliente</th>
                            <th>Coperti</th>
                            <th>Telefono</th>
                            <th>Stato</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${sorted.map(r => {
                            const customer = r.customer || {};
                            return `
                                <tr class="fp-resv-list__row" data-status="${r.status}" data-action="view-reservation-${r.id}">
                                    <td>${this.formatDateShort(r.date)}</td>
                                    <td><strong>${this.formatTime(r.time)}</strong></td>
                                    <td>${this.escapeHtml(this.getGuestName(r))}</td>
                                    <td>${r.party}</td>
                                    <td>${this.escapeHtml(customer.phone || '-')}</td>
                                    <td><span class="fp-resv-list__badge fp-resv-list__badge--${r.status}">${statusLabels[r.status] || r.status}</span></td>
                                    <td><span class="fp-resv-list__notes">${this.escapeHtml((r.notes || '').substring(0, 40))}${r.notes && r.notes.length > 40 ? '...' : ''}</span></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
        
        this.elements.listViewEl.innerHTML = html;
    }
    
    /**
     * Renderizza card prenotazione
     */
    renderReservationCard(resv) {
        const statusLabels = {
            'pending': 'In attesa',
            'confirmed': 'Confermata',
            'visited': 'Servita',
            'no_show': 'No-show',
            'cancelled': 'Annullata'
        };
        
        const customer = resv.customer || {};
        const name = this.getGuestName(resv);
        const phone = customer.phone || '';
        const notes = resv.notes || '';
        
        return `
            <div class="fp-resv-reservation-card" data-status="${resv.status}" data-action="view-reservation-${resv.id}">
                <div class="fp-resv-reservation-card__header">
                    <div class="fp-resv-reservation-card__name">${this.escapeHtml(name)}</div>
                    <div class="fp-resv-reservation-card__badge">${statusLabels[resv.status]}</div>
                </div>
                <div class="fp-resv-reservation-card__info">
                    <div class="fp-resv-reservation-card__info-item">
                        <span class="dashicons dashicons-groups"></span>
                        <span>${resv.party} ${resv.party === 1 ? 'coperto' : 'coperti'}</span>
                    </div>
                    ${phone ? `
                    <div class="fp-resv-reservation-card__info-item">
                        <span class="dashicons dashicons-phone"></span>
                        <span>${this.escapeHtml(phone)}</span>
                    </div>
                    ` : ''}
                    ${notes ? `
                    <div class="fp-resv-reservation-card__info-item">
                        <span class="dashicons dashicons-info"></span>
                        <span>${this.escapeHtml(notes.substring(0, 30))}${notes.length > 30 ? '...' : ''}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    /**
     * Aggiorna summary
     */
    updateSummary() {
        if (!this.elements.summaryEl) return;
        
        const dateEl = this.elements.summaryEl.querySelector('.fp-resv-agenda__summary-date');
        const statsEl = this.elements.summaryEl.querySelector('.fp-resv-agenda__summary-stats');
        
        if (dateEl) {
            dateEl.textContent = this.formatDateLong(this.state.currentDate);
        }
        
        if (statsEl) {
            // Usa stats pre-calcolate dal backend se disponibili, altrimenti calcola client-side
            let total, confirmed, totalGuests;
            
            if (this.state.stats) {
                // Dati dal backend (The Fork style)
                total = this.state.stats.total_reservations || 0;
                confirmed = this.state.stats.by_status?.confirmed || 0;
                totalGuests = this.state.stats.total_guests || 0;
                console.log('[Agenda] Usando stats dal backend:', this.state.stats);
            } else {
                // Calcolo client-side (fallback)
                total = this.state.reservations.length;
                confirmed = this.state.reservations.filter(r => r.status === 'confirmed').length;
                totalGuests = this.state.reservations.reduce((sum, r) => sum + (r.party || 0), 0);
                console.log('[Agenda] Calcolando stats client-side');
            }
            
            statsEl.textContent = `${total} prenotazioni • ${confirmed} confermate • ${totalGuests} coperti`;
        }
    }
    
    /**
     * Mostra loading (NON USATO - manteniamo per compatibilità)
     */
    showLoading() {
        // Non mostriamo mai il loading per evitare caricamento infinito
    }
    
    /**
     * Nascondi loading
     */
    hideLoading() {
        if (this.elements.loadingEl) {
            this.elements.loadingEl.hidden = true;
            this.elements.loadingEl.style.display = 'none';
        }
    }
    
    /**
     * Mostra empty state
     */
    showEmpty(message = null) {
        console.log('[Agenda] Mostrando empty state:', message);
        
        this.hideLoading();
        
        if (this.elements.emptyEl) {
            this.elements.emptyEl.hidden = false;
            
            const messageEl = this.elements.emptyEl.querySelector('p');
            if (messageEl) {
                if (message) {
                    messageEl.textContent = message;
                    messageEl.style.color = '#d63638';
                    messageEl.style.fontWeight = 'bold';
                } else {
                    // Ripristina il messaggio di default
                    messageEl.textContent = 'Non ci sono prenotazioni per questo periodo';
                    messageEl.style.color = '';
                    messageEl.style.fontWeight = '';
                }
            }
        }
        
        // Nascondi tutte le viste
        if (this.elements.timelineEl) this.elements.timelineEl.hidden = true;
        if (this.elements.weekViewEl) this.elements.weekViewEl.hidden = true;
        if (this.elements.monthViewEl) this.elements.monthViewEl.hidden = true;
        if (this.elements.listViewEl) this.elements.listViewEl.hidden = true;
    }
    
    /**
     * Mostra errore
     */
    showError(message) {
        this.showEmpty(`Errore: ${message}`);
    }
    
    /**
     * Mostra notifica di successo
     */
    showSuccessNotification(message) {
        // Crea elemento notifica
        const notification = document.createElement('div');
        notification.className = 'notice notice-success is-dismissible';
        notification.style.cssText = 'position: fixed; top: 32px; right: 20px; z-index: 999999; min-width: 300px;';
        notification.innerHTML = `<p><strong>${this.escapeHtml(message)}</strong></p>`;
        
        // Aggiungi al DOM
        document.body.appendChild(notification);
        
        // Auto-rimuovi dopo 3 secondi
        setTimeout(() => {
            notification.style.transition = 'opacity 0.3s';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
        
        // Rendi dismissible
        notification.addEventListener('click', (e) => {
            if (e.target.classList.contains('notice-dismiss')) {
                notification.remove();
            }
        });
    }
    
    /**
     * Apri modal nuova prenotazione
     */
    openNewReservationModal() {
        const modal = document.querySelector('[data-modal="new-reservation"]');
        if (!modal) return;
        
        const form = modal.querySelector('[data-form="new-reservation"]');
        if (form) {
            form.reset();
            
            const dateInput = form.querySelector('[data-field="date"]');
            const timeInput = form.querySelector('[data-field="time"]');
            
            if (dateInput) dateInput.value = this.formatDate(this.state.currentDate);
            if (timeInput) timeInput.value = '19:30';
            
            const errorEl = form.querySelector('[data-role="form-error"]');
            if (errorEl) errorEl.hidden = true;
        }
        
        this.openModal(modal);
    }
    
    /**
     * Submit nuova prenotazione
     */
    async submitReservation() {
        const form = document.querySelector('[data-form="new-reservation"]');
        if (!form || !form.checkValidity()) {
            form?.reportValidity();
            return;
        }
        
        // Disabilita il pulsante per evitare doppi invii
        const submitBtn = document.querySelector('[data-action="submit-reservation"]');
        const originalText = submitBtn?.textContent;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creazione in corso...';
        }
        
        // Nascondi errori precedenti
        const errorEl = document.querySelector('[data-role="form-error"]');
        if (errorEl) errorEl.hidden = true;
        
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
        // Combina date e time
        if (data.date && data.time) {
            data.slot_start = `${data.date} ${data.time}`;
        }
        
        data.status = 'pending';
        
        console.log('[Agenda] Creazione prenotazione con dati:', data);
        
        try {
            const response = await this.apiRequest('agenda/reservations', { method: 'POST', data });
            console.log('[Agenda] Prenotazione creata con successo:', response);
            
            // Chiudi modale
            this.closeModal('[data-modal="new-reservation"]');
            
            // Mostra notifica di successo
            this.showSuccessNotification('Prenotazione creata con successo!');
            
            // Ricarica le prenotazioni
            await this.loadReservations();
            
            console.log('[Agenda] Agenda aggiornata dopo creazione');
        } catch (error) {
            console.error('[Agenda] Errore creazione prenotazione:', error);
            
            if (errorEl) {
                errorEl.textContent = error.message || 'Impossibile creare la prenotazione';
                errorEl.hidden = false;
            }
        } finally {
            // Riabilita il pulsante
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }
    
    /**
     * Visualizza dettagli prenotazione
     */
    viewReservationDetails(id) {
        const resv = this.state.reservations.find(r => r.id === id);
        if (!resv) return;
        
        this.currentReservationId = id;
        
        const modal = document.querySelector('[data-modal="reservation-details"]');
        if (!modal) return;
        
        const contentEl = modal.querySelector('[data-role="details-content"]');
        if (contentEl) {
            contentEl.innerHTML = this.renderDetails(resv);
        }
        
        this.openModal(modal);
    }
    
    /**
     * Renderizza dettagli prenotazione
     */
    renderDetails(resv) {
        const customer = resv.customer || {};
        const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ') || 'N/D';
        const statusLabels = {
            'pending': 'In attesa',
            'confirmed': 'Confermata',
            'visited': 'Servita',
            'no_show': 'No-show',
            'cancelled': 'Annullata'
        };
        
        return `
            <div class="fp-resv-details__section">
                <h3 class="fp-resv-details__section-title">Informazioni prenotazione</h3>
                <div class="fp-resv-details__grid">
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Nome cliente</div>
                        <div class="fp-resv-details__value">${this.escapeHtml(name)}</div>
                    </div>
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Stato</div>
                        <div class="fp-resv-details__value">${statusLabels[resv.status] || resv.status}</div>
                    </div>
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Data e ora</div>
                        <div class="fp-resv-details__value">${this.formatDateTimeLong(resv.slot_start || resv.date)}</div>
                    </div>
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Numero coperti</div>
                        <div class="fp-resv-details__value">${resv.party || 0}</div>
                    </div>
                    ${customer.email ? `
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Email</div>
                        <div class="fp-resv-details__value">${this.escapeHtml(customer.email)}</div>
                    </div>
                    ` : ''}
                    ${customer.phone ? `
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Telefono</div>
                        <div class="fp-resv-details__value">${this.escapeHtml(customer.phone)}</div>
                    </div>
                    ` : ''}
                </div>
                ${resv.notes ? `
                <div class="fp-resv-details__item" style="margin-top: 16px;">
                    <div class="fp-resv-details__label">Note</div>
                    <div class="fp-resv-details__value">${this.escapeHtml(resv.notes)}</div>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    /**
     * Aggiorna stato prenotazione
     */
    async updateReservationStatus(status) {
        if (!this.currentReservationId) {
            console.error('Nessuna prenotazione selezionata');
            return;
        }
        
        try {
            await this.apiRequest(`agenda/reservations/${this.currentReservationId}`, {
                method: 'PATCH',
                data: { status }
            });
            
            this.closeModal('[data-modal="reservation-details"]');
            this.currentReservationId = null;
            this.loadReservations();
        } catch (error) {
            console.error('Errore aggiornamento stato:', error);
            alert(error.message || 'Impossibile aggiornare lo stato');
        }
    }
    
    /**
     * Apri modal
     */
    openModal(modal) {
        if (!modal) return;
        
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        
        setTimeout(() => {
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) firstInput.focus();
        }, 0);
        
        document.body.style.overflow = 'hidden';
    }
    
    /**
     * Chiudi modal
     */
    closeModal(selector) {
        const modal = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!modal) return;
        
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        
        if (modal.getAttribute('data-modal') === 'reservation-details') {
            this.currentReservationId = null;
        }
        
        document.body.style.overflow = '';
    }
    
    /**
     * Effettua richiesta API
     */
    async apiRequest(path, options = {}) {
        const url = path.startsWith('http') ? path : `${this.restRoot}/${path.replace(/^\//, '')}`;
        
        console.log(`[API] ${options.method || 'GET'} ${url}`, options.data ? { data: options.data } : '');
        
        const config = {
            method: options.method || 'GET',
            headers: {
                'X-WP-Nonce': this.nonce,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };
        
        if (options.data) {
            config.body = JSON.stringify(options.data);
            console.log('[API] Request body:', config.body);
            if (!config.method || config.method === 'GET') {
                config.method = 'POST';
            }
        }
        
        try {
            const response = await fetch(url, config);
            
            console.log(`[API] Status: ${response.status} ${response.statusText}`);
            
            if (!response.ok) {
                let errorMsg = `Richiesta fallita con status ${response.status}`;
                
                try {
                    const payload = await response.json();
                    console.error('[API] Error payload:', payload);
                    errorMsg = payload.message || payload.error || errorMsg;
                    
                    // Se c'è un errore di permessi, logga più dettagli
                    if (response.status === 403) {
                        console.error('[API] Errore permessi! Nonce:', this.nonce);
                        console.error('[API] Headers:', Object.fromEntries(response.headers.entries()));
                    }
                } catch (e) {
                    console.error('[API] Non posso fare parse dell\'errore:', e);
                }
                
                throw new Error(errorMsg);
            }
            
            if (response.status === 204) {
                console.log('[API] ✓ 204 No Content');
                return null;
            }
            
            const text = await response.text();
            console.log('[API] Response length:', text.length, 'bytes');
            console.log('[API] Response preview (first 200 chars):', text.substring(0, 200));
            
            if (!text || text.trim() === '') {
                console.log('[API] ✓ Empty response');
                return null;
            }
            
            try {
                const data = JSON.parse(text);
                console.log('[API] ✓ JSON parsed, type:', Array.isArray(data) ? 'array' : typeof data);
                if (data && typeof data === 'object' && !Array.isArray(data)) {
                    console.log('[API] ✓ Object keys:', Object.keys(data).slice(0, 10).join(', '));
                }
                return data;
            } catch (e) {
                console.error('[API] Errore parsing JSON:', e);
                console.error('[API] Response preview:', text.substring(0, 500));
                throw new Error('Risposta JSON non valida dal server');
            }
        } catch (error) {
            // Se è un errore di rete
            if (error instanceof TypeError && error.message.includes('fetch')) {
                console.error('[API] Errore di rete:', error);
                throw new Error('Impossibile contattare il server. Controlla la connessione.');
            }
            
            // Rilancia l'errore
            throw error;
        }
    }
    
    // ========== UTILITY FUNCTIONS ==========
    
    groupByTimeSlot(reservations) {
        const slots = {};
        reservations.forEach(r => {
            const time = this.formatTime(r.time || '12:00');
            if (!slots[time]) slots[time] = [];
            slots[time].push(r);
        });
        return slots;
    }
    
    formatDate(date) {
        const d = date instanceof Date ? date : new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    formatDateLong(date) {
        const d = date instanceof Date ? date : new Date(date);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return d.toLocaleDateString('it-IT', options);
    }
    
    formatTime(timeStr) {
        if (!timeStr) return '';
        return timeStr.substring(0, 5);
    }
    
    formatDateTimeLong(dateTimeStr) {
        if (!dateTimeStr) return 'N/D';
        const dt = new Date(dateTimeStr);
        const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit' };
        return `${dt.toLocaleDateString('it-IT', dateOptions)} alle ${dt.toLocaleTimeString('it-IT', timeOptions)}`;
    }
    
    formatDateShort(dateStr) {
        if (!dateStr) return '';
        const [year, month, day] = dateStr.split('-');
        return `${day}/${month}/${year}`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
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

// Inizializza l'app quando il DOM è pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new AgendaApp());
} else {
    new AgendaApp();
}
