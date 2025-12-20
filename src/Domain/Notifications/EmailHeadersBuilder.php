<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use function sprintf;

/**
 * Costruisce gli headers per le email di notifica.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class EmailHeadersBuilder
{
    public function __construct(
        private readonly Settings $settings
    ) {
    }

    /**
     * Costruisce gli headers per le email.
     *
     * @return array<int, string>
     */
    public function build(): array
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
}















