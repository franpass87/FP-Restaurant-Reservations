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
$dataLayer  = $context['data_layer'] ?? [];
$events     = $dataLayer['events'] ?? [];
$privacy    = $context['privacy'] ?? [];
$policyUrl  = isset($privacy['policy_url']) ? (string) $privacy['policy_url'] : '';
$policyVersion = isset($privacy['policy_version']) ? (string) $privacy['policy_version'] : '';
$marketingEnabled = !empty($privacy['marketing_enabled']);
$profilingEnabled = !empty($privacy['profiling_enabled']);
$style      = $context['style'] ?? [];
$progressLabels = is_array($strings['steps_labels'] ?? null) ? $strings['steps_labels'] : [];
$meals = isset($context['meals']) && is_array($context['meals']) ? array_values($context['meals']) : [];
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
    <style id="<?php echo esc_attr($styleId); ?>"><?php echo esc_html($styleCss); ?></style>
    <?php
endif;
?>
<div
    class="fp-resv-widget fp-resv fp-card"
    id="<?php echo esc_attr($formId); ?>"
    data-fp-resv="<?php echo esc_attr($datasetJson); ?>"
    data-style-hash="<?php echo esc_attr($styleHash); ?>"
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
                data-fp-resv-event="<?php echo esc_attr($events['pdf'] ?? 'pdf_download_click'); ?>"
                data-fp-resv-label="<?php echo esc_attr($strings['pdf_label'] ?? ''); ?>"
            >
                <?php echo esc_html($strings['pdf_label'] ?? ''); ?>
            </a>
        <?php endif; ?>
    </div>
    <form
        class="fp-resv-widget__form fp-section"
        data-fp-resv-form
        action=""
        method="post"
        data-fp-resv-start="<?php echo esc_attr($events['start'] ?? 'reservation_start'); ?>"
        novalidate
    >
        <input type="hidden" name="fp_resv_meal" value="">
        <input type="hidden" name="fp_resv_price_per_person" value="">
        <input type="hidden" name="fp_resv_location" value="<?php echo esc_attr($config['location'] ?? 'default'); ?>">
        <input type="hidden" name="fp_resv_locale" value="<?php echo esc_attr($config['locale'] ?? 'it_IT'); ?>">
        <input type="hidden" name="fp_resv_language" value="<?php echo esc_attr($config['language'] ?? 'it'); ?>">
        <input type="hidden" name="fp_resv_currency" value="<?php echo esc_attr($config['defaults']['currency'] ?? 'EUR'); ?>">
        <input type="hidden" name="fp_resv_policy_version" value="<?php echo esc_attr($policyVersion); ?>">
        <label class="fp-resv-field fp-resv-field--honeypot fp-field" aria-hidden="true" tabindex="-1">
            <span class="screen-reader-text"><?php esc_html_e('Lascia vuoto questo campo', 'fp-restaurant-reservations'); ?></span>
            <input class="fp-input" type="text" name="fp_resv_hp" value="" autocomplete="off">
        </label>
        <?php if ($steps !== []) : ?>
            <ul class="fp-progress" data-fp-resv-progress aria-label="<?php esc_attr_e('Avanzamento prenotazione', 'fp-restaurant-reservations'); ?>">
                <?php foreach ($steps as $index => $step) : ?>
                    <?php
                    $stepKey   = (string) ($step['key'] ?? '');
                    $isCurrent = $index === 0;
                    ?>
                    <li
                        class="fp-progress__item"
                        data-step="<?php echo esc_attr($stepKey); ?>"
                        <?php echo $isCurrent ? 'data-state="active" aria-current="step"' : 'data-state="locked"'; ?>
                    >
                        <span class="fp-progress__index"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                        <span class="fp-progress__label"><?php echo esc_html($progressLabels[$stepKey] ?? ($step['title'] ?? '')); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($meals !== []) : ?>
            <section class="fp-section fp-meals" data-fp-resv-meals>
                <header class="fp-meals__header">
                    <h3 class="fp-meals__title"><?php echo esc_html($strings['meals']['title'] ?? ($strings['steps_labels']['slots'] ?? '')); ?></h3>
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
                        $mealHint  = isset($meal['hint']) ? (string) $meal['hint'] : '';
                        $mealNotice = isset($meal['notice']) ? (string) $meal['notice'] : '';
                        $mealPrice  = isset($meal['price']) ? (float) $meal['price'] : 0.0;
                        $isActive  = !empty($meal['active']);
                        ?>
                        <button
                            type="button"
                            class="fp-meal-pill"
                            data-fp-resv-meal="<?php echo esc_attr($mealKey); ?>"
                            data-meal-label="<?php echo esc_attr($mealLabel); ?>"
                            data-meal-notice="<?php echo esc_attr($mealNotice); ?>"
                            data-meal-price="<?php echo esc_attr($mealPrice); ?>"
                            <?php echo $isActive ? 'data-active="true" aria-pressed="true"' : 'aria-pressed="false"'; ?>
                        >
                            <span class="fp-meal-pill__label"><?php echo esc_html($mealLabel); ?></span>
                            <?php if ($mealBadge !== '') : ?>
                                <span class="fp-badge"><?php echo esc_html($mealBadge); ?></span>
                            <?php endif; ?>
                            <?php if ($mealHint !== '') : ?>
                                <span class="fp-hint"><?php echo esc_html($mealHint); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="fp-meals__notice fp-hint" data-fp-resv-meal-notice hidden></p>
            </section>
        <?php endif; ?>
        <?php if ($noticeMessage !== '') : ?>
            <aside class="fp-alert fp-alert--info" role="status">
                <span class="fp-badge"><?php echo esc_html($strings['badges']['notice'] ?? __('Info', 'fp-restaurant-reservations')); ?></span>
                <p><?php echo esc_html($noticeMessage); ?></p>
            </aside>
        <?php endif; ?>
        <ol class="fp-resv-widget__steps" data-fp-resv-steps>
            <?php foreach ($steps as $index => $step) : ?>
                <?php
                $stepKey = (string) ($step['key'] ?? '');
                $isActive = $index === 0;
                ?>
                <li
                    class="fp-resv-step fp-section"
                    data-step="<?php echo esc_attr($stepKey); ?>"
                    data-fp-resv-section
                    data-state="<?php echo $isActive ? 'active' : 'locked'; ?>"
                    aria-hidden="<?php echo $isActive ? 'false' : 'true'; ?>"
                    aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>"
                >
                    <header class="fp-resv-step__header">
                        <span class="fp-resv-step__label">
                            <?php echo esc_html($strings['steps'][$stepKey] ?? ($step['title'] ?? '')); ?>
                        </span>
                        <h3 class="fp-resv-step__title"><?php echo esc_html($step['title'] ?? ''); ?></h3>
                        <?php if (!empty($step['description'])) : ?>
                            <p class="fp-resv-step__description"><?php echo esc_html($step['description']); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="fp-resv-step__body">
                        <?php switch ($stepKey) {
                            case 'date': ?>
                                <div class="fp-resv-field fp-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['date'] ?? ''); ?></span>
                                        <input class="fp-input" type="date" name="fp_resv_date" data-fp-resv-field="date" required>
                                        <?php if (!empty($hints['date'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['date']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <div class="fp-resv-field fp-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['time'] ?? ''); ?></span>
                                        <input class="fp-input" type="time" name="fp_resv_time" data-fp-resv-field="time">
                                        <?php if (!empty($hints['time'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['time']); ?></small>
                                        <?php endif; ?>
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
                                    <p class="fp-resv-slots__status" data-state="loading"><?php echo esc_html($strings['messages']['slots_loading'] ?? ''); ?></p>
                                    <ul class="fp-resv-slots__list" aria-live="polite" aria-busy="false"></ul>
                                    <p class="fp-resv-slots__empty" hidden><?php echo esc_html($strings['messages']['slots_empty'] ?? ''); ?></p>
                                </div>
                                <?php break;
                            case 'details': ?>
                                <div class="fp-resv-fields fp-resv-fields--grid">
                                    <label class="fp-resv-field fp-field">
                                        <span><?php echo esc_html($strings['fields']['first_name'] ?? ''); ?></span>
                                        <input class="fp-input" type="text" name="fp_resv_first_name" data-fp-resv-field="first_name" required>
                                        <?php if (!empty($hints['first_name'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['first_name']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                    <label class="fp-resv-field fp-field">
                                        <span><?php echo esc_html($strings['fields']['last_name'] ?? ''); ?></span>
                                        <input class="fp-input" type="text" name="fp_resv_last_name" data-fp-resv-field="last_name" required>
                                        <?php if (!empty($hints['last_name'] ?? '')) : ?>
                                            <small class="fp-hint"><?php echo esc_html($hints['last_name']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                    <label class="fp-resv-field fp-field">
                                        <span><?php echo esc_html($strings['fields']['email'] ?? ''); ?></span>
                                        <input class="fp-input" type="email" name="fp_resv_email" data-fp-resv-field="email" required>
                                    </label>
                                    <label class="fp-resv-field fp-field">
                                        <span><?php echo esc_html($strings['fields']['phone'] ?? ''); ?></span>
                                        <input class="fp-input" type="tel" name="fp_resv_phone" data-fp-resv-field="phone">
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
                                <label class="fp-resv-field fp-field">
                                    <span><?php echo esc_html($strings['fields']['allergies'] ?? ''); ?></span>
                                    <textarea class="fp-textarea" name="fp_resv_allergies" data-fp-resv-field="allergies" rows="3"></textarea>
                                </label>
                                <label class="fp-resv-field fp-resv-field--consent fp-field">
                                    <input class="fp-checkbox" type="checkbox" name="fp_resv_consent" data-fp-resv-field="consent" required>
                                    <span>
                                        <?php echo esc_html($strings['fields']['consent'] ?? ''); ?>
                                        <?php if ($policyUrl !== '') : ?>
                                            <a href="<?php echo esc_url($policyUrl); ?>" target="_blank" rel="noopener">
                                                <?php echo esc_html($strings['consents']['policy_link'] ?? ''); ?>
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php if ($marketingEnabled) : ?>
                                    <label class="fp-resv-field fp-resv-field--consent fp-field">
                                        <input class="fp-checkbox" type="checkbox" name="fp_resv_marketing_consent" value="1" data-fp-resv-field="marketing_consent">
                                        <span><?php echo esc_html($strings['consents']['marketing'] ?? ''); ?></span>
                                    </label>
                                <?php endif; ?>
                                <?php if ($profilingEnabled) : ?>
                                    <label class="fp-resv-field fp-resv-field--consent fp-field">
                                        <input class="fp-checkbox" type="checkbox" name="fp_resv_profiling_consent" value="1" data-fp-resv-field="profiling_consent">
                                        <span><?php echo esc_html($strings['consents']['profiling'] ?? ''); ?></span>
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
                                    </dl>
                                    <p class="fp-resv-summary__disclaimer"><?php echo esc_html($strings['summary']['disclaimer'] ?? ''); ?></p>
                                </section>
                                <?php break;
                        }
                        ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="fp-resv-widget__actions">
            <?php
            $submitLabel = $strings['actions']['submit'] ?? __('Prenota ora', 'fp-restaurant-reservations');
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
                <?php echo esc_html($submitLabel); ?>
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
