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
use function str_replace;
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
        $lines[] = '    --fp-resv-radius-lg: calc(var(--fp-resv-radius) * 1.05);';
        $lines[] = '    --fp-resv-radius-md: var(--fp-resv-radius);';
        $lines[] = '    --fp-resv-radius-sm: calc(var(--fp-resv-radius) * 0.55);';
        $lines[] = '    --fp-resv-shadow-lg: var(--fp-resv-shadow);';
        $lines[] = '    --fp-resv-shadow-sm: 0 12px 30px rgba(15, 23, 42, 0.08);';
        $lines[] = '    --fp-resv-space-unit: ' . $spacingUnit . ';';
        $lines[] = '    --fp-resv-space-xxs: calc(var(--fp-resv-space-unit) * 0.35);';
        $lines[] = '    --fp-resv-space-xs: calc(var(--fp-resv-space-unit) * 0.6);';
        $lines[] = '    --fp-resv-space-sm: calc(var(--fp-resv-space-unit) * 0.85);';
        $lines[] = '    --fp-resv-space-md: calc(var(--fp-resv-space-unit) * 1);';
        $lines[] = '    --fp-resv-space-lg: calc(var(--fp-resv-space-unit) * 1.6);';
        $lines[] = '    --fp-resv-space-xl: calc(var(--fp-resv-space-unit) * 2.4);';
        $lines[] = '    --fp-resv-spacing-xs: var(--fp-resv-space-sm);';
        $lines[] = '    --fp-resv-spacing-sm: var(--fp-resv-space-md);';
        $lines[] = '    --fp-resv-spacing-md: var(--fp-resv-space-lg);';
        $lines[] = '    --fp-resv-spacing-lg: var(--fp-resv-space-xl);';
        $lines[] = '    --fp-resv-spacing-xl: calc(var(--fp-resv-space-xl) * 1.25);';
        [$primaryRed, $primaryGreen, $primaryBlue] = $this->hexToRgb($tokens['primary']);

        $lines[] = '    --fp-resv-focus-ring-width: ' . $focusWidth . 'px;';
        $lines[] = '    --fp-resv-focus-ring: 0 0 0 var(--fp-resv-focus-ring-width, 3px) var(--fp-resv-focus);';
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
        $lines[] = '    --fp-resv-color-surface: ' . $tokens['surface'] . ';';
        $lines[] = '    --fp-resv-color-surface-alt: ' . $tokens['surface_alt'] . ';';
        $lines[] = '    --fp-resv-color-text: ' . $tokens['text'] . ';';
        $lines[] = '    --fp-resv-color-muted: ' . $tokens['muted'] . ';';
        $lines[] = '    --fp-resv-color-border: ' . $tokens['outline'] . ';';
        $lines[] = '    --fp-resv-color-primary: ' . $tokens['primary'] . ';';
        $lines[] = '    --fp-resv-color-primary-strong: ' . $tokens['primary'] . ';';
        $lines[] = '    --fp-resv-color-primary-soft: ' . $tokens['primary_soft'] . ';';
        $lines[] = '    --fp-resv-color-primary-contrast: ' . $tokens['on_primary'] . ';';
        $lines[] = '    --fp-resv-color-success: ' . $tokens['success'] . ';';
        $lines[] = '    --fp-resv-color-error: ' . $tokens['danger'] . ';';
        $lines[] = '    --fp-resv-color-warning: ' . $tokens['accent'] . ';';
        $lines[] = sprintf('    --fp-resv-color-primary-rgb: %d, %d, %d;', $primaryRed, $primaryGreen, $primaryBlue);
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
    border: 1px solid rgba(17, 25, 40, 0.04);
    display: flex;
    flex-direction: column;
    gap: clamp(1.25rem, 3vw, 2rem);
    padding: var(--fp-resv-spacing-lg, clamp(1.5rem, 1.35rem + 1vw, 2.5rem));
    width: 100%;
    max-width: min(100%, var(--fp-resv-max-width, 100%));
    margin-inline: auto;
    margin-block: var(--fp-resv-margin-block, 0);
    box-sizing: border-box;
}
%s .fp-resv-widget__topbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--fp-resv-divider);
}
%s .fp-resv-progress {
    box-sizing: border-box;
    width: 100%;
}
%s .fp-progress {
    --fp-progress-height: 6px;
    --fp-progress-fill: 0%;
    --fp-progress-gap: clamp(0.55rem, 1.4vw, 0.95rem);
    list-style: none;
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: var(--fp-progress-gap);
    padding: clamp(0.1rem, 0.45vw, 0.25rem) clamp(0.35rem, 1.6vw, 0.65rem);
    margin: 0;
    position: relative;
    isolation: isolate;
    counter-reset: fp-progress;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
    -webkit-overflow-scrolling: touch;
}
%s .fp-progress::-webkit-scrollbar {
    height: 0.35rem;
}
%s .fp-progress::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
    border-radius: 999px;
}
%s .fp-progress::-webkit-scrollbar-track {
    background: transparent;
}
%s .fp-progress::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 50%;
    height: var(--fp-progress-height);
    transform: translateY(-50%);
    background: linear-gradient(
        90deg,
        transparent,
        rgba(148, 163, 184, 0.38) 16%,
        rgba(148, 163, 184, 0.38) 84%,
        transparent
    );
    border-radius: 999px;
    pointer-events: none;
    z-index: 0;
}
%s .fp-progress::after {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    top: 50%;
    height: var(--fp-progress-height);
    transform: translateY(-50%);
    width: var(--fp-progress-fill);
    background: linear-gradient(
        90deg,
        transparent,
        var(--fp-resv-color-primary) 12%,
        var(--fp-resv-color-primary-strong) 88%,
        transparent
    );
    border-radius: 999px;
    transition: width 220ms ease;
    pointer-events: none;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.04);
    z-index: 0;
}
%s .fp-progress__item {
    --fp-progress-item-padding-inline: clamp(0.45rem, 1.35vw, 0.85rem);
    --fp-progress-item-padding-block: clamp(0.4rem, 1.2vw, 0.7rem);
    --fp-progress-item-min-width: clamp(2.45rem, 2.95vw, 2.9rem);
    --fp-progress-label-gap: 0;
    --fp-progress-label-max-width: 0;
    --fp-progress-label-opacity: 0;
    --fp-progress-label-translate: -0.25rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--fp-progress-label-gap);
    padding-inline: var(--fp-progress-item-padding-inline);
    padding-block: var(--fp-progress-item-padding-block);
    background: rgba(255, 255, 255, 0.75);
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    box-shadow: 0 12px 35px -24px rgba(15, 23, 42, 0.6);
    color: var(--fp-resv-color-muted);
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.3;
    text-transform: none;
    min-width: var(--fp-progress-item-min-width);
    flex: 0 0 auto;
    scroll-snap-align: center;
    transition: background 220ms ease, color 220ms ease, border-color 220ms ease, transform 200ms ease, box-shadow 220ms ease, padding-inline 220ms ease, min-width 220ms ease;
    isolation: isolate;
    z-index: 1;
}
%s .fp-progress__item::before {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: inherit;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(226, 232, 240, 0.25));
    opacity: 0.85;
    z-index: -1;
    pointer-events: none;
    transition: opacity 220ms ease;
}
%s .fp-progress__item:not([aria-disabled="true"]) {
    cursor: pointer;
}
%s .fp-progress__item[aria-disabled="true"] {
    cursor: default;
    pointer-events: none;
}
%s .fp-progress__item:focus-visible {
    outline: 3px solid var(--fp-resv-color-primary);
    outline-offset: 2px;
}
%s .fp-progress__item::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    box-shadow: 0 18px 42px -28px rgba(15, 23, 42, 0.55);
    opacity: 0;
    transition: opacity 200ms ease;
    z-index: -2;
}
%s .fp-progress__index {
    position: relative;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: clamp(2.1rem, 3vw, 2.4rem);
    height: clamp(2.1rem, 3vw, 2.4rem);
    border-radius: 999px;
    border: 2px solid rgba(148, 163, 184, 0.35);
    background: rgba(241, 245, 249, 0.85);
    color: var(--fp-resv-color-primary);
    font-size: 0.78em;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-feature-settings: 'tnum' 1;
    transition: background 220ms ease, border-color 220ms ease, color 220ms ease, box-shadow 220ms ease;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}
