<?php
/**
 * Frontend reservation form markup.
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    return;
}

$config     = $context['config'] ?? [];
$strings    = $context['strings'] ?? [];
$steps      = $context['steps'] ?? [];
$pdfUrl     = isset($context['pdf_url']) ? (string) $context['pdf_url'] : '';
$pdfLabel   = isset($strings['pdf_label']) ? (string) $strings['pdf_label'] : '';
$pdfTooltip = isset($strings['pdf_tooltip']) ? (string) $strings['pdf_tooltip'] : '';
if ($pdfUrl !== '' && $pdfLabel === '') {
    $pdfLabel = __('Scopri il nostro Menu', 'fp-restaurant-reservations');
}
$dataLayer  = $context['data_layer'] ?? [];
$events     = $dataLayer['events'] ?? [];
$privacy    = $context['privacy'] ?? [];
$policyUrl  = isset($privacy['policy_url']) ? (string) $privacy['policy_url'] : '';
$policyVersion = isset($privacy['policy_version']) ? (string) $privacy['policy_version'] : '';
$marketingEnabled = !empty($privacy['marketing_enabled']);
$profilingEnabled = !empty($privacy['profiling_enabled']);
$style      = $context['style'] ?? [];
$progressLabels = is_array($strings['steps'] ?? null) ? $strings['steps'] : [];
$meals = isset($context['meals']) && is_array($context['meals']) ? array_values($context['meals']) : [];
$defaultMeal = null;
foreach ($meals as $meal) {
    if (!empty($meal['active'])) {
        $defaultMeal = $meal;
        break;
    }
}
if ($defaultMeal === null && $meals !== []) {
    $defaultMeal = $meals[0];
}
$defaultMealKey   = isset($defaultMeal['key']) ? (string) $defaultMeal['key'] : '';
$defaultMealPrice = isset($defaultMeal['price']) ? (string) $defaultMeal['price'] : '';
$defaultMealNotice = isset($defaultMeal['notice']) ? (string) $defaultMeal['notice'] : '';
$hints = is_array($strings['hints'] ?? null) ? $strings['hints'] : [];
$noticeMessage = '';
if (isset($context['notice']) && is_string($context['notice'])) {
    $noticeMessage = trim($context['notice']);
}
if ($noticeMessage === '' && isset($strings['messages']['notice']) && is_string($strings['messages']['notice'])) {
    $noticeMessage = trim($strings['messages']['notice']);
}

$dataset = [
    'config'  => $config,
    'strings' => $strings,
    'steps'   => $steps,
    'events'  => $events,
    'privacy' => $privacy,
];

$datasetJson = wp_json_encode($dataset);
if (!is_string($datasetJson)) {
    $datasetJson = '{}';
}

$formId    = $config['formId'] ?? 'fp-resv-form';
$styleCss  = isset($style['css']) ? (string) $style['css'] : '';
$styleHash = isset($style['hash']) ? (string) $style['hash'] : '';
$styleId   = $styleHash !== '' ? 'fp-resv-style-' . $styleHash : 'fp-resv-style-' . md5($formId);

if ($styleCss !== '') :
    ?>
    <style id="<?php echo esc_attr($styleId); ?>"><?php echo wp_strip_all_tags($styleCss); ?></style>
    <?php
endif;
?>
<div
    class="fp-resv-widget fp-resv fp-card"
    id="<?php echo esc_attr($formId); ?>"
    data-fp-resv="<?php echo esc_attr($datasetJson); ?>"
    data-style-hash="<?php echo esc_attr($styleHash); ?>"
    data-fp-resv-app
    style="display: block !important; visibility: visible !important; opacity: 1 !important;"
>
    <form
        class="fp-resv-widget__form fp-section"
        data-fp-resv-form
        action="<?php echo esc_url(rest_url('fp-resv/v1/reservations')); ?>"
        method="post"
        data-fp-resv-start="<?php echo esc_attr($events['start'] ?? 'reservation_start'); ?>"
        novalidate
    >
        <div class="fp-resv-widget__topbar fp-topbar fp-section">
            <div class="fp-resv-widget__titles">
                <h2 class="fp-resv-widget__headline"><?php echo esc_html($strings['headline'] ?? ''); ?></h2>
                <?php if (!empty($strings['subheadline'])) : ?>
                    <p class="fp-resv-widget__subheadline"><?php echo esc_html($strings['subheadline']); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($pdfUrl !== '') : ?>
                <a
                    class="fp-resv-widget__pdf fp-btn fp-btn--ghost"
                    href="<?php echo esc_url($pdfUrl); ?>"
                    target="_blank"
                    rel="noopener"
                    <?php if ($pdfTooltip !== '') : ?>
                        title="<?php echo esc_attr($pdfTooltip); ?>"
                    <?php endif; ?>
                    data-fp-resv-event="<?php echo esc_attr($events['pdf'] ?? 'pdf_download_click'); ?>"
                    data-fp-resv-label="<?php echo esc_attr($pdfLabel); ?>"
                >
                    <?php echo esc_html($pdfLabel); ?>
                </a>
            <?php endif; ?>
        </div>
        <input type="hidden" name="fp_resv_meal" value="<?php echo esc_attr($defaultMealKey); ?>">
        <input type="hidden" name="fp_resv_price_per_person" value="<?php echo esc_attr($defaultMealPrice); ?>">
        <input type="hidden" name="fp_resv_location" value="<?php echo esc_attr($config['location'] ?? 'default'); ?>">
        <input type="hidden" name="fp_resv_locale" value="<?php echo esc_attr($config['locale'] ?? 'it_IT'); ?>">
        <input type="hidden" name="fp_resv_language" value="<?php echo esc_attr($config['language'] ?? 'it'); ?>">
        <input type="hidden" name="fp_resv_currency" value="<?php echo esc_attr($config['defaults']['currency'] ?? 'EUR'); ?>">
        <input type="hidden" name="fp_resv_policy_version" value="<?php echo esc_attr($policyVersion); ?>">
        <input type="hidden" name="fp_resv_phone_e164" value="">
        <input type="hidden" name="fp_resv_phone_cc" value="<?php echo esc_attr($config['defaults']['phone_country_code'] ?? '39'); ?>">
        <input type="hidden" name="fp_resv_phone_local" value="">
        <input type="hidden" name="fp_resv_time" value="" data-fp-resv-field="time">
        <input type="hidden" name="fp_resv_slot_start" value="">
        <div class="fp-resv-widget__feedback" aria-live="polite">
            <div class="fp-alert fp-alert--success" data-fp-resv-success hidden tabindex="-1"></div>
            <div class="fp-alert fp-alert--error" data-fp-resv-error hidden role="alert">
                <p data-fp-resv-error-message></p>
                <button type="button" class="fp-btn fp-btn--ghost" data-fp-resv-error-retry>
                    <?php esc_html_e('Riprova', 'fp-restaurant-reservations'); ?>
                </button>
            </div>
        </div>
        <div class="fp-resv-field fp-resv-field--honeypot fp-field">
            <label class="screen-reader-text" for="<?php echo esc_attr($formId); ?>-hp">
                <?php esc_html_e('Lascia vuoto questo campo', 'fp-restaurant-reservations'); ?>
            </label>
            <input
                class="fp-input"
                type="text"
                id="<?php echo esc_attr($formId); ?>-hp"
                name="fp_resv_hp"
                value=""
                autocomplete="off"
                tabindex="-1"
            >
        </div>
        <?php if ($steps !== []) : ?>
            <div class="fp-resv-progress" data-fp-resv-progress-shell>
                <ul class="fp-progress" data-fp-resv-progress aria-label="<?php esc_attr_e('Avanzamento prenotazione', 'fp-restaurant-reservations'); ?>">
                    <?php foreach ($steps as $index => $step) : ?>
                        <?php
                        $stepKey       = (string) ($step['key'] ?? '');
                        $isCurrent     = $index === 0;
                        $progressLabel = $progressLabels[$stepKey] ?? ($step['title'] ?? '');
                        $stepNumber    = $index + 1;
                        $ariaLabel     = $progressLabel !== ''
                            ? sprintf(
                                /* translators: 1: step number, 2: step label. */
                                __('Step %1$s: %2$s', 'fp-restaurant-reservations'),
                                $stepNumber,
                                $progressLabel
                            )
                            : sprintf(
                                /* translators: %s: step number. */
                                __('Step %s', 'fp-restaurant-reservations'),
                                $stepNumber
                            );
                        $stepIndexLabel = str_pad((string) $stepNumber, 2, '0', STR_PAD_LEFT);
                        ?>
                        <li
                            class="fp-progress__item"
                            data-step="<?php echo esc_attr($stepKey); ?>"
                            data-progress-index="<?php echo esc_attr((string) $stepNumber); ?>"
                            aria-label="<?php echo esc_attr($ariaLabel); ?>"
                            <?php echo $isCurrent ? 'data-state="active" aria-current="step"' : 'data-state="locked"'; ?>
                        >
                            <span class="fp-progress__index"><?php echo esc_html($stepIndexLabel); ?></span>
                            <span class="fp-progress__label"<?php echo $isCurrent ? '' : ' aria-hidden="true"'; ?>><?php echo esc_html($progressLabel); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($noticeMessage !== '') : ?>
            <aside class="fp-alert fp-alert--info" role="status">
                <span class="fp-badge"><?php echo esc_html($strings['badges']['notice'] ?? __('Info', 'fp-restaurant-reservations')); ?></span>
                <p><?php echo esc_html($noticeMessage); ?></p>
            </aside>
        <?php endif; ?>
        <?php $totalSteps = count($steps); ?>
        <ol class="fp-resv-widget__steps" data-fp-resv-steps>
            <?php foreach ($steps as $index => $step) : ?>
                <?php
                $stepKey = (string) ($step['key'] ?? '');
                $isActive = $index === 0;
                $titleId = $formId . '-section-title-' . $stepKey;
                $hasPrevious = $index > 0;
                $hasNext = $index < $totalSteps - 1;
                $previousLabel = $strings['actions']['back'] ?? __('Indietro', 'fp-restaurant-reservations');
                $nextLabel = $strings['actions']['continue'] ?? __('Continua', 'fp-restaurant-reservations');
                ?>
                <li
                    class="fp-resv-step fp-section"
                    data-step="<?php echo esc_attr($stepKey); ?>"
                    data-fp-resv-section
                    data-state="<?php echo $isActive ? 'active' : 'locked'; ?>"
                    aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>"
                    <?php echo $isActive ? '' : 'hidden'; ?>
                    role="region"
                    aria-labelledby="<?php echo esc_attr($titleId); ?>"
                >
                    <header class="fp-resv-step__header">
                        <span class="fp-resv-step__label">
                            <?php echo esc_html($strings['steps'][$stepKey] ?? ($step['title'] ?? '')); ?>
                        </span>
                        <h3 class="fp-resv-step__title" id="<?php echo esc_attr($titleId); ?>"><?php echo esc_html($step['title'] ?? ''); ?></h3>
                        <?php if (!empty($step['description'])) : ?>
                            <p class="fp-resv-step__description"><?php echo esc_html($step['description']); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="fp-resv-step__body">
                        <?php switch ($stepKey) {
                            case 'service': ?>
                                <?php if ($meals !== []) : ?>
                                    <section class="fp-meals" data-fp-resv-meals>
                                        <header class="fp-meals__header">
                                            <h3 class="fp-meals__title"><?php echo esc_html($strings['meals']['title'] ?? ($strings['steps']['service'] ?? '')); ?></h3>
                                            <?php if (!empty($strings['meals']['subtitle'] ?? '')) : ?>
                                                <p class="fp-meals__subtitle fp-hint"><?php echo esc_html($strings['meals']['subtitle']); ?></p>
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
                                                $isActive  = !empty($meal['active']);
                                                ?>
                                                <button
                                                    type="button"
                                                    class="fp-meal-pill"
                                                    data-fp-resv-meal="<?php echo esc_attr($mealKey); ?>"
                                                    data-meal-label="<?php echo esc_attr($mealLabel); ?>"
                                                    data-meal-notice="<?php echo esc_attr($mealNotice); ?>"
                                                    data-meal-default-notice="<?php echo esc_attr($mealNotice); ?>"
                                                    data-meal-price="<?php echo esc_attr($mealPrice); ?>"
                                                    <?php echo $isActive ? 'data-active="true" aria-pressed="true"' : 'aria-pressed="false"'; ?>
                                                >
                                                    <span class="fp-meal-pill__label"><?php echo esc_html($mealLabel); ?></span>
                                                    <?php if ($mealBadge !== '') : ?>
                                                        <span class="fp-badge"<?php echo $mealBadgeIcon !== '' ? ' data-icon="' . esc_attr($mealBadgeIcon) . '"' : ''; ?>><?php echo esc_html($mealBadge); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($mealHint !== '') : ?>
                                                        <span class="fp-hint"><?php echo esc_html($mealHint); ?></span>
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
                                            <em
                                                class="fp-meals__notice-text"
                                                data-fp-resv-meal-notice-text
                                            ><?php echo esc_html($defaultMealNotice); ?></em>
                                        </p>
                                    </section>
                                <?php endif; ?>
                                <?php break;
                            case 'date': ?>
                                <div class="fp-resv-field fp-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['date'] ?? ''); ?></span>
                                        <input class="fp-input" type="date" name="fp_resv_date" data-fp-resv-field="date" min="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                                        <?php if (!empty($hints['date'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['date']); ?></small>
                                        <?php endif; ?>
                                        <small class="fp-error" data-fp-resv-date-status aria-live="polite" hidden></small>
                                    </label>
                                </div>
                                <?php break;
                            case 'party': ?>
                                <div class="fp-resv-field fp-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['party'] ?? ''); ?></span>
                                        <input
                                            class="fp-input"
                                            type="number"
                                            min="1"
                                            max="40"
                                            name="fp_resv_party"
                                            data-fp-resv-field="party"
                                            value="<?php echo esc_attr((string) ($config['defaults']['partySize'] ?? 2)); ?>"
                                            required
                                        >
                                    </label>
                                </div>
                                <?php break;
                            case 'slots': ?>
                                <div class="fp-resv-slots fp-slots" data-fp-resv-slots>
                                    <ul
                                        class="fp-meals__legend"
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
                                    <p class="fp-resv-slots__status" data-fp-resv-slots-status aria-live="polite">
                                        <?php echo esc_html($strings['messages']['slots_loading'] ?? ''); ?>
                                    </p>
                                    <p class="fp-resv-slots__indicator" data-fp-resv-availability-indicator aria-live="polite" hidden></p>
                                    <ul class="fp-resv-slots__list" data-fp-resv-slots-list aria-live="polite" aria-busy="false"></ul>
                                    <p class="fp-resv-slots__empty" data-fp-resv-slots-empty hidden><?php echo esc_html($strings['messages']['slots_empty'] ?? ''); ?></p>
                                    <div class="fp-resv-slots__boundary fp-alert fp-alert--error" data-fp-resv-slots-boundary hidden role="alert">
                                        <span data-fp-resv-slots-boundary-message></span>
                                        <button type="button" class="fp-btn fp-btn--ghost" data-fp-resv-slots-retry>
                                            <?php esc_html_e('Riprova', 'fp-restaurant-reservations'); ?>
                                        </button>
                                    </div>
                                </div>
                                <?php break;
                            case 'details': ?>
                                <?php
                                $phonePrefixes = is_array($config['phone_prefixes'] ?? null) ? $config['phone_prefixes'] : [];
                                $defaultPhoneCode = isset($config['defaults']['phone_country_code']) ? (string) $config['defaults']['phone_country_code'] : '39';
                                $phonePrefixId = $formId . '-phone-prefix';
                                $phoneInputId = $formId . '-phone';
                                $phonePrefixLabel = $strings['fields']['phone_prefix'] ?? __('Prefisso', 'fp-restaurant-reservations');
                                ?>
                                <div class="fp-resv-fields fp-resv-fields--grid">
                                    <label class="fp-resv-field fp-field">
                                        <span><?php echo esc_html($strings['fields']['first_name'] ?? ''); ?></span>
                                        <input class="fp-input" type="text" name="fp_resv_first_name" data-fp-resv-field="first_name" required>
                                        <small class="fp-error" data-fp-resv-error="first_name" aria-live="polite" hidden></small>
                                        <?php if (!empty($hints['first_name'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['first_name']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                    <label class="fp-resv-field fp-field">
                                        <span><?php echo esc_html($strings['fields']['last_name'] ?? ''); ?></span>
                                        <input class="fp-input" type="text" name="fp_resv_last_name" data-fp-resv-field="last_name" required>
                                        <small class="fp-error" data-fp-resv-error="last_name" aria-live="polite" hidden></small>
                                        <?php if (!empty($hints['last_name'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['last_name']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                    <label class="fp-resv-field fp-field fp-resv-field--email">
                                        <span><?php echo esc_html($strings['fields']['email'] ?? ''); ?></span>
                                        <input class="fp-input" type="email" name="fp_resv_email" data-fp-resv-field="email" required>
                                        <small class="fp-error" data-fp-resv-error="email" aria-live="polite" hidden></small>
                                    </label>
                                    <label class="fp-resv-field fp-field fp-resv-field--phone">
                                        <span><?php echo esc_html($strings['fields']['phone'] ?? ''); ?></span>
                                        <div class="fp-resv-phone-input" data-fp-resv-phone>
                                            <input
                                                class="fp-input"
                                                type="tel"
                                                id="<?php echo esc_attr($phoneInputId); ?>"
                                                name="fp_resv_phone"
                                                data-fp-resv-field="phone"
                                                inputmode="tel"
                                                autocomplete="tel"
                                                required
                                            >
                                            <?php if ($phonePrefixes !== []) : ?>
                                                <select
                                                    class="fp-input fp-input--prefix"
                                                    id="<?php echo esc_attr($phonePrefixId); ?>"
                                                    name="fp_resv_phone_prefix"
                                                    data-fp-resv-field="phone_prefix"
                                                    aria-label="<?php echo esc_attr($phonePrefixLabel); ?>"
                                                >
                                                    <?php foreach ($phonePrefixes as $prefixOption) :
                                                        $optionValue = (string) ($prefixOption['value'] ?? '');
                                                        $optionLabel = (string) ($prefixOption['label'] ?? $optionValue);
                                                        ?>
                                                        <option value="<?php echo esc_attr($optionValue); ?>" <?php selected($optionValue === $defaultPhoneCode); ?>>
                                                            <?php echo esc_html($optionLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else : ?>
                                                <span class="fp-resv-phone-input__static" aria-hidden="true">+<?php echo esc_html($defaultPhoneCode); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="fp-error" data-fp-resv-error="phone" aria-live="polite" hidden></small>
                                        <?php if (!empty($hints['phone'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['phone']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <label class="fp-resv-field fp-field">
                                    <span><?php echo esc_html($strings['fields']['notes'] ?? ''); ?></span>
                                    <textarea class="fp-textarea" name="fp_resv_notes" data-fp-resv-field="notes" rows="3"></textarea>
                                    <?php if (!empty($hints['notes'] ?? '')) : ?>
                                        <small class="fp-hint"><?php echo esc_html($hints['notes']); ?></small>
                                    <?php endif; ?>
                                </label>
                                <fieldset class="fp-resv-extra fp-fieldset">
                                    <legend class="fp-resv-extra__title"><?php echo esc_html($strings['extras']['title'] ?? __('Richieste aggiuntive', 'fp-restaurant-reservations')); ?></legend>
                                    <div class="fp-resv-fields fp-resv-fields--grid">
                                        <label class="fp-resv-field fp-field">
                                            <span><?php echo esc_html($strings['extras']['high_chair'] ?? __('Quanti seggioloni servono?', 'fp-restaurant-reservations')); ?></span>
                                            <input
                                                class="fp-input"
                                                type="number"
                                                min="0"
                                                max="5"
                                                name="fp_resv_high_chair_count"
                                                data-fp-resv-field="high_chair_count"
                                                value="0"
                                                inputmode="numeric"
                                            >
                                        </label>
                                        <label class="fp-resv-field fp-field fp-resv-field--checkbox">
                                            <input class="fp-checkbox" type="checkbox" name="fp_resv_wheelchair_table" value="1" data-fp-resv-field="wheelchair_table">
                                            <span><?php echo esc_html($strings['extras']['wheelchair_table'] ?? __('Serve un tavolo accessibile per sedia a rotelle', 'fp-restaurant-reservations')); ?></span>
                                        </label>
                                        <label class="fp-resv-field fp-field fp-resv-field--checkbox">
                                            <input class="fp-checkbox" type="checkbox" name="fp_resv_pets" value="1" data-fp-resv-field="pets">
                                            <span><?php echo esc_html($strings['extras']['pets'] ?? __('Vengo con un animale domestico', 'fp-restaurant-reservations')); ?></span>
                                        </label>
                                    </div>
                                </fieldset>
                                <label class="fp-resv-field fp-field">
                                    <span><?php echo esc_html($strings['fields']['allergies'] ?? ''); ?></span>
                                    <textarea class="fp-textarea" name="fp_resv_allergies" data-fp-resv-field="allergies" rows="3"></textarea>
                                </label>
                                <?php
                                $requiredConsentLabel = $strings['consents_meta']['required'] ?? __('Obbligatorio', 'fp-restaurant-reservations');
                                $optionalConsentLabel = $strings['consents_meta']['optional'] ?? __('Opzionale', 'fp-restaurant-reservations');
                                ?>
                                <label class="fp-resv-field fp-resv-field--consent fp-field">
                                    <input class="fp-checkbox" type="checkbox" name="fp_resv_consent" data-fp-resv-field="consent" required>
                                    <span class="fp-resv-consent__text">
                                        <span class="fp-resv-consent__copy">
                                            <?php echo esc_html($strings['fields']['consent'] ?? ''); ?>
                                            <?php if ($policyUrl !== '') : ?>
                                                <a href="<?php echo esc_url($policyUrl); ?>" target="_blank" rel="noopener">
                                                    <?php echo esc_html($strings['consents']['policy_link'] ?? ''); ?>
                                                </a>
                                            <?php endif; ?>
                                        </span>
                                        <span class="fp-resv-consent__meta fp-resv-consent__meta--required"><?php echo esc_html($requiredConsentLabel); ?></span>
                                    </span>
                                    <small class="fp-error" data-fp-resv-error="consent" aria-live="polite" hidden></small>
                                </label>
                                <?php if ($marketingEnabled) : ?>
                                    <label class="fp-resv-field fp-resv-field--consent fp-field">
                                        <input class="fp-checkbox" type="checkbox" name="fp_resv_marketing_consent" value="1" data-fp-resv-field="marketing_consent">
                                        <span class="fp-resv-consent__text">
                                            <span class="fp-resv-consent__copy"><?php echo esc_html($strings['consents']['marketing'] ?? ''); ?></span>
                                            <span class="fp-resv-consent__meta"><?php echo esc_html($optionalConsentLabel); ?></span>
                                        </span>
                                    </label>
                                <?php endif; ?>
                                <?php if ($profilingEnabled) : ?>
                                    <label class="fp-resv-field fp-resv-field--consent fp-field">
                                        <input class="fp-checkbox" type="checkbox" name="fp_resv_profiling_consent" value="1" data-fp-resv-field="profiling_consent">
                                        <span class="fp-resv-consent__text">
                                            <span class="fp-resv-consent__copy"><?php echo esc_html($strings['consents']['profiling'] ?? ''); ?></span>
                                            <span class="fp-resv-consent__meta"><?php echo esc_html($optionalConsentLabel); ?></span>
                                        </span>
                                    </label>
                                <?php endif; ?>
                                <?php break;
                            case 'confirm': ?>
                                <section class="fp-resv-summary" data-fp-resv-summary>
                                    <h4 class="fp-resv-summary__title"><?php echo esc_html($strings['summary']['title'] ?? ''); ?></h4>
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
                                        <div>
                                            <dt><?php echo esc_html($strings['summary']['labels']['notes'] ?? ''); ?></dt>
                                            <dd data-fp-resv-summary="notes"></dd>
                                        </div>
                                        <div>
                                            <dt><?php echo esc_html($strings['summary']['labels']['extras'] ?? __('Richieste aggiuntive', 'fp-restaurant-reservations')); ?></dt>
                                            <dd data-fp-resv-summary="extras"></dd>
                                        </div>
                                    </dl>
                                    <p class="fp-resv-summary__disclaimer"><?php echo esc_html($strings['summary']['disclaimer'] ?? ''); ?></p>
                                </section>
                                <?php break;
                        }
                        ?>
                    </div>
                    <?php if ($hasPrevious || $hasNext) : ?>
                        <footer class="fp-resv-step__footer">
                            <?php if ($hasPrevious) : ?>
                                <button type="button" class="fp-btn fp-btn--ghost" data-fp-resv-nav="prev">
                                    <?php echo esc_html($previousLabel); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($hasNext) : ?>
                                <button type="button" class="fp-btn fp-btn--primary" data-fp-resv-nav="next">
                                    <?php echo esc_html($nextLabel); ?>
                                </button>
                            <?php endif; ?>
                        </footer>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="fp-resv-widget__actions fp-resv-sticky-cta" data-fp-resv-sticky-cta>
            <?php
            $submitLabel = $strings['actions']['submit'] ?? __('Prenota ora', 'fp-restaurant-reservations');
            $ctaDisabled = $strings['messages']['cta_complete_fields'] ?? __('Completa i campi richiesti', 'fp-restaurant-reservations');
            $submitHint  = $strings['messages']['submit_hint'] ?? __('Completa tutti i passaggi per prenotare.', 'fp-restaurant-reservations');
            $submitTooltip = $strings['messages']['submit_tooltip'] ?? __('Completa i campi obbligatori per abilitare la prenotazione.', 'fp-restaurant-reservations');
            $submitHintId = $formId . '-submit-hint';
            ?>
            <button
                type="submit"
                class="fp-resv-button fp-resv-button--submit fp-btn fp-btn--primary"
                data-fp-resv-submit
                data-disabled-tooltip="<?php echo esc_attr($submitTooltip); ?>"
                aria-disabled="true"
                disabled
                aria-describedby="<?php echo esc_attr($submitHintId); ?>"
            >
                <span class="fp-btn__spinner" data-fp-resv-submit-spinner hidden>···</span>
                <span class="fp-btn__label" data-fp-resv-submit-label><?php echo esc_html($ctaDisabled); ?></span>
            </button>
            <p class="fp-resv-widget__submit-hint fp-hint" id="<?php echo esc_attr($submitHintId); ?>" data-fp-resv-submit-hint aria-live="polite">
                <?php echo esc_html($submitHint); ?>
            </p>
        </div>
        <?php wp_nonce_field('fp_resv_submit', 'fp_resv_nonce'); ?>
    </form>
    <noscript class="fp-resv-widget__nojs fp-alert fp-alert--info">
        <?php echo esc_html__('Per inviare la prenotazione abilita JavaScript o contattaci direttamente.', 'fp-restaurant-reservations'); ?>
    </noscript>
</div>
