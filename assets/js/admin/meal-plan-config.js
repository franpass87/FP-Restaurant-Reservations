/**
 * Configurazioni e costanti per il meal plan editor
 */

export const defaultStrings = {
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
    slotTooltip: 'Minutes between each reservation start time that guests can book.',
    turnLabel: 'Turn duration (minutes)',
    turnTooltip: 'Total dining time you expect to keep a table before it becomes available again.',
    bufferLabel: 'Buffer (minutes)',
    bufferTooltip: 'Additional minutes kept between turns before offering the table again.',
    parallelLabel: 'Parallel reservations',
    parallelTooltip: 'How many reservations can start at the same time slot.',
    capacityLabel: 'Maximum capacity',
    removeMeal: 'Remove meal',
    emptyState: 'No meals configured yet. Add one to get started.',
    applyToAll: 'Apply to all days',
    removeSlot: 'Remove slot',
};

export const knownMealKeys = new Set([
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

export const knownAvailabilityKeys = new Set([
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

export const defaultHoursConfig = {
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

export const MAX_MINUTES = (23 * 60) + 59;
