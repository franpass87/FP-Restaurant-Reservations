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
            debugMode: window.fpResvManagerSettings?.debugMode || false,
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
        
        // Setup touch optimizations
        this.setupTouchOptimizations();
        
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
            
            // States
            loadingState: document.getElementById('fp-loading-state'),
            errorState: document.getElementById('fp-error-state'),
            emptyState: document.getElementById('fp-empty-state'),
            errorMessage: document.getElementById('fp-error-message'),
            
            // Content containers
            timeline: document.getElementById('fp-timeline'),
            weekCalendar: document.getElementById('fp-week-calendar'),
            monthCalendar: document.getElementById('fp-month-calendar'),
            
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

    setupTouchOptimizations() {
        // Rileva se siamo su dispositivo touch
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        if (!isTouchDevice) {
            return; // Niente da fare su desktop
        }

        // Smooth scroll per stats cards su mobile
        const statsContainer = document.querySelector('.fp-manager-stats');
        if (statsContainer && window.innerWidth <= 768) {
            // Aggiungi indicatore di scroll per le stats cards
            this.addScrollIndicator(statsContainer);
        }

        // Swipe gesture per navigation date su mobile
        if (window.innerWidth <= 768) {
            this.setupSwipeGestures();
        }

        // Previeni zoom accidentale su double-tap per elementi specifici
        const preventZoomElements = document.querySelectorAll(
            '.fp-btn, .fp-reservation-card, .fp-calendar-day, .fp-view-btn'
        );
        
        preventZoomElements.forEach(element => {
            element.addEventListener('touchend', (e) => {
                // Previeni comportamento di default solo per questi elementi
                const now = Date.now();
                if (element._lastTap && now - element._lastTap < 300) {
                    e.preventDefault();
                }
                element._lastTap = now;
            }, { passive: false });
        });

        // Feedback tattile su azioni importanti (se disponibile)
        if ('vibrate' in navigator) {
            document.querySelectorAll('[data-action="new-reservation"], [data-modal-action="save"]')
                .forEach(btn => {
                    btn.addEventListener('click', () => {
                        navigator.vibrate(10); // Vibrazione leggera 10ms
                    });
                });
        }

        // Pull-to-refresh custom per reload dati
        this.setupPullToRefresh();
    }

    addScrollIndicator(container) {
        // Aggiunge indicatore visivo per scroll orizzontale
        const updateScrollIndicator = () => {
            const scrollLeft = container.scrollLeft;
            const scrollWidth = container.scrollWidth;
            const clientWidth = container.clientWidth;
            
            // Aggiungi/rimuovi classe per nascondere frecce
            if (scrollLeft <= 0) {
                container.classList.add('at-start');
            } else {
                container.classList.remove('at-start');
            }
            
            if (scrollLeft >= scrollWidth - clientWidth - 10) {
                container.classList.add('at-end');
            } else {
                container.classList.remove('at-end');
            }
        };

        container.addEventListener('scroll', updateScrollIndicator, { passive: true });
        updateScrollIndicator(); // Inizializza
    }

    setupSwipeGestures() {
        // Implementa swipe left/right per cambiare data
        const toolbar = document.querySelector('.fp-manager-toolbar');
        if (!toolbar) return;

        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;
        const minSwipeDistance = 50;

        toolbar.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        toolbar.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            
            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;
            
            // Verifica che sia uno swipe orizzontale (non verticale)
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance) {
                if (deltaX > 0) {
                    // Swipe right -> giorno precedente
                    this.navigateDate(-1);
                } else {
                    // Swipe left -> giorno successivo
                    this.navigateDate(1);
                }
            }
        }, { passive: true });
    }

    setupPullToRefresh() {
        // Pull-to-refresh personalizzato per ricaricare dati
        const mainContent = document.querySelector('.fp-manager-main');
        if (!mainContent) return;

        let touchStartY = 0;
        let isPulling = false;
        let refreshThreshold = 80;

        mainContent.addEventListener('touchstart', (e) => {
            if (mainContent.scrollTop === 0) {
                touchStartY = e.touches[0].clientY;
                isPulling = true;
            }
        }, { passive: true });

        mainContent.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            
            const touchY = e.touches[0].clientY;
            const pullDistance = touchY - touchStartY;

            if (pullDistance > 0 && mainContent.scrollTop === 0) {
                // Mostra indicatore di pull
                if (pullDistance > refreshThreshold) {
                    mainContent.classList.add('pull-to-refresh-ready');
                }
            }
        }, { passive: true });

        mainContent.addEventListener('touchend', async (e) => {
            if (!isPulling) return;

            const touchEndY = e.changedTouches[0].clientY;
            const pullDistance = touchEndY - touchStartY;

            if (pullDistance > refreshThreshold && mainContent.scrollTop === 0) {
                // Trigger refresh
                mainContent.classList.add('pull-to-refresh-loading');
                await this.loadReservations();
                mainContent.classList.remove('pull-to-refresh-loading');
            }

            mainContent.classList.remove('pull-to-refresh-ready');
            isPulling = false;
        }, { passive: true });
    }

    // ============================================
    // DATE NAVIGATION
    // ============================================

    navigateDate(direction) {
        // Navigazione intelligente basata sulla vista corrente
        switch (this.state.currentView) {
            case 'month':
                this.navigateMonth(direction);
                break;
            case 'week':
                this.navigateWeek(direction);
                break;
            case 'day':
            case 'list':
            default:
                // Naviga per giorni
                const newDate = new Date(this.state.currentDate);
                newDate.setDate(newDate.getDate() + direction);
                this.setDate(newDate);
                break;
        }
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
        // NON usare toISOString() perché converte in UTC causando problemi timezone!
        // Usa invece getFullYear, getMonth, getDate per ottenere la data locale
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // ============================================
    // VIEW MANAGEMENT
    // ============================================

    setView(view) {
        console.log('[Manager] 🔄 SET VIEW:', view, 'da', this.state.currentView);
        
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

        // Aggiorna il testo del pulsante "Oggi" in base alla vista
        this.updateTodayButtonText();
        
        // Se passiamo tra viste che richiedono range diversi, ricarica i dati
        const viewsRequiringReload = ['week', 'month'];
        const previousNeedsRange = viewsRequiringReload.includes(previousView);
        const currentNeedsRange = viewsRequiringReload.includes(view);
        
        console.log('[Manager] Previous needs range:', previousNeedsRange, '| Current needs range:', currentNeedsRange);
        
        if (previousNeedsRange !== currentNeedsRange || (previousNeedsRange && currentNeedsRange && previousView !== view)) {
            console.log('[Manager] 🔄 Ricarico prenotazioni per cambio vista');
            this.loadReservations();
        } else {
            console.log('[Manager] 🎨 Solo re-render, nessun reload');
            // Altrimenti, semplicemente re-render
            this.renderCurrentView();
        }
    }
    
    updateTodayButtonText() {
        const todayButtons = document.querySelectorAll('[data-action="today"]');
        const labels = {
            month: 'Questo Mese',
            week: 'Questa Settimana',
            day: 'Oggi',
            list: 'Oggi'
        };
        
        const label = labels[this.state.currentView] || 'Oggi';
        todayButtons.forEach(btn => {
            btn.textContent = label;
        });
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
            let endDate = dateStr;
            
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
                endDate = this.formatDate(sunday);
                range = 'week';
            } else if (this.state.currentView === 'month') {
                // Per la vista mese, carica tutto il mese
                const currentDate = this.state.currentDate;
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                
                startDate = this.formatDate(firstDay);
                endDate = this.formatDate(lastDay);
                range = 'month';
                
                console.log('[Manager] 📅 LOAD vista MESE:', year, month + 1);
                console.log('[Manager] Range richiesto:', startDate, '->', endDate);
            }
            
            const params = new URLSearchParams({
                date: startDate,
                range,
            });

            const url = this.buildRestUrl(`agenda?${params.toString()}`);
            
            console.log('[Manager] 🌐 Chiamata API:', url);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);
            
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
                signal: controller.signal,
            });
            
            clearTimeout(timeoutId);

            // Debug panel (se abilitato nelle impostazioni)
            this.showDebugPanel({
                url: url,
                status: response.status,
                statusText: response.statusText,
                headers: {
                    'X-FP-Debug': response.headers.get('X-FP-Debug'),
                    'Content-Type': response.headers.get('Content-Type'),
                    'Content-Length': response.headers.get('Content-Length')
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('[Manager] Reservations response error:', response.status, errorText);
                this.showDebugPanel({
                    error: true,
                    status: response.status,
                    errorText: errorText,
                    url: url
                });
                throw new Error(`Errore ${response.status}: ${response.statusText}`);
            }

            const text = await response.text();
            if (!text || text.trim() === '') {
                this.showDebugPanel({
                    warning: true,
                    message: 'Risposta vuota dal server',
                    url: url,
                    bodyLength: 0
                });
                this.state.reservations = [];
                this.state.error = null;
                this.hideLoading();
                this.renderCurrentView();
                return;
            }

            const data = JSON.parse(text);
            this.state.reservations = data.reservations || [];
            this.state.error = null;
            
            console.log('[Manager] ✅ Prenotazioni ricevute dal backend:', this.state.reservations.length);
            console.log('[Manager] Meta:', data.meta);
            console.log('[Manager] Stats:', data.stats);
            
            if (this.state.reservations.length > 0) {
                console.log('[Manager] Prima prenotazione:', this.state.reservations[0]);
                console.log('[Manager] Ultima prenotazione:', this.state.reservations[this.state.reservations.length - 1]);
            }
            
            // Aggiorna statistiche nelle card
            this.updateStats(data.stats, data.meta);
            
            // Debug panel con successo
            this.showDebugPanel({
                success: true,
                reservationsCount: this.state.reservations.length,
                bodyLength: text.length,
                url: url
            });
            
            // Log per debugging (rimovere dopo verifica)
            if (this.state.reservations.length === 0) {
                console.warn('[Manager] ⚠️ No reservations found. Response:', {
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
                const customer = this.getCustomerData(r);
                const name = `${customer.first_name || ''} ${customer.last_name || ''}`.toLowerCase();
                const email = (customer.email || '').toLowerCase();
                const phone = (customer.phone || '').toLowerCase();
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

        // Per viste MESE e SETTIMANA, mostra SEMPRE il calendario anche se vuoto
        // Solo la vista GIORNO mostra "nessuna prenotazione"
        const shouldShowEmptyState = filtered.length === 0 && this.state.currentView === 'day';

        if (shouldShowEmptyState) {
            // Mostra empty state solo per vista giorno
            if (this.dom.emptyState) this.dom.emptyState.style.display = 'flex';
            
            // Nascondi tutte le viste
            if (this.dom.viewDay) this.dom.viewDay.style.display = 'none';
            if (this.dom.viewWeek) this.dom.viewWeek.style.display = 'none';
            if (this.dom.viewMonth) this.dom.viewMonth.style.display = 'none';
            return;
        }
        
        // Nascondi empty state
        if (this.dom.emptyState) this.dom.emptyState.style.display = 'none';

        // Mostra la vista corrente e nascondi le altre
        if (this.dom.viewDay) this.dom.viewDay.style.display = this.state.currentView === 'day' ? 'block' : 'none';
        if (this.dom.viewWeek) this.dom.viewWeek.style.display = this.state.currentView === 'week' ? 'block' : 'none';
        if (this.dom.viewMonth) this.dom.viewMonth.style.display = this.state.currentView === 'month' ? 'block' : 'none';
        
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
            const customer = this.getCustomerData(resv);
            const guestName = `${customer.first_name} ${customer.last_name}`.trim() || customer.email;
            
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
                        ${customer.phone ? `<div class="fp-reservation-card__phone">${this.escapeHtml(customer.phone)}</div>` : ''}
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

    renderMonthView() {
        const currentDate = this.state.currentDate;
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        console.log('[Manager] 🗓️ RENDER MONTH VIEW');
        console.log('[Manager] Anno:', year, 'Mese:', month + 1);
        console.log('[Manager] Prenotazioni totali in state:', this.state.reservations.length);
        
        // Get first day of month and last day
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        
        const firstDayStr = this.formatDate(firstDay);
        const lastDayStr = this.formatDate(lastDay);
        console.log('[Manager] Range mese:', firstDayStr, '->', lastDayStr);
        
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
        let totalReservationsInMonth = 0;
        
        console.log('[Manager] === INIZIO RAGGRUPPAMENTO PRENOTAZIONI ===');
        console.log('[Manager] Totale prenotazioni da processare:', this.state.reservations.length);
        
        if (this.state.reservations.length === 0) {
            console.warn('[Manager] ⚠️⚠️⚠️ NESSUNA PRENOTAZIONE IN STATE! ⚠️⚠️⚠️');
        }
        
        this.state.reservations.forEach((resv, index) => {
            const date = resv.date;
            console.log(`[Manager] [${index + 1}/${this.state.reservations.length}] Prenotazione #${resv.id}:`, {
                date: date,
                time: resv.time,
                party: resv.party,
                status: resv.status,
                inRange: date >= firstDayStr && date <= lastDayStr
            });
            
            // Verifica se la prenotazione è nel range del mese
            if (date >= firstDayStr && date <= lastDayStr) {
                console.log(`[Manager] ✅ Prenotazione #${resv.id} È nel range del mese`);
                totalReservationsInMonth++;
                if (!reservationsByDate[date]) {
                    reservationsByDate[date] = [];
                }
                reservationsByDate[date].push(resv);
            } else {
                console.log(`[Manager] ❌ Prenotazione #${resv.id} FUORI dal range:`);
                console.log(`[Manager]    Data: ${date} | Range: ${firstDayStr} - ${lastDayStr}`);
                console.log(`[Manager]    date >= firstDayStr: ${date >= firstDayStr}`);
                console.log(`[Manager]    date <= lastDayStr: ${date <= lastDayStr}`);
            }
        });
        
        console.log('[Manager] === FINE RAGGRUPPAMENTO ===');
        console.log('[Manager] Prenotazioni nel mese corrente:', totalReservationsInMonth);
        console.log('[Manager] Giorni con prenotazioni:', Object.keys(reservationsByDate).length);
        console.log('[Manager] Giorni:', Object.keys(reservationsByDate).sort());
        console.log('[Manager] reservationsByDate:', reservationsByDate);
        
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
        
        // Filtra solo prenotazioni della settimana corrente
        const weekStart = monday.toISOString().split('T')[0];
        const weekEnd = sunday.toISOString().split('T')[0];
        
        this.state.reservations.forEach(resv => {
            const date = resv.date;
            
            // Verifica che la data sia nella settimana
            if (date >= weekStart && date <= weekEnd) {
                if (!reservationsByDate[date]) {
                    reservationsByDate[date] = [];
                }
                reservationsByDate[date].push(resv);
            }
        });
        
        // Nome giorni
        const dayNames = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
        
        // Header con navigazione settimana
        const mondayStr = this.formatItalianDate(monday);
        const sundayStr = this.formatItalianDate(sunday);
        
        let html = `
            <div class="fp-week-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin: 0; font-size: 20px; font-weight: 600;">Settimana ${mondayStr} - ${sundayStr}</h2>
                <div class="fp-week-nav" style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="fp-btn-icon" data-action="prev-week" title="Settimana precedente" style="padding: 8px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <button type="button" class="fp-btn fp-btn--secondary" data-action="this-week" style="padding: 8px 16px; border: 1px solid #0073aa; background: white; color: #0073aa; border-radius: 4px; cursor: pointer; font-weight: 500;">
                        Questa Settimana
                    </button>
                    <button type="button" class="fp-btn-icon" data-action="next-week" title="Settimana successiva" style="padding: 8px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>

            <div class="fp-week-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px;">
        `;
        
        // Giorni della settimana
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        
        for (let i = 0; i < 7; i++) {
            const date = new Date(monday);
            date.setDate(monday.getDate() + i);
            const dateStr = date.toISOString().split('T')[0];
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
            
            const baseStyle = 'background: white; border-radius: 8px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-height: 200px;';
            const todayStyle = isToday ? 'border: 2px solid #0073aa;' : '';
            const selectedStyle = isSelected ? 'background: #f0f9ff;' : '';
            
            html += `
                <div class="${dayClass}" data-date="${dateStr}" style="${baseStyle} ${todayStyle} ${selectedStyle}">
                    <div class="fp-week-day__header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">
                        <div class="fp-week-day__name" style="font-weight: 600; color: #4b5563; font-size: 12px; text-transform: uppercase;">${dayNames[i]}</div>
                        <div class="fp-week-day__number" style="font-size: 18px; font-weight: 700; color: ${isToday ? '#0073aa' : '#1f2937'};">${dayNumber}</div>
                    </div>
                    ${reservations.length > 0 ? `
                        <div class="fp-week-day__stats" style="display: flex; gap: 8px; margin-bottom: 10px;">
                            <div class="fp-week-day__count" style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 12px; font-weight: 600;">${reservations.length} pren.</div>
                            <div class="fp-week-day__guests" style="font-size: 11px; background: #dcfce7; color: #15803d; padding: 4px 8px; border-radius: 12px; font-weight: 600;">${totalGuests} coperti</div>
                        </div>
                        <div class="fp-week-day__reservations" style="display: flex; flex-direction: column; gap: 6px;">
                            ${reservations.slice(0, 5).map(resv => {
                                const statusColors = {
                                    confirmed: '#10b981',
                                    pending: '#f59e0b',
                                    visited: '#3b82f6',
                                    no_show: '#ef4444',
                                    cancelled: '#6b7280',
                                };
                                const statusColor = statusColors[resv.status] || '#6b7280';
                                const customer = this.getCustomerData(resv);
                                const guestName = `${customer.first_name} ${customer.last_name}`.trim() || customer.email;
                                
                                return `
                                    <div class="fp-week-reservation" data-id="${resv.id}" data-action="view-reservation" 
                                         style="background: #f9fafb; padding: 8px; border-radius: 6px; cursor: pointer; border-left: 3px solid ${statusColor}; transition: all 0.2s;"
                                         onmouseover="this.style.background='#e5e7eb'" 
                                         onmouseout="this.style.background='#f9fafb'">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div class="fp-week-reservation__time" style="font-weight: 600; color: #374151; font-size: 13px;">${resv.time}</div>
                                            <div class="fp-week-reservation__party" style="font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 4px;">
                                                <span class="dashicons dashicons-groups" style="font-size: 14px;"></span>
                                                ${resv.party}
                                            </div>
                                        </div>
                                        <div class="fp-week-reservation__name" style="font-size: 12px; color: #6b7280; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${this.escapeHtml(guestName)}</div>
                                    </div>
                                `;
                            }).join('')}
                            ${reservations.length > 5 ? `
                                <div class="fp-week-day__more" style="text-align: center; font-size: 11px; color: #6b7280; font-weight: 600; padding: 6px;">
                                    +${reservations.length - 5} altre
                                </div>
                            ` : ''}
                        </div>
                    ` : `
                        <div class="fp-week-day__empty" style="text-align: center; color: #9ca3af; font-size: 13px; padding: 40px 10px;">Nessuna prenotazione</div>
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
                const id = parseInt(card.dataset.id);
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
                const id = parseInt(card.dataset.id);
                this.openReservationModal(id);
            });
        });
    }

    openReservationModal(id) {
        console.log('[Manager] Opening reservation modal for ID:', id);
        
        const resv = this.state.reservations.find(r => r.id === id);
        if (!resv) {
            console.error('[Manager] Reservation not found with ID:', id);
            return;
        }

        const customer = this.getCustomerData(resv);
        const guestName = `${customer.first_name} ${customer.last_name}`.trim() || customer.email;

        this.dom.modalTitle.textContent = guestName;
        this.dom.modalBody.innerHTML = this.renderReservationDetails(resv);
        this.dom.modal.style.display = 'flex';

        console.log('[Manager] Modal HTML updated, now binding actions...');
        
        // Assicuriamoci che il DOM sia aggiornato prima di collegare gli eventi
        // Usiamo requestAnimationFrame per garantire che il browser abbia renderizzato il nuovo HTML
        requestAnimationFrame(() => {
            this.bindModalActions(resv);
        });
    }

    renderReservationDetails(resv) {
        const customer = this.getCustomerData(resv);
        const statusLabels = {
            confirmed: 'Confermato',
            pending: 'In attesa',
            visited: 'Visitato',
            no_show: 'No-show',
            cancelled: 'Cancellato',
        };
        
        const statusColors = {
            confirmed: '#10b981',
            pending: '#f59e0b',
            visited: '#3b82f6',
            no_show: '#ef4444',
            cancelled: '#6b7280',
        };

        return `
            <div class="fp-reservation-details">
                <div class="fp-detail-group">
                    <label>ID Prenotazione</label>
                    <div class="fp-detail-value"><strong>#${resv.id}</strong></div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Data e Ora</label>
                    <div class="fp-detail-value">${resv.date} - ${resv.time}</div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Numero Coperti</label>
                    <div class="fp-detail-value">
                        <input type="number" class="fp-form-control" data-field="party" 
                               value="${resv.party}" min="1" max="50" style="max-width: 100px;">
                    </div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Stato</label>
                    <div class="fp-detail-value">
                        <select class="fp-detail-select" data-field="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            ${Object.entries(statusLabels).map(([value, label]) => 
                                `<option value="${value}" ${resv.status === value ? 'selected' : ''} style="color: ${statusColors[value] || '#000'}">${label}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Cliente</label>
                    <div class="fp-detail-value">
                        <div><strong>${this.escapeHtml(customer.first_name)} ${this.escapeHtml(customer.last_name)}</strong></div>
                        ${customer.email ? `<div class="fp-detail-meta">📧 ${this.escapeHtml(customer.email)}</div>` : ''}
                        ${customer.phone ? `<div class="fp-detail-meta">📞 ${this.escapeHtml(customer.phone)}</div>` : ''}
                    </div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Note</label>
                    <div class="fp-detail-value">
                        <textarea class="fp-form-control" data-field="notes" rows="3" 
                                  style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${this.escapeHtml(resv.notes || '')}</textarea>
                    </div>
                </div>
                
                <div class="fp-detail-group">
                    <label>Allergie / Intolleranze</label>
                    <div class="fp-detail-value">
                        <textarea class="fp-form-control" data-field="allergies" rows="2" 
                                  style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; ${resv.allergies ? 'background: #fef3c7; border-color: #f59e0b;' : ''}">${this.escapeHtml(resv.allergies || '')}</textarea>
                    </div>
                </div>
                
                <div class="fp-modal-actions" style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
                    <button type="button" class="fp-btn fp-btn--primary" data-modal-action="save" style="flex: 1;">
                        <span class="dashicons dashicons-yes"></span>
                        Salva Modifiche
                    </button>
                    <button type="button" class="fp-btn fp-btn--secondary" data-modal-action="close" style="flex: 1;">
                        <span class="dashicons dashicons-no-alt"></span>
                        Chiudi
                    </button>
                </div>
                
                <div class="fp-modal-actions" style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                    ${resv.status !== 'cancelled' ? `
                    <button type="button" class="fp-btn fp-btn--warning" data-modal-action="cancel-reservation" style="flex: 1; background: #f59e0b; border-color: #f59e0b;">
                        <span class="dashicons dashicons-dismiss"></span>
                        Annulla Prenotazione
                    </button>
                    ` : ''}
                    <button type="button" class="fp-btn fp-btn--danger" data-modal-action="delete" style="flex: 1;">
                        <span class="dashicons dashicons-trash"></span>
                        Elimina Definitivamente
                    </button>
                </div>
            </div>
        `;
    }

    bindModalActions(resv) {
        console.log('[Manager] Binding modal actions for reservation ID:', resv.id);
        
        // Salva modifiche
        const saveBtn = this.dom.modalBody.querySelector('[data-modal-action="save"]');
        if (saveBtn) {
            console.log('[Manager] ✅ Save button found');
            saveBtn.addEventListener('click', async () => {
                await this.saveReservation(resv);
            });
        } else {
            console.warn('[Manager] ❌ Save button NOT found');
        }

        // Chiudi modale (pulsante secondario)
        const closeBtn = this.dom.modalBody.querySelector('[data-modal-action="close"]');
        if (closeBtn) {
            console.log('[Manager] ✅ Close button found');
            closeBtn.addEventListener('click', () => {
                this.closeModal();
            });
        } else {
            console.warn('[Manager] ❌ Close button NOT found');
        }

        // Annulla prenotazione
        const cancelReservationBtn = this.dom.modalBody.querySelector('[data-modal-action="cancel-reservation"]');
        if (cancelReservationBtn) {
            console.log('[Manager] ✅ Cancel reservation button found');
            cancelReservationBtn.addEventListener('click', async () => {
                if (confirm('Sei sicuro di voler annullare questa prenotazione?')) {
                    await this.cancelReservation(resv.id);
                }
            });
        } else {
            console.log('[Manager] ℹ️ Cancel reservation button NOT found (might be hidden for cancelled reservations)');
        }

        // Elimina definitivamente
        const deleteBtn = this.dom.modalBody.querySelector('[data-modal-action="delete"]');
        if (deleteBtn) {
            console.log('[Manager] ✅ Delete button found and binding event listener');
            deleteBtn.addEventListener('click', async (e) => {
                console.log('[Manager] Delete button clicked!', e);
                if (confirm('Sei sicuro di voler eliminare definitivamente questa prenotazione?')) {
                    console.log('[Manager] User confirmed deletion');
                    await this.deleteReservation(resv.id);
                } else {
                    console.log('[Manager] User cancelled deletion');
                }
            });
            console.log('[Manager] Delete button event listener attached successfully');
        } else {
            console.error('[Manager] ❌ DELETE BUTTON NOT FOUND! This is the issue!');
            console.error('[Manager] Modal body HTML:', this.dom.modalBody.innerHTML.substring(0, 500));
        }
        
        console.log('[Manager] Finished binding modal actions');
    }

    async saveReservation(resv) {
        console.log('[Manager] Saving reservation ID:', resv.id);
        
        const status = this.dom.modalBody.querySelector('[data-field="status"]')?.value;
        const party = this.dom.modalBody.querySelector('[data-field="party"]')?.value;
        const notes = this.dom.modalBody.querySelector('[data-field="notes"]')?.value;
        const allergies = this.dom.modalBody.querySelector('[data-field="allergies"]')?.value;

        const updates = {};
        if (status) updates.status = status;
        if (party) updates.party = parseInt(party, 10);
        if (notes !== undefined) updates.notes = notes;
        if (allergies !== undefined) updates.allergies = allergies;

        console.log('[Manager] Updates to save:', updates);

        try {
            const response = await fetch(this.buildRestUrl(`agenda/reservations/${resv.id}`), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify(updates),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to update reservation');
            }

            console.log('[Manager] Reservation saved successfully');
            this.closeModal();
            await this.loadReservations();
            await this.loadOverview();
            
            alert('Prenotazione salvata con successo');
        } catch (error) {
            console.error('[Manager] Error saving reservation:', error);
            alert('Errore nel salvataggio della prenotazione: ' + error.message);
        }
    }

    async cancelReservation(id) {
        try {
            const response = await fetch(this.buildRestUrl(`agenda/reservations/${id}`), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({ status: 'cancelled' }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Errore nell\'annullamento della prenotazione');
            }

            this.closeModal();
            await this.loadReservations();
            await this.loadOverview();
            
            alert('Prenotazione annullata con successo');
        } catch (error) {
            console.error('[Manager] Error cancelling reservation:', error);
            alert('Errore nell\'annullamento della prenotazione: ' + error.message);
        }
    }

    async deleteReservation(id) {
        console.log('[Manager] Inizio eliminazione prenotazione ID:', id);
        
        try {
            const url = this.buildRestUrl(`agenda/reservations/${id}`);
            console.log('[Manager] URL DELETE:', url);
            console.log('[Manager] Nonce:', this.config.nonce);
            
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            console.log('[Manager] DELETE Response Status:', response.status);
            console.log('[Manager] DELETE Response OK:', response.ok);
            console.log('[Manager] DELETE Response Headers:', {
                'content-type': response.headers.get('content-type'),
                'content-length': response.headers.get('content-length'),
                'x-fp-delete-success': response.headers.get('x-fp-delete-success'),
                'x-fp-reservation-id': response.headers.get('x-fp-reservation-id')
            });
            
            console.log('[Manager] ALL Response Headers:');
            for (let [key, value] of response.headers.entries()) {
                console.log(`  ${key}: ${value}`);
            }

            // Leggi il body come testo prima
            const responseText = await response.text();
            console.log('[Manager] DELETE Response Body:', responseText);

            if (!response.ok) {
                let errorMessage = 'Errore nell\'eliminazione della prenotazione';
                try {
                    const errorData = JSON.parse(responseText);
                    errorMessage = errorData.message || errorMessage;
                    console.error('[Manager] DELETE Error Data:', errorData);
                } catch (e) {
                    console.error('[Manager] DELETE Response non è JSON valido:', responseText);
                }
                throw new Error(errorMessage);
            }

            // Prova a parsare il risultato
            let result = {};
            try {
                result = JSON.parse(responseText);
                console.log('[Manager] DELETE Success:', result);
            } catch (e) {
                console.error('[Manager] DELETE Success ma response non è JSON:', responseText);
                // Se non è JSON ma status è 200, considera comunque successo
                result = { success: true };
            }

            this.closeModal();
            await this.loadReservations();
            await this.loadOverview();
            
            // Mostra messaggio di successo
            alert('Prenotazione eliminata con successo');
        } catch (error) {
            console.error('[Manager] DELETE Error:', error);
            alert('Errore nell\'eliminazione della prenotazione: ' + error.message);
        }
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
            this.escapeCsv(this.getCustomerData(resv).first_name),
            this.escapeCsv(this.getCustomerData(resv).last_name),
            this.escapeCsv(this.getCustomerData(resv).email),
            this.escapeCsv(this.getCustomerData(resv).phone),
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
        const dateStr = today.toISOString().split('T')[0];
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
    }

    hideLoading() {
        if (this.dom.loadingState) this.dom.loadingState.style.display = 'none';
    }

    showError(message) {
        if (this.dom.loadingState) this.dom.loadingState.style.display = 'none';
        if (this.dom.errorState) this.dom.errorState.style.display = 'flex';
        if (this.dom.errorMessage) this.dom.errorMessage.textContent = message;
    }

    updateStats(stats, meta) {
        if (!stats) return;
        
        // Calcola statistiche per oggi, settimana e mese
        const today = new Date().toISOString().split('T')[0];
        const todayReservations = this.state.reservations.filter(r => r.date === today);
        const todayGuests = todayReservations.reduce((sum, r) => sum + r.party, 0);
        
        // Settimana corrente
        const now = new Date();
        const weekStart = new Date(now);
        weekStart.setDate(now.getDate() - now.getDay() + 1); // Lunedì
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6); // Domenica
        
        const weekReservations = this.state.reservations.filter(r => {
            const d = new Date(r.date);
            return d >= weekStart && d <= weekEnd;
        });
        const weekGuests = weekReservations.reduce((sum, r) => sum + r.party, 0);
        
        // Mese corrente  
        const monthReservations = this.state.reservations.filter(r => {
            const d = new Date(r.date);
            return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        });
        const monthGuests = monthReservations.reduce((sum, r) => sum + r.party, 0);
        
        // Confermati
        const confirmedCount = stats.by_status?.confirmed || 0;
        const confirmedPercentage = stats.confirmed_percentage || 0;
        
        // Aggiorna card
        this.updateStatCard('today-count', todayReservations.length);
        this.updateStatCard('today-guests', `${todayGuests} coperti`);
        
        this.updateStatCard('confirmed-count', confirmedCount);
        this.updateStatCard('confirmed-percentage', `${confirmedPercentage}%`);
        
        this.updateStatCard('week-count', weekReservations.length);
        this.updateStatCard('week-guests', `${weekGuests} coperti`);
        
        this.updateStatCard('month-count', monthReservations.length);
        this.updateStatCard('month-guests', `${monthGuests} coperti`);
    }
    
    updateStatCard(statKey, value) {
        const el = document.querySelector(`[data-stat="${statKey}"]`);
        if (el) {
            el.textContent = value;
        }
    }

    showDebugPanel(info) {
        // Solo se debug mode è attivo nelle impostazioni
        if (!this.config.debugMode) {
            return;
        }
        
        // Trova o crea il pannello di debug
        let panel = document.getElementById('fp-resv-debug-panel');
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'fp-resv-debug-panel';
            panel.style.cssText = `
                position: fixed;
                top: 32px;
                left: 160px;
                right: 0;
                background: #fff;
                border-left: 4px solid #0073aa;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                padding: 15px 20px;
                z-index: 9999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 13px;
                line-height: 1.6;
            `;
            document.body.appendChild(panel);
        }

        // Determina il colore in base al tipo
        let borderColor = '#0073aa';
        let icon = 'ℹ️';
        if (info.error) {
            borderColor = '#dc3232';
            icon = '❌';
        } else if (info.warning) {
            borderColor = '#f0b849';
            icon = '⚠️';
        } else if (info.success) {
            borderColor = '#46b450';
            icon = '✅';
        }
        
        panel.style.borderLeftColor = borderColor;

        // Costruisci il contenuto
        let content = `<strong>${icon} DEBUG INFO</strong> | ${new Date().toLocaleTimeString()}<br>`;
        
        if (info.url) {
            content += `<strong>URL:</strong> ${info.url}<br>`;
        }
        
        if (info.status) {
            content += `<strong>Status:</strong> ${info.status} ${info.statusText || ''}<br>`;
        }
        
        if (info.headers) {
            content += `<strong>Headers:</strong><br>`;
            for (let [key, value] of Object.entries(info.headers)) {
                if (value !== null) {
                    content += `&nbsp;&nbsp;${key}: ${value}<br>`;
                }
            }
        }
        
        if (info.bodyLength !== undefined) {
            content += `<strong>Body Length:</strong> ${info.bodyLength} bytes<br>`;
        }
        
        if (info.reservationsCount !== undefined) {
            content += `<strong>Prenotazioni caricate:</strong> ${info.reservationsCount}<br>`;
        }
        
        if (info.message) {
            content += `<strong>Messaggio:</strong> ${info.message}<br>`;
        }
        
        if (info.errorText) {
            content += `<strong>Errore:</strong> <pre style="margin:5px 0;padding:10px;background:#f5f5f5;overflow:auto;max-height:150px;">${info.errorText}</pre>`;
        }
        
        // Mostra errori recenti dal plugin
        if (this.config.errors && this.config.errors.length > 0) {
            content += `<hr style="margin:10px 0;border:none;border-top:1px solid #ddd;">`;
            content += `<strong>🔴 Errori Recenti del Plugin (${this.config.errors.length}):</strong><br>`;
            content += `<div style="max-height:200px;overflow:auto;margin-top:5px;">`;
            
            this.config.errors.slice().reverse().forEach((error, index) => {
                const timestamp = error.timestamp ? new Date(error.timestamp).toLocaleString() : 'N/A';
                const contextStr = error.context ? JSON.stringify(error.context) : '';
                
                content += `
                    <div style="margin:5px 0;padding:8px;background:${index === 0 ? '#fff3cd' : '#f5f5f5'};border-left:3px solid #dc3232;font-size:12px;">
                        <div style="font-weight:bold;color:#dc3232;">${error.message}</div>
                        <div style="color:#666;font-size:11px;margin-top:3px;">${timestamp}</div>
                        ${contextStr ? `<details style="margin-top:5px;font-size:11px;"><summary style="cursor:pointer;color:#0073aa;">Dettagli</summary><pre style="margin:3px 0;padding:5px;background:#fff;overflow:auto;">${contextStr}</pre></details>` : ''}
                    </div>
                `;
            });
            
            content += `</div>`;
        }
        
        content += `<button onclick="this.parentElement.remove()" style="position:absolute;top:10px;right:10px;border:none;background:#ddd;padding:5px 10px;cursor:pointer;border-radius:3px;">Chiudi</button>`;
        
        panel.innerHTML = content;
    }

    // Nota: showEmpty e hideEmpty ora sono gestiti direttamente in renderCurrentView

    // ============================================
    // UTILITIES
    // ============================================
    
    /**
     * Estrae i dati cliente da una prenotazione
     * Supporta sia formato vecchio (flat) che nuovo (oggetto customer)
     */
    getCustomerData(reservation) {
        if (reservation.customer) {
            return reservation.customer;
        }
        // Fallback al formato piatto (retrocompatibilità)
        return {
            first_name: reservation.first_name || '',
            last_name: reservation.last_name || '',
            email: reservation.email || '',
            phone: reservation.phone || ''
        };
    }

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

