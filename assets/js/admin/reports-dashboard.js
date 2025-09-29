(function () {
  if (typeof window === 'undefined') {
    return;
  }

  var settings = window.fpResvReportsSettings || {};
  var root = document.getElementById('fp-resv-reports-app');
  if (!root) {
    return;
  }

  var state = {
    start: settings.defaultRange ? settings.defaultRange.start : '',
    end: settings.defaultRange ? settings.defaultRange.end : '',
    logs: {
      channel: 'mail',
      status: '',
      search: '',
      page: 1,
      perPage: 25,
    },
  };

  var dateStartInput = root.querySelector('[data-role="date-start"]');
  var dateEndInput = root.querySelector('[data-role="date-end"]');
  var summaryTable = root.querySelector('[data-role="summary-table"]');
  var summaryBody = root.querySelector('[data-role="summary-body"]');
  var summaryEmpty = root.querySelector('[data-role="summary-empty"]');
  var summaryLoading = root.querySelector('[data-role="summary-loading"]');
  var logsTable = root.querySelector('[data-role="logs-table"]');
  var logsHead = root.querySelector('[data-role="logs-head"]');
  var logsBody = root.querySelector('[data-role="logs-body"]');
  var logsEmpty = root.querySelector('[data-role="logs-empty"]');
  var logsLoading = root.querySelector('[data-role="logs-loading"]');
  var logsChannel = root.querySelector('[data-role="log-channel"]');
  var logsStatus = root.querySelector('[data-role="log-status"]');
  var logsSearch = root.querySelector('[data-role="log-search"]');
  var logsPagination = root.querySelector('[data-role="logs-pagination"]');
  var logsCount = root.querySelector('[data-role="logs-count"]');
  var logsPageInput = root.querySelector('[data-role="logs-page"]');
  var logsTotalPages = root.querySelector('[data-role="logs-total-pages"]');

  if (dateStartInput && state.start) {
    dateStartInput.value = state.start;
  }

  if (dateEndInput && state.end) {
    dateEndInput.value = state.end;
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

  function formatPercent(value) {
    var number = parseFloat(value);
    if (!number) {
      return '0%';
    }
    return number.toFixed(2).replace(/\.00$/, '') + '%';
  }

  function formatCurrencyBucket(bucket, currency) {
    if (!bucket) {
      return '';
    }

    var label = currency;
    var deposit = parseFloat(bucket.deposit_amount);
    var total = parseFloat(bucket.amount);
    var amount = deposit && deposit > 0 ? deposit : total;

    return label + ' ' + amount.toFixed(2);
  }

  function renderSummary(data) {
    if (!summaryTable || !summaryBody || !summaryEmpty) {
      return;
    }

    summaryBody.innerHTML = '';

    if (!data || !data.length) {
      toggle(summaryTable, false);
      toggle(summaryEmpty, true);
      return;
    }

    data.forEach(function (item) {
      var row = document.createElement('tr');
      var payments = item.payments && item.payments.by_currency ? item.payments.by_currency : {};
      var currencies = Object.keys(payments);
      var paymentsLabel = '—';

      if (currencies.length) {
        paymentsLabel = currencies
          .map(function (currency) {
            return formatCurrencyBucket(payments[currency], currency);
          })
          .join('\n');
      }

      var columns = [
        item.date || '',
        item.reservations ? String(item.reservations.total || 0) : '0',
        item.covers ? String(item.covers.total || 0) : '0',
        item.covers ? Number(item.covers.avg_per_reservation || 0).toFixed(2) : '0.00',
        item.reservations ? formatPercent(item.reservations.visit_rate || 0) : '0%',
        item.reservations ? formatPercent(item.reservations.no_show_rate || 0) : '0%',
        paymentsLabel,
      ];

      columns.forEach(function (value, index) {
        var cell = document.createElement('td');
        if (index === columns.length - 1) {
          cell.textContent = '';
          value.split('\n').forEach(function (line, i) {
            if (i > 0) {
              cell.appendChild(document.createElement('br'));
            }
            cell.appendChild(document.createTextNode(line));
          });
        } else {
          cell.textContent = value;
        }
        row.appendChild(cell);
      });

      summaryBody.appendChild(row);
    });

    toggle(summaryEmpty, false);
    toggle(summaryTable, true);
  }

  function renderLogs(response) {
    if (!logsTable || !logsHead || !logsBody) {
      return;
    }

    var entries = (response && response.entries) || [];
    var pagination = response && response.pagination ? response.pagination : {};

    logsHead.innerHTML = '';
    logsBody.innerHTML = '';

    if (!entries.length) {
      toggle(logsTable, false);
      toggle(logsEmpty, true);
      toggle(logsPagination, false);
      return;
    }

    var columns = Object.keys(entries[0]);

    columns.forEach(function (column) {
      var th = document.createElement('th');
      th.textContent = column.replace(/_/g, ' ');
      logsHead.appendChild(th);
    });

    entries.forEach(function (entry) {
      var tr = document.createElement('tr');
      columns.forEach(function (column) {
        var td = document.createElement('td');
        var value = entry[column];
        if (value === null || value === undefined || value === '') {
          td.textContent = '—';
        } else if (typeof value === 'object') {
          td.textContent = JSON.stringify(value);
        } else {
          td.textContent = String(value);
        }
        tr.appendChild(td);
      });
      logsBody.appendChild(tr);
    });

    if (logsCount && pagination.total !== undefined) {
      logsCount.textContent = pagination.total + ' elementi';
    }

    if (logsPageInput) {
      logsPageInput.value = pagination.page || 1;
    }

    if (logsTotalPages) {
      logsTotalPages.textContent = pagination.total_pages || 1;
    }

    toggle(logsEmpty, false);
    toggle(logsTable, true);
    toggle(logsPagination, true);
  }

  function handleError(error) {
    console.error('FP Reports error:', error);
    speak('Errore durante il caricamento dei dati');
  }

  function reloadSummary() {
    toggle(summaryLoading, true);
    toggle(summaryEmpty, false);
    toggle(summaryTable, false);

    var query = buildQuery({
      start: state.start,
      end: state.end,
    });

    request('fp-resv/v1/reports/daily' + (query ? '?' + query : ''))
      .then(function (response) {
        renderSummary(response.summary || []);
      })
      .catch(handleError)
      .finally(function () {
        toggle(summaryLoading, false);
      });
  }

  function reloadLogs() {
    toggle(logsLoading, true);
    toggle(logsEmpty, false);
    toggle(logsTable, false);

    var query = buildQuery({
      channel: state.logs.channel,
      status: state.logs.status,
      search: state.logs.search,
      page: state.logs.page,
      per_page: state.logs.perPage,
      from: state.start,
      to: state.end,
    });

    request('fp-resv/v1/reports/logs' + (query ? '?' + query : ''))
      .then(function (response) {
        renderLogs(response);
      })
      .catch(handleError)
      .finally(function () {
        toggle(logsLoading, false);
      });
  }

  function parsePage(input) {
    var value = parseInt(input, 10);
    if (isNaN(value) || value < 1) {
      return 1;
    }
    return value;
  }

  function attachEvents() {
    var reloadButton = root.querySelector('[data-action="reload"]');
    if (reloadButton) {
      reloadButton.addEventListener('click', function () {
        if (dateStartInput) {
          state.start = dateStartInput.value;
        }
        if (dateEndInput) {
          state.end = dateEndInput.value;
        }
        state.logs.page = 1;
        reloadSummary();
        reloadLogs();
      });
    }

    var logsReload = root.querySelector('[data-action="logs-reload"]');
    if (logsReload) {
      logsReload.addEventListener('click', function () {
        state.logs.status = logsStatus ? logsStatus.value : '';
        state.logs.search = logsSearch ? logsSearch.value : '';
        state.logs.page = 1;
        reloadLogs();
      });
    }

    if (logsChannel) {
      logsChannel.addEventListener('change', function (event) {
        state.logs.channel = event.target.value;
        state.logs.page = 1;
        reloadLogs();
      });
    }

    if (logsPagination) {
      logsPagination.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.dataset || !target.dataset.page) {
          return;
        }
        var action = target.dataset.page;
        var total = logsTotalPages ? parseInt(logsTotalPages.textContent || '1', 10) : 1;
        if (action === 'first') {
          state.logs.page = 1;
        } else if (action === 'prev') {
          state.logs.page = Math.max(1, state.logs.page - 1);
        } else if (action === 'next') {
          state.logs.page = Math.min(total, state.logs.page + 1);
        } else if (action === 'last') {
          state.logs.page = total;
        }
        reloadLogs();
      });
    }

    if (logsPageInput) {
      logsPageInput.addEventListener('change', function (event) {
        var requested = parsePage(event.target.value);
        state.logs.page = requested;
        reloadLogs();
      });
    }

    root.querySelectorAll('[data-export]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        var format = event.target.getAttribute('data-export');
        triggerExport(format || 'csv');
      });
    });
  }

  function triggerExport(format) {
    var query = buildQuery({
      from: state.start,
      to: state.end,
      format: format,
    });

    request('fp-resv/v1/reports/export' + (query ? '?' + query : ''))
      .then(function (response) {
        if (!response || !response.content) {
          throw new Error('Empty export payload');
        }

        var binary = window.atob(response.content);
        var length = binary.length;
        var bytes = new Uint8Array(length);
        for (var i = 0; i < length; i += 1) {
          bytes[i] = binary.charCodeAt(i);
        }

        var blob = new Blob([bytes], { type: response.mime_type || 'text/csv' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = response.filename || 'export.csv';
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
          URL.revokeObjectURL(link.href);
          document.body.removeChild(link);
        }, 0);

        var readyMessage = (settings.i18n && settings.i18n.downloadReady) || 'Download pronto';
        speak(readyMessage);
      })
      .catch(function (error) {
        handleError(error);
        var failMessage = (settings.i18n && settings.i18n.downloadFailed) || 'Esportazione non riuscita. Riprova.';
        if (typeof window.alert === 'function') {
          window.alert(failMessage);
        }
      });
  }

  attachEvents();
  reloadSummary();
  reloadLogs();
})();
