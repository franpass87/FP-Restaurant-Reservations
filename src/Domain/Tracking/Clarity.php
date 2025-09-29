<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Settings\Options;
use function trim;

final class Clarity
{
    public function __construct(private readonly Options $options)
    {
    }

    public function projectId(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $id       = isset($settings['clarity_project_id']) ? (string) $settings['clarity_project_id'] : '';

        return trim($id);
    }

    public function isEnabled(): bool
    {
        return $this->projectId() !== '';
    }
}