%s .fp-progress__label {
    display: block;
    max-width: var(--fp-progress-label-max-width);
    opacity: var(--fp-progress-label-opacity);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: inherit;
    letter-spacing: 0.01em;
    transform: translateX(var(--fp-progress-label-translate));
    transition: max-width 240ms ease, opacity 180ms ease, transform 200ms ease;
}
%s .fp-progress__label[aria-hidden="true"] {
    pointer-events: none;
}
%s .fp-progress__item[data-state="active"],
%s .fp-progress__item[data-progress-state="active"],
%s .fp-progress__item:focus-visible {
    --fp-progress-item-padding-inline: clamp(0.7rem, 1.9vw, 1.2rem);
    --fp-progress-item-min-width: clamp(6.75rem, 18vw, 12rem);
    --fp-progress-label-gap: clamp(0.45rem, 1.3vw, 0.75rem);
    --fp-progress-label-max-width: clamp(7.1rem, 22vw, 12rem);
    --fp-progress-label-opacity: 1;
    --fp-progress-label-translate: 0;
    justify-content: flex-start;
    color: var(--fp-resv-color-text);
}
%s .fp-progress__item[data-state="active"],
%s .fp-progress__item[data-progress-state="active"],
%s .fp-resv-step[aria-hidden="false"] ~ .fp-progress__item,
%s .fp-progress__item:first-child {
    color: var(--fp-resv-color-text);
}
%s .fp-progress__item[data-state="active"],
%s .fp-progress__item[data-completed="true"],
%s .fp-progress__item[data-progress-state="done"],
%s .fp-progress__item[data-progress-state="active"] {
    background: linear-gradient(130deg, rgba(var(--fp-resv-color-primary-rgb, 59, 130, 246), 0.12), rgba(var(--fp-resv-color-primary-rgb, 59, 130, 246), 0.04));
    border-color: rgba(var(--fp-resv-color-primary-rgb, 59, 130, 246), 0.35);
    color: var(--fp-resv-color-text);
    transform: translateY(-1px);
}
%s .fp-progress__item[data-state="active"]::before,
%s .fp-progress__item[data-completed="true"]::before,
%s .fp-progress__item[data-progress-state="done"]::before,
%s .fp-progress__item[data-progress-state="active"]::before {
    opacity: 1;
}
%s .fp-progress__item[data-state="active"]::after,
%s .fp-progress__item[data-completed="true"]::after,
%s .fp-progress__item[data-progress-state="done"]::after,
%s .fp-progress__item[data-progress-state="active"]::after {
    opacity: 1;
}
%s .fp-progress__item[data-state="active"] .fp-progress__index,
%s .fp-progress__item[data-completed="true"] .fp-progress__index,
%s .fp-progress__item[data-progress-state="done"] .fp-progress__index,
%s .fp-progress__item[data-progress-state="active"] .fp-progress__index {
    background: linear-gradient(135deg, var(--fp-resv-color-primary), var(--fp-resv-color-primary-strong));
    border-color: transparent;
    color: var(--fp-resv-color-primary-contrast);
    box-shadow: 0 8px 18px -10px rgba(var(--fp-resv-color-primary-rgb, 59, 130, 246), 0.45);
}
%s .fp-resv-widget__headline {
    margin: 0;
    font-size: clamp(1.45rem, 2.5vw, 1.8rem);
    font-weight: var(--fp-resv-heading-weight, 600);
    letter-spacing: -0.01em;
}
%s .fp-resv-widget__subheadline {
    margin: 0.35rem 0 0;
    color: var(--fp-resv-muted);
    font-size: 0.98rem;
}
%s .fp-resv-widget__pdf {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.7rem 1.1rem;
    border-radius: calc(var(--fp-resv-radius) * 0.7);
    font-weight: 600;
    color: var(--fp-resv-primary);
    background: rgba(37, 99, 235, 0.08);
    text-decoration: none;
    transition: background 160ms ease, color 160ms ease;
}
%s .fp-resv-widget__pdf:hover,
%s .fp-resv-widget__pdf:focus-visible {
    background: rgba(37, 99, 235, 0.16);
    color: var(--fp-resv-on-primary);
}
%s .fp-resv-widget__form {
    display: grid;
    gap: clamp(1rem, 3vw, 1.75rem);
    margin: 0;
}
%s .fp-resv-widget__steps {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: clamp(1rem, 3vw, 1.5rem);
}
%s .fp-resv-step {
    display: grid;
    gap: 1.25rem;
    padding: clamp(1.1rem, 3vw, 1.6rem);
    border-radius: calc(var(--fp-resv-radius) * 0.95);
    border: 1px solid var(--fp-resv-divider);
    background: var(--fp-resv-surface-alt);
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
}
%s .fp-resv-step[data-state="locked"] {
    opacity: 0.55;
    pointer-events: none;
    filter: saturate(0.85);
}
%s .fp-resv-step[data-state="active"] {
    border-color: rgba(37, 99, 235, 0.45);
    box-shadow: 0 18px 44px rgba(37, 99, 235, 0.18);
}
%s .fp-resv-step[data-state="completed"] {
    border-color: rgba(22, 163, 74, 0.45);
    box-shadow: 0 18px 44px rgba(22, 163, 74, 0.18);
}
%s .fp-resv-step__label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--fp-resv-muted);
}
%s .fp-resv-step__title {
    margin: 0;
    font-size: clamp(1.2rem, 2.5vw, 1.35rem);
    font-weight: 600;
}
%s .fp-resv-step__description {
    margin: 0;
    color: var(--fp-resv-muted);
    font-size: 0.95rem;
}
%s .fp-resv-field {
    display: grid;
    gap: 0.35rem;
}
%s .fp-resv-field span:first-child {
    font-size: 0.9em;
    font-weight: 600;
    color: var(--fp-resv-color-muted);
    letter-spacing: 0.01em;
}
%s .fp-resv-field--phone {
    grid-column: 1 / -1;
}
%s .fp-resv-field--phone .fp-resv-phone-input {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    gap: 0.65rem;
    flex-wrap: wrap;
}
%s .fp-resv-phone-input .fp-input {
    min-height: 48px;
    flex: 1 1 12rem;
    min-width: 0;
    width: auto;
}
%s .fp-resv-phone-input input.fp-input {
    order: 2;
}
%s .fp-resv-phone-input .fp-input--prefix {
    flex: 0 0 auto;
    width: clamp(5.5rem, 32vw, 8rem);
    max-width: 100%;
    text-align: left;
    padding-inline: 0.65rem;
    order: 1;
}
%s .fp-resv-phone-input__static {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 5rem;
    padding: 0.75rem 1rem;
    border-radius: calc(var(--fp-resv-radius) * 0.55);
    border: 1px solid var(--fp-resv-divider);
    background: rgba(255, 255, 255, 0.85);
    color: var(--fp-resv-muted);
    font-weight: 600;
    flex: 0 0 auto;
    order: 1;
}
%s .fp-resv-field--consent {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: flex-start;
    column-gap: 0.65rem;
    row-gap: 0.35rem;
}
%s .fp-resv-field--consent + .fp-resv-field--consent {
    margin-top: var(--fp-resv-spacing-xs, 0.6rem);
}
%s .fp-resv-field--consent .fp-checkbox {
    align-self: flex-start;
    margin-top: 0.15rem;
}
%s .fp-resv-consent__text {
    display: flex;
    flex-direction: row;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    line-height: 1.5;
    min-width: 0;
}
%s .fp-resv-consent__copy {
    flex: 1 1 auto;
    line-height: 1.5;
}
%s .fp-resv-consent__meta {
    flex: 0 0 auto;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 500;
    letter-spacing: 0.05em;
    line-height: 1.1;
    padding: 0.18rem 0.55rem 0.22rem;
    text-transform: uppercase;
    background: rgba(148, 163, 184, 0.08);
    color: var(--fp-resv-muted);
    border: 1px solid rgba(148, 163, 184, 0.18);
}
%s .fp-resv-consent__meta--required {
    background: rgba(37, 99, 235, 0.12);
    border-color: rgba(37, 99, 235, 0.2);
    color: var(--fp-resv-primary);
    font-weight: 600;
}
%s .fp-resv-consent__text a {
    color: inherit;
    text-decoration: underline;
}
%s .fp-resv-field--honeypot {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(100%);
    border: 0;
    white-space: nowrap;
}
%s .fp-resv-field--honeypot .fp-input {
    pointer-events: none;
}
%s .fp-field {
    display: grid;
    gap: 0.35rem;
    min-width: 0;
}
%s .fp-field > label {
    display: grid;
    gap: 0.35rem;
}
%s .fp-field span:first-child {
    font-weight: 600;
    color: var(--fp-resv-color-muted);
    font-size: 0.9em;
    letter-spacing: 0.01em;
}
%s .fp-hint {
    font-size: 0.78em;
    color: var(--fp-resv-color-muted);
}
%s .fp-input,
%s .fp-select,
%s .fp-textarea {
    width: 100%;
    border-radius: calc(var(--fp-resv-radius-lg) * 0.55);
    border: 1px solid var(--fp-resv-divider, rgba(148, 163, 184, 0.45));
    background: rgba(255, 255, 255, 0.85);
    color: var(--fp-resv-color-text);
    font: inherit;
    padding: 0.75rem 1rem;
    transition: border 160ms ease, box-shadow 160ms ease, background 160ms ease;
    min-height: 48px;
}
%s .fp-input[aria-invalid="true"],
%s .fp-select[aria-invalid="true"],
%s .fp-textarea[aria-invalid="true"] {
    border-color: rgba(220, 38, 38, 0.6);
    box-shadow: var(--fp-resv-focus-ring);
}
%s .fp-textarea {
    resize: vertical;
    min-height: 120px;
}
%s .fp-input:focus,
%s .fp-select:focus,
%s .fp-textarea:focus {
    border-color: var(--fp-resv-color-primary);
    box-shadow: var(--fp-resv-focus-ring);
    background: rgba(255, 255, 255, 1);
    outline: none;
}
%s .fp-checkbox {
    width: 18px;
    height: 18px;
    border-radius: 6px;
    border: 1.5px solid var(--fp-resv-divider, rgba(148, 163, 184, 0.6));
    background: #fff;
    accent-color: var(--fp-resv-color-primary);
    transition: transform 140ms ease;
}
%s .fp-checkbox:focus-visible {
    box-shadow: var(--fp-resv-focus-ring);
    outline: none;
}
%s .fp-checkbox:checked {
    transform: scale(1.05);
}
%s .fp-resv-fields--grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 960px) {
    %s .fp-resv-fields--grid {
        grid-template-columns: 1fr;
    }
    %s .fp-resv-fields--grid .fp-resv-field--email {
        grid-column: 1 / -1;
    }
}
%s .fp-btn,
%s .fp-resv-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-radius: calc(var(--fp-resv-radius) * 0.65);
    border: 1px solid transparent;
    font-weight: 600;
    font-size: 0.95rem;
    padding: 0.75rem 1.4rem;
    cursor: pointer;
    transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease, color 180ms ease;
    text-decoration: none;
}
%s .fp-btn--primary,
%s .fp-resv-button--primary {
    background: var(--fp-resv-primary);
    color: var(--fp-resv-on-primary);
    border-color: transparent;
    box-shadow: 0 18px 40px rgba(37, 99, 235, 0.2);
}
%s .fp-btn--ghost,
%s .fp-resv-button--ghost {
    background: rgba(37, 99, 235, 0.08);
    color: var(--fp-resv-primary);
    border-color: rgba(37, 99, 235, 0.18);
}
%s .fp-btn__spinner {
    font-size: 0.95em;
    font-weight: 500;
    animation: fp-resv-spinner 640ms linear infinite;
}
%s .fp-btn:hover,
%s .fp-btn:focus-visible,
%s .fp-resv-button:hover,
%s .fp-resv-button:focus-visible {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.16);
}
%s .fp-resv-step__footer {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.75rem;
}
%s .fp-slots,
%s .fp-resv-slots {
    display: grid;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: calc(var(--fp-resv-radius-lg) * 0.75);
    border: 1px solid var(--fp-resv-slot-border);
    background: var(--fp-resv-slot-bg);
    box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
}
%s .fp-slots__status,
%s .fp-slots__empty,
%s .fp-resv-slots__status,
%s .fp-resv-slots__empty {
    margin: 0;
    color: var(--fp-resv-color-muted);
    font-size: 0.9rem;
}
%s .fp-slots__status[data-state="loading"],
%s .fp-resv-slots__status[data-state="loading"] {
    font-style: italic;
}
%s .fp-slots__status[data-state="error"],
%s .fp-resv-slots__status[data-state="error"] {
    color: var(--fp-resv-color-error);
    font-weight: 600;
}
%s .fp-slots__list,
%s .fp-resv-slots__list {
    display: grid;
    gap: 0.65rem;
    list-style: none;
    padding: 0;
    margin: 0;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}
