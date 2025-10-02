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

    const normalizeMeal = (raw) => {
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

    const createEmptyMeal = () => ({
        key: '',
        label: '',
        hint: '',
        notice: '',
        price: '',
        badge: '',
        badge_icon: '',
        active: false,
        hours: '',
        slot: '',
        turn: '',
        buffer: '',
        parallel: '',
        capacity: '',
        extras: {},
        availabilityExtras: {},
    });

    const serializeMeal = (meal) => {
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

        let state = [];
        try {
            const parsed = JSON.parse(root.getAttribute('data-value') || '[]');
            if (Array.isArray(parsed)) {
                state = parsed.map(normalizeMeal);
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
            const serialized = state.map(serializeMeal);
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
            state.push(createEmptyMeal());
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

        const createTextarea = (value, onChange) => {
            const textarea = document.createElement('textarea');
            textarea.rows = 3;
            textarea.value = value;
            textarea.addEventListener('input', (event) => {
                onChange(event.target.value);
            });
            return textarea;
        };

        const computeTitle = (meal) => meal.label.trim() || meal.key.trim() || strings.keyLabel;

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

            const hoursControl = createTextarea(meal.hours, (value) => {
                updateMeal(index, (target) => {
                    target.hours = value;
                });
            });
            hoursControl.placeholder = strings.hoursHint;
            const hoursField = createField(strings.hoursLabel, hoursControl);
            hoursField.classList.add('fp-resv-meal-plan__field--wide');

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

            advanced.appendChild(hoursField);
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
