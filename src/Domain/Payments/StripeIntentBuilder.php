<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use FP\Resv\Domain\Settings\Options;
use function home_url;
use function is_string;
use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * Costruisce i payload per gli intent Stripe.
 * Estratto da StripeService per migliorare la manutenibilità.
 */
final class StripeIntentBuilder
{
    public function __construct(
        private readonly Options $options,
        private readonly StripeAmountCalculator $amountCalculator
    ) {
    }

    /**
     * Costruisce il payload per creare un Payment Intent.
     *
     * @param array<string, mixed> $reservation
     * @return array<string, mixed>
     */
    public function buildIntentPayload(int $reservationId, array $reservation, float $amount, string $currency): array
    {
        $amountMinor = $this->amountCalculator->toMinorUnits($amount, $currency);
        $capture     = $this->captureStrategy() === 'authorization' ? 'manual' : 'automatic';

        $payload = [
            'amount'                                => $amountMinor,
            'currency'                              => strtolower($currency),
            'capture_method'                        => $capture,
            'confirmation_method'                   => 'automatic',
            'automatic_payment_methods[enabled]'    => 'true',
            'metadata[reservation_id]'              => (string) $reservationId,
            'metadata[strategy]'                    => $this->captureStrategy(),
            'metadata[site_url]'                    => home_url('/'),
            'description'                           => sprintf('Reservation #%d', $reservationId),
        ];

        if (!empty($reservation['email']) && is_string($reservation['email'])) {
            $payload['receipt_email'] = (string) $reservation['email'];
        }

        return $payload;
    }

    /**
     * Costruisce uno snapshot dei metadati dell'intent.
     *
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    public function buildMetaSnapshot(array $intent, float $amount): array
    {
        $timestamp = current_time('mysql');

        return [
            'client_secret' => $intent['client_secret'] ?? null,
            'intent_status' => $intent['status'] ?? null,
            'latest_intent' => $intent,
            'amount'        => $amount,
            'logs'          => [
                [
                    'timestamp' => $timestamp,
                    'status'    => $intent['status'] ?? null,
                ],
            ],
        ];
    }

    /**
     * Ottiene la strategia di capture.
     */
    private function captureStrategy(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_capture_type' => 'authorization',
        ]);

        $strategy = sanitize_text_field((string) ($settings['stripe_capture_type'] ?? 'authorization'));
        if (!in_array($strategy, ['authorization', 'capture', 'deposit'], true)) {
            $strategy = 'authorization';
        }

        return $strategy;
    }
}















