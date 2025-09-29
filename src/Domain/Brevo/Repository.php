<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use wpdb;
use function current_time;
use function is_array;
use function is_string;
use function substr;
use function wp_json_encode;

final class Repository
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    private function logTable(): string
    {
        return $this->wpdb->prefix . 'fp_brevo_log';
    }

    private function jobsTable(): string
    {
        return $this->wpdb->prefix . 'fp_postvisit_jobs';
    }

    public function hasSuccessfulLog(int $reservationId, string $action): bool
    {
        $sql     = 'SELECT id FROM ' . $this->logTable() . ' WHERE reservation_id = %d AND action = %s AND status = %s LIMIT 1';
        $result  = $this->wpdb->get_var($this->wpdb->prepare($sql, $reservationId, $action, 'success'));

        return $result !== null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function log(int $reservationId, string $action, array $payload, string $status, ?string $error = null): void
    {
        $snippet = wp_json_encode($payload);
        if (!is_string($snippet)) {
            $snippet = '';
        }

        $snippet = substr($snippet, 0, 1000);

        $this->wpdb->insert(
            $this->logTable(),
            [
                'reservation_id' => $reservationId,
                'action'         => $action,
                'payload_snippet'=> $snippet,
                'status'         => $status,
                'error'          => $error,
                'created_at'     => current_time('mysql'),
            ]
        );
    }

    public function enqueueFollowUp(int $reservationId, string $runAt, string $channel = 'brevo_followup'): bool
    {
        $sql = 'SELECT id FROM ' . $this->jobsTable() . ' WHERE reservation_id = %d AND channel = %s AND status IN ("pending","processing") LIMIT 1';
        $existing = $this->wpdb->get_var($this->wpdb->prepare($sql, $reservationId, $channel));
        if ($existing !== null) {
            return false;
        }

        $now = current_time('mysql');

        $this->wpdb->insert(
            $this->jobsTable(),
            [
                'reservation_id' => $reservationId,
                'run_at'         => $runAt,
                'status'         => 'pending',
                'channel'        => $channel,
                'last_error'     => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]
        );

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claimDueJobs(string $channel, int $limit = 10): array
    {
        $now = current_time('mysql');
        $sql = 'SELECT * FROM ' . $this->jobsTable() . ' WHERE channel = %s AND status = %s AND run_at <= %s ORDER BY run_at ASC LIMIT ' . (int) $limit;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $channel, 'pending', $now),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function markJobProcessing(int $jobId): bool
    {
        $result = $this->wpdb->update(
            $this->jobsTable(),
            [
                'status'     => 'processing',
                'updated_at' => current_time('mysql'),
            ],
            [
                'id'     => $jobId,
                'status' => 'pending',
            ]
        );

        return $result !== false && $result > 0;
    }

    public function markJobCompleted(int $jobId): void
    {
        $this->wpdb->update(
            $this->jobsTable(),
            [
                'status'     => 'completed',
                'last_error' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $jobId]
        );
    }

    public function markJobFailed(int $jobId, string $error): void
    {
        $this->wpdb->update(
            $this->jobsTable(),
            [
                'status'     => 'failed',
                'last_error' => $error,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $jobId]
        );
    }
}
