<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Surveys;

use function array_filter;
use function array_sum;
use function count;
use function max;
use function min;

final class NPS
{
    public function classification(int $score): string
    {
        $score = max(0, min(10, $score));
        if ($score >= 9) {
            return 'promoter';
        }

        if ($score >= 7) {
            return 'passive';
        }

        return 'detractor';
    }

    /**
     * @param array<int, int|null> $scores
     */
    public function average(array $scores): float
    {
        $filtered = array_filter($scores, static fn ($value): bool => $value !== null);
        if ($filtered === []) {
            return 0.0;
        }

        return round(array_sum($filtered) / count($filtered), 2);
    }

    public function isPositive(float $average, int $nps, float $averageThreshold, int $npsThreshold): bool
    {
        return $average >= $averageThreshold || $nps >= $npsThreshold;
    }
}
