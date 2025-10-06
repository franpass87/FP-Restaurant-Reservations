(function () {
    const root = document.querySelector('[data-fp-resv-tables]');
    if (!root) {
        return;
    }

    const settings = window.fpResvTablesSettings || {};
    const restRoot = ((settings.restRoot || '/wp-json/fp-resv/v1')).replace(/\/$/, '');
    const listRegion = root.querySelector('[data-region="list"]');

    let rooms = Array.isArray(settings.initialState && settings.initialState.rooms) ? settings.initialState.rooms : [];

    // Utility: API request
    const request = (path, options = {}) => {
        const url = typeof path === 'string' && path.startsWith('http') ? path : `${restRoot}${path}`;
        const config = {
            method: options.method || 'GET',
            headers: {
                'X-WP-Nonce': settings.nonce || '',
            },
            credentials: 'same-origin',
        };

        // Invia sempre JSON per coerenza; il backend ha già un fallback su get_params
        if (options.data) {
            config.headers['Content-Type'] = 'application/json;charset=UTF-8';
            config.body = JSON.stringify(options.data);
            if (!config.method || config.method === 'GET') {
                config.method = 'POST';
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

    // Modal management
    let currentModal = null;

    const createModal = (title, fields, onSubmit) => {
        const modal = document.createElement('div');
        modal.className = 'fp-resv-tables-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-labelledby', 'modal-title');
        
        let fieldsHTML = '';
        fields.forEach(field => {
            const value = field.value !== undefined ? field.value : '';
            const required = field.required ? 'required' : '';
            const pattern = field.pattern ? `pattern="${field.pattern}"` : '';
            
            if (field.type === 'select') {
                fieldsHTML += `
                    <div class="fp-resv-tables-modal__field">
                        <label>${field.label}</label>
                        <select name="${field.name}" ${required}>
                            ${field.options.map(opt => `<option value="${opt.value}" ${opt.value === value ? 'selected' : ''}>${opt.label}</option>`).join('')}
                        </select>
                    </div>
                `;
            } else {
                fieldsHTML += `
                    <div class="fp-resv-tables-modal__field">
                        <label>${field.label}</label>
                        <input type="${field.type || 'text'}" name="${field.name}" value="${value}" ${required} ${pattern} placeholder="${field.placeholder || ''}">
                    </div>
                `;
            }
        });
        
        modal.innerHTML = `
            <div class="fp-resv-tables-modal__backdrop" data-action="modal-close"></div>
            <div class="fp-resv-tables-modal__dialog">
                <header class="fp-resv-tables-modal__header">
                    <h2 id="modal-title">${title}</h2>
                    <button type="button" class="button-link" data-action="modal-close" aria-label="Chiudi">×</button>
                </header>
                <div class="fp-resv-tables-modal__body">
                    <form class="fp-resv-tables-modal__form" data-modal-form>
                        ${fieldsHTML}
                    </form>
                </div>
                <footer class="fp-resv-tables-modal__footer">
                    <button type="button" class="button" data-action="modal-close">Annulla</button>
                    <button type="button" class="button button-primary" data-action="modal-submit">Salva</button>
                </footer>
            </div>
        `;
        
        document.body.appendChild(modal);
        currentModal = modal;
        
        // Focus trap
        const firstInput = modal.querySelector('input, select');
        if (firstInput) {
            requestAnimationFrame(() => firstInput.focus());
        }
        
        // Event handlers
        const closeModal = () => {
            modal.remove();
            currentModal = null;
        };
        
        modal.querySelectorAll('[data-action="modal-close"]').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });
        
        modal.querySelector('[data-action="modal-submit"]').addEventListener('click', () => {
            const form = modal.querySelector('[data-modal-form]');
            if (form.checkValidity()) {
                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });
                const showError = (message) => {
                    let err = modal.querySelector('.fp-resv-tables-modal__error');
                    if (!err) {
                        err = document.createElement('p');
                        err.className = 'fp-resv-tables-modal__error';
                        err.style.color = '#dc2626';
                        err.style.margin = '8px 0 0';
                        modal.querySelector('.fp-resv-tables-modal__body').appendChild(err);
                    }
                    err.textContent = message || 'Operazione non riuscita.';
                };
                Promise.resolve()
                    .then(() => onSubmit(data, closeModal))
                    .catch((e) => {
                        showError(e && e.message ? e.message : 'Operazione non riuscita.');
                    });
            } else {
                form.reportValidity();
            }
        });
        
        // Close on escape
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
    };

    // Render functions
    const render = () => {
        if (!listRegion) return;
        listRegion.innerHTML = '';
        
        if (!rooms.length) {
            const p = document.createElement('p');
            p.className = 'fp-resv-tables-empty';
            p.textContent = 'Nessuna sala configurata. Crea una sala per iniziare.';
            listRegion.appendChild(p);
            return;
        }
        
        rooms.forEach((room) => {
            const wrapper = document.createElement('article');
            wrapper.className = 'fp-resv-room-card';
            wrapper.dataset.roomId = String(room.id);
            wrapper.style.setProperty('--room-color', room.color || '#4338ca');
            
            wrapper.innerHTML = `
                <header>
                    <h3>${room.name}</h3>
                    ${room.description ? `<p>${room.description}</p>` : ''}
                </header>
                <div class="fp-resv-room-card__actions">
                    <button type="button" class="button button-primary" data-action="add-table">+ Aggiungi tavolo</button>
                    <button type="button" class="button" data-action="edit-room">Modifica sala</button>
                    <button type="button" class="button button-link" data-action="delete-room" style="color: #dc2626;">Elimina sala</button>
                </div>
                <ul class="fp-resv-table-list" data-role="table-list"></ul>
            `;
            
            const ul = wrapper.querySelector('[data-role="table-list"]');
            const tables = room.tables || [];
            
            if (tables.length === 0) {
                const li = document.createElement('li');
                li.style.padding = '16px 24px';
                li.style.color = '#9ca3af';
                li.style.fontStyle = 'italic';
                li.textContent = 'Nessun tavolo. Clicca "Aggiungi tavolo" per iniziare.';
                ul.appendChild(li);
            } else {
                tables.forEach((table) => {
                    const li = document.createElement('li');
                    li.className = 'fp-resv-table-item';
                    li.dataset.tableId = String(table.id);
                    
                    const statusLabels = {
                        'available': 'Disponibile',
                        'blocked': 'Bloccato',
                        'maintenance': 'Manutenzione',
                        'hidden': 'Nascosto'
                    };
                    
                    li.innerHTML = `
                        <span class="fp-resv-table-code">${table.code}</span>
                        <span class="fp-resv-table-meta">
                            <span>👥 ${table.seats_std || 0} posti</span>
                            <span>• ${statusLabels[table.status] || table.status}</span>
                        </span>
                        <span class="fp-resv-table-actions">
                            <button type="button" class="button-link" data-action="edit-table">Modifica</button>
                            <button type="button" class="button-link" data-action="delete-table" style="color: #dc2626;">Elimina</button>
                        </span>
                    `;
                    ul.appendChild(li);
                });
            }
            
            listRegion.appendChild(wrapper);
        });
    };

    // API operations
    const refresh = () => {
        root.dataset.state = 'loading';
        return request('/tables/overview')
            .then((payload) => {
                rooms = Array.isArray(payload.rooms) ? payload.rooms : [];
                render();
            })
            .catch((error) => {
                alert((error && error.message) ? error.message : 'Impossibile aggiornare le sale.');
            })
            .finally(() => { root.dataset.state = ''; });
    };

    const createRoom = (form) => {
        const fd = new FormData(form);
        const name = String(fd.get('name') || '').trim();
        const color = String(fd.get('color') || '#4338ca').trim();
        if (!name) return;
        
        request('/tables/rooms', { method: 'POST', data: { name, color } })
            .then(() => { 
                form.reset(); 
                return refresh(); 
            })
            .catch((error) => { 
                alert((error && error.message) ? error.message : 'Impossibile creare la sala.'); 
            });
    };

    const addTable = (roomId) => {
        createModal('Aggiungi tavolo', [
            { name: 'code', label: 'Codice tavolo', type: 'text', required: true, placeholder: 'es. T1, A5' },
            { name: 'seats_std', label: 'Posti standard', type: 'number', value: '2', required: true },
            { name: 'status', label: 'Stato', type: 'select', value: 'available', options: [
                { value: 'available', label: 'Disponibile' },
                { value: 'blocked', label: 'Bloccato' },
                { value: 'maintenance', label: 'Manutenzione' },
                { value: 'hidden', label: 'Nascosto' }
            ]}
        ], (data, closeModal) => {
            request('/tables', { 
                method: 'POST', 
                data: { 
                    room_id: roomId, 
                    code: data.code, 
                    seats_std: Math.max(1, parseInt(data.seats_std) || 2), 
                    status: data.status 
                } 
            })
            .then(() => {
                closeModal();
                refresh();
            })
            .catch((error) => { 
                alert((error && error.message) ? error.message : 'Impossibile creare il tavolo.'); 
            });
        });
    };

    const editRoom = (roomId) => {
        const room = rooms.find((r) => Number(r.id) === Number(roomId));
        if (!room) return;
        
        createModal('Modifica sala', [
            { name: 'name', label: 'Nome sala', type: 'text', value: room.name, required: true },
            { name: 'color', label: 'Colore', type: 'color', value: room.color || '#4338ca' }
        ], (data, closeModal) => {
            request(`/tables/rooms/${roomId}`, { method: 'POST', data })
                .then(() => {
                    closeModal();
                    refresh();
                })
                .catch((error) => { 
                    alert((error && error.message) ? error.message : 'Impossibile aggiornare la sala.'); 
                });
        });
    };

    const deleteRoom = (roomId) => {
        if (!confirm('Eliminare la sala e tutti i tavoli associati?')) return;
        request(`/tables/rooms/${roomId}`, { method: 'DELETE' })
            .then(refresh)
            .catch((error) => { 
                alert((error && error.message) ? error.message : 'Impossibile eliminare la sala.'); 
            });
    };

    const editTable = (tableId) => {
        let found = null;
        for (const rm of rooms) {
            if (found) break;
            for (const tb of (rm.tables || [])) {
                if (Number(tb.id) === Number(tableId)) { 
                    found = tb; 
                    break; 
                }
            }
        }
        if (!found) return;
        
        createModal('Modifica tavolo', [
            { name: 'code', label: 'Codice tavolo', type: 'text', value: found.code, required: true },
            { name: 'seats_std', label: 'Posti standard', type: 'number', value: String(found.seats_std || 2), required: true },
            { name: 'status', label: 'Stato', type: 'select', value: found.status || 'available', options: [
                { value: 'available', label: 'Disponibile' },
                { value: 'blocked', label: 'Bloccato' },
                { value: 'maintenance', label: 'Manutenzione' },
                { value: 'hidden', label: 'Nascosto' }
            ]}
        ], (data, closeModal) => {
            request(`/tables/${tableId}`, { 
                method: 'POST', 
                data: { 
                    code: data.code, 
                    seats_std: Math.max(1, parseInt(data.seats_std) || 2), 
                    status: data.status 
                } 
            })
            .then(() => {
                closeModal();
                refresh();
            })
            .catch((error) => { 
                alert((error && error.message) ? error.message : 'Impossibile aggiornare il tavolo.'); 
            });
        });
    };

    const deleteTable = (tableId) => {
        if (!confirm('Eliminare il tavolo?')) return;
        request(`/tables/${tableId}`, { method: 'DELETE' })
            .then(refresh)
            .catch((error) => { 
                alert((error && error.message) ? error.message : 'Impossibile eliminare il tavolo.'); 
            });
    };

    // Event delegation
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
            const roomId = holder ? parseInt(holder.dataset.roomId || '0', 10) : 0;
            if (roomId) addTable(roomId);
            return;
        }
        
        if (target.matches('[data-action="edit-room"]')) {
            const holder = target.closest('[data-room-id]');
            const roomId = holder ? parseInt(holder.dataset.roomId || '0', 10) : 0;
            if (roomId) editRoom(roomId);
            return;
        }
        
        if (target.matches('[data-action="delete-room"]')) {
            const holder = target.closest('[data-room-id]');
            const roomId = holder ? parseInt(holder.dataset.roomId || '0', 10) : 0;
            if (roomId) deleteRoom(roomId);
            return;
        }
        
        if (target.matches('[data-action="delete-table"]')) {
            const row = target.closest('[data-table-id]');
            const tableId = row ? parseInt(row.dataset.tableId || '0', 10) : 0;
            if (tableId) deleteTable(tableId);
            return;
        }
        
        if (target.matches('[data-action="edit-table"]')) {
            const row = target.closest('[data-table-id]');
            const tableId = row ? parseInt(row.dataset.tableId || '0', 10) : 0;
            if (tableId) editTable(tableId);
        }
    });

    // Initial render
    render();
})();
