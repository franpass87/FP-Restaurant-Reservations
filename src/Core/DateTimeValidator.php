<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Core\Exceptions\InvalidDateException;
use FP\Resv\Core\Exceptions\InvalidTimeException;
use function __;
use function current_time;
use function function_exists;
use function preg_match;
use function wp_timezone;

/**
 * Utility centralizzata per validazione di date e orari.
 * Riduce duplicazione e fornisce validazione consistente.
 */
final class DateTimeValidator
{
    /**
     * Valida e crea un DateTimeImmutable da una stringa data.
     *
     * @param string $date Data nel formato Y-m-d
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @return DateTimeImmutable Data validata
     * @throws InvalidDateException Se la data non è valida
     */
    public static function validateDate(string $date, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidDateException(
                __('Formato data non valido. Utilizzare YYYY-MM-DD.', 'fp-restaurant-reservations'),
                ['date' => $date]
            );
        }

        $tz = $timezone ?? self::getDefaultTimezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);

        if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
            throw new InvalidDateException(
                __('La data specificata non è valida.', 'fp-restaurant-reservations'),
                ['date' => $date]
            );
        }

        return $dt;
    }

    /**
     * Valida un orario nel formato HH:MM.
     *
     * @param string $time Orario nel formato HH:MM
     * @return array{hours: int, minutes: int} Orario validato
     * @throws InvalidTimeException Se l'orario non è valido
     */
    public static function validateTime(string $time): array
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

        return [
            'hours' => $h,
            'minutes' => $m,
        ];
    }

    /**
     * Valida una combinazione data+ora e crea DateTimeImmutable.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato HH:MM
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @return DateTimeImmutable Data-ora validata
     * @throws InvalidDateException|InvalidTimeException Se data o ora non sono validi
     */
    public static function validateDateTime(string $date, string $time, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        // Valida formato base senza lanciare eccezioni se già invalidi
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidDateException(
                __('Formato data non valido.', 'fp-restaurant-reservations'),
                ['date' => $date]
            );
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidTimeException(
                __('Formato orario non valido.', 'fp-restaurant-reservations'),
                ['time' => $time]
            );
        }

        $tz = $timezone ?? self::getDefaultTimezone();
        $dateTimeString = $date . ' ' . $time . ':00';
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTimeString, $tz);

        if (!$dateTime instanceof DateTimeImmutable) {
            throw new InvalidDateException(
                __('La combinazione data e ora non è valida.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time]
            );
        }

        return $dateTime;
    }

    /**
     * Verifica se una data è nel passato.
     *
     * @param DateTimeImmutable $date Data da verificare
     * @param DateTimeZone|null $timezone Timezone per il confronto (default: WordPress timezone)
     * @return bool True se la data è nel passato
     */
    public static function isPast(DateTimeImmutable $date, ?DateTimeZone $timezone = null): bool
    {
        $tz = $timezone ?? self::getDefaultTimezone();
        $now = new DateTimeImmutable('now', $tz);

        return $date < $now;
    }

    /**
     * Verifica se una data è nel futuro.
     *
     * @param DateTimeImmutable $date Data da verificare
     * @param DateTimeZone|null $timezone Timezone per il confronto (default: WordPress timezone)
     * @return bool True se la data è nel futuro
     */
    public static function isFuture(DateTimeImmutable $date, ?DateTimeZone $timezone = null): bool
    {
        return !self::isPast($date, $timezone) && !self::isToday($date, $timezone);
    }

    /**
     * Verifica se una data è oggi.
     *
     * @param DateTimeImmutable $date Data da verificare
     * @param DateTimeZone|null $timezone Timezone per il confronto (default: WordPress timezone)
     * @return bool True se la data è oggi
     */
    public static function isToday(DateTimeImmutable $date, ?DateTimeZone $timezone = null): bool
    {
        $tz = $timezone ?? self::getDefaultTimezone();
        $today = new DateTimeImmutable('today', $tz);

        return $date->format('Y-m-d') === $today->format('Y-m-d');
    }

    /**
     * Verifica se una data-ora è nel passato.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato HH:MM
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @return bool True se la data-ora è nel passato
     * @throws InvalidDateException|InvalidTimeException Se data o ora non sono validi
     */
    public static function isDateTimePast(string $date, string $time, ?DateTimeZone $timezone = null): bool
    {
        $dateTime = self::validateDateTime($date, $time, $timezone);

        return self::isPast($dateTime, $timezone);
    }

    /**
     * Valida che una data non sia nel passato.
     *
     * @param string $date Data nel formato Y-m-d
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @throws InvalidDateException Se la data è nel passato
     */
    public static function assertNotPast(string $date, ?DateTimeZone $timezone = null): void
    {
        $dt = self::validateDate($date, $timezone);
        $tz = $timezone ?? self::getDefaultTimezone();
        $today = new DateTimeImmutable('today', $tz);

        if ($dt < $today) {
            throw new InvalidDateException(
                __('Non è possibile prenotare per giorni passati.', 'fp-restaurant-reservations'),
                ['date' => $date]
            );
        }
    }

    /**
     * Valida che una data-ora non sia nel passato.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato HH:MM
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @throws InvalidDateException Se la data-ora è nel passato
     */
    public static function assertDateTimeNotPast(string $date, string $time, ?DateTimeZone $timezone = null): void
    {
        $dateTime = self::validateDateTime($date, $time, $timezone);
        $tz = $timezone ?? self::getDefaultTimezone();
        $now = new DateTimeImmutable('now', $tz);

        if ($dateTime < $now) {
            throw new InvalidDateException(
                __('Non è possibile prenotare per orari passati.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time]
            );
        }
    }

    /**
     * Ottiene il timezone di default (WordPress timezone).
     */
    private static function getDefaultTimezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        return new DateTimeZone('Europe/Rome');
    }

    /**
     * Crea un DateTimeImmutable da una stringa data con timezone di default.
     *
     * @param string $date Data nel formato Y-m-d
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @return DateTimeImmutable|null Data creata o null se invalida
     */
    public static function createFromDate(string $date, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        try {
            return self::validateDate($date, $timezone);
        } catch (InvalidDateException $e) {
            return null;
        }
    }

    /**
     * Crea un DateTimeImmutable da una stringa data-ora con timezone di default.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato HH:MM
     * @param DateTimeZone|null $timezone Timezone (default: WordPress timezone)
     * @return DateTimeImmutable|null Data-ora creata o null se invalida
     */
    public static function createFromDateTime(string $date, string $time, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        try {
            return self::validateDateTime($date, $time, $timezone);
        } catch (InvalidDateException|InvalidTimeException $e) {
            return null;
        }
    }
}
















