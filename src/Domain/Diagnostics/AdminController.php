<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\Roles;
use function __;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function is_array;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function strtotime;
use function wp_create_nonce;
use function wp_date;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_strip_all_tags;

final class AdminController
{
    private const CAPABILITY = Roles::MANAGE_RESERVATIONS;
    private const PAGE_SLUG  = 'fp-resv-diagnostics';

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
        
        // Determina la capability appropriata: usa manage_options per admin se manage_fp_reservations non è disponibile
        $capability = current_user_can('manage_options') && !current_user_can(self::CAPABILITY) 
            ? 'manage_options' 
            : self::CAPABILITY;
        
        $this->pageHook = add_submenu_page(
            'fp-resv-settings',
            __('Diagnostica', 'fp-restaurant-reservations'),
            __('Diagnostica', 'fp-restaurant-reservations'),
            $capability,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        ) ?: null;
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->pageHook === null || $hook !== $this->pageHook) {
            return;
        }

        $baseHandle   = 'fp-resv-admin-shell';
        $styleHandle  = 'fp-resv-admin-diagnostics';
        $scriptHandle = 'fp-resv-admin-diagnostics-js';
        $version      = Plugin::assetVersion();

        wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], $version);

        if (file_exists(Plugin::$dir . 'assets/css/admin-diagnostics.css')) {
            wp_enqueue_style(
                $styleHandle,
                Plugin::$url . 'assets/css/admin-diagnostics.css',
                [$baseHandle],
                $version
            );
        }

        wp_enqueue_script(
            $scriptHandle,
            Plugin::$url . 'assets/js/admin/diagnostics-dashboard.js',
            ['wp-api-fetch'],
            $version,
            true
        );

        $end   = wp_date('Y-m-d');
        $start = wp_date('Y-m-d', strtotime('-13 days'));

        $channels = [];
        foreach ($this->service->getChannels() as $key => $channel) {
            $columns = is_array($channel['columns'] ?? null) ? $channel['columns'] : [];
            $channels[$key] = [
                'label'       => wp_strip_all_tags($this->resolveChannelLabel($key, (string) $channel['label'])),
                'description' => wp_strip_all_tags($this->resolveChannelDescription($key, (string) $channel['description'])),
                'statuses'    => $this->mapStatusLabels($channel['statuses']),
                'columns'     => $this->mapColumns($key, $columns),
            ];
        }

        wp_localize_script($scriptHandle, 'fpResvDiagnosticsSettings', [
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'defaultRange' => [
                'start' => $start,
                'end'   => $end,
            ],
            'channels' => $channels,
            'links'    => [
                'reports' => esc_url_raw(admin_url('admin.php?page=fp-resv-analytics')),
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
            'i18n' => [
                'loading'      => __('Caricamento log…', 'fp-restaurant-reservations'),
                'empty'        => __('Nessun log trovato per i filtri selezionati.', 'fp-restaurant-reservations'),
                'error'        => __('Impossibile caricare i log. Riprova più tardi.', 'fp-restaurant-reservations'),
                'retry'        => __('Riprova', 'fp-restaurant-reservations'),
                'filtersTitle' => __('Filtri diagnostica', 'fp-restaurant-reservations'),
                'searchLabel'  => __('Cerca parola chiave', 'fp-restaurant-reservations'),
                'statusLabel'  => __('Stato', 'fp-restaurant-reservations'),
                'fromLabel'    => __('Dal', 'fp-restaurant-reservations'),
                'toLabel'      => __('Al', 'fp-restaurant-reservations'),
                'applyFilters' => __('Aggiorna', 'fp-restaurant-reservations'),
                'exportCsv'    => __('Esporta CSV', 'fp-restaurant-reservations'),
                'pagination'   => [
                    'page'    => __('Pagina %d di %d', 'fp-restaurant-reservations'),
                    'prev'    => __('Pagina precedente', 'fp-restaurant-reservations'),
                    'next'    => __('Pagina successiva', 'fp-restaurant-reservations'),
                ],
                'downloadReady'  => __('Esportazione pronta.', 'fp-restaurant-reservations'),
                'downloadFailed' => __('Esportazione non riuscita. Riprova.', 'fp-restaurant-reservations'),
                'preview' => [
                    'title'       => __('Anteprima email', 'fp-restaurant-reservations'),
                    'open'        => __('Apri anteprima', 'fp-restaurant-reservations'),
                    'loading'     => __('Caricamento anteprima…', 'fp-restaurant-reservations'),
                    'error'       => __('Impossibile caricare l\'anteprima.', 'fp-restaurant-reservations'),
                    'empty'       => __('Nessun contenuto disponibile per questa email.', 'fp-restaurant-reservations'),
                    'close'       => __('Chiudi anteprima', 'fp-restaurant-reservations'),
                    'recipient'   => __('Destinatari: %s', 'fp-restaurant-reservations'),
                    'sentAt'      => __('Registrata il %s', 'fp-restaurant-reservations'),
                    'status'      => __('Stato: %s', 'fp-restaurant-reservations'),
                    'unavailable' => __('Nessuna anteprima disponibile', 'fp-restaurant-reservations'),
                ],
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/diagnostics.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Diagnostica', 'fp-restaurant-reservations') . '</h1></div>';
    }

    /**
     * @param array<int, string> $statuses
     *
     * @return array<int, array<string, string>>
     */
    private function mapStatusLabels(array $statuses): array
    {
        $mapping = [];
        foreach ($statuses as $status) {
            $label = match ($status) {
                'sent'        => __('Inviata', 'fp-restaurant-reservations'),
                'failed'      => __('Errore', 'fp-restaurant-reservations'),
                'success'     => __('Successo', 'fp-restaurant-reservations'),
                'error'       => __('Errore', 'fp-restaurant-reservations'),
                'info'        => __('Informazione', 'fp-restaurant-reservations'),
                'authorized'  => __('Autorizzato', 'fp-restaurant-reservations'),
                'paid'        => __('Pagato', 'fp-restaurant-reservations'),
                'refunded'    => __('Rimborsato', 'fp-restaurant-reservations'),
                'void'        => __('Annullato', 'fp-restaurant-reservations'),
                'warning'     => __('Attenzione', 'fp-restaurant-reservations'),
                'pending'     => __('In attesa', 'fp-restaurant-reservations'),
                'processing'  => __('In elaborazione', 'fp-restaurant-reservations'),
                'completed'   => __('Completato', 'fp-restaurant-reservations'),
                default       => ucfirst($status),
            };

            $mapping[] = [
                'value' => $status,
                'label' => wp_strip_all_tags($label),
            ];
        }

        return $mapping;
    }

    private function resolveChannelLabel(string $key, string $fallback): string
    {
        return match ($key) {
            'email'    => __('Email', 'fp-restaurant-reservations'),
            'webhooks' => __('Webhook', 'fp-restaurant-reservations'),
            'stripe'   => __('Stripe', 'fp-restaurant-reservations'),
            'api'      => __('API & REST', 'fp-restaurant-reservations'),
            'queue'    => __('Cron & Queue', 'fp-restaurant-reservations'),
            default    => $fallback,
        };
    }

    private function resolveChannelDescription(string $key, string $fallback): string
    {
        return match ($key) {
            'email'    => __('Log invio notifiche e ricevute.', 'fp-restaurant-reservations'),
            'webhooks' => __('Eventi Brevo, Stripe e Google Calendar.', 'fp-restaurant-reservations'),
            'stripe'   => __('Intenti di pagamento, capture e refund.', 'fp-restaurant-reservations'),
            'api'      => __('Richieste REST e webhook API con errori 4xx/5xx.', 'fp-restaurant-reservations'),
            'queue'    => __('Job pianificati e code post-visita.', 'fp-restaurant-reservations'),
            default    => $fallback,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     *
     * @return array<int, array<string, string>>
     */
    private function mapColumns(string $channel, array $columns): array
    {
        $mapped = [];
        foreach ($columns as $column) {
            $key = (string) ($column['key'] ?? '');
            $label = $this->resolveColumnLabel($channel, $key, (string) ($column['label'] ?? $key));

            $mapped[] = [
                'key'   => $key,
                'label' => wp_strip_all_tags($label),
            ];
        }

        return $mapped;
    }

    private function resolveColumnLabel(string $channel, string $key, string $fallback): string
    {
        return match ($key) {
            'created_at'    => __('Registrato', 'fp-restaurant-reservations'),
            'recipient'     => __('Destinatari', 'fp-restaurant-reservations'),
            'subject'       => __('Oggetto', 'fp-restaurant-reservations'),
            'status'        => __('Stato', 'fp-restaurant-reservations'),
            'excerpt'       => __('Estratto', 'fp-restaurant-reservations'),
            'preview'       => __('Anteprima', 'fp-restaurant-reservations'),
            'error'         => __('Errore', 'fp-restaurant-reservations'),
            'source'        => __('Sorgente', 'fp-restaurant-reservations'),
            'action'        => __('Evento', 'fp-restaurant-reservations'),
            'summary'       => __('Dettagli', 'fp-restaurant-reservations'),
            'type'          => __('Tipo', 'fp-restaurant-reservations'),
            'amount'        => __('Importo', 'fp-restaurant-reservations'),
            'currency'      => __('Valuta', 'fp-restaurant-reservations'),
            'external_id'   => __('Intent / Charge', 'fp-restaurant-reservations'),
            'meta'          => __('Dettagli', 'fp-restaurant-reservations'),
            'entity'        => __('Entità', 'fp-restaurant-reservations'),
            'actor'         => __('Ruolo', 'fp-restaurant-reservations'),
            'ip'            => __('IP', 'fp-restaurant-reservations'),
            'details'       => __('Dettagli', 'fp-restaurant-reservations'),
            'updated_at'    => __('Aggiornato', 'fp-restaurant-reservations'),
            'run_at'        => __('Esecuzione', 'fp-restaurant-reservations'),
            'channel'       => __('Canale', 'fp-restaurant-reservations'),
            'reservation_id'=> __('Prenotazione', 'fp-restaurant-reservations'),
            default         => $fallback,
        };
    }
}
