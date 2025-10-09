<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Settings\Options;
use function array_filter;
use function trim;

final class Meta
{
    public function __construct(private readonly Options $options)
    {
    }

    public function pixelId(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $id       = isset($settings['meta_pixel_id']) ? (string) $settings['meta_pixel_id'] : '';

        return trim($id);
    }

    public function accessToken(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $token    = isset($settings['meta_access_token']) ? (string) $settings['meta_access_token'] : '';

        return trim($token);
    }

    public function isEnabled(): bool
    {
        return $this->pixelId() !== '';
    }

    public function isServerSideEnabled(): bool
    {
        return $this->pixelId() !== '' && $this->accessToken() !== '';
    }

    public function eventPayload(string $event, float $value, string $currency, int $reservationId = 0): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $params = array_filter([
            'value'    => $value > 0 ? $value : null,
            'currency' => $currency !== '' ? $currency : null,
            'contents' => $reservationId > 0 ? [['id' => 'reservation-' . $reservationId]] : null,
        ], static fn ($item) => $item !== null);

        return [
            'name'   => $event,
            'params' => $params,
        ];
    }
}
