<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Email;

use function sprintf;

/**
 * Costruisce gli headers per le email di notifica.
 * Estratto da EmailService per migliorare la manutenibilità.
 */
final class EmailHeadersBuilder
{
    /**
     * Costruisce gli headers per le email.
     *
     * @param array<string, mixed> $notifications Impostazioni notifiche
     * @return array<int, string> Headers email
     */
    public function build(array $notifications): array
    {
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















