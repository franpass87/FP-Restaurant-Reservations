<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Settings\Options;
use function explode;
use function trim;

final class Ads
{
    public function __construct(private readonly Options $options)
    {
    }

    public function conversionId(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', []);
        $id       = isset($settings['google_ads_conversion_id']) ? (string) $settings['google_ads_conversion_id'] : '';

        return trim($id);
    }

    public function gtagLoaderId(): string
    {
        $id = $this->conversionId();
        if ($id === '') {
            return '';
        }

        $parts = explode('/', $id, 2);

        return trim($parts[0]);
    }

    public function isEnabled(): bool
    {
        return $this->conversionId() !== '';
    }

    public function conversionPayload(int $reservationId, float $value, string $currency): ?array
    {
        $id = $this->conversionId();
        if ($id === '') {
            return null;
        }

        $payload = [
            'name'   => 'conversion',
            'params' => [
                'send_to' => $id,
                'value'   => $value > 0 ? $value : 0,
                'currency'=> $currency !== '' ? $currency : 'EUR',
            ],
        ];

        if ($reservationId > 0) {
            $payload['params']['transaction_id'] = 'resv-' . $reservationId;
        }

        return $payload;
    }
}
