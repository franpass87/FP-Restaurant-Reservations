<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Mailer;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Surveys\Token as SurveyToken;
use Throwable;
use function __;
use function add_action;
use function add_query_arg;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_values;
use function current_time;
use function explode;
use function home_url;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function strip_tags;
use function strtolower;
use function substr;
use function trim;
use function wp_timezone;

final class AutomationService
{
    public function __construct(
        private readonly Options $options,
        private readonly Client $client,
        private readonly Mapper $mapper,
        private readonly Repository $repository,
        private readonly ReservationsRepository $reservations,
        private readonly Mailer $mailer
    ) {
    }

    public function boot(): void
    {
        add_action('fp_resv_reservation_created', [$this, 'onReservationCreated'], 10, 4);
        add_action('fp_resv_reservation_status_changed', [$this, 'onReservationStatusChanged'], 10, 4);
        add_action('fp_resv_survey_submitted', [$this, 'onSurveySubmitted'], 10, 2);
    }

    public function onReservationCreated(int $reservationId, array $payload, mixed $reservation, string $manageUrl = ''): void
    {
        unset($reservation);
        if (!$this->isEnabled()) {
            return;
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            return;
        }

        $contact = $this->mapper->mapReservation([
            'email'        => $email,
            'first_name'   => $payload['first_name'] ?? '',
            'last_name'    => $payload['last_name'] ?? '',
            'phone'        => $payload['phone'] ?? '',
            'language'     => $payload['language'] ?? '',
            'date'         => $payload['date'] ?? '',
            'time'         => $payload['time'] ?? '',
            'party'        => $payload['party'] ?? '',
            'status'       => $payload['status'] ?? '',
            'location'     => $payload['location'] ?? '',
            'manage_url'   => $manageUrl,
            'utm_source'   => $payload['utm_source'] ?? '',
            'utm_medium'   => $payload['utm_medium'] ?? '',
            'utm_campaign' => $payload['utm_campaign'] ?? '',
        ]);

        $this->syncContact($reservationId, $contact);

        $status = strtolower((string) ($payload['status'] ?? ''));
        if ($status === 'confirmed') {
            $this->dispatchEvent(
                'reservation_confirmed',
                $email,
                [
                    'reservation' => [
                        'id'     => $reservationId,
                        'date'   => $payload['date'] ?? '',
                        'time'   => $payload['time'] ?? '',
                        'party'  => $payload['party'] ?? 0,
                        'status' => $status,
                    ],
                ],
                $reservationId
            );
        }
    }

    public function onReservationStatusChanged(int $reservationId, string $previousStatus, string $currentStatus, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $email = (string) ($context['email'] ?? '');
        if ($email === '' && isset($context['customer']['email'])) {
            $email = (string) $context['customer']['email'];
        }

        if ($email === '') {
            return;
        }

        $contact = $this->mapper->mapReservation([
            'email'        => $email,
            'first_name'   => $context['first_name'] ?? ($context['customer']['first_name'] ?? ''),
            'last_name'    => $context['last_name'] ?? ($context['customer']['last_name'] ?? ''),
            'phone'        => $context['phone'] ?? ($context['customer']['phone'] ?? ''),
            'language'     => $context['customer']['language'] ?? ($context['customer_lang'] ?? ''),
            'date'         => $context['date'] ?? '',
            'time'         => isset($context['time']) ? substr((string) $context['time'], 0, 5) : '',
            'party'        => $context['party'] ?? 0,
            'status'       => $currentStatus,
            'location'     => $context['location_id'] ?? '',
            'manage_url'   => $context['manage_url'] ?? '',
        ]);

        $this->syncContact($reservationId, $contact);

        if ($currentStatus === 'confirmed' && $previousStatus !== 'confirmed') {
            $this->dispatchEvent(
                'reservation_confirmed',
                $email,
                [
                    'reservation' => [
                        'id'     => $reservationId,
                        'date'   => $context['date'] ?? '',
                        'time'   => isset($context['time']) ? substr((string) $context['time'], 0, 5) : '',
                        'party'  => $context['party'] ?? 0,
                        'status' => $currentStatus,
                    ],
                ],
                $reservationId
            );
        }

        if ($currentStatus === 'visited') {
            $this->dispatchEvent(
                'reservation_visited',
                $email,
                [
                    'reservation' => [
                        'id'       => $reservationId,
                        'date'     => $context['date'] ?? '',
                        'time'     => isset($context['time']) ? substr((string) $context['time'], 0, 5) : '',
                        'party'    => $context['party'] ?? 0,
                        'visited_at' => $context['visited_at'] ?? current_time('mysql'),
                    ],
                ],
                $reservationId
            );

            $this->scheduleFollowUp($reservationId, $context, $email);
        }
    }

