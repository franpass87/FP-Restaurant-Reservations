<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use FP\Resv\Domain\Settings\Options;
use RuntimeException;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function preg_match;
use function rawurlencode;
use function sanitize_text_field;
use function strtoupper;
use function wp_json_encode;

final class StripeService
{
    public const STATUS_PENDING    = StripeStatusMapper::STATUS_PENDING;
    public const STATUS_AUTHORIZED = StripeStatusMapper::STATUS_AUTHORIZED;
    public const STATUS_PAID       = StripeStatusMapper::STATUS_PAID;
    public const STATUS_REFUNDED   = StripeStatusMapper::STATUS_REFUNDED;
    public const STATUS_VOID       = StripeStatusMapper::STATUS_VOID;

    public function __construct(
        private readonly Options $options,
        private readonly Repository $repository,
        private readonly StripeApiClient $apiClient,
        private readonly StripeAmountCalculator $amountCalculator,
        private readonly StripeIntentBuilder $intentBuilder,
        private readonly StripeStatusMapper $statusMapper,
        private readonly StripePaymentFormatter $formatter
    ) {
    }

    public function isEnabled(): bool
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_enabled' => '0',
        ]);

        return (string) ($settings['stripe_enabled'] ?? '0') === '1';
    }

    public function mode(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_mode' => 'test',
        ]);

        $mode = sanitize_text_field((string) ($settings['stripe_mode'] ?? 'test'));
        if (!in_array($mode, ['test', 'live'], true)) {
            $mode = 'test';
        }

        return $mode;
    }

    public function captureStrategy(): string
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

    public function currency(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_currency' => 'EUR',
        ]);

        $currency = strtoupper(sanitize_text_field((string) ($settings['stripe_currency'] ?? 'EUR')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        return $currency;
    }

    public function depositAmount(): float
    {
        $settings = $this->options->getGroup('fp_resv_payments', [
            'stripe_deposit_amount' => '0',
        ]);

        return (float) $settings['stripe_deposit_amount'];
    }

    public function publishableKey(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', []);

        return (string) ($settings['stripe_publishable_key'] ?? '');
    }

    public function secretKey(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', []);

        return (string) ($settings['stripe_secret_key'] ?? '');
    }

    public function webhookSecret(): string
    {
        $settings = $this->options->getGroup('fp_resv_payments', []);

        return (string) ($settings['stripe_webhook_secret'] ?? '');
    }

    /**
     * @param array<string, mixed> $reservation
     */
    public function shouldRequireReservationPayment(array $reservation): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $amount = $this->calculateReservationAmount($reservation, (int) ($reservation['id'] ?? 0));

        return $amount > 0;
    }

    /**
     * @param array<string, mixed> $reservation
     */
    public function calculateReservationAmount(array $reservation, int $reservationId): float
    {
        return $this->amountCalculator->calculateReservationAmount($reservation, $reservationId);
    }

    /**
     * @param array<string, mixed> $reservation
     */
    public function createReservationIntent(int $reservationId, array $reservation, ?float $amountOverride = null): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Stripe payments are disabled.');
        }

        $amount   = $amountOverride ?? $this->calculateReservationAmount($reservation, $reservationId);
        $amount   = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Invalid payment amount.');
        }

        $currency = strtoupper((string) ($reservation['currency'] ?? $this->currency()));
        if ($currency === '') {
            $currency = $this->currency();
        }

        $intent = $this->apiClient->request('POST', '/payment_intents', $this->intentBuilder->buildIntentPayload($reservationId, $reservation, $amount, $currency));

        $status = $this->statusMapper->mapIntentStatus($intent);
        $meta   = $this->intentBuilder->buildMetaSnapshot($intent, $amount);

        $paymentId = $this->repository->insert([
            'reservation_id' => $reservationId,
            'provider'       => 'stripe',
            'type'           => $this->captureStrategy(),
            'amount'         => $amount,
            'currency'       => strtoupper($currency),
            'status'         => $status,
            'external_id'    => $intent['id'] ?? null,
            'meta_json'      => wp_json_encode($meta),
        ]);

        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException('Payment record could not be stored.');
        }

        return $this->formatter->formatPaymentRecord($record);
    }

    public function refreshPayment(int $paymentId): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException('Payment not found.');
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException('Stripe intent id missing.');
        }

        $intent = $this->apiClient->request('GET', '/payment_intents/' . rawurlencode($intentId));

        $meta   = $this->formatter->mergeMeta($record, $intent);
        $status = $this->statusMapper->mapIntentStatus($intent);

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException('Unable to reload updated payment.');
        }

        return $this->formatPaymentRecord($updated);
    }

    public function capturePayment(int $paymentId): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException('Payment not found.');
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException('Stripe intent id missing.');
        }

        $intent = $this->apiClient->request('POST', '/payment_intents/' . rawurlencode($intentId) . '/capture');

        $meta   = $this->formatter->mergeMeta($record, $intent);
        $status = $this->statusMapper->mapIntentStatus($intent);

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException('Unable to reload captured payment.');
        }

        return $this->formatPaymentRecord($updated);
    }

    public function voidPayment(int $paymentId, string $reason = 'requested_by_customer'): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException('Payment not found.');
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException('Stripe intent id missing.');
        }

        $intent = $this->apiClient->request('POST', '/payment_intents/' . rawurlencode($intentId) . '/cancel', [
            'cancellation_reason' => $reason,
        ]);

        $meta   = $this->formatter->mergeMeta($record, $intent);
        $status = $this->statusMapper->mapIntentStatus($intent);

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException('Unable to reload voided payment.');
        }

        return $this->formatPaymentRecord($updated);
    }

    public function refundPayment(int $paymentId, ?float $amount = null): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException('Payment not found.');
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException('Stripe intent id missing.');
        }

        $payload = [
            'payment_intent' => $intentId,
        ];

        if ($amount !== null && $amount > 0) {
            $payload['amount'] = $this->toMinorUnits($amount, (string) ($record['currency'] ?? $this->currency()));
        }

        $refund = $this->request('POST', '/refunds', $payload);

        $meta   = $this->mergeMeta($record, $refund, 'refund');
        $status = self::STATUS_REFUNDED;

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException('Unable to reload refunded payment.');
        }

        return $this->formatPaymentRecord($updated);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function formatPaymentRecord(array $record): array
    {
        $formatted = $this->formatter->formatPaymentRecord($record);
        $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];

        return array_merge($formatted, [
            'status'           => (string) ($record['status'] ?? self::STATUS_PENDING),
            'strategy'          => (string) ($record['type'] ?? $this->captureStrategy()),
            'currency'          => strtoupper((string) ($record['currency'] ?? $this->currency())),
            'client_secret'     => (string) ($meta['client_secret'] ?? ''),
            'intent_status'     => (string) ($meta['intent_status'] ?? ''),
            'mode'              => $this->mode(),
            'capture_strategy'  => $this->captureStrategy(),
        ]);
    }
}
