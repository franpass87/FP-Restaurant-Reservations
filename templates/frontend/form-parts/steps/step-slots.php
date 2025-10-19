<?php
/**
 * Form Step: Time Slot Selection
 * 
 * Mostra gli slot orari disponibili per la data selezionata.
 * Il contenuto viene popolato dinamicamente da JavaScript.
 * 
 * @var array $strings Stringhe localizzate
 */
?>

<div class="fp-resv-slots fp-slots" data-fp-resv-slots>
    <!-- Legenda disponibilità -->
    <aside class="fp-resv-slots__legend-container">
        <ul
            class="fp-meals__legend fp-resv-slots__legend"
            aria-label="<?php echo esc_attr__('Legenda disponibilità', 'fp-restaurant-reservations'); ?>"
            data-fp-resv-slots-legend
            hidden
        >
            <li class="fp-meals__legend-item fp-meals__legend-item--available">
                <span class="fp-meals__legend-indicator" aria-hidden="true"></span>
                <span class="fp-meals__legend-text">
                    <?php echo esc_html__('Disponibile', 'fp-restaurant-reservations'); ?>
                </span>
            </li>
            <li class="fp-meals__legend-item fp-meals__legend-item--limited">
                <span class="fp-meals__legend-indicator" aria-hidden="true"></span>
                <span class="fp-meals__legend-text">
                    <?php echo esc_html__('Posti limitati', 'fp-restaurant-reservations'); ?>
                </span>
            </li>
            <li class="fp-meals__legend-item fp-meals__legend-item--full">
                <span class="fp-meals__legend-indicator" aria-hidden="true"></span>
                <span class="fp-meals__legend-text">
                    <?php echo esc_html__('Tutto prenotato', 'fp-restaurant-reservations'); ?>
                </span>
            </li>
        </ul>
    </aside>

    <!-- Area di feedback e stato -->
    <div class="fp-resv-slots__feedback">
        <p class="fp-resv-slots__status" data-fp-resv-slots-status aria-live="polite">
            <?php echo esc_html($strings['messages']['slots_loading'] ?? ''); ?>
        </p>
        <p class="fp-resv-slots__indicator" data-fp-resv-availability-indicator aria-live="polite" hidden></p>
    </div>

    <!-- Lista orari disponibili -->
    <div class="fp-resv-slots__container">
        <ul 
            class="fp-resv-slots__list" 
            data-fp-resv-slots-list 
            aria-live="polite" 
            aria-busy="false"
            role="group"
            aria-label="<?php echo esc_attr__('Orari disponibili', 'fp-restaurant-reservations'); ?>"
        ></ul>
    </div>

    <!-- Messaggi di stato vuoto/errore -->
    <div class="fp-resv-slots__messages">
        <p class="fp-resv-slots__empty" data-fp-resv-slots-empty hidden>
            <?php echo esc_html($strings['messages']['slots_empty'] ?? ''); ?>
        </p>
        <div class="fp-resv-slots__boundary fp-alert fp-alert--error" data-fp-resv-slots-boundary hidden role="alert">
            <span data-fp-resv-slots-boundary-message></span>
            <button type="button" class="fp-btn fp-btn--ghost" data-fp-resv-slots-retry>
                <?php esc_html_e('Riprova', 'fp-restaurant-reservations'); ?>
            </button>
        </div>
    </div>
</div>
