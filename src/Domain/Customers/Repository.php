<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Customers;

use DateTimeImmutable;
use wpdb;
use function current_time;
use function is_array;
use function trim;

final class Repository
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'fp_customers';
    }

    public function findByEmail(string $email): ?Model
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE email = %s', $email),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $model          = new Model();
        $model->id      = (int) $row['id'];
        $model->email   = (string) $row['email'];
        $model->firstName = (string) ($row['first_name'] ?? '');
        $model->lastName  = (string) ($row['last_name'] ?? '');
        $model->lang    = isset($row['lang']) ? (string) $row['lang'] : '';
        $model->phone   = isset($row['phone']) ? (string) $row['phone'] : '';
        $model->name    = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        $model->marketingConsent = ((int) ($row['marketing_consent'] ?? 0)) === 1;
        $model->profilingConsent = ((int) ($row['profiling_consent'] ?? 0)) === 1;
        $model->consentTimestamp = isset($row['consent_ts']) ? (string) $row['consent_ts'] : null;
        $model->consentVersion   = isset($row['consent_version']) ? (string) $row['consent_version'] : null;

        return $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(string $email, array $data): int
    {
        $existing = $this->findByEmail($email);
        $payload  = array_merge([
            'first_name'        => '',
            'last_name'         => '',
            'email'             => $email,
            'phone'             => '',
            'lang'              => null,
            'marketing_consent' => 0,
            'profiling_consent' => 0,
            'consent_ts'        => current_time('mysql'),
            'consent_version'   => null,
            'updated_at'        => current_time('mysql'),
        ], $data);

        $payload['marketing_consent'] = (int) (!empty($payload['marketing_consent']));
        $payload['profiling_consent'] = (int) (!empty($payload['profiling_consent']));

        if (!isset($payload['consent_ts']) || $payload['consent_ts'] === '') {
            $payload['consent_ts'] = current_time('mysql');
        }

        if (isset($payload['consent_version'])) {
            $payload['consent_version'] = $payload['consent_version'] !== ''
                ? (string) $payload['consent_version']
                : null;
        }

        if ($existing !== null) {
            $this->wpdb->update(
                $this->tableName(),
                $payload,
                ['id' => $existing->id]
            );

            return $existing->id;
        }

        $payload['created_at'] = current_time('mysql');

        $result = $this->wpdb->insert($this->tableName(), $payload);
        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error ?: 'Unable to save customer');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function anonymizeCustomer(int $customerId, string $placeholderEmail): int
    {
        $result = $this->wpdb->update(
            $this->tableName(),
            [
                'first_name'        => '',
                'last_name'         => '',
                'email'             => $placeholderEmail,
                'phone'             => null,
                'lang'              => null,
                'marketing_consent' => 0,
                'profiling_consent' => 0,
                'consent_ts'        => null,
                'consent_version'   => null,
                'updated_at'        => current_time('mysql'),
            ],
            ['id' => $customerId]
        );

        return $result === false ? 0 : (int) $result;
    }

    public function placeholderEmail(int $customerId): string
    {
        return 'deleted-' . $customerId . '@fp-reservations.invalid';
    }

    public function anonymizeInactive(DateTimeImmutable $cutoff, string $reservationsTable): int
    {
        $cutoffDate     = $cutoff->format('Y-m-d');
        $cutoffDateTime = $cutoff->format('Y-m-d H:i:s');

        $sql = $this->wpdb->prepare(
            'SELECT c.id FROM ' . $this->tableName() . ' c '
            . 'LEFT JOIN ' . $reservationsTable . ' r ON r.customer_id = c.id AND r.date >= %s '
            . 'WHERE r.id IS NULL AND c.updated_at < %s',
            $cutoffDate,
            $cutoffDateTime
        );

        $ids = $this->wpdb->get_col($sql);
        if (!is_array($ids) || $ids === []) {
            return 0;
        }

        $count = 0;
        foreach ($ids as $rawId) {
            $customerId = (int) $rawId;
            if ($customerId <= 0) {
                continue;
            }

            $result = $this->anonymizeCustomer($customerId, $this->placeholderEmail($customerId));
            if ($result > 0) {
                $count += $result;
            }
        }

        return $count;
    }
}
