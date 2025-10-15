/**
 * FP Restaurant Reservations - Form Colors Admin
 * Live preview and color picker functionality
 */

/* global fpResvFormColors */

(function() {
    'use strict';

    const FormColors = {
        iframe: null,
        iframeDoc: null,
        colorPickers: {},
        
        init() {
            this.iframe = document.getElementById('fp-resv-preview-iframe');
            if (!this.iframe) {
                console.error('Preview iframe not found');
                return;
            }

            // Setup iframe
            this.setupIframe();

            // Setup color pickers
            this.setupColorPickers();

            // Setup presets
            this.setupPresets();

            // Setup text inputs sync
            this.setupTextInputs();
        },

        setupIframe() {
            const iframeDoc = this.iframe.contentDocument || this.iframe.contentWindow.document;
            this.iframeDoc = iframeDoc;

            // Create basic HTML structure
            const html = `
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview</title>
    <link rel="stylesheet" href="${fpResvFormColors.cssUrl}">
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .preview-container {
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="fp-resv-widget fp-resv fp-card">
            <div class="fp-topbar">
                <div>
                    <h2 class="fp-resv-widget__headline">Prenota un Tavolo</h2>
                    <p class="fp-resv-widget__subheadline">Scegli data, orario e completa i tuoi dati</p>
                </div>
            </div>
            
            <div class="fp-section">
                <div class="fp-field">
                    <label>
                        <span class="fp-field__label">Data Prenotazione</span>
                        <input type="date" class="fp-input" value="2025-10-15">
                    </label>
                </div>

                <div class="fp-field">
                    <label>
                        <span class="fp-field__label">Numero Persone</span>
                        <div class="fp-resv-party-input-wrapper">
                            <button type="button" class="fp-resv-party-btn">−</button>
                            <input type="number" class="fp-input fp-resv-party-input" value="2">
                            <button type="button" class="fp-resv-party-btn">+</button>
                        </div>
                    </label>
                </div>

                <div class="fp-meals">
                    <div class="fp-meals__header">
                        <h3 class="fp-meals__title">Scegli il Servizio</h3>
                    </div>
                    <div class="fp-meals__list">
                        <button type="button" class="fp-pill fp-meal-pill" data-active="true">
                            <span class="fp-pill__label">🍽️ Pranzo</span>
                        </button>
                        <button type="button" class="fp-pill fp-meal-pill">
                            <span class="fp-pill__label">🌙 Cena</span>
                        </button>
                    </div>
                </div>

                <div class="fp-slots fp-resv-slots">
                    <h4>Orari Disponibili</h4>
                    <ul class="fp-slots__list fp-resv-slots__list">
                        <li><button type="button" class="fp-slot-pill">19:00</button></li>
                        <li><button type="button" class="fp-slot-pill" aria-pressed="true">19:30</button></li>
                        <li><button type="button" class="fp-slot-pill">20:00</button></li>
                        <li><button type="button" class="fp-slot-pill">20:30</button></li>
                        <li><button type="button" class="fp-slot-pill">21:00</button></li>
                        <li><button type="button" class="fp-slot-pill">21:30</button></li>
                    </ul>
                </div>
            </div>

            <div class="fp-sticky">
                <button type="button" class="fp-btn fp-btn--primary">Prosegui</button>
            </div>

            <div class="fp-alert fp-alert--success" style="margin-top: 1rem;">
                <p>✓ Prenotazione confermata con successo!</p>
            </div>
        </div>
    </div>
</body>
</html>
            `;

            iframeDoc.open();
            iframeDoc.write(html);
            iframeDoc.close();

            // Wait for iframe to load
            this.iframe.addEventListener('load', () => {
                this.updatePreviewColors();
            });
        },

        setupColorPickers() {
            const pickers = document.querySelectorAll('.fp-color-picker');
            pickers.forEach(picker => {
                this.colorPickers[picker.id] = picker;
                picker.addEventListener('input', (e) => {
                    this.onColorChange(picker);
                });
            });
        },

        setupTextInputs() {
            const textInputs = document.querySelectorAll('.fp-color-text');
            textInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    const pickerId = input.getAttribute('data-for');
                    const picker = document.getElementById(pickerId);
                    if (picker && /^#[0-9A-Fa-f]{6}$/.test(input.value)) {
                        picker.value = input.value;
                        this.onColorChange(picker);
                    }
                });
            });
        },

        setupPresets() {
            const presetBtns = document.querySelectorAll('.fp-resv-preset-btn');
            presetBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    try {
                        const colorsAttr = btn.getAttribute('data-colors');
                        if (!colorsAttr) {
                            console.error('Missing data-colors attribute');
                            return;
                        }
                        const colors = JSON.parse(colorsAttr);
                        this.applyPreset(colors);
                    } catch (error) {
                        console.error('Error parsing preset colors:', error);
                    }
                });
            });
        },

        applyPreset(colors) {
            Object.keys(colors).forEach(key => {
                const picker = document.getElementById('fp_color_' + key);
                const textInput = document.querySelector(`[data-for="fp_color_${key}"]`);
                
                if (picker) {
                    picker.value = colors[key];
                    if (textInput) {
                        textInput.value = colors[key];
                    }
                }
            });

            this.updatePreviewColors();
        },

        onColorChange(picker) {
            // Update text input
            const textInput = document.querySelector(`[data-for="${picker.id}"]`);
            if (textInput) {
                textInput.value = picker.value;
            }

            // Update preview
            this.updatePreviewColors();
        },

        updatePreviewColors() {
            if (!this.iframeDoc) return;

            const colors = this.getCurrentColors();
            const css = this.generateCSSVariables(colors);

            // Remove old style
            const oldStyle = this.iframeDoc.getElementById('fp-custom-colors');
            if (oldStyle) {
                oldStyle.remove();
            }

            // Add new style
            const style = this.iframeDoc.createElement('style');
            style.id = 'fp-custom-colors';
            style.textContent = css;
            this.iframeDoc.head.appendChild(style);
        },

        getCurrentColors() {
            const colors = {};
            Object.keys(this.colorPickers).forEach(id => {
                const key = id.replace('fp_color_', '');
                colors[key] = this.colorPickers[id].value;
            });
            return colors;
        },

        generateCSSVariables(colors) {
            const hexToRgba = (hex, alpha) => {
                hex = hex.replace('#', '');
                const r = parseInt(hex.substring(0, 2), 16);
                const g = parseInt(hex.substring(2, 4), 16);
                const b = parseInt(hex.substring(4, 6), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            };

            const hexToRgb = (hex) => {
                hex = hex.replace('#', '');
                const r = parseInt(hex.substring(0, 2), 16);
                const g = parseInt(hex.substring(2, 4), 16);
                const b = parseInt(hex.substring(4, 6), 16);
                return `${r}, ${g}, ${b}`;
            };

            return `
:root {
    --fp-color-primary: ${colors.primary || '#000000'};
    --fp-color-primary-hover: ${colors.primary_hover || '#1a1a1a'};
    --fp-color-primary-light: ${hexToRgba(colors.primary || '#000000', 0.05)};
    --fp-color-primary-rgb: ${hexToRgb(colors.primary || '#000000')};
    --fp-color-surface: ${colors.surface || '#ffffff'};
    --fp-color-surface-alt: ${colors.surface_alt || '#fafafa'};
    --fp-color-text: ${colors.text || '#000000'};
    --fp-color-text-muted: ${colors.text_muted || '#666666'};
    --fp-color-border: ${colors.border || '#e0e0e0'};
    --fp-resv-button-bg: ${colors.button_bg || '#000000'};
    --fp-resv-button-text: ${colors.button_text || '#ffffff'};
    --fp-gradient-primary: linear-gradient(135deg, ${colors.primary || '#000000'} 0%, ${colors.primary_hover || '#1a1a1a'} 100%);
}
            `;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FormColors.init());
    } else {
        FormColors.init();
    }
})();

