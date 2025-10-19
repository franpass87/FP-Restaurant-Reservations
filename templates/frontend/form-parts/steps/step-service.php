<?php
/**
 * Form Step: Service Selection (Meals)
 * 
 * Permette all'utente di selezionare il servizio (pranzo/cena).
 * 
 * @var array $context Context del form
 * @var array $meals Lista dei servizi disponibili
 * @var array $strings Stringhe localizzate
 * @var string $defaultMealNotice Notice del meal di default
 */

if (!isset($meals) || !is_array($meals) || $meals === []) {
    return;
}
?>

<section class="fp-meals" data-fp-resv-meals>
    <header class="fp-meals__header">
        <h3 class="fp-meals__title">
            <?php echo esc_html($strings['meals']['title'] ?? ($strings['steps']['service'] ?? '')); ?>
        </h3>
        <?php if (!empty($strings['meals']['subtitle'] ?? '')) : ?>
            <p class="fp-meals__subtitle fp-hint">
                <?php echo esc_html($strings['meals']['subtitle']); ?>
            </p>
        <?php endif; ?>
    </header>
    
    <div class="fp-meals__list" role="group">
        <?php foreach ($meals as $meal) : ?>
            <?php
            $mealKey   = isset($meal['key']) ? (string) $meal['key'] : '';
            $mealLabel = isset($meal['label']) ? (string) $meal['label'] : $mealKey;
            $mealBadge = isset($meal['badge']) ? (string) $meal['badge'] : '';
            $mealBadgeIcon = isset($meal['badge_icon']) ? (string) $meal['badge_icon'] : '';
            $mealHint  = isset($meal['hint']) ? (string) $meal['hint'] : '';
            $mealNotice = isset($meal['notice']) ? (string) $meal['notice'] : '';
            $mealPrice  = isset($meal['price']) ? (string) $meal['price'] : '';
            $mealAvailableDays = isset($meal['available_days']) && is_array($meal['available_days']) 
                ? wp_json_encode($meal['available_days']) 
                : '[]';
            $isActive  = !empty($meal['active']);
            $mealIcon = isset($meal['icon']) ? (string) $meal['icon'] : '';
            ?>
            <button
                type="button"
                class="fp-meal-pill"
                data-fp-resv-meal="<?php echo esc_attr($mealKey); ?>"
                data-meal-label="<?php echo esc_attr($mealLabel); ?>"
                data-meal-notice="<?php echo esc_attr($mealNotice); ?>"
                data-meal-default-notice="<?php echo esc_attr($mealNotice); ?>"
                data-meal-price="<?php echo esc_attr($mealPrice); ?>"
                data-meal-available-days="<?php echo esc_attr($mealAvailableDays); ?>"
                <?php echo $isActive ? 'data-active="true" aria-pressed="true"' : 'aria-pressed="false"'; ?>
            >
                <span class="fp-meal-pill__label">
                    <?php if ($mealIcon !== '') : ?>
                        <span class="fp-meal-pill__icon" aria-hidden="true">
                            <?php echo esc_html($mealIcon); ?>
                        </span>
                    <?php endif; ?>
                    <?php echo esc_html($mealLabel); ?>
                </span>
                <?php if ($mealBadge !== '') : ?>
                    <span class="fp-badge"<?php echo $mealBadgeIcon !== '' ? ' data-icon="' . esc_attr($mealBadgeIcon) . '"' : ''; ?>>
                        <?php echo esc_html($mealBadge); ?>
                    </span>
                <?php endif; ?>
                <?php if ($mealHint !== '') : ?>
                    <span class="fp-hint">
                        <?php echo esc_html($mealHint); ?>
                    </span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <p
        class="fp-meals__notice fp-hint"
        data-fp-resv-meal-notice
        <?php echo $defaultMealNotice === '' ? 'hidden' : ''; ?>
    >
        <span class="fp-meals__notice-icon" aria-hidden="true">i</span>
        <em class="fp-meals__notice-text" data-fp-resv-meal-notice-text>
            <?php echo esc_html($defaultMealNotice); ?>
        </em>
    </p>
</section>
