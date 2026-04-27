<?php
/**
 * Manager Prenotazioni - Stile The Fork
 * Interfaccia moderna per gestione prenotazioni ristorante
 */

declare(strict_types=1);

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$serviceSettingsUrl = $settingsUrl . '#general-service-hours';
$headingId = 'fp-resv-manager-title';
?>

<div class="wrap fp-resv-admin-outer">
    <h1 class="screen-reader-text" id="<?php echo esc_attr($headingId); ?>"><?php esc_html_e('Manager Prenotazioni', 'fp-restaurant-reservations'); ?></h1>
<div id="fp-resv-manager" class="fp-resv-manager">
    <!-- Header del Manager -->
    <header class="fp-manager-header">
        <div class="fp-manager-header__top">
            <div class="fp-manager-header__branding">
                <h2 aria-hidden="true">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e('Manager Prenotazioni', 'fp-restaurant-reservations'); ?>
                </h2>
                <nav class="fp-manager-breadcrumbs">
                    <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('Impostazioni', 'fp-restaurant-reservations'); ?></a>
                    <span>/</span>
                    <span><?php esc_html_e('Manager', 'fp-restaurant-reservations'); ?></span>
                </nav>
            </div>
            
            <div class="fp-manager-header__actions">
                <button type="button" class="fp-btn fp-btn--secondary" data-action="export">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Esporta', 'fp-restaurant-reservations'); ?>
                </button>
                <button type="button" class="fp-btn fp-btn--secondary" data-action="open-closures-planner-modal">
                    <span class="dashicons dashicons-calendar"></span>
                    <?php esc_html_e('Calendario operativo', 'fp-restaurant-reservations'); ?>
                </button>
                <button type="button" class="fp-btn fp-btn--primary" data-action="new-reservation">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Nuova Prenotazione', 'fp-restaurant-reservations'); ?>
                </button>
                <span class="fpresv-page-header-badge">v<?php echo esc_html(\FP\Resv\Core\Plugin::VERSION); ?></span>
            </div>
        </div>

        <!-- Stats Dashboard -->
        <div class="fp-manager-stats" id="fp-manager-stats">
            <div class="fp-stat-card">
                <div class="fp-stat-card__icon fp-stat-card__icon--blue">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="fp-stat-card__content">
                    <div class="fp-stat-card__label"><?php esc_html_e('Oggi', 'fp-restaurant-reservations'); ?></div>
                    <div class="fp-stat-card__value" data-stat="today-count">--</div>
                    <div class="fp-stat-card__meta" data-stat="today-guests">-- <?php esc_html_e('coperti', 'fp-restaurant-reservations'); ?></div>
                </div>
            </div>

            <div class="fp-stat-card">
                <div class="fp-stat-card__icon fp-stat-card__icon--green">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="fp-stat-card__content">
                    <div class="fp-stat-card__label"><?php esc_html_e('Confermati', 'fp-restaurant-reservations'); ?></div>
                    <div class="fp-stat-card__value" data-stat="confirmed-count">--</div>
                    <div class="fp-stat-card__meta" data-stat="confirmed-percentage">--%</div>
                </div>
            </div>

            <div class="fp-stat-card">
                <div class="fp-stat-card__icon fp-stat-card__icon--orange">
                    <span class="dashicons dashicons-calendar"></span>
                </div>
                <div class="fp-stat-card__content">
                    <div class="fp-stat-card__label"><?php esc_html_e('Settimana', 'fp-restaurant-reservations'); ?></div>
                    <div class="fp-stat-card__value" data-stat="week-count">--</div>
                    <div class="fp-stat-card__meta" data-stat="week-guests">-- <?php esc_html_e('coperti', 'fp-restaurant-reservations'); ?></div>
                </div>
            </div>

            <div class="fp-stat-card">
                <div class="fp-stat-card__icon fp-stat-card__icon--purple">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="fp-stat-card__content">
                    <div class="fp-stat-card__label"><?php esc_html_e('Mese', 'fp-restaurant-reservations'); ?></div>
                    <div class="fp-stat-card__value" data-stat="month-count">--</div>
                    <div class="fp-stat-card__meta" data-stat="month-guests">-- <?php esc_html_e('coperti', 'fp-restaurant-reservations'); ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="fp-manager-main">
        <!-- Toolbar con Filtri -->
        <div class="fp-manager-toolbar">
            <div class="fp-manager-toolbar__left">
                <!-- Date Navigation -->
                <div class="fp-date-nav">
                    <button type="button" class="fp-btn-icon" data-action="prev-day" title="<?php esc_attr_e('Giorno precedente', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <input 
                        type="date" 
                        class="fp-date-picker" 
                        id="fp-manager-date" 
                        data-role="date-picker"
                        aria-label="<?php esc_attr_e('Seleziona data', 'fp-restaurant-reservations'); ?>"
                    />
                    <button type="button" class="fp-btn-icon" data-action="next-day" title="<?php esc_attr_e('Giorno successivo', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                    <button type="button" class="fp-btn fp-btn--secondary" data-action="today">
                        <?php esc_html_e('Oggi', 'fp-restaurant-reservations'); ?>
                    </button>
                </div>

                <!-- View Switcher -->
                <div class="fp-view-switcher" role="group" aria-label="<?php esc_attr_e('Modalità visualizzazione', 'fp-restaurant-reservations'); ?>">
                    <button type="button" class="fp-view-btn" data-view="day" data-action="set-view" title="<?php esc_attr_e('Vista giornaliera con timeline oraria', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-clock"></span>
                        <span><?php esc_html_e('Giorno', 'fp-restaurant-reservations'); ?></span>
                    </button>
                    <button type="button" class="fp-view-btn is-active" data-view="week" data-action="set-view" title="<?php esc_attr_e('Vista settimanale - Consigliata', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-calendar"></span>
                        <span><?php esc_html_e('Settimana', 'fp-restaurant-reservations'); ?></span>
                    </button>
                    <button type="button" class="fp-view-btn" data-view="month" data-action="set-view" title="<?php esc_attr_e('Vista mensile con calendario', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span><?php esc_html_e('Mese', 'fp-restaurant-reservations'); ?></span>
                    </button>
                </div>
            </div>

            <div class="fp-manager-toolbar__right">
                <!-- Filters -->
                <select class="fp-filter-select" data-role="service-filter" aria-label="<?php esc_attr_e('Filtra per servizio', 'fp-restaurant-reservations'); ?>">
                    <option value=""><?php esc_html_e('Tutti i servizi', 'fp-restaurant-reservations'); ?></option>
                    <!-- Le opzioni dei servizi verranno popolate dinamicamente via JavaScript -->
                </select>

                <select class="fp-filter-select" data-role="status-filter" aria-label="<?php esc_attr_e('Filtra per stato', 'fp-restaurant-reservations'); ?>">
                    <option value=""><?php esc_html_e('Tutti gli stati', 'fp-restaurant-reservations'); ?></option>
                    <option value="pending"><?php esc_html_e('In attesa', 'fp-restaurant-reservations'); ?></option>
                    <option value="confirmed"><?php esc_html_e('Confermato', 'fp-restaurant-reservations'); ?></option>
                    <option value="visited"><?php esc_html_e('Visitato', 'fp-restaurant-reservations'); ?></option>
                    <option value="no_show"><?php esc_html_e('No-show', 'fp-restaurant-reservations'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Cancellato', 'fp-restaurant-reservations'); ?></option>
                </select>

                <div class="fp-search-box">
                    <input 
                        type="text" 
                        class="fp-search-input" 
                        placeholder="<?php esc_attr_e('Cerca per nome, email o telefono...', 'fp-restaurant-reservations'); ?>"
                        data-role="search-input"
                    />
                </div>
            </div>
        </div>

        <!-- Content Views -->
        <div class="fp-manager-content">
            <!-- Loading State -->
            <div class="fp-loading-state" id="fp-loading-state">
                <div class="fp-spinner"></div>
                <p><?php esc_html_e('Caricamento prenotazioni...', 'fp-restaurant-reservations'); ?></p>
            </div>

            <!-- Error State -->
            <div class="fp-error-state" id="fp-error-state" style="display: none;">
                <span class="dashicons dashicons-warning"></span>
                <h3><?php esc_html_e('Errore nel caricamento', 'fp-restaurant-reservations'); ?></h3>
                <p id="fp-error-message"></p>
                <button type="button" class="fp-btn fp-btn--primary" data-action="retry">
                    <?php esc_html_e('Riprova', 'fp-restaurant-reservations'); ?>
                </button>
            </div>

            <!-- Empty State -->
            <div class="fp-empty-state" id="fp-empty-state" style="display: none;">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h3><?php esc_html_e('Nessuna prenotazione', 'fp-restaurant-reservations'); ?></h3>
                <p><?php esc_html_e('Non ci sono prenotazioni per la data selezionata.', 'fp-restaurant-reservations'); ?></p>
                <button type="button" class="fp-btn fp-btn--primary" data-action="new-reservation">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Crea Prenotazione', 'fp-restaurant-reservations'); ?>
                </button>
            </div>

            <!-- Day View (Timeline) -->
            <div class="fp-view fp-view--day" id="fp-view-day" style="display: none;">
                <div class="fp-timeline" id="fp-timeline">
                    <!-- Timeline slots will be rendered here by JavaScript -->
                </div>
            </div>

            <!-- Week View -->
            <div class="fp-view fp-view--week" id="fp-view-week" style="display: none;">
                <div class="fp-week-calendar" id="fp-week-calendar">
                    <!-- Week calendar will be rendered here by JavaScript -->
                </div>
            </div>

            <!-- Month View (Calendar) -->
            <div class="fp-view fp-view--month" id="fp-view-month" style="display: none;">
                <div class="fp-month-calendar" id="fp-month-calendar">
                    <!-- Month calendar will be rendered here by JavaScript -->
                </div>
            </div>
        </div>
    </main>
</div>
</div>

<!-- Modal planner chiusure / aperture (design FP: header gradiente + corpo scrollabile) -->
<div class="fp-modal fp-modal--closures-planner" id="fp-resv-closures-modal" hidden aria-hidden="true">
    <div class="fp-modal__backdrop" data-action="close-closures-planner-modal" tabindex="-1" aria-hidden="true"></div>
    <div class="fp-modal__content fpresv-closures-modal__shell" role="dialog" aria-modal="true" aria-labelledby="fp-resv-closures-modal-title" aria-describedby="fp-resv-closures-modal-desc">
        <h1 class="screen-reader-text" id="fp-resv-closures-modal-sr-heading"><?php esc_html_e('Calendario operativo — planner chiusure e aperture', 'fp-restaurant-reservations'); ?></h1>
        <header class="fpresv-closures-modal__header">
            <div class="fpresv-closures-modal__header-text">
                <h2 id="fp-resv-closures-modal-title" class="fpresv-closures-modal__title">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <?php esc_html_e('Calendario operativo', 'fp-restaurant-reservations'); ?>
                </h2>
                <p id="fp-resv-closures-modal-desc" class="fpresv-closures-modal__desc">
                    <?php esc_html_e('Planner chiusure, aperture speciali e riduzioni di capienza.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
            <button type="button" class="fpresv-closures-modal__close" data-action="close-closures-planner-modal" aria-label="<?php esc_attr_e('Chiudi', 'fp-restaurant-reservations'); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
        </header>
        <div class="fp-modal__body fp-modal__body--closures-planner fpresv-closures-modal__body">
            <div class="fpresv-closures-modal__hint-card" role="note">
                <span class="dashicons dashicons-admin-links fpresv-closures-modal__hint-icon" aria-hidden="true"></span>
                <p class="fpresv-closures-modal__hint-text">
                    <a class="fpresv-closures-modal__hint-link" href="<?php echo esc_url($serviceSettingsUrl); ?>"><?php esc_html_e('Turni e parametri aperture speciali', 'fp-restaurant-reservations'); ?></a>
                    <?php esc_html_e('— configurazione in Impostazioni → Generali.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
            <div class="fp-resv-closures__stats fp-resv-manager-closures__stats fpresv-closures-modal__stats" aria-live="polite">
                <div class="fp-resv-closures__stat">
                    <span class="fp-resv-closures__stat-label"><?php esc_html_e('Blocchi attivi', 'fp-restaurant-reservations'); ?></span>
                    <span class="fp-resv-closures__stat-value" data-role="closures-active">0</span>
                </div>
                <div class="fp-resv-closures__stat">
                    <span class="fp-resv-closures__stat-label"><?php esc_html_e('Riduzioni capienza', 'fp-restaurant-reservations'); ?></span>
                    <span class="fp-resv-closures__stat-value" data-role="closures-capacity">0%</span>
                </div>
                <div class="fp-resv-closures__stat">
                    <span class="fp-resv-closures__stat-label"><?php esc_html_e('Prossimo evento in chiusura', 'fp-restaurant-reservations'); ?></span>
                    <span class="fp-resv-closures__stat-value" data-role="closures-next">—</span>
                </div>
            </div>
            <div id="fp-resv-closures-app" class="fp-resv-closures-app fpresv-closures-modal__app" data-fp-resv-closures>
                <?php esc_html_e('Caricamento planner…', 'fp-restaurant-reservations'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal per dettagli/modifica prenotazione -->
<div class="fp-modal" id="fp-reservation-modal" style="display: none;">
    <div class="fp-modal__backdrop" data-action="close-modal"></div>
    <div class="fp-modal__content">
        <div class="fp-modal__header">
            <h2 id="fp-modal-title"><?php esc_html_e('Dettagli Prenotazione', 'fp-restaurant-reservations'); ?></h2>
            <button type="button" class="fp-modal__close" data-action="close-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="fp-modal__body" id="fp-modal-body">
            <!-- Modal content will be rendered by JavaScript -->
        </div>
    </div>
</div>
