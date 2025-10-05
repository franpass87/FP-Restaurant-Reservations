<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use DateTimeImmutable;
use FP\Resv\Core\Exceptions\InvalidContactException;
use FP\Resv\Core\Exceptions\InvalidDateException;
use FP\Resv\Core\Exceptions\InvalidPartyException;
use FP\Resv\Core\Exceptions\InvalidTimeException;
use function __;
use function filter_var;
use function preg_match;
use const FILTER_VALIDATE_EMAIL;

class ReservationValidator
{
    private array $errors = [];

    public function validate(array $payload): bool
    {
        $this->errors = [];

        $this->validateDate($payload['date'] ?? '');
        $this->validateTime($payload['time'] ?? '');
        $this->validateParty($payload['party'] ?? 0);
        $this->validateContact($payload);

        return $this->errors === [];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }

        return reset($this->errors);
    }

    /**
     * @throws InvalidDateException
     */
    public function assertValidDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidDateException(
                __('Formato data non valido. Utilizzare YYYY-MM-DD.', 'fp-restaurant-reservations'),
                ['date' => $date]
            );
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
            throw new InvalidDateException(
                __('La data specificata non è valida.', 'fp-restaurant-reservations'),
                ['date' => $date]
            );
        }
    }

    /**
     * @throws InvalidTimeException
     */
    public function assertValidTime(string $time): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidTimeException(
                __('Formato orario non valido. Utilizzare HH:MM.', 'fp-restaurant-reservations'),
                ['time' => $time]
            );
        }

        [$hours, $minutes] = explode(':', $time);
        $h = (int) $hours;
        $m = (int) $minutes;

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            throw new InvalidTimeException(
                __('Orario non valido.', 'fp-restaurant-reservations'),
                ['time' => $time]
            );
        }
    }

    /**
     * @throws InvalidPartyException
     */
    public function assertValidParty(int $party, int $maxCapacity = 0): void
    {
        if ($party < 1) {
            throw new InvalidPartyException(
                __('Il numero di coperti deve essere almeno 1.', 'fp-restaurant-reservations'),
                ['party' => $party]
            );
        }

        if ($maxCapacity > 0 && $party > $maxCapacity) {
            throw new InvalidPartyException(
                sprintf(
                    __('Il numero massimo di coperti è %d.', 'fp-restaurant-reservations'),
                    $maxCapacity
                ),
                ['party' => $party, 'max_capacity' => $maxCapacity]
            );
        }
    }

    /**
     * @throws InvalidContactException
     */
    public function assertValidContact(array $payload): void
    {
        $errors = [];

        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName  = trim((string) ($payload['last_name'] ?? ''));
        $email     = trim((string) ($payload['email'] ?? ''));

        if ($firstName === '') {
            $errors['first_name'] = __('Il nome è obbligatorio.', 'fp-restaurant-reservations');
        }

        if ($lastName === '') {
            $errors['last_name'] = __('Il cognome è obbligatorio.', 'fp-restaurant-reservations');
        }

        if ($email === '') {
            $errors['email'] = __('L\'email è obbligatoria.', 'fp-restaurant-reservations');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = __('L\'email non è valida.', 'fp-restaurant-reservations');
        }

        if ($errors !== []) {
            throw new InvalidContactException(
                __('Dati di contatto non validi.', 'fp-restaurant-reservations'),
                $errors
            );
        }
    }

    private function validateDate(string $date): void
    {
        try {
            $this->assertValidDate($date);
        } catch (InvalidDateException $exception) {
            $this->errors['date'] = $exception->getMessage();
        }
    }

    private function validateTime(string $time): void
    {
        try {
            $this->assertValidTime($time);
        } catch (InvalidTimeException $exception) {
            $this->errors['time'] = $exception->getMessage();
        }
    }

    private function validateParty(int $party): void
    {
        try {
            $this->assertValidParty($party);
        } catch (InvalidPartyException $exception) {
            $this->errors['party'] = $exception->getMessage();
        }
    }

    private function validateContact(array $payload): void
    {
        try {
            $this->assertValidContact($payload);
        } catch (InvalidContactException $exception) {
            $this->errors = array_merge($this->errors, $exception->getContext());
        }
    }
}
