<?php

declare(strict_types=1);

namespace FP\Resv\Domain\QA;

use function absint;
use function defined;
use function filter_var;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_NULL_ON_FAILURE;

final class CLI
{
    public function __construct(private readonly Seeder $seeder)
    {
    }

    public function register(): void
    {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        \WP_CLI::add_command('fp-resv qa seed', [$this, 'handleSeed']);
    }

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function handleSeed(array $args, array $assocArgs): void
    {
        $days = isset($assocArgs['days']) ? absint((int) $assocArgs['days']) : 14;
        $dryRun = isset($assocArgs['dry-run'])
            ? (bool) filter_var($assocArgs['dry-run'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : false;

        $summary = $this->seeder->seed($days, $dryRun);

        $lines = [
            'Intervallo: ' . $summary['start_date'] . ' → ' . $summary['end_date'],
            'Prenotazioni create: ' . $summary['reservations_created'],
            'Clienti creati: ' . $summary['customers_created'],
            'Email loggate: ' . $summary['mail_logged'],
            'Webhook loggati: ' . $summary['webhooks_logged'],
            'Job di coda: ' . $summary['queue_logged'],
            'Pagamenti registrati: ' . $summary['payments_logged'],
            'Audit inseriti: ' . $summary['audit_entries_logged'],
        ];

        if ($dryRun) {
            \WP_CLI::log('DRY-RUN attivo, nessun dato inserito.');
        }

        foreach ($lines as $line) {
            \WP_CLI::log($line);
        }

        if ($dryRun) {
            \WP_CLI::success('Simulazione seed completata.');

            return;
        }

        \WP_CLI::success('Seed QA completato.');
    }
}
