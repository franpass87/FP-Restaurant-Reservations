<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\Exceptions\ConflictException;
use FP\Resv\Core\Logging;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use function __;
use function in_array;
use function is_array;
use function substr;
use Throwable;

/**
 * Guard per verifiche di disponibilità e conflitti calendario.
 * Estratto da Service.php per migliorare modularità.
 */
final class AvailabilityGuard
{
    /** @var string[] */
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_AVAILABILITY;

    public function __construct(
        private readonly Availability $availability,
        private readonly ?GoogleCalendarService $calendar = null
    ) {
    }

    /**
     * Verifica conflitti con Google Calendar.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato H:i
     * @param string $status Stato prenotazione
     * @throws ConflictException Se c'è un conflitto
     */
    public function guardCalendarConflicts(string $date, string $time, string $status): void
    {
        if ($this->calendar === null || !$this->calendar->shouldBlockOnBusy()) {
            return;
        }

        if (!in_array($status, ['confirmed', 'pending_payment'], true)) {
            return;
        }

        if ($this->calendar->isWindowBusy($date, $time)) {
            throw new ConflictException(
                __('Lo slot selezionato risulta occupato su Google Calendar. Scegli un altro orario.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'status' => $status]
            );
        }
    }

    /**
     * Verifica atomicamente la disponibilità per uno slot specifico.
     * DEVE essere chiamato dentro una transazione database per garantire
     * che il controllo e l'inserimento siano atomici.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato H:i
     * @param int $party Numero di persone
     * @param int|null $roomId Sala richiesta (opzionale)
     * @param string $meal Identificatore del meal plan (pranzo/cena)
     * @param string $status Lo stato della prenotazione che si sta per creare
     * @throws ConflictException Se non c'è disponibilità
     */
    public function guardAvailabilityForSlot(
        string $date,
        string $time,
        int $party,
        ?int $roomId,
        string $meal,
        string $status
    ): void {
        // Skip per stati che non occupano capacità
        if (!in_array($status, self::ACTIVE_STATUSES, true)) {
            return;
        }

        // Calcola la disponibilità per lo slot richiesto
        $criteria = [
            'date'  => $date,
            'party' => $party,
        ];

        if ($roomId !== null && $roomId > 0) {
            $criteria['room'] = $roomId;
        }

        if ($meal !== '' && $meal !== null) {
            $criteria['meal'] = $meal;
        }

        try {
            $availability = $this->availability->findSlots($criteria);
        } catch (\Throwable $exception) {
            Logging::log('reservations', 'Errore durante calcolo disponibilità atomica', [
                'date'  => $date,
                'time'  => $time,
                'party' => $party,
                'meal'  => $meal,
                'error' => $exception->getMessage(),
            ]);

            throw new ConflictException(
                __('Impossibile verificare la disponibilità. Riprova tra qualche secondo.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        if (!isset($availability['slots']) || !is_array($availability['slots'])) {
            throw new ConflictException(
                __('Nessuno slot disponibile per la data selezionata.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        // Cerca lo slot specifico richiesto
        $requestedTime = substr($time, 0, 5); // Assicura formato H:i
        $slotFound = false;
        $slotAvailable = false;

        // DEBUG: Log dettagliato degli slot disponibili
        $availableSlotLabels = [];
        foreach ($availability['slots'] as $slot) {
            if (is_array($slot) && isset($slot['label'])) {
                $availableSlotLabels[] = $slot['label'] . ' (' . ($slot['status'] ?? 'unknown') . ')';
            }
        }

        foreach ($availability['slots'] as $slot) {
            if (!is_array($slot) || !isset($slot['label'])) {
                continue;
            }

            // Confronta il label dello slot (formato H:i) con l'orario richiesto
            if ($slot['label'] === $requestedTime) {
                $slotFound = true;
                $slotStatus = $slot['status'] ?? 'full';

                // Accetta solo slot disponibili o con disponibilità limitata
                if (in_array($slotStatus, ['available', 'limited'], true)) {
                    $slotAvailable = true;
                }

                break;
            }
        }

        if (!$slotFound) {
            Logging::log('reservations', 'Slot non trovato durante verifica atomica', [
                'date'           => $date,
                'time'           => $time,
                'requested_time' => $requestedTime,
                'party'          => $party,
                'meal'           => $meal,
                'available_slots'=> count($availability['slots']),
                'available_slot_labels' => $availableSlotLabels,
                'criteria_used' => $criteria,
            ]);

            throw new ConflictException(
                __('L\'orario selezionato non è disponibile. Scegli un altro orario.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        if (!$slotAvailable) {
            Logging::log('reservations', 'Slot non disponibile durante verifica atomica', [
                'date'  => $date,
                'time'  => $time,
                'party' => $party,
                'meal'  => $meal,
                'slot_found' => $slotFound,
            ]);

            throw new ConflictException(
                __('L\'orario selezionato è ora esaurito. Scegli un altro orario o contattaci.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }
    }
}

