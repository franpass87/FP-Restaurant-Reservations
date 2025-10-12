<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use function get_option;
use function sanitize_hex_color;
use function update_option;

final class FormColors
{
    private const OPTION_NAME = 'fp_resv_form_colors';

    /**
     * Get default colors (Black & White scheme)
     *
     * @return array<string, string>
     */
    public function getDefaults(): array
    {
        return [
            'primary'             => '#000000',
            'primary_hover'       => '#1a1a1a',
            'surface'             => '#ffffff',
            'surface_alt'         => '#fafafa',
            'text'                => '#000000',
            'text_muted'          => '#666666',
            'text_light'          => '#999999',
            'border'              => '#e0e0e0',
            'border_light'        => '#f0f0f0',
            'divider'             => '#e5e5e5',
            'success'             => '#2d2d2d',
            'error'               => '#dc2626',
            'warning'             => '#666666',
            'button_bg'           => '#000000',
            'button_text'         => '#ffffff',
            'button_hover'        => '#1a1a1a',
        ];
    }

    /**
     * Get saved colors or defaults
     *
     * @return array<string, string>
     */
    public function getColors(): array
    {
        $saved = get_option(self::OPTION_NAME, []);
        
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($this->getDefaults(), $saved);
    }

    /**
     * Save colors
     *
     * @param array<string, string> $colors
     * @return bool
     */
    public function saveColors(array $colors): bool
    {
        $sanitized = [];

        foreach ($colors as $key => $value) {
            if (!isset($this->getDefaults()[$key])) {
                continue;
            }

            $clean = sanitize_hex_color($value);
            if ($clean !== null) {
                $sanitized[$key] = $clean;
            }
        }

        return update_option(self::OPTION_NAME, $sanitized);
    }

    /**
     * Reset to defaults
     *
     * @return bool
     */
    public function reset(): bool
    {
        return update_option(self::OPTION_NAME, $this->getDefaults());
    }

    /**
     * Generate CSS variables
     *
     * @return string
     */
    public function generateCSS(): string
    {
        $colors = $this->getColors();

        $css = ":root {\n";
        $css .= "  /* FP Restaurant Reservations - Custom Colors */\n";
        $css .= "  --fp-color-primary: {$colors['primary']};\n";
        $css .= "  --fp-color-primary-hover: {$colors['primary_hover']};\n";
        $css .= "  --fp-color-primary-light: " . $this->hexToRgba($colors['primary'], 0.05) . ";\n";
        $css .= "  --fp-color-primary-rgb: " . $this->hexToRgb($colors['primary']) . ";\n";
        $css .= "  --fp-color-surface: {$colors['surface']};\n";
        $css .= "  --fp-color-surface-alt: {$colors['surface_alt']};\n";
        $css .= "  --fp-color-text: {$colors['text']};\n";
        $css .= "  --fp-color-text-muted: {$colors['text_muted']};\n";
        $css .= "  --fp-color-text-light: {$colors['text_light']};\n";
        $css .= "  --fp-color-border: {$colors['border']};\n";
        $css .= "  --fp-color-border-light: {$colors['border_light']};\n";
        $css .= "  --fp-color-divider: {$colors['divider']};\n";
        $css .= "  --fp-color-success: {$colors['success']};\n";
        $css .= "  --fp-color-error: {$colors['error']};\n";
        $css .= "  --fp-color-warning: {$colors['warning']};\n";
        $css .= "  \n";
        $css .= "  /* Button Colors */\n";
        $css .= "  --fp-resv-button-bg: {$colors['button_bg']};\n";
        $css .= "  --fp-resv-button-text: {$colors['button_text']};\n";
        $css .= "  \n";
        $css .= "  /* Gradients */\n";
        $css .= "  --fp-gradient-primary: linear-gradient(135deg, {$colors['primary']} 0%, {$colors['primary_hover']} 100%);\n";
        $css .= "  --fp-gradient-surface: linear-gradient(145deg, {$colors['surface']} 0%, {$colors['surface_alt']} 100%);\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * Convert hex to rgba
     *
     * @param string $hex
     * @param float $alpha
     * @return string
     */
    private function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba($r, $g, $b, $alpha)";
    }

    /**
     * Convert hex to rgb
     *
     * @param string $hex
     * @return string
     */
    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "$r, $g, $b";
    }

    /**
     * Get color presets
     *
     * @return array<string, array<string, string>>
     */
    public function getPresets(): array
    {
        return [
            'black_white' => [
                'name'   => __('Bianco e Nero', 'fp-restaurant-reservations'),
                'colors' => [
                    'primary'       => '#000000',
                    'primary_hover' => '#1a1a1a',
                    'surface'       => '#ffffff',
                    'button_bg'     => '#000000',
                    'button_text'   => '#ffffff',
                ],
            ],
            'dark_gray' => [
                'name'   => __('Grigio Scuro', 'fp-restaurant-reservations'),
                'colors' => [
                    'primary'       => '#2d2d2d',
                    'primary_hover' => '#1a1a1a',
                    'surface'       => '#ffffff',
                    'button_bg'     => '#2d2d2d',
                    'button_text'   => '#ffffff',
                ],
            ],
            'navy_blue' => [
                'name'   => __('Blu Navy', 'fp-restaurant-reservations'),
                'colors' => [
                    'primary'       => '#1a237e',
                    'primary_hover' => '#0d47a1',
                    'surface'       => '#ffffff',
                    'button_bg'     => '#1a237e',
                    'button_text'   => '#ffffff',
                ],
            ],
            'forest_green' => [
                'name'   => __('Verde Bosco', 'fp-restaurant-reservations'),
                'colors' => [
                    'primary'       => '#1b5e20',
                    'primary_hover' => '#2e7d32',
                    'surface'       => '#ffffff',
                    'button_bg'     => '#1b5e20',
                    'button_text'   => '#ffffff',
                ],
            ],
        ];
    }
}

