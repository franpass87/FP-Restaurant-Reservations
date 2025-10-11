<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

/**
 * Costanti e utility per gli stati delle prenotazioni.
 * Centralizza le definizioni di status usate in vari moduli.
 */
final class ReservationStatuses
{
    /**
     * Tutti gli stati possibili per una prenotazione.
     */
    public const ALLOWED_STATUSES = [
        'pending',
        'pending_payment',
        'confirmed',
        'waitlist',
        'cancelled',
        'no-show',
        'visited',
        'seated',
        'checked_in',
    ];

    /**
     * Stati considerati "attivi" per disponibilità slot.
     */
    public const ACTIVE_FOR_AVAILABILITY = ['pending', 'confirmed', 'seated', 'pending_payment'];

    /**
     * Stati considerati "attivi" per eventi.
     */
    public const ACTIVE_FOR_EVENTS = ['pending', 'pending_payment', 'confirmed', 'checked_in'];

    /**
     * Stati che richiedono conferma.
     */
    public const REQUIRES_CONFIRMATION = ['pending', 'pending_payment'];

    /**
     * Stati completati.
     */
    public const COMPLETED = ['visited', 'checked_in'];

    /**
     * Stati cancellati/non completati.
     */
    public const CANCELLED_OR_FAILED = ['cancelled', 'no-show'];

    /**
     * Verifica se uno status è valido.
     *
     * @param string $status Lo status da verificare
     * @return bool True se valido
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALLOWED_STATUSES, true);
    }

    /**
     * Verifica se uno status è attivo (occupa disponibilità).
     *
     * @param string $status Lo status da verificare
     * @return bool True se attivo
     */
    public static function isActive(string $status): bool
    {
        return in_array($status, self::ACTIVE_FOR_AVAILABILITY, true);
    }

    /**
     * Verifica se uno status richiede conferma.
     *
     * @param string $status Lo status da verificare
     * @return bool True se richiede conferma
     */
    public static function requiresConfirmation(string $status): bool
    {
        return in_array($status, self::REQUIRES_CONFIRMATION, true);
    }

    /**
     * Verifica se uno status è completato.
     *
     * @param string $status Lo status da verificare
     * @return bool True se completato
     */
    public static function isCompleted(string $status): bool
    {
        return in_array($status, self::COMPLETED, true);
    }

    /**
     * Verifica se uno status è cancellato/fallito.
     *
     * @param string $status Lo status da verificare
     * @return bool True se cancellato/fallito
     */
    public static function isCancelledOrFailed(string $status): bool
    {
        return in_array($status, self::CANCELLED_OR_FAILED, true);
    }
}
