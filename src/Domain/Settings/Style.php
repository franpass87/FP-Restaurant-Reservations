<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use FP\Resv\Domain\Settings\Style\ColorCalculator;
use FP\Resv\Domain\Settings\Style\ContrastReporter;
use FP\Resv\Domain\Settings\Style\StyleCssGenerator;
use FP\Resv\Domain\Settings\Style\StyleTokenBuilder;
use function update_option;

final class Style
{
    public function __construct(
        private readonly Options $options,
        private readonly ColorCalculator $colorCalculator,
        private readonly StyleTokenBuilder $tokenBuilder,
        private readonly StyleCssGenerator $cssGenerator,
        private readonly ContrastReporter $contrastReporter
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return [
            'style_palette'          => 'grayscale', // Palette grigia professionale di default
            'style_primary_color'    => '#374151', // Grigio scuro professionale
            'style_button_bg'        => '#374151', // Bottoni grigi eleganti
            'style_button_text'      => '#ffffff', // Testo bianco
            'style_font_family'      => '"Inter", sans-serif',
            'style_font_size'        => '16',
            'style_heading_weight'   => '600',
            'style_border_radius'    => '12', // Bordi più arrotondati
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
            'grayscale' => [
                'background'      => '#f9fafb',
                'surface'         => '#ffffff',
                'text'            => '#111827',
                'muted'           => '#6b7280',
                'accent'          => '#374151',
                'dark_background' => '#111827',
                'dark_surface'    => '#1f2937',
                'dark_text'       => '#f9fafb',
                'dark_muted'      => '#9ca3af',
                'dark_accent'     => '#4b5563',
            ],
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
        return $this->tokenBuilder->build($palettes, $settings);
    }

    /**
     * @param array<string, string> $tokens
     * @param array<string, mixed>  $settings
     *
     * @return array<string, string>
     */
    private function buildCss(string $formId, array $tokens, array $settings): array
    {
        $shadowPresets = $this->getShadowPresets();
        return $this->cssGenerator->build($formId, $tokens, $settings, $shadowPresets);
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildContrastReport(array $tokens): array
    {
        return $this->contrastReporter->buildReport($tokens);
    }

}
