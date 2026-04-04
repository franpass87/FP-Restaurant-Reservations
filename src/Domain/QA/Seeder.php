<?php

declare(strict_types=1);

namespace FP\Resv\Domain\QA;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use wpdb;
use function absint;
use function array_fill;
use function array_map;
use function current_time;
use function do_action;
use function has_action;
use function is_array;
use function is_numeric;
use function max;
use function min;
use function sprintf;
use function wp_json_encode;
use function wp_timezone;

final class Seeder
{
    private const MAX_DAYS = 60;

    public function __construct(
        private readonly ReservationsRepository $reservations,
        private readonly CustomersRepository $customers,
        private readonly PaymentsRepository $payments,
        private readonly wpdb $wpdb
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function seed(int $days = 14, bool $dryRun = false): array
    {
        $days = max(1, min(self::MAX_DAYS, absint($days)));

        $timezone = wp_timezone();
        if (!$timezone instanceof DateTimeZone) {
            $timezone = new DateTimeZone('UTC');
        }

        $now    = new DateTimeImmutable('now', $timezone);
        $start  = $now->setTime(0, 0)->sub(new DateInterval('P' . ($days - 1) . 'D'));
        $end    = $now->setTime(23, 59, 59);

        $summary = [
            'start_date'            => $start->format('Y-m-d'),
            'end_date'              => $end->format('Y-m-d'),
            'days'                  => $days,
            'dry_run'               => $dryRun,
            'reservations_created'  => 0,
            'customers_created'     => 0,
            'mail_logged'           => 0,
            'webhooks_logged'       => 0,
            'queue_logged'          => 0,
            'payments_logged'       => 0,
            'audit_entries_logged'  => 0,
            'cleanup'               => [
                'reservations' => 0,
                'customers'    => 0,
                'mail'         => 0,
                'webhooks'     => 0,
                'queue'        => 0,
                'payments'     => 0,
                'audit'        => 0,
            ],
        ];

        if ($dryRun) {
            return $summary;
        }

        $summary['cleanup'] = $this->cleanup();

        $channels = [
            ['source' => 'qa-seed-google', 'medium' => 'cpc', 'campaign' => 'qa-brand'],
            ['source' => 'qa-seed-meta', 'medium' => 'paid_social', 'campaign' => 'qa-retargeting'],
            ['source' => 'qa-seed-organic', 'medium' => 'organic', 'campaign' => 'qa-blog'],
            ['source' => 'qa-seed-direct', 'medium' => 'direct', 'campaign' => ''],
            ['source' => 'qa-seed-referral', 'medium' => 'referral', 'campaign' => 'qa-press'],
            ['source' => 'qa-seed-email', 'medium' => 'email', 'campaign' => 'qa-newsletter'],
        ];

        $times = ['19:00', '20:30', '13:00'];
        $statuses = ['confirmed', 'visited', 'pending'];
        $locationPool = ['milano-centro', 'roma-trastevere', 'torino-centrale'];

        $seedIndex = 0;
        for ($day = 0; $day < $days; $day++) {
            $currentDate = $start->add(new DateInterval('P' . $day . 'D'));
            for ($slot = 0; $slot < 2; $slot++) {
                $channel = $channels[($seedIndex + $slot) % count($channels)];
                $timeParts = explode(':', $times[($seedIndex + $slot) % count($times)]);
                $hour = (int) ($timeParts[0] ?? '19');
                $minute = (int) ($timeParts[1] ?? '00');

                $reservationTime = $currentDate->setTime($hour, $minute);
                $createdAt       = $currentDate->setTime(9 + ($slot * 2), 15, 0);
                $completedAt     = $reservationTime->add(new DateInterval('PT90M'));

                $status = $statuses[($seedIndex + $slot) % count($statuses)];
                if ($day === $days - 1) {
                    $status = 'pending';
                }

                $party = 2 + ($seedIndex % 4);
                $value = (float) ($party * (35 + (($seedIndex + $slot) % 3) * 5));
                $currency = 'EUR';

                $email = sprintf('qa-seed+%s-%02d@example.test', $currentDate->format('Ymd'), $slot + 1);
                $customerId = $this->customers->upsert($email, [
                    'first_name'        => 'QA',
                    'last_name'         => sprintf('Seed %d', $seedIndex + 1),
                    'phone'             => '+39020000' . sprintf('%04d', $seedIndex + 1),
                    'lang'              => 'it',
                    'marketing_consent' => true,
                    'profiling_consent' => $slot % 2 === 0,
                    'consent_ts'        => $createdAt->format('Y-m-d H:i:s'),
                    'consent_version'   => 'qa-seed',
                ]);

                $summary['customers_created']++;

                $reservationId = $this->reservations->insert([
                    'status'       => $status,
                    'date'         => $reservationTime->format('Y-m-d'),
                    'time'         => $reservationTime->format('H:i:s'),
                    'party'        => $party,
                    'notes'        => 'QA Seed scenario',
                    'allergies'    => $slot % 2 === 0 ? 'Noci' : '',
                    'utm_source'   => $channel['source'],
                    'utm_medium'   => $channel['medium'],
                    'utm_campaign' => $channel['campaign'],
                    'lang'         => 'it',
                    'location_id'  => $locationPool[$seedIndex % count($locationPool)],
                    'value'        => $value,
                    'currency'     => $currency,
                    'customer_id'  => $customerId,
                    'created_at'   => $createdAt->format('Y-m-d H:i:s'),
                    'updated_at'   => $createdAt->format('Y-m-d H:i:s'),
                    'visited_at'   => $status === 'visited' ? $completedAt->format('Y-m-d H:i:s') : null,
                ]);

                $summary['reservations_created']++;

                $this->wpdb->insert(
                    $this->wpdb->prefix . 'fp_mail_log',
                    [
                        'reservation_id' => $reservationId,
                        'to_emails'      => $email,
                        'subject'        => sprintf('Conferma prenotazione #%d', $reservationId),
                        'first_line'     => 'Grazie per la tua prenotazione da QA Seed.',
                        'status'         => 'sent',
                        'error'          => null,
                        'content_type'   => 'text/html',
                        'body'           => '<p><strong>Grazie</strong> per aver prenotato con noi!</p>',
                        'created_at'     => $createdAt->format('Y-m-d H:i:s'),
                    ]
                );
                $summary['mail_logged']++;

                $this->wpdb->insert(
                    $this->wpdb->prefix . 'fp_postvisit_jobs',
                    [
                        'reservation_id' => $reservationId,
                        'run_at'         => $completedAt->format('Y-m-d H:i:s'),
                        'status'         => 'completed',
                        'channel'        => 'qa-postvisit',
                        'last_error'     => null,
                        'created_at'     => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at'     => $completedAt->format('Y-m-d H:i:s'),
                    ]
                );
                $summary['queue_logged']++;

                $this->wpdb->insert(
                    $this->wpdb->prefix . 'fp_brevo_log',
                    [
                        'reservation_id' => $reservationId,
                        'action'         => 'qa_seed_webhook',
                        'payload_snippet'=> wp_json_encode([
                            'event' => 'reservation.confirmed',
                            'id'    => $reservationId,
                            'email' => $email,
                        ]),
                        'status'         => 'success',
                        'error'          => null,
                        'created_at'     => $createdAt->format('Y-m-d H:i:s'),
                    ]
                );
                $summary['webhooks_logged']++;

                if ($seedIndex % 2 === 0) {
                    $this->payments->insert([
                        'reservation_id' => $reservationId,
                        'type'           => 'payment_intent',
                        'status'         => 'paid',
                        'amount'         => $value,
                        'currency'       => $currency,
                        'external_id'    => sprintf('qa_seed_%d', $reservationId),
                        'meta_json'      => wp_json_encode([
                            'qa_seed'   => true,
                            'captured'  => $status !== 'pending',
                            'channel'   => $channel['source'],
                        ]),
                        'created_at'     => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at'     => $completedAt->format('Y-m-d H:i:s'),
                    ]);
                    $summary['payments_logged']++;
                }

                $this->reservations->logAudit([
                    'action'      => 'rest_seed_success',
                    'entity'      => 'reservation',
                    'entity_id'   => $reservationId,
                    'actor_role'  => 'qa_seed',
                    'after_json'  => wp_json_encode([
                        'status' => $status,
                        'party'  => $party,
                        'value'  => $value,
                        'source' => $channel['source'],
                    ]),
                    'created_at'  => $createdAt->format('Y-m-d H:i:s'),
                    'ip'          => '127.0.0.1',
                ]);
                $summary['audit_entries_logged']++;

                $seedIndex++;
            }
        }

        return $summary;
    }

    /**
     * Simula il flusso integrazioni senza chiamate esterne o credenziali.
     *
     * @return array<string, mixed>
     */
    public function simulateIntegrations(bool $dryRun = false, int $reservationId = 0, bool $includeFailure = true): array
    {
        $now = new DateTimeImmutable('now', wp_timezone());
        $targetReservationId = $reservationId > 0 ? $reservationId : 0;
        $createdReservation = false;

        if ($targetReservationId > 0 && $this->reservations->find($targetReservationId) === null) {
            $targetReservationId = 0;
        }

        if ($targetReservationId <= 0) {
            $targetReservationId = $this->createSimulationReservation($now);
            $createdReservation = true;
        }

        $summary = [
            'dry_run' => $dryRun,
            'reservation_id' => $targetReservationId,
            'reservation_created' => $createdReservation,
            'include_failure' => $includeFailure,
            'email_logs' => 1 + ($includeFailure ? 1 : 0),
            'brevo_logs' => 1 + ($includeFailure ? 1 : 0),
            'queue_logs' => 1 + ($includeFailure ? 1 : 0),
            'payments_logged' => 1,
            'audit_entries_logged' => 4 + ($includeFailure ? 1 : 0),
            'tracking_events_dispatched' => 2,
            'tracking_hook_active' => has_action('fp_tracking_event') !== false,
            'mode' => 'simulated_no_credentials',
        ];

        if ($dryRun) {
            return $summary;
        }

        $timestamp = current_time('mysql');
        $runAt = $now->add(new DateInterval('PT2H'))->format('Y-m-d H:i:s');

        $this->wpdb->insert(
            $this->wpdb->prefix . 'fp_mail_log',
            [
                'reservation_id' => $targetReservationId,
                'to_emails'      => sprintf('qa-sim+%d@example.test', $targetReservationId),
                'subject'        => sprintf('[QA SIM] Conferma prenotazione #%d', $targetReservationId),
                'first_line'     => 'Invio simulato (nessuna chiamata SMTP/Brevo).',
                'status'         => 'sent',
                'error'          => null,
                'content_type'   => 'text/html',
                'body'           => '<p>Messaggio simulato per verifica diagnostica integrazioni.</p>',
                'created_at'     => $timestamp,
            ]
        );

        if ($includeFailure) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'fp_mail_log',
                [
                    'reservation_id' => $targetReservationId,
                    'to_emails'      => sprintf('qa-sim+%d@example.test', $targetReservationId),
                    'subject'        => sprintf('[QA SIM] Errore invio prenotazione #%d', $targetReservationId),
                    'first_line'     => 'Errore simulato per test dashboard diagnostica.',
                    'status'         => 'failed',
                    'error'          => 'simulation_error: provider_unavailable',
                    'content_type'   => 'text/plain',
                    'body'           => 'Errore simulato (nessuna chiamata reale).',
                    'created_at'     => $timestamp,
                ]
            );
        }

