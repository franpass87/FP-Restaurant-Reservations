<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

final class CLI
{
    public function register(): void
    {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        \WP_CLI::add_command('fp-resv reservations revoke-tokens', [$this, 'handleRevoke']);
    }

    public function handleRevoke(): void
    {
        ManageTokens::revokeAll();
        \WP_CLI::success('Token di gestione prenotazioni revocati.');
    }
}
