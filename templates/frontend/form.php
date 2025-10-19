<?php
/**
 * Frontend reservation form - VERSIONE SEMPLIFICATA
 * 
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    return;
}

// USA IL FORM SEMPLICE
echo "<!-- FORM SEMPLICE CARICATO: " . date('H:i:s') . " -->";
include __DIR__ . '/form-simple.php';
return;

$config = $context['config'] ?? [];
$strings = $context['strings'] ?? [];
$steps = $context['steps'] ?? [];
$meals = $context['meals'] ?? [];
$privacy = $context['privacy'] ?? [];
$style = $context['style'] ?? [];
$events = ($context['data_layer'] ?? [])['events'] ?? [];

$formId = $config['formId'] ?? 'fp-resv-form';
$pdfUrl = $context['pdf_url'] ?? '';
$pdfLabel = $strings['pdf_label'] ?? __('Scopri il Menu', 'fp-restaurant-reservations');

// Preparazione dataset per JavaScript
$dataset = [
    'config' => $config,
    'strings' => $strings,
    'steps' => $steps,
    'events' => $events,
    'privacy' => $privacy,
    'meals' => $meals,
];

$datasetJson = wp_json_encode($dataset);
if (!is_string($datasetJson)) {
    $datasetJson = '{}';
}

// CSS dinamico inline
$styleCss = $style['css'] ?? '';
$styleHash = $style['hash'] ?? md5($formId);
$styleId = 'fp-resv-style-' . $styleHash;

if ($styleCss !== '') {
    $escapedCss = str_replace(["\r\n", "\n", "\r"], '', $styleCss);
    $escapedCss = str_replace("'", "\\'", $escapedCss);
    ?>
    <script>
    (function() {
        var styleId = '<?php echo esc_js($styleId); ?>';
        var css = '<?php echo $escapedCss; ?>';
        if (css && !document.getElementById(styleId)) {
            var style = document.createElement('style');
            style.id = styleId;
            style.type = 'text/css';
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        }
    })();
    </script>
    <?php
}
?>

<div 
    class="fp-resv-widget" 
    id="<?php echo esc_attr($formId); ?>"
    data-fp-resv="<?php echo esc_attr($datasetJson); ?>"
    data-fp-resv-app
    role="region"
    aria-label="<?php echo esc_attr($strings['headline'] ?? 'Modulo di prenotazione'); ?>"
>
    <form 
        class="fp-resv-form" 
        data-fp-resv-form
        action="<?php echo esc_url(rest_url('fp-resv/v1/reservations')); ?>"
        method="post"
        novalidate
    >
        <!-- Header -->
        <div class="fp-resv-topbar">
            <div class="fp-resv-titles">
                <h2 class="fp-resv-headline"><?php echo esc_html($strings['headline'] ?? 'Prenota un tavolo'); ?></h2>
                <?php if (!empty($strings['subheadline'])) : ?>
                    <p class="fp-resv-subheadline"><?php echo esc_html($strings['subheadline']); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($pdfUrl !== '') : ?>
                <a 
                    class="fp-btn fp-btn--ghost" 
                    href="<?php echo esc_url($pdfUrl); ?>" 
                    target="_blank" 
                    rel="noopener"
                >
                    <?php echo esc_html($pdfLabel); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Hidden fields -->
        <?php
        $defaultMeal = null;
        foreach ($meals as $meal) {
            if (!empty($meal['active'])) {
                $defaultMeal = $meal;
                break;
            }
        }
        if (!$defaultMeal && count($meals) > 0) {
            $defaultMeal = $meals[0];
        }
        ?>
        <input type="hidden" name="fp_resv_meal" value="<?php echo esc_attr($defaultMeal['key'] ?? ''); ?>">
        <input type="hidden" name="fp_resv_price_per_person" value="<?php echo esc_attr($defaultMeal['price'] ?? ''); ?>">
        <input type="hidden" name="fp_resv_location" value="<?php echo esc_attr($config['location'] ?? 'default'); ?>">
        <input type="hidden" name="fp_resv_locale" value="<?php echo esc_attr($config['locale'] ?? 'it_IT'); ?>">
        <input type="hidden" name="fp_resv_language" value="<?php echo esc_attr($config['language'] ?? 'it'); ?>">
        <input type="hidden" name="fp_resv_currency" value="<?php echo esc_attr($config['defaults']['currency'] ?? 'EUR'); ?>">
        <input type="hidden" name="fp_resv_policy_version" value="<?php echo esc_attr($privacy['policy_version'] ?? ''); ?>">
        <input type="hidden" name="fp_resv_phone_e164" value="">
        <input type="hidden" name="fp_resv_phone_cc" value="<?php echo esc_attr($config['defaults']['phone_country_code'] ?? '39'); ?>">
        <input type="hidden" name="fp_resv_phone_local" value="">
        <input type="hidden" name="fp_resv_time" value="" data-fp-resv-field="time">
        <input type="hidden" name="fp_resv_slot_start" value="">

        <!-- Honeypot anti-spam -->
        <div class="fp-field-honeypot">
            <label class="screen-reader-text" for="<?php echo esc_attr($formId); ?>-hp">Lascia vuoto</label>
            <input 
                type="text" 
                id="<?php echo esc_attr($formId); ?>-hp" 
                name="fp_resv_hp" 
                value="" 
                autocomplete="off" 
                tabindex="-1"
            >
        </div>

        <!-- Feedback area -->
        <div class="fp-resv-feedback" aria-live="polite">
            <div class="fp-alert fp-alert--success" data-fp-resv-success hidden></div>
            <div class="fp-alert fp-alert--error" data-fp-resv-error hidden>
                <p data-fp-resv-error-message></p>
                <button type="button" class="fp-btn fp-btn--ghost" data-fp-resv-error-retry>
                    <?php esc_html_e('Riprova', 'fp-restaurant-reservations'); ?>
                </button>
            </div>
        </div>

        <!-- Progress bar -->
        <?php if (count($steps) > 0) : ?>
            <div class="fp-resv-progress" data-fp-resv-progress-shell>
                <ul class="fp-progress" data-fp-resv-progress>
                    <?php foreach ($steps as $index => $step) : ?>
                        <?php
                        $stepKey = $step['key'] ?? '';
                        $stepLabel = ($strings['steps'][$stepKey] ?? '') ?: ($step['title'] ?? '');
                        $stepNumber = $index + 1;
                        ?>
                        <li 
                            class="fp-progress__item" 
                            data-step="<?php echo esc_attr($stepKey); ?>"
                            data-state="<?php echo $index === 0 ? 'active' : 'locked'; ?>"
                            aria-label="Step <?php echo esc_attr($stepNumber); ?>: <?php echo esc_attr($stepLabel); ?>"
                        >
                            <span class="fp-progress__index"><?php echo str_pad($stepNumber, 2, '0', STR_PAD_LEFT); ?></span>
                            <span class="fp-progress__label"><?php echo esc_html($stepLabel); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Steps -->
        <ol class="fp-resv-steps" data-fp-resv-steps>
            <?php foreach ($steps as $index => $step) : ?>
                <?php
                $stepKey = $step['key'] ?? '';
                $isActive = $index === 0;
                ?>
                <li 
                    class="fp-resv-step" 
                    data-step="<?php echo esc_attr($stepKey); ?>"
                    data-fp-resv-section
                    data-state="<?php echo $isActive ? 'active' : 'locked'; ?>"
                    <?php echo $isActive ? '' : 'hidden'; ?>
                >
                    <header class="fp-resv-step__header">
                        <span class="fp-resv-step__label"><?php echo esc_html($strings['steps'][$stepKey] ?? $step['title'] ?? ''); ?></span>
                        <h3 class="fp-resv-step__title"><?php echo esc_html($step['title'] ?? ''); ?></h3>
                        <?php if (!empty($step['description'])) : ?>
                            <p class="fp-resv-step__description"><?php echo esc_html($step['description']); ?></p>
                        <?php endif; ?>
                    </header>

                    <div class="fp-resv-step__body">
                        <?php
                        // Include step partial
                        $stepPartialFile = __DIR__ . '/form-parts/steps/step-' . $stepKey . '.php';
                        if (file_exists($stepPartialFile)) {
                            include $stepPartialFile;
                        }
                        ?>
                    </div>

                    <?php if ($index > 0 || $index < count($steps) - 1) : ?>
                        <footer class="fp-resv-step__footer">
                            <?php if ($index > 0) : ?>
                                <button type="button" class="fp-btn fp-btn--ghost" data-fp-resv-nav="prev">
                                    <?php echo esc_html($strings['actions']['back'] ?? 'Indietro'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($index < count($steps) - 1) : ?>
                                <button type="button" class="fp-btn fp-btn--primary" data-fp-resv-nav="next">
                                    <?php echo esc_html($strings['actions']['continue'] ?? 'Continua'); ?>
                                </button>
                            <?php endif; ?>
                        </footer>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <!-- Submit area -->
        <div class="fp-resv-actions" data-fp-resv-sticky-cta>
            <button 
                type="submit" 
                class="fp-btn fp-btn--primary fp-btn--submit" 
                data-fp-resv-submit 
                disabled 
                aria-disabled="true"
            >
                <span class="fp-btn__spinner" data-fp-resv-submit-spinner hidden>···</span>
                <span class="fp-btn__label" data-fp-resv-submit-label>
                    <?php echo esc_html($strings['messages']['cta_complete_fields'] ?? 'Completa i campi'); ?>
                </span>
            </button>
            <p class="fp-resv-submit-hint" data-fp-resv-submit-hint>
                <?php echo esc_html($strings['messages']['submit_hint'] ?? 'Completa tutti i passaggi per prenotare'); ?>
            </p>
        </div>

        <?php wp_nonce_field('fp_resv_submit', 'fp_resv_nonce'); ?>
    </form>

    <noscript class="fp-alert fp-alert--info">
        <?php esc_html_e('Per prenotare abilita JavaScript nel tuo browser', 'fp-restaurant-reservations'); ?>
    </noscript>
</div>
