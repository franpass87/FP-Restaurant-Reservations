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
    class="fp-resv-widget"
    id="<?php echo esc_attr($formId); ?>"
    data-fp-resv="<?php echo esc_attr($datasetJson); ?>"
    data-style-hash="<?php echo esc_attr($styleHash); ?>"
>
    <div class="fp-resv-widget__topbar">
        <div class="fp-resv-widget__titles">
            <h2 class="fp-resv-widget__headline"><?php echo esc_html($strings['headline'] ?? ''); ?></h2>
            <?php if (!empty($strings['subheadline'])) : ?>
                <p class="fp-resv-widget__subheadline"><?php echo esc_html($strings['subheadline']); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($pdfUrl !== '') : ?>
            <a
                class="fp-resv-widget__pdf"
                href="<?php echo esc_url($pdfUrl); ?>"
                target="_blank"
                rel="noopener"
                data-fp-resv-event="<?php echo esc_attr($events['pdf'] ?? 'pdf_download_click'); ?>"
            >
                <?php echo esc_html($strings['pdf_label'] ?? ''); ?>
            </a>
        <?php endif; ?>
    </div>
    <form
        class="fp-resv-widget__form"
        data-fp-resv-form
        action=""
        method="post"
        data-fp-resv-start="<?php echo esc_attr($events['start'] ?? 'reservation_start'); ?>"
        novalidate
    >
        <input type="hidden" name="fp_resv_location" value="<?php echo esc_attr($config['location'] ?? 'default'); ?>">
        <input type="hidden" name="fp_resv_locale" value="<?php echo esc_attr($config['locale'] ?? 'it_IT'); ?>">
        <input type="hidden" name="fp_resv_language" value="<?php echo esc_attr($config['language'] ?? 'it'); ?>">
        <input type="hidden" name="fp_resv_currency" value="<?php echo esc_attr($config['defaults']['currency'] ?? 'EUR'); ?>">
        <input type="hidden" name="fp_resv_policy_version" value="<?php echo esc_attr($policyVersion); ?>">
        <label class="fp-resv-field fp-resv-field--honeypot" aria-hidden="true" tabindex="-1">
            <span class="screen-reader-text"><?php esc_html_e('Lascia vuoto questo campo', 'fp-restaurant-reservations'); ?></span>
            <input type="text" name="fp_resv_hp" value="" autocomplete="off">
        </label>
        <ol class="fp-resv-widget__steps">
            <?php foreach ($steps as $step) : ?>
                <?php $stepKey = (string) ($step['key'] ?? ''); ?>
                <li class="fp-resv-step" data-step="<?php echo esc_attr($stepKey); ?>">
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
                                <div class="fp-resv-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['date'] ?? ''); ?></span>
                                        <input type="date" name="fp_resv_date" data-fp-resv-field="date" required>
                                    </label>
                                </div>
                                <div class="fp-resv-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['time'] ?? ''); ?></span>
                                        <input type="time" name="fp_resv_time" data-fp-resv-field="time">
                                    </label>
                                </div>
                                <?php break;
                            case 'party': ?>
                                <div class="fp-resv-field">
                                    <label>
                                        <span><?php echo esc_html($strings['fields']['party'] ?? ''); ?></span>
                                        <input
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
                                <div class="fp-resv-slots" data-fp-resv-slots>
                                    <p class="fp-resv-slots__status" data-state="loading"><?php echo esc_html($strings['messages']['slots_loading'] ?? ''); ?></p>
                                    <ul class="fp-resv-slots__list" aria-live="polite" aria-busy="false"></ul>
                                    <p class="fp-resv-slots__empty" hidden><?php echo esc_html($strings['messages']['slots_empty'] ?? ''); ?></p>
                                </div>
                                <?php break;
                            case 'details': ?>
                                <div class="fp-resv-fields fp-resv-fields--grid">
                                    <label class="fp-resv-field">
                                        <span><?php echo esc_html($strings['fields']['first_name'] ?? ''); ?></span>
                                        <input type="text" name="fp_resv_first_name" data-fp-resv-field="first_name" required>
                                    </label>
                                    <label class="fp-resv-field">
                                        <span><?php echo esc_html($strings['fields']['last_name'] ?? ''); ?></span>
                                        <input type="text" name="fp_resv_last_name" data-fp-resv-field="last_name" required>
                                    </label>
                                    <label class="fp-resv-field">
                                        <span><?php echo esc_html($strings['fields']['email'] ?? ''); ?></span>
                                        <input type="email" name="fp_resv_email" data-fp-resv-field="email" required>
                                    </label>
                                    <label class="fp-resv-field">
                                        <span><?php echo esc_html($strings['fields']['phone'] ?? ''); ?></span>
                                        <input type="tel" name="fp_resv_phone" data-fp-resv-field="phone">
                                    </label>
                                </div>
                                <label class="fp-resv-field">
                                    <span><?php echo esc_html($strings['fields']['notes'] ?? ''); ?></span>
                                    <textarea name="fp_resv_notes" data-fp-resv-field="notes" rows="3"></textarea>
                                </label>
                                <label class="fp-resv-field">
                                    <span><?php echo esc_html($strings['fields']['allergies'] ?? ''); ?></span>
                                    <textarea name="fp_resv_allergies" data-fp-resv-field="allergies" rows="3"></textarea>
                                </label>
                                <label class="fp-resv-field fp-resv-field--consent">
                                    <input type="checkbox" name="fp_resv_consent" data-fp-resv-field="consent" required>
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
                                    <label class="fp-resv-field fp-resv-field--consent">
                                        <input type="checkbox" name="fp_resv_marketing_consent" value="1" data-fp-resv-field="marketing_consent">
                                        <span><?php echo esc_html($strings['consents']['marketing'] ?? ''); ?></span>
                                    </label>
                                <?php endif; ?>
                                <?php if ($profilingEnabled) : ?>
                                    <label class="fp-resv-field fp-resv-field--consent">
                                        <input type="checkbox" name="fp_resv_profiling_consent" value="1" data-fp-resv-field="profiling_consent">
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
                    <footer class="fp-resv-step__footer">
                        <?php if ($stepKey !== 'date') : ?>
                            <button type="button" class="fp-resv-button fp-resv-button--ghost" data-fp-resv-prev>
                                <?php echo esc_html($strings['actions']['previous'] ?? ''); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($stepKey !== 'confirm') : ?>
                            <button type="button" class="fp-resv-button fp-resv-button--primary" data-fp-resv-next>
                                <?php echo esc_html($strings['actions']['next'] ?? ''); ?>
                            </button>
                        <?php else : ?>
                            <button type="submit" class="fp-resv-button fp-resv-button--submit">
                                <?php echo esc_html($strings['actions']['submit'] ?? ''); ?>
                            </button>
                        <?php endif; ?>
                    </footer>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php wp_nonce_field('fp_resv_submit', 'fp_resv_nonce'); ?>
    </form>
    <noscript class="fp-resv-widget__nojs">
        <?php echo esc_html__('Per inviare la prenotazione abilita JavaScript o contattaci direttamente.', 'fp-restaurant-reservations'); ?>
    </noscript>
</div>
