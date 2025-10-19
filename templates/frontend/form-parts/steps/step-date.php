<?php
/**
 * Form Step: Date Selection
 * 
 * Campo per selezionare la data della prenotazione.
 * 
 * @var array $strings Stringhe localizzate
 * @var array $hints Hint per i campi
 */
?>

<div class="fp-resv-field fp-field">
    <label>
        <span><?php echo esc_html($strings['fields']['date'] ?? ''); ?></span>
        <input 
            class="fp-input" 
            type="date" 
            name="fp_resv_date" 
            data-fp-resv-field="date" 
            min="<?php echo esc_attr(date('Y-m-d')); ?>" 
            required
        >
        <?php if (!empty($hints['date'] ?? '')) : ?>
            <small class="fp-hint"><?php echo esc_html($hints['date']); ?></small>
        <?php endif; ?>
        <small class="fp-error" data-fp-resv-date-status aria-live="polite" hidden></small>
    </label>
</div>
