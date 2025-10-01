<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use wpdb;
use function array_keys;
use function array_map;
use function array_values;
use function ceil;
use function fclose;
use function fopen;
use function fputcsv;
use function fwrite;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function ksort;
use function max;
use function number_format;
use function preg_match;
use function round;
use function rewind;
use function sprintf;
use function stream_get_contents;
use function str_replace;
use function str_contains;
use function strtolower;
use function strtoupper;
use function substr;
use function usort;
use function trim;

final class Service
{
    /**
     * @var array<int, string>
     */
    private const ANALYTICS_CHANNELS = [
        'google_ads',
        'meta_ads',
        'organic',
        'direct',
        'referral',
        'email',
        'other',
    ];

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly ReservationsRepository $reservations,
        private readonly PaymentsRepository $payments
    ) {
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listLocations(): array
    {
        $table = $this->reservations->tableName();
        $sql   = 'SELECT DISTINCT location_id FROM ' . $table . ' WHERE location_id IS NOT NULL AND location_id <> "" ORDER BY location_id ASC';

        $rows = $this->wpdb->get_col($sql);

        if (!is_array($rows)) {
            return [];
        }

        $locations = [];
        foreach ($rows as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $locations[] = [
                'id'    => $value,
                'label' => $value,
            ];
        }

        return $locations;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function getAnalytics(array $args = []): array
    {
        [$start, $end] = $this->resolveRange(
            (string) ($args['start'] ?? ''),
            isset($args['end']) ? (string) $args['end'] : null
        );

        $location = isset($args['location']) ? trim((string) $args['location']) : '';
        $location = $location !== '' ? $location : null;

        $buckets   = $this->bootstrapTrendBuckets($start, $end);
        $channels  = $this->bootstrapChannelBuckets();
        $sources   = [];
        $totals    = [
            'reservations' => 0,
            'covers'       => 0,
            'values'       => [],
        ];
        $currencyCounts = [];

        $table = $this->reservations->tableName();
        $where = 'date BETWEEN %s AND %s AND status <> %s';
        $params = [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            'cancelled',
        ];

        if ($location !== null) {
            $where   .= ' AND location_id = %s';
            $params[] = $location;
        }

        $sql = 'SELECT date, party, value, currency, utm_source, utm_medium, utm_campaign, status '
            . 'FROM ' . $table
            . ' WHERE ' . $where;

        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $status = strtolower((string) ($row['status'] ?? ''));
                if ($status === 'deleted') {
                    continue;
                }

                $date = (string) ($row['date'] ?? '');
                if (!isset($buckets[$date])) {
                    continue;
                }

                $covers = (int) ($row['party'] ?? 0);
                $value  = $this->normalizeFloat($row['value'] ?? null);
                $currency = strtoupper((string) ($row['currency'] ?? ''));
                if ($currency === '') {
                    $currency = 'UNKNOWN';
                }

                $totals['values'][$currency] = ($totals['values'][$currency] ?? 0.0) + $value;
                $currencyCounts[$currency] = ($currencyCounts[$currency] ?? 0) + 1;

                $buckets[$date]['reservations'] += 1;
                $buckets[$date]['covers']       += $covers;
                $buckets[$date]['values'][$currency] = ($buckets[$date]['values'][$currency] ?? 0.0) + $value;
                $buckets[$date]['value'] += $value;

                $totals['reservations'] += 1;
                $totals['covers']       += $covers;

                $source   = strtolower(trim((string) ($row['utm_source'] ?? '')));
                $medium   = strtolower(trim((string) ($row['utm_medium'] ?? '')));
                $campaign = (string) ($row['utm_campaign'] ?? '');
                $channel  = $this->classifyChannel($source, $medium);

                $channels[$channel]['reservations'] += 1;
                $channels[$channel]['covers']       += $covers;
                $channels[$channel]['values'][$currency] = ($channels[$channel]['values'][$currency] ?? 0.0) + $value;
                $channels[$channel]['value'] += $value;

                $key = $source . '|' . $medium . '|' . $campaign;
                if (!isset($sources[$key])) {
                    $sources[$key] = [
                        'source'        => $source,
                        'medium'        => $medium,
                        'campaign'      => $campaign,
                        'reservations'  => 0,
                        'covers'        => 0,
                        'value'         => 0.0,
                        'values'        => [],
                    ];
                }

                $sources[$key]['reservations'] += 1;
                $sources[$key]['covers']       += $covers;
                $sources[$key]['values'][$currency] = ($sources[$key]['values'][$currency] ?? 0.0) + $value;
                $sources[$key]['value'] += $value;
            }
        }

        $currencyList = array_keys($totals['values']);
        $primaryCurrency = $currencyList[0] ?? '';

        foreach ($buckets as &$bucket) {
            foreach ($bucket['values'] as $code => $amount) {
                $bucket['values'][$code] = round((float) $amount, 2);
            }

            $bucket['value'] = $bucket['values'][$primaryCurrency] ?? 0.0;
        }
        unset($bucket);

        foreach ($channels as &$channelBucket) {
            $channelBucket['share'] = $totals['reservations'] > 0
                ? round(($channelBucket['reservations'] / $totals['reservations']) * 100, 2)
                : 0.0;
            foreach ($channelBucket['values'] as $code => $amount) {
                $channelBucket['values'][$code] = round((float) $amount, 2);
            }

            $channelBucket['value'] = $channelBucket['values'][$primaryCurrency] ?? 0.0;
        }
        unset($channelBucket);

        $topSources = array_values($sources);
        usort($topSources, static function (array $a, array $b): int {
            return ($b['reservations'] <=> $a['reservations']) ?: ($b['value'] <=> $a['value']);
        });

        $topSources = array_map(function (array $row) use ($totals): array {
            foreach ($row['values'] as $code => $amount) {
                $row['values'][$code] = round((float) $amount, 2);
            }

            $row['value'] = $row['values'][$this->resolvePrimaryCurrency($row['values'])] ?? 0.0;
            $row['share'] = $totals['reservations'] > 0
                ? round(($row['reservations'] / $totals['reservations']) * 100, 2)
                : 0.0;

            return $row;
        }, $topSources);

        foreach ($totals['values'] as $code => $amount) {
            $totals['values'][$code] = round((float) $amount, 2);
        }

        $totals['value'] = $totals['values'][$primaryCurrency] ?? 0.0;

        $avgTicketBreakdown = [];
        foreach ($totals['values'] as $code => $amount) {
            $count = $currencyCounts[$code] ?? 0;
            $avgTicketBreakdown[$code] = $count > 0 ? round($amount / $count, 2) : 0.0;
        }

        return [
            'range' => [
                'start' => $start->format('Y-m-d'),
                'end'   => $end->format('Y-m-d'),
            ],
            'summary' => [
                'reservations' => $totals['reservations'],
                'covers'       => $totals['covers'],
                'value'        => $totals['value'],
                'values'       => $totals['values'],
                'avg_party'    => $totals['reservations'] > 0
                    ? round($totals['covers'] / $totals['reservations'], 2)
                    : 0.0,
                'avg_ticket'   => $avgTicketBreakdown[$primaryCurrency] ?? 0.0,
                'avg_ticket_breakdown' => $avgTicketBreakdown,
                'currency'     => $primaryCurrency,
                'currencies'   => $currencyList,
            ],
            'channels'   => array_values($channels),
            'trend'      => array_values($buckets),
            'topSources' => $topSources,
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, string>
     */
    public function exportAnalytics(array $args = []): array
    {
        $analytics = $this->getAnalytics($args);

        $headers = ['source', 'medium', 'campaign', 'reservations', 'covers', 'value', 'share'];

        $rows = array_map(function (array $row): array {
            $values = $row['values'] ?? [];
            if (is_array($values)) {
                $valueString = $this->formatCurrencyMap($values);
            } else {
                $valueString = number_format((float) ($row['value'] ?? 0.0), 2, '.', '');
            }

            return [
                'source'       => $row['source'] !== '' ? $row['source'] : 'direct',
                'medium'       => $row['medium'],
                'campaign'     => $row['campaign'],
                'reservations' => (string) $row['reservations'],
                'covers'       => (string) $row['covers'],
                'value'        => $valueString,
                'share'        => number_format((float) $row['share'], 2, '.', ''),
            ];
        }, $analytics['topSources']);

        $content = $this->buildCsv($headers, $rows, ',', false);

        $range = $analytics['range'];

        return [
            'filename'  => sprintf('fp-analytics-%s-%s.csv', $range['start'], $range['end']),
            'content'   => $content,
            'mime_type' => 'text/csv; charset=utf-8',
            'delimiter' => ',',
            'format'    => 'csv',
        ];
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

    /**
     * @param array<string, float> $amounts
     */
    private function formatCurrencyMap(array $amounts): string
    {
        if ($amounts === []) {
            return '0.00';
        }

        $parts = [];
        foreach ($amounts as $currency => $amount) {
            $parts[] = $currency . ' ' . number_format((float) $amount, 2, '.', '');
        }

        return implode('; ', $parts);
    }

    /**
     * @return array<string, array<string, float|int|string>>
     */
    private function bootstrapChannelBuckets(): array
    {
        $buckets = [];
        foreach (self::ANALYTICS_CHANNELS as $channel) {
            $buckets[$channel] = [
                'channel'      => $channel,
                'reservations' => 0,
                'covers'       => 0,
                'value'        => 0.0,
                'values'       => [],
                'share'        => 0.0,
            ];
        }

        return $buckets;
    }

    /**
     * @return array<string, array<string, float|int|string>>
     */
    private function bootstrapTrendBuckets(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $buckets = [];

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $buckets[$key] = [
                'date'         => $key,
                'reservations' => 0,
                'covers'       => 0,
                'value'        => 0.0,
                'values'       => [],
            ];
        }

        return $buckets;
    }

    private function classifyChannel(string $source, string $medium): string
    {
        $source = strtolower($source);
        $medium = strtolower($medium);

        if ($source === '' && $medium === '') {
            return 'direct';
        }

        if (str_contains($source, 'google') && ($medium === 'cpc' || $medium === 'ppc' || $medium === 'paid_search')) {
            return 'google_ads';
        }

        if (str_contains($source, 'gclid')) {
            return 'google_ads';
        }

        if (
            str_contains($source, 'facebook')
            || str_contains($source, 'instagram')
            || str_contains($source, 'meta')
            || $medium === 'paid_social'
        ) {
            return 'meta_ads';
        }

        if ($medium === 'email' || $medium === 'newsletter' || str_contains($source, 'newsletter')) {
            return 'email';
        }

        if ($medium === 'organic' || str_contains($source, 'google') || str_contains($source, 'bing')) {
            return 'organic';
        }

        if ($medium === 'referral') {
            return 'referral';
        }

        if ($source === 'direct' || $medium === '(none)') {
            return 'direct';
        }

        return 'other';
    }

    private function normalizeFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return 0.0;
    }

    /**
     * @param array<string, float> $values
     */
    private function resolvePrimaryCurrency(array $values): string
    {
        if ($values === []) {
            return '';
        }

        arsort($values);

        return (string) array_key_first($values);
    }
}
