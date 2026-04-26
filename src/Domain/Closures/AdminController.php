<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use FP\Resv\Core\Roles;
use function add_action;
use function admin_url;
use function current_user_can;
use function is_admin;
use function sanitize_key;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Redirect della vecchia pagina planner verso il Manager (tab Calendario operativo).
 */
final class AdminController
{
    public const PAGE_SLUG = 'fp-resv-closures-app';

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeRedirectLegacyClosuresPage'], 0);
    }

    /**
     * Reindirizza admin.php?page=fp-resv-closures-app verso il Manager con tab chiusure.
     */
    public function maybeRedirectLegacyClosuresPage(): void
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash((string) $_GET['page'])) : '';
        if ($page !== self::PAGE_SLUG) {
            return;
        }

        if (!$this->currentUserCanAccessPlanner()) {
            return;
        }

        Roles::ensureAdminCapabilities();

        wp_safe_redirect(admin_url('admin.php?page=fp-resv-manager&fp_resv_tab=closures'));
        exit;
    }

    /**
     * Stessa logica di accesso al Manager: admin, gestore prenotazioni o vista manager.
     */
    private function currentUserCanAccessPlanner(): bool
    {
        return current_user_can('manage_options')
            || current_user_can(Roles::MANAGE_RESERVATIONS)
            || current_user_can(Roles::VIEW_RESERVATIONS_MANAGER);
    }
}
