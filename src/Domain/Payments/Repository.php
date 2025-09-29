<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use wpdb;
use function current_time;
use function is_array;
use function json_decode;
use function wp_json_encode;

final class Repository
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'fp_payments';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $defaults = [
            'reservation_id' => null,
            'provider'       => 'stripe',
            'type'           => 'authorization',
            'amount'         => 0.0,
            'currency'       => 'EUR',
            'status'         => 'pending',
            'external_id'    => null,
            'meta_json'      => null,
            'created_at'     => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ];

        $payload = array_merge($defaults, $data);

        $result = $this->wpdb->insert($this->tableName(), $payload);
        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error ?: 'Unable to create payment record.');
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        if (isset($row['meta_json'])) {
            $meta = json_decode((string) $row['meta_json'], true);
            if (is_array($meta)) {
                $row['meta'] = $meta;
            }
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReservation(int $reservationId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE reservation_id = %d ORDER BY id DESC LIMIT 1', $reservationId),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        if (isset($row['meta_json'])) {
            $meta = json_decode((string) $row['meta_json'], true);
            if (is_array($meta)) {
                $row['meta'] = $meta;
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateStatus(int $id, string $status, array $meta = []): void
    {
        $payload = [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($meta !== []) {
            $payload['meta_json'] = wp_json_encode($meta);
        }

        $result = $this->wpdb->update($this->tableName(), $payload, ['id' => $id]);
        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error ?: 'Unable to update payment status.');
        }
    }
}
