<?php
/**
 * Admin closures SPA container.
 */

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$headingId   = 'fp-resv-closures-title';
?>
<div class="fp-resv-admin fp-resv-admin--closures" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Chiusure', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>">
                    <?php esc_html_e('Chiusure & orari speciali', 'fp-restaurant-reservations'); ?>
                </h1>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e('Programma sospensioni temporanee, riduzioni di capienza e note operative con anteprima dell’impatto sugli slot.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button" href="<?php echo esc_url($settingsUrl); ?>">
                <?php esc_html_e('Impostazioni', 'fp-restaurant-reservations'); ?>
            </a>
            <a class="button button-primary" href="#fp-resv-closures-app">
                <?php esc_html_e('Gestisci calendario', 'fp-restaurant-reservations'); ?>
            </a>
        </div>
    </header>

    <main class="fp-resv-admin__main">
        <section class="fp-resv-surface">
            <div class="fp-resv-closures__stats" aria-live="polite">
                <div class="fp-resv-closures__stat">
                    <span class="fp-resv-closures__stat-label"><?php esc_html_e('Chiusure attive', 'fp-restaurant-reservations'); ?></span>
                    <span class="fp-resv-closures__stat-value" data-role="closures-active">0</span>
                </div>
                <div class="fp-resv-closures__stat">
                    <span class="fp-resv-closures__stat-label"><?php esc_html_e('Riduzioni capienza', 'fp-restaurant-reservations'); ?></span>
                    <span class="fp-resv-closures__stat-value" data-role="closures-capacity">0%</span>
                </div>
                <div class="fp-resv-closures__stat">
                    <span class="fp-resv-closures__stat-label"><?php esc_html_e('Prossima riapertura', 'fp-restaurant-reservations'); ?></span>
                    <span class="fp-resv-closures__stat-value" data-role="closures-next">—</span>
                </div>
            </div>
            <p class="fp-resv-closures__note">
                <?php esc_html_e('I dati verranno aggiornati in tempo reale quando l’applicazione interattiva sarà collegata al motore delle prenotazioni.', 'fp-restaurant-reservations'); ?>
            </p>
            <div id="fp-resv-closures-app" class="fp-resv-closures-app" data-fp-resv-closures>
                <?php esc_html_e('L’interfaccia drag & drop per le chiusure verrà caricata a breve.', 'fp-restaurant-reservations'); ?>
            </div>
        </section>
    </main>
</div>