    public function onSurveySubmitted(int $reservationId, array $result): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $email = (string) ($result['email'] ?? '');
        if ($email === '') {
            $reservation = $this->reservations->findAgendaEntry($reservationId);
            if (is_array($reservation)) {
                $email = (string) ($reservation['email'] ?? '');
            }
        }

        if ($email === '') {
            return;
        }

        $event = $result['positive'] ? 'survey_completed' : 'survey_negative';

        $this->dispatchEvent(
            $event,
            $email,
            [
                'survey' => [
                    'reservation_id' => $reservationId,
                    'scores'         => $result['scores'] ?? [],
                    'average'        => $result['average'] ?? null,
                    'nps'            => $result['nps'] ?? null,
                    'comment'        => $result['comment'] ?? '',
                    'review_url'     => $result['review_url'] ?? '',
                ],
            ],
            $reservationId
        );

        if (!$result['positive']) {
            $this->sendNegativeAlert($reservationId, $result, $email);
        }
    }

    public function processDueJobs(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $jobs = $this->repository->claimDueJobs('brevo_followup', 10);
        foreach ($jobs as $job) {
            $jobId = (int) ($job['id'] ?? 0);
            if ($jobId <= 0 || !$this->repository->markJobProcessing($jobId)) {
                continue;
            }

            try {
                $reservationId = (int) ($job['reservation_id'] ?? 0);
                if ($reservationId <= 0) {
                    throw new Exception('Missing reservation reference');
                }

                $reservation = $this->reservations->findAgendaEntry($reservationId);
                if (!is_array($reservation)) {
                    throw new Exception('Reservation not found');
                }

                $email = (string) ($reservation['email'] ?? '');
                if ($email === '') {
                    throw new Exception('Reservation email missing');
                }

                $surveyUrl = $this->generateSurveyUrl($reservationId, $email, $reservation);

                $this->dispatchEvent(
                    'post_visit_24h',
                    $email,
                    [
                        'reservation' => [
                            'id'        => $reservationId,
                            'date'      => $reservation['date'] ?? '',
                            'time'      => isset($reservation['time']) ? substr((string) $reservation['time'], 0, 5) : '',
                            'party'     => $reservation['party'] ?? 0,
                            'surveyUrl' => $surveyUrl,
                            'language'  => $reservation['customer_lang'] ?? '',
                        ],
                    ],
                    $reservationId
                );

                $this->repository->markJobCompleted($jobId);
            } catch (Throwable $exception) {
                $this->repository->markJobFailed($jobId, $exception->getMessage());
                Logging::log('brevo', 'Failed to process Brevo follow-up job', [
                    'job_id' => $jobId,
                    'error'  => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $reservation
     */
    private function scheduleFollowUp(int $reservationId, array $reservation, string $email): void
    {
        $visitedAt = $reservation['visited_at'] ?? null;
        if (!is_string($visitedAt) || $visitedAt === '') {
            $visitedAt = current_time('mysql');
        }

        $runAt = $this->computeFollowUpRunAt($visitedAt);
        if ($runAt === null) {
            return;
        }

        $enqueued = $this->repository->enqueueFollowUp($reservationId, $runAt);
        if ($enqueued) {
            $this->repository->log($reservationId, 'post_visit_job_scheduled', [
                'run_at' => $runAt,
                'email'  => $email,
            ], 'success');
        }
    }

    private function computeFollowUpRunAt(string $visitedAt): ?string
    {
        try {
            $timezone = wp_timezone();
            $visit    = new DateTimeImmutable($visitedAt, $timezone);
        } catch (Exception) {
            return null;
        }

        $settings    = $this->options->getGroup('fp_resv_brevo', []);
        $offsetHours = (int) ($settings['brevo_followup_offset_hours'] ?? 24);
        if ($offsetHours <= 0) {
            $offsetHours = 24;
        }

        $run = $visit->add(new DateInterval('PT' . $offsetHours . 'H'));
        $hour = (int) $run->format('G');
        if ($hour < 9) {
            $run = $run->setTime(9, 0);
        } elseif ($hour >= 19) {
            $run = $run->modify('+1 day')->setTime(9, 0);
        }

        return $run->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $contact
     */
    private function syncContact(int $reservationId, array $contact): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $payload = $contact;
        $listIds = $this->listIds();
        if ($listIds !== []) {
            $payload['listIds'] = $listIds;
        }

        $response = $this->client->upsertContact($payload);
        $status   = $response['success'] ? 'success' : 'error';

        $this->repository->log($reservationId, 'contact_upsert', [
            'payload'  => $payload,
            'response' => $response,
        ], $status, $response['success'] ? null : ($response['message'] ?? null));
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function dispatchEvent(string $event, string $email, array $properties, int $reservationId): void
    {
        if ($email === '') {
            return;
        }

        if ($this->repository->hasSuccessfulLog($reservationId, $event)) {
            return;
        }

        $response = $this->client->sendEvent($event, [
            'email'      => strtolower(trim($email)),
            'properties' => $properties,
        ]);

        $status = $response['success'] ? 'success' : 'error';

        $this->repository->log($reservationId, $event, [
            'email'      => $email,
            'properties' => $properties,
            'response'   => $response,
        ], $status, $response['success'] ? null : ($response['message'] ?? null));
    }

    private function generateSurveyUrl(int $reservationId, string $email, array $reservation): string
    {
        $base = (string) apply_filters('fp_resv_survey_base_url', home_url('/?fp_resv_survey=1'));
        $token = SurveyToken::generate($reservationId, $email);

        $args = [
            'reservation_id' => $reservationId,
            'token'          => $token,
            'email'          => strtolower(trim($email)),
        ];

        if (isset($reservation['customer_lang']) && $reservation['customer_lang'] !== '') {
            $args['lang'] = $reservation['customer_lang'];
        }

        return add_query_arg($args, $base);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function sendNegativeAlert(int $reservationId, array $result, string $customerEmail): void
    {
        $settings = $this->options->getGroup('fp_resv_notifications', []);
        $emails   = $this->parseEmails((string) ($settings['restaurant_emails'] ?? ''));
        $emails   = array_merge($emails, $this->parseEmails((string) ($settings['webmaster_emails'] ?? '')));
        $emails   = array_values(array_unique($emails));

        if ($emails === []) {
            return;
        }

        $subject = sprintf(__('Feedback negativo prenotazione #%d', 'fp-restaurant-reservations'), $reservationId);
        $lines   = [
            sprintf(__('Prenotazione #%d ha ricevuto un feedback negativo.', 'fp-restaurant-reservations'), $reservationId),
            sprintf(__('NPS: %d', 'fp-restaurant-reservations'), (int) ($result['nps'] ?? 0)),
            sprintf(__('Media stelle: %s', 'fp-restaurant-reservations'), (string) ($result['average'] ?? '0')),
            sprintf(__('Commento: %s', 'fp-restaurant-reservations'), strip_tags((string) ($result['comment'] ?? ''))),
            sprintf(__('Email cliente: %s', 'fp-restaurant-reservations'), $customerEmail),
        ];

        $message = implode("\n", $lines);

        $this->mailer->send(
            implode(',', $emails),
            $subject,
            $message,
            [],
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'survey_alert',
                'content_type'   => 'text/plain',
            ]
        );
    }

    private function parseEmails(string $list): array
    {
        if ($list === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $list));

        return array_values(array_filter($parts, static fn (string $email): bool => $email !== ''));
    }

    private function listIds(): array
    {
        $settings = $this->options->getGroup('fp_resv_brevo', []);
        $value    = (string) ($settings['brevo_list_id'] ?? '');
        if ($value === '') {
            return [];
        }

        $ids = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $id): bool => $id !== ''));

        return array_map('intval', $ids);
    }

    private function isEnabled(): bool
    {
        $settings = $this->options->getGroup('fp_resv_brevo', []);

        return ($settings['brevo_enabled'] ?? '0') === '1' && $this->client->isConnected();
    }
}
