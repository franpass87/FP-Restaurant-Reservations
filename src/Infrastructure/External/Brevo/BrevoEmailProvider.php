<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\External\Brevo;

use FP\Resv\Core\Services\HttpClientInterface;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Integrations\EmailProviderInterface;
use function get_option;
use function json_decode;

/**
 * Brevo Email Provider
 * 
 * Implements EmailProviderInterface using Brevo (Sendinblue) API.
 * This is the Infrastructure layer implementation.
 *
 * @package FP\Resv\Infrastructure\External\Brevo
 */
final class BrevoEmailProvider implements EmailProviderInterface
{
    private const API_BASE_URL = 'https://api.brevo.com/v3';
    
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey
    ) {
    }
    
    /**
     * Send an email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param array<string, mixed> $options Additional options
     * @return bool Success status
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $payload = [
            'sender' => [
                'name' => $options['sender_name'] ?? 'Restaurant',
                'email' => $options['sender_email'] ?? get_option('admin_email'),
            ],
            'to' => [
                [
                    'email' => $to,
                    'name' => $options['recipient_name'] ?? '',
                ],
            ],
            'subject' => $subject,
            'htmlContent' => $body,
        ];
        
        $response = $this->httpClient->post(
            self::API_BASE_URL . '/smtp/email',
            $payload,
            [
                'headers' => [
                    'api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]
        );
        
        if (isset($response['error']) || ($response['response']['code'] ?? 0) !== 201) {
            $this->logger->error('Brevo email send failed', [
                'to' => $to,
                'subject' => $subject,
                'response' => $response,
            ]);
            return false;
        }
        
        $this->logger->info('Brevo email sent', [
            'to' => $to,
            'subject' => $subject,
        ]);
        
        return true;
    }
    
    /**
     * Send email to multiple recipients
     * 
     * @param array<string> $to Recipient emails
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array<string, mixed> $options Additional options
     * @return bool Success status
     */
    public function sendBulk(array $to, string $subject, string $body, array $options = []): bool
    {
        $recipients = array_map(fn($email) => ['email' => $email], $to);
        
        $payload = [
            'sender' => [
                'name' => $options['sender_name'] ?? 'Restaurant',
                'email' => $options['sender_email'] ?? get_option('admin_email'),
            ],
            'to' => $recipients,
            'subject' => $subject,
            'htmlContent' => $body,
        ];
        
        $response = $this->httpClient->post(
            self::API_BASE_URL . '/smtp/email',
            $payload,
            [
                'headers' => [
                    'api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]
        );
        
        if (isset($response['error']) || ($response['response']['code'] ?? 0) !== 201) {
            $this->logger->error('Brevo bulk email send failed', [
                'recipients_count' => count($to),
                'response' => $response,
            ]);
            return false;
        }
        
        $this->logger->info('Brevo bulk email sent', [
            'recipients_count' => count($to),
        ]);
        
        return true;
    }
}