%s .fp-slots__list button,
%s .fp-resv-slots__list button {
    border-radius: 999px;
    border: 1px solid var(--fp-resv-slot-border);
    background: var(--fp-resv-slot-bg);
    color: var(--fp-resv-slot-text);
    padding: 0.55rem 0.95rem;
    font-size: 0.9rem;
    transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease, color 160ms ease;
}
%s .fp-slots__list button:hover,
%s .fp-slots__list button:focus-visible,
%s .fp-slots__list button[aria-pressed="true"],
%s .fp-resv-slots__list button:hover,
%s .fp-resv-slots__list button:focus-visible,
%s .fp-resv-slots__list button[aria-pressed="true"] {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
    background: var(--fp-resv-slot-selected-bg);
    color: var(--fp-resv-slot-selected-text);
}
%s .fp-meals {
    border: 1px solid var(--fp-resv-divider, rgba(148, 163, 184, 0.35));
    border-radius: calc(var(--fp-resv-radius-lg) * 0.8);
    padding: clamp(0.85rem, 2.5vw, 1.25rem);
    background: var(--fp-resv-color-surface-alt);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}
%s .fp-meals__header {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.85rem;
}
%s .fp-meals__title {
    margin: 0;
    font-size: 1.05em;
}
%s .fp-meals__subtitle {
    margin: 0;
}
%s .fp-meals__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin: 0 0 1.25rem;
    padding: 0;
    list-style: none;
}
%s .fp-meals__legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    color: var(--fp-resv-color-muted, rgba(15, 23, 42, 0.75));
}
%s .fp-meals__legend-indicator {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 999px;
    border: 1px solid currentColor;
    background: currentColor;
    opacity: 0.7;
}
%s .fp-meals__legend-item--available {
    color: #16a34a;
}
%s .fp-meals__legend-item--limited {
    color: #ca8a04;
}
%s .fp-meals__legend-item--full {
    color: #dc2626;
}
%s .fp-meals__legend-text {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
%s .fp-meals__list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}
%s .fp-meal-pill {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.95rem;
    border-radius: 999px;
    --fp-meal-pill-border: var(--fp-resv-divider, rgba(148, 163, 184, 0.4));
    --fp-meal-pill-border-hover: var(--fp-resv-color-primary);
    --fp-meal-pill-bg: rgba(255, 255, 255, 0.5);
    --fp-meal-pill-text: var(--fp-resv-color-text);
    --fp-meal-pill-highlight: linear-gradient(120deg, var(--fp-resv-color-primary-soft), rgba(14, 116, 144, 0.08));
    --fp-meal-pill-shadow: rgba(15, 23, 42, 0.16);
    border: 1px solid var(--fp-meal-pill-border);
    background: var(--fp-meal-pill-bg);
    color: var(--fp-meal-pill-text);
    font-size: 0.92em;
    font-weight: 500;
    transition: border 180ms ease, box-shadow 180ms ease, transform 180ms ease;
    will-change: transform;
}
%s .fp-meal-pill::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: var(--fp-meal-pill-highlight);
    opacity: 0;
    transition: opacity 180ms ease;
    z-index: -1;
}
%s .fp-meal-pill[data-active],
%s .fp-meal-pill:focus-visible,
%s .fp-meal-pill:hover {
    border-color: var(--fp-meal-pill-border-hover);
    box-shadow: 0 12px 26px var(--fp-meal-pill-shadow);
    transform: translateY(-1px);
}
%s .fp-meal-pill[data-active]::before,
%s .fp-meal-pill:focus-visible::before,
%s .fp-meal-pill:hover::before {
    opacity: 1;
}
%s .fp-meal-pill[data-active] {
    animation: fp-meal-pill-scale-in 120ms ease;
}
%s .fp-meal-pill__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
%s .fp-meal-pill[data-availability-state="available"] {
    --fp-meal-pill-border: rgba(34, 197, 94, 0.5);
    --fp-meal-pill-border-hover: rgba(22, 101, 52, 0.85);
    --fp-meal-pill-bg: rgba(34, 197, 94, 0.16);
    --fp-meal-pill-text: #166534;
    --fp-meal-pill-highlight: linear-gradient(120deg, rgba(34, 197, 94, 0.26), rgba(21, 128, 61, 0.22));
    --fp-meal-pill-shadow: rgba(34, 197, 94, 0.35);
}
%s .fp-meal-pill[data-availability-state="limited"] {
    --fp-meal-pill-border: rgba(234, 179, 8, 0.55);
    --fp-meal-pill-border-hover: rgba(133, 77, 14, 0.85);
    --fp-meal-pill-bg: rgba(250, 204, 21, 0.2);
    --fp-meal-pill-text: #854d0e;
    --fp-meal-pill-highlight: linear-gradient(120deg, rgba(250, 204, 21, 0.28), rgba(202, 138, 4, 0.22));
    --fp-meal-pill-shadow: rgba(250, 204, 21, 0.35);
}
%s .fp-meal-pill[data-availability-state="full"] {
    --fp-meal-pill-border: rgba(248, 113, 113, 0.55);
    --fp-meal-pill-border-hover: rgba(127, 29, 29, 0.85);
    --fp-meal-pill-bg: rgba(248, 113, 113, 0.18);
    --fp-meal-pill-text: #7f1d1d;
    --fp-meal-pill-highlight: linear-gradient(120deg, rgba(248, 113, 113, 0.28), rgba(220, 38, 38, 0.24));
    --fp-meal-pill-shadow: rgba(248, 113, 113, 0.32);
}
%s .fp-meals__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin: clamp(2rem, 5vw, 2.75rem) 0 0;
    font-size: 0.92em;
    color: var(--fp-resv-color-primary);
}
%s .fp-meals__notice-text {
    font-style: italic;
}
%s .fp-meals__notice-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 1.75em;
    height: 1.75em;
    margin-top: 0.1em;
    border-radius: 999px;
    background-color: var(--fp-resv-color-primary);
    color: var(--fp-resv-color-primary-contrast);
    font-style: normal;
    font-weight: 600;
    font-size: 0.75em;
    line-height: 1;
}
%s .fp-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.1rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: var(--fp-resv-badge-bg, rgba(37, 99, 235, 0.12));
    color: var(--fp-resv-badge-text, var(--fp-resv-on-primary));
}
%s .fp-badge::before {
    content: attr(data-icon);
    font-size: 0.78em;
    font-weight: 700;
    opacity: 0.75;
}
%s .fp-badge:not([data-icon]),
%s .fp-badge[data-icon=""],
%s .fp-badge[data-icon=" "] {
    gap: 0;
}
%s .fp-badge:not([data-icon])::before,
%s .fp-badge[data-icon=""]::before,
%s .fp-badge[data-icon=" "]::before {
    display: none;
}
%s .fp-alert {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.75rem;
    padding: clamp(0.85rem, 2vw, 1rem);
    border-radius: calc(var(--fp-resv-radius-lg) * 0.7);
    border: 1px solid transparent;
    font-size: 0.95em;
    animation: fp-resv-fade-in 240ms ease;
}
%s .fp-resv-widget__feedback {
    display: grid;
    gap: var(--fp-space-2, clamp(0.75rem, 2vw, 1rem));
    min-width: 0;
}
%s .fp-resv-widget__feedback .fp-alert {
    margin: 0;
}
%s .fp-alert[hidden] {
    display: none !important;
}
%s .fp-alert--info {
    background: var(--fp-resv-color-primary-soft);
    border-color: rgba(14, 165, 233, 0.25);
    color: var(--fp-resv-color-text);
}
%s .fp-alert--success {
    background: rgba(34, 197, 94, 0.14);
    border-color: rgba(34, 197, 94, 0.25);
    color: var(--fp-resv-color-text);
}
%s .fp-alert--error {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.35);
    color: var(--fp-resv-color-text);
}
%s .fp-skeleton {
    position: relative;
    overflow: hidden;
    display: inline-block;
    width: 100%;
    height: 14px;
    border-radius: 8px;
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.12), rgba(148, 163, 184, 0.2), rgba(148, 163, 184, 0.12));
}
%s .fp-skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    transform: translateX(-100%);
    animation: fp-skeleton-shimmer 1200ms ease-in-out infinite;
}
%s .fp-resv-widget__nojs {
    margin: 0;
}
%s .fp-resv-summary {
    display: grid;
    gap: 0.75rem;
}
%s .fp-resv-summary__list {
    display: grid;
    gap: 0.65rem;
}
%s .fp-resv-summary__list dt {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--fp-resv-muted);
}
%s .fp-resv-summary__list dd {
    margin: 0;
    font-weight: 600;
    color: var(--fp-resv-text);
}
%s .fp-resv-summary__disclaimer {
    margin: 0;
    font-size: 0.85rem;
    color: var(--fp-resv-muted);
}
%s .fp-resv-widget__actions {
    display: grid;
    gap: 0.65rem;
}
%s .fp-resv-widget__nojs {
    margin: 0;
    padding: 1rem;
    border-radius: calc(var(--fp-resv-radius) * 0.85);
    background: rgba(37, 99, 235, 0.12);
    color: var(--fp-resv-text);
}
@keyframes fp-resv-fade-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes fp-meal-pill-scale-in {
    from {
        transform: scale(0.96);
        opacity: 0.75;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}
@keyframes fp-resv-spinner {
    0% {
        opacity: 0.4;
    }
    50% {
        opacity: 1;
    }
    100% {
        opacity: 0.4;
    }
}
@keyframes fp-skeleton-shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
@media (prefers-reduced-motion: reduce) {
    %s .fp-resv,
    %s .fp-progress__item,
    %s .fp-meal-pill,
    %s .fp-btn,
    %s .fp-resv-button,
    %s .fp-resv-step,
    %s .fp-slots__list button,
    %s .fp-resv-slots__list button {
        transition: none !important;
    }
    %s .fp-resv,
    %s .fp-resv-step,
    %s .fp-alert {
        animation: none !important;
    }
    %s .fp-meal-pill[data-active] {
        animation: none !important;
    }
}
@media (max-width: 960px) {
    %s .fp-resv-widget__topbar {
        flex-direction: column;
        align-items: flex-start;
    }
    %s .fp-resv-step__footer {
        justify-content: stretch;
    }
    %s .fp-resv-step__footer > * {
        flex: 1 1 auto;
    }
    %s .fp-progress__item {
        --fp-progress-item-padding-inline: 0.6rem;
        --fp-progress-item-padding-block: 0.55rem;
        --fp-progress-item-min-width: 2.45rem;
        font-size: 0.82em;
    }
    %s .fp-progress__item[data-state="active"],
    %s .fp-progress__item[data-progress-state="active"],
    %s .fp-progress__item:focus-visible {
        --fp-progress-item-padding-inline: 0.68rem;
        --fp-progress-item-min-width: 7.35rem;
        --fp-progress-label-gap: 0.5rem;
        --fp-progress-label-max-width: clamp(6.1rem, 34vw, 9.65rem);
    }
    %s .fp-progress__index {
        width: 2rem;
        height: 2rem;
        font-size: 0.72em;
        letter-spacing: 0.06em;
    }
}
@media (min-width: 961px) {
    %s .fp-progress {
        justify-content: center;
    }
    %s .fp-progress__item {
        flex: 1 1 clamp(2.6rem, 6.8vw, 9.25rem);
        min-width: 0;
    }
}
@media (max-width: 640px) {
    %s .fp-progress {
        gap: 0.45rem;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 0.25rem 0.35rem 0.35rem;
        scroll-snap-type: x proximity;
        scroll-padding-inline: 0.35rem;
    }
    %s .fp-progress::before,
    %s .fp-progress::after {
        display: none;
    }
    %s .fp-progress__item {
        --fp-progress-item-padding-inline: 0.5rem;
        --fp-progress-item-padding-block: 0.5rem;
        --fp-progress-item-min-width: 2.35rem;
        font-size: 0.78em;
    }
    %s .fp-progress__index {
        width: 1.95rem;
        height: 1.95rem;
        font-size: 0.7em;
    }
    %s .fp-progress__item[data-state="active"],
    %s .fp-progress__item[data-progress-state="active"],
    %s .fp-progress__item:focus-visible {
        --fp-progress-item-padding-inline: 0.65rem;
        --fp-progress-item-min-width: min(72vw, 10.75rem);
        --fp-progress-label-gap: 0.5rem;
        --fp-progress-label-max-width: min(68vw, 10.25rem);
    }
    %s .fp-progress__item[data-state="active"] .fp-progress__label,
    %s .fp-progress__item[data-progress-state="active"] .fp-progress__label,
    %s .fp-progress__item:focus-visible .fp-progress__label {
        white-space: normal;
    }
    %s .fp-meals__legend {
        gap: 0.75rem;
        margin-bottom: 1.6rem;
    }
    %s .fp-meals__list {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 0.25rem;
    }
    %s .fp-meal-pill {
        flex: 0 0 auto;
    }
}
CSS;

        return str_replace('%s', $scope, $layout);
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
