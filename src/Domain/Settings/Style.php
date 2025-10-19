<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use FP\Resv\Domain\Settings\StyleCss;
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
            'style_palette'          => 'neutral', // B/W palette di default
            'style_primary_color'    => '#000000', // Nero di default
            'style_button_bg'        => '#000000', // Bottoni neri
            'style_button_text'      => '#ffffff', // Testo bianco
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
                'accent'          => '#000000', // B/W: nero invece di blu
                'dark_background' => '#0f172a',
                'dark_surface'    => '#1e293b',
                'dark_text'       => '#e2e8f0',
                'dark_muted'      => '#94a3b8',
                'dark_accent'     => '#ffffff', // B/W: bianco invece di blu
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
        $buttonBg     = $this->normalizeHex((string) ($settings['style_button_bg'] ?? '#000000'));
        $buttonText   = $this->normalizeHex((string) ($settings['style_button_text'] ?? '#ffffff'));
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
            'button_bg'              => $buttonBg,
            'button_text'            => $buttonText,
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
        $lines[] = '    --fp-resv-button-bg: ' . $tokens['button_bg'] . ';';
        $lines[] = '    --fp-resv-button-text: ' . $tokens['button_text'] . ';';
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
        return StyleCss::buildBaseCss($scope);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildContrastReport(array $tokens): array
    {
        $entries = [
            [
                'id'         => 'primary-button',
                'label'      => __('Bottone "Continua"', 'fp-restaurant-reservations'),
                'foreground' => $tokens['button_text'],
                'background' => $tokens['button_bg'],
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
