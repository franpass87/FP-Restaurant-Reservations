<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings\Style;

use function __;
use function array_merge;
use function in_array;

/**
 * Genera report di contrasto per gli stili.
 * Estratto da Style per migliorare la manutenibilità.
 */
final class ContrastReporter
{
    public function __construct(
        private readonly ColorCalculator $colorCalculator
    ) {
    }

    /**
     * Costruisce un report di contrasto per i token CSS.
     *
     * @param array<string, string> $tokens
     * @return array<int, array<string, mixed>>
     */
    public function buildReport(array $tokens): array
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
            $ratio = $this->colorCalculator->contrastRatio($entry['foreground'], $entry['background']);
            $grade = $this->colorCalculator->gradeFromRatio($ratio);

            $report[] = array_merge($entry, [
                'ratio'        => (float) $this->colorCalculator->formatContrastRatio($ratio),
                'grade'        => $grade,
                'is_compliant' => in_array($grade, ['AA', 'AAA'], true),
            ]);
        }

        return $report;
    }
}















