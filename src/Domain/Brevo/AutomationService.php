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
use function array_key_exists;
use function array_map;
use function array_values;
use function ctype_digit;
use function current_time;
use function explode;
use function home_url;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strpos;
use function strlen;
use function strcmp;
use function strip_tags;
use function strtolower;
use function substr;
use function trim;
use function strtoupper;
use function uksort;
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

        $status = strtolower((string) ($payload['status'] ?? ''));

        $contact = $this->mapper->mapReservation([
            'email'              => $email,
            'first_name'         => $payload['first_name'] ?? '',
            'last_name'          => $payload['last_name'] ?? '',
            'phone'              => $payload['phone'] ?? '',
            'language'           => $payload['language'] ?? '',
            'date'               => $payload['date'] ?? '',
            'time'               => $payload['time'] ?? '',
            'party'              => $payload['party'] ?? '',
            'status'             => $status,
            'location'           => $payload['location'] ?? '',
            'manage_url'         => $manageUrl,
            'notes'              => $payload['notes'] ?? '',
            'marketing_consent'  => $payload['marketing_consent'] ?? null,
            'reservation_id'     => $reservationId,
            'value'              => $payload['value'] ?? null,
            'currency'           => $payload['currency'] ?? '',
            'utm_source'         => $payload['utm_source'] ?? '',
            'utm_medium'         => $payload['utm_medium'] ?? '',
            'utm_campaign'       => $payload['utm_campaign'] ?? '',
            'gclid'              => $payload['gclid'] ?? '',
            'fbclid'             => $payload['fbclid'] ?? '',
            'msclkid'            => $payload['msclkid'] ?? '',
            'ttclid'             => $payload['ttclid'] ?? '',
        ]);

        $this->syncContact($reservationId, $contact);

        $subscriptionContext = [
            'forced_language' => $payload['language_forced'] ?? '',
            'page_language'   => $payload['language'] ?? '',
            'phone'           => $payload['phone'] ?? '',
        ];

        $this->subscribeContact($reservationId, $contact, $subscriptionContext);

        if ($status === 'confirmed') {
            $attributes = $contact['attributes'] ?? [];
            $reservationDate = $attributes['RESERVATION_DATE'] ?? ($payload['date'] ?? '');
            $reservationTime = $attributes['RESERVATION_TIME'] ?? ($payload['time'] ?? '');
            $reservationParty = $attributes['RESERVATION_PARTY'] ?? (isset($payload['party']) ? (int) $payload['party'] : 0);
            $eventProperties = $this->buildEventProperties(
                $contact,
                $attributes,
                [
                    'id'        => $reservationId,
                    'date'      => $reservationDate,
                    'time'      => $reservationTime,
                    'party'     => $reservationParty,
                    'status'    => 'confirmed',
                    'location'  => $payload['location'] ?? '',
                    'manage_url'=> $manageUrl,
                ],
                [
                    'language'          => $payload['language'] ?? '',
                    'notes'             => $payload['notes'] ?? '',
                    'marketing_consent' => $payload['marketing_consent'] ?? null,
                    'utm_source'        => $payload['utm_source'] ?? '',
                    'utm_medium'        => $payload['utm_medium'] ?? '',
                    'utm_campaign'      => $payload['utm_campaign'] ?? '',
                    'gclid'             => $payload['gclid'] ?? '',
                    'fbclid'            => $payload['fbclid'] ?? '',
                    'msclkid'           => $payload['msclkid'] ?? '',
                    'ttclid'            => $payload['ttclid'] ?? '',
                    'value'             => $payload['value'] ?? null,
                    'currency'          => $payload['currency'] ?? '',
                ]
            );

            $this->dispatchEvent(
                'reservation_confirmed',
                $email,
                $eventProperties,
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
            'email'              => $email,
            'first_name'         => $context['first_name'] ?? ($context['customer']['first_name'] ?? ''),
            'last_name'          => $context['last_name'] ?? ($context['customer']['last_name'] ?? ''),
            'phone'              => $context['phone'] ?? ($context['customer']['phone'] ?? ''),
            'language'           => $context['customer']['language'] ?? ($context['customer_lang'] ?? ''),
            'date'               => $context['date'] ?? '',
            'time'               => isset($context['time']) ? substr((string) $context['time'], 0, 5) : '',
            'party'              => $context['party'] ?? 0,
            'status'             => $currentStatus,
            'location'           => $context['location_id'] ?? '',
            'manage_url'         => $context['manage_url'] ?? '',
            'notes'              => $context['notes'] ?? '',
            'marketing_consent'  => $context['marketing_consent'] ?? ($context['customer']['marketing_consent'] ?? null),
            'reservation_id'     => $reservationId,
            'value'              => $context['value'] ?? null,
            'currency'           => $context['currency'] ?? '',
            'utm_source'         => $context['utm_source'] ?? '',
            'utm_medium'         => $context['utm_medium'] ?? '',
            'utm_campaign'       => $context['utm_campaign'] ?? '',
            'gclid'              => $context['gclid'] ?? '',
            'fbclid'             => $context['fbclid'] ?? '',
            'msclkid'            => $context['msclkid'] ?? '',
            'ttclid'             => $context['ttclid'] ?? '',
        ]);

        $this->syncContact($reservationId, $contact);

        $subscriptionContext = [
            'forced_language' => $context['customer']['language'] ?? '',
            'page_language'   => $context['customer_lang'] ?? '',
            'phone'           => $context['phone'] ?? ($context['customer']['phone'] ?? ''),
        ];

        $this->subscribeContact($reservationId, $contact, $subscriptionContext);

        $attributes = $contact['attributes'] ?? [];
        $reservationDate = $attributes['RESERVATION_DATE'] ?? ($context['date'] ?? '');
        $reservationTime = $attributes['RESERVATION_TIME'] ?? (isset($context['time']) ? substr((string) $context['time'], 0, 5) : '');
        $reservationParty = $attributes['RESERVATION_PARTY'] ?? (isset($context['party']) ? (int) $context['party'] : 0);

        if ($currentStatus === 'confirmed' && $previousStatus !== 'confirmed') {
            $eventProperties = $this->buildEventProperties(
                $contact,
                $attributes,
                [
                    'id'        => $reservationId,
                    'date'      => $reservationDate,
                    'time'      => $reservationTime,
                    'party'     => $reservationParty,
                    'status'    => 'confirmed',
                    'location'  => $context['location_id'] ?? '',
                    'manage_url'=> $context['manage_url'] ?? '',
                ],
                [
                    'language'          => $context['customer_lang'] ?? ($context['customer']['language'] ?? ''),
                    'notes'             => $context['notes'] ?? '',
                    'marketing_consent' => $context['marketing_consent'] ?? ($context['customer']['marketing_consent'] ?? null),
                    'utm_source'        => $context['utm_source'] ?? '',
                    'utm_medium'        => $context['utm_medium'] ?? '',
                    'utm_campaign'      => $context['utm_campaign'] ?? '',
                    'gclid'             => $context['gclid'] ?? '',
                    'fbclid'            => $context['fbclid'] ?? '',
                    'msclkid'           => $context['msclkid'] ?? '',
                    'ttclid'            => $context['ttclid'] ?? '',
                    'value'             => $context['value'] ?? null,
                    'currency'          => $context['currency'] ?? '',
                ]
            );

            $this->dispatchEvent(
                'reservation_confirmed',
                $email,
                $eventProperties,
                $reservationId
            );
        }

        if ($currentStatus === 'visited') {
            $eventProperties = $this->buildEventProperties(
                $contact,
                $attributes,
                [
                    'id'        => $reservationId,
                    'date'      => $context['date'] ?? '',
                    'time'      => isset($context['time']) ? substr((string) $context['time'], 0, 5) : '',
                    'party'     => isset($context['party']) ? (int) $context['party'] : 0,
                    'status'    => $currentStatus,
                    'location'  => $context['location_id'] ?? '',
                    'manage_url'=> $context['manage_url'] ?? '',
                ],
                [
                    'language'          => $context['customer_lang'] ?? ($context['customer']['language'] ?? ''),
                    'notes'             => $context['notes'] ?? '',
                    'marketing_consent' => $context['marketing_consent'] ?? ($context['customer']['marketing_consent'] ?? null),
                    'utm_source'        => $context['utm_source'] ?? '',
                    'utm_medium'        => $context['utm_medium'] ?? '',
                    'utm_campaign'      => $context['utm_campaign'] ?? '',
                    'gclid'             => $context['gclid'] ?? '',
                    'fbclid'            => $context['fbclid'] ?? '',
                    'msclkid'           => $context['msclkid'] ?? '',
                    'ttclid'            => $context['ttclid'] ?? '',
                    'value'             => $context['value'] ?? null,
                    'currency'          => $context['currency'] ?? '',
                ]
            );
            $eventProperties['reservation']['visited_at'] = $context['visited_at'] ?? current_time('mysql');

            $this->dispatchEvent(
                'reservation_visited',
                $email,
                $eventProperties,
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

                $contact = $this->mapper->mapReservation([
                    'email'              => $email,
                    'first_name'         => $reservation['first_name'] ?? '',
                    'last_name'          => $reservation['last_name'] ?? '',
                    'phone'              => $reservation['phone'] ?? '',
                    'language'           => $reservation['customer_lang'] ?? '',
                    'date'               => $reservation['date'] ?? '',
                    'time'               => isset($reservation['time']) ? substr((string) $reservation['time'], 0, 5) : '',
                    'party'              => $reservation['party'] ?? 0,
                    'status'             => $reservation['status'] ?? '',
                    'location'           => $reservation['location_id'] ?? '',
                    'notes'              => $reservation['notes'] ?? '',
                    'marketing_consent'  => $reservation['marketing_consent'] ?? null,
                    'reservation_id'     => $reservationId,
                    'value'              => $reservation['value'] ?? null,
                    'currency'           => $reservation['currency'] ?? '',
                    'utm_source'         => $reservation['utm_source'] ?? '',
                    'utm_medium'         => $reservation['utm_medium'] ?? '',
                    'utm_campaign'       => $reservation['utm_campaign'] ?? '',
                    'gclid'              => $reservation['gclid'] ?? '',
                    'fbclid'             => $reservation['fbclid'] ?? '',
                    'msclkid'            => $reservation['msclkid'] ?? '',
                    'ttclid'             => $reservation['ttclid'] ?? '',
                ]);

                $attributes = $contact['attributes'] ?? [];

                $eventProperties = $this->buildEventProperties(
                    $contact,
                    $attributes,
                    [
                        'id'       => $reservationId,
                        'date'     => $reservation['date'] ?? '',
                        'time'     => isset($reservation['time']) ? $this->mapper->normalizeTime($reservation['time']) : '',
                        'party'    => isset($reservation['party']) ? (int) $reservation['party'] : 0,
                        'status'   => $reservation['status'] ?? '',
                        'location' => $reservation['location_id'] ?? '',
                    ],
                    [
                        'language'          => $reservation['customer_lang'] ?? '',
                        'notes'             => $reservation['notes'] ?? '',
                        'marketing_consent' => $reservation['marketing_consent'] ?? null,
                        'utm_source'        => $reservation['utm_source'] ?? '',
                        'utm_medium'        => $reservation['utm_medium'] ?? '',
                        'utm_campaign'      => $reservation['utm_campaign'] ?? '',
                        'gclid'             => $reservation['gclid'] ?? '',
                        'fbclid'            => $reservation['fbclid'] ?? '',
                        'msclkid'           => $reservation['msclkid'] ?? '',
                        'ttclid'            => $reservation['ttclid'] ?? '',
                        'value'             => $reservation['value'] ?? null,
                        'currency'          => $reservation['currency'] ?? '',
                        'visited_at'        => $reservation['visited_at'] ?? '',
                    ]
                );
                $eventProperties['reservation']['surveyUrl'] = $surveyUrl;

                $this->dispatchEvent(
                    'post_visit_24h',
                    $email,
                    $eventProperties,
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
     *
     * @return array<string, mixed>
     */
    private function syncContact(int $reservationId, array $contact, ?int $listId = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Brevo integration disabled',
                'code'    => 0,
            ];
        }

        $payload = $contact;

        if ($listId !== null) {
            $payload['listIds'] = [$listId];
        } else {
            $listIds = $this->defaultListIds();
            if ($listIds !== []) {
                $payload['listIds'] = $listIds;
            }
        }

        $response = $this->client->upsertContact($payload);
        $status   = !empty($response['success']) ? 'success' : 'error';

        $this->repository->log($reservationId, 'contact_upsert', [
            'payload'  => $payload,
            'response' => $response,
        ], $status, !empty($response['success']) ? null : ($response['message'] ?? null));

        return $response;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function buildEventProperties(
        array $contact,
        array $attributes,
        array $reservation,
        array $meta = []
    ): array {
        $reservationPayload = array_filter(
            $reservation,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        $metaPayload = array_filter(
            $meta,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        $properties = [
            'reservation' => $reservationPayload,
            'contact'     => array_filter(
                [
                    'email'      => $contact['email'] ?? '',
                    'first_name' => $attributes['FIRSTNAME'] ?? '',
                    'last_name'  => $attributes['LASTNAME'] ?? '',
                    'phone'      => $attributes['PHONE'] ?? '',
                ],
                static fn ($value): bool => $value !== null && $value !== ''
            ),
            'attributes'  => $attributes,
        ];

        if ($metaPayload !== []) {
            $properties['meta'] = $metaPayload;
        }

        foreach ($attributes as $key => $value) {
            if (!array_key_exists($key, $properties)) {
                $properties[$key] = $value;
            }
        }

        return $properties;
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
        $emails   = $this->parseEmails($settings['restaurant_emails'] ?? []);
        $emails   = array_merge($emails, $this->parseEmails($settings['webmaster_emails'] ?? []));
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

    /**
     * @param string|array<int|string, mixed> $list
     */
    private function parseEmails(string|array $list): array
    {
        if (is_array($list)) {
            $candidates = array_map(static fn ($email): string => trim((string) $email), $list);
        } else {
            if ($list === '') {
                return [];
            }

            $candidates = array_map('trim', preg_split('/[,;\n]/', $list) ?: []);
        }

        $valid = [];

        foreach ($candidates as $email) {
            if ($email === '') {
                continue;
            }

            $sanitized = sanitize_email($email);
            if ($sanitized === '' || !is_email($sanitized)) {
                continue;
            }

            $valid[] = $sanitized;
        }

        return array_values(array_unique($valid));
    }

    private function defaultListIds(): array
    {
        $settings = $this->options->getGroup('fp_resv_brevo', []);
        $value    = (string) ($settings['brevo_list_id'] ?? '');
        if ($value === '') {
            return [];
        }

        $ids    = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $id): bool => $id !== ''));
        $result = [];

        foreach ($ids as $id) {
            if ($id === '') {
                continue;
            }

            if (!ctype_digit($id)) {
                $id = preg_replace('/[^0-9]/', '', $id);
                if (!is_string($id) || $id === '') {
                    continue;
                }
            }

            $intId = (int) $id;
            if ($intId > 0) {
                $result[] = $intId;
            }
        }

        return $result;
    }

    private function listIdForKey(string $key): ?int
    {
        $settings   = $this->options->getGroup('fp_resv_brevo', []);
        $key        = strtoupper($key);
        $candidates = [];

        if ($key === 'IT') {
            $candidates[] = (string) ($settings['brevo_list_id_it'] ?? '');
        } elseif ($key === 'EN') {
            $candidates[] = (string) ($settings['brevo_list_id_en'] ?? '');
        } else {
            $candidates[] = (string) ($settings['brevo_list_id_en'] ?? '');
            $candidates[] = (string) ($settings['brevo_list_id_it'] ?? '');
        }

        $candidates[] = (string) ($settings['brevo_list_id'] ?? '');

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (!ctype_digit($candidate)) {
                $candidate = preg_replace('/[^0-9]/', '', $candidate);
                if (!is_string($candidate) || $candidate === '') {
                    continue;
                }
            }

            $listId = (int) $candidate;
            if ($listId > 0) {
                return $listId;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function phonePrefixMap(): array
    {
        $settings = $this->options->getGroup('fp_resv_brevo', []);
        $raw      = $settings['brevo_phone_prefix_map'] ?? '';
        $map      = [];

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $prefix => $language) {
                    if (!is_string($prefix) || !is_string($language)) {
                        continue;
                    }

                    $normalizedPrefix = $this->normalizePhonePrefix($prefix);
                    if ($normalizedPrefix === '') {
                        continue;
                    }

                    $languageCode = $this->normalizeLanguage($language, true);
                    if ($languageCode === '') {
                        $languageCode = 'INT';
                    }

                    $map[$normalizedPrefix] = $languageCode;
                }
            }
        }

        if ($map === []) {
            $map['+39'] = 'IT';
        }

        uksort($map, static function (string $a, string $b): int {
            $lengthComparison = strlen($b) <=> strlen($a);

            return $lengthComparison !== 0 ? $lengthComparison : strcmp($a, $b);
        });

        return $map;
    }

    /**
     * @param array<string, string> $map
     */
    public function parsePhoneCountry(string $phone, array $map): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return 'INT';
        }

        $normalized = preg_replace('/[^0-9+]/', '', $normalized);
        if (!is_string($normalized) || $normalized === '') {
            return 'INT';
        }

        if ($normalized[0] !== '+') {
            if (strpos($normalized, '00') === 0) {
                $normalized = '+' . substr($normalized, 2);
            } else {
                $normalized = '+' . $normalized;
            }
        }

        $normalizedMap = [];
        foreach ($map as $prefix => $language) {
            if (!is_string($prefix) || !is_string($language)) {
                continue;
            }

            $cleanPrefix = $this->normalizePhonePrefix($prefix);
            if ($cleanPrefix === '') {
                continue;
            }

            $normalizedMap[$cleanPrefix] = $this->normalizeLanguage($language, true);
        }

        uksort($normalizedMap, static function (string $a, string $b): int {
            $lengthComparison = strlen($b) <=> strlen($a);

            return $lengthComparison !== 0 ? $lengthComparison : strcmp($a, $b);
        });

        foreach ($normalizedMap as $prefix => $languageCode) {
            if (strpos($normalized, $prefix) === 0) {
                if ($languageCode === 'IT' || $languageCode === 'EN') {
                    return $languageCode;
                }

                return 'INT';
            }
        }

        return 'INT';
    }

    /**
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $context
     */
    private function subscribeContact(int $reservationId, array $contact, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $map          = $this->phonePrefixMap();
        $forcedLang   = $this->normalizeLanguage((string) ($context['forced_language'] ?? ''), true);
        $pageLanguage = $this->normalizeLanguage((string) ($context['page_language'] ?? ''), false);

        $phone = (string) ($context['phone'] ?? '');
        if ($phone === '' && isset($contact['attributes']['PHONE'])) {
            $phone = (string) $contact['attributes']['PHONE'];
        }

        $phoneCountry = $this->parsePhoneCountry($phone, $map);
        $targetKey    = $this->resolveListKey($forcedLang, $phoneCountry, $pageLanguage);

        $listId = $this->listIdForKey($targetKey);
        if ($listId === null && $targetKey !== 'INT') {
            $listId = $this->listIdForKey('INT');
        }

        if ($listId === null) {
            $this->repository->log($reservationId, 'subscribe', [
                'list'            => $targetKey,
                'list_key'        => $targetKey,
                'forced_language' => $forcedLang,
                'page_language'   => $pageLanguage,
                'phone_country'   => $phoneCountry,
                'phone'           => $phone,
            ], 'error', 'Missing list ID for subscription');

            return;
        }

        $response = $this->syncContact($reservationId, $contact, $listId);
        $success  = !empty($response['success']);

        $this->repository->log($reservationId, 'subscribe', [
            'list'            => $targetKey,
            'list_key'        => $targetKey,
            'list_id'         => $listId,
            'forced_language' => $forcedLang,
            'page_language'   => $pageLanguage,
            'phone_country'   => $phoneCountry,
            'phone'           => $phone,
        ], $success ? 'success' : 'error', $success ? null : ($response['message'] ?? null));
    }

    private function resolveListKey(string $forced, string $phoneCountry, string $pageLanguage): string
    {
        if ($forced !== '') {
            return $forced;
        }

        if ($phoneCountry === 'IT' || $phoneCountry === 'EN') {
            return $phoneCountry;
        }

        if ($pageLanguage === 'IT' || $pageLanguage === 'EN') {
            return $pageLanguage;
        }

        return 'INT';
    }

    private function normalizeLanguage(string $value, bool $allowInternational = false): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return '';
        }

        if (strpos($upper, 'IT') === 0) {
            return 'IT';
        }

        if (strpos($upper, 'EN') === 0) {
            return 'EN';
        }

        if ($allowInternational && strpos($upper, 'INT') === 0) {
            return 'INT';
        }

        return '';
    }

    private function normalizePhonePrefix(string $prefix): string
    {
        $normalized = str_replace(' ', '', trim($prefix));
        if ($normalized === '') {
            return '';
        }

        if (strpos($normalized, '00') === 0) {
            $normalized = '+' . substr($normalized, 2);
        }

        if (strpos($normalized, '+') !== 0) {
            $normalized = '+' . ltrim($normalized, '+');
        }

        return $normalized === '+' ? '' : $normalized;
    }

    private function isEnabled(): bool
    {
        $settings = $this->options->getGroup('fp_resv_brevo', []);

        return ($settings['brevo_enabled'] ?? '0') === '1' && $this->client->isConnected();
    }
}
