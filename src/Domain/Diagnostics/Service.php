<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

use DateTimeImmutable;
use FP\Resv\Core\Helpers;
use FP\Resv\Domain\Diagnostics\ChannelsConfig;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use wpdb;
use function __;
use function array_map;
use function array_merge;
use function array_slice;
use function array_values;
use function ceil;
use function fputcsv;
use function fopen;
use function gmdate;
use function get_option;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function number_format;
use function rewind;
use function sanitize_textarea_field;
use function sprintf;
use function stream_get_contents;
use function str_contains;
use function strtolower;
use function strtoupper;
use function strtotime;
use function trim;
use function ucfirst;
use function usort;
use function wp_date;
use function wp_kses_post;

final class Service
{
    private const MAX_EXPORT_ROWS = 2000;

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly PaymentsRepository $payments,
        private readonly ReservationsRepository $reservations
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getChannels(): array
    {
        $channels = ChannelsConfig::getChannels();
        ksort($channels);

        return $channels;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function getLogs(string $channel, array $args = []): array
    {
        $channel = $this->normalizeChannel($channel);
        if ($channel === null) {
            return [
                'channel'    => null,
                'columns'    => [],
                'entries'    => [],
                'pagination' => $this->buildPagination(0, 1, 25),
            ];
        }

        return match ($channel) {
            'email'    => $this->queryEmailLogs($args, false),
            'webhooks' => $this->queryWebhookLogs($args, false),
            'stripe'   => $this->queryStripeLogs($args, false),
            'api'      => $this->queryApiLogs($args, false),
            'queue'    => $this->queryQueueLogs($args, false),
            default    => [
                'channel'    => $channel,
                'columns'    => ChannelsConfig::getChannels()[$channel]['columns'],
                'entries'    => [],
                'pagination' => $this->buildPagination(0, 1, 25),
            ],
        };
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, string>
     */
    public function export(string $channel, array $args = []): array
    {
        $channel = $this->normalizeChannel($channel);
        if ($channel === null) {
            return [
                'filename'  => 'fp-resv-diagnostics.csv',
                'mime_type' => 'text/csv',
                'format'    => 'csv',
                'delimiter' => ';',
                'content'   => '',
            ];
        }

        $result = match ($channel) {
            'email'    => $this->queryEmailLogs($args, true),
            'webhooks' => $this->queryWebhookLogs($args, true),
            'stripe'   => $this->queryStripeLogs($args, true),
            'api'      => $this->queryApiLogs($args, true),
            'queue'    => $this->queryQueueLogs($args, true),
            default    => [
                'channel' => $channel,
                'columns' => ChannelsConfig::getChannels()[$channel]['columns'],
                'entries' => [],
            ],
        };

        $columns = array_map(static function (array $column): string {
            return (string) ($column['label'] ?? $column['key']);
        }, $result['columns']);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [
                'filename'  => 'fp-resv-diagnostics.csv',
                'mime_type' => 'text/csv',
                'format'    => 'csv',
                'delimiter' => ';',
                'content'   => '',
            ];
        }

        fputcsv($stream, $columns, ';');

        foreach ($result['entries'] as $entry) {
            $row = [];
            foreach ($result['columns'] as $column) {
                $key   = (string) $column['key'];
                $row[] = isset($entry[$key]) ? $this->stringify($entry[$key]) : '';
            }

            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $content = stream_get_contents($stream);

        return [
            'filename'  => sprintf('fp-resv-%s-logs-%s.csv', $channel, gmdate('Ymd-His')),
            'mime_type' => 'text/csv',
            'format'    => 'csv',
            'delimiter' => ';',
            'content'   => is_string($content) ? $content : '',
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function queryEmailLogs(array $args, bool $export): array
    {
        $table = $this->wpdb->prefix . 'fp_mail_log';

        $page    = $this->resolvePage($args, $export);
        $perPage = $this->resolvePerPage($args, $export);
        $status  = $this->filterStatus('email', $args);
        $range   = $this->normalizeRange($args);
        $search  = $this->normalizeSearch($args);

        $where  = [];
        $params = [];
        if ($status !== null) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ($range['from'] !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like      = '%' . $this->wpdb->esc_like($search) . '%';
            $searchSql = [];
            foreach (['to_emails', 'subject', 'first_line', 'error', 'body'] as $column) {
                $searchSql[] = $column . ' LIKE %s';
                $params[]    = $like;
            }

            $where[] = '(' . implode(' OR ', $searchSql) . ')';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM ' . $table . $whereSql;
        $total    = (int) ($params !== []
            ? $this->wpdb->get_var($this->wpdb->prepare($countSql, $params))
            : $this->wpdb->get_var($countSql));

        $query = 'SELECT id, reservation_id, to_emails, subject, first_line, status, error, content_type, body, created_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY created_at DESC';

        if (!$export) {
            $query .= ' LIMIT %d OFFSET %d';
            $queryParams = $params;
            $queryParams[] = $perPage;
            $queryParams[] = ($page - 1) * $perPage;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $perPage, ($page - 1) * $perPage), ARRAY_A);
        } else {
            $limit = min(self::MAX_EXPORT_ROWS, $perPage);
            $query .= ' LIMIT %d';
            $queryParams = $params;
            $queryParams[] = $limit;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $limit), ARRAY_A);
        }

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $body       = (string) ($row['body'] ?? '');
                $hasPreview = trim($body) !== '';

                $entries[] = [
                    'id'                 => isset($row['id']) ? (int) $row['id'] : null,
                    'created_at'         => (string) ($row['created_at'] ?? ''),
                    'status'             => strtolower((string) ($row['status'] ?? '')),
                    'recipient'          => (string) ($row['to_emails'] ?? ''),
                    'subject'            => (string) ($row['subject'] ?? ''),
                    'excerpt'            => (string) ($row['first_line'] ?? ''),
                    'preview'            => $hasPreview
                        ? __('Disponibile', 'fp-restaurant-reservations')
                        : __('Non disponibile', 'fp-restaurant-reservations'),
                    'preview_id'         => $hasPreview && isset($row['id']) ? (int) $row['id'] : null,
                    'preview_available'  => $hasPreview,
                    'error'              => (string) ($row['error'] ?? ''),
                    'reservation_id'     => isset($row['reservation_id']) ? (int) $row['reservation_id'] : null,
                ];
            }
        }

