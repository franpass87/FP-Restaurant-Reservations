/* global wp, fpResvTablesSettings */
(function () {
    const root = document.querySelector('[data-fp-resv-tables]');
    if (!root) {
        return;
    }

    const settings = window.fpResvTablesSettings || {};
    const apiFetch = (path, options = {}) => {
        const { apiFetch: wpApiFetch } = wp || {};
        if (!wpApiFetch) {
            // eslint-disable-next-line no-console
            console.warn('wp.apiFetch non disponibile');
            return Promise.reject(new Error('apiFetch unavailable'));
        }

        return wpApiFetch({
            path: `fp-resv/v1${path}`,
            method: 'GET',
            headers: {
                'X-WP-Nonce': settings.nonce || '',
            },
            ...options,
        });
    };

    const initialRooms = settings.initialState && Array.isArray(settings.initialState.rooms)
        ? settings.initialState.rooms
        : [];

    const state = {
        rooms: initialRooms,
        scale: 1,
    };

    const template = document.createElement('template');
    template.innerHTML = `
        <div class="fp-resv-tables-toolbar">
            <div class="fp-resv-tables-toolbar__left">
                <button type="button" class="button button-primary" data-action="add-room">
                    ${(settings.strings && settings.strings.createRoomCta) || 'Aggiungi sala'}
                </button>
                <button type="button" class="button" data-action="refresh">
                    Aggiorna
                </button>
            </div>
            <div class="fp-resv-tables-toolbar__right">
                <label>
                    Zoom
                    <input type="range" min="0.6" max="1.6" step="0.1" value="1" data-action="zoom">
                </label>
            </div>
        </div>
        <div class="fp-resv-tables-layout">
            <aside class="fp-resv-tables-sidebar" data-region="rooms"></aside>
            <section class="fp-resv-tables-canvas" data-region="canvas"></section>
        </div>
    `;

    root.classList.add('fp-resv-tables-ready');
    root.dataset.state = '';
    root.appendChild(template.content.cloneNode(true));

    const sidebar = root.querySelector('[data-region="rooms"]');
    const canvas = root.querySelector('[data-region="canvas"]');

    const renderRooms = () => {
        if (!sidebar) {
            return;
        }

        sidebar.innerHTML = '';

        if (!state.rooms.length) {
            const empty = document.createElement('p');
            empty.className = 'fp-resv-tables-empty';
            empty.textContent = (settings.strings && settings.strings.empty) || 'Nessuna sala configurata.';
            sidebar.appendChild(empty);
            return;
        }

        state.rooms.forEach((room) => {
            const item = document.createElement('article');
            item.className = 'fp-resv-room-card';
            item.dataset.roomId = String(room.id);
            item.innerHTML = `
                <header style="--room-color:${room.color || '#6b7280'}">
                    <h3>${room.name}</h3>
                    <p>${room.description || ''}</p>
                    <span>${room.tables.length} tavoli</span>
                </header>
                <div class="fp-resv-room-card__actions">
                    <button type="button" class="button" data-action="add-table">Nuovo tavolo</button>
                    <button type="button" class="button-link" data-action="suggest" title="Suggerisci disposizione">Suggerisci</button>
                </div>
            `;
            sidebar.appendChild(item);
        });
    };

    const renderCanvas = () => {
        if (!canvas) {
            return;
        }

        canvas.innerHTML = '';
        canvas.style.setProperty('--scale', state.scale);

        state.rooms.forEach((room) => {
            const layer = document.createElement('section');
            layer.className = 'fp-resv-canvas-room';
            layer.dataset.roomId = String(room.id);
            layer.innerHTML = `<header><h2>${room.name}</h2></header>`;

            const board = document.createElement('div');
            board.className = 'fp-resv-canvas-board';
            board.dataset.roomId = String(room.id);
            board.style.setProperty('--room-color', room.color || '#0ea5e9');

            room.tables.forEach((table) => {
                const node = document.createElement('button');
                node.type = 'button';
                node.className = `fp-resv-table-node status-${table.status || 'available'}`;
                node.dataset.tableId = String(table.id);
                node.dataset.roomId = String(room.id);
                node.textContent = table.code;

                const baseX = table.pos_x !== undefined && table.pos_x !== null ? table.pos_x : 40;
                const baseY = table.pos_y !== undefined && table.pos_y !== null ? table.pos_y : 40;
                const x = Number.parseFloat(baseX);
                const y = Number.parseFloat(baseY);
                node.style.transform = `translate(${x}px, ${y}px)`;
                node.dataset.x = String(x);
                node.dataset.y = String(y);

                node.addEventListener('pointerdown', (event) => startDrag(event, node));
                board.appendChild(node);
            });

            layer.appendChild(board);
            canvas.appendChild(layer);
        });
    };

    const refresh = () => {
        root.dataset.state = 'loading';
        return apiFetch('/tables/overview')
            .then((payload) => {
                state.rooms = Array.isArray(payload.rooms) ? payload.rooms : [];
                renderRooms();
                renderCanvas();
            })
            .finally(() => {
                root.dataset.state = '';
            });
    };

    const createRoom = () => {
        const name = window.prompt('Nome sala');
        if (!name) {
            return;
        }

        const payload = {
            name,
            capacity: 0,
            active: true,
        };

        apiFetch('/tables/rooms', {
            method: 'POST',
            data: payload,
        }).then(refresh);
    };

    const createTable = (roomId) => {
        const code = window.prompt('Codice tavolo');
        if (!code) {
            return;
        }

        const payload = {
            room_id: roomId,
            code,
            seats_std: 2,
            status: 'available',
            pos_x: 40,
            pos_y: 40,
        };

        apiFetch('/tables', {
            method: 'POST',
            data: payload,
        }).then(refresh);
    };

    const showSuggestion = (roomId) => {
        apiFetch(`/tables/suggest?room_id=${roomId}&party=2`).then((result) => {
            const best = result && result.best ? result.best : null;
            if (!best) {
                window.alert('Nessuna combinazione trovata.');
                return;
            }

            const info = `Tavoli suggeriti: ${best.table_ids.join(', ')}\nCapienza standard: ${best.capacity.std}`;
            window.alert(info);
        });
    };

    const persistPosition = (tableId, x, y) => {
        apiFetch('/tables/positions', {
            method: 'POST',
            data: {
                positions: [{ id: tableId, x, y }],
            },
        }).catch((error) => {
            // eslint-disable-next-line no-console
            console.error('Unable to persist table position', error);
        });
    };

    const startDrag = (event, node) => {
        event.preventDefault();
        node.setPointerCapture(event.pointerId);

        const startX = event.clientX;
        const startY = event.clientY;
        const originX = Number.parseFloat(node.dataset.x || '0');
        const originY = Number.parseFloat(node.dataset.y || '0');

        const moveHandler = (moveEvent) => {
            const deltaX = (moveEvent.clientX - startX) / state.scale;
            const deltaY = (moveEvent.clientY - startY) / state.scale;
            const nextX = Math.round((originX + deltaX) / 5) * 5;
            const nextY = Math.round((originY + deltaY) / 5) * 5;
            node.style.transform = `translate(${nextX}px, ${nextY}px)`;
            node.dataset.x = String(nextX);
            node.dataset.y = String(nextY);
        };

        const upHandler = (upEvent) => {
            node.releasePointerCapture(upEvent.pointerId);
            node.removeEventListener('pointermove', moveHandler);
            node.removeEventListener('pointerup', upHandler);
            node.removeEventListener('pointercancel', upHandler);

            const tableId = Number.parseInt(node.dataset.tableId || '0', 10);
            persistPosition(tableId, Number.parseFloat(node.dataset.x || '0'), Number.parseFloat(node.dataset.y || '0'));
        };

        node.addEventListener('pointermove', moveHandler);
        node.addEventListener('pointerup', upHandler);
        node.addEventListener('pointercancel', upHandler);
    };

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const action = target.dataset.action;
        if (!action) {
            return;
        }

        if (action === 'add-room') {
            createRoom();
        }

        if (action === 'refresh') {
            refresh();
        }

        if (action === 'add-table') {
            const parent = target.closest('[data-room-id]');
            const roomId = parent ? Number.parseInt(parent.dataset.roomId || '0', 10) : 0;
            if (roomId) {
                createTable(roomId);
            }
        }

        if (action === 'suggest') {
            const parent = target.closest('[data-room-id]');
            const roomId = parent ? Number.parseInt(parent.dataset.roomId || '0', 10) : 0;
            if (roomId) {
                showSuggestion(roomId);
            }
        }
    });

    root.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.dataset.action === 'zoom') {
            state.scale = Number.parseFloat(target.value || '1');
            if (canvas) {
                canvas.style.setProperty('--scale', state.scale);
            }
        }
    });

    renderRooms();
    renderCanvas();
})();
