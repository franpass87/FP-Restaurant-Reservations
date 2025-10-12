<?php
/**
 * Vista Agenda Moderna - Stile The Fork
 * Template completamente rifatto da zero
 */

$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$headingId   = 'fp-resv-agenda-title';
?>
<div class="fp-resv-admin fp-resv-admin--agenda" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <!-- Header -->
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Agenda', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>">
                    <?php esc_html_e('Agenda prenotazioni', 'fp-restaurant-reservations'); ?>
                </h1>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e('Gestisci le prenotazioni in modo semplice e intuitivo', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <button type="button" class="button button-primary" data-action="new-reservation">
                <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                <?php esc_html_e('Nuova prenotazione', 'fp-restaurant-reservations'); ?>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="fp-resv-admin__main">
        <section class="fp-resv-surface">
            <!-- Toolbar -->
            <div class="fp-resv-agenda__toolbar">
                <!-- Navigazione -->
                <div class="fp-resv-agenda__nav">
                    <button type="button" class="button" data-action="prev-period" title="<?php esc_attr_e('Periodo precedente', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <button type="button" class="button" data-action="today">
                        <?php esc_html_e('Oggi', 'fp-restaurant-reservations'); ?>
                    </button>
                    <button type="button" class="button" data-action="next-period" title="<?php esc_attr_e('Periodo successivo', 'fp-restaurant-reservations'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
                
                <!-- Filtri -->
                <div class="fp-resv-agenda__filters">
                    <input 
                        type="date" 
                        data-role="date-picker" 
                        class="fp-resv-agenda__date-input"
                        aria-label="<?php esc_attr_e('Seleziona data', 'fp-restaurant-reservations'); ?>"
                    />
                    <select data-role="service-filter" class="fp-resv-agenda__select" aria-label="<?php esc_attr_e('Filtra per servizio', 'fp-restaurant-reservations'); ?>">
                        <option value=""><?php esc_html_e('Tutti i servizi', 'fp-restaurant-reservations'); ?></option>
                        <option value="lunch"><?php esc_html_e('Pranzo', 'fp-restaurant-reservations'); ?></option>
                        <option value="dinner"><?php esc_html_e('Cena', 'fp-restaurant-reservations'); ?></option>
                    </select>
                </div>

                <!-- Switcher Vista -->
                <div class="fp-resv-agenda__view-switcher" role="group" aria-label="<?php esc_attr_e('Seleziona vista', 'fp-restaurant-reservations'); ?>">
                    <button 
                        type="button" 
                        class="button button-primary" 
                        data-action="set-view" 
                        data-view="day"
                        title="<?php esc_attr_e('Vista giornaliera', 'fp-restaurant-reservations'); ?>"
                    >
                        <span class="dashicons dashicons-clock"></span>
                        <span><?php esc_html_e('Giorno', 'fp-restaurant-reservations'); ?></span>
                    </button>
                    <button 
                        type="button" 
                        class="button" 
                        data-action="set-view" 
                        data-view="week"
                        title="<?php esc_attr_e('Vista settimanale', 'fp-restaurant-reservations'); ?>"
                    >
                        <span class="dashicons dashicons-calendar"></span>
                        <span><?php esc_html_e('Settimana', 'fp-restaurant-reservations'); ?></span>
                    </button>
                    <button 
                        type="button" 
                        class="button" 
                        data-action="set-view" 
                        data-view="month"
                        title="<?php esc_attr_e('Vista mensile', 'fp-restaurant-reservations'); ?>"
                    >
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span><?php esc_html_e('Mese', 'fp-restaurant-reservations'); ?></span>
                    </button>
                    <button 
                        type="button" 
                        class="button" 
                        data-action="set-view" 
                        data-view="list"
                        title="<?php esc_attr_e('Vista lista', 'fp-restaurant-reservations'); ?>"
                    >
                        <span class="dashicons dashicons-list-view"></span>
                        <span><?php esc_html_e('Lista', 'fp-restaurant-reservations'); ?></span>
                    </button>
                </div>
                
                <!-- Summary -->
                <div class="fp-resv-agenda__summary" data-role="summary">
                    <span class="fp-resv-agenda__summary-date"></span>
                    <span class="fp-resv-agenda__summary-stats"></span>
                </div>
            </div>

            <!-- Contenitore Agenda -->
            <div class="fp-resv-agenda__container">
                <!-- Loading (nascosto di default) -->
                <div class="fp-resv-agenda__loading" data-role="loading" hidden>
                    <div class="fp-resv-spinner"></div>
                    <p><?php esc_html_e('Caricamento...', 'fp-restaurant-reservations'); ?></p>
                </div>

                <!-- Empty State -->
                <div class="fp-resv-agenda__empty" data-role="empty" hidden>
                    <span class="dashicons dashicons-calendar-alt" style="font-size: 48px; color: #ddd;"></span>
                    <h3><?php esc_html_e('Nessuna prenotazione', 'fp-restaurant-reservations'); ?></h3>
                    <p><?php esc_html_e('Non ci sono prenotazioni per questo periodo', 'fp-restaurant-reservations'); ?></p>
                    <button type="button" class="button button-primary" data-action="new-reservation">
                        <?php esc_html_e('Crea la prima prenotazione', 'fp-restaurant-reservations'); ?>
                    </button>
                </div>

                <!-- Vista Giornaliera (Timeline) -->
                <div class="fp-resv-agenda__timeline" data-role="timeline" hidden>
                    <!-- Generato da JavaScript -->
                </div>

                <!-- Vista Settimanale -->
                <div class="fp-resv-agenda__week" data-role="week-view" hidden>
                    <!-- Generato da JavaScript -->
                </div>

                <!-- Vista Mensile -->
                <div class="fp-resv-agenda__month" data-role="month-view" hidden>
                    <!-- Generato da JavaScript -->
                </div>

                <!-- Vista Lista -->
                <div class="fp-resv-agenda__list" data-role="list-view" hidden>
                    <!-- Generato da JavaScript -->
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Modal: Nuova Prenotazione -->
<div class="fp-resv-modal" data-modal="new-reservation" hidden role="dialog" aria-labelledby="new-resv-title" aria-modal="true">
    <div class="fp-resv-modal__backdrop" data-action="close-modal"></div>
    <div class="fp-resv-modal__dialog">
        <header class="fp-resv-modal__header">
            <h2 id="new-resv-title"><?php esc_html_e('Nuova prenotazione', 'fp-restaurant-reservations'); ?></h2>
            <button type="button" class="button-link" data-action="close-modal" aria-label="<?php esc_attr_e('Chiudi', 'fp-restaurant-reservations'); ?>">
                &times;
            </button>
        </header>
        <div class="fp-resv-modal__body">
            <form data-form="new-reservation">
                <div class="fp-resv-form__row">
                    <label>
                        <span><?php esc_html_e('Data', 'fp-restaurant-reservations'); ?> *</span>
                        <input type="date" name="date" min="<?php echo esc_attr(gmdate('Y-m-d')); ?>" required />
                    </label>
                    <label>
                        <span><?php esc_html_e('Ora', 'fp-restaurant-reservations'); ?> *</span>
                        <input type="time" name="time" step="900" required />
                    </label>
                </div>
                <div class="fp-resv-form__row">
                    <label>
                        <span><?php esc_html_e('Nome', 'fp-restaurant-reservations'); ?> *</span>
                        <input type="text" name="first_name" required />
                    </label>
                    <label>
                        <span><?php esc_html_e('Cognome', 'fp-restaurant-reservations'); ?></span>
                        <input type="text" name="last_name" />
                    </label>
                </div>
                <div class="fp-resv-form__row">
                    <label>
                        <span><?php esc_html_e('Email', 'fp-restaurant-reservations'); ?></span>
                        <input type="email" name="email" />
                    </label>
                    <label>
                        <span><?php esc_html_e('Telefono', 'fp-restaurant-reservations'); ?></span>
                        <input type="tel" name="phone" />
                    </label>
                </div>
                <div class="fp-resv-form__row">
                    <label>
                        <span><?php esc_html_e('Numero coperti', 'fp-restaurant-reservations'); ?> *</span>
                        <input type="number" name="party" min="1" max="50" value="2" required />
                    </label>
                </div>
                <div class="fp-resv-form__row">
                    <label style="grid-column: 1 / -1;">
                        <span><?php esc_html_e('Note', 'fp-restaurant-reservations'); ?></span>
                        <textarea name="notes" rows="3"></textarea>
                    </label>
                </div>
            </form>
        </div>
        <footer class="fp-resv-modal__footer">
            <button type="button" class="button" data-action="close-modal">
                <?php esc_html_e('Annulla', 'fp-restaurant-reservations'); ?>
            </button>
            <button type="button" class="button button-primary" data-action="submit-reservation">
                <?php esc_html_e('Crea prenotazione', 'fp-restaurant-reservations'); ?>
            </button>
        </footer>
    </div>
</div>

<!-- Modal: Dettagli Prenotazione -->
<div class="fp-resv-modal" data-modal="reservation-details" hidden role="dialog" aria-labelledby="details-title" aria-modal="true">
    <div class="fp-resv-modal__backdrop" data-action="close-modal"></div>
    <div class="fp-resv-modal__dialog fp-resv-modal__dialog--wide">
        <header class="fp-resv-modal__header">
            <h2 id="details-title"><?php esc_html_e('Dettagli prenotazione', 'fp-restaurant-reservations'); ?></h2>
            <button type="button" class="button-link" data-action="close-modal" aria-label="<?php esc_attr_e('Chiudi', 'fp-restaurant-reservations'); ?>">
                &times;
            </button>
        </header>
        <div class="fp-resv-modal__body">
            <div data-role="details-content">
                <!-- Generato da JavaScript -->
            </div>
        </div>
        <footer class="fp-resv-modal__footer">
            <button type="button" class="button" data-action="close-modal">
                <?php esc_html_e('Chiudi', 'fp-restaurant-reservations'); ?>
            </button>
        </footer>
    </div>
</div>
