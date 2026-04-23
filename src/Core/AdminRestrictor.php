<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use WP_Admin_Bar;
use WP_User;

use function add_action;
use function apply_filters;
use function in_array;
use function is_array;
use function remove_menu_page;
use function remove_meta_box;
use function remove_submenu_page;
use function strpos;
use function wp_get_current_user;

/**
 * Limita l'UI admin per gli utenti con il ruolo `fp_manager` (e non admin).
 *
 * Nasconde i menu top-level non pertinenti, i widget della Bacheca e le voci
 * della admin bar che non appartengono a FP Experiences o FP Restaurant
 * Reservations. Gli amministratori non sono mai interessati da queste regole.
 */
final class AdminRestrictor
{
    /**
     * Slug top-level consentiti per il ruolo FP Manager.
     */
    private const ALLOWED_TOP_LEVEL_SLUGS = [
        'index.php',         // Bacheca
        'profile.php',       // Profilo utente
        'fp_exp_dashboard',  // FP Experiences
        'fp-resv-settings',  // FP Restaurant Reservations
    ];

    /**
     * Prefissi di node ID della admin bar considerati "FP" e da preservare.
     */
    private const FP_ADMIN_BAR_PREFIXES = [
        'fp_exp',
        'fp-exp',
        'fp_resv',
        'fp-resv',
    ];

    /**
     * Node ID della admin bar sempre preservati (navigazione base WP).
     */
    private const ALLOWED_ADMIN_BAR_NODES = [
        'top-secondary',
        'my-account',
        'user-actions',
        'user-info',
        'edit-profile',
        'logout',
        'menu-toggle',
        'site-name',
    ];

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'restrictAdminMenu'], 9999);
        add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets'], 9999);
        add_action('wp_network_dashboard_setup', [self::class, 'removeDashboardWidgets'], 9999);
        add_action('admin_bar_menu', [self::class, 'restrictAdminBar'], 9999);
    }

    /**
     * Determina se l'utente corrente è un FP Manager "puro" (non amministratore).
     */
    public static function isRestrictedUser(): bool
    {
        $user = wp_get_current_user();
        if (! $user instanceof WP_User || ! $user->exists()) {
            return false;
        }

        $roles = (array) $user->roles;

        // Gli amministratori non sono mai ristretti.
        if (in_array('administrator', $roles, true)) {
            return false;
        }

        // Anche gli Store Manager WooCommerce ereditano spesso capability FP
        // tramite propagate_custom_role_caps: lasciamoli alla loro UI abituale.
        if (in_array('shop_manager', $roles, true)) {
            return false;
        }

        return in_array(Roles::FP_MANAGER, $roles, true);
    }

    /**
     * Rimuove dai menu top-level admin tutto ciò che non è in whitelist.
     */
    public static function restrictAdminMenu(): void
    {
        if (! self::isRestrictedUser()) {
            return;
        }

        /**
         * Consente di personalizzare la whitelist dei menu top-level per FP Manager.
         *
         * @param string[] $allowed Slug top-level consentiti.
         */
        $allowed = (array) apply_filters(
            'fp_resv_admin_menu_whitelist',
            self::ALLOWED_TOP_LEVEL_SLUGS
        );

        global $menu, $submenu;

        if (is_array($menu)) {
            foreach ($menu as $item) {
                $slug = isset($item[2]) ? (string) $item[2] : '';
                if ($slug === '' || in_array($slug, $allowed, true)) {
                    continue;
                }

                remove_menu_page($slug);
            }
        }

        // Per gli item "index.php" (Bacheca) lasciamo solo la home: rimuoviamo
        // sottomenu aggiunti da altri plugin (es. Aggiornamenti / Clarity).
        if (is_array($submenu) && isset($submenu['index.php']) && is_array($submenu['index.php'])) {
            foreach ($submenu['index.php'] as $sub) {
                $subSlug = isset($sub[2]) ? (string) $sub[2] : '';
                if ($subSlug === '' || $subSlug === 'index.php') {
                    continue;
                }

                remove_submenu_page('index.php', $subSlug);
            }
        }
    }

    /**
     * Rimuove tutti i widget della Bacheca per gli utenti FP Manager.
     */
    public static function removeDashboardWidgets(): void
    {
        if (! self::isRestrictedUser()) {
            return;
        }

        global $wp_meta_boxes;

        if (! isset($wp_meta_boxes['dashboard']) || ! is_array($wp_meta_boxes['dashboard'])) {
            return;
        }

        foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
            if (! is_array($priorities)) {
                continue;
            }

            foreach ($priorities as $widgets) {
                if (! is_array($widgets)) {
                    continue;
                }

                foreach ($widgets as $widget_id => $_widget) {
                    remove_meta_box((string) $widget_id, 'dashboard', (string) $context);
                }
            }
        }
    }

    /**
     * Rimuove dalla admin bar tutte le voci che non appartengono a FP
     * Experiences o FP Restaurant Reservations.
     */
    public static function restrictAdminBar(WP_Admin_Bar $admin_bar): void
    {
        if (! self::isRestrictedUser()) {
            return;
        }

        foreach ($admin_bar->get_nodes() as $node_id => $node) {
            if (in_array($node_id, self::ALLOWED_ADMIN_BAR_NODES, true)) {
                continue;
            }

            // Conserva tutte le voci discendenti da "my-account" (account utente).
            if (self::nodeBelongsToUserMenu($admin_bar, $node_id)) {
                continue;
            }

            if (self::nodeMatchesFpPrefix($node_id)) {
                continue;
            }

            $admin_bar->remove_node((string) $node_id);
        }
    }

    private static function nodeBelongsToUserMenu(WP_Admin_Bar $admin_bar, string $node_id): bool
    {
        $current = $node_id;
        $guard = 0;

        while ($current !== '' && $guard < 10) {
            $node = $admin_bar->get_node($current);
            if (! $node) {
                return false;
            }

            $parent = isset($node->parent) ? (string) $node->parent : '';

            if (in_array($parent, ['my-account', 'user-actions', 'top-secondary'], true)) {
                return true;
            }

            if ($parent === '' || $parent === $current) {
                return false;
            }

            $current = $parent;
            $guard++;
        }

        return false;
    }

    private static function nodeMatchesFpPrefix(string $node_id): bool
    {
        foreach (self::FP_ADMIN_BAR_PREFIXES as $prefix) {
            if (strpos($node_id, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
