<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\External;

use FP\Resv\Domain\Integrations\EmailProviderInterface;

/**
 * No-Op Email Provider
 * 
 * Null implementation that does nothing.
 * Used when email provider is not configured.
 *
 * @package FP\Resv\Infrastructure\External
 */
final class NoOpEmailProvider implements EmailProviderInterface
{
    /**
     * Send an email (no-op)
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array<string, mixed> $options Additional options
     * @return bool Always returns true (no-op)
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        // No-op: email provider not configured
        return true;
    }
    
    /**
     * Send email to multiple recipients (no-op)
     * 
     * @param array<string> $to Recipient emails
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array<string, mixed> $options Additional options
     * @return bool Always returns true (no-op)
     */
    public function sendBulk(array $to, string $subject, string $body, array $options = []): bool
    {
        // No-op: email provider not configured
        return true;
    }
}










