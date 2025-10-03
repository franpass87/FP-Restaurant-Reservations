<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP\Resv\Core\Mailer;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Options;
use function add_action;
use function add_query_arg;
use function apply_filters;
use function esc_url_raw;
use function home_url;
use function in_array;
use function is_array;
use function is_string;
use function hash_hmac;
use function strtolower;
use function time;
use function trailingslashit;
use function trim;
use function sprintf;
use function substr;
use function wp_get_scheduled_event;
use function wp_schedule_single_event;
use function wp_salt;
use function wp_unschedule_event;

final class Manager
{
    private const HOOK_REMINDER = 'fp_resv_send_reminder_email';
    private const HOOK_REVIEW   = 'fp_resv_send_review_email';

    public function __construct(
        private readonly Options $options,
        private readonly Settings $settings,
        private readonly TemplateRenderer $templates,
        private readonly ReservationsRepository $reservations,
        private readonly Mailer $mailer
    ) {
    }

    public function boot(): void
    {
        add_action('fp_resv_reservation_created', [$this, 'onReservationCreated'], 20, 4);
        add_action('fp_resv_reservation_status_changed', [$this, 'onReservationStatusChanged'], 20, 4);
        add_action(self::HOOK_REMINDER, [$this, 'dispatchReminder'], 10, 1);
        add_action(self::HOOK_REVIEW, [$this, 'dispatchReview'], 10, 1);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function onReservationCreated(int $reservationId, array $payload, mixed $reservation, string $manageUrl = ''): void
    {
        unset($reservation, $manageUrl);

        $status = strtolower((string) ($payload['status'] ?? ''));
        $this->maybeScheduleReminder($reservationId, [
            'status' => $status,
            'date'   => (string) ($payload['date'] ?? ''),
            'time'   => (string) ($payload['time'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onReservationStatusChanged(int $reservationId, string $previousStatus, string $currentStatus, array $context = []): void
    {
        unset($previousStatus);
        $status = strtolower($currentStatus);

        if (in_array($status, ['cancelled', 'no-show'], true)) {
            $this->cancelReminder($reservationId);
            $this->cancelReview($reservationId);

            return;
        }

        $this->maybeScheduleReminder($reservationId, $context + ['status' => $status]);

        $visitedAt = (string) ($context['visited_at'] ?? '');
        if ($status === 'visited' || $visitedAt !== '') {
            $this->cancelReminder($reservationId);
            $this->maybeScheduleReview($reservationId, $context + ['status' => $status]);
        }
    }

    public function dispatchReminder(int $reservationId): void
    {
        $reservation = $this->reservations->findAgendaEntry($reservationId);
        if (!is_array($reservation)) {
            return;
        }

        if (!$this->settings->shouldUsePlugin(Settings::CHANNEL_REMINDER) || !$this->settings->isEnabled(Settings::CHANNEL_REMINDER)) {
            return;
        }

        $status = strtolower((string) ($reservation['status'] ?? ''));
        if ($status !== 'confirmed') {
            return;
        }

        $email = (string) ($reservation['email'] ?? '');
        if ($email === '') {
            return;
        }

        $manageUrl = $this->generateManageUrl($reservationId, $email);
        $context   = $this->buildContext($reservationId, $reservation, $manageUrl);

        $rendered = $this->templates->render('reminder', $context + [
            'review_url' => '',
        ]);

        $subject = apply_filters('fp_resv_customer_reminder_subject', $rendered['subject'], $context);
        $body    = apply_filters('fp_resv_customer_reminder_body', $rendered['body'], $context);

        if (trim($subject) === '' || trim($body) === '') {
            return;
        }

        $headers = $this->buildHeaders();
        $this->mailer->send(
            $email,
            $subject,
            $body,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'customer_reminder',
                'content_type'   => 'text/html',
            ]
        );
    }

    public function dispatchReview(int $reservationId): void
    {
        $reservation = $this->reservations->findAgendaEntry($reservationId);
        if (!is_array($reservation)) {
            return;
        }

        if (!$this->settings->shouldUsePlugin(Settings::CHANNEL_REVIEW) || !$this->settings->isEnabled(Settings::CHANNEL_REVIEW)) {
            return;
        }

        $email = (string) ($reservation['email'] ?? '');
        if ($email === '') {
            return;
        }

        $manageUrl = $this->generateManageUrl($reservationId, $email);
        $context   = $this->buildContext($reservationId, $reservation, $manageUrl);
        $reviewUrl = $this->settings->reviewUrl();

        $rendered = $this->templates->render('review', $context + [
            'review_url' => $reviewUrl,
        ]);

        $subject = apply_filters('fp_resv_customer_review_subject', $rendered['subject'], $context + ['review_url' => $reviewUrl]);
        $body    = apply_filters('fp_resv_customer_review_body', $rendered['body'], $context + ['review_url' => $reviewUrl]);

        if (trim($subject) === '' || trim($body) === '') {
            return;
        }

        $headers = $this->buildHeaders();
        $this->mailer->send(
            $email,
            $subject,
            $body,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'customer_review',
                'content_type'   => 'text/html',
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function maybeScheduleReminder(int $reservationId, array $data): void
    {
        if (!$this->settings->shouldUsePlugin(Settings::CHANNEL_REMINDER) || !$this->settings->isEnabled(Settings::CHANNEL_REMINDER)) {
            $this->cancelReminder($reservationId);

            return;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        if ($status !== 'confirmed') {
            $this->cancelReminder($reservationId);

            return;
        }

        $timestamp = $this->computeReminderTimestamp((string) ($data['date'] ?? ''), (string) ($data['time'] ?? ''));
        if ($timestamp === null) {
            $this->cancelReminder($reservationId);

            return;
        }

        $this->scheduleEvent(self::HOOK_REMINDER, $timestamp, [$reservationId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function maybeScheduleReview(int $reservationId, array $data): void
    {
        if (!$this->settings->shouldUsePlugin(Settings::CHANNEL_REVIEW) || !$this->settings->isEnabled(Settings::CHANNEL_REVIEW)) {
            $this->cancelReview($reservationId);

            return;
        }

        $timestamp = $this->computeReviewTimestamp(
            (string) ($data['visited_at'] ?? ''),
            (string) ($data['date'] ?? ''),
            (string) ($data['time'] ?? '')
        );

        if ($timestamp === null) {
            $this->cancelReview($reservationId);

            return;
        }

        $this->scheduleEvent(self::HOOK_REVIEW, $timestamp, [$reservationId]);
    }

    private function cancelReminder(int $reservationId): void
    {
        $this->cancelEvent(self::HOOK_REMINDER, [$reservationId]);
    }

    private function cancelReview(int $reservationId): void
    {
        $this->cancelEvent(self::HOOK_REVIEW, [$reservationId]);
    }

    private function cancelEvent(string $hook, array $args): void
    {
        $existing = wp_get_scheduled_event($hook, $args);
        if ($existing !== false && $existing !== null) {
            wp_unschedule_event($existing->timestamp, $hook, $args);
        }
    }

    private function scheduleEvent(string $hook, int $timestamp, array $args): void
    {
        $existing = wp_get_scheduled_event($hook, $args);
        if ($existing !== false && $existing !== null) {
            if ($existing->timestamp === $timestamp) {
                return;
            }

            wp_unschedule_event($existing->timestamp, $hook, $args);
        }

        if ($timestamp <= time()) {
            if ($hook === self::HOOK_REMINDER) {
                $this->dispatchReminder((int) ($args[0] ?? 0));
            } elseif ($hook === self::HOOK_REVIEW) {
                $this->dispatchReview((int) ($args[0] ?? 0));
            }

            return;
        }

        wp_schedule_single_event($timestamp, $hook, $args);
    }

    private function computeReminderTimestamp(string $date, string $time): ?int
    {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') {
            return null;
        }

        $time = substr($time, 0, 5);

        try {
            $timezone  = new DateTimeZone($this->restaurantTimezone());
            $dateTime  = new DateTimeImmutable($date . ' ' . $time, $timezone);
            $offset    = $this->settings->offsetHours(Settings::CHANNEL_REMINDER, 4);
            $scheduled = $dateTime->sub(new DateInterval('PT' . $offset . 'H'));
        } catch (Exception) {
            return null;
        }

        return $scheduled->getTimestamp();
    }

    private function computeReviewTimestamp(string $visitedAt, string $date, string $time): ?int
    {
        try {
            $timezone = new DateTimeZone($this->restaurantTimezone());
        } catch (Exception) {
            $timezone = new DateTimeZone('Europe/Rome');
        }

        try {
            if ($visitedAt !== '') {
                $reference = new DateTimeImmutable($visitedAt, $timezone);
            } elseif ($date !== '' && $time !== '') {
                $reference = new DateTimeImmutable($date . ' ' . substr($time, 0, 5), $timezone);
            } else {
                return null;
            }

            $delay     = $this->settings->offsetHours(Settings::CHANNEL_REVIEW, 24);
            $scheduled = $reference->add(new DateInterval('PT' . $delay . 'H'));
        } catch (Exception) {
            return null;
        }

        return $scheduled->getTimestamp();
    }

    /**
     * @param array<string, mixed> $reservation
     *
     * @return array<string, mixed>
     */
    private function buildContext(int $reservationId, array $reservation, string $manageUrl): array
    {
        $time = (string) ($reservation['time'] ?? '');
        if ($time !== '') {
            $time = substr($time, 0, 5);
        }

        $restaurantName = $this->restaurantName();

        return [
            'id'         => $reservationId,
            'status'     => (string) ($reservation['status'] ?? ''),
            'date'       => (string) ($reservation['date'] ?? ''),
            'time'       => $time,
            'party'      => isset($reservation['party']) ? (int) $reservation['party'] : '',
            'language'   => (string) ($reservation['customer_lang'] ?? ''),
            'manage_url' => $manageUrl,
            'customer'   => [
                'first_name' => (string) ($reservation['first_name'] ?? ''),
                'last_name'  => (string) ($reservation['last_name'] ?? ''),
            ],
            'restaurant' => [
                'name' => $restaurantName,
            ],
        ];
    }

    private function buildHeaders(): array
    {
        $notifications = $this->settings->all();

        $headers     = [];
        $senderEmail = (string) ($notifications['sender_email'] ?? '');
        $senderName  = (string) ($notifications['sender_name'] ?? '');

        if ($senderEmail !== '') {
            $from = $senderEmail;
            if ($senderName !== '') {
                $from = sprintf('%s <%s>', $senderName, $senderEmail);
            }

            $headers[] = 'From: ' . $from;
        }

        $replyTo = (string) ($notifications['reply_to_email'] ?? '');
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return $headers;
    }

    private function generateManageUrl(int $reservationId, string $email): string
    {
        $base = trailingslashit(apply_filters('fp_resv_manage_base_url', home_url('/')));
        $token = $this->generateManageToken($reservationId, $email);

        return esc_url_raw(add_query_arg([
            'fp_resv_manage' => $reservationId,
            'fp_resv_token'  => $token,
        ], $base));
    }

    private function generateManageToken(int $reservationId, string $email): string
    {
        $email = strtolower(trim($email));
        $data  = sprintf('%d|%s', $reservationId, $email);

        return hash_hmac('sha256', $data, wp_salt('fp_resv_manage'));
    }

    private function restaurantTimezone(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_timezone' => 'Europe/Rome',
        ]);

        $timezone = (string) ($general['restaurant_timezone'] ?? 'Europe/Rome');
        if ($timezone === '') {
            $timezone = 'Europe/Rome';
        }

        return $timezone;
    }

    private function restaurantName(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_name' => '',
        ]);

        return (string) ($general['restaurant_name'] ?? '');
    }
}
