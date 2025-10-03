/**
 * FP Restaurant Reservations - Admin Agenda application.
 */

var fpResvAgendaTranslate = (function () {
  if (typeof window !== 'undefined' && window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function') {
    return function (text) {
      return window.wp.i18n.__(text, 'fp-restaurant-reservations');
    };
  }

  return function (text) {
    return text;
  };
})();

(function () {
  if (typeof window === 'undefined') {
    return;
  }

  var rootId = 'fp-resv-agenda-app';
  var container = document.getElementById(rootId);
  if (!container) {
    return;
  }

  var settings = window.fpResvAgendaSettings || {};
  var strings = settings.strings || {};
  var activeTab = settings.activeTab || 'agenda';

  var request = createRequester(settings);

  var calendarSection = container.querySelector('[data-role="calendar"]');
  var arrivalsSection = container.querySelector('[data-role="arrivals"]');

  if ((activeTab === 'arrivi-oggi' || activeTab === 'settimana') && arrivalsSection) {
    initArrivals(arrivalsSection, activeTab === 'settimana' ? 'week' : 'today', request, strings);
    if (calendarSection) {
      calendarSection.hidden = true;
    }
    return;
  }

  if (calendarSection) {
    initCalendar(calendarSection, request, strings);
  }
})();

function createRequester(settings) {
  return function (path, options) {
    options = options || {};
    var headers = options.headers || {};
    if (settings.nonce) {
      headers['X-WP-Nonce'] = settings.nonce;
    }

    var body = null;
    if (options.data) {
      body = JSON.stringify(options.data);
      headers['Content-Type'] = 'application/json';
      if (!options.method) {
        options.method = 'POST';
      }
    }

    if (window.wp && window.wp.apiFetch) {
      return window.wp
        .apiFetch({
          path: path,
          method: options.method || 'GET',
          data: options.data,
          headers: headers,
          parse: false,
        })
        .then(function (response) {
          return parseAgendaResponse(response);
        });
    }

    var base = (settings.restRoot || '').replace(/\/$/, '');
    var url = path;
    if (path.indexOf('http') !== 0) {
      url = base + '/' + path.replace(/^\//, '');
    }

    return fetch(url, {
      method: options.method || 'GET',
      headers: headers,
      credentials: 'same-origin',
      body: body,
    }).then(function (response) {
      return parseAgendaResponse(response);
    });
  };
}

function parseAgendaResponse(response) {
  if (!response.ok) {
    return response
      .text()
      .then(function (text) {
        var message = 'Richiesta non riuscita';
        if (text) {
          try {
            var payload = JSON.parse(text);
            if (payload && payload.message) {
              message = payload.message;
            } else {
              message = text.trim() || message;
            }
          } catch (error) {
            message = text.trim() || message;
          }
        }
        var failure = new Error(message);
        failure.status = response.status;
        throw failure;
      });
  }

  if (response.status === 204) {
    return Promise.resolve(null);
  }

  var contentLength = response.headers.get('content-length');
  if (contentLength === '0') {
    return Promise.resolve(null);
  }

  return response.text().then(function (text) {
    if (!text) {
      return null;
    }

    var contentType = response.headers.get('content-type') || '';
    if (contentType.indexOf('json') === -1) {
      var invalid = new Error(text.trim() || 'Risposta non valida.');
      invalid.status = response.status;
      throw invalid;
    }

    try {
      return JSON.parse(text);
    } catch (error) {
      var parseError = error instanceof Error ? error : new Error('Risposta non valida.');
      parseError.status = response.status;
      throw parseError;
    }
  });
}
function initCalendar(section, request, strings) {
  section.hidden = false;

  var grid = section.querySelector('[data-role="agenda-grid"]');
  var empty = section.querySelector('[data-role="agenda-empty"]');
  var title = section.querySelector('[data-role="agenda-title"]');
  var hint = section.querySelector('[data-role="agenda-hint"]');
  var dateInput = section.querySelector('[data-role="agenda-date"]');
  var roomSelect = section.querySelector('[data-role="agenda-room"]');
  var viewSelect = section.querySelector('[data-role="agenda-view"]');
  var prevBtn = section.querySelector('[data-action="agenda-prev"]');
  var nextBtn = section.querySelector('[data-action="agenda-next"]');
  var todayBtn = section.querySelector('[data-action="agenda-today"]');
  var createBtn = section.querySelector('[data-action="agenda-create"]');

  if (!grid || !empty || !dateInput || !roomSelect || !viewSelect || !prevBtn || !nextBtn || !todayBtn || !createBtn) {
    return;
  }

  if (strings.agendaCreate) {
    createBtn.textContent = strings.agendaCreate;
  }
  if (strings.agendaPrevDay) {
    prevBtn.textContent = strings.agendaPrevDay;
  }
  if (strings.agendaNextDay) {
    nextBtn.textContent = strings.agendaNextDay;
  }
  if (strings.agendaToday) {
    todayBtn.textContent = strings.agendaToday;
  }

  if (!viewSelect.options.length) {
    addOption(viewSelect, 'day', strings.agendaViewDay || 'Giorno');
    addOption(viewSelect, 'week', strings.agendaViewWeek || 'Settimana');
  }

  var state = {
    date: normalizeDate(new Date()),
    mode: 'day',
    room: 'all',
    rooms: [],
    tables: [],
    days: [],
    loading: false,
  };

  var tableIndex = {};
  var lastSlots = [];
  var lastColumns = [];

  dateInput.value = formatDate(state.date);
  viewSelect.value = state.mode;

  roomSelect.addEventListener('change', function () {
    state.room = roomSelect.value === '' ? 'all' : roomSelect.value;
    render();
  });

  viewSelect.addEventListener('change', function () {
    var mode = viewSelect.value === 'week' ? 'week' : 'day';
    if (mode !== state.mode) {
      state.mode = mode;
      updateNavLabels();
      loadAgenda();
    }
  });

  dateInput.addEventListener('change', function () {
    var parsed = parseISODate(dateInput.value);
    if (parsed) {
      state.date = parsed;
      loadAgenda();
    }
  });

  prevBtn.addEventListener('click', function () {
    state.date = state.mode === 'week' ? addDays(state.date, -7) : addDays(state.date, -1);
    dateInput.value = formatDate(state.date);
    loadAgenda();
  });

  nextBtn.addEventListener('click', function () {
    state.date = state.mode === 'week' ? addDays(state.date, 7) : addDays(state.date, 1);
    dateInput.value = formatDate(state.date);
    loadAgenda();
  });

  todayBtn.addEventListener('click', function () {
    state.date = normalizeDate(new Date());
    dateInput.value = formatDate(state.date);
    loadAgenda();
  });

  createBtn.addEventListener('click', function () {
    var defaultTime = lastSlots.length ? lastSlots[0] : '19:00';
    var defaultColumn = null;
    for (var i = 0; i < lastColumns.length; i++) {
      if (lastColumns[i].type === 'table') {
        defaultColumn = lastColumns[i];
        break;
      }
    }

    openQuickCreate({
      date: formatDate(state.date),
      time: defaultTime,
      tableId: defaultColumn ? defaultColumn.id : null,
      roomId: defaultColumn ? defaultColumn.room_id : state.room !== 'all' ? parseInt(state.room, 10) : null,
    });
  });

  updateNavLabels();
  loadAgenda();

  function updateNavLabels() {
    if (state.mode === 'week') {
      if (strings.agendaPrevWeek) {
        prevBtn.textContent = strings.agendaPrevWeek;
      }
      if (strings.agendaNextWeek) {
        nextBtn.textContent = strings.agendaNextWeek;
      }
    } else {
      if (strings.agendaPrevDay) {
        prevBtn.textContent = strings.agendaPrevDay;
      }
      if (strings.agendaNextDay) {
        nextBtn.textContent = strings.agendaNextDay;
      }
    }
  }

  function loadAgenda() {
    state.loading = true;
    setMessage(strings.agendaLoading || 'Caricamento…', false);
    grid.innerHTML = '';
    lastSlots = [];
    lastColumns = [];

    var params = new URLSearchParams();
    params.append('date', formatDate(state.date));
    params.append('range', state.mode === 'week' ? 'week' : 'day');

    request('/fp-resv/v1/agenda?' + params.toString())
      .then(function (response) {
        state.loading = false;
        applyData(response || {});
        render();
      })
      .catch(function () {
        state.loading = false;
        setMessage(strings.agendaError || 'Impossibile caricare l’agenda.', true);
      });
  }

  function applyData(payload) {
    state.rooms = Array.isArray(payload.rooms) ? payload.rooms : [];
    state.tables = Array.isArray(payload.tables) ? payload.tables : [];
    state.days = normalizeDays(payload);

    tableIndex = {};
    for (var i = 0; i < state.tables.length; i++) {
      var table = state.tables[i];
      if (table && table.id !== null && table.id !== undefined) {
        tableIndex[String(table.id)] = table;
      }
    }
  }

  function render() {
    var heading = state.mode === 'week' ? strings.agendaWeekTitle : strings.agendaDayTitle;
    if (title) {
      var formatted = state.mode === 'week' ? formatWeekRange(state.date) : formatHumanDate(state.date);
      title.textContent = (heading || '') + (formatted ? ' · ' + formatted : '');
    }
    if (hint) {
      hint.textContent = strings.agendaDragHelp || '';
    }

    buildRoomOptions();

    if (state.loading) {
      setMessage(strings.agendaLoading || 'Caricamento…', false);
      return;
    }

    grid.innerHTML = '';
    grid.classList.remove('fp-resv-calendar__grid--day');
    grid.classList.remove('fp-resv-calendar__grid--week');

    var days = state.mode === 'week' ? expandWeekDays(state.days, state.date) : [findDay(state.date)];
    lastSlots = [];
    lastColumns = [];

    if (state.mode === 'week') {
      renderWeek(days);
    } else {
      renderDayView(days[0]);
    }

    var hasReservations = false;
    for (var i = 0; i < days.length; i++) {
      if (days[i] && Array.isArray(days[i].reservations) && days[i].reservations.length) {
        hasReservations = true;
        break;
      }
    }

    if (!hasReservations && !lastSlots.length) {
      setMessage(strings.agendaEmpty || 'Nessuna prenotazione per il periodo selezionato.', false);
    } else {
      setMessage('', false);
    }
  }

  function renderDayView(day) {
    var dayData = day || { date: formatDate(state.date), reservations: [] };

    var wrapper = document.createElement('div');
    wrapper.className = 'fp-resv-calendar__day-view';
    grid.classList.add('fp-resv-calendar__grid--day');
    grid.appendChild(wrapper);

    var header = document.createElement('header');
    header.className = 'fp-resv-calendar__day-header';
    header.textContent = formatHumanDate(parseISODate(dayData.date));
    wrapper.appendChild(header);

    var timeline = document.createElement('div');
    timeline.className = 'fp-resv-calendar__timeline';
    wrapper.appendChild(timeline);

    var columns = buildColumns(dayData);
    lastColumns = columns.slice();

    var headerRow = document.createElement('div');
    headerRow.className = 'fp-resv-calendar__timeline-row fp-resv-calendar__timeline-row--header';

    var timeHead = document.createElement('div');
    timeHead.className = 'fp-resv-calendar__timeline-time';
    timeHead.textContent = strings.agendaTimeLabel || 'Orario';
    headerRow.appendChild(timeHead);

    for (var c = 0; c < columns.length; c++) {
      var columnCell = document.createElement('div');
      columnCell.className = 'fp-resv-calendar__timeline-slot fp-resv-calendar__timeline-slot--header';
      columnCell.textContent = columns[c].label;
      headerRow.appendChild(columnCell);
    }

    timeline.appendChild(headerRow);

    var slots = buildTimeSlots(dayData);
    lastSlots = slots.slice();

    var dragState = { current: null };

    for (var s = 0; s < slots.length; s++) {
      (function () {
        var slotTime = slots[s];
        var row = document.createElement('div');
        row.className = 'fp-resv-calendar__timeline-row';

        var timeCell = document.createElement('div');
        timeCell.className = 'fp-resv-calendar__timeline-time';
        timeCell.textContent = slotTime;
        row.appendChild(timeCell);

        for (var col = 0; col < columns.length; col++) {
          (function () {
            var column = columns[col];
            var cell = document.createElement('div');
            cell.className = 'fp-resv-calendar__timeline-slot';
            cell.setAttribute('data-slot', dayData.date + 'T' + slotTime + ':00');
            cell.setAttribute('data-time', slotTime);

            if (column.type === 'table' && column.id !== null) {
              cell.setAttribute('data-table', String(column.id));
              if (column.room_id !== null && column.room_id !== undefined) {
                cell.setAttribute('data-room', String(column.room_id));
              }
              if (!column.active) {
                cell.classList.add('is-inactive');
              }
            } else {
              cell.setAttribute('data-table', '');
              cell.classList.add('fp-resv-calendar__timeline-slot--unassigned');
            }

            cell.addEventListener('dragover', function (event) {
              if (!dragState.current) {
                return;
              }
              event.preventDefault();
              cell.classList.add('is-droppable');
            });

            cell.addEventListener('dragleave', function () {
              cell.classList.remove('is-droppable');
            });

            cell.addEventListener('drop', function (event) {
              cell.classList.remove('is-droppable');
              event.preventDefault();
              if (!dragState.current) {
                return;
              }

              var targetTable = column.type === 'table' ? column.id : null;
              var targetRoom = column.type === 'table' ? column.room_id : state.room !== 'all' ? parseInt(state.room, 10) : null;

              if (
                dragState.current.date === dayData.date &&
                dragState.current.time === slotTime &&
                (dragState.current.tableId || null) === (targetTable || null)
              ) {
                return;
              }

              moveReservation(
                dragState.current.id,
                {
                  date: dayData.date,
                  time: slotTime,
                  table_id: targetTable,
                  room_id: targetRoom,
                }
              );
            });

            cell.addEventListener('dblclick', function () {
              openQuickCreate(
                {
                  date: dayData.date,
                  time: slotTime,
                  tableId: column.type === 'table' ? column.id : null,
                  roomId: column.type === 'table' ? column.room_id : state.room !== 'all' ? parseInt(state.room, 10) : null,
                }
              );
            });

            var reservations = findReservationsForSlot(dayData, column, slotTime);
            for (var r = 0; r < reservations.length; r++) {
              cell.appendChild(createReservationCard(reservations[r], dragState));
            }

            row.appendChild(cell);
          })();
        }

        timeline.appendChild(row);
      })();
    }
  }

  function renderWeek(days) {
    grid.classList.add('fp-resv-calendar__grid--week');
    for (var i = 0; i < days.length; i++) {
      var day = days[i];
      var container = document.createElement('section');
      container.className = 'fp-resv-calendar__week-day';

      var header = document.createElement('header');
      header.className = 'fp-resv-calendar__week-day-header';
      header.textContent = formatHumanDate(parseISODate(day.date));
      container.appendChild(header);

      var list = document.createElement('ul');
      list.className = 'fp-resv-calendar__week-list';

      var reservations = Array.isArray(day.reservations) ? day.reservations : [];
      if (!reservations.length) {
        var emptyItem = document.createElement('li');
        emptyItem.className = 'fp-resv-calendar__week-empty';
        emptyItem.textContent = strings.agendaEmpty || 'Nessuna prenotazione.';
        list.appendChild(emptyItem);
      } else {
        for (var r = 0; r < reservations.length; r++) {
          if (!shouldDisplayReservation(reservations[r])) {
            continue;
          }
          var item = document.createElement('li');
          item.className = 'fp-resv-calendar__week-item';
          item.innerHTML =
            '<strong>' +
            escapeHtml(reservations[r].time || '') +
            '</strong><span>' +
            escapeHtml(reservations[r].customer && reservations[r].customer.name ? reservations[r].customer.name : '') +
            '</span><span class="fp-resv-calendar__week-meta">' +
            escapeHtml(String(reservations[r].party || 0)) +
            ' px</span>';
          list.appendChild(item);
        }
      }

      container.appendChild(list);
      grid.appendChild(container);
    }
  }

  function buildRoomOptions() {
    var current = state.room;
    roomSelect.innerHTML = '';
    addOption(roomSelect, '', strings.agendaRoomAll || 'Tutte le sale');

    for (var i = 0; i < state.rooms.length; i++) {
      var room = state.rooms[i];
      if (!room || room.id === undefined || room.id === null) {
        continue;
      }
      var option = document.createElement('option');
      option.value = String(room.id);
      option.textContent = room.name || ('Sala ' + room.id);
      if (String(room.id) === String(current)) {
        option.selected = true;
      }
      roomSelect.appendChild(option);
    }

    if (current !== 'all' && roomSelect.value !== String(current)) {
      roomSelect.value = String(current);
    }
  }

  function buildColumns(day) {
    var columns = [];
    var selectedRoom = state.room;

    for (var i = 0; i < state.tables.length; i++) {
      var table = state.tables[i];
      if (!table) {
        continue;
      }
      var roomId = table.room_id !== undefined && table.room_id !== null ? String(table.room_id) : '';
      if (selectedRoom !== 'all' && roomId !== String(selectedRoom)) {
        continue;
      }
      columns.push({
        type: 'table',
        id: table.id,
        room_id: table.room_id !== undefined ? table.room_id : null,
        label: buildTableLabel(table),
        active: !!table.active,
      });
    }

    if (!columns.length || hasUnassignedReservations(day)) {
      columns.push({
        type: 'unassigned',
        id: null,
        room_id: null,
        label: strings.agendaUnassigned || 'Da assegnare',
        active: true,
      });
    }

    return columns;
  }

  function buildTableLabel(table) {
    var label = table.label || table.code || ('Tavolo ' + table.id);
    var capacity = table.seats_std || table.seats_max || table.seats_min;
    if (capacity) {
      label += ' · ' + capacity + ' px';
    }
    if (table.room_name) {
      label += ' (' + table.room_name + ')';
    }
    if (table.status && table.status !== 'available') {
      label += ' · ' + table.status;
    }
    return label;
  }

  function buildTimeSlots(day) {
    var reservations = Array.isArray(day.reservations) ? day.reservations : [];
    var min = 18 * 60;
    var max = 22 * 60;
    var found = false;

    for (var i = 0; i < reservations.length; i++) {
      var reservation = reservations[i];
      if (!shouldDisplayReservation(reservation)) {
        continue;
      }
      var minutes = timeToMinutes(reservation.time);
      if (!Number.isFinite(minutes)) {
        continue;
      }
      if (!found) {
        min = minutes;
        max = minutes;
        found = true;
      } else {
        if (minutes < min) {
          min = minutes;
        }
        if (minutes > max) {
          max = minutes;
        }
      }
    }

    if (!found) {
      return defaultSlots();
    }

    min = Math.floor(min / 30) * 30;
    max = Math.ceil(max / 30) * 30;
    if (max <= min) {
      max = min + 120;
    }

    var slots = [];
    for (var current = min; current <= max; current += 30) {
      slots.push(minutesToTime(current));
    }
    return slots;
  }

  function defaultSlots() {
    var slots = [];
    for (var current = 18 * 60; current <= 22 * 60; current += 30) {
      slots.push(minutesToTime(current));
    }
    return slots;
  }

  function hasUnassignedReservations(day) {
    var reservations = Array.isArray(day.reservations) ? day.reservations : [];
    for (var i = 0; i < reservations.length; i++) {
      if (!shouldDisplayReservation(reservations[i])) {
        continue;
      }
      if (reservations[i].table_id === null || reservations[i].table_id === undefined) {
        return true;
      }
    }
    return false;
  }

  function findReservationsForSlot(day, column, time) {
    var reservations = Array.isArray(day.reservations) ? day.reservations : [];
    var matches = [];

    for (var i = 0; i < reservations.length; i++) {
      var entry = reservations[i];
      if (!shouldDisplayReservation(entry)) {
        continue;
      }
      if ((entry.time || '') !== time) {
        continue;
      }
      if (column.type === 'table') {
        if (entry.table_id === null || entry.table_id === undefined) {
          continue;
        }
        if (String(entry.table_id) !== String(column.id)) {
          continue;
        }
      } else if (entry.table_id !== null && entry.table_id !== undefined) {
        continue;
      }
      matches.push(entry);
    }

    return matches;
  }

  function shouldDisplayReservation(reservation) {
    if (!reservation) {
      return false;
    }

    if (state.room === 'all') {
      return true;
    }

    var roomId = reservation.room_id !== undefined && reservation.room_id !== null ? String(reservation.room_id) : null;
    if (roomId !== null) {
      return roomId === String(state.room);
    }

    if (reservation.table_id !== undefined && reservation.table_id !== null) {
      var table = tableIndex[String(reservation.table_id)];
      if (table && table.room_id !== undefined && table.room_id !== null) {
        return String(table.room_id) === String(state.room);
      }
    }

    return true;
  }

  function createReservationCard(reservation, dragState) {
    var card = document.createElement('article');
    card.className = 'fp-resv-calendar__reservation status-' + sanitizeStatus(reservation.status);
    card.setAttribute('data-reservation-id', String(reservation.id));
    card.setAttribute('draggable', 'true');

    var time = document.createElement('span');
    time.className = 'fp-resv-calendar__reservation-time';
    time.textContent = reservation.time || '';
    card.appendChild(time);

    var name = document.createElement('div');
    name.className = 'fp-resv-calendar__reservation-name';
    name.textContent = reservation.customer && reservation.customer.name ? reservation.customer.name : '';
    card.appendChild(name);

    var meta = document.createElement('div');
    meta.className = 'fp-resv-calendar__reservation-meta';
    meta.textContent = String(reservation.party || 0) + ' px';
    card.appendChild(meta);

    if (reservation.notes) {
      var notes = document.createElement('div');
      notes.className = 'fp-resv-calendar__reservation-notes';
      notes.textContent = reservation.notes;
      card.appendChild(notes);
    }

    card.addEventListener('dragstart', function (event) {
      dragState.current = {
        id: reservation.id,
        date: reservation.date || '',
        time: reservation.time || '',
        tableId: reservation.table_id !== undefined ? reservation.table_id : null,
      };
      card.classList.add('is-dragging');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        try {
          event.dataTransfer.setData('text/plain', JSON.stringify(dragState.current));
        } catch (error) {
          // Ignore serialization errors.
        }
      }
    });

    card.addEventListener('dragend', function () {
      dragState.current = null;
      card.classList.remove('is-dragging');
    });

    card.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openQuickCreate(
          {
            date: reservation.date || formatDate(state.date),
            time: reservation.time || '19:00',
            tableId: reservation.table_id !== undefined ? reservation.table_id : null,
            roomId: reservation.room_id !== undefined ? reservation.room_id : null,
          }
        );
      }
    });

    return card;
  }

  function findDay(date) {
    var iso = formatDate(date);
    for (var i = 0; i < state.days.length; i++) {
      if (state.days[i] && state.days[i].date === iso) {
        return state.days[i];
      }
    }
    return { date: iso, reservations: [] };
  }

  function normalizeDays(payload) {
    if (Array.isArray(payload.days)) {
      return payload.days.map(normalizeDay);
    }

    if (Array.isArray(payload.reservations)) {
      var grouped = {};
      for (var i = 0; i < payload.reservations.length; i++) {
        var reservation = normalizeReservation(payload.reservations[i]);
        var date = reservation.date || formatDate(state.date);
        if (!grouped[date]) {
          grouped[date] = { date: date, reservations: [] };
        }
        grouped[date].reservations.push(reservation);
      }
      var result = [];
      for (var key in grouped) {
        if (Object.prototype.hasOwnProperty.call(grouped, key)) {
          result.push(grouped[key]);
        }
      }
      return result;
    }

    return [];
  }

  function normalizeDay(day) {
    var date = day && day.date ? String(day.date) : formatDate(state.date);
    var reservations = Array.isArray(day.reservations) ? day.reservations.map(normalizeReservation) : [];
    return { date: date, reservations: reservations };
  }

  function normalizeReservation(entry) {
    var reservation = entry || {};
    return {
      id: reservation.id !== undefined ? Number(reservation.id) : null,
      status: reservation.status || 'pending',
      date: reservation.date || reservation.reservation_date || formatDate(state.date),
      time: reservation.time || '',
      party: reservation.party !== undefined ? Number(reservation.party) : 0,
      notes: reservation.notes || '',
      allergies: reservation.allergies || '',
      room_id: reservation.room_id !== undefined && reservation.room_id !== null ? Number(reservation.room_id) : null,
      table_id: reservation.table_id !== undefined && reservation.table_id !== null ? Number(reservation.table_id) : null,
      customer: normalizeCustomer(reservation.customer),
    };
  }

  function normalizeCustomer(customer) {
    if (!customer || typeof customer !== 'object') {
      return { name: '' };
    }
    var name = customer.name || '';
    if (!name) {
      var first = customer.first_name || '';
      var last = customer.last_name || '';
      name = (first + ' ' + last).trim();
    }
    if (!name) {
      name = customer.email || customer.phone || '';
    }
    return {
      id: customer.id !== undefined && customer.id !== null ? Number(customer.id) : null,
      name: name,
      email: customer.email || '',
      phone: customer.phone || '',
    };
  }

  function expandWeekDays(days, date) {
    var start = startOfWeek(date);
    var result = [];
    for (var i = 0; i < 7; i++) {
      var dayDate = addDays(start, i);
      var iso = formatDate(dayDate);
      var match = null;
      for (var j = 0; j < days.length; j++) {
        if (days[j] && days[j].date === iso) {
          match = days[j];
          break;
        }
      }
      result.push(match || { date: iso, reservations: [] });
    }
    return result;
  }

  function moveReservation(id, payload) {
    request('/fp-resv/v1/agenda/reservations/' + id + '/move', {
      method: 'POST',
      data: {
        date: payload.date,
        time: payload.time,
        table_id: payload.table_id,
        room_id: payload.room_id,
      },
    })
      .then(function () {
        announce(strings.agendaMoveSuccess || fpResvAgendaTranslate('Reservation updated.'));
        loadAgenda();
      })
      .catch(function () {
        window.alert(strings.agendaMoveError || fpResvAgendaTranslate('Unable to move the reservation.'));
      });
  }

  function openQuickCreate(slot) {
    var guestLabel = strings.agendaCreateGuest || fpResvAgendaTranslate('Guest name');
    var partyLabel = strings.agendaCreateParty || fpResvAgendaTranslate('Party size');

    var guest = window.prompt(guestLabel + ':', slot && slot.guest ? slot.guest : '');
    if (guest === null) {
      return;
    }
    guest = guest.trim();

    var partyInput = window.prompt(partyLabel + ':', String(slot && slot.party ? slot.party : 2));
    if (partyInput === null) {
      return;
    }

    var party = parseInt(partyInput, 10);
    if (!guest || !party || party <= 0 || Number.isNaN(party)) {
      window.alert(strings.agendaCreateInvalid || fpResvAgendaTranslate('Enter a valid name and party size.'));
      return;
    }

    var split = splitName(guest);
    var payload = {
      date: slot.date,
      time: slot.time,
      party: party,
      first_name: split.first,
      last_name: split.last,
      status: 'pending',
    };

    if (slot.tableId) {
      payload.table_id = slot.tableId;
    }
    if (slot.roomId) {
      payload.room_id = slot.roomId;
    }

    request('/fp-resv/v1/agenda/reservations', {
      method: 'POST',
      data: payload,
    })
      .then(function () {
        announce(strings.agendaCreateSuccess || 'Prenotazione creata.');
        loadAgenda();
      })
      .catch(function () {
        window.alert(strings.agendaCreateError || 'Impossibile creare la prenotazione.');
      });
  }

  function setMessage(message, isError) {
    if (!empty) {
      return;
    }
    if (!message) {
      empty.hidden = true;
      empty.textContent = '';
      empty.classList.remove('is-error');
      return;
    }
    empty.hidden = false;
    empty.textContent = message;
    if (isError) {
      empty.classList.add('is-error');
    } else {
      empty.classList.remove('is-error');
    }
  }
}
function splitName(value) {
  var parts = (value || '').split(/\s+/).filter(Boolean);
  if (!parts.length) {
    return { first: value, last: '' };
  }
  var first = parts.shift();
  return { first: first || value, last: parts.join(' ') };
}

