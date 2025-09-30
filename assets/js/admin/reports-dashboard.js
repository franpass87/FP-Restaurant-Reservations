(function () {
  if (typeof window === 'undefined') {
    return;
  }

  var settings = window.fpResvAnalyticsSettings || {};
  var root = document.querySelector('[data-role="analytics-app"]');
  if (!root) {
    return;
  }

  var startInput = document.querySelector('[data-role="date-start"]');
  var endInput = document.querySelector('[data-role="date-end"]');
  var locationSelect = document.querySelector('[data-role="location"]');
  var reloadButton = document.querySelector('[data-action="reload"]');
  var exportButton = document.querySelector('[data-action="export"]');
  var loadingEl = root.querySelector('[data-role="loading"]');
  var emptyEl = root.querySelector('[data-role="empty"]');
  var summaryReservations = root.querySelector('[data-role="summary-reservations"]');
  var summaryCovers = root.querySelector('[data-role="summary-covers"]');
  var summaryValue = root.querySelector('[data-role="summary-value"]');
  var summaryAvgParty = root.querySelector('[data-role="summary-avg-party"]');
  var summaryAvgTicket = root.querySelector('[data-role="summary-avg-ticket"]');
  var channelsCanvas = root.querySelector('[data-role="channels-chart"]');
  var trendCanvas = root.querySelector('[data-role="trend-chart"]');
  var tableBody = root.querySelector('[data-role="sources-body"]');
  var tableEmpty = root.querySelector('[data-role="sources-empty"]');
  var liveRegion = root.querySelector('[data-role="live-region"]');

  var channelChart = null;
  var trendChart = null;
  var isLoading = false;

  var state = {
    start: settings.defaultRange ? settings.defaultRange.start : '',
    end: settings.defaultRange ? settings.defaultRange.end : '',
    location: '',
    currency: '',
  };

  populateLocations();
  initialiseInputs();
  bindEvents();
  fetchAnalytics();

  function bindEvents() {
    if (reloadButton) {
      reloadButton.addEventListener('click', function () {
        state.start = startInput ? startInput.value : state.start;
        state.end = endInput ? endInput.value : state.end;
        state.location = locationSelect ? locationSelect.value : state.location;
        fetchAnalytics();
      });
    }

    if (exportButton) {
      exportButton.addEventListener('click', handleExport);
    }
  }

  function populateLocations() {
    if (!locationSelect || !Array.isArray(settings.locations)) {
      return;
    }

    settings.locations.forEach(function (item) {
      if (!item || typeof item.id !== 'string') {
        return;
      }

      var option = document.createElement('option');
      option.value = item.id;
      option.textContent = item.label || item.id;
      locationSelect.appendChild(option);
    });
  }

  function initialiseInputs() {
    if (startInput && state.start) {
      startInput.value = state.start;
    }
    if (endInput && state.end) {
      endInput.value = state.end;
    }
    if (locationSelect) {
      state.location = locationSelect.value || state.location;
    }
  }

  function request(path, data) {
    var headers = {};
    if (settings.nonce) {
      headers['X-WP-Nonce'] = settings.nonce;
    }

    if (window.wp && window.wp.apiFetch) {
      return window.wp.apiFetch({
        path: path,
        method: 'GET',
        data: data,
        headers: headers,
      });
    }

    var base = (settings.restRoot || '').replace(/\/$/, '');
    var url = path.indexOf('http') === 0 ? path : base + '/' + path.replace(/^\//, '');
    if (data) {
      var query = new URLSearchParams();
      Object.keys(data).forEach(function (key) {
        var value = data[key];
        if (value !== undefined && value !== null && value !== '') {
          query.append(key, value);
        }
      });
      if (query.toString()) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + query.toString();
      }
    }

    return fetch(url, {
      method: 'GET',
      headers: headers,
      credentials: 'same-origin',
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Request failed: ' + response.status);
      }
      return response.json();
    });
  }

  function setLoading(loading) {
    isLoading = loading;
    if (loadingEl) {
      loadingEl.hidden = !loading;
    }
    if (reloadButton) {
      reloadButton.disabled = loading;
    }
    if (exportButton) {
      exportButton.disabled = loading;
    }
  }

  function fetchAnalytics() {
    if (isLoading) {
      return;
    }

    setLoading(true);
    toggleEmpty(false);

    request('/fp-resv/v1/reports/analytics', {
      start: state.start,
      end: state.end,
      location: state.location,
    })
      .then(function (response) {
        var analytics = response && response.analytics ? response.analytics : null;
        if (!analytics) {
          toggleEmpty(true);
          resetSummary();
          destroyCharts();
          clearTable();
          speak(settings.i18n ? settings.i18n.empty : '');
          return;
        }

        if (analytics.range) {
          state.start = analytics.range.start || state.start;
          state.end = analytics.range.end || state.end;
          if (startInput && state.start) {
            startInput.value = state.start;
          }
          if (endInput && state.end) {
            endInput.value = state.end;
          }
        }

        state.currency = analytics.summary ? analytics.summary.currency || '' : '';
        renderSummary(analytics.summary || {});
        renderChannels(analytics.channels || []);
        renderTrend(analytics.trend || []);
        renderTable(analytics.topSources || []);
        var hasData = false;
        if (analytics.summary && analytics.summary.reservations) {
          hasData = analytics.summary.reservations > 0;
        }
        if (!hasData && Array.isArray(analytics.trend)) {
          hasData = analytics.trend.length > 0;
        }
        toggleEmpty(!hasData);

        speak(settings.i18n ? settings.i18n.title : '');
      })
      .catch(function () {
        toggleEmpty(true);
        resetSummary();
        destroyCharts();
        clearTable();
        speak(settings.i18n ? settings.i18n.downloadFailed : '');
      })
      .finally(function () {
        setLoading(false);
      });
  }

  function toggleEmpty(isEmpty) {
    if (emptyEl) {
      emptyEl.hidden = !isEmpty;
    }
  }

  function resetSummary() {
    updateText(summaryReservations, '0');
    updateText(summaryCovers, '0');
    updateText(summaryValue, formatCurrency(0));
    updateText(summaryAvgParty, '0');
    updateText(summaryAvgTicket, formatCurrency(0));
  }

  function renderSummary(summary) {
    if (!summary) {
      resetSummary();
      return;
    }

    updateText(summaryReservations, formatNumber(summary.reservations || 0));
    updateText(summaryCovers, formatNumber(summary.covers || 0));
    updateText(summaryValue, formatCurrency(summary.value || 0));
    updateText(summaryAvgParty, formatDecimal(summary.avg_party || 0));
    updateText(summaryAvgTicket, formatCurrency(summary.avg_ticket || 0));
  }

  function renderChannels(channels) {
    if (!channelsCanvas || typeof window.Chart === 'undefined') {
      return;
    }

    var labels = [];
    var data = [];

    if (!Array.isArray(channels) || channels.length === 0) {
      destroyChart('channel');
      return;
    }

    channels.forEach(function (channel) {
      if (!channel) {
        return;
      }
      var label = resolveChannelLabel(channel.channel || 'other');
      labels.push(label);
      data.push(channel.reservations || 0);
    });

    var palette = ['#4361EE', '#3A0CA3', '#F72585', '#4CC9F0', '#f8961e', '#9b5de5', '#2ec4b6'];

    if (channelChart) {
      channelChart.data.labels = labels;
      channelChart.data.datasets[0].data = data;
      channelChart.update();
      return;
    }

    channelChart = new window.Chart(channelsCanvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: palette,
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 14,
              font: {
                family: 'Inter, system-ui, sans-serif',
              },
            },
          },
        },
      },
    });
  }

  function renderTrend(trend) {
    if (!trendCanvas || typeof window.Chart === 'undefined') {
      return;
    }

    if (!Array.isArray(trend) || trend.length === 0) {
      destroyChart('trend');
      return;
    }

    var labels = trend.map(function (item) {
      return item.date;
    });
    var reservations = trend.map(function (item) {
      return item.reservations || 0;
    });
    var covers = trend.map(function (item) {
      return item.covers || 0;
    });

    if (trendChart) {
      trendChart.data.labels = labels;
      trendChart.data.datasets[0].data = reservations;
      trendChart.data.datasets[1].data = covers;
      trendChart.update();
      return;
    }

    trendChart = new window.Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: settings.i18n ? settings.i18n.reservationsLabel : 'Prenotazioni',
            data: reservations,
            borderColor: '#4361EE',
            backgroundColor: 'rgba(67, 97, 238, 0.16)',
            tension: 0.35,
            fill: true,
            pointRadius: 2,
          },
          {
            label: settings.i18n ? settings.i18n.coversLabel : 'Coperti',
            data: covers,
            borderColor: '#2EC4B6',
            backgroundColor: 'rgba(46, 196, 182, 0.16)',
            tension: 0.25,
            fill: true,
            pointRadius: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              font: {
                family: 'Inter, system-ui, sans-serif',
              },
            },
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                var value = context.parsed.y || 0;
                return context.dataset.label + ': ' + formatNumber(value);
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return formatNumber(value);
              },
            },
          },
        },
      },
    });
  }

  function renderTable(rows) {
    if (!tableBody || !tableEmpty) {
      return;
    }

    tableBody.innerHTML = '';

    if (!Array.isArray(rows) || rows.length === 0) {
      tableEmpty.hidden = false;
      return;
    }

    tableEmpty.hidden = true;

    rows.slice(0, 25).forEach(function (row) {
      var tr = document.createElement('tr');
      appendCell(tr, row.source || 'direct');
      appendCell(tr, row.medium || '');
      appendCell(tr, row.campaign || '');
      appendCell(tr, formatNumber(row.reservations || 0));
      appendCell(tr, formatNumber(row.covers || 0));
      appendCell(tr, formatCurrency(row.value || 0));
      appendCell(tr, formatDecimal(row.share || 0) + '%');
      tableBody.appendChild(tr);
    });
  }

  function appendCell(row, value) {
    var td = document.createElement('td');
    td.textContent = value;
    row.appendChild(td);
  }

  function clearTable() {
    if (tableBody) {
      tableBody.innerHTML = '';
    }
    if (tableEmpty) {
      tableEmpty.hidden = false;
    }
  }

  function destroyCharts() {
    destroyChart('channel');
    destroyChart('trend');
  }

  function destroyChart(type) {
    if (type === 'channel' && channelChart) {
      channelChart.destroy();
      channelChart = null;
    }
    if (type === 'trend' && trendChart) {
      trendChart.destroy();
      trendChart = null;
    }
  }

  function handleExport() {
    if (isLoading) {
      return;
    }

    setLoading(true);

    request('/fp-resv/v1/reports/analytics/export', {
      start: state.start,
      end: state.end,
      location: state.location,
    })
      .then(function (response) {
        if (!response || !response.content) {
          speak(settings.i18n ? settings.i18n.downloadFailed : '');
          return;
        }

        var filename = response.filename || 'fp-analytics.csv';
        downloadBase64(response.content, response.mime_type || 'text/csv', filename);
        speak(settings.i18n ? settings.i18n.downloadReady : '');
      })
      .catch(function () {
        speak(settings.i18n ? settings.i18n.downloadFailed : '');
      })
      .finally(function () {
        setLoading(false);
      });
  }

  function downloadBase64(content, mimeType, filename) {
    try {
      var binary = window.atob(content);
      var length = binary.length;
      var bytes = new Uint8Array(length);
      for (var i = 0; i < length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
      }

      var blob = new Blob([bytes], { type: mimeType });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      setTimeout(function () {
        URL.revokeObjectURL(link.href);
        document.body.removeChild(link);
      }, 0);
    } catch (error) {
      speak(settings.i18n ? settings.i18n.downloadFailed : '');
    }
  }

  function updateText(element, value) {
    if (!element) {
      return;
    }
    element.textContent = value;
  }

  function resolveChannelLabel(channel) {
    var labels = settings.i18n && settings.i18n.channelLabels ? settings.i18n.channelLabels : {};
    return labels[channel] || channel;
  }

  function formatNumber(value) {
    var number = Number(value) || 0;
    try {
      return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(number);
    } catch (error) {
      return String(Math.round(number));
    }
  }

  function formatDecimal(value) {
    var number = Number(value) || 0;
    try {
      return new Intl.NumberFormat(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(number);
    } catch (error) {
      return number.toFixed(2);
    }
  }

  function formatCurrency(value) {
    var number = Number(value) || 0;
    var currency = state.currency || 'EUR';
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }).format(number);
    } catch (error) {
      return currency + ' ' + formatDecimal(number);
    }
  }

  function speak(message) {
    if (!message) {
      return;
    }

    if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
      window.wp.a11y.speak(message);
      return;
    }

    if (liveRegion) {
      liveRegion.textContent = '';
      window.setTimeout(function () {
        liveRegion.textContent = message;
      }, 50);
    }
  }
})();
