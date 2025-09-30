(function (window, document) {
    'use strict';

    const settings = window.fpResvDiagnosticsSettings || null;
    const root = document.querySelector('[data-role="diagnostics-app"]');

    if (!settings || !root) {
        return;
    }

    const apiFetch = window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;
    if (apiFetch) {
        if (settings.nonce) {
            apiFetch.use(apiFetch.createNonceMiddleware(settings.nonce));
        }

        if (settings.restRoot) {
            apiFetch.use(apiFetch.createRootURLMiddleware(settings.restRoot));
        }
    }

    const filtersEl = root.previousElementSibling && root.previousElementSibling.matches('.fp-resv-admin__toolbar')
        ? root.previousElementSibling.querySelector('[data-role="filters"]')
        : document.querySelector('[data-role="filters"]');

    const elements = {
        tabs: root.querySelector('[data-role="tabs"]'),
        loading: root.querySelector('[data-role="loading"]'),
        empty: root.querySelector('[data-role="empty"]'),
        tableHead: root.querySelector('[data-role="table-head"]'),
        tableBody: root.querySelector('[data-role="table-body"]'),
        tableWrapper: root.querySelector('[data-role="table-wrapper"]'),
        pagination: root.querySelector('[data-role="pagination"]'),
        pageIndicator: root.querySelector('[data-role="page-indicator"]'),
        live: root.querySelector('[data-role="live"]'),
        reload: filtersEl ? filtersEl.querySelector('[data-action="reload"]') : null,
        exportBtn: filtersEl ? filtersEl.querySelector('[data-action="export"]') : null,
        start: filtersEl ? filtersEl.querySelector('[data-role="date-start"]') : null,
        end: filtersEl ? filtersEl.querySelector('[data-role="date-end"]') : null,
        status: filtersEl ? filtersEl.querySelector('[data-role="status"]') : null,
        search: filtersEl ? filtersEl.querySelector('[data-role="search"]') : null,
        prev: null,
        next: null,
    };

    if (elements.pagination) {
        elements.prev = elements.pagination.querySelector('[data-action="prev"]');
        elements.next = elements.pagination.querySelector('[data-action="next"]');
    }

    const channelKeys = Object.keys(settings.channels || {});
    const state = {
        channel: channelKeys.length ? channelKeys[0] : null,
        page: 1,
        perPage: 25,
        totalPages: 0,
        loading: false,
    };

    function setLoading(isLoading) {
        state.loading = isLoading;
        if (elements.loading) {
            elements.loading.hidden = !isLoading;
        }
        if (elements.reload) {
            elements.reload.disabled = isLoading;
        }
        if (elements.exportBtn) {
            elements.exportBtn.disabled = isLoading;
        }
        if (elements.prev) {
            elements.prev.disabled = isLoading || state.page <= 1;
        }
        if (elements.next) {
            elements.next.disabled = isLoading || state.page >= state.totalPages;
        }
    }

    function announce(message) {
        if (!elements.live) {
            return;
        }
        elements.live.textContent = message || '';
    }

    function populateStatusOptions() {
        if (!elements.status || !state.channel) {
            return;
        }

        const definitions = settings.channels[state.channel];
        const options = definitions ? definitions.statuses || [] : [];

        elements.status.innerHTML = '';
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = settings.i18n ? (settings.i18n.statusLabel || 'Stato') : 'Stato';
        elements.status.appendChild(emptyOption);

        options.forEach(function (option) {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.label;
            elements.status.appendChild(opt);
        });
    }

    function buildTabs() {
        if (!elements.tabs || channelKeys.length === 0) {
            return;
        }

        elements.tabs.innerHTML = '';
        channelKeys.forEach(function (key, index) {
            const definition = settings.channels[key];
            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('role', 'tab');
            button.dataset.channel = key;
            button.textContent = definition ? definition.label : key;
            button.setAttribute('aria-selected', state.channel === key ? 'true' : 'false');
            button.addEventListener('click', function () {
                if (state.channel === key || state.loading) {
                    return;
                }

                state.channel = key;
                state.page = 1;
                updateTabSelection();
                populateStatusOptions();
                fetchLogs();
            });

            if (index === 0) {
                button.setAttribute('tabindex', '0');
            }

            elements.tabs.appendChild(button);
        });
    }

    function updateTabSelection() {
        if (!elements.tabs) {
            return;
        }

        elements.tabs.querySelectorAll('button[role="tab"]').forEach(function (button) {
            const selected = button.dataset.channel === state.channel;
            button.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
    }

    function readFilters() {
        return {
            start: elements.start && elements.start.value ? elements.start.value : (settings.defaultRange ? settings.defaultRange.start : ''),
            end: elements.end && elements.end.value ? elements.end.value : (settings.defaultRange ? settings.defaultRange.end : ''),
            status: elements.status && elements.status.value ? elements.status.value : '',
            search: elements.search && elements.search.value ? elements.search.value.trim() : '',
        };
    }

    function applyDefaultDates() {
        if (elements.start && settings.defaultRange && settings.defaultRange.start) {
            elements.start.value = settings.defaultRange.start;
        }
        if (elements.end && settings.defaultRange && settings.defaultRange.end) {
            elements.end.value = settings.defaultRange.end;
        }
    }

    function renderTable(columns, entries) {
        if (!elements.tableHead || !elements.tableBody) {
            return;
        }

        elements.tableHead.innerHTML = '';
        elements.tableBody.innerHTML = '';

        const headRow = document.createElement('tr');
        columns.forEach(function (column) {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = column.label || column.key;
            headRow.appendChild(th);
        });
        elements.tableHead.appendChild(headRow);

        entries.forEach(function (entry) {
            const row = document.createElement('tr');
            columns.forEach(function (column) {
                const td = document.createElement('td');
                const key = column.key;
                const value = entry && Object.prototype.hasOwnProperty.call(entry, key) ? entry[key] : '';
                const stringValue = value === null || typeof value === 'undefined' ? '' : String(value);
                if (key === 'status') {
                    td.dataset.status = stringValue.toLowerCase();
                }
                td.textContent = stringValue;
                row.appendChild(td);
            });
            elements.tableBody.appendChild(row);
        });
    }

    function renderPagination(pagination) {
        state.totalPages = pagination.total_pages || 0;
        const hasMultiple = state.totalPages > 1;

        if (elements.prev) {
            elements.prev.disabled = state.loading || state.page <= 1;
        }
        if (elements.next) {
            elements.next.disabled = state.loading || state.page >= state.totalPages;
        }
        if (elements.pagination) {
            elements.pagination.hidden = !hasMultiple;
        }
        if (elements.pageIndicator) {
            if (!hasMultiple) {
                elements.pageIndicator.textContent = '';
            } else {
                const template = settings.i18n && settings.i18n.pagination && settings.i18n.pagination.page
                    ? settings.i18n.pagination.page
                    : 'Pagina %d di %d';
                elements.pageIndicator.textContent = template.replace('%d', state.page).replace('%d', state.totalPages);
            }
        }
    }

    function buildQuery(params) {
        const query = new URLSearchParams();
        Object.keys(params).forEach(function (key) {
            const value = params[key];
            if (value === null || value === '' || (typeof value === 'number' && Number.isNaN(value))) {
                return;
            }
            query.append(key, String(value));
        });

        return query.toString();
    }

    function fetchLogs() {
        if (!state.channel) {
            return;
        }

        if (!apiFetch) {
            announce(settings.i18n ? settings.i18n.error : 'API non disponibile.');
            return;
        }

        const filters = readFilters();
        const params = {
            channel: state.channel,
            page: state.page,
            per_page: state.perPage,
            status: filters.status,
            from: filters.start,
            to: filters.end,
            search: filters.search,
        };

        setLoading(true);
        announce(settings.i18n ? settings.i18n.loading : 'Caricamento…');

        apiFetch({
            path: '/fp-resv/v1/diagnostics/logs?' + buildQuery(params),
            method: 'GET',
        })
            .then(function (response) {
                setLoading(false);
                if (!response || !response.entries) {
                    throw new Error('invalid_response');
                }

                const columns = response.columns || [];
                const entries = response.entries || [];

                renderTable(columns, entries);
                renderPagination(response.pagination || { total_pages: 0 });

                if (entries.length === 0) {
                    if (elements.empty) {
                        elements.empty.hidden = false;
                    }
                    announce(settings.i18n ? settings.i18n.empty : 'Nessun log trovato.');
                } else if (elements.empty) {
                    elements.empty.hidden = true;
                    announce('');
                }
            })
            .catch(function () {
                setLoading(false);
                if (elements.empty) {
                    elements.empty.hidden = false;
                    elements.empty.textContent = settings.i18n ? settings.i18n.error : 'Errore di caricamento.';
                }
                announce(settings.i18n ? settings.i18n.error : 'Errore di caricamento.');
            });
    }

    function exportCsv() {
        if (!state.channel || !apiFetch || state.loading) {
            return;
        }

        const filters = readFilters();
        const params = {
            channel: state.channel,
            status: filters.status,
            from: filters.start,
            to: filters.end,
            search: filters.search,
            per_page: 500,
        };

        setLoading(true);
        apiFetch({
            path: '/fp-resv/v1/diagnostics/export?' + buildQuery(params),
            method: 'GET',
        })
            .then(function (response) {
                setLoading(false);
                if (!response || !response.content) {
                    throw new Error('invalid_export');
                }

                const binary = window.atob(response.content);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i += 1) {
                    bytes[i] = binary.charCodeAt(i);
                }

                const blob = new Blob([bytes], { type: response.mime_type || 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = response.filename || 'fp-reservations-diagnostics.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                announce(settings.i18n ? settings.i18n.downloadReady : 'Esportazione pronta.');
            })
            .catch(function () {
                setLoading(false);
                announce(settings.i18n ? settings.i18n.downloadFailed : 'Esportazione non riuscita.');
            });
    }

    if (elements.reload) {
        elements.reload.addEventListener('click', function () {
            state.page = 1;
            fetchLogs();
        });
    }

    if (elements.exportBtn) {
        elements.exportBtn.addEventListener('click', exportCsv);
    }

    if (elements.prev) {
        elements.prev.addEventListener('click', function () {
            if (state.loading || state.page <= 1) {
                return;
            }
            state.page -= 1;
            fetchLogs();
        });
    }

    if (elements.next) {
        elements.next.addEventListener('click', function () {
            if (state.loading || state.page >= state.totalPages) {
                return;
            }
            state.page += 1;
            fetchLogs();
        });
    }

    applyDefaultDates();
    buildTabs();
    populateStatusOptions();
    fetchLogs();
})(window, document);
