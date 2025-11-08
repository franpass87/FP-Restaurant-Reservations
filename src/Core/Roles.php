<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_role;
use function current_user_can;
use function get_role;
use function remove_role;
use function __;

/**
 * Gestisce i ruoli personalizzati e le capabilities del plugin.
 */
final class Roles
{
    /**
     * Capability principale per gestire le prenotazioni del ristorante.
     */
    public const MANAGE_RESERVATIONS = 'manage_fp_reservations';

    /**
     * Capability per visualizzare solo il manager delle prenotazioni.
     */
    public const VIEW_RESERVATIONS_MANAGER = 'view_fp_reservations_manager';

    /**
     * Slug del ruolo Restaurant Manager.
     */
    public const RESTAURANT_MANAGER = 'fp_restaurant_manager';

    /**
     * Slug del ruolo Reservations Viewer (solo accesso al manager).
     */
    public const RESERVATIONS_VIEWER = 'fp_reservations_viewer';

    /**
     * Crea i ruoli personalizzati del plugin.
     */
    public static function create(): void
    {
        // Rimuove i ruoli se esistono già (per aggiornare le capabilities)
        remove_role(self::RESTAURANT_MANAGER);
        remove_role(self::RESERVATIONS_VIEWER);

        // Crea il ruolo Restaurant Manager con le capabilities complete
        add_role(
            self::RESTAURANT_MANAGER,
            __('Restaurant Manager', 'fp-restaurant-reservations'),
            self::getRestaurantManagerCapabilities()
        );

        // Crea il ruolo Reservations Viewer (solo accesso al manager)
        add_role(
            self::RESERVATIONS_VIEWER,
            __('Reservations Viewer', 'fp-restaurant-reservations'),
            self::getReservationsViewerCapabilities()
        );

        // Aggiungi le capability anche agli amministratori
        self::addCapabilityToAdministrators();
    }

    /**
     * Rimuove i ruoli personalizzati del plugin.
     */
    public static function remove(): void
    {
        remove_role(self::RESTAURANT_MANAGER);
        remove_role(self::RESERVATIONS_VIEWER);
        
        // Rimuove le capability dagli amministratori
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
     * Ottiene le capabilities per il ruolo Restaurant Manager.
     * 
     * @return array<string, bool>
     */
    private static function getRestaurantManagerCapabilities(): array
    {
        return [
            // Capability principale del plugin (accesso completo)
            self::MANAGE_RESERVATIONS => true,

            // Capability per visualizzare il manager
            self::VIEW_RESERVATIONS_MANAGER => true,

            // Capabilities base di lettura (necessarie per accedere al backend)
            'read' => true,

            // Capabilities per gli upload (utili per immagini eventi, etc.)
            'upload_files' => true,
        ];
    }

    /**
     * Ottiene le capabilities per il ruolo Reservations Viewer.
     * Questo ruolo ha accesso SOLO al manager delle prenotazioni.
     * 
     * @return array<string, bool>
     */
    private static function getReservationsViewerCapabilities(): array
    {
        return [
            // Solo la capability per visualizzare il manager
            self::VIEW_RESERVATIONS_MANAGER => true,

            // Capability base di lettura (necessaria per accedere al backend)
            'read' => true,
        ];
    }

    /**
     * Aggiunge le capability agli amministratori.
     */
    private static function addCapabilityToAdministrators(): void
    {
        $adminRole = get_role('administrator');
        if ($adminRole !== null) {
            // Verifica se le capability sono già presenti prima di aggiungerle
            if (!$adminRole->has_cap(self::MANAGE_RESERVATIONS)) {
                $adminRole->add_cap(self::MANAGE_RESERVATIONS);
            }
            if (!$adminRole->has_cap(self::VIEW_RESERVATIONS_MANAGER)) {
                $adminRole->add_cap(self::VIEW_RESERVATIONS_MANAGER);
            }
        }
    }

    /**
     * Verifica e ripara le capabilities degli amministratori se necessario.
     * Questo metodo può essere chiamato durante l'inizializzazione per garantire
     * che gli amministratori abbiano sempre accesso al plugin.
     */
    public static function ensureAdminCapabilities(): void
    {
        $adminRole = get_role('administrator');
        if ($adminRole !== null) {
            if (!$adminRole->has_cap(self::MANAGE_RESERVATIONS)) {
                $adminRole->add_cap(self::MANAGE_RESERVATIONS);
                error_log('[FP Resv] added manage_fp_reservations to administrator role');
            }
            if (!$adminRole->has_cap(self::VIEW_RESERVATIONS_MANAGER)) {
                $adminRole->add_cap(self::VIEW_RESERVATIONS_MANAGER);
                error_log('[FP Resv] added view_fp_reservations_manager to administrator role');
            }
        }
    }
}
