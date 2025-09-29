(function () {
    var root = document.querySelector('[data-fp-resv-closures]');
    if (!root) {
        return;
    }

    var settings = window.fpResvClosuresSettings || {};
    var previewSummary = (settings.preview && settings.preview.summary) || {};
    var headline = (settings.strings && settings.strings.headline) || 'Chiusure programmate';
    var description = (settings.strings && settings.strings.description) || 'Gestisci chiusure e periodi speciali dal pannello impostazioni dedicato.';
    var createCta = (settings.strings && settings.strings.createCta) || 'Nuova chiusura';
    var empty = (settings.strings && settings.strings.empty) || 'Nessuna chiusura programmata per il periodo selezionato.';

    var wrapper = document.createElement('div');
    wrapper.className = 'fp-resv-closures__placeholder';

    wrapper.innerHTML = '' +
        '<header class="fp-resv-closures__header">' +
            '<h2 class="fp-resv-closures__title">' + headline + '</h2>' +
            '<p class="fp-resv-closures__description">' + description + '</p>' +
        '</header>' +
        '<section class="fp-resv-closures__stats">' +
            '<div class="fp-resv-closures__stat">' +
                '<span class="fp-resv-closures__stat-label">' + createCta + '</span>' +
                '<span class="fp-resv-closures__stat-value">' + (previewSummary.total_events || 0) + '</span>' +
            '</div>' +
            '<div class="fp-resv-closures__stat">' +
                '<span class="fp-resv-closures__stat-label">Blocchi (h)</span>' +
                '<span class="fp-resv-closures__stat-value">' + (previewSummary.blocked_hours || 0) + '</span>' +
            '</div>' +
            '<div class="fp-resv-closures__stat">' +
                '<span class="fp-resv-closures__stat-label">Riduzioni</span>' +
                '<span class="fp-resv-closures__stat-value">' + ((previewSummary.capacity_reduction && previewSummary.capacity_reduction.count) || 0) + '</span>' +
            '</div>' +
            '<div class="fp-resv-closures__stat">' +
                '<span class="fp-resv-closures__stat-label">Orari speciali</span>' +
                '<span class="fp-resv-closures__stat-value">' + (previewSummary.special_hours || 0) + '</span>' +
            '</div>' +
        '</section>' +
        '<p class="fp-resv-closures__note">' + empty + '</p>';

    root.appendChild(wrapper);
})();
