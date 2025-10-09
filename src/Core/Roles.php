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
     * Slug del ruolo Restaurant Manager.
     */
    public const RESTAURANT_MANAGER = 'fp_restaurant_manager';

    /**
     * Crea i ruoli personalizzati del plugin.
     */
    public static function create(): void
    {
        // Rimuove il ruolo se esiste già (per aggiornare le capabilities)
        remove_role(self::RESTAURANT_MANAGER);

        // Crea il ruolo Restaurant Manager con le capabilities base
        add_role(
            self::RESTAURANT_MANAGER,
            __('Restaurant Manager', 'fp-restaurant-reservations'),
            self::getRestaurantManagerCapabilities()
        );

        // Aggiungi la capability anche agli amministratori
        self::addCapabilityToAdministrators();
    }

    /**
     * Rimuove i ruoli personalizzati del plugin.
     */
    public static function remove(): void
    {
        remove_role(self::RESTAURANT_MANAGER);
        
        // Rimuove la capability dagli amministratori
        $adminRole = get_role('administrator');
        if ($adminRole !== null) {
            $adminRole->remove_cap(self::MANAGE_RESERVATIONS);
        }
    }

    /**
     * Verifica se l'utente corrente può gestire le prenotazioni.
     * Include un fallback per gli amministratori che potrebbero non avere ancora la capability personalizzata.
     */
    public static function currentUserCanManageReservations(): bool
    {
        return current_user_can(self::MANAGE_RESERVATIONS) || current_user_can('manage_options');
    }

    /**
     * Ottiene le capabilities per il ruolo Restaurant Manager.
     * 
     * @return array<string, bool>
     */
    private static function getRestaurantManagerCapabilities(): array
    {
        return [
            // Capability principale del plugin
            self::MANAGE_RESERVATIONS => true,

            // Capabilities base di lettura (necessarie per accedere al backend)
            'read' => true,

            // Capabilities per gli upload (utili per immagini eventi, etc.)
            'upload_files' => true,
        ];
    }

    /**
     * Aggiunge la capability agli amministratori.
     */
    private static function addCapabilityToAdministrators(): void
    {
        $adminRole = get_role('administrator');
        if ($adminRole !== null) {
            $adminRole->add_cap(self::MANAGE_RESERVATIONS);
        }
    }
}
