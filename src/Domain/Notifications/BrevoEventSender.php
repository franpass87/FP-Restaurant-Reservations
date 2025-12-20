<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use FP\Resv\Core\Logging;
use FP\Resv\Domain\Brevo\Client as BrevoClient;
use function array_filter;
use function strtolower;
use function substr;
use function trim;

/**
 * Invia eventi a Brevo per automazioni.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class BrevoEventSender
{
    public function __construct(
        private readonly ?BrevoClient $brevoClient,
        private readonly ManageUrlGenerator $urlGenerator,
        private readonly Settings $settings
    ) {
    }

    /**
     * Invia un evento reminder a Brevo.
     *
     * @param array<string, mixed> $reservation
     */
    public function sendReminderEvent(int $reservationId, array $reservation): void
    {
        if ($this->brevoClient === null || !$this->brevoClient->isConnected()) {
            Logging::log('brevo', 'Brevo client non disponibile per invio evento reminder', [
                'reservation_id' => $reservationId,
                'email'          => $reservation['email'] ?? '',
            ]);
            return;
        }

        $email = (string) ($reservation['email'] ?? '');
        if ($email === '') {
            return;
        }

        $manageUrl = $this->urlGenerator->generate($reservationId, $email);

        $eventProperties = [
            'reservation' => array_filter([
                'id'         => $reservationId,
                'date'       => $reservation['date'] ?? '',
                'time'       => isset($reservation['time']) ? substr((string) $reservation['time'], 0, 5) : '',
                'party'      => $reservation['party'] ?? 0,
                'status'     => $reservation['status'] ?? '',
                'location'   => $reservation['location_id'] ?? '',
                'manage_url' => $manageUrl,
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'contact' => array_filter([
                'first_name' => $reservation['first_name'] ?? '',
                'last_name'  => $reservation['last_name'] ?? '',
                'phone'      => $reservation['phone'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'meta' => array_filter([
                'language' => $reservation['customer_lang'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];

        $response = $this->brevoClient->sendEvent('email_reminder', [
            'email'      => strtolower(trim($email)),
            'properties' => $eventProperties,
        ]);

        Logging::log('brevo', 'Evento email_reminder inviato a Brevo', [
            'reservation_id' => $reservationId,
            'email'          => $email,
            'success'        => $response['success'] ?? false,
            'response'       => $response,
        ]);
    }

    /**
     * Invia un evento review a Brevo.
     *
     * @param array<string, mixed> $reservation
     */
    public function sendReviewEvent(int $reservationId, array $reservation): void
    {
        if ($this->brevoClient === null || !$this->brevoClient->isConnected()) {
            Logging::log('brevo', 'Brevo client non disponibile per invio evento review', [
                'reservation_id' => $reservationId,
                'email'          => $reservation['email'] ?? '',
            ]);
            return;
        }

        $email = (string) ($reservation['email'] ?? '');
        if ($email === '') {
            return;
        }

        $manageUrl = $this->urlGenerator->generate($reservationId, $email);
        $reviewUrl = $this->settings->reviewUrl();

        $eventProperties = [
            'reservation' => array_filter([
                'id'         => $reservationId,
                'date'       => $reservation['date'] ?? '',
                'time'       => isset($reservation['time']) ? substr((string) $reservation['time'], 0, 5) : '',
                'party'      => $reservation['party'] ?? 0,
                'status'     => $reservation['status'] ?? '',
                'location'   => $reservation['location_id'] ?? '',
                'manage_url' => $manageUrl,
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'contact' => array_filter([
                'first_name' => $reservation['first_name'] ?? '',
                'last_name'  => $reservation['last_name'] ?? '',
                'phone'      => $reservation['phone'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'meta' => array_filter([
                'language'   => $reservation['customer_lang'] ?? '',
                'review_url' => $reviewUrl,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];

        $response = $this->brevoClient->sendEvent('email_review', [
            'email'      => strtolower(trim($email)),
            'properties' => $eventProperties,
        ]);

        Logging::log('brevo', 'Evento email_review inviato a Brevo', [
            'reservation_id' => $reservationId,
            'email'          => $email,
            'success'        => $response['success'] ?? false,
            'response'       => $response,
        ]);
    }
}















