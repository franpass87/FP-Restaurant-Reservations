<?php
/**
 * Admin: Sale & Tavoli (semplice)
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
                    <?php esc_html_e('Gestione semplice: crea, modifica ed elimina sale e tavoli. Nessun visualizzatore.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button button-primary" href="#fp-resv-tables-app">
                <?php esc_html_e('Apri gestione sale', 'fp-restaurant-reservations'); ?>
            </a>
            <button type="button" class="button" data-action="refresh">
                <?php esc_html_e('Aggiorna', 'fp-restaurant-reservations'); ?>
            </button>
        </div>
    </header>

    <main class="fp-resv-admin__main">
        <section class="fp-resv-surface">
            <div
                id="fp-resv-tables-app"
                class="fp-resv-tables-app"
                data-fp-resv-tables
                data-loading-label="<?php esc_attr_e('Caricamento…', 'fp-restaurant-reservations'); ?>"
            >
                <div class="fp-resv-tables-toolbar">
                    <div class="fp-resv-tables-toolbar__left">
                        <form class="fp-resv-inline-form" data-action="create-room" aria-label="<?php esc_attr_e('Crea sala', 'fp-restaurant-reservations'); ?>">
                            <input type="text" name="name" placeholder="<?php esc_attr_e('Nome sala', 'fp-restaurant-reservations'); ?>" required>
                            <input type="text" name="color" placeholder="#6b7280" pattern="^#?[0-9a-fA-F]{6}$" title="#RRGGBB">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Aggiungi sala', 'fp-restaurant-reservations'); ?></button>
                        </form>
                        <button type="button" class="button" data-action="refresh">
                            <?php esc_html_e('Aggiorna', 'fp-restaurant-reservations'); ?>
                        </button>
                    </div>
                </div>
                <div class="fp-resv-tables-list" data-region="list" aria-live="polite"></div>
            </div>
        </section>
    </main>
</div>
