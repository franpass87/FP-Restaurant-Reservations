<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP\Resv\Domain\Settings\Options;
use function substr;
use function trim;

/**
 * Calcola i timestamp per reminder e review.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class TimestampCalculator
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Options $options
    ) {
    }

    /**
     * Calcola il timestamp per un reminder.
     */
    public function computeReminderTimestamp(string $date, string $time): ?int
    {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') {
            return null;
        }

        $time = substr($time, 0, 5);

        try {
            $timezone  = new DateTimeZone($this->restaurantTimezone());
            $dateTime  = new DateTimeImmutable($date . ' ' . $time, $timezone);
            $offset    = $this->settings->offsetHours(Settings::CHANNEL_REMINDER, 4);
            $scheduled = $dateTime->sub(new DateInterval('PT' . $offset . 'H'));
        } catch (Exception) {
            return null;
        }

        return $scheduled->getTimestamp();
    }

    /**
     * Calcola il timestamp per una review.
     */
    public function computeReviewTimestamp(string $visitedAt, string $date, string $time): ?int
    {
        try {
            $timezone = new DateTimeZone($this->restaurantTimezone());
        } catch (Exception) {
            $timezone = new DateTimeZone('Europe/Rome');
        }

        try {
            if ($visitedAt !== '') {
                $reference = new DateTimeImmutable($visitedAt, $timezone);
            } elseif ($date !== '' && $time !== '') {
                $reference = new DateTimeImmutable($date . ' ' . substr($time, 0, 5), $timezone);
            } else {
                return null;
            }

            $delay     = $this->settings->offsetHours(Settings::CHANNEL_REVIEW, 24);
            $scheduled = $reference->add(new DateInterval('PT' . $delay . 'H'));
        } catch (Exception) {
            return null;
        }

        return $scheduled->getTimestamp();
    }

    /**
     * Ottiene il timezone del ristorante.
     */
    private function restaurantTimezone(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_timezone' => 'Europe/Rome',
        ]);

        $timezone = (string) ($general['restaurant_timezone'] ?? 'Europe/Rome');
        if ($timezone === '') {
            $timezone = 'Europe/Rome';
        }

        return $timezone;
    }
}















