<?php
/**
 * Admin layout container.
 */

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$headingId   = 'fp-resv-tables-title';
?>
<div class="fp-resv-admin fp-resv-admin--tables" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Sale & Tavoli', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>">
                    <?php esc_html_e('Sale & Tavoli', 'fp-restaurant-reservations'); ?>
                </h1>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e('Progetta la mappa delle sale con merge/split dei tavoli, viste dinamiche e strumenti di capacità assistiti.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button" href="<?php echo esc_url($settingsUrl); ?>">
                <?php esc_html_e('Impostazioni', 'fp-restaurant-reservations'); ?>
            </a>
            <a class="button button-primary" href="#fp-resv-tables-app">
                <?php esc_html_e('Apri planner sale', 'fp-restaurant-reservations'); ?>
            </a>
        </div>
    </header>

    <main class="fp-resv-admin__main">
        <section class="fp-resv-surface">
            <div
                id="fp-resv-tables-app"
                class="fp-resv-tables-app"
                data-fp-resv-tables
                data-loading-label="<?php esc_attr_e('Caricamento layout…', 'fp-restaurant-reservations'); ?>"
            ></div>
        </section>
    </main>
</div>
