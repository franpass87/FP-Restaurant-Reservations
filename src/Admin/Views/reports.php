<?php
/** @var string $hook_suffix */
?>
<div class="wrap fp-resv-reports">
    <h1 class="wp-heading-inline"><?php esc_html_e('Dashboard & Report', 'fp-restaurant-reservations'); ?></h1>
    <p class="description">
        <?php esc_html_e('Monitora KPI giornalieri, esporta prenotazioni e consulta i log di sistema.', 'fp-restaurant-reservations'); ?>
    </p>

    <div id="fp-resv-reports-app" class="fp-resv-reports__app">
        <div class="fp-resv-reports__filters" data-section="toolbar">
            <div class="fp-resv-reports__dates">
                <label>
                    <span><?php esc_html_e('Dal', 'fp-restaurant-reservations'); ?></span>
                    <input type="date" data-role="date-start" />
                </label>
                <label>
                    <span><?php esc_html_e('Al', 'fp-restaurant-reservations'); ?></span>
                    <input type="date" data-role="date-end" />
                </label>
                <button type="button" class="button button-primary" data-action="reload">
                    <?php esc_html_e('Aggiorna intervallo', 'fp-restaurant-reservations'); ?>
                </button>
            </div>
            <div class="fp-resv-reports__export">
                <span class="fp-resv-reports__export-label"><?php esc_html_e('Esporta prenotazioni', 'fp-restaurant-reservations'); ?></span>
                <button type="button" class="button" data-export="csv"><?php esc_html_e('CSV', 'fp-restaurant-reservations'); ?></button>
                <button type="button" class="button" data-export="excel"><?php esc_html_e('Excel (;)', 'fp-restaurant-reservations'); ?></button>
            </div>
        </div>

        <section class="fp-resv-card" data-section="summary">
            <header class="fp-resv-card__header">
                <h2><?php esc_html_e('KPI giornalieri', 'fp-restaurant-reservations'); ?></h2>
                <p><?php esc_html_e('Prenotazioni, coperti e caparre registrate giorno per giorno.', 'fp-restaurant-reservations'); ?></p>
            </header>
            <div class="fp-resv-card__body">
                <div class="fp-resv-reports__loading" data-role="summary-loading" hidden>
                    <?php esc_html_e('Caricamento in corso…', 'fp-restaurant-reservations'); ?>
                </div>
                <table class="widefat fixed" data-role="summary-table" hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Data', 'fp-restaurant-reservations'); ?></th>
                            <th><?php esc_html_e('Prenotazioni totali', 'fp-restaurant-reservations'); ?></th>
                            <th><?php esc_html_e('Coperti', 'fp-restaurant-reservations'); ?></th>
                            <th><?php esc_html_e('Media coperti', 'fp-restaurant-reservations'); ?></th>
                            <th><?php esc_html_e('Visitati %', 'fp-restaurant-reservations'); ?></th>
                            <th><?php esc_html_e('No-show %', 'fp-restaurant-reservations'); ?></th>
                            <th><?php esc_html_e('Caparre', 'fp-restaurant-reservations'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-role="summary-body"></tbody>
                </table>
                <p class="fp-resv-reports__empty" data-role="summary-empty" hidden>
                    <?php esc_html_e('Nessun dato disponibile.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </section>

        <section class="fp-resv-card" data-section="logs">
            <header class="fp-resv-card__header">
                <h2><?php esc_html_e('Log di sistema', 'fp-restaurant-reservations'); ?></h2>
                <div class="fp-resv-reports__log-filters">
                    <label>
                        <span><?php esc_html_e('Canale', 'fp-restaurant-reservations'); ?></span>
                        <select data-role="log-channel">
                            <option value="mail"><?php esc_html_e('Email', 'fp-restaurant-reservations'); ?></option>
                            <option value="brevo"><?php esc_html_e('Brevo', 'fp-restaurant-reservations'); ?></option>
                            <option value="audit"><?php esc_html_e('Audit', 'fp-restaurant-reservations'); ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Stato', 'fp-restaurant-reservations'); ?></span>
                        <input type="text" data-role="log-status" placeholder="sent, failed…" />
                    </label>
                    <label class="fp-resv-reports__log-search">
                        <span><?php esc_html_e('Ricerca', 'fp-restaurant-reservations'); ?></span>
                        <input type="search" data-role="log-search" placeholder="subject, action, IP…" />
                    </label>
                    <button type="button" class="button" data-action="logs-reload"><?php esc_html_e('Filtra', 'fp-restaurant-reservations'); ?></button>
                </div>
            </header>
            <div class="fp-resv-card__body">
                <div class="fp-resv-reports__loading" data-role="logs-loading" hidden>
                    <?php esc_html_e('Caricamento log…', 'fp-restaurant-reservations'); ?>
                </div>
                <table class="widefat striped" data-role="logs-table" hidden>
                    <thead>
                        <tr data-role="logs-head"></tr>
                    </thead>
                    <tbody data-role="logs-body"></tbody>
                </table>
                <p class="fp-resv-reports__empty" data-role="logs-empty" hidden>
                    <?php esc_html_e('Nessun evento trovato per i filtri selezionati.', 'fp-restaurant-reservations'); ?>
                </p>
                <div class="tablenav" data-role="logs-pagination" hidden>
                    <div class="tablenav-pages">
                        <span class="displaying-num" data-role="logs-count"></span>
                        <span class="pagination-links">
                            <button type="button" class="button" data-page="first" aria-label="<?php esc_attr_e('Prima pagina', 'fp-restaurant-reservations'); ?>">&laquo;</button>
                            <button type="button" class="button" data-page="prev" aria-label="<?php esc_attr_e('Pagina precedente', 'fp-restaurant-reservations'); ?>">&lsaquo;</button>
                            <span class="paging-input">
                                <input type="number" data-role="logs-page" min="1" value="1" />
                                <span class="tablenav-paging-text">/ <span data-role="logs-total-pages">1</span></span>
                            </span>
                            <button type="button" class="button" data-page="next" aria-label="<?php esc_attr_e('Pagina successiva', 'fp-restaurant-reservations'); ?>">&rsaquo;</button>
                            <button type="button" class="button" data-page="last" aria-label="<?php esc_attr_e('Ultima pagina', 'fp-restaurant-reservations'); ?>">&raquo;</button>
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
