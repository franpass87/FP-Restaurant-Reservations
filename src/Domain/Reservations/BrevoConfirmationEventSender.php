<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\Logging;
use FP\Resv\Domain\Brevo\Client as BrevoClient;
use FP\Resv\Domain\Brevo\Repository as BrevoRepository;
use function array_filter;
use function strtolower;
use function substr;
use function trim;

/**
 * Invia eventi Brevo per conferme prenotazioni.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class BrevoConfirmationEventSender
{
    public function __construct(
        private readonly ?BrevoClient $brevoClient,
        private readonly ?BrevoRepository $brevoRepository
    ) {
    }

    /**
     * Invia un evento a Brevo per far partire l'automazione di conferma.
     *
     * @param array<string, mixed> $payload
     */
    public function send(array $payload, int $reservationId, string $manageUrl, string $status): void
    {
        if ($this->brevoClient === null || !$this->brevoClient->isConnected()) {
            Logging::log('brevo', 'Brevo client non disponibile per invio evento confirmation', [
                'reservation_id' => $reservationId,
                'email'          => $payload['email'] ?? '',
            ]);
            return;
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            return;
        }

        // Controlla se l'evento è già stato inviato con successo per evitare duplicati
        if ($this->brevoRepository !== null && $this->brevoRepository->hasSuccessfulLog($reservationId, 'email_confirmation')) {
            Logging::log('brevo', 'Evento email_confirmation già inviato, skip per evitare duplicati', [
                'reservation_id' => $reservationId,
                'email'          => $email,
            ]);
            return;
        }

        $eventProperties = [
            'reservation' => array_filter([
                'id'         => $reservationId,
                'date'       => $payload['date'] ?? '',
                'time'       => isset($payload['time']) ? substr((string) $payload['time'], 0, 5) : '',
                'party'      => $payload['party'] ?? 0,
                'status'     => $status,
                'location'   => $payload['location'] ?? '',
                'manage_url' => $manageUrl,
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'contact' => array_filter([
                'first_name' => $payload['first_name'] ?? '',
                'last_name'  => $payload['last_name'] ?? '',
                'phone'      => $payload['phone'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'meta' => array_filter([
                'language'          => $payload['language'] ?? '',
                'notes'             => $payload['notes'] ?? '',
                'marketing_consent' => $payload['marketing_consent'] ?? null,
                'utm_source'        => $payload['utm_source'] ?? '',
                'utm_medium'        => $payload['utm_medium'] ?? '',
                'utm_campaign'      => $payload['utm_campaign'] ?? '',
                'gclid'             => $payload['gclid'] ?? '',
                'fbclid'            => $payload['fbclid'] ?? '',
                'msclkid'           => $payload['msclkid'] ?? '',
                'ttclid'            => $payload['ttclid'] ?? '',
                'value'             => $payload['value'] ?? null,
                'currency'          => $payload['currency'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];

        $response = $this->brevoClient->sendEvent('email_confirmation', [
            'email'      => strtolower(trim($email)),
            'properties' => $eventProperties,
        ]);

        // Logga l'evento nel repository Brevo se disponibile
        $success = $response['success'] ?? false;
        if ($this->brevoRepository !== null) {
            $this->brevoRepository->log(
                $reservationId,
                'email_confirmation',
                [
                    'email'      => $email,
                    'properties' => $eventProperties,
                    'response'   => $response,
                ],
                $success ? 'success' : 'error',
                $success ? null : ($response['message'] ?? null)
            );
        }

        Logging::log('brevo', 'Evento email_confirmation inviato a Brevo', [
            'reservation_id' => $reservationId,
            'email'          => $email,
            'success'        => $success,
            'response'       => $response,
        ]);
    }
}















