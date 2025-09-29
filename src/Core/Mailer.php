<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_action;
use function apply_filters;
use function as_schedule_single_action;
use function current_time;
use function file_exists;
use function file_put_contents;
use function function_exists;
use function is_array;
use function mb_substr;
use function preg_split;
use function rename;
use function sanitize_file_name;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function uniqid;
use function trim;
use function wp_mail;
use function wp_strip_all_tags;

final class Mailer
{
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $attachments
     * @param array<string, mixed> $context
     */
    public function send(
        string $to,
        string $subject,
        string $message,
        array $headers = [],
        array $attachments = [],
        array $context = []
    ): bool {
        [$preparedAttachments, $temporaryFiles] = $this->prepareAttachments($attachments, $context);

        $headers = $this->normalizeHeaders($headers, $context);

        $success = wp_mail($to, $subject, $message, $headers, $preparedAttachments);

        $this->logEmail($to, $subject, $message, $context, $success);

        if (!$success) {
            $this->scheduleRetry($to, $subject, $message, $headers, $attachments, $context);
        }

        foreach ($temporaryFiles as $file) {
            if (is_string($file) && $file !== '' && file_exists($file)) {
                @unlink($file);
            }
        }

        return $success;
    }

    public function registerHooks(): void
    {
        add_action('fp_resv_retry_email', [$this, 'handleRetry']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handleRetry(array $data): void
    {
        $attempt = (int) ($data['context']['attempt'] ?? 1);
        if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
            Logging::log('mail', 'Max retry attempts reached', $data['context'] ?? []);

            return;
        }

        $context             = is_array($data['context'] ?? null) ? $data['context'] : [];
        $context['attempt']   = $attempt + 1;
        $context['retry_uid'] = uniqid('fp_resv_retry_', true);

        $this->send(
            (string) ($data['to'] ?? ''),
            (string) ($data['subject'] ?? ''),
            (string) ($data['message'] ?? ''),
            is_array($data['headers'] ?? null) ? $data['headers'] : [],
            is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
            $context
        );
    }

    /**
     * @param array<int, string> $headers
     *
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers, array $context): array
    {
        $normalized = [];
        $hasContentType = false;

        foreach ($headers as $header) {
            $normalized[] = $header;
            if (stripos($header, 'content-type:') === 0) {
                $hasContentType = true;
            }
        }

        if (!$hasContentType && ($context['content_type'] ?? 'text/html') === 'text/html') {
            $normalized[] = 'Content-Type: text/html; charset=UTF-8';
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $attachments
     * @param array<string, mixed> $context
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function prepareAttachments(array $attachments, array $context): array
    {
        $prepared = $attachments;
        $temporary = [];

        if (isset($context['ics_content']) && is_string($context['ics_content']) && $context['ics_content'] !== '') {
            $filename = (string) ($context['ics_filename'] ?? 'reservation.ics');
            $icsPath  = $this->createTemporaryAttachment($filename, $context['ics_content']);
            if ($icsPath !== null) {
                $prepared[] = $icsPath;
                $temporary[] = $icsPath;
            }
        }

        return [$prepared, $temporary];
    }

    private function createTemporaryAttachment(string $filename, string $content): ?string
    {
        $temp = tempnam(sys_get_temp_dir(), 'fp-resv-');
        if ($temp === false) {
            return null;
        }

        $target = $temp . '-' . sanitize_file_name($filename);
        if (!rename($temp, $target)) {
            $target = $temp;
        }

        $written = file_put_contents($target, $content);
        if ($written === false) {
            @unlink($target);

            return null;
        }

        return $target;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logEmail(string $to, string $subject, string $message, array $context, bool $success): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fp_mail_log';
        $firstLine = $this->extractFirstLine($message);
        $status    = $success ? 'sent' : 'failed';
        $error     = $success ? null : ($context['error'] ?? 'wp_mail returned false');

        $data = [
            'reservation_id' => isset($context['reservation_id']) ? (int) $context['reservation_id'] : null,
            'to_emails'      => $to,
            'subject'        => $subject,
            'first_line'     => $firstLine,
            'status'         => $status,
            'error'          => $error,
            'created_at'     => current_time('mysql'),
        ];

        $wpdb->insert($table, $data);
    }

    private function extractFirstLine(string $message): string
    {
        $stripped = trim(wp_strip_all_tags($message));
        $line     = preg_split('/\r?\n/', $stripped, 2)[0] ?? '';

        return mb_substr($line, 0, 191);
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed> $context
     */
    private function scheduleRetry(
        string $to,
        string $subject,
        string $message,
        array $headers,
        array $attachments,
        array $context
    ): void {
        if (!function_exists('as_schedule_single_action')) {
            Logging::log('mail', 'wp_mail failed and Action Scheduler not available', [
                'to'        => $to,
                'subject'   => $subject,
                'context'   => $context,
                'headers'   => $headers,
                'attachments' => $attachments,
            ]);

            return;
        }

        $attempt = (int) ($context['attempt'] ?? 1);
        if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
            Logging::log('mail', 'Retry skipped because maximum attempts reached', [
                'to'      => $to,
                'subject' => $subject,
                'attempt' => $attempt,
            ]);

            return;
        }

        $retryContext = $context;
        $retryContext['attempt'] = $attempt;

        $payload = [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => $headers,
            'attachments' => $attachments,
            'context'     => $retryContext,
        ];

        $delay    = (int) apply_filters('fp_resv_mail_retry_delay', 300, $payload);
        $schedule = time() + max(60, $delay);

        as_schedule_single_action($schedule, 'fp_resv_retry_email', [$payload], 'fp-resv');
    }
}
