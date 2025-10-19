<?php
/**
 * Form Step: Party Size Selection
 * 
 * Selezione numero di persone con bottoni +/-.
 * 
 * @var array $strings Stringhe localizzate
 * @var array $config Configurazione
 */

$defaultPartySize = isset($config['defaults']['partySize']) ? (int) $config['defaults']['partySize'] : 2;
?>

<div class="fp-resv-field fp-field">
    <label>
        <span><?php echo esc_html($strings['fields']['party'] ?? ''); ?></span>
        <div class="fp-resv-party-input-wrapper">
            <button 
                type="button" 
                class="fp-resv-party-btn fp-resv-party-btn--decrement" 
                data-fp-resv-party-decrement
                aria-label="<?php echo esc_attr__('Riduci numero persone', 'fp-restaurant-reservations'); ?>"
            >
                <span aria-hidden="true">−</span>
            </button>
            <input
                class="fp-input fp-resv-party-input"
                type="number"
                min="1"
                max="40"
                name="fp_resv_party"
                data-fp-resv-field="party"
                value="<?php echo esc_attr((string) $defaultPartySize); ?>"
                required
            >
            <button 
                type="button" 
                class="fp-resv-party-btn fp-resv-party-btn--increment" 
                data-fp-resv-party-increment
                aria-label="<?php echo esc_attr__('Aumenta numero persone', 'fp-restaurant-reservations'); ?>"
            >
                <span aria-hidden="true">+</span>
            </button>
        </div>
    </label>
</div>
