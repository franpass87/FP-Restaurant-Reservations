(function () {
    if (typeof window === 'undefined') {
        return;
    }

    const globals = window.fpResvStylePreview || {};
    const form = document.getElementById('fp-resv-style-form');
    const previewRoot = document.querySelector('[data-fp-resv-style-preview]');
    const iframe = previewRoot ? previewRoot.querySelector('[data-style-iframe]') : null;
    if (!form || !previewRoot || !(iframe instanceof HTMLIFrameElement)) {
        return;
    }

    const scopeId = (globals.initial && globals.initial.formId) || 'fp-resv-style-preview-widget';
    const scopeSelector = `#${scopeId}`;
    const palettes = globals.palettes || {};
    const defaults = globals.defaults || {};
    const shadows = globals.shadows || {};
    const i18n = globals.i18n || {};

    let dynamicStyle = null;
    let pendingSettings = null;
    const contrastList = previewRoot.querySelector('[data-contrast-list]');

    const initialSettings = Object.assign({}, defaults, (globals.initial && globals.initial.settings) || {});
    if (!updatePreview(initialSettings)) {
        pendingSettings = initialSettings;
    }

    iframe.addEventListener('load', () => {
        const settings = pendingSettings || collectSettings();
        pendingSettings = null;
        updatePreview(settings);
    });

    const requestUpdate = () => {
        const settings = collectSettings();
        if (!updatePreview(settings)) {
            pendingSettings = settings;
        }
    };

    form.addEventListener('input', requestUpdate);
    form.addEventListener('change', requestUpdate);

    document.querySelectorAll('[data-style-reset]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const message = i18n.resetConfirm || 'Ripristinare lo stile di default?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    function collectSettings() {
        return {
            style_palette: getFieldValue('style_palette'),
            style_primary_color: getFieldValue('style_primary_color'),
            style_font_family: getFieldValue('style_font_family'),
            style_border_radius: getFieldValue('style_border_radius'),
            style_shadow_level: getFieldValue('style_shadow_level'),
            style_spacing_scale: getFieldValue('style_spacing_scale'),
            style_font_size: getFieldValue('style_font_size'),
            style_heading_weight: getFieldValue('style_heading_weight'),
            style_focus_ring_width: getFieldValue('style_focus_ring_width'),
            style_enable_dark_mode: getCheckboxValue('style_enable_dark_mode'),
            style_custom_css: getFieldValue('style_custom_css'),
        };
    }

    function getFrameDocument() {
        if (!(iframe instanceof HTMLIFrameElement)) {
            return null;
        }

        return iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document) || null;
    }

    function ensureDynamicStyle(doc) {
        if (!doc) {
            return null;
        }

        if (!dynamicStyle || dynamicStyle.ownerDocument !== doc) {
            dynamicStyle = doc.getElementById('fp-resv-style-preview-dynamic');
            if (!dynamicStyle) {
                dynamicStyle = doc.createElement('style');
                dynamicStyle.id = 'fp-resv-style-preview-dynamic';
                doc.head.appendChild(dynamicStyle);
            }
        }

        return dynamicStyle;
    }

    function getFieldValue(name) {
        const field = form.querySelector(`[name="fp_resv_style[${name}]"]`);
        if (!field) {
            return defaults[name] || '';
        }

        if (field.tagName === 'TEXTAREA') {
            return field.value || '';
        }

        if (field.type === 'number') {
            return field.value || defaults[name] || '';
        }

        if (field.type === 'color') {
            return field.value || defaults[name] || '';
        }

        return field.value || '';
    }

    function getCheckboxValue(name) {
        const field = form.querySelector(`[name="fp_resv_style[${name}]"]`);
        if (!field) {
            return defaults[name] === '0' ? '0' : '1';
        }

        return field.checked ? '1' : '0';
    }

    function updatePreview(settings) {
        const doc = getFrameDocument();
        const styleNode = ensureDynamicStyle(doc);
        if (!doc || !styleNode) {
            return false;
        }

        const tokens = buildTokens(settings);
        const cssParts = composeCss(scopeSelector, tokens, settings);
        styleNode.textContent = [cssParts.variables, cssParts.dark, cssParts.custom].filter(Boolean).join('\n');
        renderContrast(tokens);

        return true;
    }

    function composeCss(scope, tokens, settings) {
        return {
            variables: buildVariableBlock(scope, tokens, settings),
            dark: settings.style_enable_dark_mode === '1' ? buildDarkBlock(scope, tokens) : '',
            custom: scopeCustomCss(settings.style_custom_css || '', scope),
        };
    }

    function buildTokens(settings) {
        const paletteKey = settings.style_palette && palettes[settings.style_palette] ? settings.style_palette : (defaults.style_palette || 'brand');
        const basePalette = palettes[paletteKey] || palettes.brand || {};
        const primary = normalizeHex(settings.style_primary_color || defaults.style_primary_color || '#bb2649');
        const background = normalizeHex(basePalette.background || '#f9f7f8');
        const surface = normalizeHex(basePalette.surface || '#ffffff');
        const text = normalizeHex(basePalette.text || '#1f1b24');
        const muted = normalizeHex(basePalette.muted || '#625f6b');
        const accent = normalizeHex(basePalette.accent || '#f0b429');
        const onPrimary = pickForeground(primary);
        const accentText = pickForeground(accent);
        const focus = mix(primary, '#ffffff', 0.6);
        const outline = mix(primary, background, 0.45);
        const surfaceAlt = mix(surface, '#000000', 0.06);
        const divider = mix(surface, '#000000', 0.12);
        const slotAvailable = mix(surface, primary, 0.1);
        const slotHover = mix(primary, '#ffffff', 0.85);
        const badgeBg = mix(accent, '#ffffff', 0.2);
        const badgeText = pickForeground(badgeBg);

        const darkBackground = normalizeHex(basePalette.dark_background || '#10111b');
        const darkSurface = normalizeHex(basePalette.dark_surface || '#1a1b25');
        const darkText = normalizeHex(basePalette.dark_text || '#f8fafc');
        const darkMuted = normalizeHex(basePalette.dark_muted || '#9da3b5');
        const darkAccent = normalizeHex(basePalette.dark_accent || '#f6c049');
        const darkBadgeBg = mix(darkAccent, '#000000', 0.25);
        const darkBadgeText = pickForeground(darkBadgeBg);
        const darkOutline = mix(primary, darkBackground, 0.5);
        const darkFocus = mix(primary, '#ffffff', 0.5);
        const darkSlot = mix(darkSurface, primary, 0.12);
        const darkDivider = mix(darkSurface, '#ffffff', 0.12);

        return {
            primary,
            on_primary: onPrimary,
            primary_soft: slotHover,
            background,
            surface,
            surface_alt: surfaceAlt,
            text,
            muted,
            accent,
            accent_text: accentText,
            focus,
            outline,
            divider,
            slot_available_bg: slotAvailable,
            slot_available_text: text,
            slot_available_border: outline,
            slot_selected_bg: primary,
            slot_selected_text: onPrimary,
            badge_bg: badgeBg,
            badge_text: badgeText,
            success: '#1d9a6c',
            success_text: '#ffffff',
            danger: '#d14545',
            danger_text: '#ffffff',
            dark_background: darkBackground,
            dark_surface: darkSurface,
            dark_surface_alt: mix(darkSurface, '#000000', 0.18),
            dark_text: darkText,
            dark_muted: darkMuted,
            dark_accent: darkAccent,
            dark_accent_text: pickForeground(darkAccent),
            dark_focus: darkFocus,
            dark_outline: darkOutline,
            dark_divider: darkDivider,
            dark_slot_available_bg: darkSlot,
            dark_slot_available_text: darkText,
            dark_slot_available_border: darkOutline,
            dark_slot_selected_bg: primary,
            dark_slot_selected_text: onPrimary,
            dark_badge_bg: darkBadgeBg,
            dark_badge_text: darkBadgeText,
        };
    }

    function buildVariableBlock(scope, tokens, settings) {
        const radius = clampInt(settings.style_border_radius || defaults.style_border_radius || 8, 0, 48);
        const shadowKey = settings.style_shadow_level || defaults.style_shadow_level || 'soft';
        const shadow = shadows[shadowKey] || shadows.soft || 'none';
        const font = (settings.style_font_family || defaults.style_font_family || '"Inter", sans-serif').trim();
        const fontSize = clampInt(settings.style_font_size || defaults.style_font_size || 16, 14, 20);
        const headingWeight = resolveHeadingWeight(settings.style_heading_weight || defaults.style_heading_weight || '600');
        const spacingUnit = resolveSpacingUnit(settings.style_spacing_scale || defaults.style_spacing_scale || 'cozy');
        const focusWidth = clampInt(settings.style_focus_ring_width || defaults.style_focus_ring_width || 3, 1, 6);

        const lines = [
            `${scope} {`,
            `    font-family: ${font};`,
            `    font-size: ${fontSize}px;`,
            `    --fp-resv-font-size-base: ${fontSize}px;`,
            `    --fp-resv-heading-weight: ${headingWeight};`,
            `    --fp-resv-radius: ${radius}px;`,
            `    --fp-resv-shadow: ${shadow};`,
            `    --fp-resv-space-unit: ${spacingUnit};`,
            `    --fp-resv-space-xxs: calc(var(--fp-resv-space-unit) * 0.35);`,
            `    --fp-resv-space-xs: calc(var(--fp-resv-space-unit) * 0.6);`,
            `    --fp-resv-space-sm: calc(var(--fp-resv-space-unit) * 0.85);`,
            `    --fp-resv-space-md: calc(var(--fp-resv-space-unit) * 1);`,
            `    --fp-resv-space-lg: calc(var(--fp-resv-space-unit) * 1.6);`,
            `    --fp-resv-space-xl: calc(var(--fp-resv-space-unit) * 2.4);`,
            `    --fp-resv-focus-ring-width: ${focusWidth}px;`,
            `    --fp-resv-primary: ${tokens.primary};`,
            `    --fp-resv-on-primary: ${tokens.on_primary};`,
            `    --fp-resv-primary-soft: ${tokens.primary_soft};`,
            `    --fp-resv-background: ${tokens.background};`,
            `    --fp-resv-surface: ${tokens.surface};`,
            `    --fp-resv-surface-alt: ${tokens.surface_alt};`,
            `    --fp-resv-text: ${tokens.text};`,
            `    --fp-resv-muted: ${tokens.muted};`,
            `    --fp-resv-accent: ${tokens.accent};`,
            `    --fp-resv-accent-text: ${tokens.accent_text};`,
            `    --fp-resv-focus: ${tokens.focus};`,
            `    --fp-resv-outline: ${tokens.outline};`,
            `    --fp-resv-divider: ${tokens.divider};`,
            `    --fp-resv-slot-bg: ${tokens.slot_available_bg};`,
            `    --fp-resv-slot-text: ${tokens.slot_available_text};`,
            `    --fp-resv-slot-border: ${tokens.slot_available_border};`,
            `    --fp-resv-slot-selected-bg: ${tokens.slot_selected_bg};`,
            `    --fp-resv-slot-selected-text: ${tokens.slot_selected_text};`,
            `    --fp-resv-badge-bg: ${tokens.badge_bg};`,
            `    --fp-resv-badge-text: ${tokens.badge_text};`,
            `    --fp-resv-success: ${tokens.success};`,
            `    --fp-resv-success-text: ${tokens.success_text};`,
            `    --fp-resv-danger: ${tokens.danger};`,
            `    --fp-resv-danger-text: ${tokens.danger_text};`,
            `}`,
        ];

        return lines.join('\n');
    }

    function buildDarkBlock(scope, tokens) {
        return (
            `@media (prefers-color-scheme: dark) {\n` +
            `${scope} {\n` +
            `    --fp-resv-background: ${tokens.dark_background};\n` +
            `    --fp-resv-surface: ${tokens.dark_surface};\n` +
            `    --fp-resv-surface-alt: ${tokens.dark_surface_alt};\n` +
            `    --fp-resv-text: ${tokens.dark_text};\n` +
            `    --fp-resv-muted: ${tokens.dark_muted};\n` +
            `    --fp-resv-accent: ${tokens.dark_accent};\n` +
            `    --fp-resv-accent-text: ${tokens.dark_accent_text};\n` +
            `    --fp-resv-focus: ${tokens.dark_focus};\n` +
            `    --fp-resv-outline: ${tokens.dark_outline};\n` +
            `    --fp-resv-divider: ${tokens.dark_divider};\n` +
            `    --fp-resv-slot-bg: ${tokens.dark_slot_available_bg};\n` +
            `    --fp-resv-slot-text: ${tokens.dark_slot_available_text};\n` +
            `    --fp-resv-slot-border: ${tokens.dark_slot_available_border};\n` +
            `    --fp-resv-slot-selected-bg: ${tokens.dark_slot_selected_bg};\n` +
            `    --fp-resv-slot-selected-text: ${tokens.dark_slot_selected_text};\n` +
            `    --fp-resv-badge-bg: ${tokens.dark_badge_bg};\n` +
            `    --fp-resv-badge-text: ${tokens.dark_badge_text};\n` +
            `}\n` +
            `}`
        );
    }

    function renderContrast(tokens) {
        if (!contrastList) {
            return;
        }

        const entries = [
            {
                id: 'primary-button',
                label: i18n.primary || 'CTA',
                foreground: tokens.on_primary,
                background: tokens.primary,
            },
            {
                id: 'surface-text',
                label: i18n.surface || 'Surface text',
                foreground: tokens.text,
                background: tokens.surface,
            },
            {
                id: 'muted-text',
                label: i18n.muted || 'Secondary text',
                foreground: tokens.muted,
                background: tokens.surface,
            },
            {
                id: 'badge-text',
                label: i18n.badge || 'Badge',
                foreground: tokens.badge_text,
                background: tokens.badge_bg,
            },
        ];

        const fragment = document.createDocumentFragment();
        const contrastData = [];

        entries.forEach((entry) => {
            const ratio = contrastRatio(entry.foreground, entry.background);
            const grade = gradeFromRatio(ratio);
            const compliant = grade === 'AA' || grade === 'AAA';
            const item = document.createElement('article');
            item.className = 'fp-resv-style-preview__contrast-item' + (compliant ? '' : ' is-warning');
            item.dataset.contrastItem = entry.id;

            const swatch = document.createElement('span');
            swatch.className = 'fp-resv-style-preview__swatch';
            swatch.style.background = entry.background;
            swatch.style.color = entry.foreground;
            swatch.textContent = 'Aa';

            const copy = document.createElement('div');
            copy.className = 'fp-resv-style-preview__contrast-copy';
            const heading = document.createElement('h4');
            heading.textContent = entry.label;
            const paragraph = document.createElement('p');
            const ratioEl = document.createElement('span');
            ratioEl.className = 'fp-resv-style-preview__ratio';
            ratioEl.textContent = ratio.toFixed(2);
            const gradeEl = document.createElement('span');
            gradeEl.className = 'fp-resv-style-preview__grade';
            gradeEl.textContent = grade;

            paragraph.append(ratioEl, gradeEl);
            copy.append(heading, paragraph);

            item.append(swatch, copy);
            fragment.appendChild(item);

            contrastData.push({
                id: entry.id,
                label: entry.label,
                ratio: parseFloat(ratio.toFixed(2)),
                grade,
                is_compliant: compliant,
                foreground: entry.foreground,
                background: entry.background,
            });
        });

        contrastList.innerHTML = '';
        contrastList.appendChild(fragment);
        previewRoot.dataset.contrast = JSON.stringify(contrastData);
    }

    function normalizeHex(color) {
        if (!color) {
            return '#000000';
        }

        let value = String(color).trim().toLowerCase();
        if (value[0] !== '#') {
            value = `#${value}`;
        }

        if (value.length === 4) {
            value = `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`;
        }

        if (!/^#[0-9a-f]{6}$/.test(value)) {
            return '#000000';
        }

        return value;
    }

    function hexToRgb(color) {
        const normalized = normalizeHex(color).slice(1);
        return {
            r: parseInt(normalized.slice(0, 2), 16),
            g: parseInt(normalized.slice(2, 4), 16),
            b: parseInt(normalized.slice(4, 6), 16),
        };
    }

    function mix(from, to, amount) {
        const ratio = Math.max(0, Math.min(1, amount));
        const rgb1 = hexToRgb(from);
        const rgb2 = hexToRgb(to);
        const r = Math.round(rgb1.r * (1 - ratio) + rgb2.r * ratio);
        const g = Math.round(rgb1.g * (1 - ratio) + rgb2.g * ratio);
        const b = Math.round(rgb1.b * (1 - ratio) + rgb2.b * ratio);
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    function pickForeground(background) {
        const white = contrastRatio('#ffffff', background);
        const dark = contrastRatio('#111827', background);
        return white >= dark ? '#ffffff' : '#111827';
    }

    function contrastRatio(colorA, colorB) {
        const lumA = relativeLuminance(colorA);
        const lumB = relativeLuminance(colorB);
        const lighter = Math.max(lumA, lumB);
        const darker = Math.min(lumA, lumB);
        return (lighter + 0.05) / (darker + 0.05);
    }

    function relativeLuminance(color) {
        const { r, g, b } = hexToRgb(color);
        const channels = [r / 255, g / 255, b / 255].map((value) => {
            return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        });

        return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    }

    function gradeFromRatio(ratio) {
        if (ratio >= 7) {
            return 'AAA';
        }

        if (ratio >= 4.5) {
            return 'AA';
        }

        if (ratio >= 3) {
            return 'AA Large';
        }

        return 'Fail';
    }

    function resolveSpacingUnit(scale) {
        const map = {
            compact: 0.85,
            cozy: 1,
            comfortable: 1.15,
            spacious: 1.3,
        };

        const factor = map[scale] || map.cozy;
        return `${parseFloat(factor.toFixed(3))}rem`;
    }

    function resolveHeadingWeight(value) {
        const allowed = ['500', '600', '700'];
        return allowed.includes(String(value)) ? String(value) : '600';
    }

    function clampInt(value, minValue, maxValue) {
        const parsed = parseInt(value, 10);
        if (Number.isNaN(parsed)) {
            return minValue;
        }

        return Math.min(maxValue, Math.max(minValue, parsed));
    }

    function toHex(component) {
        return component.toString(16).padStart(2, '0');
    }

    function scopeCustomCss(css, scope) {
        if (!css) {
            return '';
        }

        const trimmed = String(css).trim();
        if (!trimmed) {
            return '';
        }

        if (!trimmed.includes('{')) {
            return `${scope} {\n${trimmed}\n}`;
        }

        return trimmed.replace(/(^|})\s*([^{}]+){/g, (_, prefix, selector) => {
            return `${prefix} ${scope} ${selector.trim()}{`;
        });
    }
})();
