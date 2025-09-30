<?php
/** @var string $hook_suffix */

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$headingId   = 'fp-resv-analytics-title';
?>
<div class="fp-resv-admin fp-resv-admin--analytics" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Report & Analytics', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>">
                    <?php esc_html_e('Report & Analytics', 'fp-restaurant-reservations'); ?>
                </h1>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e("Visualizza l'andamento delle prenotazioni, individua i canali più efficaci e scarica i dati in CSV.", 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button" href="<?php echo esc_url($settingsUrl); ?>">
                <?php esc_html_e('Impostazioni', 'fp-restaurant-reservations'); ?>
            </a>
            <a class="button button-primary" href="#fp-resv-analytics-app">
                <?php esc_html_e('Vai alla dashboard', 'fp-restaurant-reservations'); ?>
            </a>
        </div>
    </header>

    <main class="fp-resv-admin__main">
        <section class="fp-resv-admin__toolbar" aria-labelledby="<?php echo esc_attr($headingId); ?>-filters">
            <h2 id="<?php echo esc_attr($headingId); ?>-filters" class="screen-reader-text">
                <?php esc_html_e('Filtri Analytics', 'fp-restaurant-reservations'); ?>
            </h2>
            <div class="fp-resv-admin__toolbar-row fp-resv-analytics__filters" data-role="filters">
                <label>
                    <span><?php esc_html_e('Dal', 'fp-restaurant-reservations'); ?></span>
                    <input type="date" data-role="date-start" />
                </label>
                <label>
                    <span><?php esc_html_e('Al', 'fp-restaurant-reservations'); ?></span>
                    <input type="date" data-role="date-end" />
                </label>
                <label>
                    <span><?php esc_html_e('Sede', 'fp-restaurant-reservations'); ?></span>
                    <select data-role="location">
                        <option value=""><?php esc_html_e('Tutte le sedi', 'fp-restaurant-reservations'); ?></option>
                    </select>
                </label>
                <button type="button" class="button button-primary" data-action="reload">
                    <?php esc_html_e('Aggiorna', 'fp-restaurant-reservations'); ?>
                </button>
                <button type="button" class="button" data-action="export" aria-live="polite">
                    <?php esc_html_e('Esporta CSV', 'fp-restaurant-reservations'); ?>
                </button>
            </div>
        </section>

        <div id="fp-resv-analytics-app" class="fp-resv-analytics__app" data-role="analytics-app">
            <div class="fp-resv-analytics__loading" data-role="loading" hidden>
                <?php esc_html_e('Caricamento analytics…', 'fp-restaurant-reservations'); ?>
            </div>
            <p class="fp-resv-analytics__empty" data-role="empty" hidden>
                <?php esc_html_e('Nessun dato disponibile per i filtri selezionati.', 'fp-restaurant-reservations'); ?>
            </p>

            <section class="fp-resv-analytics__summary" aria-labelledby="<?php echo esc_attr($headingId); ?>-summary">
                <h2 id="<?php echo esc_attr($headingId); ?>-summary" class="screen-reader-text">
                    <?php esc_html_e('Riepilogo prenotazioni', 'fp-restaurant-reservations'); ?>
                </h2>
                <article class="fp-resv-analytics__summary-card" data-metric="reservations">
                    <h3><?php esc_html_e('Prenotazioni', 'fp-restaurant-reservations'); ?></h3>
                    <p data-role="summary-reservations" aria-live="polite">0</p>
                </article>
                <article class="fp-resv-analytics__summary-card" data-metric="covers">
                    <h3><?php esc_html_e('Coperti', 'fp-restaurant-reservations'); ?></h3>
                    <p data-role="summary-covers" aria-live="polite">0</p>
                </article>
                <article class="fp-resv-analytics__summary-card" data-metric="revenue">
                    <h3><?php esc_html_e('Valore', 'fp-restaurant-reservations'); ?></h3>
                    <p data-role="summary-value" aria-live="polite">€0</p>
                </article>
                <article class="fp-resv-analytics__summary-card" data-metric="avg-party">
                    <h3><?php esc_html_e('Party medio', 'fp-restaurant-reservations'); ?></h3>
                    <p data-role="summary-avg-party" aria-live="polite">0</p>
                </article>
                <article class="fp-resv-analytics__summary-card" data-metric="avg-ticket">
                    <h3><?php esc_html_e('Ticket medio', 'fp-restaurant-reservations'); ?></h3>
                    <p data-role="summary-avg-ticket" aria-live="polite">0</p>
                </article>
            </section>

            <section class="fp-resv-card fp-resv-analytics__card" data-section="channels">
                <header class="fp-resv-card__header">
                    <h2><?php esc_html_e('Canali principali', 'fp-restaurant-reservations'); ?></h2>
                    <p><?php esc_html_e('Distribuzione delle prenotazioni per canale di acquisizione.', 'fp-restaurant-reservations'); ?></p>
                </header>
                <div class="fp-resv-card__body">
                    <canvas data-role="channels-chart" aria-label="<?php esc_attr_e('Distribuzione prenotazioni per canale', 'fp-restaurant-reservations'); ?>" role="img"></canvas>
                </div>
            </section>

            <section class="fp-resv-card fp-resv-analytics__card" data-section="trend">
                <header class="fp-resv-card__header">
                    <h2><?php esc_html_e('Trend giornaliero', 'fp-restaurant-reservations'); ?></h2>
                    <p><?php esc_html_e('Prenotazioni e coperti giorno per giorno.', 'fp-restaurant-reservations'); ?></p>
                </header>
                <div class="fp-resv-card__body">
                    <canvas data-role="trend-chart" aria-label="<?php esc_attr_e('Trend giornaliero delle prenotazioni', 'fp-restaurant-reservations'); ?>" role="img"></canvas>
                </div>
            </section>

            <section class="fp-resv-card fp-resv-analytics__card" data-section="table">
                <header class="fp-resv-card__header">
                    <h2><?php esc_html_e('Sorgenti top', 'fp-restaurant-reservations'); ?></h2>
                    <p><?php esc_html_e('Le principali combinazioni di sorgente, mezzo e campagna con share sul totale.', 'fp-restaurant-reservations'); ?></p>
                </header>
                <div class="fp-resv-card__body">
                    <table class="widefat fixed" data-role="sources-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Sorgente', 'fp-restaurant-reservations'); ?></th>
                                <th scope="col"><?php esc_html_e('Mezzo', 'fp-restaurant-reservations'); ?></th>
                                <th scope="col"><?php esc_html_e('Campagna', 'fp-restaurant-reservations'); ?></th>
                                <th scope="col"><?php esc_html_e('Prenotazioni', 'fp-restaurant-reservations'); ?></th>
                                <th scope="col"><?php esc_html_e('Coperti', 'fp-restaurant-reservations'); ?></th>
                                <th scope="col"><?php esc_html_e('Valore', 'fp-restaurant-reservations'); ?></th>
                                <th scope="col"><?php esc_html_e('Share', 'fp-restaurant-reservations'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-role="sources-body"></tbody>
                    </table>
                    <p class="fp-resv-analytics__empty" data-role="sources-empty" hidden>
                        <?php esc_html_e('Nessuna sorgente tracciata.', 'fp-restaurant-reservations'); ?>
                    </p>
                </div>
            </section>

            <div class="screen-reader-text" aria-live="polite" data-role="live-region"></div>
        </div>
    </main>
</div>
