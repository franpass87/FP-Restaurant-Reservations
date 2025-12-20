<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\Logging;
use FP\Resv\Domain\Payments\StripeService as StripePayments;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use function __;
use function array_merge;
use function is_array;
use function Throwable;

/**
 * Servizio centralizzato per gestione pagamenti prenotazioni.
 * Estratto da Service.php per migliorare modularità.
 */
final class PaymentService
{
    public function __construct(
        private readonly StripePayments $stripe
    ) {
    }

    /**
     * Verifica se una prenotazione richiede pagamento.
     *
     * @param array<string, mixed> $payload Dati prenotazione
     * @return bool True se richiede pagamento
     */
    public function requiresPayment(array $payload): bool
    {
        return $this->stripe->shouldRequireReservationPayment($payload);
    }

    /**
     * Crea un payment intent per una prenotazione.
     *
     * @param int $reservationId ID prenotazione
     * @param array<string, mixed> $payload Dati prenotazione completi
     * @return array<string, mixed> Dati payment intent o errore
     */
    public function createPaymentIntent(int $reservationId, array $payload): array
    {
        try {
            $intentData = $this->stripe->createReservationIntent(
                $reservationId,
                array_merge($payload, ['id' => $reservationId])
            );

            return array_merge([
                'required'         => true,
                'capture_strategy' => $this->stripe->captureStrategy(),
                'publishable_key'  => $this->stripe->publishableKey(),
            ], $intentData);
        } catch (\Throwable $exception) {
            Logging::log('payments', 'Failed to create Stripe payment intent', [
                'reservation_id' => $reservationId,
                'error'          => $exception->getMessage(),
            ]);

            return [
                'required' => true,
                'status'   => 'error',
                'message'  => __('Impossibile avviare il pagamento. Ti contatteremo a breve per completare la prenotazione.', 'fp-restaurant-reservations'),
                'publishable_key' => $this->stripe->publishableKey(),
            ];
        }
    }

    /**
     * Risolve lo stato prenotazione in base a pagamento richiesto.
     *
     * @param array<string, mixed> $payload Dati prenotazione
     * @param string $defaultStatus Stato di default
     * @return string Stato risolto
     */
    public function resolveStatus(array $payload, string $defaultStatus): string
    {
        $requiresPayment = $this->requiresPayment($payload);

        if ($requiresPayment && ($payload['status'] === null || $payload['status'] === '' || $defaultStatus === 'pending')) {
            return 'pending_payment';
        }

        return $defaultStatus;
    }

    /**
     * Ottiene la chiave pubblicabile Stripe.
     */
    public function getPublishableKey(): string
    {
        return $this->stripe->publishableKey();
    }

    /**
     * Ottiene la strategia di capture Stripe.
     */
    public function getCaptureStrategy(): string
    {
        return $this->stripe->captureStrategy();
    }
}
















