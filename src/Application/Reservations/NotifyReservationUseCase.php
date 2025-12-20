<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Domain\Integrations\EmailProviderInterface;
use FP\Resv\Domain\Integrations\CalendarProviderInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Core\Services\LoggerInterface;
use function get_option;
use function wp_timezone_string;

/**
 * Notify Reservation Use Case
 * 
 * Handles sending notifications and calendar events for reservations.
 * This demonstrates how to use integration services in use cases.
 *
 * @package FP\Resv\Application\Reservations
 */
final class NotifyReservationUseCase
{
    public function __construct(
        private readonly EmailProviderInterface $emailProvider,
        private readonly CalendarProviderInterface $calendarProvider,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Send confirmation email for a reservation
     * 
     * @param Reservation $reservation Reservation to notify
     * @return bool Success status
     */
    public function sendConfirmationEmail(Reservation $reservation): bool
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $reservation->getDate());
        $formattedDate = $dateObj ? $dateObj->format('d/m/Y') : $reservation->getDate();
        
        $subject = sprintf(
            'Conferma prenotazione - %s',
            $formattedDate
        );
        
        $body = $this->buildEmailBody($reservation);
        
        $success = $this->emailProvider->send(
            $reservation->getEmail(),
            $subject,
            $body,
            [
                'sender_name' => 'Ristorante',
                'sender_email' => get_option('admin_email'),
            ]
        );
        
        if ($success) {
            $this->logger->info('Reservation confirmation email sent', [
                'reservation_id' => $reservation->getId(),
                'email' => $reservation->getEmail(),
            ]);
        } else {
            $this->logger->warning('Failed to send reservation confirmation email', [
                'reservation_id' => $reservation->getId(),
                'email' => $reservation->getEmail(),
            ]);
        }
        
        return $success;
    }
    
    /**
     * Create calendar event for a reservation
     * 
     * @param Reservation $reservation Reservation to add to calendar
     * @return string|null Calendar event ID
     */
    public function createCalendarEvent(Reservation $reservation): ?string
    {
        $name = trim($reservation->getFirstName() . ' ' . $reservation->getLastName());
        
        $title = sprintf(
            'Prenotazione - %s (%d persone)',
            $name,
            $reservation->getParty()
        );
        
        $description = sprintf(
            "Cliente: %s\nEmail: %s\nTelefono: %s\nNote: %s",
            $name,
            $reservation->getEmail(),
            $reservation->getPhone(),
            $reservation->getNotes() ?? 'Nessuna nota'
        );
        
        // Parse date and time strings
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $reservation->getDate());
        if (!$dateObj) {
            $this->logger->error('Invalid reservation date format', [
                'date' => $reservation->getDate(),
            ]);
            return null;
        }
        
        $timeParts = explode(':', $reservation->getTime());
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);
        
        $start = $dateObj->setTime($hour, $minute);
        
        // Assume 2 hours duration
        $end = $start->modify('+2 hours');
        
        $eventId = $this->calendarProvider->createEvent(
            $title,
            $description,
            $start,
            $end,
            [
                'timezone' => wp_timezone_string(),
            ]
        );
        
        if ($eventId) {
            $this->logger->info('Calendar event created for reservation', [
                'reservation_id' => $reservation->getId(),
                'calendar_event_id' => $eventId,
            ]);
        } else {
            $this->logger->warning('Failed to create calendar event for reservation', [
                'reservation_id' => $reservation->getId(),
            ]);
        }
        
        return $eventId;
    }
    
    /**
     * Build email body HTML
     * 
     * @param Reservation $reservation Reservation data
     * @return string HTML email body
     */
    private function buildEmailBody(Reservation $reservation): string
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $reservation->getDate());
        $date = $dateObj ? $dateObj->format('d/m/Y') : $reservation->getDate();
        $time = $reservation->getTime();
        $name = trim($reservation->getFirstName() . ' ' . $reservation->getLastName());
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f4f4f4; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .details { background-color: #f9f9f9; padding: 15px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Conferma Prenotazione</h1>
        </div>
        <div class="content">
            <p>Gentile {$name},</p>
            <p>La tua prenotazione è stata confermata con successo.</p>
            <div class="details">
                <h3>Dettagli Prenotazione</h3>
                <p><strong>Data:</strong> {$date}</p>
                <p><strong>Ora:</strong> {$time}</p>
                <p><strong>Numero ospiti:</strong> {$reservation->getParty()}</p>
                <p><strong>Telefono:</strong> {$reservation->getPhone()}</p>
            </div>
            <p>Ti aspettiamo!</p>
        </div>
        <div class="footer">
            <p>Questo è un messaggio automatico, si prega di non rispondere.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

