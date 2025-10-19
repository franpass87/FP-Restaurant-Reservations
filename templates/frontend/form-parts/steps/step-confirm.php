<?php
/**
 * Form Step: Confirmation Summary
 * 
 * Mostra il riepilogo della prenotazione prima dell'invio.
 * I valori vengono popolati dinamicamente da JavaScript.
 * 
 * @var array $strings Stringhe localizzate
 */
?>

<section class="fp-resv-summary" data-fp-resv-summary>
    <h4 class="fp-resv-summary__title">
        <?php echo esc_html($strings['summary']['title'] ?? ''); ?>
    </h4>
    <dl class="fp-resv-summary__list">
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['date'] ?? ''); ?></dt>
            <dd data-fp-resv-summary="date"></dd>
        </div>
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['time'] ?? ''); ?></dt>
            <dd data-fp-resv-summary="time"></dd>
        </div>
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['party'] ?? ''); ?></dt>
            <dd data-fp-resv-summary="party"></dd>
        </div>
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['name'] ?? ''); ?></dt>
            <dd data-fp-resv-summary="name"></dd>
        </div>
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['contact'] ?? ''); ?></dt>
            <dd data-fp-resv-summary="contact"></dd>
        </div>
        <div data-fp-resv-summary-occasion-row>
            <dt><?php echo esc_html($strings['summary']['labels']['occasion'] ?? __('Occasione', 'fp-restaurant-reservations')); ?></dt>
            <dd data-fp-resv-summary="occasion"></dd>
        </div>
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['notes'] ?? __('Note', 'fp-restaurant-reservations')); ?></dt>
            <dd data-fp-resv-summary="notes"></dd>
        </div>
        <div>
            <dt><?php echo esc_html($strings['summary']['labels']['extras'] ?? __('Richieste aggiuntive', 'fp-restaurant-reservations')); ?></dt>
            <dd data-fp-resv-summary="extras"></dd>
        </div>
    </dl>
    <p class="fp-resv-summary__disclaimer">
        <?php echo esc_html($strings['summary']['disclaimer'] ?? ''); ?>
    </p>
</section>