        $this->wpdb->insert(
            $this->wpdb->prefix . 'fp_brevo_log',
            [
                'reservation_id' => $targetReservationId,
                'action'         => 'qa_sim_brevo_event',
                'payload_snippet'=> wp_json_encode([
                    'mode' => 'simulation',
                    'provider' => 'brevo',
                    'event' => 'reservation.confirmed',
                    'reservation_id' => $targetReservationId,
                    'credentials_required' => false,
                ]),
                'status'         => 'success',
                'error'          => null,
                'created_at'     => $timestamp,
            ]
        );

        if ($includeFailure) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'fp_brevo_log',
                [
                    'reservation_id' => $targetReservationId,
                    'action'         => 'qa_sim_brevo_event_failed',
                    'payload_snippet'=> wp_json_encode([
                        'mode' => 'simulation',
                        'provider' => 'brevo',
                        'event' => 'reservation.reminder',
                        'reservation_id' => $targetReservationId,
                        'credentials_required' => false,
                    ]),
                    'status'         => 'error',
                    'error'          => 'simulation_error: network_timeout',
                    'created_at'     => $timestamp,
                ]
            );
        }

        $this->wpdb->insert(
            $this->wpdb->prefix . 'fp_postvisit_jobs',
            [
                'reservation_id' => $targetReservationId,
                'run_at'         => $runAt,
                'status'         => 'completed',
                'channel'        => 'qa-sim-postvisit',
                'last_error'     => null,
                'created_at'     => $timestamp,
                'updated_at'     => $timestamp,
            ]
        );

        if ($includeFailure) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'fp_postvisit_jobs',
                [
                    'reservation_id' => $targetReservationId,
                    'run_at'         => $runAt,
                    'status'         => 'failed',
                    'channel'        => 'qa-sim-postvisit',
                    'last_error'     => 'simulation_error: queue_worker_stopped',
                    'created_at'     => $timestamp,
                    'updated_at'     => $timestamp,
                ]
            );
        }

        $this->payments->insert([
            'reservation_id' => $targetReservationId,
            'type'           => 'payment_intent',
            'status'         => 'paid',
            'amount'         => 90.00,
            'currency'       => 'EUR',
            'external_id'    => sprintf('qa_sim_%d', $targetReservationId),
            'meta_json'      => wp_json_encode([
                'qa_simulation' => true,
                'provider' => 'stripe',
                'credentials_required' => false,
            ]),
            'created_at'     => $timestamp,
            'updated_at'     => $timestamp,
        ]);

        $this->reservations->logAudit([
            'action'      => 'rest_simulate_integrations',
            'entity'      => 'api',
            'entity_id'   => $targetReservationId,
            'actor_role'  => 'qa_sim',
            'after_json'  => wp_json_encode([
                'mode' => 'simulation',
                'channels' => ['brevo', 'google_calendar', 'stripe', 'email', 'queue'],
                'credentials_required' => false,
            ]),
            'created_at'  => $timestamp,
            'ip'          => '127.0.0.1',
        ]);

        $this->reservations->logAudit([
            'action'      => 'calendar_sync_simulated',
            'entity'      => 'calendar',
            'entity_id'   => $targetReservationId,
            'actor_role'  => 'qa_sim',
            'after_json'  => wp_json_encode([
                'provider' => 'google_calendar',
                'result' => 'success',
                'credentials_required' => false,
            ]),
            'created_at'  => $timestamp,
            'ip'          => '127.0.0.1',
        ]);

        $this->reservations->logAudit([
            'action'      => 'stripe_webhook_simulated',
            'entity'      => 'payment',
            'entity_id'   => $targetReservationId,
            'actor_role'  => 'qa_sim',
            'after_json'  => wp_json_encode([
                'provider' => 'stripe',
                'event' => 'payment_intent.succeeded',
                'credentials_required' => false,
            ]),
            'created_at'  => $timestamp,
            'ip'          => '127.0.0.1',
        ]);

        if ($includeFailure) {
            $this->reservations->logAudit([
                'action'      => 'rest_simulation_warning',
                'entity'      => 'api',
                'entity_id'   => $targetReservationId,
                'actor_role'  => 'qa_sim',
                'after_json'  => wp_json_encode([
                    'provider' => 'google_calendar',
                    'warning' => 'rate_limited_simulated',
                ]),
                'created_at'  => $timestamp,
                'ip'          => '127.0.0.1',
            ]);
        }

        $trackingBaseParams = [
            'reservation_id'       => $targetReservationId,
            'transaction_id'       => 'resv-' . $targetReservationId,
            'value'                => 90.00,
            'currency'             => 'EUR',
            'reservation_party'    => 2,
            'reservation_date'     => $now->format('Y-m-d'),
            'reservation_time'     => $now->format('H:i:s'),
            'meal_type'            => 'dinner',
            'reservation_location' => 'qa-sim-location',
            'event_id'             => 'qa_sim_tracking_' . $targetReservationId,
            'qa_simulation'        => true,
            'credentials_required' => false,
            'user_data'            => [
                'em' => sprintf('qa-sim+%d@example.test', $targetReservationId),
                'fn' => 'QA',
                'ln' => 'Simulation',
                'ph' => '+390200009999',
            ],
        ];

        do_action('fp_tracking_event', 'booking_confirmed', $trackingBaseParams);

        $this->reservations->logAudit([
            'action'      => 'rest_tracking_simulated',
            'entity'      => 'api',
            'entity_id'   => $targetReservationId,
            'actor_role'  => 'qa_sim',
            'after_json'  => wp_json_encode([
                'tracking_events' => ['booking_confirmed'],
                'qa_simulation' => true,
                'credentials_required' => false,
            ]),
            'created_at'  => $timestamp,
            'ip'          => '127.0.0.1',
        ]);

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function cleanup(): array
    {
        $reservationsTable = $this->reservations->tableName();
        $ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                'SELECT id FROM ' . $reservationsTable . ' WHERE utm_source LIKE %s OR notes LIKE %s',
                'qa-seed-%',
                'QA Seed%'
            )
        );

        $removed = [
            'reservations' => 0,
            'customers'    => 0,
            'mail'         => 0,
            'webhooks'     => 0,
            'queue'        => 0,
            'payments'     => 0,
            'audit'        => 0,
        ];

        if (is_array($ids) && $ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $intIds       = array_map('intval', $ids);

            $removed['mail'] = $this->deleteByIds($this->wpdb->prefix . 'fp_mail_log', 'reservation_id', $placeholders, $intIds);
            $removed['webhooks'] = $this->deleteByIds($this->wpdb->prefix . 'fp_brevo_log', 'reservation_id', $placeholders, $intIds);
            $removed['queue'] = $this->deleteByIds($this->wpdb->prefix . 'fp_postvisit_jobs', 'reservation_id', $placeholders, $intIds);
            $removed['payments'] = $this->deleteByIds($this->payments->tableName(), 'reservation_id', $placeholders, $intIds);
            $removed['audit'] = $this->deleteByIds($this->reservations->auditTable(), 'entity_id', $placeholders, $intIds);

            $deleted = $this->wpdb->query(
                $this->wpdb->prepare(
                    'DELETE FROM ' . $reservationsTable . ' WHERE id IN (' . $placeholders . ')',
                    ...$intIds
                )
            );
            if (is_numeric($deleted)) {
                $removed['reservations'] = (int) $deleted;
            }
        }

        $deletedCustomers = $this->wpdb->query(
            $this->wpdb->prepare(
                'DELETE FROM ' . $this->customers->tableName() . ' WHERE email LIKE %s',
                'qa-seed+%@example.test'
            )
        );
        if (is_numeric($deletedCustomers)) {
            $removed['customers'] = (int) $deletedCustomers;
        }

        return $removed;
    }

    /**
     * Crea una prenotazione minima per legare i log di simulazione.
     */
    private function createSimulationReservation(DateTimeImmutable $now): int
    {
        $email = sprintf('qa-sim+%s@example.test', $now->format('YmdHis'));
        $customerId = $this->customers->upsert($email, [
            'first_name'        => 'QA',
            'last_name'         => 'Simulation',
            'phone'             => '+390200009999',
            'lang'              => 'it',
            'marketing_consent' => true,
            'profiling_consent' => false,
            'consent_ts'        => $now->format('Y-m-d H:i:s'),
            'consent_version'   => 'qa-sim',
        ]);

        $reservationTime = $now->add(new DateInterval('PT1H'));

        return $this->reservations->insert([
            'status'       => 'pending',
            'date'         => $reservationTime->format('Y-m-d'),
            'time'         => $reservationTime->format('H:i:s'),
            'party'        => 2,
            'notes'        => 'QA Simulation reservation',
            'utm_source'   => 'qa-sim',
            'utm_medium'   => 'simulation',
            'utm_campaign' => 'integrations',
            'lang'         => 'it',
            'location_id'  => 'qa-sim-location',
            'value'        => 90.00,
            'currency'     => 'EUR',
            'customer_id'  => $customerId,
            'created_at'   => $now->format('Y-m-d H:i:s'),
            'updated_at'   => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, int> $ids
     */
    private function deleteByIds(string $table, string $column, string $placeholders, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                'DELETE FROM ' . $table . ' WHERE ' . $column . ' IN (' . $placeholders . ')',
                ...$ids
            )
        );

        return is_numeric($deleted) ? (int) $deleted : 0;
    }
}
