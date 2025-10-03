(function () {
    const roots = document.querySelectorAll('[data-meal-plan]');
    if (!roots.length) {
        return;
    }

    const defaultStrings = {
        addMeal: 'Add meal',
        defaultLabel: 'Default',
        keyLabel: 'Key',
        labelLabel: 'Label',
        hintLabel: 'Hint',
        noticeLabel: 'Message',
        priceLabel: 'Price per guest',
        badgeLabel: 'Badge',
        badgeIconLabel: 'Badge icon',
        hoursLabel: 'Custom hours',
        hoursHint: 'Use the format mon=12:30-15:00;sat=19:00-23:00',
        slotLabel: 'Slot interval (minutes)',
        turnLabel: 'Turn duration (minutes)',
        bufferLabel: 'Buffer (minutes)',
        parallelLabel: 'Parallel reservations',
        capacityLabel: 'Maximum capacity',
        removeMeal: 'Remove meal',
        emptyState: 'No meals configured yet. Add one to get started.',
    };

    const knownMealKeys = new Set([
        'key',
        'label',
        'hint',
        'notice',
        'price',
        'badge',
        'badge_icon',
        'active',
        'availability',
        'hours',
        'schedule',
        'service_hours',
        'hours_definition',
        'slot',
        'slot_interval',
        'slotInterval',
        'turn',
        'turnover',
        'turnover_minutes',
        'buffer',
        'buffer_minutes',
        'parallel',
        'max_parallel',
        'maxParallel',
        'capacity',
    ]);

    const knownAvailabilityKeys = new Set([
        'hours',
        'schedule',
        'service_hours',
        'hours_definition',
        'slot',
        'slot_interval',
        'slotInterval',
        'turn',
        'turnover',
        'turnover_minutes',
        'buffer',
        'buffer_minutes',
        'parallel',
        'max_parallel',
        'maxParallel',
        'capacity',
    ]);

    const defaultHoursConfig = {
        days: [
            { key: 'mon', label: 'Monday', short: 'Mon' },
            { key: 'tue', label: 'Tuesday', short: 'Tue' },
            { key: 'wed', label: 'Wednesday', short: 'Wed' },
            { key: 'thu', label: 'Thursday', short: 'Thu' },
            { key: 'fri', label: 'Friday', short: 'Fri' },
            { key: 'sat', label: 'Saturday', short: 'Sat' },
            { key: 'sun', label: 'Sunday', short: 'Sun' },
        ],
        strings: {
            addRange: 'Add range',
            removeRange: 'Remove',
            from: 'From',
            to: 'To',
            closed: 'Closed',
        },
    };

    const normalizeHoursConfig = (raw) => {
        const config = { days: defaultHoursConfig.days, strings: defaultHoursConfig.strings };
        if (!raw || typeof raw !== 'object') {
            return config;
        }

        if (Array.isArray(raw.days) && raw.days.length) {
            const normalizedDays = raw.days
                .map((day) => {
                    if (!day || typeof day !== 'object') {
                        return null;
                    }
                    const key = typeof day.key === 'string' ? day.key.toLowerCase() : '';
                    if (!key) {
                        return null;
                    }
                    return {
                        key,
                        label: typeof day.label === 'string' ? day.label : key,
                        short: typeof day.short === 'string' ? day.short : key,
                    };
                })
                .filter(Boolean);
            if (normalizedDays.length) {
                config.days = normalizedDays;
            }
        }

        if (raw.strings && typeof raw.strings === 'object') {
            config.strings = { ...defaultHoursConfig.strings, ...raw.strings };
        }

        return config;
    };

    const createEmptyHoursState = (config) => {
        const state = {};
        config.days.forEach((day) => {
            state[day.key] = [];
        });
        return state;
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

    const splitHoursEntries = (line) => {
        const normalized = typeof line === 'string' ? line.trim().replace(/\u00a0/g, ' ') : '';
        if (normalized === '') {
            return [];
        }

        const regex = /(?:^|\s+)([A-Za-z]{3})\s*=\s*(.+?)(?=\s*$|\s+[A-Za-z]{3}\s*=)/g;
        const entries = [];
        let match = regex.exec(normalized);
        while (match) {
            if (Array.isArray(match) && match[1] && match[2]) {
                entries.push(match[1] + '=' + match[2].trim());
            }
            match = regex.exec(normalized);
        }

        if (entries.length) {
            return entries;
        }

        return [normalized];
    };

    const parseHoursDefinition = (definition, config) => {
        const state = createEmptyHoursState(config);
        const allowedDays = new Set(config.days.map((day) => day.key));
        if (typeof definition !== 'string') {
            return state;
        }

        const lines = definition.split(/\n/);
        lines.forEach((line) => {
            splitHoursEntries(line).forEach((entry) => {
                if (typeof entry !== 'string' || entry.trim() === '') {
                    return;
                }
                const [dayRaw, rangesRaw] = entry.split('=', 2);
                const dayKey = typeof dayRaw === 'string' ? dayRaw.trim().toLowerCase() : '';
                if (!allowedDays.has(dayKey)) {
                    return;
                }
                const ranges = (rangesRaw || '').split(/[|,]/);
                ranges.forEach((rangeRaw) => {
                    const normalized = typeof rangeRaw === 'string' ? rangeRaw.trim() : '';
                    if (!normalized) {
                        return;
                    }
                    const match = normalized.match(/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/);
                    if (!match) {
                        return;
                    }
                    const start = normalizeTime(match[1] + ':' + match[2]);
                    const end = normalizeTime(match[3] + ':' + match[4]);
                    if (!start || !end || end <= start) {
                        return;
                    }
                    state[dayKey].push({ start, end });
                });
            });
        });

        return state;
    };

    const formatHoursDefinition = (state, config) => {
        if (!state || typeof state !== 'object') {
            return '';
        }
        const lines = [];
        config.days.forEach((day) => {
            const ranges = Array.isArray(state[day.key]) ? state[day.key] : [];
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
            if (valid.length) {
                lines.push(day.key + '=' + valid.join('|'));
            }
        });

        return lines.join('\n');
    };

    const ensureMealHoursDefinition = (meal, config) => {
        if (!meal.hoursDefinition || typeof meal.hoursDefinition !== 'object') {
            meal.hoursDefinition = createEmptyHoursState(config);
        }
        const ensured = meal.hoursDefinition;
        config.days.forEach((day) => {
            if (!Array.isArray(ensured[day.key])) {
                ensured[day.key] = [];
            }
        });
        return ensured;
    };

    const syncMealHoursDefinition = (meal, config) => {
        const state = ensureMealHoursDefinition(meal, config);
        meal.hours = formatHoursDefinition(state, config);
    };

    const normalizeNumericInput = (value) => {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return String(Math.round(value));
        }
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed === '') {
                return '';
            }
            const parsed = parseInt(trimmed, 10);
            if (Number.isNaN(parsed)) {
                return '';
            }
            return String(parsed);
        }
        return '';
    };

    const normalizePriceInput = (value) => {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return value.toFixed(2);
        }
        if (typeof value === 'string') {
            const normalized = value.replace(',', '.').trim();
            if (normalized === '') {
                return '';
            }
            const parsed = Number(normalized);
            if (!Number.isFinite(parsed)) {
                return '';
            }
            return parsed.toFixed(2);
        }
        return '';
    };

    const serializeInteger = (value) => {
        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = parseInt(value.trim(), 10);
            if (!Number.isNaN(parsed)) {
                return parsed;
            }
        }
        if (typeof value === 'number' && Number.isFinite(value)) {
            return Math.round(value);
        }
        return null;
    };

    const normalizeMeal = (raw, hoursConfig) => {
        const meal = {
            key: '',
            label: '',
            hint: '',
            notice: '',
            price: '',
            badge: '',
            badge_icon: '',
            active: false,
            hours: '',
            hoursDefinition: createEmptyHoursState(hoursConfig),
            slot: '',
            turn: '',
            buffer: '',
            parallel: '',
            capacity: '',
            extras: {},
            availabilityExtras: {},
        };

        if (!raw || typeof raw !== 'object') {
            return meal;
        }

        const source = raw;

        if (typeof source.key === 'string') {
            meal.key = source.key;
        }
        if (typeof source.label === 'string') {
            meal.label = source.label;
        }
        if (typeof source.hint === 'string') {
            meal.hint = source.hint;
        }
        if (typeof source.notice === 'string') {
            meal.notice = source.notice;
        }
        if (typeof source.badge === 'string') {
            meal.badge = source.badge;
        }
        if (typeof source.badge_icon === 'string') {
            meal.badge_icon = source.badge_icon;
        }
        if (typeof source.badgeIcon === 'string' && !meal.badge_icon) {
            meal.badge_icon = source.badgeIcon;
        }
        if (Object.prototype.hasOwnProperty.call(source, 'price')) {
            meal.price = normalizePriceInput(source.price);
        }
        if (source.active) {
            meal.active = true;
        }

        const availability = {};
        if (source.availability && typeof source.availability === 'object') {
            Object.assign(availability, source.availability);
        }

        ['hours', 'schedule', 'service_hours', 'hours_definition', 'slot', 'slot_interval', 'slotInterval', 'turn', 'turnover', 'turnover_minutes', 'buffer', 'buffer_minutes', 'parallel', 'max_parallel', 'maxParallel', 'capacity'].forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(source, key) && !Object.prototype.hasOwnProperty.call(availability, key)) {
                availability[key] = source[key];
            }
        });

        if (typeof availability.hours_definition === 'string' && availability.hours_definition.trim() !== '') {
            meal.hours = availability.hours_definition.trim();
        } else if (typeof availability.hours === 'string' && availability.hours.trim() !== '') {
            meal.hours = availability.hours.trim();
        } else if (typeof availability.schedule === 'string' && availability.schedule.trim() !== '') {
            meal.hours = availability.schedule.trim();
        } else if (typeof availability.service_hours === 'string' && availability.service_hours.trim() !== '') {
            meal.hours = availability.service_hours.trim();
        }

        meal.hoursDefinition = parseHoursDefinition(meal.hours, hoursConfig);
        syncMealHoursDefinition(meal, hoursConfig);

        const slotValue = availability.slot_interval ?? availability.slotInterval ?? availability.slot;
        const turnValue = availability.turnover ?? availability.turn ?? availability.turnover_minutes;
        const bufferValue = availability.buffer ?? availability.buffer_minutes;
        const parallelValue = availability.max_parallel ?? availability.parallel ?? availability.maxParallel;
        const capacityValue = availability.capacity;

        meal.slot = normalizeNumericInput(slotValue);
        meal.turn = normalizeNumericInput(turnValue);
        meal.buffer = normalizeNumericInput(bufferValue);
        meal.parallel = normalizeNumericInput(parallelValue);
        meal.capacity = normalizeNumericInput(capacityValue);

        Object.keys(source).forEach((key) => {
            if (!knownMealKeys.has(key)) {
                meal.extras[key] = source[key];
            }
        });

        Object.keys(availability).forEach((key) => {
            if (!knownAvailabilityKeys.has(key)) {
                meal.availabilityExtras[key] = availability[key];
            }
        });

        return meal;
    };

    const createEmptyMeal = (hoursConfig) => ({
        key: '',
        label: '',
        hint: '',
        notice: '',
        price: '',
        badge: '',
        badge_icon: '',
        active: false,
        hours: '',
        hoursDefinition: createEmptyHoursState(hoursConfig),
        slot: '',
        turn: '',
        buffer: '',
        parallel: '',
        capacity: '',
        extras: {},
        availabilityExtras: {},
    });

    const serializeMeal = (meal, hoursConfig) => {
        const payload = { ...meal.extras };
        const key = meal.key.trim();
        if (key) {
            payload.key = key;
        }
        const label = meal.label.trim();
        if (label) {
            payload.label = label;
        }
        const hint = meal.hint.trim();
        if (hint) {
            payload.hint = hint;
        }
        const notice = meal.notice.trim();
        if (notice) {
            payload.notice = notice;
        }
        const price = meal.price.trim();
        if (price) {
            payload.price = price;
        }
        const badge = meal.badge.trim();
        if (badge) {
            payload.badge = badge;
        }
        const badgeIcon = meal.badge_icon.trim();
        if (badgeIcon) {
            payload.badge_icon = badgeIcon;
        }
        if (meal.active) {
            payload.active = true;
        }

        const availability = { ...meal.availabilityExtras };
        syncMealHoursDefinition(meal, hoursConfig);
        const hours = meal.hours.trim();
        if (hours) {
            availability.hours = hours;
        }

        const slot = serializeInteger(meal.slot);
        if (slot !== null) {
            availability.slot = slot;
        }
        const turn = serializeInteger(meal.turn);
        if (turn !== null) {
            availability.turn = turn;
        }
        const buffer = serializeInteger(meal.buffer);
        if (buffer !== null) {
            availability.buffer = buffer;
        }
        const parallel = serializeInteger(meal.parallel);
        if (parallel !== null) {
            availability.parallel = parallel;
        }
        const capacity = serializeInteger(meal.capacity);
        if (capacity !== null) {
            availability.capacity = capacity;
        }

        if (Object.keys(availability).length) {
            payload.availability = availability;
        }

        return payload;
    };

    roots.forEach((root, rootIndex) => {
        const targetSelector = root.getAttribute('data-target');
        const input = targetSelector ? document.querySelector(targetSelector) : null;
        if (!(input instanceof HTMLTextAreaElement)) {
            return;
        }

        let strings = { ...defaultStrings };
        try {
            const parsedStrings = JSON.parse(root.getAttribute('data-strings') || '{}');
            if (parsedStrings && typeof parsedStrings === 'object') {
                strings = { ...strings, ...parsedStrings };
            }
        } catch (error) {
            strings = { ...defaultStrings };
        }

        let hoursConfig = defaultHoursConfig;
        try {
            const parsedHours = JSON.parse(root.getAttribute('data-hours-config') || '{}');
            hoursConfig = normalizeHoursConfig(parsedHours);
        } catch (error) {
            hoursConfig = normalizeHoursConfig({});
        }

        let state = [];
        try {
            const parsed = JSON.parse(root.getAttribute('data-value') || '[]');
            if (Array.isArray(parsed)) {
                state = parsed.map((entry) => normalizeMeal(entry, hoursConfig));
            }
        } catch (error) {
            state = [];
        }

        if (!Array.isArray(state)) {
            state = [];
        }

        const ensureDefault = () => {
            if (!state.length) {
                return;
            }
            if (!state.some((meal) => meal.active)) {
                state[0].active = true;
            }
        };

        const sync = () => {
            ensureDefault();
            const serialized = state.map((meal) => serializeMeal(meal, hoursConfig));
            input.value = JSON.stringify(serialized);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const updateMeal = (index, updater, options = {}) => {
            const meal = state[index];
            if (!meal) {
                return;
            }
            updater(meal);
            if (options.rerender) {
                render();
            }
            sync();
        };

        const setDefaultMeal = (index) => {
            state = state.map((meal, currentIndex) => ({
                ...meal,
                active: currentIndex === index,
            }));
            render();
            sync();
        };

        const removeMeal = (index) => {
            state.splice(index, 1);
            render();
            sync();
        };

        const addMeal = () => {
            state.push(createEmptyMeal(hoursConfig));
            render();
            sync();
        };

        const createField = (label, control) => {
            const wrapper = document.createElement('label');
            wrapper.className = 'fp-resv-meal-plan__field';
            const caption = document.createElement('span');
            caption.textContent = label;
            wrapper.appendChild(caption);
            wrapper.appendChild(control);
            return wrapper;
        };

        const createInput = (value, onChange, type = 'text') => {
            const inputEl = document.createElement('input');
            inputEl.type = type;
            if (type === 'number') {
                inputEl.step = '1';
                inputEl.min = '0';
            }
            inputEl.value = value;
            inputEl.addEventListener('input', (event) => {
                onChange(event.target.value);
            });
            return inputEl;
        };

        const computeTitle = (meal) => meal.label.trim() || meal.key.trim() || strings.keyLabel;

        const createDefaultRange = (ranges) => {
            if (ranges.length) {
                const last = ranges[ranges.length - 1];
                const lastEnd = normalizeTime(last.end);
                if (lastEnd) {
                    const parts = lastEnd.split(':').map((part) => parseInt(part, 10));
                    const baseHours = Number.isFinite(parts[0]) ? parts[0] : 19;
                    const baseMinutes = Number.isFinite(parts[1]) ? parts[1] : 0;
                    const start = clampTime(baseHours + 2, baseMinutes);
                    let end = clampTime(baseHours + 4, baseMinutes);
                    if (end <= start) {
                        end = clampTime(baseHours + 3, baseMinutes);
                    }
                    if (end <= start) {
                        end = clampTime(baseHours + 2, baseMinutes + 30);
                    }
                    return { start, end: end > start ? end : clampTime(baseHours + 2, baseMinutes + 45) };
                }
            }
            return { start: '19:00', end: '21:00' };
        };

        const createHoursField = (meal, index) => {
            const field = document.createElement('div');
            field.className = 'fp-resv-meal-plan__field fp-resv-meal-plan__field--wide';

            const caption = document.createElement('span');
            caption.textContent = strings.hoursLabel;
            field.appendChild(caption);

            if (strings.hoursHint) {
                const hint = document.createElement('p');
                hint.className = 'fp-resv-meal-plan__hint';
                hint.textContent = strings.hoursHint;
                field.appendChild(hint);
            }

            const editor = document.createElement('div');
            editor.className = 'fp-resv-meal-plan__hours';
            field.appendChild(editor);

            const renderEditor = () => {
                editor.innerHTML = '';

                const grid = document.createElement('div');
                grid.className = 'fp-resv-meal-plan__hours-grid';
                editor.appendChild(grid);

                hoursConfig.days.forEach((day) => {
                    const dayKey = day.key;
                    const dayCard = document.createElement('article');
                    dayCard.className = 'fp-resv-meal-plan__hours-day';
                    dayCard.dataset.day = dayKey;

                    const header = document.createElement('header');
                    header.className = 'fp-resv-meal-plan__hours-header';

                    const toggle = document.createElement('label');
                    toggle.className = 'fp-resv-meal-plan__hours-toggle';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    const hoursState = ensureMealHoursDefinition(meal, hoursConfig);
                    const ranges = Array.isArray(hoursState[dayKey]) ? hoursState[dayKey] : [];
                    checkbox.checked = ranges.length > 0;
                    checkbox.addEventListener('change', () => {
                        updateMeal(index, (target) => {
                            const targetState = ensureMealHoursDefinition(target, hoursConfig);
                            if (checkbox.checked) {
                                const existing = Array.isArray(targetState[dayKey]) ? targetState[dayKey] : [];
                                if (!existing.length) {
                                    targetState[dayKey] = [createDefaultRange(existing)];
                                }
                            } else {
                                targetState[dayKey] = [];
                            }
                            syncMealHoursDefinition(target, hoursConfig);
                        }, { rerender: true });
                    });
                    const toggleText = document.createElement('span');
                    toggleText.textContent = day.label;
                    toggle.appendChild(checkbox);
                    toggle.appendChild(toggleText);

                    const closedBadge = document.createElement('span');
                    closedBadge.className = 'fp-resv-meal-plan__hours-status';
                    closedBadge.textContent = hoursConfig.strings.closed;
                    if (ranges.length > 0) {
                        closedBadge.hidden = true;
                    }

                    header.appendChild(toggle);
                    header.appendChild(closedBadge);
                    dayCard.appendChild(header);

                    const rangeList = document.createElement('div');
                    rangeList.className = 'fp-resv-meal-plan__hours-ranges';

                    if (ranges.length === 0) {
                        const empty = document.createElement('p');
                        empty.className = 'fp-resv-meal-plan__hours-empty';
                        empty.textContent = hoursConfig.strings.closed;
                        rangeList.appendChild(empty);
                    } else {
                        ranges.forEach((range, rangeIndex) => {
                            const row = document.createElement('div');
                            row.className = 'fp-resv-meal-plan__hours-range';

                            const fromLabel = document.createElement('label');
                            fromLabel.className = 'fp-resv-meal-plan__hours-field';
                            fromLabel.innerHTML = '<span>' + hoursConfig.strings.from + '</span>';
                            const fromInput = document.createElement('input');
                            fromInput.type = 'time';
                            fromInput.value = normalizeTime(range.start) || '';
                            fromInput.addEventListener('change', (event) => {
                                const target = event.target;
                                if (!(target instanceof HTMLInputElement)) {
                                    return;
                                }
                                const value = normalizeTime(target.value);
                                updateMeal(index, (mealTarget) => {
                                    const targetState = ensureMealHoursDefinition(mealTarget, hoursConfig);
                                    const targetRanges = Array.isArray(targetState[dayKey]) ? targetState[dayKey] : [];
                                    if (!targetRanges[rangeIndex]) {
                                        targetRanges[rangeIndex] = { start: '', end: '' };
                                    }
                                    targetRanges[rangeIndex].start = value;
                                    syncMealHoursDefinition(mealTarget, hoursConfig);
                                });
                                target.value = value;
                            });
                            fromLabel.appendChild(fromInput);

                            const toLabel = document.createElement('label');
                            toLabel.className = 'fp-resv-meal-plan__hours-field';
                            toLabel.innerHTML = '<span>' + hoursConfig.strings.to + '</span>';
                            const toInput = document.createElement('input');
                            toInput.type = 'time';
                            toInput.value = normalizeTime(range.end) || '';
                            toInput.addEventListener('change', (event) => {
                                const target = event.target;
                                if (!(target instanceof HTMLInputElement)) {
                                    return;
                                }
                                const value = normalizeTime(target.value);
                                updateMeal(index, (mealTarget) => {
                                    const targetState = ensureMealHoursDefinition(mealTarget, hoursConfig);
                                    const targetRanges = Array.isArray(targetState[dayKey]) ? targetState[dayKey] : [];
                                    if (!targetRanges[rangeIndex]) {
                                        targetRanges[rangeIndex] = { start: '', end: '' };
                                    }
                                    targetRanges[rangeIndex].end = value;
                                    syncMealHoursDefinition(mealTarget, hoursConfig);
                                });
                                target.value = value;
                            });
                            toLabel.appendChild(toInput);

                            const removeButton = document.createElement('button');
                            removeButton.type = 'button';
                            removeButton.className = 'button-link fp-resv-meal-plan__hours-remove';
                            removeButton.textContent = hoursConfig.strings.removeRange;
                            removeButton.addEventListener('click', () => {
                                updateMeal(index, (mealTarget) => {
                                    const targetState = ensureMealHoursDefinition(mealTarget, hoursConfig);
                                    const targetRanges = Array.isArray(targetState[dayKey]) ? targetState[dayKey] : [];
                                    targetRanges.splice(rangeIndex, 1);
                                    targetState[dayKey] = targetRanges;
                                    syncMealHoursDefinition(mealTarget, hoursConfig);
                                }, { rerender: true });
                            });

                            row.appendChild(fromLabel);
                            row.appendChild(toLabel);
                            row.appendChild(removeButton);
                            rangeList.appendChild(row);
                        });
                    }

                    const addButton = document.createElement('button');
                    addButton.type = 'button';
                    addButton.className = 'button button-secondary fp-resv-meal-plan__hours-add';
                    addButton.textContent = hoursConfig.strings.addRange;
                    addButton.disabled = !checkbox.checked;
                    addButton.addEventListener('click', () => {
                        updateMeal(index, (mealTarget) => {
                            const targetState = ensureMealHoursDefinition(mealTarget, hoursConfig);
                            const targetRanges = Array.isArray(targetState[dayKey]) ? targetState[dayKey] : [];
                            const nextRange = createDefaultRange(targetRanges);
                            targetRanges.push(nextRange);
                            targetState[dayKey] = targetRanges;
                            syncMealHoursDefinition(mealTarget, hoursConfig);
                        }, { rerender: true });
                    });

                    dayCard.appendChild(rangeList);
                    dayCard.appendChild(addButton);
                    grid.appendChild(dayCard);
                });
            };

            renderEditor();

            return field;
        };

        const renderMealCard = (meal, index) => {
            const card = document.createElement('section');
            card.className = 'fp-resv-meal-plan__card';

            const header = document.createElement('header');
            header.className = 'fp-resv-meal-plan__card-header';

            const title = document.createElement('div');
            title.className = 'fp-resv-meal-plan__title';
            title.textContent = computeTitle(meal);

            const defaultWrapper = document.createElement('label');
            defaultWrapper.className = 'fp-resv-meal-plan__default';
            const defaultRadio = document.createElement('input');
            defaultRadio.type = 'radio';
            defaultRadio.name = 'fp-resv-meal-plan-default-' + rootIndex;
            defaultRadio.checked = Boolean(meal.active);
            defaultRadio.addEventListener('change', () => {
                setDefaultMeal(index);
            });
            const defaultLabel = document.createElement('span');
            defaultLabel.textContent = strings.defaultLabel;
            defaultWrapper.appendChild(defaultRadio);
            defaultWrapper.appendChild(defaultLabel);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button button-link-delete fp-resv-meal-plan__remove';
            removeButton.textContent = strings.removeMeal;
            removeButton.addEventListener('click', () => {
                removeMeal(index);
            });

            header.appendChild(title);
            header.appendChild(defaultWrapper);
            header.appendChild(removeButton);
            card.appendChild(header);

            const grid = document.createElement('div');
            grid.className = 'fp-resv-meal-plan__grid';

            const keyField = createField(strings.keyLabel, createInput(meal.key, (value) => {
                updateMeal(index, (target) => {
                    target.key = value;
                    title.textContent = computeTitle(target);
                });
            }));

            const labelField = createField(strings.labelLabel, createInput(meal.label, (value) => {
                updateMeal(index, (target) => {
                    target.label = value;
                    title.textContent = computeTitle(target);
                });
            }));

            const hintField = createField(strings.hintLabel, createInput(meal.hint, (value) => {
                updateMeal(index, (target) => {
                    target.hint = value;
                });
            }));

            const noticeField = createField(strings.noticeLabel, createInput(meal.notice, (value) => {
                updateMeal(index, (target) => {
                    target.notice = value;
                });
            }));

            const priceInput = createInput(meal.price, (value) => {
                updateMeal(index, (target) => {
                    target.price = value;
                });
            }, 'number');
            priceInput.step = '0.01';
            priceInput.min = '0';
            const priceField = createField(strings.priceLabel, priceInput);

            const badgeField = createField(strings.badgeLabel, createInput(meal.badge, (value) => {
                updateMeal(index, (target) => {
                    target.badge = value;
                });
            }));

            const badgeIconField = createField(strings.badgeIconLabel, createInput(meal.badge_icon, (value) => {
                updateMeal(index, (target) => {
                    target.badge_icon = value;
                });
            }));

            grid.appendChild(keyField);
            grid.appendChild(labelField);
            grid.appendChild(hintField);
            grid.appendChild(noticeField);
            grid.appendChild(priceField);
            grid.appendChild(badgeField);
            grid.appendChild(badgeIconField);

            const advanced = document.createElement('div');
            advanced.className = 'fp-resv-meal-plan__advanced';

            const slotInput = createInput(meal.slot, (value) => {
                updateMeal(index, (target) => {
                    target.slot = value;
                });
            }, 'number');
            slotInput.min = '5';
            const slotField = createField(strings.slotLabel, slotInput);

            const turnInput = createInput(meal.turn, (value) => {
                updateMeal(index, (target) => {
                    target.turn = value;
                });
            }, 'number');
            turnInput.min = '15';
            turnInput.step = '5';
            const turnField = createField(strings.turnLabel, turnInput);

            const bufferInput = createInput(meal.buffer, (value) => {
                updateMeal(index, (target) => {
                    target.buffer = value;
                });
            }, 'number');
            bufferInput.min = '0';
            const bufferField = createField(strings.bufferLabel, bufferInput);

            const parallelInput = createInput(meal.parallel, (value) => {
                updateMeal(index, (target) => {
                    target.parallel = value;
                });
            }, 'number');
            parallelInput.min = '1';
            const parallelField = createField(strings.parallelLabel, parallelInput);

            const capacityInput = createInput(meal.capacity, (value) => {
                updateMeal(index, (target) => {
                    target.capacity = value;
                });
            }, 'number');
            capacityInput.min = '1';
            const capacityField = createField(strings.capacityLabel, capacityInput);

            advanced.appendChild(createHoursField(meal, index));
            const numericGrid = document.createElement('div');
            numericGrid.className = 'fp-resv-meal-plan__numeric-grid';
            numericGrid.appendChild(slotField);
            numericGrid.appendChild(turnField);
            numericGrid.appendChild(bufferField);
            numericGrid.appendChild(parallelField);
            numericGrid.appendChild(capacityField);
            advanced.appendChild(numericGrid);

            card.appendChild(grid);
            card.appendChild(advanced);

            return card;
        };

        const render = () => {
            root.innerHTML = '';
            ensureDefault();

            if (!state.length) {
                const empty = document.createElement('p');
                empty.className = 'fp-resv-meal-plan__empty';
                empty.textContent = strings.emptyState;
                root.appendChild(empty);
            } else {
                state.forEach((meal, index) => {
                    const card = renderMealCard(meal, index);
                    root.appendChild(card);
                });
            }

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'button button-secondary fp-resv-meal-plan__add';
            addButton.textContent = strings.addMeal;
            addButton.addEventListener('click', () => {
                addMeal();
            });
            root.appendChild(addButton);
        };

        render();
        sync();
    });
})();
