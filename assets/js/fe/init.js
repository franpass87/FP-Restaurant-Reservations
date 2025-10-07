import { pushDataLayerEvent } from './tracking/dataLayer.js';
import { FormApp } from './onepage.js';

function initializeFPResv() {
    console.log('[FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active');
    const widgets = document.querySelectorAll('[data-fp-resv]');
    console.log('[FP-RESV] Found widgets:', widgets.length);

    Array.prototype.forEach.call(widgets, function (widget) {
        try {
            console.log('[FP-RESV] Initializing widget:', widget.id || 'unnamed');
            console.log('[FP-RESV] Widget sections found:', widget.querySelectorAll('[data-fp-resv-section]').length);

            const app = new FormApp(widget);
            console.log('[FP-RESV] Widget initialized successfully:', widget.id || 'unnamed');

            const sections = app.sections || [];
            sections.forEach(function(section, index) {
                const step = section.getAttribute('data-step');
                const state = section.getAttribute('data-state');
                const hidden = section.hasAttribute('hidden');
                console.log(`[FP-RESV] Step ${index + 1} (${step}): state=${state}, hidden=${hidden}`);
            });

        } catch (error) {
            console.error('[FP-RESV] Error initializing widget:', error);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFPResv);
} else {
    initializeFPResv();
}

document.addEventListener('fp-resv:tracking:push', function (event) {
    if (!event || !event.detail) {
        return;
    }

    const detail = event.detail;
    const name = detail && (detail.event || detail.name);
    if (!name) {
        return;
    }

    const payload = detail.payload || detail.data || {};
    pushDataLayerEvent(name, payload && typeof payload === 'object' ? payload : {});
});


