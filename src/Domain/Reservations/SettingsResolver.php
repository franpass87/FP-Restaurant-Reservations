<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Domain\Reservations\ReservationStatuses;
use FP\Resv\Domain\Settings\Options;
use function in_array;
use function preg_match;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Risolve i valori di default dalle impostazioni.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class SettingsResolver
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Risolve lo stato di default per le prenotazioni.
     */
    public function resolveDefaultStatus(): string
    {
        $defaults = [
            'default_reservation_status' => 'pending',
            'default_currency'           => 'EUR',
        ];

        $general = $this->options->getGroup('fp_resv_general', $defaults);
        $status  = (string) ($general['default_reservation_status'] ?? 'pending');

        $status = strtolower($status);
        if (!in_array($status, ReservationStatuses::ALLOWED_STATUSES, true)) {
            $status = 'pending';
        }

        return $status;
    }

    /**
     * Risolve la valuta di default.
     */
    public function resolveDefaultCurrency(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'default_currency' => 'EUR',
        ]);

        $currency = strtoupper((string) ($general['default_currency'] ?? 'EUR'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        return $currency;
    }

    /**
     * Risolve la versione della policy di privacy.
     */
    public function resolvePolicyVersion(): string
    {
        $tracking = $this->options->getGroup('fp_resv_tracking', [
            'privacy_policy_version' => '1.0',
        ]);

        $version = trim((string) ($tracking['privacy_policy_version'] ?? '1.0'));
        if ($version === '') {
            $version = '1.0';
        }

        return $version;
    }
}















