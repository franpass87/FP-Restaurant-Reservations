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

    public function isEnabled(): bool
    {
        return $this->measurementId() !== '';
    }
}
