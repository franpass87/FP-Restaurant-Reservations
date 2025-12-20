<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use function array_slice;
use function current_time;
use function is_array;

/**
 * Formatta i record di pagamento Stripe.
 * Estratto da StripeService per migliorare la manutenibilità.
 */
final class StripePaymentFormatter
{
    /**
     * Formatta un record di pagamento per la risposta.
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function formatPaymentRecord(array $record): array
    {
        $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];

        return [
            'id'            => (int) ($record['id'] ?? 0),
            'reservation_id'=> (int) ($record['reservation_id'] ?? 0),
            'provider'     => (string) ($record['provider'] ?? ''),
            'type'          => (string) ($record['type'] ?? ''),
            'amount'        => (float) ($record['amount'] ?? 0),
            'currency'      => (string) ($record['currency'] ?? ''),
            'status'        => (string) ($record['status'] ?? ''),
            'external_id'   => (string) ($record['external_id'] ?? ''),
            'client_secret' => $meta['client_secret'] ?? null,
            'meta'          => $meta,
        ];
    }

    /**
     * Unisce i metadati esistenti con nuovi dati.
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function mergeMeta(array $record, array $payload, string $context = 'intent'): array
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
}















