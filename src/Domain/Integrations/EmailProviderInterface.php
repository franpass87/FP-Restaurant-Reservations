<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Integrations;

/**
 * Email Provider Interface
 * 
 * Defines the contract for email sending services.
 * This interface is WordPress-agnostic and can be implemented
 * by any email service (Brevo, SMTP, etc.).
 *
 * @package FP\Resv\Domain\Integrations
 */
interface EmailProviderInterface
{
    /**
     * Send an email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param array<string, mixed> $options Additional options (headers, attachments, etc.)
     * @return bool Success status
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool;
    
    /**
     * Send email to multiple recipients
     * 
     * @param array<string> $to Recipient emails
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array<string, mixed> $options Additional options
     * @return bool Success status
     */
    public function sendBulk(array $to, string $subject, string $body, array $options = []): bool;
}










