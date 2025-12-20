<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Email;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Core\ICS;
use function apply_filters;
use function implode;
use function sprintf;
use function trim;
use function __;

/**
 * Genera contenuto ICS per allegato calendario.
 * Estratto da EmailService per migliorare la manutenibilità.
 */
final class ICSGenerator
{
    /**
     * Genera contenuto ICS per allegato calendario.
     *
     * @param array<string, mixed> $context Contesto prenotazione
     * @return string|null Contenuto ICS o null se errore
     */
    public function generate(array $context): ?string
    {
        try {
            $timezone = new DateTimeZone((string) ($context['restaurant']['timezone'] ?? 'Europe/Rome'));
        } catch (\Exception $exception) {
            $timezone = new DateTimeZone('Europe/Rome');
        }

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $context['date'] . ' ' . $context['time'], $timezone);
        if (!$dateTime instanceof DateTimeImmutable) {
            return null;
        }

        $turnover = (int) ($context['restaurant']['turnover_minutes'] ?? 120);
        if ($turnover <= 0) {
            $turnover = 120;
        }

        $end = $dateTime->modify('+' . $turnover . ' minutes');

        $summary = sprintf(
            /* translators: 1: customer name, 2: party size */
            __('Prenotazione %1$s (%2$d persone)', 'fp-restaurant-reservations'),
            trim($context['customer']['first_name'] . ' ' . $context['customer']['last_name']),
            (int) $context['party']
        );

        $descriptionLines = [
            sprintf(__('Cliente: %s', 'fp-restaurant-reservations'), $context['customer']['email']),
            sprintf(__('Telefono: %s', 'fp-restaurant-reservations'), $context['customer']['phone'] ?: '—'),
            sprintf(__('Note: %s', 'fp-restaurant-reservations'), $context['notes'] ?: '—'),
            sprintf(__('Allergie: %s', 'fp-restaurant-reservations'), $context['allergies'] ?: '—'),
            $context['manage_url'],
        ];

        $icsData = [
            'start'       => $dateTime,
            'end'         => $end,
            'timezone'    => $timezone->getName(),
            'summary'     => $summary,
            'description' => implode("\n", $descriptionLines),
            'location'    => (string) ($context['restaurant']['name'] ?? ''),
            'organizer'   => 'MAILTO:' . $context['customer']['email'],
        ];

        $icsData = apply_filters('fp_resv_staff_ics_payload', $icsData, $context);

        return ICS::generate($icsData);
    }
}















