<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use function is_array;
use function is_string;

/**
 * Mappa gli status degli intent Stripe agli status interni.
 * Estratto da StripeService per migliorare la manutenibilità.
 */
final class StripeStatusMapper
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_PAID       = 'paid';
    public const STATUS_REFUNDED   = 'refunded';
    public const STATUS_VOID       = 'void';

    /**
     * Mappa lo status di un intent Stripe allo status interno.
     *
     * @param array<string, mixed>|string $intent
     */
    public function mapIntentStatus(array|string $intent): string
    {
        $status = is_array($intent) ? (string) ($intent['status'] ?? '') : (string) $intent;

        return match ($status) {
            'requires_capture' => self::STATUS_AUTHORIZED,
            'succeeded'        => self::STATUS_PAID,
            'canceled'         => self::STATUS_VOID,
            default            => self::STATUS_PENDING,
        };
    }
}















