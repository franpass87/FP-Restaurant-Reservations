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

// Track already initialized widgets to avoid double initialization
const initializedWidgets = new Set();

function initializeFPResv() {
    console.log('[FP-RESV] Plugin v0.1.11 loaded - Complete form functionality active');
    console.log('[FP-RESV] Current readyState:', document.readyState);
    console.log('[FP-RESV] Body innerHTML length:', document.body ? document.body.innerHTML.length : 0);
    
    const widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
    console.log('[FP-RESV] Found widgets:', widgets.length);
    
    // Debug: verifica cosa c'è nel DOM
    const byDataAttr = document.querySelectorAll('[data-fp-resv]');
    const byClass = document.querySelectorAll('.fp-resv-widget');
    const byAppAttr = document.querySelectorAll('[data-fp-resv-app]');
    console.log('[FP-RESV] Debug - Found by [data-fp-resv]:', byDataAttr.length);
    console.log('[FP-RESV] Debug - Found by .fp-resv-widget:', byClass.length);
    console.log('[FP-RESV] Debug - Found by [data-fp-resv-app]:', byAppAttr.length);
    
    // Cerca se c'è testo "fp-resv" nel body
    if (document.body && document.body.innerHTML.indexOf('fp-resv') !== -1) {
        console.log('[FP-RESV] Debug - "fp-resv" text found in body HTML');
    } else {
        console.log('[FP-RESV] Debug - "fp-resv" text NOT found in body HTML');
    }

    if (widgets.length === 0) {
        console.warn('[FP-RESV] No widgets found on page. Expected shortcode [fp_reservations] or Gutenberg block.');
        console.log('[FP-RESV] Searching for potential widget containers...');
        
        // Debug: check for common WordPress content areas
        const content = document.querySelector('.entry-content, .post-content, .page-content, main, article');
        if (content) {
            console.log('[FP-RESV] Found content container:', content.className || 'unnamed');
            console.log('[FP-RESV] Content container innerHTML length:', content.innerHTML.length);
            
            // Check if there's any fp-resv related content
            if (content.innerHTML.includes('fp-resv')) {
                console.log('[FP-RESV] Found fp-resv string in content, but no valid widget element');
            }
        } else {
            console.log('[FP-RESV] No standard content container found');
        }
        
        return;
    }

    Array.prototype.forEach.call(widgets, function (widget) {
        // Skip if already initialized
        if (initializedWidgets.has(widget)) {
            console.log('[FP-RESV] Widget already initialized, skipping:', widget.id || 'unnamed');
            return;
        }
        
        try {
            // Mark as initialized
            initializedWidgets.add(widget);
            
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
            // Remove from initialized set on error so it can be retried
            initializedWidgets.delete(widget);
        }
    });
}

// Set up MutationObserver to detect widgets added dynamically
function setupWidgetObserver() {
    if (typeof MutationObserver === 'undefined') {
        console.warn('[FP-RESV] MutationObserver not supported, dynamic widgets won\'t be detected');
        return;
    }
    
    const observer = new MutationObserver(function(mutations) {
        let hasNewWidgets = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                Array.prototype.forEach.call(mutation.addedNodes, function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Check if the node itself is a widget
                        if (node.matches && (node.matches('[data-fp-resv]') || node.matches('.fp-resv-widget') || node.matches('[data-fp-resv-app]'))) {
                            hasNewWidgets = true;
                        }
                        // Check if the node contains a widget
                        else if (node.querySelector) {
                            const widget = node.querySelector('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
                            if (widget) {
                                hasNewWidgets = true;
                            }
                        }
                    }
                });
            }
        });
        
        if (hasNewWidgets) {
            console.log('[FP-RESV] New widget(s) detected in DOM, initializing...');
            initializeFPResv();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    console.log('[FP-RESV] MutationObserver set up to detect dynamic widgets');
}

// Retry initialization with increasing delays
function retryInitialization() {
    const delays = [500, 1000, 2000, 3000]; // Retry after 0.5s, 1s, 2s, 3s
    
    delays.forEach(function(delay) {
        setTimeout(function() {
            const currentWidgetCount = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]').length;
            if (currentWidgetCount > initializedWidgets.size) {
                console.log('[FP-RESV] Retry: Found ' + currentWidgetCount + ' widgets, ' + initializedWidgets.size + ' initialized');
                initializeFPResv();
            }
        }, delay);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeFPResv();
        // Start auto-check after initialization
        setTimeout(autoCheckVisibility, 500);
        // Set up observer for dynamic widgets
        setupWidgetObserver();
        // Retry in case widgets load late
        retryInitialization();
    });
} else {
    initializeFPResv();
    // Start auto-check after initialization
    setTimeout(autoCheckVisibility, 500);
    // Set up observer for dynamic widgets
    setupWidgetObserver();
    // Retry in case widgets load late
    retryInitialization();
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


