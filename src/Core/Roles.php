<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use WP_User;

use function add_role;
use function apply_filters;
use function current_user_can;
use function get_option;
use function get_role;
use function get_userdata;
use function get_users;
use function in_array;
use function remove_role;
use function update_option;
use function __;

/**
 * Gestisce il ruolo unificato FP Manager e le capabilities del plugin.
 *
 * A partire dall'unificazione dei ruoli, FP Restaurant Reservations e FP Experiences
 * condividono un unico ruolo `fp_manager` che ha accesso completo a entrambi i plugin.
 * I vecchi ruoli (`fp_restaurant_manager`, `fp_reservations_viewer`, `fp_exp_manager`,
 * `fp_exp_operator`, `fp_exp_guide`) vengono rimossi e gli utenti assegnati vengono
 * migrati automaticamente al nuovo ruolo.
 */
final class Roles
{
    /**
     * Capability principale per gestire le prenotazioni del ristorante.
     */
    public const MANAGE_RESERVATIONS = 'manage_fp_reservations';

    /**
     * Capability per visualizzare il manager delle prenotazioni.
     * Mantenuta per retrocompatibilità con i controlli esistenti nel codice:
     * il ruolo unificato la include insieme a MANAGE_RESERVATIONS.
     */
    public const VIEW_RESERVATIONS_MANAGER = 'view_fp_reservations_manager';

    /**
     * Slug del ruolo unificato. Ha accesso completo a FP Restaurant e FP Experiences.
     */
    public const FP_MANAGER = 'fp_manager';

    /**
     * Alias di retrocompatibilità per il vecchio nome della costante.
     *
     * @deprecated Usare self::FP_MANAGER.
     */
    public const RESTAURANT_MANAGER = self::FP_MANAGER;

    /**
     * Flag usato per eseguire la migrazione dei ruoli legacy una sola volta.
     */
    private const MIGRATION_OPTION = 'fp_resv_roles_unified_v2';

    /**
     * Ruoli legacy da rimuovere dopo la migrazione degli utenti.
     *
     * @var string[]
     */
    private const LEGACY_ROLES = [
        'fp_restaurant_manager',
        'fp_reservations_viewer',
        'fp_exp_manager',
        'fp_exp_operator',
        'fp_exp_guide',
    ];

    /**
     * Crea (o aggiorna) il ruolo unificato e migra gli utenti dai ruoli legacy.
     *
     * L'aggiornamento è idempotente: se il ruolo esiste già (ad esempio perché
     * FP Experiences lo ha creato per primo), vengono soltanto aggiunte le
     * capabilities mancanti, senza sovrascrivere quelle già presenti.
     */
    public static function create(): void
    {
        self::ensureFpManagerRole();
        self::addCapabilitiesToAdministrators();
        self::migrateLegacyRoleUsers();
        self::removeLegacyRoles();

        update_option(self::MIGRATION_OPTION, '1', false);
    }

    /**
     * Rimuove le capabilities di FP Restaurant dal ruolo unificato e
     * dall'amministratore.
     *
     * Non rimuove l'intero ruolo `fp_manager` perché potrebbe essere condiviso
     * con FP Experiences; rimuove invece solo le capabilities specifiche
     * di FP Restaurant.
     */
    public static function remove(): void
    {
        $role = get_role(self::FP_MANAGER);
        if ($role !== null) {
            $role->remove_cap(self::MANAGE_RESERVATIONS);
            $role->remove_cap(self::VIEW_RESERVATIONS_MANAGER);
        }

        self::removeLegacyRoles();

        $adminRole = get_role('administrator');
        if ($adminRole !== null) {
            $adminRole->remove_cap(self::MANAGE_RESERVATIONS);
            $adminRole->remove_cap(self::VIEW_RESERVATIONS_MANAGER);
        }
    }

    /**
     * Verifica se l'utente corrente può gestire le prenotazioni.
     */
    public static function currentUserCanManageReservations(): bool
    {
        return current_user_can(self::MANAGE_RESERVATIONS);
    }

    /**
     * Garantisce che il ruolo unificato esista, che gli amministratori abbiano
     * tutte le capabilities e che non siano rimasti utenti sui ruoli legacy.
     *
     * Il chunk di migrazione utenti viene eseguito una sola volta, grazie al
     * flag in opzione, per evitare query ripetute ad ogni caricamento admin.
     */
    public static function ensureAdminCapabilities(): void
    {
        self::ensureFpManagerRole();
        self::addCapabilitiesToAdministrators();

        if (get_option(self::MIGRATION_OPTION, '0') !== '1') {
            self::migrateLegacyRoleUsers();
            self::removeLegacyRoles();
            update_option(self::MIGRATION_OPTION, '1', false);
        }
    }

