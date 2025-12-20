<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use RuntimeException;
use function filter_var;
use function __;
use const FILTER_VALIDATE_EMAIL;

/**
 * Valida il payload di prenotazione evento.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class BookingPayloadValidator
{
    /**
     * Valida il payload di prenotazione.
     *
     * @param array<string, mixed> $payload
     * @throws RuntimeException
     */
    public function assert(array $payload): void
    {
        if ($payload['first_name'] === '' || $payload['last_name'] === '') {
            throw new RuntimeException(__('Nome e cognome sono obbligatori per i biglietti evento.', 'fp-restaurant-reservations'));
        }

        if ($payload['email'] === '' || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(__('Inserisci un indirizzo email valido per l\'evento.', 'fp-restaurant-reservations'));
        }
    }
}















