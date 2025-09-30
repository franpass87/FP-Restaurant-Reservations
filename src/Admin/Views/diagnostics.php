<?php
/** @var string $hook_suffix */

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$reportsUrl  = admin_url('admin.php?page=fp-resv-analytics');
$headingId   = 'fp-resv-diagnostics-title';
?>
<div class="fp-resv-admin fp-resv-admin--diagnostics" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Diagnostica', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>"><?php esc_html_e('Diagnostica', 'fp-restaurant-reservations'); ?></h1>
                <p class="fp-resv-admin__subtitle"><?php esc_html_e('Monitora email, webhook, pagamenti e code per individuare rapidamente anomalie operative.', 'fp-restaurant-reservations'); ?></p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button" href="<?php echo esc_url($reportsUrl); ?>"><?php esc_html_e('Vai ai report', 'fp-restaurant-reservations'); ?></a>
            <a class="button" href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('Impostazioni', 'fp-restaurant-reservations'); ?></a>
        </div>
    </header>
    <main class="fp-resv-admin__main">
        <section class="fp-resv-admin__toolbar" aria-labelledby="<?php echo esc_attr($headingId); ?>-filters">
            <h2 id="<?php echo esc_attr($headingId); ?>-filters" class="screen-reader-text"><?php esc_html_e('Filtri diagnostica', 'fp-restaurant-reservations'); ?></h2>
            <div class="fp-resv-diagnostics__filters" data-role="filters">
                <label>
                    <span><?php esc_html_e('Dal', 'fp-restaurant-reservations'); ?></span>
                    <input type="date" data-role="date-start" />
                </label>
                <label>
                    <span><?php esc_html_e('Al', 'fp-restaurant-reservations'); ?></span>
                    <input type="date" data-role="date-end" />
                </label>
                <label>
                    <span><?php esc_html_e('Stato', 'fp-restaurant-reservations'); ?></span>
                    <select data-role="status"></select>
                </label>
                <label class="fp-resv-diagnostics__search">
                    <span><?php esc_html_e('Cerca', 'fp-restaurant-reservations'); ?></span>
                    <input type="search" placeholder="<?php esc_attr_e('Email, ID, messaggi…', 'fp-restaurant-reservations'); ?>" data-role="search" />
                </label>
                <button type="button" class="button button-primary" data-action="reload"><?php esc_html_e('Aggiorna', 'fp-restaurant-reservations'); ?></button>
                <button type="button" class="button" data-action="export"><?php esc_html_e('Esporta CSV', 'fp-restaurant-reservations'); ?></button>
            </div>
        </section>
        <section class="fp-resv-surface fp-resv-diagnostics__surface" data-role="diagnostics-app">
            <div class="fp-resv-diagnostics__layout">
                <nav class="fp-resv-diagnostics__tabs" data-role="tabs" role="tablist" aria-label="<?php esc_attr_e('Canali di diagnostica', 'fp-restaurant-reservations'); ?>">
                    <!-- Tabs injected by JS -->
                </nav>
                <div class="fp-resv-diagnostics__content">
                    <div class="fp-resv-diagnostics__loading" data-role="loading" hidden><?php esc_html_e('Caricamento log…', 'fp-restaurant-reservations'); ?></div>
                    <p class="fp-resv-diagnostics__empty" data-role="empty" hidden><?php esc_html_e('Nessun log disponibile per i filtri selezionati.', 'fp-restaurant-reservations'); ?></p>
                    <div class="fp-resv-diagnostics__table" data-role="table-wrapper">
                        <table class="widefat fixed" data-role="table">
                            <thead data-role="table-head"></thead>
                            <tbody data-role="table-body"></tbody>
                        </table>
                    </div>
                    <nav class="fp-resv-diagnostics__pagination" data-role="pagination" aria-label="<?php esc_attr_e('Paginazione log', 'fp-restaurant-reservations'); ?>">
                        <button type="button" class="button" data-action="prev" disabled><?php esc_html_e('Precedente', 'fp-restaurant-reservations'); ?></button>
                        <span data-role="page-indicator"></span>
                        <button type="button" class="button" data-action="next" disabled><?php esc_html_e('Successiva', 'fp-restaurant-reservations'); ?></button>
                    </nav>
                    <div class="screen-reader-text" aria-live="polite" data-role="live"></div>
                </div>
            </div>
        </section>
    </main>
</div>
