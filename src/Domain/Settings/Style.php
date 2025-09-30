<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use function __;
use function array_merge;
use function in_array;
use function max;
use function min;
use function number_format;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;
use function update_option;
use function wp_parse_args;

final class Style
{
    private const SUCCESS_COLOR = '#1d9a6c';
    private const DANGER_COLOR  = '#d14545';

    public function __construct(private readonly Options $options)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return [
            'style_palette'          => 'brand',
            'style_primary_color'    => '#bb2649',
            'style_font_family'      => '"Inter", sans-serif',
            'style_font_size'        => '16',
            'style_heading_weight'   => '600',
            'style_border_radius'    => '8',
            'style_shadow_level'     => 'soft',
            'style_spacing_scale'    => 'cozy',
            'style_focus_ring_width' => '3',
            'style_enable_dark_mode' => '1',
            'style_custom_css'       => '',
        ];
    }

    public function resetToDefaults(): void
    {
        update_option('fp_resv_style', $this->getDefaults());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getPalettes(): array
    {
        return [
            'brand' => [
                'background'      => '#f9f7f8',
                'surface'         => '#ffffff',
                'text'            => '#1f1b24',
                'muted'           => '#625f6b',
                'accent'          => '#f0b429',
                'dark_background' => '#10111b',
                'dark_surface'    => '#1a1b25',
                'dark_text'       => '#f8fafc',
                'dark_muted'      => '#9da3b5',
                'dark_accent'     => '#f6c049',
            ],
            'neutral' => [
                'background'      => '#f6f6f7',
                'surface'         => '#ffffff',
                'text'            => '#202225',
                'muted'           => '#5f6368',
                'accent'          => '#2563eb',
                'dark_background' => '#0f172a',
                'dark_surface'    => '#1e293b',
                'dark_text'       => '#e2e8f0',
                'dark_muted'      => '#94a3b8',
                'dark_accent'     => '#3b82f6',
            ],
            'dark' => [
                'background'      => '#10131a',
                'surface'         => '#161b23',
                'text'            => '#f3f4f6',
                'muted'           => '#9aa2b2',
                'accent'          => '#4f9cdb',
                'dark_background' => '#07080d',
                'dark_surface'    => '#121720',
                'dark_text'       => '#f9fafb',
                'dark_muted'      => '#bfc6d4',
                'dark_accent'     => '#61a9e6',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getShadowPresets(): array
    {
        return [
            'none'   => 'none',
            'soft'   => '0 18px 45px rgba(15, 23, 42, 0.08)',
            'strong' => '0 24px 60px rgba(15, 23, 42, 0.16)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $raw = $this->options->getGroup('fp_resv_style', []);

        return wp_parse_args($raw, $this->getDefaults());
    }

    /**
     * Build frontend-ready payload for the given form scope.
     *
     * @return array<string, mixed>
     */
    public function buildFrontend(string $formId): array
    {
        $settings = $this->getSettings();
        $tokens   = $this->buildTokens($settings);
        $cssParts = $this->buildCss($formId, $tokens, $settings);
        $hash     = substr(md5($cssParts['css']), 0, 10);

        return [
            'css'        => $cssParts['css'],
            'hash'       => $hash,
            'tokens'     => $tokens,
            'settings'   => $settings,
            'contrast'   => $this->buildContrastReport($tokens),
            'css_parts'  => $cssParts,
            'form_scope' => '#' . $formId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreviewData(string $formId = 'fp-resv-style-preview-widget'): array
    {
        $payload = $this->buildFrontend($formId);

        return array_merge($payload, [
            'palettes' => $this->getPalettes(),
            'shadows'  => $this->getShadowPresets(),
            'defaults' => $this->getDefaults(),
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, string>
     */
    private function buildTokens(array $settings): array
    {
        $palettes = $this->getPalettes();
        $palette  = $settings['style_palette'] ?? 'brand';
        if (!isset($palettes[$palette])) {
            $palette = 'brand';
        }

        $base         = $palettes[$palette];
        $primary      = $this->normalizeHex((string) ($settings['style_primary_color'] ?? '#bb2649'));
        $background   = $this->normalizeHex($base['background']);
        $surface      = $this->normalizeHex($base['surface']);
        $text         = $this->normalizeHex($base['text']);
        $muted        = $this->normalizeHex($base['muted']);
        $accent       = $this->normalizeHex($base['accent']);
        $onPrimary    = $this->pickForeground($primary);
        $accentText   = $this->pickForeground($accent);
        $focus        = $this->mix($primary, '#ffffff', 0.6);
        $outline      = $this->mix($primary, $background, 0.45);
        $surfaceAlt   = $this->mix($surface, '#000000', 0.06);
        $divider      = $this->mix($surface, '#000000', 0.12);
        $slotBg       = $this->mix($surface, $primary, 0.1);
        $slotHover    = $this->mix($primary, '#ffffff', 0.85);
        $badgeBg      = $this->mix($accent, '#ffffff', 0.2);
        $badgeText    = $this->pickForeground($badgeBg);

        $darkBackground = $this->normalizeHex($base['dark_background']);
        $darkSurface    = $this->normalizeHex($base['dark_surface']);
        $darkText       = $this->normalizeHex($base['dark_text']);
        $darkMuted      = $this->normalizeHex($base['dark_muted']);
        $darkAccent     = $this->normalizeHex($base['dark_accent']);
        $darkBadgeBg    = $this->mix($darkAccent, '#000000', 0.25);
        $darkBadgeText  = $this->pickForeground($darkBadgeBg);
        $darkOutline    = $this->mix($primary, $darkBackground, 0.5);
        $darkFocus      = $this->mix($primary, '#ffffff', 0.5);
        $darkSlotBg     = $this->mix($darkSurface, $primary, 0.12);
        $darkSlotHover  = $this->mix($primary, '#ffffff', 0.82);
        $darkDivider    = $this->mix($darkSurface, '#ffffff', 0.12);

        return [
            'primary'                => $primary,
            'on_primary'             => $onPrimary,
            'primary_soft'           => $slotHover,
            'background'             => $background,
            'surface'                => $surface,
            'surface_alt'            => $surfaceAlt,
            'text'                   => $text,
            'muted'                  => $muted,
            'accent'                 => $accent,
            'accent_text'            => $accentText,
            'focus'                  => $focus,
            'outline'                => $outline,
            'divider'                => $divider,
            'slot_available_bg'      => $slotBg,
            'slot_available_text'    => $text,
            'slot_available_border'  => $outline,
            'slot_selected_bg'       => $primary,
            'slot_selected_text'     => $onPrimary,
            'badge_bg'               => $badgeBg,
            'badge_text'             => $badgeText,
            'success'                => self::SUCCESS_COLOR,
            'success_text'           => '#ffffff',
            'danger'                 => self::DANGER_COLOR,
            'danger_text'            => '#ffffff',
            'dark_background'        => $darkBackground,
            'dark_surface'           => $darkSurface,
            'dark_surface_alt'       => $this->mix($darkSurface, '#000000', 0.18),
            'dark_text'              => $darkText,
            'dark_muted'             => $darkMuted,
            'dark_accent'            => $darkAccent,
            'dark_accent_text'       => $this->pickForeground($darkAccent),
            'dark_focus'             => $darkFocus,
            'dark_outline'           => $darkOutline,
            'dark_divider'           => $darkDivider,
            'dark_slot_available_bg' => $darkSlotBg,
            'dark_slot_available_text' => $darkText,
            'dark_slot_available_border' => $darkOutline,
            'dark_slot_selected_bg'  => $primary,
            'dark_slot_selected_text'=> $onPrimary,
            'dark_badge_bg'          => $darkBadgeBg,
            'dark_badge_text'        => $darkBadgeText,
        ];
    }

    /**
     * @param array<string, string> $tokens
     * @param array<string, mixed>  $settings
     *
     * @return array<string, string>
     */
    private function buildCss(string $formId, array $tokens, array $settings): array
    {
        $scope     = '#' . $formId;
        $variables = $this->buildVariableBlock($scope, $tokens, $settings);
        $base      = $this->buildBaseCss($scope);
        $dark      = '';
        if (($settings['style_enable_dark_mode'] ?? '1') === '1') {
            $dark = $this->buildDarkVariables($scope, $tokens);
        }

        $custom = $this->scopeCustomCss((string) ($settings['style_custom_css'] ?? ''), $scope);

        $css = $variables . "\n" . $base;
        if ($dark !== '') {
            $css .= "\n" . $dark;
        }
        if ($custom !== '') {
            $css .= "\n" . $custom;
        }

        return [
            'variables' => $variables,
            'base'      => $base,
            'dark'      => $dark,
            'custom'    => $custom,
            'css'       => $css,
        ];
    }

    /**
     * @param array<string, string> $tokens
     */
    private function buildVariableBlock(string $scope, array $tokens, array $settings): string
    {
        $radius = (int) ($settings['style_border_radius'] ?? 8);
        $radius = max(0, min(48, $radius));
        $shadows = $this->getShadowPresets();
        $shadow  = $shadows[$settings['style_shadow_level'] ?? 'soft'] ?? $shadows['soft'];
        $font    = trim((string) ($settings['style_font_family'] ?? '"Inter", sans-serif'));
        $fontSize = (int) ($settings['style_font_size'] ?? 16);
        $fontSize = max(14, min(20, $fontSize));
        $headingWeight = $this->resolveHeadingWeight((string) ($settings['style_heading_weight'] ?? '600'));
        $spacingUnit   = $this->formatSpacingUnit((string) ($settings['style_spacing_scale'] ?? 'cozy'));
        $focusWidth    = (int) ($settings['style_focus_ring_width'] ?? 3);
        $focusWidth    = max(1, min(6, $focusWidth));

        $lines   = [];
        $lines[] = sprintf('%s {', $scope);
        $lines[] = '    font-family: ' . $font . ';';
        $lines[] = '    font-size: ' . $fontSize . 'px;';
        $lines[] = '    --fp-resv-font-size-base: ' . $fontSize . 'px;';
        $lines[] = '    --fp-resv-heading-weight: ' . $headingWeight . ';';
        $lines[] = '    --fp-resv-radius: ' . $radius . 'px;';
        $lines[] = '    --fp-resv-shadow: ' . $shadow . ';';
        $lines[] = '    --fp-resv-space-unit: ' . $spacingUnit . ';';
        $lines[] = '    --fp-resv-space-xxs: calc(var(--fp-resv-space-unit) * 0.35);';
        $lines[] = '    --fp-resv-space-xs: calc(var(--fp-resv-space-unit) * 0.6);';
        $lines[] = '    --fp-resv-space-sm: calc(var(--fp-resv-space-unit) * 0.85);';
        $lines[] = '    --fp-resv-space-md: calc(var(--fp-resv-space-unit) * 1);';
        $lines[] = '    --fp-resv-space-lg: calc(var(--fp-resv-space-unit) * 1.6);';
        $lines[] = '    --fp-resv-space-xl: calc(var(--fp-resv-space-unit) * 2.4);';
        $lines[] = '    --fp-resv-focus-ring-width: ' . $focusWidth . 'px;';
        $lines[] = '    --fp-resv-primary: ' . $tokens['primary'] . ';';
        $lines[] = '    --fp-resv-on-primary: ' . $tokens['on_primary'] . ';';
        $lines[] = '    --fp-resv-primary-soft: ' . $tokens['primary_soft'] . ';';
        $lines[] = '    --fp-resv-background: ' . $tokens['background'] . ';';
        $lines[] = '    --fp-resv-surface: ' . $tokens['surface'] . ';';
        $lines[] = '    --fp-resv-surface-alt: ' . $tokens['surface_alt'] . ';';
        $lines[] = '    --fp-resv-text: ' . $tokens['text'] . ';';
        $lines[] = '    --fp-resv-muted: ' . $tokens['muted'] . ';';
        $lines[] = '    --fp-resv-accent: ' . $tokens['accent'] . ';';
        $lines[] = '    --fp-resv-accent-text: ' . $tokens['accent_text'] . ';';
        $lines[] = '    --fp-resv-focus: ' . $tokens['focus'] . ';';
        $lines[] = '    --fp-resv-outline: ' . $tokens['outline'] . ';';
        $lines[] = '    --fp-resv-divider: ' . $tokens['divider'] . ';';
        $lines[] = '    --fp-resv-slot-bg: ' . $tokens['slot_available_bg'] . ';';
        $lines[] = '    --fp-resv-slot-text: ' . $tokens['slot_available_text'] . ';';
        $lines[] = '    --fp-resv-slot-border: ' . $tokens['slot_available_border'] . ';';
        $lines[] = '    --fp-resv-slot-selected-bg: ' . $tokens['slot_selected_bg'] . ';';
        $lines[] = '    --fp-resv-slot-selected-text: ' . $tokens['slot_selected_text'] . ';';
        $lines[] = '    --fp-resv-badge-bg: ' . $tokens['badge_bg'] . ';';
        $lines[] = '    --fp-resv-badge-text: ' . $tokens['badge_text'] . ';';
        $lines[] = '    --fp-resv-success: ' . $tokens['success'] . ';';
        $lines[] = '    --fp-resv-success-text: ' . $tokens['success_text'] . ';';
        $lines[] = '    --fp-resv-danger: ' . $tokens['danger'] . ';';
        $lines[] = '    --fp-resv-danger-text: ' . $tokens['danger_text'] . ';';
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, string> $tokens
     */
    private function buildDarkVariables(string $scope, array $tokens): string
    {
        return sprintf(
            "@media (prefers-color-scheme: dark) {\n%s {\n    --fp-resv-background: %s;\n    --fp-resv-surface: %s;\n    --fp-resv-surface-alt: %s;\n    --fp-resv-text: %s;\n    --fp-resv-muted: %s;\n    --fp-resv-accent: %s;\n    --fp-resv-accent-text: %s;\n    --fp-resv-focus: %s;\n    --fp-resv-outline: %s;\n    --fp-resv-divider: %s;\n    --fp-resv-slot-bg: %s;\n    --fp-resv-slot-text: %s;\n    --fp-resv-slot-border: %s;\n    --fp-resv-slot-selected-bg: %s;\n    --fp-resv-slot-selected-text: %s;\n    --fp-resv-badge-bg: %s;\n    --fp-resv-badge-text: %s;\n}\n}\n",
            $scope,
            $tokens['dark_background'],
            $tokens['dark_surface'],
            $tokens['dark_surface_alt'],
            $tokens['dark_text'],
            $tokens['dark_muted'],
            $tokens['dark_accent'],
            $tokens['dark_accent_text'],
            $tokens['dark_focus'],
            $tokens['dark_outline'],
            $tokens['dark_divider'],
            $tokens['dark_slot_available_bg'],
            $tokens['dark_slot_available_text'],
            $tokens['dark_slot_available_border'],
            $tokens['dark_slot_selected_bg'],
            $tokens['dark_slot_selected_text'],
            $tokens['dark_badge_bg'],
            $tokens['dark_badge_text']
        );
    }

    private function buildBaseCss(string $scope): string
    {
        $layout = <<<'CSS'
%s.fp-resv-widget {
    background: var(--fp-resv-surface);
    color: var(--fp-resv-text);
    border-radius: var(--fp-resv-radius);
    box-shadow: var(--fp-resv-shadow);
    padding: clamp(1.5rem, 1.25rem + 1vw, 2.5rem);
    display: grid;
    gap: 1.75rem;
    border: 1px solid rgba(17, 25, 40, 0.04);
}
%s .fp-resv-widget__topbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
}
%s .fp-resv-widget__headline {
    margin: 0;
    font-size: clamp(1.5rem, 1.3rem + 0.5vw, 1.9rem);
    font-weight: 600;
}
%s .fp-resv-widget__subheadline {
    margin: 0.25rem 0 0;
    color: var(--fp-resv-muted);
    font-size: 0.95rem;
}
%s .fp-resv-widget__pdf {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.1rem;
    border-radius: calc(var(--fp-resv-radius) / 1.2);
    font-weight: 600;
    color: var(--fp-resv-primary);
    background: rgba(0, 0, 0, 0.03);
    transition: background 120ms ease, color 120ms ease;
    text-decoration: none;
}
%s .fp-resv-widget__pdf:hover {
    background: var(--fp-resv-primary-soft);
    color: var(--fp-resv-on-primary);
}
%s .fp-resv-widget__form {
    margin: 0;
    display: grid;
    gap: 1.5rem;
}
%s .fp-resv-widget__steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 1.25rem;
}
%s .fp-resv-step {
    padding: 1.25rem;
    border-radius: calc(var(--fp-resv-radius) * 0.85);
    background: var(--fp-resv-surface-alt);
    border: 1px solid var(--fp-resv-divider);
    display: grid;
    gap: 1rem;
}
%s .fp-resv-step__label {
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    color: var(--fp-resv-muted);
}
%s .fp-resv-step__title {
    margin: 0.35rem 0 0;
    font-size: 1.25rem;
    font-weight: 600;
}
%s .fp-resv-step__description {
    margin: 0.25rem 0 0;
    color: var(--fp-resv-muted);
    font-size: 0.92rem;
}
%s .fp-resv-field {
    display: grid;
    gap: 0.4rem;
}
%s .fp-resv-field span {
    font-size: 0.85rem;
    color: var(--fp-resv-muted);
}
%s input[type="text"],
%s input[type="email"],
%s input[type="tel"],
%s input[type="number"],
%s input[type="date"],
%s input[type="time"],
%s textarea,
%s select {
    border-radius: calc(var(--fp-resv-radius) * 0.65);
    border: 1px solid var(--fp-resv-divider);
    padding: 0.65rem 0.75rem;
    font-size: 0.95rem;
    background: #ffffff;
    color: var(--fp-resv-text);
    transition: border-color 120ms ease, box-shadow 120ms ease;
}
%s textarea {
    resize: vertical;
    min-height: 4.5rem;
}
%s input:focus-visible,
%s textarea:focus-visible,
%s select:focus-visible {
    outline: 2px solid var(--fp-resv-focus);
    outline-offset: 2px;
}
%s .fp-resv-fields--grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
%s .fp-resv-button {
    border: none;
    border-radius: calc(var(--fp-resv-radius) * 0.8);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    padding: 0.75rem 1.4rem;
    transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
}
%s .fp-resv-button--primary {
    background: var(--fp-resv-primary);
    color: var(--fp-resv-on-primary);
    box-shadow: 0 12px 24px rgba(187, 38, 73, 0.12);
}
%s .fp-resv-button--ghost {
    background: transparent;
    color: var(--fp-resv-primary);
}
%s .fp-resv-button--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(187, 38, 73, 0.16);
}
%s .fp-resv-button--ghost:hover {
    color: var(--fp-resv-on-primary);
    background: var(--fp-resv-primary);
}
%s .fp-resv-step__footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
%s .fp-resv-slots {
    display: grid;
    gap: 0.75rem;
}
%s .fp-resv-slots__status,
%s .fp-resv-slots__empty {
    margin: 0;
    color: var(--fp-resv-muted);
    font-size: 0.9rem;
}
%s .fp-resv-slots__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
}
%s .fp-resv-slots__list button {
    border-radius: calc(var(--fp-resv-radius) * 0.7);
    border: 1px solid var(--fp-resv-slot-border);
    padding: 0.65rem 0.8rem;
    background: var(--fp-resv-slot-bg);
    color: var(--fp-resv-slot-text);
    font-weight: 600;
    cursor: pointer;
    transition: background 120ms ease, border-color 120ms ease, color 120ms ease;
}
%s .fp-resv-slots__list button:hover,
%s .fp-resv-slots__list button[aria-pressed="true"] {
    background: var(--fp-resv-slot-selected-bg);
    color: var(--fp-resv-slot-selected-text);
    border-color: transparent;
}
%s .fp-resv-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: var(--fp-resv-badge-bg);
    color: var(--fp-resv-badge-text);
}
%s .fp-resv-summary {
    display: grid;
    gap: 1rem;
}
%s .fp-resv-summary__list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem 1.5rem;
}
%s .fp-resv-summary__list dt {
    font-size: 0.8rem;
    text-transform: uppercase;
    color: var(--fp-resv-muted);
}
%s .fp-resv-summary__list dd {
    margin: 0.15rem 0 0;
    font-weight: 600;
}
%s .fp-resv-summary__disclaimer {
    margin: 0;
    font-size: 0.85rem;
    color: var(--fp-resv-muted);
}
%s .fp-resv-widget__nojs {
    padding: 1rem;
    background: rgba(255, 196, 0, 0.12);
    border-radius: calc(var(--fp-resv-radius) * 0.8);
    color: #6b4d00;
}
@media (max-width: 768px) {
    %s .fp-resv-widget__topbar {
        flex-direction: column;
        align-items: stretch;
    }
    %s .fp-resv-step__footer {
        flex-direction: column-reverse;
        align-items: stretch;
    }
    %s .fp-resv-widget__pdf {
        width: 100%%;
        justify-content: center;
    }
}
CSS;

        return sprintf(
            $layout,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope,
            $scope
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildContrastReport(array $tokens): array
    {
        $entries = [
            [
                'id'         => 'primary-button',
                'label'      => __('Bottone principale', 'fp-restaurant-reservations'),
                'foreground' => $tokens['on_primary'],
                'background' => $tokens['primary'],
            ],
            [
                'id'         => 'surface-text',
                'label'      => __('Testo su superficie', 'fp-restaurant-reservations'),
                'foreground' => $tokens['text'],
                'background' => $tokens['surface'],
            ],
            [
                'id'         => 'muted-text',
                'label'      => __('Testo secondario', 'fp-restaurant-reservations'),
                'foreground' => $tokens['muted'],
                'background' => $tokens['surface'],
            ],
            [
                'id'         => 'badge-text',
                'label'      => __('Badge slot', 'fp-restaurant-reservations'),
                'foreground' => $tokens['badge_text'],
                'background' => $tokens['badge_bg'],
            ],
        ];

        $report = [];
        foreach ($entries as $entry) {
            $ratio = $this->contrastRatio($entry['foreground'], $entry['background']);
            $grade = $this->gradeFromRatio($ratio);

            $report[] = array_merge($entry, [
                'ratio'       => (float) number_format($ratio, 2, '.', ''),
                'grade'       => $grade,
                'is_compliant'=> in_array($grade, ['AA', 'AAA'], true),
            ]);
        }

        return $report;
    }

    private function gradeFromRatio(float $ratio): string
    {
        if ($ratio >= 7.0) {
            return 'AAA';
        }

        if ($ratio >= 4.5) {
            return 'AA';
        }

        if ($ratio >= 3.0) {
            return 'AA Large';
        }

        return 'Fail';
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $lum1 = $this->relativeLuminance($foreground);
        $lum2 = $this->relativeLuminance($background);
        $lighter = max($lum1, $lum2);
        $darker  = min($lum1, $lum2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $color): float
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $channels    = [$r / 255, $g / 255, $b / 255];
        foreach ($channels as $index => $value) {
            $channels[$index] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    private function pickForeground(string $background): string
    {
        $whiteContrast = $this->contrastRatio('#ffffff', $background);
        $darkContrast  = $this->contrastRatio('#111827', $background);

        return $whiteContrast >= $darkContrast ? '#ffffff' : '#111827';
    }

    private function normalizeHex(string $color): string
    {
        $color = strtolower(trim($color));
        if ($color === '') {
            return '#000000';
        }

        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        if (preg_match('/^#([0-9a-f]{3})$/', $color, $matches) === 1) {
            $chars = str_split($matches[1]);
            $color = sprintf('#%s%s%s%s%s%s', $chars[0], $chars[0], $chars[1], $chars[1], $chars[2], $chars[2]);
        }

        if (preg_match('/^#([0-9a-f]{6})$/', $color) !== 1) {
            return '#000000';
        }

        return $color;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $color): array
    {
        $color = $this->normalizeHex($color);
        $hex   = substr($color, 1);

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function mix(string $from, string $to, float $amount): string
    {
        $amount = max(0.0, min(1.0, $amount));
        [$r1, $g1, $b1] = $this->hexToRgb($from);
        [$r2, $g2, $b2] = $this->hexToRgb($to);

        $r = (int) round(($r1 * (1 - $amount)) + ($r2 * $amount));
        $g = (int) round(($g1 * (1 - $amount)) + ($g2 * $amount));
        $b = (int) round(($b1 * (1 - $amount)) + ($b2 * $amount));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function scopeCustomCss(string $css, string $scope): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        if (!str_contains($css, '{')) {
            return sprintf("%s {\n%s\n}", $scope, $css);
        }

        return preg_replace('/(^|})\s*([^{}]+){/m', '$1 ' . $scope . ' $2{', $css) ?? '';
    }

    private function formatSpacingUnit(string $scale): string
    {
        $map = [
            'compact'      => 0.85,
            'cozy'         => 1.0,
            'comfortable'  => 1.15,
            'spacious'     => 1.3,
        ];
        $factor = $map[$scale] ?? $map['cozy'];

        $value = number_format($factor, 3, '.', '');
        $value = rtrim(rtrim($value, '0'), '.');

        return $value . 'rem';
    }

    private function resolveHeadingWeight(string $weight): string
    {
        $allowed = ['500', '600', '700'];

        return in_array($weight, $allowed, true) ? $weight : '600';
    }
}
