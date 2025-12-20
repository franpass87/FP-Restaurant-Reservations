<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use FP\Resv\Core\Mailer;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Options;
use function add_action;
use function apply_filters;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final class Manager
{
    private const HOOK_REMINDER = 'fp_resv_send_reminder_email';
    private const HOOK_REVIEW   = 'fp_resv_send_review_email';

    public function __construct(
        private readonly Options $options,
        private readonly Settings $settings,
        private readonly TemplateRenderer $templates,
        private readonly ReservationsRepository $reservations,
        private readonly Mailer $mailer,
        private readonly NotificationScheduler $scheduler,
        private readonly NotificationContextBuilder $contextBuilder,
        private readonly EmailHeadersBuilder $headersBuilder,
        private readonly ManageUrlGenerator $urlGenerator,
        private readonly BrevoEventSender $brevoEventSender
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
        $this->scheduler->maybeScheduleReminder($reservationId, [
            'status' => $status,
            'date'   => (string) ($payload['date'] ?? ''),
            'time'   => (string) ($payload['time'] ?? ''),
        ], [$this, 'dispatchReminder']);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onReservationStatusChanged(int $reservationId, string $previousStatus, string $currentStatus, array $context = []): void
    {
        unset($previousStatus);
        $status = strtolower($currentStatus);

        if (in_array($status, ['cancelled', 'no-show'], true)) {
            $this->scheduler->cancelReminder($reservationId);
            $this->scheduler->cancelReview($reservationId);
            return;
        }

        $this->scheduler->maybeScheduleReminder($reservationId, $context + ['status' => $status], [$this, 'dispatchReminder']);

        $visitedAt = (string) ($context['visited_at'] ?? '');
        if ($status === 'visited' || $visitedAt !== '') {
            $this->scheduler->cancelReminder($reservationId);
            $this->scheduler->maybeScheduleReview($reservationId, $context + ['status' => $status], [$this, 'dispatchReview']);
        }
    }

    public function dispatchReminder(int $reservationId): void
    {
        $reservation = $this->reservations->findAgendaEntry($reservationId);
        if (!is_array($reservation)) {
            return;
        }

        // Se Brevo è configurato per gestire i reminder, invia l'evento invece dell'email
        if ($this->settings->shouldUseBrevo(Settings::CHANNEL_REMINDER) && $this->settings->isEnabled(Settings::CHANNEL_REMINDER)) {
            $this->brevoEventSender->sendReminderEvent($reservationId, $reservation);
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

        $manageUrl = $this->urlGenerator->generate($reservationId, $email);
        $context   = $this->contextBuilder->build($reservationId, $reservation, $manageUrl);

        $rendered = $this->templates->render('reminder', $context + [
            'review_url' => '',
        ]);

        $subject = apply_filters('fp_resv_customer_reminder_subject', $rendered['subject'], $context);
        $body    = apply_filters('fp_resv_customer_reminder_body', $rendered['body'], $context);

        if (trim($subject) === '' || trim($body) === '') {
            return;
        }

        $headers = $this->headersBuilder->build();
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

        // Se Brevo è configurato per gestire le review, invia l'evento invece dell'email
        if ($this->settings->shouldUseBrevo(Settings::CHANNEL_REVIEW) && $this->settings->isEnabled(Settings::CHANNEL_REVIEW)) {
            $this->brevoEventSender->sendReviewEvent($reservationId, $reservation);
            return;
        }

        if (!$this->settings->shouldUsePlugin(Settings::CHANNEL_REVIEW) || !$this->settings->isEnabled(Settings::CHANNEL_REVIEW)) {
            return;
        }

        $email = (string) ($reservation['email'] ?? '');
        if ($email === '') {
            return;
        }

        $manageUrl = $this->urlGenerator->generate($reservationId, $email);
        $context   = $this->contextBuilder->build($reservationId, $reservation, $manageUrl);
        $reviewUrl = $this->settings->reviewUrl();

        $rendered = $this->templates->render('review', $context + [
            'review_url' => $reviewUrl,
        ]);

        $subject = apply_filters('fp_resv_customer_review_subject', $rendered['subject'], $context + ['review_url' => $reviewUrl]);
        $body    = apply_filters('fp_resv_customer_review_body', $rendered['body'], $context + ['review_url' => $reviewUrl]);

        if (trim($subject) === '' || trim($body) === '') {
            return;
        }

        $headers = $this->headersBuilder->build();
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

}
