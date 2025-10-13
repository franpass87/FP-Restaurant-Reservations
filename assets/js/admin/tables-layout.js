(function () {
    const root = document.querySelector('[data-fp-resv-tables]');
    if (!root) {
        return;
    }

    const settings = window.fpResvTablesSettings || {};
    const restRoot = ((settings.restRoot || '/wp-json/fp-resv/v1')).replace(/\/$/, '');
    const listRegion = root.querySelector('[data-region="list"]');

    let rooms = Array.isArray(settings.initialState && settings.initialState.rooms) ? settings.initialState.rooms : [];

    // Debug: test endpoint availability
    const testEndpoint = () => {
        return fetch(`${restRoot}/tables/overview`, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': settings.nonce || '',
            },
            credentials: 'same-origin',
        }).then(response => {
            console.log('[FP-Resv] Endpoint test response:', {
                status: response.status,
                ok: response.ok,
                headers: Object.fromEntries(response.headers.entries())
            });
            return response;
        });
    };

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
                    // Log tecnico per debug
                    try { console.error('[FP-Resv] API error', { url, status: response.status, payload }); } catch (_) { void 0; }
                    throw error;
                });
            }
            if (response.status === 204) {
                return null;
            }
            return response.text().then((text) => {
                if (!text) {
                    // Restituisce null invece di {} per indicare una risposta vuota valida
                    return null;
                }
                try { 
                    return JSON.parse(text); 
                } catch (e) { 
                    // Log dell'errore di parsing JSON
                    try { console.error('[FP-Resv] JSON parse error', { url, text, error: e.message }); } catch (_) { void 0; }
                    throw new Error('Risposta non valida dal server');
                }
            });
        });
    };

    // Badge ultimo aggiornamento
    const setLastUpdate = (date) => {
        const title = document.getElementById('fp-resv-tables-title');
        if (!title) return;
        let badge = title.parentElement && title.parentElement.querySelector('.fp-resv-last-update');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'fp-resv-last-update';
            badge.style.marginLeft = '12px';
            badge.style.fontSize = '12px';
            badge.style.padding = '2px 8px';
            badge.style.borderRadius = '9999px';
            badge.style.background = '#eef2ff';
            badge.style.color = '#3730a3';
            badge.style.verticalAlign = 'middle';
            title.insertAdjacentElement('afterend', badge);
        }
        const d = (date instanceof Date) ? date : new Date();
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        const ss = String(d.getSeconds()).padStart(2, '0');
        badge.textContent = `Ultimo aggiornamento: ${hh}:${mm}:${ss}`;
    };

    // Utility: avviso non bloccante
    const notify = (message, level = 'error') => {
        if (!root) return;
        const note = document.createElement('div');
        note.className = `fp-resv-notice fp-resv-notice--${level}`;
        note.style.margin = '8px 0 16px';
        note.style.padding = '8px 12px';
        note.style.borderRadius = '6px';
        note.style.background = level === 'warning' ? '#fef3c7' : '#fee2e2';
        note.style.color = '#111827';
        note.textContent = message;
        root.insertBefore(note, root.firstChild);
        setTimeout(() => { try { note.remove(); } catch (_) { void 0; } }, 5000);
    };

    // Modal management
    let currentModal = null; // eslint-disable-line no-unused-vars
    
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
                // Mantieni lo stato corrente se la risposta non è nel formato atteso
                const valid = payload && Array.isArray(payload.rooms);
                if (!valid) {
                    let errorMsg = 'Risposta inattesa dal server. Visualizzo dati correnti.';
                    
                    if (payload === null) {
                        errorMsg = 'Nessuna risposta dal server. Verificare i permessi di accesso.';
                    } else if (payload && payload.message) {
                        errorMsg = payload.message;
                    } else if (payload && typeof payload === 'object') {
                        errorMsg = 'Formato dati non valido ricevuto dal server.';
                    }
                    
                    notify(errorMsg, 'warning');
                    try { console.warn('[FP-Resv] Unexpected overview payload', payload); } catch (_) { void 0; }
                }
                rooms = valid ? payload.rooms : rooms;
                render();
            })
            .catch((error) => {
                let errorMessage = 'Impossibile aggiornare le sale.';
                
                if (error && error.message) {
                    errorMessage = error.message;
                } else if (error && error.status === 403) {
                    errorMessage = 'Permessi insufficienti per accedere alle sale.';
                } else if (error && error.status === 404) {
                    errorMessage = 'Endpoint API non trovato. Verificare la configurazione del plugin.';
                }
                
                alert(errorMessage);
                notify('Impossibile aggiornare le sale. Dati correnti mantenuti.', 'warning');
                try { console.error('[FP-Resv] Refresh error', error); } catch (_) { void 0; }
            })
            .finally(() => { 
                root.dataset.state = ''; 
                setLastUpdate(new Date());
            });
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
            .then((result) => {
                // Accetta diversi formati di risposta possibili:
                // - { id, ... }
                // - { created: { id, ... } } oppure { created: [ { id, ... } ] }
                // - { data: { id, ... } }
                let created = null;
                if (result && typeof result === 'object') {
                    if (result.id) {
                        created = result;
                    } else if (result.created) {
                        created = Array.isArray(result.created) ? (result.created[0] || null) : result.created;
                    } else if (result.data && typeof result.data === 'object' && result.data.id) {
                        created = result.data;
                    }
                }

                if (created && created.id) {
                    closeModal();
                    refresh();
                    notify('Tavolo creato con successo', 'success');
                } else {
                    throw new Error('Risposta inattesa dal server durante la creazione del tavolo');
                }
            })
            .catch((error) => { 
                let errorMessage = 'Impossibile creare il tavolo.';
                
                if (error && error.message) {
                    errorMessage = error.message;
                } else if (error && error.status === 403) {
                    errorMessage = 'Permessi insufficienti per creare tavoli.';
                } else if (error && error.status === 400) {
                    errorMessage = 'Dati non validi per la creazione del tavolo.';
                }
                
                alert(errorMessage);
                try { console.error('[FP-Resv] Add table error', error); } catch (_) { void 0; }
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

    // Supporto al pulsante refresh nella topbar esterna al container
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.matches('[data-action="refresh"]')) {
            // Evita doppio handling se l'evento è già stato gestito dentro root
            if (!root.contains(target)) {
                refresh();
            }
        }
    });

    // Test endpoint availability on startup
    testEndpoint().catch(error => {
        console.error('[FP-Resv] Endpoint test failed:', error);
    });
    
    // Initial render
    render();
    // Imposta badge al primo caricamento
    setLastUpdate(new Date());
})();
