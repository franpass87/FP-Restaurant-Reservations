/**
 * FP Restaurant Reservations - Form Colors Admin
 * Live preview: markup allineato a form-simple.php + CSS form-simple-inline.css
 */

/* global fpResvFormColors */

(function() {
    'use strict';

    const PREVIEW_ROOT = '#fp-resv-preview-widget';

    const FormColors = {
        iframe: null,
        iframeDoc: null,
        colorPickers: {},

        init() {
            this.iframe = document.getElementById('fp-resv-preview-iframe');
            if (!this.iframe) {
                return;
            }

            if (typeof fpResvFormColors === 'undefined' || !fpResvFormColors || !fpResvFormColors.cssUrl) {
                return;
            }

            this.setupIframe();
            this.setupColorPickers();
            this.setupPresets();
            this.setupTextInputs();
        },

        setupIframe() {
            const iframeDoc = this.iframe.contentDocument || this.iframe.contentWindow.document;
            this.iframeDoc = iframeDoc;

            const html = `
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview</title>
    <link rel="stylesheet" href="${fpResvFormColors.cssUrl}">
    <style>
        body { margin: 0; padding: 16px; background: #eef2f7; font-family: system-ui, sans-serif; }
    </style>
</head>
<body>
    <div id="fp-resv-preview-widget" class="fp-resv-simple">
        <div class="fp-resv-header">
            <div class="fp-resv-header__titles">
                <h2>Prenota un tavolo</h2>
            </div>
        </div>
        <div class="fp-steps-container">
            <div class="fp-step active" data-step="preview">
                <div class="fp-field">
                    <label for="fp-preview-date">Data prenotazione</label>
                    <input type="date" id="fp-preview-date" class="fp-input" value="2026-04-15">
                </div>
                <div class="fp-field">
                    <label id="party-size-label">Numero persone</label>
                    <div class="fp-party-selector" role="group" aria-labelledby="party-size-label">
                        <button type="button" class="fp-btn-minus" tabindex="-1" aria-hidden="true">−</button>
                        <div class="fp-party-display">
                            <span id="party-count">2</span>
                            <span id="party-label">persone</span>
                        </div>
                        <button type="button" class="fp-btn-plus" tabindex="-1" aria-hidden="true">+</button>
                    </div>
                    <small class="fp-hint">Anteprima statica — i controlli non sono collegati al salvataggio.</small>
                </div>
                <div class="fp-field">
                    <label>Servizio</label>
                    <div class="fp-meals">
                        <button type="button" class="fp-meal-btn selected">🍽️ Pranzo</button>
                        <button type="button" class="fp-meal-btn">🌙 Cena</button>
                    </div>
                </div>
                <div class="fp-field">
                    <label>Orari disponibili</label>
                    <div class="fp-time-slots" role="radiogroup" aria-label="Orari">
                        <button type="button" class="fp-time-slot">19:00</button>
                        <button type="button" class="fp-time-slot selected">19:30</button>
                        <button type="button" class="fp-time-slot">20:00</button>
                        <button type="button" class="fp-time-slot">20:30</button>
                        <button type="button" class="fp-time-slot">21:00</button>
                        <button type="button" class="fp-time-slot">21:30</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="fp-buttons">
            <span></span>
            <button type="button" class="fp-btn fp-btn-primary">Prosegui</button>
        </div>
    </div>
</body>
</html>
            `;

            iframeDoc.open();
            iframeDoc.write(html);
            iframeDoc.close();

            this.updatePreviewColors();
        },

        setupColorPickers() {
            document.querySelectorAll('.fp-color-picker').forEach((picker) => {
                this.colorPickers[picker.id] = picker;
                picker.addEventListener('input', () => {
                    this.onColorChange(picker);
                });
            });
        },

        setupTextInputs() {
            document.querySelectorAll('.fp-color-text').forEach((input) => {
                input.addEventListener('input', () => {
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
            document.querySelectorAll('.fp-resv-preset-btn').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    try {
                        const colorsAttr = btn.getAttribute('data-colors');
                        if (!colorsAttr) {
                            return;
                        }
                        const colors = JSON.parse(colorsAttr);
                        this.applyPreset(colors);
                    } catch {
                        return;
                    }
                });
            });
        },

        applyPreset(colors) {
            Object.keys(colors).forEach((key) => {
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
            const textInput = document.querySelector(`[data-for="${picker.id}"]`);
            if (textInput) {
                textInput.value = picker.value;
            }
            this.updatePreviewColors();
        },

        updatePreviewColors() {
            if (!this.iframeDoc) {
                return;
            }

            const colors = this.getCurrentColors();
            const css = this.generateCSSVariables(colors);

            const oldStyle = this.iframeDoc.getElementById('fp-custom-colors');
            if (oldStyle) {
                oldStyle.remove();
            }

            const style = this.iframeDoc.createElement('style');
            style.id = 'fp-custom-colors';
            style.textContent = css;
            this.iframeDoc.head.appendChild(style);
        },

        getCurrentColors() {
            const colors = {};
            Object.keys(this.colorPickers).forEach((id) => {
                const key = id.replace('fp_color_', '');
                colors[key] = this.colorPickers[id].value;
            });
            return colors;
        },

        /**
         * Variabili + override ad alta specificità sull’anteprima (form-simple-inline usa colori fissi).
         *
         * @param {Record<string, string>} colors
         * @return {string}
         */
        generateCSSVariables(colors) {
            const hexToRgba = (hex, alpha) => {
                let h = hex.replace('#', '');
                if (h.length === 3) {
                    h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
                }
                const r = parseInt(h.substring(0, 2), 16);
                const g = parseInt(h.substring(2, 4), 16);
                const b = parseInt(h.substring(4, 6), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            };

            const hexToRgb = (hex) => {
                let h = hex.replace('#', '');
                if (h.length === 3) {
                    h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
                }
                const r = parseInt(h.substring(0, 2), 16);
                const g = parseInt(h.substring(2, 4), 16);
                const b = parseInt(h.substring(4, 6), 16);
                return `${r}, ${g}, ${b}`;
            };

            const p = colors.primary || '#000000';
            const ph = colors.primary_hover || '#1a1a1a';
            const surf = colors.surface || '#ffffff';
            const surfAlt = colors.surface_alt || '#fafafa';
            const txt = colors.text || '#111827';
            const txtMuted = colors.text_muted || '#6b7280';
            const brd = colors.border || '#e5e7eb';
            const btnBg = colors.button_bg || p;
            const btnTx = colors.button_text || '#ffffff';
            const root = PREVIEW_ROOT;

            return `
:root {
    --fp-color-primary: ${p};
    --fp-color-primary-hover: ${ph};
    --fp-color-primary-light: ${hexToRgba(p, 0.08)};
    --fp-color-primary-rgb: ${hexToRgb(p)};
    --fp-color-surface: ${surf};
    --fp-color-surface-alt: ${surfAlt};
    --fp-color-text: ${txt};
    --fp-color-text-muted: ${txtMuted};
    --fp-color-border: ${brd};
    --fp-resv-button-bg: ${btnBg};
    --fp-resv-button-text: ${btnTx};
    --fp-gradient-primary: linear-gradient(135deg, ${p} 0%, ${ph} 100%);
}
${root}.fp-resv-simple {
    background: ${surf} !important;
    border-color: ${hexToRgba(brd, 0.35)} !important;
}
${root}.fp-resv-simple::before {
    background: var(--fp-gradient-primary) !important;
}
${root}.fp-resv-simple h2 {
    color: ${txt} !important;
}
${root}.fp-resv-simple h2::after {
    background: var(--fp-gradient-primary) !important;
}
${root} .fp-field {
    background: ${hexToRgba(surfAlt, 0.85)} !important;
    border-color: ${hexToRgba(brd, 0.25)} !important;
}
${root} .fp-field label {
    color: ${txt} !important;
}
${root} .fp-hint {
    color: ${txtMuted} !important;
}
${root} .fp-field input:not([type="checkbox"]):not([type="radio"]),
${root} .fp-field select,
${root} .fp-field textarea {
    border-color: ${brd} !important;
    color: ${txt} !important;
    background: ${surf} !important;
}
${root} .fp-party-selector {
    border-color: ${brd} !important;
    background: ${hexToRgba(surfAlt, 0.9)} !important;
}
${root} .fp-party-display #party-count,
${root} .fp-party-display #party-label {
    color: ${txt} !important;
}
${root} .fp-meal-btn {
    color: ${txt} !important;
    border-color: ${brd} !important;
    background: ${surf} !important;
}
${root} .fp-meal-btn:hover {
    border-color: ${p} !important;
    background: ${hexToRgba(surfAlt, 1)} !important;
}
${root} .fp-meal-btn.selected {
    background: ${btnBg} !important;
    color: ${btnTx} !important;
    border-color: ${btnBg} !important;
    box-shadow: 0 4px 12px ${hexToRgba(btnBg, 0.25)} !important;
}
${root} .fp-time-slot {
    color: ${txt} !important;
    border-color: ${brd} !important;
    background: ${surf} !important;
}
${root} .fp-time-slot:hover {
    border-color: ${p} !important;
    background: ${hexToRgba(surfAlt, 1)} !important;
}
${root} .fp-time-slot.selected {
    background: ${btnBg} !important;
    color: ${btnTx} !important;
    border-color: ${btnBg} !important;
    box-shadow: 0 4px 12px ${hexToRgba(btnBg, 0.2)} !important;
}
${root} .fp-btn-minus,
${root} .fp-btn-plus {
    border-color: ${brd} !important;
    color: ${txt} !important;
    background: ${surf} !important;
}
${root} .fp-btn-minus:hover,
${root} .fp-btn-plus:hover {
    border-color: ${p} !important;
    color: ${p} !important;
}
${root} .fp-buttons {
    border-color: ${brd} !important;
    background: ${surf} !important;
}
${root} .fp-btn-primary {
    background: ${btnBg} !important;
    color: ${btnTx} !important;
    border-color: ${btnBg} !important;
    box-shadow: 0 2px 8px ${hexToRgba(btnBg, 0.2)} !important;
}
${root} .fp-btn-primary:hover {
    background: ${ph} !important;
    border-color: ${ph} !important;
}
`;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FormColors.init());
    } else {
        FormColors.init();
    }
})();
