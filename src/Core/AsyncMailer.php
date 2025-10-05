<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_action;
use function as_enqueue_async_action;
use function function_exists;

final class AsyncMailer
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Initialize async email handlers.
     */
    public function boot(): void
    {
        add_action('fp_resv_send_customer_email', [$this, 'handleCustomerEmail'], 10, 1);
        add_action('fp_resv_send_staff_notification', [$this, 'handleStaffNotification'], 10, 1);
    }

    /**
     * Queue customer confirmation email for async sending.
     * 
     * @param array<string, mixed> $data
     */
    public function queueCustomerEmail(array $data): void
    {
        if ($this->shouldUseAsync()) {
            as_enqueue_async_action(
                'fp_resv_send_customer_email',
                [$data],
                'fp-resv-emails'
            );
        } else {
            // Fallback to sync if Action Scheduler not available
            $this->handleCustomerEmail($data);
        }
    }

    /**
     * Queue staff notification email for async sending.
     * 
     * @param array<string, mixed> $data
     */
    public function queueStaffNotification(array $data): void
    {
        if ($this->shouldUseAsync()) {
            as_enqueue_async_action(
                'fp_resv_send_staff_notification',
                [$data],
                'fp-resv-emails'
            );
        } else {
            // Fallback to sync if Action Scheduler not available
            $this->handleStaffNotification($data);
        }
    }

    /**
     * Handle async customer email sending.
     * 
     * @param array<string, mixed> $data
     */
    public function handleCustomerEmail(array $data): void
    {
        $to = (string) ($data['to'] ?? '');
        $subject = (string) ($data['subject'] ?? '');
        $message = (string) ($data['message'] ?? '');
        $headers = $data['headers'] ?? [];
        $attachments = $data['attachments'] ?? [];
        $meta = $data['meta'] ?? [];

        if ($to === '' || $subject === '' || $message === '') {
            Logging::log('mail', 'Async customer email missing required fields', [
                'data' => $data,
            ]);
            return;
        }

        $this->mailer->send($to, $subject, $message, $headers, $attachments, $meta);
    }

    /**
     * Handle async staff notification sending.
     * 
     * @param array<string, mixed> $data
     */
    public function handleStaffNotification(array $data): void
    {
        $to = (string) ($data['to'] ?? '');
        $subject = (string) ($data['subject'] ?? '');
        $message = (string) ($data['message'] ?? '');
        $headers = $data['headers'] ?? [];
        $attachments = $data['attachments'] ?? [];
        $meta = $data['meta'] ?? [];

        if ($to === '' || $subject === '' || $message === '') {
            Logging::log('mail', 'Async staff notification missing required fields', [
                'data' => $data,
            ]);
            return;
        }

        $this->mailer->send($to, $subject, $message, $headers, $attachments, $meta);
    }

    /**
     * Check if async email sending should be used.
     * Requires Action Scheduler (included in WooCommerce and other plugins).
     */
    private function shouldUseAsync(): bool
    {
        // Check if Action Scheduler is available
        if (!function_exists('as_enqueue_async_action')) {
            return false;
        }

        // Allow disabling via constant
        if (defined('FP_RESV_DISABLE_ASYNC_EMAIL') && FP_RESV_DISABLE_ASYNC_EMAIL) {
            return false;
        }

        return true;
    }
}
