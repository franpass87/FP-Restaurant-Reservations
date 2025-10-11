/**
 * Utility functions per il meal plan editor
 */

import { MAX_MINUTES, defaultHoursConfig } from './meal-plan-config.js';

export const normalizeHoursConfig = (raw) => {
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
        config.strings = Object.assign({}, defaultHoursConfig.strings, raw.strings);
    }

    return config;
};

export const createEmptyHoursState = (config) => {
    const state = {};
    for (const day of config.days) {
        state[day.key] = [];
    }
    return state;
};

export const normalizeTime = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }
    const trimmed = value.trim();
    if (!/^\d{1,2}:\d{2}$/.test(trimmed)) {
        return '';
    }
    return trimmed;
};

export const timeToMinutes = (value) => {
    const time = normalizeTime(value);
    if (!time) {
        return null;
    }
    const [hours, minutes] = time.split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return null;
    }
    return (hours * 60) + minutes;
};

export const minutesToTime = (minutes) => {
    if (minutes === null || minutes === undefined || Number.isNaN(minutes)) {
        return '';
    }
    const clamped = Math.max(0, Math.min(MAX_MINUTES, Math.floor(minutes)));
    const h = Math.floor(clamped / 60);
    const m = clamped % 60;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
};

export const parseDurationMinutes = (value) => {
    if (value === null || value === undefined) {
        return null;
    }
    if (typeof value === 'number') {
        return Number.isFinite(value) && value >= 0 ? Math.floor(value) : null;
    }
    const parsed = parseInt(String(value), 10);
    return Number.isNaN(parsed) || parsed < 0 ? null : parsed;
};

export const computeMealDuration = (meal) => {
    const turn = parseDurationMinutes(meal.turn || meal.turnover || meal.turnover_minutes);
    const buffer = parseDurationMinutes(meal.buffer || meal.buffer_minutes);
    if (turn === null) {
        return null;
    }
    return turn + (buffer || 0);
};

export const computeRangeEndForMeal = (meal, start) => {
    const duration = computeMealDuration(meal);
    if (duration === null || start === null) {
        return null;
    }
    return start + duration;
};

export const splitHoursEntries = (line) => {
    if (!line || typeof line !== 'string') {
        return [];
    }

    return line
        .split(';')
        .map((part) => part.trim())
        .filter((part) => {
            const normalized = part.toLowerCase();
            return normalized.includes('=') && !normalized.includes('closed');
        })
        .map((entry) => {
            const [dayKey, hoursValue] = entry.split('=').map((s) => s.trim());
            return { dayKey: dayKey.toLowerCase(), hoursValue };
        })
        .filter((item) => item.dayKey && item.hoursValue);
};

export const normalizeNumericInput = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const parsed = parseInt(String(value), 10);
    if (Number.isNaN(parsed)) {
        return '';
    }

    return String(Math.max(0, parsed));
};

export const normalizePriceInput = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const str = String(value).replace(',', '.');
    const parsed = parseFloat(str);
    if (Number.isNaN(parsed)) {
        return '';
    }

    return String(Math.max(0, parsed));
};

export const serializeInteger = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }
    const parsed = parseInt(String(value), 10);
    return Number.isNaN(parsed) || parsed < 0 ? 0 : parsed;
};
