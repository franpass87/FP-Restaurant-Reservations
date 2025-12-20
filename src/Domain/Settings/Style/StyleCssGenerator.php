<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings\Style;

use FP\Resv\Domain\Settings\StyleCss;
use function implode;
use function max;
use function min;
use function number_format;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Genera il CSS per gli stili.
 * Estratto da Style per migliorare la manutenibilità.
 */
final class StyleCssGenerator
{
    public function __construct()
    {
    }

    /**
     * Genera il CSS completo per un form.
     *
     * @param array<string, string> $tokens
     * @param array<string, mixed>  $settings
     * @param array<string, string> $shadowPresets
     * @return array<string, string>
     */
    public function build(string $formId, array $tokens, array $settings, array $shadowPresets): array
    {
        $scope     = '#' . $formId;
        $variables = $this->buildVariableBlock($scope, $tokens, $settings, $shadowPresets);
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
     * Costruisce il blocco di variabili CSS.
     *
     * @param array<string, string> $tokens
     * @param array<string, mixed>  $settings
     * @param array<string, string> $shadowPresets
     */
    private function buildVariableBlock(string $scope, array $tokens, array $settings, array $shadowPresets): string
    {
        $radius = (int) ($settings['style_border_radius'] ?? 8);
        $radius = max(0, min(48, $radius));
        $shadow  = $shadowPresets[$settings['style_shadow_level'] ?? 'soft'] ?? $shadowPresets['soft'] ?? '';
        $shadow  = $shadows[$settings['style_shadow_level'] ?? 'soft'] ?? $shadows['soft'];
        $font    = trim((string) ($settings['style_font_family'] ?? '"Inter", sans-serif'));
        $fontSize = (int) ($settings['style_font_size'] ?? 16);
        $fontSize = max(14, min(20, $fontSize));
        $headingWeight = $this->resolveHeadingWeight((string) ($settings['style_heading_weight'] ?? '600'));
        $spacingUnit   = $this->formatSpacingUnit((string) ($settings['style_spacing_scale'] ?? 'cozy'));
        $focusWidth    = (int) ($settings['style_focus_ring_width'] ?? 3);
        $focusWidth    = max(1, min(6, $focusWidth));

        $colorCalculator = new ColorCalculator();
        [$primaryRed, $primaryGreen, $primaryBlue] = $colorCalculator->hexToRgb($tokens['primary']);

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
     * Costruisce le variabili CSS per la dark mode.
     *
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

    /**
     * Costruisce il CSS base.
     */
    private function buildBaseCss(string $scope): string
    {
        return StyleCss::buildBaseCss($scope);
    }

    /**
     * Applica lo scope al CSS personalizzato.
     */
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

    /**
     * Formatta l'unità di spaziatura.
     */
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

    /**
     * Risolve il peso del font per i titoli.
     */
    private function resolveHeadingWeight(string $weight): string
    {
        $allowed = ['500', '600', '700'];
        return in_array($weight, $allowed, true) ? $weight : '600';
    }
}

