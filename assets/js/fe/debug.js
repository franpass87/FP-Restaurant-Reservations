function normalizeDetailValue(value) {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value.trim();
    }

    if (Array.isArray(value)) {
        return value
            .map((item) => normalizeDetailValue(item))
            .filter((item) => item !== '')
            .join('; ');
    }

    if (typeof value === 'object') {
        if (typeof value.message === 'string' && value.message.trim() !== '') {
            return value.message.trim();
        }

        if (typeof value.detail === 'string' && value.detail.trim() !== '') {
            return value.detail.trim();
        }
    }

    const stringified = String(value);

    return stringified.trim();
}

export function extractDebugDetails(source) {
    if (source === null || source === undefined) {
        return '';
    }

    const queue = Array.isArray(source) ? [...source] : [source];

    while (queue.length > 0) {
        const current = queue.shift();

        if (current === null || current === undefined) {
            continue;
        }

        if (Array.isArray(current)) {
            queue.push(...current);
            continue;
        }

        if (typeof current !== 'object') {
            const normalized = normalizeDetailValue(current);
            if (normalized !== '') {
                return normalized;
            }

            continue;
        }

        const detailKeys = ['details', 'detail', 'debug', 'error'];
        for (let index = 0; index < detailKeys.length; index += 1) {
            const key = detailKeys[index];
            if (Object.prototype.hasOwnProperty.call(current, key)) {
                const normalized = normalizeDetailValue(current[key]);
                if (normalized !== '') {
                    return normalized;
                }
            }
        }

        if (Object.prototype.hasOwnProperty.call(current, 'data') && current.data && typeof current.data === 'object') {
            queue.push(current.data);
        }
    }

    return '';
}

export function formatDebugMessage(message, source) {
    const details = extractDebugDetails(source);
    if (details === '') {
        return message;
    }

    if (!message) {
        return details;
    }

    if (message.includes(details)) {
        return message;
    }

    return message + ' (' + details + ')';
}
