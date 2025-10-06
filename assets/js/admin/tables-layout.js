(function () {
    const root = document.querySelector('[data-fp-resv-tables]');
    if (!root) {
        return;
    }

    const settings = window.fpResvTablesSettings || {};
    const restRoot = ((settings.restRoot || '/wp-json/fp-resv/v1')).replace(/\/$/, '');
    const listRegion = root.querySelector('[data-region="list"]');

    const request = (path, options = {}) => {
        const url = typeof path === 'string' && path.startsWith('http') ? path : `${restRoot}${path}`;
        const config = {
            method: options.method || 'GET',
            headers: {
                'X-WP-Nonce': settings.nonce || '',
            },
            credentials: 'same-origin',
        };
        // Per massima compatibilità con hosting che bloccano JSON, inviamo
        // di default form-url-encoded. Se serve JSON, passare options.json === true.
        if (options.data) {
            if (options.json === true) {
                config.headers['Content-Type'] = 'application/json';
                config.body = JSON.stringify(options.data);
            } else {
                const usp = new URLSearchParams();
                Object.entries(options.data).forEach(([k, v]) => {
                    if (v === undefined || v === null) return;
                    usp.append(String(k), String(v));
                });
                config.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
                config.body = usp.toString();
            }
        }
        return fetch(url, config).then((response) => {
            if (!response.ok) {
                return response.json().catch(() => ({})).then((payload) => {
                    const error = new Error(payload && payload.message ? payload.message : 'Request failed');
                    error.status = response.status;
                    throw error;
                });
            }
            if (response.status === 204) {
                return null;
            }
            return response.text().then((text) => {
                if (!text) return {};
                try { return JSON.parse(text); } catch (e) { return {}; }
            });
        });
    };

    let rooms = Array.isArray(settings.initialState && settings.initialState.rooms) ? settings.initialState.rooms : [];

    const render = () => {
        if (!listRegion) return;
        listRegion.innerHTML = '';
        if (!rooms.length) {
            const p = document.createElement('p');
            p.className = 'fp-resv-tables-empty';
            p.textContent = (settings.strings && settings.strings.empty) || 'Nessuna sala configurata.';
            listRegion.appendChild(p);
            return;
        }
        rooms.forEach((room) => {
            const wrapper = document.createElement('article');
            wrapper.className = 'fp-resv-room-card';
            wrapper.dataset.roomId = String(room.id);
            wrapper.innerHTML = `
                <header style="--room-color:${room.color || '#6b7280'}">
                    <h3>${room.name}</h3>
                    <p>${room.description || ''}</p>
                </header>
                <div class="fp-resv-room-card__actions">
                    <button type="button" class="button" data-action="add-table">Aggiungi tavolo</button>
                    <button type="button" class="button" data-action="edit-room">Modifica sala</button>
                    <button type="button" class="button button-link" data-action="delete-room">Elimina sala</button>
                </div>
                <ul class="fp-resv-table-list" data-role="table-list"></ul>
            `;
            const ul = wrapper.querySelector('[data-role="table-list"]');
            (room.tables || []).forEach((table) => {
                const li = document.createElement('li');
                li.className = 'fp-resv-table-item';
                li.dataset.tableId = String(table.id);
                li.innerHTML = `
                    <span class="fp-resv-table-code">${table.code}</span>
                    <span class="fp-resv-table-meta">${(table.seats_std || 0)} posti · ${table.status || 'available'}</span>
                    <span class="fp-resv-table-actions">
                        <button type="button" class="button button-link" data-action="edit-table">Modifica</button>
                        <button type="button" class="button button-link" data-action="delete-table">Elimina</button>
                    </span>
                `;
                ul.appendChild(li);
            });
            listRegion.appendChild(wrapper);
        });
    };

    const refresh = () => {
        root.dataset.state = 'loading';
        return request('/tables/overview')
            .then((payload) => {
                rooms = Array.isArray(payload.rooms) ? payload.rooms : [];
                render();
            })
            .catch((error) => {
                window.alert((error && error.message) ? error.message : 'Impossibile aggiornare le sale.');
            })
            .finally(() => { root.dataset.state = ''; });
    };

    const createRoom = (form) => {
        const fd = new FormData(form);
        const name = String(fd.get('name') || '').trim();
        const color = String(fd.get('color') || '').trim();
        if (!name) return;
        request('/tables/rooms', { method: 'POST', data: { name, color } })
            .then(() => { form.reset(); return refresh(); })
            .catch((error) => { window.alert((error && error.message) ? error.message : 'Impossibile creare la sala.'); });
    };

    const addTable = (roomId) => {
        const code = window.prompt('Codice tavolo');
        if (!code) return;
        const seats = Number.parseInt(window.prompt('Posti standard', '2') || '2', 10) || 2;
        request('/tables', { method: 'POST', data: { room_id: roomId, code, seats_std: Math.max(1, seats), status: 'available' } })
            .then(refresh)
            .catch((error) => { window.alert((error && error.message) ? error.message : 'Impossibile creare il tavolo.'); });
    };

    const deleteRoom = (roomId) => {
        if (!window.confirm('Eliminare la sala e tutti i tavoli?')) return;
        request(`/tables/rooms/${roomId}`, { method: 'DELETE' })
            .then(refresh)
            .catch((error) => { window.alert((error && error.message) ? error.message : 'Impossibile eliminare la sala.'); });
    };

    const deleteTable = (tableId) => {
        if (!window.confirm('Eliminare il tavolo?')) return;
        request(`/tables/${tableId}`, { method: 'DELETE' })
            .then(refresh)
            .catch((error) => { window.alert((error && error.message) ? error.message : 'Impossibile eliminare il tavolo.'); });
    };

    const updateRoom = (roomId) => {
        const room = rooms.find((r) => Number(r.id) === Number(roomId));
        const currentName = room && room.name ? String(room.name) : '';
        const currentColor = room && room.color ? String(room.color) : '';
        const name = window.prompt('Nome sala', currentName);
        if (!name) return;
        const color = window.prompt('Colore (hex #RRGGBB, opzionale)', currentColor || '#6b7280') || '';
        request(`/tables/rooms/${roomId}`, { method: 'POST', data: { name, color } })
            .then(refresh)
            .catch((error) => { window.alert((error && error.message) ? error.message : 'Impossibile aggiornare la sala.'); });
    };

    const updateTable = (tableId) => {
        // Trova info correnti per prefilling
        let found = null;
        for (const rm of rooms) {
            if (found) break;
            for (const tb of (rm.tables || [])) {
                if (Number(tb.id) === Number(tableId)) { found = tb; break; }
            }
        }
        const code = window.prompt('Codice tavolo', found && found.code ? String(found.code) : '');
        if (!code) return;
        const seatsStr = window.prompt('Posti standard', String((found && found.seats_std) ? found.seats_std : 2)) || '2';
        const seats = Math.max(1, Number.parseInt(seatsStr, 10) || 2);
        const status = window.prompt('Stato (available, blocked, maintenance, hidden)', (found && found.status) ? String(found.status) : 'available') || 'available';
        request(`/tables/${tableId}`, { method: 'POST', data: { code, seats_std: seats, status } })
            .then(refresh)
            .catch((error) => { window.alert((error && error.message) ? error.message : 'Impossibile aggiornare il tavolo.'); });
    };

    root.addEventListener('submit', (event) => {
            const target = event.target;
        if (!(target instanceof HTMLFormElement)) return;
        if (target.matches('[data-action="create-room"]')) {
            event.preventDefault();
            createRoom(target);
        }
    });

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.matches('[data-action="refresh"]')) {
            refresh();
            return;
        }
        if (target.matches('[data-action="add-table"]')) {
            const holder = target.closest('[data-room-id]');
            const roomId = holder ? Number.parseInt(holder.dataset.roomId || '0', 10) : 0;
            if (roomId) addTable(roomId);
            return;
        }
        if (target.matches('[data-action="edit-room"]')) {
            const holder = target.closest('[data-room-id]');
            const roomId = holder ? Number.parseInt(holder.dataset.roomId || '0', 10) : 0;
            if (roomId) updateRoom(roomId);
            return;
        }
        if (target.matches('[data-action="delete-room"]')) {
            const holder = target.closest('[data-room-id]');
            const roomId = holder ? Number.parseInt(holder.dataset.roomId || '0', 10) : 0;
            if (roomId) deleteRoom(roomId);
            return;
        }
        if (target.matches('[data-action="delete-table"]')) {
            const row = target.closest('[data-table-id]');
            const tableId = row ? Number.parseInt(row.dataset.tableId || '0', 10) : 0;
            if (tableId) deleteTable(tableId);
            return;
        }
        if (target.matches('[data-action="edit-table"]')) {
            const row = target.closest('[data-table-id]');
            const tableId = row ? Number.parseInt(row.dataset.tableId || '0', 10) : 0;
            if (tableId) updateTable(tableId);
        }
    });

    render();
})();
