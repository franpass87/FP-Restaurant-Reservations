<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use wpdb;
use function array_map;
use function array_values;
use function ceil;
use function fclose;
use function fopen;
use function fputcsv;
use function fwrite;
use function is_array;
use function is_numeric;
use function is_string;
use function ksort;
use function max;
use function preg_match;
use function rewind;
use function sprintf;
use function stream_get_contents;
use function str_replace;
use function strtoupper;
use function substr;
use function trim;

final class Service
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const LOG_TABLES = [
        'mail' => [
            'table'           => 'fp_mail_log',
            'order_by'        => 'created_at',
            'search_columns'  => ['to_emails', 'subject', 'first_line', 'error'],
            'status_column'   => 'status',
        ],
        'brevo' => [
            'table'           => 'fp_brevo_log',
            'order_by'        => 'created_at',
            'search_columns'  => ['action', 'payload_snippet', 'status', 'error'],
            'status_column'   => 'status',
        ],
        'audit' => [
            'table'           => 'fp_audit_log',
            'order_by'        => 'created_at',
            'search_columns'  => ['action', 'entity', 'actor_role', 'ip', 'before_json', 'after_json'],
            'status_column'   => null,
        ],
    ];

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly ReservationsRepository $reservations,
        private readonly PaymentsRepository $payments
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDailySummary(string $startDate, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveRange($startDate, $endDate);

        $buckets = $this->bootstrapDailyBuckets($start, $end);

        $reservationTable = $this->reservations->tableName();
        $sql              = 'SELECT date, status, COUNT(*) AS total_reservations, SUM(party) AS total_covers'
            . ' FROM ' . $reservationTable
            . ' WHERE date BETWEEN %s AND %s'
            . ' GROUP BY date, status';

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $start->format('Y-m-d'), $end->format('Y-m-d')),
            ARRAY_A
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $date   = (string) ($row['date'] ?? '');
                $status = (string) ($row['status'] ?? '');
                if (!isset($buckets[$date])) {
                    continue;
                }

                $reservationsCount = (int) ($row['total_reservations'] ?? 0);
                $coversCount       = (int) ($row['total_covers'] ?? 0);

                $buckets[$date]['reservations']['by_status'][$status] = $reservationsCount;
                $buckets[$date]['reservations']['total']              += $reservationsCount;

                $buckets[$date]['covers']['by_status'][$status] = $coversCount;
                $buckets[$date]['covers']['total']              += $coversCount;
            }
        }

        $paymentsTable = $this->payments->tableName();
        $paymentsSql   = 'SELECT r.date AS report_date, p.currency, p.type, COUNT(*) AS transactions, SUM(p.amount) AS total_amount'
            . ' FROM ' . $paymentsTable . ' p'
            . ' INNER JOIN ' . $reservationTable . ' r ON r.id = p.reservation_id'
            . ' WHERE r.date BETWEEN %s AND %s'
            . " AND p.status IN ('paid','authorized')"
            . ' GROUP BY r.date, p.currency, p.type';

        $paymentsRows = $this->wpdb->get_results(
            $this->wpdb->prepare($paymentsSql, $start->format('Y-m-d'), $end->format('Y-m-d')),
            ARRAY_A
        );

        if (is_array($paymentsRows)) {
            foreach ($paymentsRows as $row) {
                $date = (string) ($row['report_date'] ?? '');
                if (!isset($buckets[$date])) {
                    continue;
                }

                $currency = strtoupper((string) ($row['currency'] ?? '')) ?: 'EUR';
                $type     = (string) ($row['type'] ?? '');
                $amount   = (float) ($row['total_amount'] ?? 0);
                $count    = (int) ($row['transactions'] ?? 0);

                if (!isset($buckets[$date]['payments']['by_currency'][$currency])) {
                    $buckets[$date]['payments']['by_currency'][$currency] = [
                        'amount'               => 0.0,
                        'transactions'         => 0,
                        'deposit_amount'       => 0.0,
                        'deposit_transactions' => 0,
                    ];
                }

                $buckets[$date]['payments']['by_currency'][$currency]['amount']       += $amount;
                $buckets[$date]['payments']['by_currency'][$currency]['transactions'] += $count;

                if ($type === 'deposit') {
                    $buckets[$date]['payments']['by_currency'][$currency]['deposit_amount']       += $amount;
                    $buckets[$date]['payments']['by_currency'][$currency]['deposit_transactions'] += $count;
                }
            }
        }

        foreach ($buckets as $date => &$data) {
            $totalReservations = (int) $data['reservations']['total'];
            $totalCovers       = (int) $data['covers']['total'];

            $data['covers']['avg_per_reservation'] = $totalReservations > 0
                ? round($totalCovers / $totalReservations, 2)
                : 0.0;

            $data['reservations']['no_show_rate'] = $totalReservations > 0
                ? round((($data['reservations']['by_status']['no-show'] ?? 0) / $totalReservations) * 100, 2)
                : 0.0;

            $data['reservations']['visit_rate'] = $totalReservations > 0
                ? round((($data['reservations']['by_status']['visited'] ?? 0) / $totalReservations) * 100, 2)
                : 0.0;

            foreach ($data['payments']['by_currency'] as $currency => $bucket) {
                $data['payments']['by_currency'][$currency]['amount']               = round((float) $bucket['amount'], 2);
                $data['payments']['by_currency'][$currency]['deposit_amount']       = round((float) $bucket['deposit_amount'], 2);
                $data['payments']['by_currency'][$currency]['transactions']         = (int) $bucket['transactions'];
                $data['payments']['by_currency'][$currency]['deposit_transactions'] = (int) $bucket['deposit_transactions'];

                $data['payments']['totals']['amount']       += $data['payments']['by_currency'][$currency]['amount'];
                $data['payments']['totals']['transactions'] += $data['payments']['by_currency'][$currency]['transactions'];
            }

            $data['payments']['totals']['amount']       = round((float) $data['payments']['totals']['amount'], 2);
            $data['payments']['totals']['transactions'] = (int) $data['payments']['totals']['transactions'];

            ksort($data['reservations']['by_status']);
            ksort($data['covers']['by_status']);
            ksort($data['payments']['by_currency']);
        }
        unset($data);

        return array_values($buckets);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function getLogs(string $channel, array $args = []): array
    {
        $channel = strtolower(trim($channel));
        if (!isset(self::LOG_TABLES[$channel])) {
            return [
                'entries'    => [],
                'pagination' => [
                    'page'        => 1,
                    'per_page'    => 20,
                    'total'       => 0,
                    'total_pages' => 0,
                ],
            ];
        }

        $config = self::LOG_TABLES[$channel];
        $table  = $this->wpdb->prefix . $config['table'];

        $page    = max(1, (int) ($args['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($args['per_page'] ?? 25)));

        $where    = [];
        $params   = [];
        $status   = isset($args['status']) ? trim((string) $args['status']) : '';
        $dateFrom = isset($args['from']) ? $this->normalizeDateString((string) $args['from']) : null;
        $dateTo   = isset($args['to']) ? $this->normalizeDateString((string) $args['to']) : null;
        $search   = isset($args['search']) ? trim((string) $args['search']) : '';

        if ($status !== '' && is_string($config['status_column'])) {
            $where[]  = $config['status_column'] . ' = %s';
            $params[] = $status;
        }

        if ($dateFrom !== null) {
            $where[]  = 'created_at >= %s';
            $params[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== null) {
            $where[]  = 'created_at <= %s';
            $params[] = $dateTo . ' 23:59:59';
        }

        if ($search !== '' && !empty($config['search_columns'])) {
            $like      = '%' . $this->wpdb->esc_like($search) . '%';
            $searchSql = [];
            foreach ($config['search_columns'] as $column) {
                $searchSql[] = $column . ' LIKE %s';
                $params[]    = $like;
            }

            if ($searchSql !== []) {
                $where[] = '(' . implode(' OR ', $searchSql) . ')';
            }
        }

        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = 'SELECT COUNT(*) FROM ' . $table . $whereSql;
        $count    = (int) ($params !== []
            ? $this->wpdb->get_var($this->wpdb->prepare($countSql, $params))
            : $this->wpdb->get_var($countSql));

        $offset = ($page - 1) * $perPage;
        $query  = 'SELECT * FROM ' . $table . $whereSql
            . ' ORDER BY ' . $config['order_by'] . ' DESC'
            . ' LIMIT %d OFFSET %d';

        $queryParams = $params;
        $queryParams[] = $perPage;
        $queryParams[] = $offset;

        $preparedQuery = $params !== []
            ? $this->wpdb->prepare($query, $queryParams)
            : $this->wpdb->prepare($query, $perPage, $offset);

        $rows = $this->wpdb->get_results($preparedQuery, ARRAY_A);

        $entries = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $entries[] = array_map(static function ($value) {
                    if (is_numeric($value)) {
                        return $value + 0;
                    }

                    return $value;
                }, $row);
            }
        }

        $totalPages = $count > 0 ? (int) ceil($count / $perPage) : 0;

        return [
            'entries'    => $entries,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $count,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, string>
     */
    public function exportReservations(array $args = []): array
    {
        [$start, $end] = $this->resolveRange(
            (string) ($args['from'] ?? ''),
            isset($args['to']) ? (string) $args['to'] : null
        );

        $statusFilter = isset($args['status']) ? trim((string) $args['status']) : '';
        $format       = strtolower(trim((string) ($args['format'] ?? 'csv')));
        $delimiter    = $format === 'excel' ? ';' : ',';
        $withBom      = $format === 'excel';

        $reservationsTable = $this->reservations->tableName();
        $customersTable    = $this->wpdb->prefix . 'fp_customers';

        $where    = 'r.date BETWEEN %s AND %s';
        $params   = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        if ($statusFilter !== '') {
            $where   .= ' AND r.status = %s';
            $params[] = $statusFilter;
        }

        $sql = 'SELECT r.id, r.status, r.date, r.time, r.party, r.notes, r.allergies, r.value, r.currency,'
            . ' r.lang, r.location_id, r.utm_source, r.utm_medium, r.utm_campaign, r.created_at, r.updated_at, r.visited_at,'
            . ' c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang'
            . ' FROM ' . $reservationsTable . ' r'
            . ' LEFT JOIN ' . $customersTable . ' c ON c.id = r.customer_id'
            . ' WHERE ' . $where
            . ' ORDER BY r.date ASC, r.time ASC, r.id ASC';

        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        $data = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $data[] = [
                    'reservation_id'  => (string) ($row['id'] ?? ''),
                    'status'          => (string) ($row['status'] ?? ''),
                    'date'            => (string) ($row['date'] ?? ''),
                    'time'            => $this->formatTime((string) ($row['time'] ?? '')),
                    'party'           => (string) ($row['party'] ?? ''),
                    'location'        => (string) ($row['location_id'] ?? ''),
                    'language'        => (string) ($row['lang'] ?? ''),
                    'customer_name'   => $this->composeName((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? '')),
                    'customer_email'  => (string) ($row['email'] ?? ''),
                    'customer_phone'  => (string) ($row['phone'] ?? ''),
                    'customer_lang'   => (string) ($row['customer_lang'] ?? ''),
                    'notes'           => $this->sanitizeMultiline((string) ($row['notes'] ?? '')),
                    'allergies'       => $this->sanitizeMultiline((string) ($row['allergies'] ?? '')),
                    'value'           => (string) ($row['value'] ?? ''),
                    'currency'        => (string) ($row['currency'] ?? ''),
                    'utm_source'      => (string) ($row['utm_source'] ?? ''),
                    'utm_medium'      => (string) ($row['utm_medium'] ?? ''),
                    'utm_campaign'    => (string) ($row['utm_campaign'] ?? ''),
                    'created_at'      => (string) ($row['created_at'] ?? ''),
                    'updated_at'      => (string) ($row['updated_at'] ?? ''),
                    'visited_at'      => (string) ($row['visited_at'] ?? ''),
                ];
            }
        }

        $headers = array_keys($data[0] ?? [
            'reservation_id' => 'ID',
            'status'         => 'status',
            'date'           => 'date',
            'time'           => 'time',
            'party'          => 'party',
            'location'       => 'location',
            'language'       => 'language',
            'customer_name'  => 'customer_name',
            'customer_email' => 'customer_email',
            'customer_phone' => 'customer_phone',
            'customer_lang'  => 'customer_lang',
            'notes'          => 'notes',
            'allergies'      => 'allergies',
            'value'          => 'value',
            'currency'       => 'currency',
            'utm_source'     => 'utm_source',
            'utm_medium'     => 'utm_medium',
            'utm_campaign'   => 'utm_campaign',
            'created_at'     => 'created_at',
            'updated_at'     => 'updated_at',
            'visited_at'     => 'visited_at',
        ]);

        $content = $this->buildCsv($headers, $data, $delimiter, $withBom);

        $filename = sprintf(
            'fp-reservations-%s-%s.csv',
            $start->format('Ymd'),
            $end->format('Ymd')
        );

        return [
            'filename'  => $filename,
            'content'   => $content,
            'mime_type' => 'text/csv; charset=utf-8',
            'delimiter' => $delimiter,
            'format'    => $format,
        ];
    }

    private function composeName(string $first, string $last): string
    {
        $full = trim($first . ' ' . $last);

        return $full;
    }

    private function sanitizeMultiline(string $value): string
    {
        return trim(str_replace(["\r\n", "\n", "\r"], ' ', $value));
    }

    private function formatTime(string $time): string
    {
        if ($time === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}/', $time) === 1) {
            return substr($time, 0, 5);
        }

        return $time;
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function resolveRange(string $start, ?string $end): array
    {
        $startDate = $this->createDate($start) ?? new DateTimeImmutable('today');
        $endDate   = $this->createDate($end ?? '') ?? $startDate;

        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate->setTime(0, 0, 0), $endDate->setTime(0, 0, 0)];
    }

    private function createDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        return null;
    }

    private function normalizeDateString(string $value): ?string
    {
        $date = $this->createDate($value);

        return $date?->format('Y-m-d');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function bootstrapDailyBuckets(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $period  = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $buckets = [];

        foreach ($period as $date) {
            $key            = $date->format('Y-m-d');
            $buckets[$key] = [
                'date'         => $key,
                'reservations' => [
                    'total'     => 0,
                    'by_status' => [],
                ],
                'covers'       => [
                    'total'     => 0,
                    'by_status' => [],
                ],
                'payments'     => [
                    'totals'      => [
                        'amount'       => 0.0,
                        'transactions' => 0,
                    ],
                    'by_currency' => [],
                ],
            ];
        }

        return $buckets;
    }

    /**
     * @param array<int, string>               $headers
     * @param array<int, array<string, string>> $rows
     */
    private function buildCsv(array $headers, array $rows, string $delimiter, bool $withBom): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        if ($withBom) {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        fputcsv($handle, $headers, $delimiter);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }

            fputcsv($handle, $line, $delimiter);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }
}
