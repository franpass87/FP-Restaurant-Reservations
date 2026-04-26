<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use FP\Resv\Core\Plugin;
use InvalidArgumentException;
use function __;
use function admin_url;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_timezone;

/**
 * Enqueue CSS/JS e localize per il planner chiusure/aperture (admin).
 */
final class ClosuresAdminAssets
{
    public const SCRIPT_HANDLE = 'fp-resv-admin-closures';

    public const STYLE_HANDLE = 'fp-resv-admin-closures-style';

    private const BASE_STYLE_HANDLE = 'fp-resv-admin-shell';

    /**
     * Carica asset del planner sulla schermata indicata (es. Manager o stub redirect).
     *
     * @param string $hook        Hook corrente di admin_enqueue_scripts.
     * @param string $screenHook  Hook della pagina target (deve coincidere con $hook).
     * @param Service $service    Servizio chiusure per il payload preview iniziale.
     */
    public static function enqueue(string $hook, string $screenHook, Service $service): void
    {
        if ($hook !== $screenHook) {
            return;
        }

        $version = Plugin::assetVersion();
        $scriptUrl = Plugin::$url . 'assets/js/admin/closures-app.js';
        $styleUrl = Plugin::$url . 'assets/css/admin-closures.css';

        wp_enqueue_script(self::SCRIPT_HANDLE, $scriptUrl, ['wp-api-fetch', 'fp-resv-admin-manager'], $version, true);

        wp_enqueue_style(self::BASE_STYLE_HANDLE, Plugin::$url . 'assets/css/admin-shell.css', [], $version);

        if (file_exists(Plugin::$dir . 'assets/css/admin-closures.css')) {
            wp_enqueue_style(self::STYLE_HANDLE, $styleUrl, [self::BASE_STYLE_HANDLE], $version);
        }

        $timezone = wp_timezone();
        $start = new DateTimeImmutable('today', $timezone);
        $end = $start->add(new DateInterval('P30D'));

        try {
            $preview = $service->preview($start, $end);
        } catch (InvalidArgumentException $exception) {
            $preview = [
                'range' => [
                    'start' => $start->format(DateTimeInterface::ATOM),
                    'end' => $end->format(DateTimeInterface::ATOM),
                ],
                'events' => [],
                'summary' => [
                    'total_events' => 0,
                    'blocked_hours' => 0,
                    'capacity_reduction' => ['count' => 0, 'min' => null, 'max' => null],
                    'special_hours' => 0,
                    'impacted_scopes' => [],
                ],
                'error' => $exception->getMessage(),
            ];
        }

        $managerUrl = admin_url('admin.php?page=fp-resv-manager&fp_resv_tab=closures');

        wp_localize_script(self::SCRIPT_HANDLE, 'fpResvClosuresSettings', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce' => wp_create_nonce('fp_resv_admin'),
            'preview' => $preview,
            'strings' => [
                'headline' => __('Planner operativo: chiusure e aperture', 'fp-restaurant-reservations'),
                'description' => __('Crea e gestisci eventi operativi in modo semplice: giorno intero, fasce orarie o aperture speciali.', 'fp-restaurant-reservations'),
                'createCta' => __('Nuovo evento operativo', 'fp-restaurant-reservations'),
                'empty' => __('Nessuna chiusura o apertura programmata nel periodo selezionato.', 'fp-restaurant-reservations'),
                'emptyFiltered' => __('Nessun risultato con i filtri attuali. Modifica ricerca o filtri per vedere altri eventi.', 'fp-restaurant-reservations'),
                'formTitle' => __('Configura nuovo evento operativo', 'fp-restaurant-reservations'),
                'formTitleEdit' => __('Modifica evento operativo', 'fp-restaurant-reservations'),
                'edit' => __('Modifica', 'fp-restaurant-reservations'),
                'searchLabel' => __('Cerca evento', 'fp-restaurant-reservations'),
                'startLabel' => __('Inizio', 'fp-restaurant-reservations'),
                'endLabel' => __('Fine', 'fp-restaurant-reservations'),
                'typeLabel' => __('Tipologia', 'fp-restaurant-reservations'),
                'scopeLabel' => __('Ambito', 'fp-restaurant-reservations'),
                'noteLabel' => __('Nota (facoltativa)', 'fp-restaurant-reservations'),
                'scopeRestaurant' => __('Ristorante intero', 'fp-restaurant-reservations'),
                'typeFull' => __('Chiusura totale', 'fp-restaurant-reservations'),
                'typeCapacity' => __('Riduzione capienza', 'fp-restaurant-reservations'),
                'typeSpecial' => __('Orari speciali', 'fp-restaurant-reservations'),
                'typeSpecialOpening' => __('Apertura speciale', 'fp-restaurant-reservations'),
                'percentLabel' => __('Capienza disponibile (%)', 'fp-restaurant-reservations'),
                'labelPlaceholder' => __('Nome servizio (es. Brunch di Natale)', 'fp-restaurant-reservations'),
                'capacityLabel' => __('Capacità massima', 'fp-restaurant-reservations'),
                'slotsLabel' => __('Fasce orarie', 'fp-restaurant-reservations'),
                'addSlotCta' => __('Aggiungi fascia', 'fp-restaurant-reservations'),
                'slotStartLabel' => __('Dalle', 'fp-restaurant-reservations'),
                'slotEndLabel' => __('Alle', 'fp-restaurant-reservations'),
                'save' => __('Salva', 'fp-restaurant-reservations'),
                'cancel' => __('Annulla', 'fp-restaurant-reservations'),
                'delete' => __('Elimina', 'fp-restaurant-reservations'),
                'clearFilters' => __('Reset filtri', 'fp-restaurant-reservations'),
                'confirmDelete' => __('Eliminare definitivamente?', 'fp-restaurant-reservations'),
                'searchPlaceholder' => __('Cerca per nota, tipo o ambito...', 'fp-restaurant-reservations'),
                'filterLabel' => __('Filtro', 'fp-restaurant-reservations'),
                'filterAll' => __('Tutte', 'fp-restaurant-reservations'),
                'filterActive' => __('Attive', 'fp-restaurant-reservations'),
                'filterUpcoming' => __('Future', 'fp-restaurant-reservations'),
                'filterExpired' => __('Scadute', 'fp-restaurant-reservations'),
                'filterSpecial' => __('Aperture speciali', 'fp-restaurant-reservations'),
                'sortLabel' => __('Ordina', 'fp-restaurant-reservations'),
                'sortNearest' => __('Più vicine', 'fp-restaurant-reservations'),
                'sortLatest' => __('Più lontane', 'fp-restaurant-reservations'),
                'statusActive' => __('Attiva', 'fp-restaurant-reservations'),
                'statusUpcoming' => __('Futura', 'fp-restaurant-reservations'),
                'statusExpired' => __('Scaduta', 'fp-restaurant-reservations'),
            ],
            'links' => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
                'manager' => esc_url_raw($managerUrl),
            ],
        ]);
    }
}
