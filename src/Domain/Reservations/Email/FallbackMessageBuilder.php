<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Email;

use function array_map;
use function esc_html;
use function implode;
use function is_array;
use function sprintf;
use function __;

/**
 * Genera messaggio fallback per email staff quando il template non è disponibile.
 * Estratto da EmailService per migliorare la manutenibilità.
 */
final class FallbackMessageBuilder
{
    /**
     * Genera messaggio fallback per staff.
     *
     * @param array<string, mixed> $context Contesto prenotazione
     * @param array<string, mixed> $staffCopy Testi email staff
     * @return string Messaggio HTML
     */
    public function build(array $context, array $staffCopy = []): string
    {
        $fallback = is_array($staffCopy['fallback'] ?? null) ? $staffCopy['fallback'] : [];

        $lines = [
            sprintf(
                (string) ($fallback['reservation'] ?? __('Prenotazione #%d', 'fp-restaurant-reservations')),
                $context['id']
            ),
            sprintf(
                (string) ($fallback['date_time'] ?? __('Data: %s alle %s', 'fp-restaurant-reservations')),
                $context['date_formatted'] ?? $context['date'],
                $context['time_formatted'] ?? $context['time']
            ),
            sprintf(
                (string) ($fallback['party'] ?? __('Coperti: %d', 'fp-restaurant-reservations')),
                (int) $context['party']
            ),
            sprintf(
                (string) ($fallback['customer'] ?? __('Cliente: %s %s', 'fp-restaurant-reservations')),
                $context['customer']['first_name'],
                $context['customer']['last_name']
            ),
            sprintf(
                (string) ($fallback['email'] ?? __('Email: %s', 'fp-restaurant-reservations')),
                $context['customer']['email']
            ),
        ];

        if ($context['customer']['phone'] !== '') {
            $lines[] = sprintf(
                (string) ($fallback['phone'] ?? __('Telefono: %s', 'fp-restaurant-reservations')),
                $context['customer']['phone']
            );
        }

        if ($context['notes'] !== '') {
            $lines[] = sprintf(
                (string) ($fallback['notes'] ?? __('Note: %s', 'fp-restaurant-reservations')),
                $context['notes']
            );
        }

        if ($context['allergies'] !== '') {
            $lines[] = sprintf(
                (string) ($fallback['allergies'] ?? __('Allergie: %s', 'fp-restaurant-reservations')),
                $context['allergies']
            );
        }

        $lines[] = sprintf(
            (string) ($fallback['manage'] ?? __('Gestione: %s', 'fp-restaurant-reservations')),
            $context['manage_url']
        );

        return '<p>' . implode('</p><p>', array_map('esc_html', $lines)) . '</p>';
    }
}