function timeToMinutes(time) {
  var parts = (time || '').split(':');
  if (parts.length < 2) {
    return NaN;
  }
  var hours = parseInt(parts[0], 10);
  var minutes = parseInt(parts[1], 10);
  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return NaN;
  }
  return hours * 60 + minutes;
}

function minutesToTime(minutes) {
  var hours = Math.floor(minutes / 60);
  var mins = minutes % 60;
  return pad(hours) + ':' + pad(mins);
}

function pad(value) {
  var string = String(Math.abs(value));
  return (string.length < 2 ? '0' : '') + string;
}

function normalizeDate(date) {
  var normalized = new Date(date.getTime());
  normalized.setHours(0, 0, 0, 0);
  return normalized;
}

function formatDate(date) {
  return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
}

function parseISODate(value) {
  if (!value) {
    return null;
  }
  var parts = value.split('-');
  if (parts.length !== 3) {
    return null;
  }
  var year = parseInt(parts[0], 10);
  var month = parseInt(parts[1], 10) - 1;
  var day = parseInt(parts[2], 10);
  if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
    return null;
  }
  return normalizeDate(new Date(year, month, day));
}

function formatHumanDate(value) {
  if (!value) {
    return '';
  }
  var date = value instanceof Date ? value : parseISODate(String(value));
  if (!date) {
    return '';
  }
  return date.toLocaleDateString(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function formatWeekRange(date) {
  var start = startOfWeek(date);
  var end = addDays(start, 6);
  return formatHumanDate(start) + ' – ' + formatHumanDate(end);
}

function startOfWeek(date) {
  var normalized = normalizeDate(date);
  var day = normalized.getDay();
  var diff = day === 0 ? -6 : 1 - day;
  return addDays(normalized, diff);
}

function addDays(date, amount) {
  var clone = new Date(date.getTime());
  clone.setDate(clone.getDate() + amount);
  return normalizeDate(clone);
}

function sanitizeStatus(status) {
  return String(status || 'pending').toLowerCase().replace(/[^a-z0-9-]/g, '-');
}

function escapeHtml(value) {
  var div = document.createElement('div');
  div.textContent = value || '';
  return div.innerHTML;
}

function addOption(select, value, label) {
  var option = document.createElement('option');
  option.value = value;
  option.textContent = label;
  select.appendChild(option);
}

function announce(message) {
  if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
    window.wp.a11y.speak(message);
  }
}
function initArrivals(section, range, request, strings) {
  section.hidden = false;

  var title = section.querySelector('[data-role="arrivals-title"]');
  var list = section.querySelector('[data-role="arrivals-list"]');
  var empty = section.querySelector('[data-role="arrivals-empty"]');
  var reload = section.querySelector('[data-action="arrivals-reload"]');
  var roomFilter = section.querySelector('[data-role="arrivals-room"]');
  var statusFilter = section.querySelector('[data-role="arrivals-status"]');

  if (title) {
    var baseTitle = strings.arrivalsTitle || 'Prenotazioni in arrivo';
    title.textContent = range === 'week' ? baseTitle + ' · 7 giorni' : baseTitle + ' · Oggi';
  }

  if (reload && strings.arrivalsReload) {
    reload.textContent = strings.arrivalsReload;
  }

  function buildQuery(params) {
    var query = new URLSearchParams();
    Object.keys(params).forEach(function (key) {
      var value = params[key];
      if (value === undefined || value === null || value === '') {
        return;
      }
      query.append(key, value);
    });
    return query.toString();
  }

  function toggle(element, shouldShow) {
    if (!element) {
      return;
    }
    element.hidden = !shouldShow;
  }

  function renderArrivals(reservations) {
    if (!list || !empty) {
      return;
    }

    list.innerHTML = '';

    if (!reservations || !reservations.length) {
      empty.textContent = strings.arrivalsEmpty || 'Nessuna prenotazione imminente.';
      toggle(empty, true);
      toggle(list, false);
      return;
    }

    reservations.forEach(function (item) {
      var card = document.createElement('li');
      card.className = 'fp-resv-arrivals__card';

      var header = document.createElement('header');
      header.className = 'fp-resv-arrivals__card-header';
      header.innerHTML =
        '<span class="fp-resv-arrivals__time">' +
        escapeHtml(item.time || '') +
        '</span>' +
        '<span class="fp-resv-arrivals__table">' +
        escapeHtml(item.table_label || '') +
        '</span>' +
        '<span class="fp-resv-arrivals__party">' +
        escapeHtml(String(item.party || 0)) +
        ' px</span>';

      var body = document.createElement('div');
      body.className = 'fp-resv-arrivals__card-body';

      var guestName = document.createElement('p');
      guestName.className = 'fp-resv-arrivals__guest';
      guestName.textContent = item.guest || '';
      body.appendChild(guestName);

      if (item.notes) {
        var notes = document.createElement('p');
        notes.className = 'fp-resv-arrivals__notes';
        notes.textContent = item.notes;
        body.appendChild(notes);
      }

      if (item.allergies && item.allergies.length) {
        var badges = document.createElement('ul');
        badges.className = 'fp-resv-arrivals__badges';
        item.allergies.forEach(function (badge) {
          var badgeItem = document.createElement('li');
          badgeItem.textContent = badge;
          badges.appendChild(badgeItem);
        });
        body.appendChild(badges);
      }

      var actions = document.createElement('div');
      actions.className = 'fp-resv-arrivals__actions';
      actions.appendChild(createActionButton(item, 'confirm'));
      actions.appendChild(createActionButton(item, 'visited'));
      actions.appendChild(createActionButton(item, 'no-show'));
      actions.appendChild(createActionButton(item, 'move'));

      card.appendChild(header);
      card.appendChild(body);
      card.appendChild(actions);

      list.appendChild(card);
    });

    toggle(empty, false);
    toggle(list, true);
  }

  function createActionButton(item, action) {
    var labelKey =
      action === 'confirm'
        ? 'actionConfirm'
        : action === 'visited'
        ? 'actionVisited'
        : action === 'no-show'
        ? 'actionNoShow'
        : 'actionMove';

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-small';
    button.textContent = strings[labelKey] || action;
    button.addEventListener('click', function () {
      handleAction(item, action);
    });

    return button;
  }

  function handleAction(item, action) {
    if (!item || !item.id) {
      return;
    }

    if (action === 'move') {
      window.alert(strings.drawerPlaceholder || 'Funzionalità in sviluppo.');
      return;
    }

    var payload = {};
    if (action === 'confirm') {
      payload.status = 'confirmed';
    }
    if (action === 'visited') {
      payload.status = 'visited';
      payload.visited = true;
    }
    if (action === 'no-show') {
      payload.status = 'no-show';
    }

    request('/fp-resv/v1/agenda/reservations/' + item.id, {
      method: 'POST',
      data: payload,
    })
      .then(function () {
        announce(strings.agendaMoveSuccess || 'Prenotazione aggiornata.');
        loadArrivals();
      })
      .catch(function () {
        announce(strings.arrivalsError || 'Aggiornamento non riuscito');
      });
  }

  function loadArrivals() {
    if (empty) {
      empty.textContent = strings.arrivalsLoading || 'Caricamento…';
      toggle(empty, true);
    }

    var params = {
      range: range,
    };

    if (roomFilter && roomFilter.value.trim() !== '') {
      params.room = roomFilter.value.trim();
    }
    if (statusFilter && statusFilter.value.trim() !== '') {
      params.status = statusFilter.value.trim();
    }

    var query = buildQuery(params);

    request('/fp-resv/v1/reservations/arrivals?' + query)
      .then(function (response) {
        var reservations = (response && response.reservations) || [];
        renderArrivals(reservations);
      })
      .catch(function () {
        if (empty) {
          empty.textContent = strings.arrivalsError || 'Errore durante il caricamento';
          toggle(empty, true);
        }
        if (list) {
          toggle(list, false);
        }
      });
  }

  if (reload) {
    reload.addEventListener('click', function () {
      loadArrivals();
    });
  }

  loadArrivals();
}
