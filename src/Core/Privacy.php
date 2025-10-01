<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Options;
use wpdb;
use function array_fill;
use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function is_email;
use function esc_sql;
use function md5;
use function sanitize_email;
use function sprintf;
use function trim;
use function wp_timezone;

final class Privacy
{
    public function __construct(
        private readonly Options $options,
        private readonly CustomersRepository $customers,
        private readonly ReservationsRepository $reservations,
        private readonly wpdb $wpdb
    ) {
    }

    public function policyVersion(): string
    {
        $settings = $this->options->getGroup('fp_resv_tracking', [
            'privacy_policy_version' => '1.0',
        ]);

        $version = trim((string) ($settings['privacy_policy_version'] ?? '1.0'));

        return $version !== '' ? $version : '1.0';
    }

    public function retentionMonths(): int
    {
        $settings = $this->options->getGroup('fp_resv_tracking', [
            'privacy_retention_months' => '24',
        ]);

        $months = (int) ($settings['privacy_retention_months'] ?? 24);
        if ($months < 0) {
            $months = 0;
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    public function exportByEmail(string $email): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return [];
        }

        $customer = $this->customers->findByEmail($email);
        $reservations = [];
        $mailLog = [];

        if ($customer !== null) {
            $reservations = $this->reservations->listByCustomer($customer->id);
            $reservationIds = array_values(array_filter(array_map(static fn ($row) => (int) ($row['id'] ?? 0), $reservations))); 
            if ($reservationIds !== []) {
                $mailLog = $this->fetchMailLog($reservationIds);
            }
        }

        return [
            'customer'      => $customer === null ? null : [
                'id'                => $customer->id,
                'email'             => $customer->email,
                'first_name'        => $customer->firstName,
                'last_name'         => $customer->lastName,
                'phone'             => $customer->phone,
                'lang'              => $customer->lang,
                'marketing_consent' => $customer->marketingConsent,
                'profiling_consent' => $customer->profilingConsent,
                'consent_ts'        => $customer->consentTimestamp,
                'consent_version'   => $customer->consentVersion,
            ],
            'reservations'  => $reservations,
            'surveys'       => $this->fetchSurveysByEmail($email),
            'mail_log'      => $mailLog,
            'policy_version'=> $this->policyVersion(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function anonymizeByEmail(string $email): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return [
                'customer'     => 0,
                'reservations' => 0,
                'surveys'      => 0,
            ];
        }

        $customer = $this->customers->findByEmail($email);
        if ($customer === null) {
            return [
                'customer'     => 0,
                'reservations' => 0,
                'surveys'      => $this->anonymizeSurveysByEmail($email, 'deleted-' . md5($email) . '@fp-reservations.invalid'),
            ];
        }

        $placeholderEmail = $this->customers->placeholderEmail($customer->id);

        $reservationsCount = $this->reservations->anonymizeCustomer($customer->id);
        $customerCount     = $this->customers->anonymizeCustomer($customer->id, $placeholderEmail);
        $surveysCount      = $this->anonymizeSurveysByEmail($email, $placeholderEmail);

        return [
            'customer'     => $customerCount,
            'reservations' => $reservationsCount,
            'surveys'      => $surveysCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runRetentionCleanup(): array
    {
        $cutoff = $this->resolveCutoffDate();
        if ($cutoff === null) {
            return [
                'reservations' => 0,
                'customers'    => 0,
                'surveys'      => 0,
            ];
        }

        $reservationsCount = $this->reservations->anonymizeOlderThan($cutoff);
        $customersCount    = $this->customers->anonymizeInactive($cutoff, $this->reservations->tableName());
        $surveysCount      = $this->anonymizeSurveysOlderThan($cutoff);
        $mailLogsCount     = $this->cleanupLogTable($this->wpdb->prefix . 'fp_mail_log', 'created_at', $cutoff);
        $brevoLogsCount    = $this->cleanupLogTable($this->wpdb->prefix . 'fp_brevo_log', 'created_at', $cutoff);

        return [
            'reservations' => $reservationsCount,
            'customers'    => $customersCount,
            'surveys'      => $surveysCount,
            'mail_logs'    => $mailLogsCount,
            'brevo_logs'   => $brevoLogsCount,
            'cutoff_date'  => $cutoff->format('Y-m-d'),
        ];
    }

    private function cleanupLogTable(string $table, string $dateColumn, DateTimeImmutable $cutoff): int
    {
        $table = esc_sql($table);
        $dateColumn = esc_sql($dateColumn);

        $sql = $this->wpdb->prepare(
            'DELETE FROM ' . $table . ' WHERE ' . $dateColumn . ' < %s',
            $cutoff->format('Y-m-d H:i:s')
        );

        $result = $this->wpdb->query($sql);

        return $result === false ? 0 : (int) $result;
    }

    private function resolveCutoffDate(): ?DateTimeImmutable
    {
        $months = $this->retentionMonths();
        if ($months <= 0) {
            return null;
        }

        $now = new DateTimeImmutable('now', wp_timezone());

        return $now->sub(new DateInterval('P' . $months . 'M'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMailLog(array $reservationIds): array
    {
        $reservationIds = array_values(array_filter(array_map(static fn ($id) => (int) $id, $reservationIds)));
        if ($reservationIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($reservationIds), '%d'));
        $sql = sprintf(
            'SELECT reservation_id, to_emails, subject, status, created_at FROM %s WHERE reservation_id IN (%s) ORDER BY created_at ASC',
            $this->wpdb->prefix . 'fp_mail_log',
            $placeholders
        );

        $prepared = $this->wpdb->prepare($sql, $reservationIds);
        $rows     = $this->wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSurveysByEmail(string $email): array
    {
        $table = $this->wpdb->prefix . 'fp_surveys';
        $rows  = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT reservation_id, email, lang, stars_food, stars_service, stars_atmosphere, nps, comment, created_at, review_link_shown '
                . 'FROM ' . $table . ' WHERE email = %s ORDER BY created_at ASC',
                $email
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function anonymizeSurveysByEmail(string $email, string $placeholder): int
    {
        $table = $this->wpdb->prefix . 'fp_surveys';
        $result = $this->wpdb->update(
            $table,
            [
                'email'             => $placeholder,
                'comment'           => null,
                'review_link_shown' => 0,
            ],
            ['email' => $email]
        );

        return $result === false ? 0 : (int) $result;
    }

    private function anonymizeSurveysOlderThan(DateTimeImmutable $cutoff): int
    {
        $table = $this->wpdb->prefix . 'fp_surveys';
        $sql   = $this->wpdb->prepare(
            'UPDATE ' . $table . ' SET email = CONCAT("deleted-", id, "@fp-reservations.invalid"), comment = NULL '
            . 'WHERE created_at < %s AND (email NOT LIKE "deleted-%%" OR comment IS NOT NULL)',
            $cutoff->format('Y-m-d H:i:s')
        );

        $result = $this->wpdb->query($sql);

        return $result === false ? 0 : (int) $result;
    }
}
