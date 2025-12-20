<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings\Style;

use function max;
use function min;
use function number_format;
use function preg_match;
use function sprintf;
use function str_split;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Calcola contrasti e manipola colori per gli stili.
 * Estratto da Style per migliorare la manutenibilità.
 */
final class ColorCalculator
{
    /**
     * Calcola il rapporto di contrasto tra due colori.
     */
    public function contrastRatio(string $foreground, string $background): float
    {
        $lum1 = $this->relativeLuminance($foreground);
        $lum2 = $this->relativeLuminance($background);
        $lighter = max($lum1, $lum2);
        $darker  = min($lum1, $lum2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Calcola la luminanza relativa di un colore.
     */
    public function relativeLuminance(string $color): float
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

    /**
     * Sceglie il colore del testo ottimale per uno sfondo.
     */
    public function pickForeground(string $background): string
    {
        $whiteContrast = $this->contrastRatio('#ffffff', $background);
        $darkContrast  = $this->contrastRatio('#111827', $background);

        return $whiteContrast >= $darkContrast ? '#ffffff' : '#111827';
    }

    /**
     * Normalizza un colore hex in formato #rrggbb.
     */
    public function normalizeHex(string $color): string
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
     * Converte un colore hex in RGB.
     *
     * @return array{0:int,1:int,2:int}
     */
    public function hexToRgb(string $color): array
    {
        $color = $this->normalizeHex($color);
        $hex   = substr($color, 1);

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Mescola due colori.
     */
    public function mix(string $from, string $to, float $amount): string
    {
        [$fromR, $fromG, $fromB] = $this->hexToRgb($from);
        [$toR, $toG, $toB] = $this->hexToRgb($to);

        $r = (int) round($fromR + ($toR - $fromR) * $amount);
        $g = (int) round($fromG + ($toG - $fromG) * $amount);
        $b = (int) round($fromB + ($toB - $fromB) * $amount);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Valuta il contrasto e restituisce un grade (AAA, AA, AA Large, Fail).
     */
    public function gradeFromRatio(float $ratio): string
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

    /**
     * Calcola il rapporto di contrasto formattato.
     */
    public function formatContrastRatio(float $ratio): string
    {
        return (string) number_format($ratio, 2, '.', '');
    }
}















