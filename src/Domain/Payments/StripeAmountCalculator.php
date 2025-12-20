<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use FP\Resv\Domain\Settings\Options;
use function apply_filters;
use function in_array;
use function max;
use function round;
use function strtoupper;

/**
 * Calcola gli importi per i pagamenti Stripe.
 * Estratto da StripeService per migliorare la manutenibilità.
 */
final class StripeAmountCalculator
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Calcola l'importo della prenotazione.
     *
     * @param array<string, mixed> $reservation
     */
    public function calculateReservationAmount(array $reservation, int $reservationId): float
    {
        $strategy = $this->captureStrategy();
        $party    = max(1, (int) ($reservation['party'] ?? 1));
        $amount   = 0.0;

        $submittedValue = $reservation['value'] ?? null;
        if ($submittedValue !== null && is_numeric($submittedValue)) {
            $amount = (float) $submittedValue;
        }

        if ($strategy === 'deposit') {
            $amount = max($amount, $this->depositAmount() * $party);
        } elseif ($amount <= 0) {
            $amount = $this->depositAmount() * $party;
        }

        $amount = (float) apply_filters(
            'fp_resv_stripe_reservation_amount',
            $amount,
            $reservation,
            $reservationId,
            $strategy
        );

        if ($amount < 0) {
            $amount = 0.0;
        }

        return round($amount, 2);
    }

    /**
     * Converte un importo in unità minori (centesimi per la maggior parte delle valute).
     */
    public function toMinorUnits(float $amount, string $currency): int
    {
        $currency = strtoupper($currency);
        $zeroDecimalCurrencies = apply_filters('fp_resv_stripe_zero_decimal_currencies', [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ]);

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return (int) round($amount, 0);
        }

        return (int) round($amount * 100);
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

    /**
     * Ottiene l'importo del deposito.
     */
    private function depositAmount(): float
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_deposit_amount' => '0',
        ]);

        $amount = (float) ($settings['stripe_deposit_amount'] ?? 0);
        if ($amount < 0) {
            $amount = 0.0;
        }

        return round($amount, 2);
    }
}















