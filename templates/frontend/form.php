<?php
/**
 * Frontend reservation form markup.
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    error_log('[FP-RESV] Template: context non valido o assente');
    error_log('[FP-RESV] Template: context type: ' . gettype($context));
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div style="background:#fee;border:2px solid #c33;padding:20px;margin:20px 0;border-radius:8px;">';
        echo '<h3 style="color:#c33;">⚠️ Errore Template Form</h3>';
        echo '<p>Il context non è disponibile o non è valido.</p>';
        echo '</div>';
    }
    return;
}

error_log('[FP-RESV] Template: inizia rendering form con context keys: ' . implode(', ', array_keys($context)));

// Validate essential context data
if (empty($context['config'])) {
    error_log('[FP-RESV] Template: WARNING - config is empty');
}
if (empty($context['strings'])) {
    error_log('[FP-RESV] Template: WARNING - strings is empty');
}
if (empty($context['steps'])) {
    error_log('[FP-RESV] Template: WARNING - steps is empty');
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
    'meals'   => $meals,
];

$datasetJson = wp_json_encode($dataset);
if (!is_string($datasetJson)) {
    $datasetJson = '{}';
}

$formId    = $config['formId'] ?? 'fp-resv-form';
$styleCss  = isset($style['css']) ? (string) $style['css'] : '';
$styleHash = isset($style['hash']) ? (string) $style['hash'] : '';
$styleId   = $styleHash !== '' ? 'fp-resv-style-' . $styleHash : 'fp-resv-style-' . md5($formId);

// Output inline styles using WordPress best practices
// This avoids WPBakery escaping issues while being more semantic
// NOTE: CSS Bridge in form/_variables-bridge.css ensures compatibility between
//       dynamic (--fp-resv-*) and static (--fp-color-*) variable systems
if ($styleCss !== '') {
    // Check if we're in a WPBakery/Visual Composer context
    $isWPBakery = function_exists('vc_is_inline') && vc_is_inline();
    
    if ($isWPBakery) {
        // WPBakery: Use JavaScript injection to avoid escaping
        $escapedCss = str_replace("'", "\\'", str_replace("\n", '', $styleCss));
        ?>
        <script>
        (function() {
            var styleId = '<?php echo esc_js($styleId); ?>';
            var css = '<?php echo $escapedCss; ?>';
            if (css && !document.getElementById(styleId)) {
                var style = document.createElement('style');
                style.id = styleId;
                style.textContent = css;
                document.head.appendChild(style);
            }
        })();
        </script>
        <?php
    } else {
        // Normal context: Use proper <style> tag
        ?>
        <style id="<?php echo esc_attr($styleId); ?>" type="text/css">
        <?php echo wp_strip_all_tags($styleCss); ?>
        </style>
        <?php
    }
}
?>
<div
    class="fp-resv-widget fp-resv fp-card"
    id="<?php echo esc_attr($formId); ?>"
    data-fp-resv="<?php echo esc_attr($datasetJson); ?>"
    data-style-hash="<?php echo esc_attr($styleHash); ?>"
    data-fp-resv-app
    data-version="<?php echo esc_attr(defined('FP_RESV_VERSION') ? FP_RESV_VERSION : '0.1.0'); ?>"
    style="display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important; width: 100% !important; height: auto !important;"
    role="region"
    aria-label="<?php echo esc_attr($strings['headline'] ?? 'Modulo di prenotazione'); ?>"
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
                        <?php
                        // Include step partial file
                        $stepPartialFile = __DIR__ . '/form-parts/steps/step-' . $stepKey . '.php';
                        if (file_exists($stepPartialFile)) {
                            include $stepPartialFile;
                        } else {
                            // Fallback: mostra messaggio di errore in debug mode
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                echo '<!-- Step file not found: ' . esc_html($stepPartialFile) . ' -->';
                            }
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
