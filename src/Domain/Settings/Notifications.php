<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

final class Notifications
{
    public function getDefaultRecipients(): array
    {
        return ['info@francescopasseri.com'];
    }
}
