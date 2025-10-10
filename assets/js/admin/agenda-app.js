/**
 * FP Reservations - Agenda semplificata stile TheFork
 */

(function() {
    'use strict';

    // Settings
    const settings = window.fpResvAgendaSettings || {};
    const restRoot = (settings.restRoot || '/wp-json/fp-resv/v1').replace(/\/$/, '');
    const nonce = settings.nonce || '';

    // DOM Elements
    const datePicker = document.querySelector('[data-role="date-picker"]');
    const serviceFilter = document.querySelector('[data-role="service-filter"]');
    const summaryEl = document.querySelector('[data-role="summary"]');
    const loadingEl = document.querySelector('[data-role="loading"]');
    const emptyEl = document.querySelector('[data-role="empty"]');
    const timelineEl = document.querySelector('[data-role="timeline"]');
    const weekViewEl = document.querySelector('[data-role="week-view"]');
    const monthViewEl = document.querySelector('[data-role="month-view"]');
    const listViewEl = document.querySelector('[data-role="list-view"]');

    // State
    let currentDate = new Date();
    let currentService = '';
    let currentView = 'day'; // day, week, month, list
    let reservations = [];
    let currentModal = null;
    let loadRequestId = 0; // Counter to prevent race conditions
    let currentReservationId = null; // Current reservation being viewed

    // Initialize
    init();

    function init() {
        if (!datePicker) return;

        // Set default date
        datePicker.value = formatDate(currentDate);

        // Event listeners
        document.addEventListener('click', handleClick);
        datePicker.addEventListener('change', handleDateChange);
        serviceFilter?.addEventListener('change', handleServiceChange);

        // Set initial view (this already calls loadReservations internally)
        setActiveView('day');
    }

    // Event Handlers
    function handleClick(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.getAttribute('data-action');

        switch (action) {
            case 'prev-period':
                navigatePeriod(-1);
                break;
            case 'today':
                navigateToToday();
                break;
            case 'next-period':
                navigatePeriod(1);
                break;
            case 'set-view':
                const view = target.getAttribute('data-view');
                if (view) setActiveView(view);
                break;
            case 'new-reservation':
                openNewReservationModal();
                break;
            case 'close-modal':
                closeModal('[data-modal="new-reservation"]');
                break;
            case 'submit-reservation':
                submitReservation();
                break;
            case 'close-details':
                closeModal('[data-modal="reservation-details"]');
                break;
            case 'confirm-reservation':
                updateReservationStatus('confirmed');
                break;
            case 'edit-reservation':
                // TODO: Implement edit reservation functionality
                console.log('Edit reservation not yet implemented');
                break;
            default:
                if (action.startsWith('view-reservation-')) {
                    const id = parseInt(action.replace('view-reservation-', ''));
                    viewReservationDetails(id);
                } else if (target.hasAttribute('data-quickparty')) {
                    const party = target.getAttribute('data-quickparty');
                    const input = document.querySelector('[data-field="party"]');
                    if (input) input.value = party;
                }
        }
    }

    function handleDateChange() {
        const dateStr = datePicker.value;
        if (!dateStr) return;

        const [year, month, day] = dateStr.split('-').map(Number);
        currentDate = new Date(year, month - 1, day);
        loadReservations();
    }

    function handleServiceChange() {
        currentService = serviceFilter?.value || '';
        loadReservations();
    }

    // Navigation
    function navigatePeriod(offset) {
        switch (currentView) {
            case 'day':
                currentDate.setDate(currentDate.getDate() + offset);
                break;
            case 'week':
                currentDate.setDate(currentDate.getDate() + (offset * 7));
                break;
            case 'month':
                currentDate.setMonth(currentDate.getMonth() + offset);
                break;
            case 'list':
                currentDate.setDate(currentDate.getDate() + (offset * 7));
                break;
        }
        datePicker.value = formatDate(currentDate);
        loadReservations();
    }

    function navigateToToday() {
        currentDate = new Date();
        datePicker.value = formatDate(currentDate);
        loadReservations();
    }

    function setActiveView(view) {
        currentView = view;

        // Update button states
        document.querySelectorAll('[data-action="set-view"]').forEach(btn => {
            btn.classList.toggle('button-primary', btn.getAttribute('data-view') === view);
        });

        // Hide all views
        if (timelineEl) timelineEl.hidden = true;
        if (weekViewEl) weekViewEl.hidden = true;
        if (monthViewEl) monthViewEl.hidden = true;
        if (listViewEl) listViewEl.hidden = true;

        // Reload data
        loadReservations();
    }

    // API
    function request(path, options = {}) {
        const url = path.startsWith('http') ? path : `${restRoot}/${path.replace(/^\//, '')}`;
        
        const config = {
            method: options.method || 'GET',
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };

        if (options.data) {
            config.body = JSON.stringify(options.data);
            if (!config.method || config.method === 'GET') {
                config.method = 'POST';
            }
        }

        return fetch(url, config).then(response => {
            if (!response.ok) {
                return response.json().catch(() => ({})).then(payload => {
                    throw new Error(payload.message || 'Request failed');
                });
            }
            if (response.status === 204) return null;
            
            // Check if response has content before parsing JSON
            return response.text().then(text => {
                if (!text || text.trim() === '') {
                    return null;
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        });
    }

    function loadReservations() {
        showLoading();

        // Increment request ID to detect stale responses
        const requestId = ++loadRequestId;

        const { startDate, endDate } = getDateRange();
        const params = new URLSearchParams({
            date: startDate,
            ...(currentView === 'week' && { range: 'week' }),
            ...(currentView === 'month' && { range: 'month' }),
            ...(currentView === 'list' && { range: 'week' }),
            ...(currentService && { service: currentService })
        });

        request(`agenda?${params}`)
            .then(data => {
                // Ignore stale responses
                if (requestId !== loadRequestId) {
                    return;
                }
                
                // Handle null or empty responses
                if (!data) {
                    reservations = [];
                } else {
                    reservations = Array.isArray(data) ? data : (data.reservations || []);
                }
                
                renderCurrentView();
                updateSummary();
            })
            .catch(error => {
                // Ignore stale responses
                if (requestId !== loadRequestId) {
                    return;
                }
                
                console.error('Error loading reservations:', error);
                showEmpty();
            })
            .finally(() => {
                // Always hide loading, regardless of whether the request is stale
                // This prevents infinite loading states when requests are cancelled or superseded
                if (loadingEl) {
                    loadingEl.hidden = true;
                }
            });
    }

    function getDateRange() {
        const start = new Date(currentDate);
        let end = new Date(currentDate);

        switch (currentView) {
            case 'week':
                // Start from Monday
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
            startDate: formatDate(start),
            endDate: formatDate(end)
        };
    }

    function renderCurrentView() {
        switch (currentView) {
            case 'day':
                renderTimeline();
                break;
            case 'week':
                renderWeekView();
                break;
            case 'month':
                renderMonthView();
                break;
            case 'list':
                renderListView();
                break;
        }
    }

    function createReservation(data) {
        return request('agenda/reservations', { method: 'POST', data })
            .then(() => {
                loadReservations();
                closeModal('[data-modal="new-reservation"]');
            })
            .catch(error => {
                const errorEl = document.querySelector('[data-role="form-error"]');
                if (errorEl) {
                    errorEl.textContent = error.message || 'Impossibile creare la prenotazione';
                    errorEl.hidden = false;
                }
                throw error;
            });
    }

    function updateReservationStatus(status) {
        if (!currentReservationId) {
            console.error('No reservation selected');
            return;
        }

        request(`agenda/reservations/${currentReservationId}`, {
            method: 'PATCH',
            data: { status }
        })
            .then(() => {
                loadReservations();
                closeModal('[data-modal="reservation-details"]');
                currentReservationId = null;
            })
            .catch(error => {
                console.error('Error updating reservation status:', error);
                alert(error.message || 'Impossibile aggiornare lo stato della prenotazione');
            });
    }

    // Rendering
    function showLoading() {
        if (loadingEl) loadingEl.hidden = false;
        if (emptyEl) emptyEl.hidden = true;
        // Hide all views
        if (timelineEl) timelineEl.hidden = true;
        if (weekViewEl) weekViewEl.hidden = true;
        if (monthViewEl) monthViewEl.hidden = true;
        if (listViewEl) listViewEl.hidden = true;
    }

    function showEmpty() {
        if (loadingEl) loadingEl.hidden = true;
        if (emptyEl) emptyEl.hidden = false;
        // Hide all views
        if (timelineEl) timelineEl.hidden = true;
        if (weekViewEl) weekViewEl.hidden = true;
        if (monthViewEl) monthViewEl.hidden = true;
        if (listViewEl) listViewEl.hidden = true;
    }

    function renderTimeline() {
        if (!timelineEl) return;

        if (loadingEl) loadingEl.hidden = true;

        if (!reservations.length) {
            showEmpty();
            return;
        }

        // Hide all other views and empty state
        if (emptyEl) emptyEl.hidden = true;
        if (weekViewEl) weekViewEl.hidden = true;
        if (monthViewEl) monthViewEl.hidden = true;
        if (listViewEl) listViewEl.hidden = true;
        timelineEl.hidden = false;

        // Group by time slot
        const slots = groupByTimeSlot(reservations);
        
        timelineEl.innerHTML = '';

        Object.keys(slots).sort().forEach(time => {
            const slotReservations = slots[time];
            
            const slotEl = document.createElement('div');
            slotEl.className = 'fp-resv-timeline__slot';
            
            slotEl.innerHTML = `
                <div class="fp-resv-timeline__time">${time}</div>
                <div class="fp-resv-timeline__reservations">
                    ${slotReservations.map(renderReservationCard).join('')}
                </div>
            `;
            
            timelineEl.appendChild(slotEl);
        });
    }

    function renderWeekView() {
        if (!weekViewEl) return;

        if (loadingEl) loadingEl.hidden = true;

        if (!reservations.length) {
            showEmpty();
            return;
        }

        // Hide all other views and empty state
        if (emptyEl) emptyEl.hidden = true;
        if (timelineEl) timelineEl.hidden = true;
        if (monthViewEl) monthViewEl.hidden = true;
        if (listViewEl) listViewEl.hidden = true;
        weekViewEl.hidden = false;

        const { startDate } = getDateRange();
        const weekStart = new Date(startDate);
        const days = [];

        for (let i = 0; i < 7; i++) {
            const day = new Date(weekStart);
            day.setDate(weekStart.getDate() + i);
            const dayStr = formatDate(day);
            const dayReservations = reservations.filter(r => r.date === dayStr);
            
            days.push({
                date: day,
                dateStr: dayStr,
                reservations: dayReservations
            });
        }

        weekViewEl.innerHTML = `
            <div class="fp-resv-week__grid">
                ${days.map(day => `
                    <div class="fp-resv-week__day">
                        <div class="fp-resv-week__header">
                            <div class="fp-resv-week__day-name">${formatDayName(day.date)}</div>
                            <div class="fp-resv-week__day-number">${day.date.getDate()}</div>
                        </div>
                        <div class="fp-resv-week__content">
                            ${day.reservations.length ? 
                                day.reservations.map(resv => `
                                    <div class="fp-resv-week__item" data-status="${resv.status}" data-action="view-reservation-${resv.id}">
                                        <div class="fp-resv-week__time">${formatTime(resv.slot_start || resv.time)}</div>
                                        <div class="fp-resv-week__guest">${escapeHtml(getGuestName(resv))}</div>
                                        <div class="fp-resv-week__party">${resv.party} ${resv.party === 1 ? 'coperto' : 'coperti'}</div>
                                    </div>
                                `).join('') :
                                '<div class="fp-resv-week__empty">Nessuna prenotazione</div>'
                            }
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderMonthView() {
        if (!monthViewEl) return;

        if (loadingEl) loadingEl.hidden = true;

        if (!reservations.length) {
            showEmpty();
            return;
        }

        // Hide all other views and empty state
        if (emptyEl) emptyEl.hidden = true;
        if (timelineEl) timelineEl.hidden = true;
        if (weekViewEl) weekViewEl.hidden = true;
        if (listViewEl) listViewEl.hidden = true;
        monthViewEl.hidden = false;

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        
        // Calculate calendar grid
        const startDay = firstDay.getDay();
        const daysInMonth = lastDay.getDate();
        const gridStart = startDay === 0 ? -6 : 1 - startDay;
        
        const days = [];
        for (let i = gridStart; i <= daysInMonth; i++) {
            if (i < 1) {
                days.push({ date: null, reservations: [] });
            } else {
                const day = new Date(year, month, i);
                const dayStr = formatDate(day);
                const dayReservations = reservations.filter(r => r.date === dayStr);
                days.push({ date: day, dateStr: dayStr, reservations: dayReservations });
            }
        }

        monthViewEl.innerHTML = `
            <div class="fp-resv-month__header">
                <div class="fp-resv-month__title">${formatMonthYear(currentDate)}</div>
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
                        const isToday = formatDate(day.date) === formatDate(new Date());
                        return `
                            <div class="fp-resv-month__day ${isToday ? 'fp-resv-month__day--today' : ''}">
                                <div class="fp-resv-month__day-number">${day.date.getDate()}</div>
                                ${day.reservations.length ? `
                                    <div class="fp-resv-month__count" title="${day.reservations.length} prenotazioni">
                                        ${day.reservations.length}
                                    </div>
                                    <div class="fp-resv-month__items">
                                        ${day.reservations.slice(0, 3).map(resv => `
                                            <div class="fp-resv-month__item" data-status="${resv.status}" data-action="view-reservation-${resv.id}" title="${escapeHtml(getGuestName(resv))} - ${resv.party} coperti">
                                                ${formatTime(resv.slot_start || resv.time)} ${escapeHtml(getGuestName(resv))}
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
    }

    function renderListView() {
        if (!listViewEl) return;

        if (loadingEl) loadingEl.hidden = true;

        if (!reservations.length) {
            showEmpty();
            return;
        }

        // Hide all other views and empty state
        if (emptyEl) emptyEl.hidden = true;
        if (timelineEl) timelineEl.hidden = true;
        if (weekViewEl) weekViewEl.hidden = true;
        if (monthViewEl) monthViewEl.hidden = true;
        listViewEl.hidden = false;

        // Sort reservations by date and time
        const sortedReservations = [...reservations].sort((a, b) => {
            const dateA = a.date + ' ' + (a.time || '00:00');
            const dateB = b.date + ' ' + (b.time || '00:00');
            return dateA.localeCompare(dateB);
        });

        listViewEl.innerHTML = `
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
                        ${sortedReservations.map(resv => {
                            const customer = resv.customer || {};
                            const statusLabels = {
                                'pending': 'In attesa',
                                'confirmed': 'Confermata',
                                'visited': 'Servita',
                                'no_show': 'No-show',
                                'cancelled': 'Annullata'
                            };
                            return `
                                <tr class="fp-resv-list__row" data-status="${resv.status}" data-action="view-reservation-${resv.id}">
                                    <td>${formatDateShort(resv.date)}</td>
                                    <td><strong>${formatTime(resv.slot_start || resv.time)}</strong></td>
                                    <td>${escapeHtml(getGuestName(resv))}</td>
                                    <td>${resv.party}</td>
                                    <td>${escapeHtml(customer.phone || '-')}</td>
                                    <td><span class="fp-resv-list__badge fp-resv-list__badge--${resv.status}">${statusLabels[resv.status] || resv.status}</span></td>
                                    <td><span class="fp-resv-list__notes">${escapeHtml((resv.notes || '').substring(0, 40))}${resv.notes && resv.notes.length > 40 ? '...' : ''}</span></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function groupByTimeSlot(reservations) {
        const slots = {};
        
        reservations.forEach(resv => {
            const time = formatTime(resv.slot_start || resv.time || '12:00');
            if (!slots[time]) {
                slots[time] = [];
            }
            slots[time].push(resv);
        });
        
        return slots;
    }

    function renderReservationCard(resv) {
        const status = resv.status || 'pending';
        const statusLabels = {
            'pending': 'In attesa',
            'confirmed': 'Confermata',
            'visited': 'Servita',
            'no_show': 'No-show',
            'cancelled': 'Annullata'
        };

        const customer = resv.customer || {};
        const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ') || 'Cliente';
        const party = resv.party || resv.guests || 2;
        const phone = customer.phone || '';
        const notes = resv.notes || '';

        return `
            <div class="fp-resv-reservation-card" data-status="${status}" data-action="view-reservation-${resv.id}">
                <div class="fp-resv-reservation-card__header">
                    <div class="fp-resv-reservation-card__name">${escapeHtml(name)}</div>
                    <div class="fp-resv-reservation-card__badge">${statusLabels[status]}</div>
                </div>
                <div class="fp-resv-reservation-card__info">
                    <div class="fp-resv-reservation-card__info-item">
                        <span class="dashicons dashicons-groups"></span>
                        <span>${party} ${party === 1 ? 'coperto' : 'coperti'}</span>
                    </div>
                    ${phone ? `
                    <div class="fp-resv-reservation-card__info-item">
                        <span class="dashicons dashicons-phone"></span>
                        <span>${escapeHtml(phone)}</span>
                    </div>
                    ` : ''}
                    ${notes ? `
                    <div class="fp-resv-reservation-card__info-item">
                        <span class="dashicons dashicons-info"></span>
                        <span>${escapeHtml(notes.substring(0, 30))}${notes.length > 30 ? '...' : ''}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    function updateSummary() {
        if (!summaryEl) return;

        const dateEl = summaryEl.querySelector('.fp-resv-agenda__summary-date');
        const statsEl = summaryEl.querySelector('.fp-resv-agenda__summary-stats');

        if (dateEl) {
            dateEl.textContent = formatDateLong(currentDate);
        }

        if (statsEl) {
            const total = reservations.length;
            const confirmed = reservations.filter(r => r.status === 'confirmed').length;
            const totalGuests = reservations.reduce((sum, r) => sum + (r.party || r.guests || 0), 0);
            
            statsEl.textContent = `${total} prenotazioni • ${confirmed} confermate • ${totalGuests} coperti`;
        }
    }

    // Modals
    function openNewReservationModal() {
        const modal = document.querySelector('[data-modal="new-reservation"]');
        if (!modal) return;

        const form = modal.querySelector('[data-form="new-reservation"]');
        if (form) {
            form.reset();
            
            // Set default date and time
            const dateInput = form.querySelector('[data-field="date"]');
            const timeInput = form.querySelector('[data-field="time"]');
            
            if (dateInput) dateInput.value = formatDate(currentDate);
            if (timeInput) timeInput.value = '19:30';

            // Clear errors
            const errorEl = form.querySelector('[data-role="form-error"]');
            if (errorEl) errorEl.hidden = true;
        }

        openModal(modal);
    }

    function submitReservation() {
        const form = document.querySelector('[data-form="new-reservation"]');
        if (!form || !form.checkValidity()) {
            form?.reportValidity();
            return;
        }

        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        // Combine date and time into slot_start
        if (data.date && data.time) {
            data.slot_start = `${data.date} ${data.time}`;
        }

        // Set default status
        data.status = 'pending';

        createReservation(data);
    }

    function viewReservationDetails(id) {
        const resv = reservations.find(r => r.id === id);
        if (!resv) return;

        // Store current reservation ID for actions
        currentReservationId = id;

        const modal = document.querySelector('[data-modal="reservation-details"]');
        if (!modal) return;

        const contentEl = modal.querySelector('[data-role="details-content"]');
        if (contentEl) {
            contentEl.innerHTML = renderDetails(resv);
        }

        openModal(modal);
    }

    function renderDetails(resv) {
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
                        <div class="fp-resv-details__value">${escapeHtml(name)}</div>
                    </div>
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Stato</div>
                        <div class="fp-resv-details__value">${statusLabels[resv.status] || resv.status}</div>
                    </div>
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Data e ora</div>
                        <div class="fp-resv-details__value">${formatDateTimeLong(resv.slot_start || resv.date)}</div>
                    </div>
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Numero coperti</div>
                        <div class="fp-resv-details__value">${resv.party || resv.guests || 0}</div>
                    </div>
                    ${customer.email ? `
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Email</div>
                        <div class="fp-resv-details__value">${escapeHtml(customer.email)}</div>
                    </div>
                    ` : ''}
                    ${customer.phone ? `
                    <div class="fp-resv-details__item">
                        <div class="fp-resv-details__label">Telefono</div>
                        <div class="fp-resv-details__value">${escapeHtml(customer.phone)}</div>
                    </div>
                    ` : ''}
                </div>
                ${resv.notes ? `
                <div class="fp-resv-details__item" style="margin-top: 16px;">
                    <div class="fp-resv-details__label">Note</div>
                    <div class="fp-resv-details__value">${escapeHtml(resv.notes)}</div>
                </div>
                ` : ''}
            </div>
        `;
    }

    function openModal(modal) {
        if (!modal) return;
        currentModal = modal;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');

        // Focus first input
        requestAnimationFrame(() => {
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) firstInput.focus();
        });

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    function closeModal(selector) {
        const modal = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!modal) return;

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        currentModal = null;

        // Clear current reservation ID when closing details modal
        if (modal.getAttribute('data-modal') === 'reservation-details') {
            currentReservationId = null;
        }

        // Restore body scroll
        document.body.style.overflow = '';
    }

    // Utility functions
    function formatDate(date) {
        const d = date instanceof Date ? date : new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatDateLong(date) {
        const d = date instanceof Date ? date : new Date(date);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return d.toLocaleDateString('it-IT', options);
    }

    function formatTime(timeStr) {
        if (!timeStr) return '';
        const time = timeStr.includes(':') ? timeStr : timeStr.substring(0, 5);
        return time.substring(0, 5);
    }

    function formatDateTimeLong(dateTimeStr) {
        if (!dateTimeStr) return 'N/D';
        const dt = new Date(dateTimeStr);
        const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit' };
        return `${dt.toLocaleDateString('it-IT', dateOptions)} alle ${dt.toLocaleTimeString('it-IT', timeOptions)}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getGuestName(resv) {
        const customer = resv.customer || {};
        const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ');
        return name || customer.email || 'Cliente';
    }

    function formatDayName(date) {
        const days = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
        return days[date.getDay()];
    }

    function formatMonthYear(date) {
        const months = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
                       'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        return `${months[date.getMonth()]} ${date.getFullYear()}`;
    }

    function formatDateShort(dateStr) {
        if (!dateStr) return '';
        const [year, month, day] = dateStr.split('-');
        return `${day}/${month}/${year}`;
    }

})();
