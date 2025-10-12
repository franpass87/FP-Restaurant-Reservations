import { pushDataLayerEvent } from './tracking/dataLayer.js';
import { FormApp } from './onepage.js';

/**
 * Ensures widget visibility by forcing display styles and checking parent containers
 * This prevents theme/plugin CSS conflicts from hiding the form
 */
function ensureWidgetVisibility(widget) {
    if (!widget) {
        return;
    }
    
    // Force visibility with inline styles as a fallback
    widget.style.display = 'block';
    widget.style.visibility = 'visible';
    widget.style.opacity = '1';
    widget.style.position = 'relative';
    widget.style.width = '100%';
    widget.style.height = 'auto';
    
    // Ensure parent containers don't hide the widget
    let parent = widget.parentElement;
    let depth = 0;
    while (parent && depth < 5) {
        const display = window.getComputedStyle(parent).display;
        if (display === 'none') {
            console.warn('[FP-RESV] Found hidden parent element, making visible:', parent);
            parent.style.display = 'block';
        }
        parent = parent.parentElement;
        depth++;
    }
    
    console.log('[FP-RESV] Widget visibility ensured:', widget.id || 'unnamed');
}

/**
 * Auto-check visibility every second for the first 10 seconds
 * This catches cases where CSS is applied after initialization
 */
function autoCheckVisibility() {
    let checks = 0;
    const maxChecks = 10;
    
    const interval = setInterval(function() {
        checks++;
        
        const widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
        let hasHiddenWidget = false;
        
        Array.prototype.forEach.call(widgets, function(widget) {
            const computed = window.getComputedStyle(widget);
            if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
                console.warn('[FP-RESV] Widget became hidden, forcing visibility again:', widget.id || 'unnamed');
                ensureWidgetVisibility(widget);
                hasHiddenWidget = true;
            }
        });
        
        if (checks >= maxChecks || !hasHiddenWidget) {
            clearInterval(interval);
            if (checks >= maxChecks) {
                console.log('[FP-RESV] Visibility auto-check completed after ' + checks + ' checks');
            }
        }
    }, 1000);
}

function initializeFPResv() {
    console.log('[FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active');
    const widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
    console.log('[FP-RESV] Found widgets:', widgets.length);

    if (widgets.length === 0) {
        console.warn('[FP-RESV] No widgets found on page. Expected shortcode [fp_reservations] or Gutenberg block.');
    }

    Array.prototype.forEach.call(widgets, function (widget) {
        try {
            // Ensure widget is visible FIRST
            ensureWidgetVisibility(widget);
            
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
    document.addEventListener('DOMContentLoaded', function() {
        initializeFPResv();
        // Start auto-check after initialization
        setTimeout(autoCheckVisibility, 500);
    });
} else {
    initializeFPResv();
    // Start auto-check after initialization
    setTimeout(autoCheckVisibility, 500);
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


