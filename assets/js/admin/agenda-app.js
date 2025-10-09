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

    // State
    let currentDate = new Date();
    let currentService = '';
    let reservations = [];
    let currentModal = null;

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

        // Load initial data
        loadReservations();
    }

    // Event Handlers
    function handleClick(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.getAttribute('data-action');

        switch (action) {
            case 'prev-day':
                navigateDay(-1);
                break;
            case 'today':
                navigateToToday();
                break;
            case 'next-day':
                navigateDay(1);
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
    function navigateDay(offset) {
        currentDate.setDate(currentDate.getDate() + offset);
        datePicker.value = formatDate(currentDate);
        loadReservations();
    }

    function navigateToToday() {
        currentDate = new Date();
        datePicker.value = formatDate(currentDate);
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
            return response.json();
        });
    }

    function loadReservations() {
        showLoading();

        const dateStr = formatDate(currentDate);
        const params = new URLSearchParams({
            date: dateStr,
            ...(currentService && { service: currentService })
        });

        request(`agenda?${params}`)
            .then(data => {
                reservations = Array.isArray(data) ? data : (data.reservations || []);
                renderTimeline();
                updateSummary();
            })
            .catch(error => {
                console.error('Error loading reservations:', error);
                showEmpty();
            });
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
        // Implementazione placeholder
        console.log('Update status to:', status);
        closeModal('[data-modal="reservation-details"]');
    }

    // Rendering
    function showLoading() {
        if (loadingEl) loadingEl.hidden = false;
        if (emptyEl) emptyEl.hidden = true;
        if (timelineEl) timelineEl.hidden = true;
    }

    function showEmpty() {
        if (loadingEl) loadingEl.hidden = true;
        if (emptyEl) emptyEl.hidden = false;
        if (timelineEl) timelineEl.hidden = true;
    }

    function renderTimeline() {
        if (!timelineEl) return;

        if (loadingEl) loadingEl.hidden = true;

        if (!reservations.length) {
            showEmpty();
            return;
        }

        emptyEl.hidden = true;
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

})();
