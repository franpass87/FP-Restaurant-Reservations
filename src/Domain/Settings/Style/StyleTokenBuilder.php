<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings\Style;

use FP\Resv\Domain\Settings\Style;

/**
 * Costruisce i token CSS per gli stili.
 * Estratto da Style per migliorare la manutenibilità.
 */
final class StyleTokenBuilder
{
    private const SUCCESS_COLOR = '#1d9a6c';
    private const DANGER_COLOR  = '#d14545';

    public function __construct(
        private readonly ColorCalculator $colorCalculator
    ) {
    }

    /**
     * Costruisce i token CSS dalle impostazioni.
     *
     * @param array<string, array<string, string>> $palettes
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    public function build(array $palettes, array $settings): array
    {
        $palette  = $settings['style_palette'] ?? 'brand';
        if (!isset($palettes[$palette])) {
            $palette = 'brand';
        }

        $base         = $palettes[$palette];
        $primary      = $this->colorCalculator->normalizeHex((string) ($settings['style_primary_color'] ?? '#bb2649'));
        $buttonBg     = $this->colorCalculator->normalizeHex((string) ($settings['style_button_bg'] ?? '#000000'));
        $buttonText   = $this->colorCalculator->normalizeHex((string) ($settings['style_button_text'] ?? '#ffffff'));
        $background   = $this->colorCalculator->normalizeHex($base['background']);
        $surface      = $this->colorCalculator->normalizeHex($base['surface']);
        $text         = $this->colorCalculator->normalizeHex($base['text']);
        $muted        = $this->colorCalculator->normalizeHex($base['muted']);
        $accent       = $this->colorCalculator->normalizeHex($base['accent']);
        $onPrimary    = $this->colorCalculator->pickForeground($primary);
        $accentText   = $this->colorCalculator->pickForeground($accent);
        $focus        = $this->colorCalculator->mix($primary, '#ffffff', 0.6);
        $outline      = $this->colorCalculator->mix($primary, $background, 0.45);
        $surfaceAlt   = $this->colorCalculator->mix($surface, '#000000', 0.06);
        $divider      = $this->colorCalculator->mix($surface, '#000000', 0.12);
        $slotBg       = $this->colorCalculator->mix($surface, $primary, 0.1);
        $slotHover    = $this->colorCalculator->mix($primary, '#ffffff', 0.85);
        $badgeBg      = $this->colorCalculator->mix($accent, '#ffffff', 0.2);
        $badgeText    = $this->colorCalculator->pickForeground($badgeBg);

        $darkBackground = $this->colorCalculator->normalizeHex($base['dark_background']);
        $darkSurface    = $this->colorCalculator->normalizeHex($base['dark_surface']);
        $darkText       = $this->colorCalculator->normalizeHex($base['dark_text']);
        $darkMuted      = $this->colorCalculator->normalizeHex($base['dark_muted']);
        $darkAccent     = $this->colorCalculator->normalizeHex($base['dark_accent']);
        $darkBadgeBg    = $this->colorCalculator->mix($darkAccent, '#000000', 0.25);
        $darkBadgeText  = $this->colorCalculator->pickForeground($darkBadgeBg);
        $darkOutline    = $this->colorCalculator->mix($primary, $darkBackground, 0.5);
        $darkFocus      = $this->colorCalculator->mix($primary, '#ffffff', 0.5);
        $darkSlotBg     = $this->colorCalculator->mix($darkSurface, $primary, 0.12);
        $darkSlotHover  = $this->colorCalculator->mix($primary, '#ffffff', 0.82);
        $darkDivider    = $this->colorCalculator->mix($darkSurface, '#ffffff', 0.12);

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
            'dark_surface_alt'       => $this->colorCalculator->mix($darkSurface, '#000000', 0.18),
            'dark_text'              => $darkText,
            'dark_muted'             => $darkMuted,
            'dark_accent'            => $darkAccent,
            'dark_accent_text'       => $this->colorCalculator->pickForeground($darkAccent),
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
}

