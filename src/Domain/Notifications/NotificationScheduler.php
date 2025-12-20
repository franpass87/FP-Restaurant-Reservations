<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use function strtolower;
use function time;
use function wp_get_scheduled_event;
use function wp_schedule_single_event;
use function wp_unschedule_event;

/**
 * Gestisce lo scheduling degli eventi di notifica (reminder e review).
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class NotificationScheduler
{
    private const HOOK_REMINDER = 'fp_resv_send_reminder_email';
    private const HOOK_REVIEW   = 'fp_resv_send_review_email';

    public function __construct(
        private readonly Settings $settings,
        private readonly TimestampCalculator $timestampCalculator
    ) {
    }

    /**
     * Schedula un reminder se necessario.
     *
     * @param int $reservationId
     * @param array<string, mixed> $data
     * @param callable(int): void $dispatchCallback
     */
    public function maybeScheduleReminder(int $reservationId, array $data, callable $dispatchCallback): void
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

        $timestamp = $this->timestampCalculator->computeReminderTimestamp(
            (string) ($data['date'] ?? ''),
            (string) ($data['time'] ?? '')
        );
        
        if ($timestamp === null) {
            $this->cancelReminder($reservationId);
            return;
        }

        $this->scheduleEvent(self::HOOK_REMINDER, $timestamp, [$reservationId], $dispatchCallback);
    }

    /**
     * Schedula una review se necessario.
     *
     * @param int $reservationId
     * @param array<string, mixed> $data
     * @param callable(int): void $dispatchCallback
     */
    public function maybeScheduleReview(int $reservationId, array $data, callable $dispatchCallback): void
    {
        if (!$this->settings->shouldUsePlugin(Settings::CHANNEL_REVIEW) || !$this->settings->isEnabled(Settings::CHANNEL_REVIEW)) {
            $this->cancelReview($reservationId);
            return;
        }

        $timestamp = $this->timestampCalculator->computeReviewTimestamp(
            (string) ($data['visited_at'] ?? ''),
            (string) ($data['date'] ?? ''),
            (string) ($data['time'] ?? '')
        );

        if ($timestamp === null) {
            $this->cancelReview($reservationId);
            return;
        }

        $this->scheduleEvent(self::HOOK_REVIEW, $timestamp, [$reservationId], $dispatchCallback);
    }

    /**
     * Cancella un reminder schedulato.
     */
    public function cancelReminder(int $reservationId): void
    {
        $this->cancelEvent(self::HOOK_REMINDER, [$reservationId]);
    }

    /**
     * Cancella una review schedulata.
     */
    public function cancelReview(int $reservationId): void
    {
        $this->cancelEvent(self::HOOK_REVIEW, [$reservationId]);
    }

    /**
     * Cancella un evento schedulato.
     */
    private function cancelEvent(string $hook, array $args): void
    {
        $existing = wp_get_scheduled_event($hook, $args);
        if ($existing !== false && $existing !== null) {
            wp_unschedule_event($existing->timestamp, $hook, $args);
        }
    }

    /**
     * Schedula un evento.
     *
     * @param callable(int): void $dispatchCallback
     */
    private function scheduleEvent(string $hook, int $timestamp, array $args, callable $dispatchCallback): void
    {
        $existing = wp_get_scheduled_event($hook, $args);
        if ($existing !== false && $existing !== null) {
            if ($existing->timestamp === $timestamp) {
                return;
            }
            wp_unschedule_event($existing->timestamp, $hook, $args);
        }

        if ($timestamp <= time()) {
            $dispatchCallback((int) ($args[0] ?? 0));
            return;
        }

        wp_schedule_single_event($timestamp, $hook, $args);
    }
}

