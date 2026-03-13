<?php
/**
 * Admin closures SPA container.
 */

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$serviceSettingsUrl = $settingsUrl . '#general-service-hours';
$headingId   = 'fp-resv-closures-title';
?>
<div class="fp-resv-admin fp-resv-admin--closures" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Calendario Operativo', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>">
                    <?php esc_html_e('Calendario Operativo', 'fp-restaurant-reservations'); ?>
                </h1>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e('Configura in pochi passaggi chiusure, fasce bloccate e aperture speciali. Tutto in una sola schermata, con filtri chiari e stato immediato.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button" href="<?php echo esc_url($serviceSettingsUrl); ?>">
                <?php esc_html_e('Regole servizio', 'fp-restaurant-reservations'); ?>
            </a>
            <a class="button button-primary" href="#fp-resv-closures-app">
                <?php esc_html_e('Apri planner operativo', 'fp-restaurant-reservations'); ?>
            </a>
        </div>
    </header>

    <main class="fp-resv-admin__main">
        <section class="fp-resv-surface">
            <div class="fp-resv-closures__quick-guide" aria-label="<?php esc_attr_e('Guida rapida', 'fp-restaurant-reservations'); ?>">
                <article class="fp-resv-closures__quick-step">
                    <h3><?php esc_html_e('1. Scegli azione', 'fp-restaurant-reservations'); ?></h3>
                    <p><?php esc_html_e('Decidi se chiudere un giorno intero, una fascia oraria o creare un’apertura speciale.', 'fp-restaurant-reservations'); ?></p>
                </article>
                <article class="fp-resv-closures__quick-step">
                    <h3><?php esc_html_e('2. Inserisci periodo', 'fp-restaurant-reservations'); ?></h3>
                    <p><?php esc_html_e('Imposta date e orari in modo preciso. Il sistema mostra solo i campi utili alla modalità scelta.', 'fp-restaurant-reservations'); ?></p>
                </article>
                <article class="fp-resv-closures__quick-step">
                    <h3><?php esc_html_e('3. Verifica e salva', 'fp-restaurant-reservations'); ?></h3>
                    <p><?php esc_html_e('Controlla stato e note nella lista eventi, poi salva con un click.', 'fp-restaurant-reservations'); ?></p>
                </article>
            </div>

            <div class="fp-resv-closures__stats" aria-live="polite">
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
            <p class="fp-resv-closures__note">
                <?php esc_html_e('Suggerimento: usa filtri e ricerca per trovare rapidamente eventi attivi, futuri o scaduti.', 'fp-restaurant-reservations'); ?>
            </p>
            <div id="fp-resv-closures-app" class="fp-resv-closures-app" data-fp-resv-closures>
                <?php esc_html_e('L’interfaccia drag & drop per le chiusure verrà caricata a breve.', 'fp-restaurant-reservations'); ?>
            </div>
        </section>
    </main>
</div>
