/* global fpResvTablesSettings */
(function () {
    const root = document.querySelector('[data-fp-resv-tables]');
    if (!root) {
        return;
    }

    const settings = window.fpResvTablesSettings || {};
    const restRoot = ((settings.restRoot || '/wp-json/fp-resv/v1')).replace(/\/$/, '');
    const request = (path, options = {}) => {
        const url = typeof path === 'string' && path.startsWith('http')
            ? path
            : `${restRoot}${path}`;

        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': settings.nonce || '',
            },
            credentials: 'same-origin',
        };

        if (options.data) {
            config.body = JSON.stringify(options.data);
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

            return response.json();
        });
    };

    const initialRooms = settings.initialState && Array.isArray(settings.initialState.rooms)
        ? settings.initialState.rooms
        : [];

    const state = {
        rooms: initialRooms,
        scale: 1,
    };

    let modalOpen = false;

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
        <div class="fp-resv-tables-modal" data-modal hidden>
            <div class="fp-resv-tables-modal__backdrop" data-modal-close></div>
            <div class="fp-resv-tables-modal__dialog" role="dialog" aria-modal="true">
                <header class="fp-resv-tables-modal__header">
                    <h2>${(settings.strings && settings.strings.newRoomTitle) || 'Nuova sala'}</h2>
                </header>
                <form class="fp-resv-tables-modal__form" data-modal-form>
                    <label class="fp-resv-tables-modal__field">
                        <span>${(settings.strings && settings.strings.newRoomName) || 'Nome sala'}</span>
                        <input type="text" data-field="name" required>
                    </label>
                    <label class="fp-resv-tables-modal__field">
                        <span>${(settings.strings && settings.strings.newRoomCapacity) || 'Capienza stimata'}</span>
                        <input type="number" min="0" step="1" data-field="capacity" placeholder="40">
                    </label>
                    <label class="fp-resv-tables-modal__field">
                        <span>${(settings.strings && settings.strings.newRoomColor) || 'Colore identificativo'}</span>
                        <input type="color" data-field="color" value="#4338ca">
                    </label>
                    <footer class="fp-resv-tables-modal__actions">
                        <button type="button" class="button" data-modal-cancel>${(settings.strings && settings.strings.modalCancel) || 'Annulla'}</button>
                        <button type="submit" class="button button-primary" data-modal-submit>${(settings.strings && settings.strings.modalCreate) || 'Crea sala'}</button>
                    </footer>
                </form>
            </div>
        </div>
    `;

    root.classList.add('fp-resv-tables-ready');
    root.dataset.state = '';
    root.appendChild(template.content.cloneNode(true));

    const sidebar = root.querySelector('[data-region="rooms"]');
    const canvas = root.querySelector('[data-region="canvas"]');
    const modal = root.querySelector('[data-modal]');
    const modalForm = modal ? modal.querySelector('[data-modal-form]') : null;
    const modalName = modal ? modal.querySelector('[data-field="name"]') : null;
    const modalCapacity = modal ? modal.querySelector('[data-field="capacity"]') : null;
    const modalColor = modal ? modal.querySelector('[data-field="color"]') : null;
    const modalSubmit = modal ? modal.querySelector('[data-modal-submit]') : null;

    const resetModalForm = () => {
        if (modalForm) {
            modalForm.reset();
        }
        if (modalColor) {
            modalColor.value = '#4338ca';
        }
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.removeAttribute('data-open');
        modalOpen = false;
        if (modalSubmit) {
            modalSubmit.disabled = false;
        }
        resetModalForm();
    };

    const openModal = () => {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        modal.setAttribute('data-open', 'true');
        modalOpen = true;
        resetModalForm();
        if (modalName) {
            modalName.focus();
            modalName.select();
        }
    };

    if (modal) {
        modal.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target.hasAttribute('data-modal-close') || target.hasAttribute('data-modal-cancel')) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modalOpen) {
                event.preventDefault();
                closeModal();
            }
        });
    }

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
        return request('/tables/overview')
            .then((payload) => {
                state.rooms = Array.isArray(payload.rooms) ? payload.rooms : [];
                renderRooms();
                renderCanvas();
            })
            .catch((error) => {
                window.alert((error && error.message) ? error.message : 'Impossibile aggiornare le sale.');
            })
            .finally(() => {
                root.dataset.state = '';
            });
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

        request('/tables', {
            method: 'POST',
            data: payload,
        }).then(refresh).catch((error) => {
            window.alert((error && error.message) ? error.message : 'Impossibile creare il tavolo.');
        });
    };

    const showSuggestion = (roomId) => {
        request(`/tables/suggest?room_id=${roomId}&party=2`).then((result) => {
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
        request('/tables/positions', {
            method: 'POST',
            data: {
                positions: [{ id: tableId, x, y }],
            },
        }).catch((error) => {
            // eslint-disable-next-line no-console
            console.error('Unable to persist table position', error);
        });
    };

    if (modalForm) {
        modalForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!modalName) {
                return;
            }

            const name = modalName.value.trim();
            if (name === '') {
                modalName.focus();
                return;
            }

            const capacityValue = modalCapacity && modalCapacity.value !== ''
                ? Number.parseInt(modalCapacity.value, 10)
                : 0;
            let colorValue = modalColor && typeof modalColor.value === 'string'
                ? modalColor.value.trim()
                : '';
            if (colorValue !== '') {
                colorValue = `#${colorValue.replace(/^#+/, '')}`.slice(0, 7);
            }

            if (modalSubmit) {
                modalSubmit.disabled = true;
            }

            request('/tables/rooms', {
                method: 'POST',
                data: {
                    name,
                    capacity: Number.isFinite(capacityValue) ? capacityValue : 0,
                    color: colorValue,
                    active: true,
                },
            })
                .then(() => {
                    closeModal();
                    refresh();
                })
                .catch((error) => {
                    window.alert((error && error.message) ? error.message : 'Impossibile creare la sala.');
                })
                .finally(() => {
                    if (modalSubmit) {
                        modalSubmit.disabled = false;
                    }
                });
        });
    }

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
            openModal();
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
