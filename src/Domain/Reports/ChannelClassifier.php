<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use function str_contains;
use function strtolower;

/**
 * Classifica i canali di marketing basandosi su source e medium.
 * Estratto da Service.php per migliorare modularità.
 */
final class ChannelClassifier
{
    public function classifyChannel(string $source, string $medium): string
    {
        $source = strtolower($source);
        $medium = strtolower($medium);

        if ($source === '' && $medium === '') {
            return 'direct';
        }

        if (str_contains($source, 'google') && ($medium === 'cpc' || $medium === 'ppc' || $medium === 'paid_search')) {
            return 'google_ads';
        }

        if (str_contains($source, 'gclid')) {
            return 'google_ads';
        }

        if (
            str_contains($source, 'facebook')
            || str_contains($source, 'instagram')
            || str_contains($source, 'meta')
            || $medium === 'paid_social'
        ) {
            return 'meta_ads';
        }

        if ($medium === 'email' || $medium === 'newsletter' || str_contains($source, 'newsletter')) {
            return 'email';
        }

        if ($medium === 'organic' || str_contains($source, 'google') || str_contains($source, 'bing')) {
            return 'organic';
        }

        if ($medium === 'referral') {
            return 'referral';
        }

        if ($source === 'direct' || $medium === '(none)') {
            return 'direct';
        }

        return 'other';
    }
}
















