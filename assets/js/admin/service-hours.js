(function () {
    const roots = document.querySelectorAll('[data-service-hours]');
    if (!roots.length) {
        return;
    }

    const defaults = {
        days: [
            { key: 'mon', label: 'Lunedì', short: 'Lun' },
            { key: 'tue', label: 'Martedì', short: 'Mar' },
            { key: 'wed', label: 'Mercoledì', short: 'Mer' },
            { key: 'thu', label: 'Giovedì', short: 'Gio' },
            { key: 'fri', label: 'Venerdì', short: 'Ven' },
            { key: 'sat', label: 'Sabato', short: 'Sab' },
            { key: 'sun', label: 'Domenica', short: 'Dom' },
        ],
        strings: {
            addRange: 'Aggiungi fascia',
            removeRange: 'Rimuovi',
            from: 'Dalle',
            to: 'Alle',
            closed: 'Chiuso',
        },
    };

    roots.forEach((root) => {
        const targetSelector = root.getAttribute('data-target');
        const input = targetSelector ? document.querySelector(targetSelector) : null;
        if (!(input instanceof HTMLTextAreaElement)) {
            return;
        }

        let state = {};
        try {
            const parsed = JSON.parse(root.getAttribute('data-value') || '{}');
            if (parsed && typeof parsed === 'object') {
                state = parsed;
            }
        } catch (error) {
            state = {};
        }

        let config = defaults;
        try {
            const parsedConfig = JSON.parse(root.getAttribute('data-config') || '{}');
            if (parsedConfig && typeof parsedConfig === 'object') {
                config = {
                    days: Array.isArray(parsedConfig.days) ? parsedConfig.days : defaults.days,
                    strings: typeof parsedConfig.strings === 'object' && parsedConfig.strings !== null
                        ? { ...defaults.strings, ...parsedConfig.strings }
                        : defaults.strings,
                };
            }
        } catch (error) {
            config = defaults;
        }

        const normalizeState = () => {
            const normalized = {};
            config.days.forEach((day) => {
                const key = day.key;
                const ranges = Array.isArray(state[key]) ? state[key] : [];
                normalized[key] = ranges
                    .filter((range) => range && typeof range === 'object')
                    .map((range) => ({
                        start: typeof range.start === 'string' ? range.start : '',
                        end: typeof range.end === 'string' ? range.end : '',
                    }));
            });
            state = normalized;
        };

        normalizeState();

        const formatLine = (key, ranges) => {
            const valid = [];
            ranges.forEach((range) => {
                if (!range || typeof range !== 'object') {
                    return;
                }
                const start = normalizeTime(range.start);
                const end = normalizeTime(range.end);
                if (!start || !end || end <= start) {
                    return;
                }
                valid.push(start + '-' + end);
            });
            return valid.length ? key + '=' + valid.join('|') : '';
        };

        const sync = () => {
            const lines = [];
            config.days.forEach((day) => {
                const key = day.key;
                const ranges = Array.isArray(state[key]) ? state[key] : [];
                const line = formatLine(key, ranges);
                if (line) {
                    lines.push(line);
                }
            });

            input.value = lines.join('\n');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const handleRangeChange = (dayKey, index, field, value) => {
            if (!state[dayKey]) {
                state[dayKey] = [];
            }
            const ranges = Array.isArray(state[dayKey]) ? state[dayKey] : [];
            if (!ranges[index]) {
                ranges[index] = { start: '', end: '' };
            }
            ranges[index][field] = value;
            state[dayKey] = ranges;
            sync();
        };

        const handleRemoveRange = (dayKey, index) => {
            const ranges = Array.isArray(state[dayKey]) ? state[dayKey] : [];
            ranges.splice(index, 1);
            state[dayKey] = ranges;
            render();
            sync();
        };

        const handleAddRange = (dayKey) => {
            const ranges = Array.isArray(state[dayKey]) ? state[dayKey] : [];
            let start = '19:00';
            let end = '21:00';
            if (ranges.length) {
                const last = ranges[ranges.length - 1];
                const lastEnd = normalizeTime(last.end);
                if (lastEnd) {
                    const [h, m] = lastEnd.split(':').map((part) => parseInt(part, 10));
                    const nextStart = clampTime(h + 2, m);
                    start = nextStart;
                    end = clampTime(h + 4, m);
                }
            }
            ranges.push({ start, end });
            state[dayKey] = ranges;
            render();
            sync();
        };

        const handleToggleClosed = (dayKey, closed) => {
            if (closed) {
                state[dayKey] = [];
            } else {
                state[dayKey] = [{ start: '19:00', end: '21:00' }];
            }
            render();
            sync();
        };

        const clampTime = (hours, minutes) => {
            const h = Math.min(23, Math.max(0, Number.isFinite(hours) ? hours : 0));
            const m = Math.min(59, Math.max(0, Number.isFinite(minutes) ? minutes : 0));
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        };

        const normalizeTime = (value) => {
            if (typeof value !== 'string') {
                return '';
            }
            const match = value.match(/^(\d{1,2}):(\d{2})$/);
            if (!match) {
                return '';
            }
            const hours = Math.min(23, Math.max(0, parseInt(match[1], 10)));
            const minutes = Math.min(59, Math.max(0, parseInt(match[2], 10)));
            return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        };

        const createRangeRow = (dayKey, range, index) => {
            const row = document.createElement('div');
            row.className = 'fp-resv-service-hours__range';

            const fromLabel = document.createElement('label');
            fromLabel.className = 'fp-resv-service-hours__field';
            fromLabel.innerHTML = '<span>' + config.strings.from + '</span>';
            const fromInput = document.createElement('input');
            fromInput.type = 'time';
            fromInput.value = normalizeTime(range.start) || '';
            fromInput.addEventListener('input', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }
                handleRangeChange(dayKey, index, 'start', normalizeTime(target.value));
            });
            fromLabel.appendChild(fromInput);

            const toLabel = document.createElement('label');
            toLabel.className = 'fp-resv-service-hours__field';
            toLabel.innerHTML = '<span>' + config.strings.to + '</span>';
            const toInput = document.createElement('input');
            toInput.type = 'time';
            toInput.value = normalizeTime(range.end) || '';
            toInput.addEventListener('input', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }
                handleRangeChange(dayKey, index, 'end', normalizeTime(target.value));
            });
            toLabel.appendChild(toInput);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button-link fp-resv-service-hours__remove';
            removeButton.textContent = config.strings.removeRange;
            removeButton.addEventListener('click', () => handleRemoveRange(dayKey, index));

            row.appendChild(fromLabel);
            row.appendChild(toLabel);
            row.appendChild(removeButton);

            return row;
        };

        const render = () => {
            root.innerHTML = '';
            const wrapper = document.createElement('div');
            wrapper.className = 'fp-resv-service-hours__grid';
            root.appendChild(wrapper);

            config.days.forEach((day) => {
                const dayKey = day.key;
                const card = document.createElement('article');
                card.className = 'fp-resv-service-hours__day';
                card.dataset.day = dayKey;

                const header = document.createElement('header');
                header.className = 'fp-resv-service-hours__header';
                const title = document.createElement('h4');
                title.textContent = day.label;
                header.appendChild(title);

                const toggle = document.createElement('label');
                toggle.className = 'fp-resv-service-hours__toggle';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                const ranges = Array.isArray(state[dayKey]) ? state[dayKey] : [];
                checkbox.checked = ranges.length === 0;
                checkbox.addEventListener('change', () => {
                    handleToggleClosed(dayKey, checkbox.checked);
                });
                const toggleText = document.createElement('span');
                toggleText.textContent = config.strings.closed;
                toggle.appendChild(checkbox);
                toggle.appendChild(toggleText);
                header.appendChild(toggle);

                const rangesList = document.createElement('div');
                rangesList.className = 'fp-resv-service-hours__ranges';

                if (ranges.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'fp-resv-service-hours__empty';
                    empty.textContent = config.strings.closed;
                    rangesList.appendChild(empty);
                } else {
                    ranges.forEach((range, index) => {
                        rangesList.appendChild(createRangeRow(dayKey, range, index));
                    });
                }

                const addButton = document.createElement('button');
                addButton.type = 'button';
                addButton.className = 'button fp-resv-service-hours__add';
                addButton.textContent = config.strings.addRange;
                addButton.disabled = checkbox.checked;
                addButton.addEventListener('click', () => handleAddRange(dayKey));

                card.appendChild(header);
                card.appendChild(rangesList);
                card.appendChild(addButton);
                wrapper.appendChild(card);
            });
        };

        render();
        sync();
    });
})();