        return [
            'channel'    => 'email',
            'columns'    => ChannelsConfig::getChannels()['email']['columns'],
            'entries'    => $entries,
            'pagination' => $this->buildPagination($total, $page, $perPage),
        ];
    }

    public function getEmailPreview(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $table = $this->wpdb->prefix . 'fp_mail_log';
        $row   = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT id, reservation_id, to_emails, subject, status, content_type, body, created_at'
                . ' FROM ' . $table . ' WHERE id = %d LIMIT 1',
                $id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $contentType = $this->normalizeContentType((string) ($row['content_type'] ?? 'text/html'));
        $body        = (string) ($row['body'] ?? '');

        if ($contentType === 'text/html') {
            $body = wp_kses_post($body);
        } else {
            $body = sanitize_textarea_field($body);
        }

        $status = strtolower((string) ($row['status'] ?? ''));

        return [
            'id'                    => (int) ($row['id'] ?? 0),
            'reservation_id'        => isset($row['reservation_id']) ? (int) $row['reservation_id'] : null,
            'recipient'             => (string) ($row['to_emails'] ?? ''),
            'subject'               => (string) ($row['subject'] ?? ''),
            'status'                => $status,
            'status_label'          => $this->emailStatusLabel($status),
            'content_type'          => $contentType,
            'body'                  => $body,
            'created_at'            => (string) ($row['created_at'] ?? ''),
            'created_at_formatted'  => $this->formatTimestamp((string) ($row['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function queryWebhookLogs(array $args, bool $export): array
    {
        $range  = $this->normalizeRange($args);
        $status = $this->filterStatus('webhooks', $args);
        $search = $this->normalizeSearch($args);
        $page   = $this->resolvePage($args, $export);
        $perPage = $this->resolvePerPage($args, $export);

        $entries = array_merge(
            $this->collectBrevoLogs($range, $status, $search),
            $this->collectStripeWebhookAudits($range, $status, $search),
            $this->collectCalendarAudits($range, $status, $search)
        );

        $entries = array_values($entries);

        usort($entries, static function (array $a, array $b): int {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        $total = count($entries);

        if (!$export) {
            $offset  = ($page - 1) * $perPage;
            $entries = array_slice($entries, $offset, $perPage);
        } else {
            $entries = array_slice($entries, 0, min(self::MAX_EXPORT_ROWS, $perPage));
        }

        return [
            'channel'    => 'webhooks',
            'columns'    => ChannelsConfig::getChannels()['webhooks']['columns'],
            'entries'    => $entries,
            'pagination' => $this->buildPagination($total, $page, $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function queryStripeLogs(array $args, bool $export): array
    {
        $table = $this->payments->tableName();

        $range  = $this->normalizeRange($args);
        $status = $this->filterStatus('stripe', $args);
        $search = $this->normalizeSearch($args);
        $page   = $this->resolvePage($args, $export);
        $perPage = $this->resolvePerPage($args, $export);

        $where  = [];
        $params = [];
        if ($status !== null) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ($range['from'] !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like      = '%' . $this->wpdb->esc_like($search) . '%';
            $searchSql = [];
            foreach (['external_id', 'type', 'meta_json'] as $column) {
                $searchSql[] = $column . ' LIKE %s';
                $params[]    = $like;
            }

            $where[] = '(' . implode(' OR ', $searchSql) . ')';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM ' . $table . $whereSql;
        $total    = (int) ($params !== []
            ? $this->wpdb->get_var($this->wpdb->prepare($countSql, $params))
            : $this->wpdb->get_var($countSql));

        $query = 'SELECT id, reservation_id, type, status, amount, currency, external_id, meta_json, created_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY created_at DESC';

        if (!$export) {
            $query .= ' LIMIT %d OFFSET %d';
            $queryParams = $params;
            $queryParams[] = $perPage;
            $queryParams[] = ($page - 1) * $perPage;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $perPage, ($page - 1) * $perPage), ARRAY_A);
        } else {
            $limit = min(self::MAX_EXPORT_ROWS, $perPage);
            $query .= ' LIMIT %d';
            $queryParams = $params;
            $queryParams[] = $limit;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $limit), ARRAY_A);
        }

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $meta = '';
                if (isset($row['meta_json']) && is_string($row['meta_json']) && $row['meta_json'] !== '') {
                    $decoded = json_decode($row['meta_json'], true);
                    if (is_array($decoded)) {
                        $meta = Helpers::substr(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 180);
                    } else {
                        $meta = Helpers::substr($row['meta_json'], 0, 180);
                    }
                }

                $entries[] = [
                    'id'            => isset($row['id']) ? (int) $row['id'] : null,
                    'created_at'    => (string) ($row['created_at'] ?? ''),
                    'type'          => strtolower((string) ($row['type'] ?? '')),
                    'status'        => strtolower((string) ($row['status'] ?? '')),
                    'amount'        => isset($row['amount']) ? number_format((float) $row['amount'], 2, ',', '.') : '0,00',
                    'currency'      => strtoupper((string) ($row['currency'] ?? '')),
                    'external_id'   => (string) ($row['external_id'] ?? ''),
                    'meta'          => $meta,
                    'reservation_id'=> isset($row['reservation_id']) ? (int) $row['reservation_id'] : null,
                ];
            }
        }

        return [
            'channel'    => 'stripe',
            'columns'    => ChannelsConfig::getChannels()['stripe']['columns'],
            'entries'    => $entries,
            'pagination' => $this->buildPagination($total, $page, $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function queryApiLogs(array $args, bool $export): array
    {
        $table = $this->reservations->auditTable();

        $range  = $this->normalizeRange($args);
        $status = $this->filterStatus('api', $args);
        $search = $this->normalizeSearch($args);
        $page   = $this->resolvePage($args, $export);
        $perPage = $this->resolvePerPage($args, $export);

        $where  = [
            "(action LIKE 'rest_%' OR action LIKE 'api_%' OR action LIKE 'webhook_%' OR action LIKE 'http_%')",
        ];
        $params = [];

        if ($range['from'] !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like      = '%' . $this->wpdb->esc_like($search) . '%';
            $where[]   = '(action LIKE %s OR entity LIKE %s OR actor_role LIKE %s OR ip LIKE %s OR before_json LIKE %s OR after_json LIKE %s)';
            for ($i = 0; $i < 6; $i++) {
                $params[] = $like;
            }
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM ' . $table . $whereSql;
        $total    = (int) ($params !== []
            ? $this->wpdb->get_var($this->wpdb->prepare($countSql, $params))
            : $this->wpdb->get_var($countSql));

        $query = 'SELECT id, action, entity, actor_role, actor_id, ip, before_json, after_json, created_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY created_at DESC';

        if (!$export) {
            $query .= ' LIMIT %d OFFSET %d';
            $queryParams = $params;
            $queryParams[] = $perPage;
            $queryParams[] = ($page - 1) * $perPage;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $perPage, ($page - 1) * $perPage), ARRAY_A);
        } else {
            $limit = min(self::MAX_EXPORT_ROWS, $perPage);
            $query .= ' LIMIT %d';
            $queryParams = $params;
            $queryParams[] = $limit;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $limit), ARRAY_A);
        }

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $action = (string) ($row['action'] ?? '');
                $severity = 'info';
                if (str_contains($action, 'error') || str_contains($action, 'failed')) {
                    $severity = 'error';
                } elseif (str_contains($action, 'warning') || str_contains($action, 'retry')) {
                    $severity = 'warning';
                }

                if ($status !== null && $severity !== $status) {
                    continue;
                }

                $details = '';
                $payload = $row['after_json'] ?? $row['before_json'] ?? '';
                if (is_string($payload) && $payload !== '') {
                    $decoded = json_decode($payload, true);
                    if (is_array($decoded)) {
                        $details = Helpers::substr(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 180);
                    } else {
                        $details = Helpers::substr($payload, 0, 180);
                    }
                }

                $entries[] = [
                    'id'         => isset($row['id']) ? (int) $row['id'] : null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'action'     => $action,
                    'entity'     => (string) ($row['entity'] ?? ''),
                    'actor'      => (string) ($row['actor_role'] ?? ''),
                    'ip'         => (string) ($row['ip'] ?? ''),
                    'status'     => $severity,
                    'details'    => $details,
                ];
            }
        }

        $totalEntries = count($entries);
        if (!$export) {
            $offset  = ($page - 1) * $perPage;
            $entries = array_slice($entries, $offset, $perPage);
        } else {
            $entries = array_slice($entries, 0, min(self::MAX_EXPORT_ROWS, $perPage));
        }

        return [
            'channel'    => 'api',
            'columns'    => ChannelsConfig::getChannels()['api']['columns'],
            'entries'    => $entries,
            'pagination' => $this->buildPagination($totalEntries, $page, $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function queryQueueLogs(array $args, bool $export): array
    {
        $table = $this->wpdb->prefix . 'fp_postvisit_jobs';

        $range  = $this->normalizeRange($args);
        $status = $this->filterStatus('queue', $args);
        $search = $this->normalizeSearch($args);
        $page   = $this->resolvePage($args, $export);
        $perPage = $this->resolvePerPage($args, $export);

        $where  = [];
        $params = [];

        if ($status !== null) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ($range['from'] !== null) {
            $where[]  = 'updated_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'updated_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(channel LIKE %s OR last_error LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM ' . $table . $whereSql;
        $total    = (int) ($params !== []
            ? $this->wpdb->get_var($this->wpdb->prepare($countSql, $params))
            : $this->wpdb->get_var($countSql));

        $query = 'SELECT id, reservation_id, channel, status, run_at, last_error, updated_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY updated_at DESC';

        if (!$export) {
            $query .= ' LIMIT %d OFFSET %d';
            $queryParams = $params;
            $queryParams[] = $perPage;
            $queryParams[] = ($page - 1) * $perPage;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $perPage, ($page - 1) * $perPage), ARRAY_A);
        } else {
            $limit = min(self::MAX_EXPORT_ROWS, $perPage);
            $query .= ' LIMIT %d';
            $queryParams = $params;
            $queryParams[] = $limit;

            $rows = $params !== []
                ? $this->wpdb->get_results($this->wpdb->prepare($query, $queryParams), ARRAY_A)
                : $this->wpdb->get_results($this->wpdb->prepare($query, $limit), ARRAY_A);
        }

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $entries[] = [
                    'id'             => isset($row['id']) ? (int) $row['id'] : null,
                    'updated_at'     => (string) ($row['updated_at'] ?? ''),
                    'run_at'         => (string) ($row['run_at'] ?? ''),
                    'channel'        => (string) ($row['channel'] ?? ''),
                    'status'         => strtolower((string) ($row['status'] ?? '')),
                    'reservation_id' => isset($row['reservation_id']) ? (int) $row['reservation_id'] : null,
                    'error'          => (string) ($row['last_error'] ?? ''),
                ];
            }
        }

        return [
            'channel'    => 'queue',
            'columns'    => ChannelsConfig::getChannels()['queue']['columns'],
            'entries'    => $entries,
            'pagination' => $this->buildPagination($total, $page, $perPage),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectBrevoLogs(array $range, ?string $status, ?string $search): array
    {
        $table = $this->wpdb->prefix . 'fp_brevo_log';

        $where  = [];
        $params = [];

        if ($status !== null && $status !== 'info') {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ($range['from'] !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(action LIKE %s OR payload_snippet LIKE %s OR error LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $sql = 'SELECT id, reservation_id, action, payload_snippet, status, error, created_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY created_at DESC LIMIT 500';

        $rows = $params !== []
            ? $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A)
            : $this->wpdb->get_results($sql, ARRAY_A);

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $entries[] = [
                    'id'         => isset($row['id']) ? (int) $row['id'] : null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'status'     => strtolower((string) ($row['status'] ?? 'success')),
                    'source'     => 'Brevo',
                    'action'     => (string) ($row['action'] ?? ''),
                    'summary'    => (string) ($row['payload_snippet'] ?? ''),
                    'error'      => (string) ($row['error'] ?? ''),
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectStripeWebhookAudits(array $range, ?string $status, ?string $search): array
    {
        $table = $this->reservations->auditTable();

        $where = [
            "(action LIKE 'stripe_webhook%' OR entity = 'payment')",
        ];
        $params = [];

        if ($range['from'] !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(action LIKE %s OR before_json LIKE %s OR after_json LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $sql = 'SELECT id, action, before_json, after_json, created_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY created_at DESC LIMIT 200';

        $rows = $params !== []
            ? $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A)
            : $this->wpdb->get_results($sql, ARRAY_A);

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $action = (string) ($row['action'] ?? '');
                $severity = str_contains($action, 'error') ? 'error' : 'info';
                if ($status !== null && $status !== $severity) {
                    continue;
                }

                $payload = $row['after_json'] ?? $row['before_json'] ?? '';
                $summary = '';
                if (is_string($payload) && $payload !== '') {
                    $decoded = json_decode($payload, true);
                    if (is_array($decoded)) {
                        $summary = Helpers::substr(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 180);
                    } else {
                        $summary = Helpers::substr($payload, 0, 180);
                    }
                }

                $entries[] = [
                    'id'         => isset($row['id']) ? (int) $row['id'] : null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'status'     => $severity,
                    'source'     => 'Stripe',
                    'action'     => $action,
                    'summary'    => $summary,
                    'error'      => $severity === 'error' ? $summary : '',
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectCalendarAudits(array $range, ?string $status, ?string $search): array
    {
        $table = $this->reservations->auditTable();

        $where = [
            "(entity = 'calendar' OR action LIKE 'calendar_%')",
        ];
        $params = [];

        if ($range['from'] !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $range['from'] . ' 00:00:00';
        }

        if ($range['to'] !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $range['to'] . ' 23:59:59';
        }

        if ($search !== null) {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(action LIKE %s OR before_json LIKE %s OR after_json LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $sql = 'SELECT id, action, before_json, after_json, created_at'
            . ' FROM ' . $table . $whereSql
            . ' ORDER BY created_at DESC LIMIT 200';

        $rows = $params !== []
            ? $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A)
            : $this->wpdb->get_results($sql, ARRAY_A);

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $action = (string) ($row['action'] ?? '');
                $severity = str_contains($action, 'error') || str_contains($action, 'failed') ? 'error' : 'info';
                if ($status !== null && $status !== $severity) {
                    continue;
                }

                $payload = $row['after_json'] ?? $row['before_json'] ?? '';
                $summary = '';
                if (is_string($payload) && $payload !== '') {
                    $decoded = json_decode($payload, true);
                    if (is_array($decoded)) {
                        $summary = Helpers::substr(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 180);
                    } else {
                        $summary = Helpers::substr($payload, 0, 180);
                    }
                }

                $entries[] = [
                    'id'         => isset($row['id']) ? (int) $row['id'] : null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'status'     => $severity,
                    'source'     => 'Google Calendar',
                    'action'     => $action,
                    'summary'    => $summary,
                    'error'      => $severity === 'error' ? $summary : '',
                ];
            }
        }

        return $entries;
    }

    private function normalizeContentType(string $contentType): string
    {
        $normalized = strtolower(trim($contentType));

        if ($normalized === '') {
            return 'text/html';
        }

        if (str_contains($normalized, 'plain')) {
            return 'text/plain';
        }

        if (str_contains($normalized, 'html')) {
            return 'text/html';
        }

        return 'text/html';
    }

    private function emailStatusLabel(string $status): string
    {
        return match ($status) {
            'sent'   => __('Inviata', 'fp-restaurant-reservations'),
            'failed' => __('Errore', 'fp-restaurant-reservations'),
            default  => ucfirst($status),
        };
    }

    private function formatTimestamp(string $timestamp): string
    {
        $timestamp = trim($timestamp);
        if ($timestamp === '') {
            return '';
        }

        $time = strtotime($timestamp);
        if ($time === false) {
            return $timestamp;
        }

        $dateFormat = (string) get_option('date_format', 'Y-m-d');
        $timeFormat = (string) get_option('time_format', 'H:i');

        return wp_date(trim($dateFormat . ' ' . $timeFormat), $time);
    }

    private function normalizeChannel(string $channel): ?string
    {
        $key = strtolower(trim($channel));
        $channels = ChannelsConfig::getChannels();
        if ($key === '' || !isset($channels[$key])) {
            return null;
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function resolvePage(array $args, bool $export): int
    {
        if ($export) {
            return 1;
        }

        $page = (int) ($args['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function resolvePerPage(array $args, bool $export): int
    {
        $perPage = (int) ($args['per_page'] ?? 25);
        if ($perPage < 1) {
            $perPage = 25;
        }

        return $export ? min(self::MAX_EXPORT_ROWS, max(1, $perPage)) : min(100, $perPage);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array{from: string|null, to: string|null}
     */
    private function normalizeRange(array $args): array
    {
        $from = $this->normalizeDate($args['from'] ?? null);
        $to   = $this->normalizeDate($args['to'] ?? null);

        return ['from' => $from, 'to' => $to];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function normalizeSearch(array $args): ?string
    {
        if (!isset($args['search']) || !is_string($args['search'])) {
            return null;
        }

        $search = trim($args['search']);
        if ($search === '') {
            return null;
        }

        return $search;
    }

    private function filterStatus(string $channel, array $args): ?string
    {
        if (!isset($args['status']) || !is_string($args['status'])) {
            return null;
        }

        $status = strtolower(trim($args['status']));
        $channels = ChannelsConfig::getChannels();
        if ($status === '' || !in_array($status, $channels[$channel]['statuses'], true)) {
            return null;
        }

        return $status;
    }

    /**
     * @return array<string, int>
     */
    private function buildPagination(int $total, int $page, int $perPage): array
    {
        $totalPages = $total > 0 ? (int) ceil($total / max(1, $perPage)) : 0;

        return [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
        ];
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : '';
        }

        return (string) $value;
    }
}