    /**
     * Garantisce l'esistenza del ruolo `fp_manager` e l'aggiunta idempotente
     * delle capabilities di FP Restaurant. Non rimuove capabilities aggiunte
     * da FP Experiences sul medesimo ruolo, ma si occupa di rimuovere le
     * capability esplicitamente vietate (vedi FP_MANAGER_FORBIDDEN_CAPS).
     */
    private static function ensureFpManagerRole(): void
    {
        $role = get_role(self::FP_MANAGER);

        if ($role === null) {
            add_role(
                self::FP_MANAGER,
                __('FP Manager', 'fp-restaurant-reservations'),
                self::getFpManagerCapabilities()
            );
            $role = get_role(self::FP_MANAGER);
        } else {
            foreach (self::getFpManagerCapabilities() as $cap => $granted) {
                if (!$granted) {
                    continue;
                }

                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }

        if ($role === null) {
            return;
        }

        // Rimuove capability vietate (es. `edit_posts`) che darebbero accesso
        // a menu non pertinenti per il ruolo FP Manager.
        foreach (self::FP_MANAGER_FORBIDDEN_CAPS as $cap) {
            if ($role->has_cap($cap)) {
                $role->remove_cap($cap);
            }
        }
    }

    /**
     * Capabilities di FP Restaurant Reservations per il ruolo FP Manager.
     *
     * Il ruolo `fp_manager` è condiviso con FP Experiences: questo metodo ritorna
     * solo le capabilities di competenza di FP Restaurant (più quelle base di
     * WordPress necessarie per accedere al backend). Le capabilities di FP
     * Experiences vengono aggiunte dall'altro plugin in modo idempotente.
     *
     * NOTA: `edit_posts` è volutamente escluso. Concederebbe accesso ad Articoli
     * WP e ai menu dei temi/page builder che lo usano come capability di accesso
     * (Salient, WPBakery, ecc.). Il CPT delle esperienze e le pagine admin di
     * entrambi i plugin usano capabilities custom (`fp_exp_*`, `manage_fp_reservations`),
     * quindi `edit_posts` non è necessario.
     *
     * @return array<string, bool>
     */
    private static function getFpManagerCapabilities(): array
    {
        $caps = [
            'read' => true,
            'upload_files' => true,

            // Consente l'accesso al backend senza dare `edit_posts`.
            // Senza questa capability WooCommerce (woocommerce_prevent_admin_access)
            // redirige gli utenti fp_manager alla pagina "Mio account".
            'view_admin_dashboard' => true,

            self::MANAGE_RESERVATIONS => true,
            self::VIEW_RESERVATIONS_MANAGER => true,
        ];

        /**
         * Consente di personalizzare le capabilities aggiunte dal plugin al ruolo FP Manager.
         *
         * @param array<string, bool> $caps
         */
        return (array) apply_filters('fp_resv_fp_manager_capabilities', $caps);
    }

    /**
     * Capabilities che vanno esplicitamente rimosse dal ruolo `fp_manager` se
     * presenti (ad esempio perché aggiunte da versioni precedenti del plugin o
     * da plugin di terze parti). Impediscono la comparsa di menu non pertinenti
     * (Articoli, builder, ecc.) per l'utente FP Manager.
     *
     * @var string[]
     */
    private const FP_MANAGER_FORBIDDEN_CAPS = [
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'publish_posts',
        'delete_posts',
        'delete_others_posts',
        'delete_published_posts',
        'delete_private_posts',
        'edit_private_posts',
        'read_private_posts',
    ];

    /**
     * Aggiunge tutte le capabilities del ruolo FP Manager agli amministratori.
     */
    private static function addCapabilitiesToAdministrators(): void
    {
        $adminRole = get_role('administrator');
        if ($adminRole === null) {
            return;
        }

        foreach (array_keys(self::getFpManagerCapabilities()) as $cap) {
            if (!$adminRole->has_cap($cap)) {
                $adminRole->add_cap($cap);
            }
        }
    }

    /**
     * Migra gli utenti dai ruoli legacy al nuovo ruolo `fp_manager`.
     *
     * Un utente può avere più ruoli contemporaneamente: se ha un ruolo legacy,
     * gli viene aggiunto `fp_manager` (se non già presente) e il ruolo legacy
     * viene rimosso dal suo profilo.
     */
    private static function migrateLegacyRoleUsers(): void
    {
        foreach (self::LEGACY_ROLES as $legacyRole) {
            $users = get_users([
                'role'   => $legacyRole,
                'fields' => ['ID'],
            ]);

            foreach ($users as $user) {
                $wpUser = get_userdata((int) $user->ID);
                if (!$wpUser instanceof WP_User) {
                    continue;
                }

                if (!in_array(self::FP_MANAGER, (array) $wpUser->roles, true)) {
                    $wpUser->add_role(self::FP_MANAGER);
                }

                $wpUser->remove_role($legacyRole);
            }
        }
    }

    /**
     * Rimuove dal sito i ruoli legacy non più utilizzati.
     */
    private static function removeLegacyRoles(): void
    {
        foreach (self::LEGACY_ROLES as $legacyRole) {
            if ($legacyRole === self::FP_MANAGER) {
                continue;
            }

            if (get_role($legacyRole) !== null) {
                remove_role($legacyRole);
            }
        }
    }
}
