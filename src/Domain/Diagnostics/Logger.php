<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

use wpdb;
use function current_time;
use const ARRAY_A;
use function is_numeric;
use function is_string;
use function wp_json_encode;

final class Logger
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'fp_resv_logs';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $channel, string $message, array $context = []): void
    {
        $channel = $channel !== '' ? $channel : 'general';
        $payload = [
            'channel'        => $channel,
            'message'        => $message,
            'context_json'   => $this->encodeContext($context),
            'reservation_id' => $this->extractNumeric($context, 'reservation_id'),
            'customer_id'    => $this->extractNumeric($context, 'customer_id'),
            'created_at'     => current_time('mysql'),
        ];

        $this->wpdb->insert($this->tableName(), $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, $limit);
        $sql   = $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' ORDER BY id DESC LIMIT %d', $limit);

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private function encodeContext(array $context): ?string
    {
        if ($context === []) {
            return null;
        }

        $encoded = wp_json_encode($context);

        return is_string($encoded) ? $encoded : null;
    }

    private function extractNumeric(array $context, string $key): ?int
    {
        if (!isset($context[$key])) {
            return null;
        }

        $value = $context[$key];
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
