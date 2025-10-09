<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Settings\Options;
use function trim;

final class GA4
{
    public function __construct(private readonly Options $options)
    {
    }

    public function measurementId(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $id       = isset($settings['ga4_measurement_id']) ? (string) $settings['ga4_measurement_id'] : '';

        return trim($id);
    }

    public function apiSecret(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $secret   = isset($settings['ga4_api_secret']) ? (string) $settings['ga4_api_secret'] : '';

        return trim($secret);
    }

    public function isEnabled(): bool
    {
        return $this->measurementId() !== '';
    }

    public function isServerSideEnabled(): bool
    {
        return $this->measurementId() !== '' && $this->apiSecret() !== '';
    }
}
