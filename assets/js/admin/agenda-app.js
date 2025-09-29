/**
 * FP Restaurant Reservations - Admin Agenda SPA bootstrap.
 *
 * This placeholder avoids shipping compiled assets while keeping the admin
 * agenda page functional. The rich drag & drop experience will be implemented
 * in upcoming phases.
 */

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

  var arrivalsSection = container.querySelector('[data-role="arrivals"]');

  if (activeTab === 'arrivi-oggi' || activeTab === 'settimana') {
    if (arrivalsSection) {
      initArrivals(arrivalsSection, activeTab === 'settimana' ? 'week' : 'today');
    }

    return;
  }

  renderPlaceholder(container, strings);

  function renderPlaceholder(target, i18n) {
    var headline = i18n.headline || 'Agenda in arrivo';
    var description =
      i18n.description ||
      "L'interfaccia drag & drop dell'agenda verrà caricata qui nelle fasi successive.";
    var cta =
      i18n.cta ||
      'Le API REST sono già disponibili: questo spazio mostrerà i dati delle prenotazioni.';

    var info = document.createElement('div');
    info.className = 'fp-resv-agenda-placeholder';
    info.innerHTML =
      '<h2>' +
      headline +
      '</h2><p>' +
      description +
      "</p><p class=\"description\">" +
      cta +
      '</p>';

    target.appendChild(info);
  }

  function initArrivals(section, range) {
    section.hidden = false;

    var title = section.querySelector('[data-role="arrivals-title"]');
    var list = section.querySelector('[data-role="arrivals-list"]');
    var empty = section.querySelector('[data-role="arrivals-empty"]');
    var reload = section.querySelector('[data-action="arrivals-reload"]');
    var roomFilter = section.querySelector('[data-role="arrivals-room"]');
    var statusFilter = section.querySelector('[data-role="arrivals-status"]');

    if (title) {
      var baseTitle = strings.arrivalsTitle || 'Prenotazioni in arrivo';
      title.textContent =
        range === 'week' ? baseTitle + ' · 7 giorni' : baseTitle + ' · Oggi';
    }

    function speak(message) {
      if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
        window.wp.a11y.speak(message);
      }
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

    function request(path, options) {
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
        return window.wp.apiFetch({
          path: path,
          method: options.method || 'GET',
          data: options.data,
          headers: headers,
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
        if (!response.ok) {
          throw new Error('Request failed: ' + response.status);
        }
        return response.json();
      });
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
          speak('Prenotazione aggiornata');
          loadArrivals();
        })
        .catch(function () {
          speak('Aggiornamento non riuscito');
        });
    }

    function escapeHtml(value) {
      var div = document.createElement('div');
      div.textContent = value || '';

      return div.innerHTML;
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
          renderArrivals((response && response.reservations) || []);
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
})();
