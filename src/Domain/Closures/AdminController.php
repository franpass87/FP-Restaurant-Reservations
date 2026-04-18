<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use FP\Resv\Core\Plugin;
use FP\Resv\Core\Roles;
use InvalidArgumentException;
use function __;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_timezone;

final class AdminController
{
    private const CAPABILITY = Roles::MANAGE_RESERVATIONS;
    private const PAGE_SLUG = 'fp-resv-closures-app';

    private ?string $pageHook = null;

    public function __construct(private readonly Service $service)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        // Assicura che gli amministratori abbiano sempre la capability necessaria
        Roles::ensureAdminCapabilities();
        
        // Usa sempre manage_options per garantire accesso agli amministratori
        // Le pagine admin dovrebbero essere accessibili solo agli amministratori
        $this->pageHook = add_submenu_page(
            'fp-resv-settings',
            __('Calendario Operativo', 'fp-restaurant-reservations'),
            __('Calendario Operativo', 'fp-restaurant-reservations'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        ) ?: null;
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->pageHook === null || $hook !== $this->pageHook) {
            return;
        }

        $scriptHandle = 'fp-resv-admin-closures';
        $styleHandle  = 'fp-resv-admin-closures-style';
        $baseHandle   = 'fp-resv-admin-shell';

        $scriptUrl = Plugin::$url . 'assets/js/admin/closures-app.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-closures.css';
        // Forza reload con timestamp fisso dopo refactor AJAX + timezone fix
        $version   = '0.9.0-rc2.' . time();

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch'], $version, true);

        wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], $version);

        if (file_exists(Plugin::$dir . 'assets/css/admin-closures.css')) {
            wp_enqueue_style($styleHandle, $styleUrl, [$baseHandle], $version);
        }

        $timezone = wp_timezone();
        $start    = new DateTimeImmutable('today', $timezone);
        $end      = $start->add(new DateInterval('P30D'));

        try {
            $preview = $this->service->preview($start, $end);
        } catch (InvalidArgumentException $exception) {
            $preview = [
                'range'   => [
                    'start' => $start->format(DateTimeInterface::ATOM),
                    'end'   => $end->format(DateTimeInterface::ATOM),
                ],
                'events'  => [],
                'summary' => [
                    'total_events'       => 0,
                    'blocked_hours'      => 0,
                    'capacity_reduction' => ['count' => 0, 'min' => null, 'max' => null],
                    'special_hours'      => 0,
                    'impacted_scopes'    => [],
                ],
                'error'   => $exception->getMessage(),
            ];
        }

        wp_localize_script($scriptHandle, 'fpResvClosuresSettings', [
            'ajaxUrl'  => esc_url_raw(admin_url('admin-ajax.php')),
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'    => wp_create_nonce('fp_resv_admin'),
            'preview'  => $preview,
            'strings'  => [
                'headline'    => __('Planner operativo: chiusure e aperture', 'fp-restaurant-reservations'),
                'description' => __('Crea e gestisci eventi operativi in modo semplice: giorno intero, fasce orarie o aperture speciali.', 'fp-restaurant-reservations'),
                'createCta'   => __('Nuovo evento operativo', 'fp-restaurant-reservations'),
                'empty'       => __('Nessuna chiusura o apertura programmata nel periodo selezionato.', 'fp-restaurant-reservations'),
                'emptyFiltered' => __('Nessun risultato con i filtri attuali. Modifica ricerca o filtri per vedere altri eventi.', 'fp-restaurant-reservations'),
                'formTitle'   => __('Configura nuovo evento operativo', 'fp-restaurant-reservations'),
                'formTitleEdit' => __('Modifica evento operativo', 'fp-restaurant-reservations'),
                'edit'        => __('Modifica', 'fp-restaurant-reservations'),
                'searchLabel' => __('Cerca evento', 'fp-restaurant-reservations'),
                'startLabel'  => __('Inizio', 'fp-restaurant-reservations'),
                'endLabel'    => __('Fine', 'fp-restaurant-reservations'),
                'typeLabel'   => __('Tipologia', 'fp-restaurant-reservations'),
                'scopeLabel'  => __('Ambito', 'fp-restaurant-reservations'),
                'noteLabel'   => __('Nota (facoltativa)', 'fp-restaurant-reservations'),
                'scopeRestaurant' => __('Ristorante intero', 'fp-restaurant-reservations'),
                'typeFull'    => __('Chiusura totale', 'fp-restaurant-reservations'),
                'typeCapacity' => __('Riduzione capienza', 'fp-restaurant-reservations'),
                'typeSpecial' => __('Orari speciali', 'fp-restaurant-reservations'),
                'typeSpecialOpening' => __('Apertura speciale', 'fp-restaurant-reservations'),
                'percentLabel' => __('Capienza disponibile (%)', 'fp-restaurant-reservations'),
                'labelPlaceholder' => __('Nome servizio (es. Brunch di Natale)', 'fp-restaurant-reservations'),
                'capacityLabel' => __('Capacità massima', 'fp-restaurant-reservations'),
                'slotsLabel'  => __('Fasce orarie', 'fp-restaurant-reservations'),
                'addSlotCta'  => __('Aggiungi fascia', 'fp-restaurant-reservations'),
                'slotStartLabel' => __('Dalle', 'fp-restaurant-reservations'),
                'slotEndLabel' => __('Alle', 'fp-restaurant-reservations'),
                'save'        => __('Salva', 'fp-restaurant-reservations'),
                'cancel'      => __('Annulla', 'fp-restaurant-reservations'),
                'delete'      => __('Elimina', 'fp-restaurant-reservations'),
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
            'links'    => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/closures.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Chiusure', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
