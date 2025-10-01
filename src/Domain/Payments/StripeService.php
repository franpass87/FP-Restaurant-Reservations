<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use FP\Resv\Domain\Settings\Options;
use RuntimeException;
use function __;
use WP_Error;
use function apply_filters;
use function current_time;
use function home_url;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function preg_match;
use function sanitize_text_field;
use function sprintf;
use function strtolower;
use function strtoupper;
use function wp_json_encode;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_response_message;

final class StripeService
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_PAID       = 'paid';
    public const STATUS_REFUNDED   = 'refunded';
    public const STATUS_VOID       = 'void';

    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly Options $options,
        private readonly Repository $repository
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
     * @param array<string, mixed> $reservation
     */
    public function createReservationIntent(int $reservationId, array $reservation, ?float $amountOverride = null): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException(__('I pagamenti Stripe sono disabilitati.', 'fp-restaurant-reservations'));
        }

        $amount   = $amountOverride ?? $this->calculateReservationAmount($reservation, $reservationId);
        $amount   = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException(__('L\'importo del pagamento non è valido.', 'fp-restaurant-reservations'));
        }

        $currency = strtoupper((string) ($reservation['currency'] ?? $this->currency()));
        if ($currency === '') {
            $currency = $this->currency();
        }

        $intent = $this->request('POST', '/payment_intents', $this->buildIntentPayload($reservationId, $reservation, $amount, $currency));

        $status = $this->mapIntentStatus($intent);
        $meta   = $this->buildMetaSnapshot($intent, $amount);

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
            throw new RuntimeException(__('Impossibile salvare il record di pagamento.', 'fp-restaurant-reservations'));
        }

        return $this->formatPaymentRecord($record);
    }

    public function refreshPayment(int $paymentId): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException(__('Pagamento non trovato.', 'fp-restaurant-reservations'));
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException(__('ID dell\'intent Stripe mancante.', 'fp-restaurant-reservations'));
        }

        $intent = $this->request('GET', '/payment_intents/' . rawurlencode($intentId));

        $meta   = $this->mergeMeta($record, $intent);
        $status = $this->mapIntentStatus($intent);

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException(__('Impossibile ricaricare il pagamento aggiornato.', 'fp-restaurant-reservations'));
        }

        return $this->formatPaymentRecord($updated);
    }

    public function capturePayment(int $paymentId): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException(__('Pagamento non trovato.', 'fp-restaurant-reservations'));
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException(__('ID dell\'intent Stripe mancante.', 'fp-restaurant-reservations'));
        }

        $intent = $this->request('POST', '/payment_intents/' . rawurlencode($intentId) . '/capture');

        $meta   = $this->mergeMeta($record, $intent);
        $status = $this->mapIntentStatus($intent);

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException(__('Impossibile ricaricare il pagamento acquisito.', 'fp-restaurant-reservations'));
        }

        return $this->formatPaymentRecord($updated);
    }

    public function voidPayment(int $paymentId, string $reason = 'requested_by_customer'): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException(__('Pagamento non trovato.', 'fp-restaurant-reservations'));
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException(__('ID dell\'intent Stripe mancante.', 'fp-restaurant-reservations'));
        }

        $intent = $this->request('POST', '/payment_intents/' . rawurlencode($intentId) . '/cancel', [
            'cancellation_reason' => $reason,
        ]);

        $meta   = $this->mergeMeta($record, $intent);
        $status = $this->mapIntentStatus($intent);

        $this->repository->updateStatus((int) $record['id'], $status, $meta);

        $updated = $this->repository->find((int) $record['id']);
        if ($updated === null) {
            throw new RuntimeException(__('Impossibile ricaricare il pagamento annullato.', 'fp-restaurant-reservations'));
        }

        return $this->formatPaymentRecord($updated);
    }

    public function refundPayment(int $paymentId, ?float $amount = null): array
    {
        $record = $this->repository->find($paymentId);
        if ($record === null) {
            throw new RuntimeException(__('Pagamento non trovato.', 'fp-restaurant-reservations'));
        }

        $intentId = (string) ($record['external_id'] ?? '');
        if ($intentId === '') {
            throw new RuntimeException(__('ID dell\'intent Stripe mancante.', 'fp-restaurant-reservations'));
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
            throw new RuntimeException(__('Impossibile ricaricare il pagamento rimborsato.', 'fp-restaurant-reservations'));
        }

        return $this->formatPaymentRecord($updated);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function formatPaymentRecord(array $record): array
    {
        $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];

        return [
            'id'               => (int) $record['id'],
            'reservation_id'   => (int) ($record['reservation_id'] ?? 0),
            'status'           => (string) ($record['status'] ?? self::STATUS_PENDING),
            'strategy'         => (string) ($record['type'] ?? $this->captureStrategy()),
            'amount'           => (float) ($record['amount'] ?? 0),
            'currency'         => strtoupper((string) ($record['currency'] ?? $this->currency())),
            'provider'         => (string) ($record['provider'] ?? 'stripe'),
            'external_id'      => (string) ($record['external_id'] ?? ''),
            'client_secret'    => (string) ($meta['client_secret'] ?? ''),
            'intent_status'    => (string) ($meta['intent_status'] ?? ''),
            'mode'             => $this->mode(),
            'capture_strategy' => $this->captureStrategy(),
            'meta'             => $meta,
        ];
    }

    /**
     * @param array<string, mixed> $reservation
     * @return array<string, mixed>
     */
    private function buildIntentPayload(int $reservationId, array $reservation, float $amount, string $currency): array
    {
        $amountMinor = $this->toMinorUnits($amount, $currency);
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
     * @param array<string, mixed> $intent
     */
    private function buildMetaSnapshot(array $intent, float $amount): array
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
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     */
    private function mergeMeta(array $record, array $payload, string $context = 'intent'): array
    {
        $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];

        if ($context === 'intent') {
            $meta['client_secret'] = $payload['client_secret'] ?? ($meta['client_secret'] ?? null);
            $meta['intent_status'] = $payload['status'] ?? ($meta['intent_status'] ?? null);
        } else {
            $meta['last_refund'] = $payload;
        }

        $meta['latest_' . $context] = $payload;

        $logs   = is_array($meta['logs'] ?? null) ? $meta['logs'] : [];
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'status'    => $payload['status'] ?? null,
            'context'   => $context,
        ];
        $meta['logs'] = array_slice($logs, -20);

        return $meta;
    }

    /**
     * @param array<string, mixed>|string $intent
     */
    private function mapIntentStatus(array|string $intent): string
    {
        $status = is_array($intent) ? (string) ($intent['status'] ?? '') : (string) $intent;

        return match ($status) {
            'requires_capture' => self::STATUS_AUTHORIZED,
            'succeeded'        => self::STATUS_PAID,
            'canceled'         => self::STATUS_VOID,
            default            => self::STATUS_PENDING,
        };
    }

    private function toMinorUnits(float $amount, string $currency): int
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
     * @param array<string, mixed> $body
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $secret = $this->secretKey();
        if ($secret === '') {
            throw new RuntimeException(__('La chiave segreta di Stripe è mancante.', 'fp-restaurant-reservations'));
        }

        $headers = [
            'Authorization'  => 'Bearer ' . $secret,
            'Stripe-Version' => '2022-11-15',
        ];

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];

        if ($body !== []) {
            $args['body'] = $this->encodeBody($body);
        }

        $response = wp_remote_request(self::API_BASE . $path, $args);
        if ($response instanceof WP_Error) {
            throw new RuntimeException(sprintf(__('Richiesta a Stripe non riuscita: %s', 'fp-restaurant-reservations'), $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException(__('Le API di Stripe hanno restituito una risposta non valida.', 'fp-restaurant-reservations'));
        }

        if ($code >= 400) {
            $message = $data['error']['message'] ?? wp_remote_retrieve_response_message($response);
            throw new RuntimeException(sprintf(__('Errore API Stripe (%d): %s', 'fp-restaurant-reservations'), $code, (string) $message));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function encodeBody(array $body): string
    {
        $fields = [];
        foreach ($body as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $fields[] = $key . '=' . rawurlencode((string) $value);
        }

        return implode('&', $fields);
    }
}
