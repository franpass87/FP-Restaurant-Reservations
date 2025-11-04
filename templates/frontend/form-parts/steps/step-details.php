<?php
/**
 * Form Step: Contact Details
 * 
 * Raccoglie informazioni di contatto e richieste speciali.
 * 
 * @var array $strings Stringhe localizzate
 * @var array $hints Hint per i campi
 * @var array $config Configurazione
 * @var array $privacy Impostazioni privacy
 * @var string $formId ID del form
 * @var string $policyUrl URL privacy policy
 */

$phonePrefixes = is_array($config['phone_prefixes'] ?? null) ? $config['phone_prefixes'] : [];
$defaultPhoneCode = isset($config['defaults']['phone_country_code']) ? (string) $config['defaults']['phone_country_code'] : '39';
$phonePrefixId = $formId . '-phone-prefix';
$phoneInputId = $formId . '-phone';
$phonePrefixLabel = $strings['fields']['phone_prefix'] ?? __('Prefisso', 'fp-restaurant-reservations');
$marketingEnabled = !empty($privacy['marketing_enabled']);
$profilingEnabled = !empty($privacy['profiling_enabled']);
$requiredConsentLabel = $strings['consents_meta']['required'] ?? __('Obbligatorio', 'fp-restaurant-reservations');
$optionalConsentLabel = $strings['consents_meta']['optional'] ?? __('Opzionale', 'fp-restaurant-reservations');
?>

<!-- Nome e Cognome su 2 colonne -->
<fieldset class="fp-resv-fields fp-resv-fields--grid fp-resv-fields--2col fp-fieldset">
    <legend class="screen-reader-text"><?php echo esc_html__('Informazioni personali', 'fp-restaurant-reservations'); ?></legend>
    <label class="fp-resv-field fp-field">
        <span>
            <?php echo esc_html($strings['fields']['first_name'] ?? ''); ?>
            <abbr class="fp-required" title="<?php echo esc_attr__('Obbligatorio', 'fp-restaurant-reservations'); ?>" aria-label="<?php echo esc_attr__('Campo obbligatorio', 'fp-restaurant-reservations'); ?>">*</abbr>
        </span>
        <input 
            class="fp-input" 
            type="text" 
            name="fp_resv_first_name" 
            data-fp-resv-field="first_name" 
            required 
            autocomplete="given-name"
            aria-describedby="first-name-hint first-name-error"
            aria-invalid="false"
        >
        <small class="fp-error" id="first-name-error" data-fp-resv-error="first_name" role="alert" aria-live="polite" hidden></small>
        <small class="fp-hint" id="first-name-hint" <?php echo empty($hints['first_name'] ?? '') ? 'hidden' : ''; ?>>
            <?php echo esc_html($hints['first_name'] ?? ''); ?>
        </small>
    </label>
    <label class="fp-resv-field fp-field">
        <span>
            <?php echo esc_html($strings['fields']['last_name'] ?? ''); ?>
            <abbr class="fp-required" title="<?php echo esc_attr__('Obbligatorio', 'fp-restaurant-reservations'); ?>" aria-label="<?php echo esc_attr__('Campo obbligatorio', 'fp-restaurant-reservations'); ?>">*</abbr>
        </span>
        <input 
            class="fp-input" 
            type="text" 
            name="fp_resv_last_name" 
            data-fp-resv-field="last_name" 
            required 
            autocomplete="family-name"
            aria-describedby="last-name-hint last-name-error"
            aria-invalid="false"
        >
        <small class="fp-error" id="last-name-error" data-fp-resv-error="last_name" role="alert" aria-live="polite" hidden></small>
        <small class="fp-hint" id="last-name-hint" <?php echo empty($hints['last_name'] ?? '') ? 'hidden' : ''; ?>>
            <?php echo esc_html($hints['last_name'] ?? ''); ?>
        </small>
    </label>
</fieldset>

<!-- Email -->
<label class="fp-resv-field fp-field fp-resv-field--email">
    <span>
        <?php echo esc_html($strings['fields']['email'] ?? ''); ?>
        <abbr class="fp-required" title="<?php echo esc_attr__('Obbligatorio', 'fp-restaurant-reservations'); ?>" aria-label="<?php echo esc_attr__('Campo obbligatorio', 'fp-restaurant-reservations'); ?>">*</abbr>
    </span>
    <input 
        class="fp-input" 
        type="email" 
        name="fp_resv_email" 
        data-fp-resv-field="email" 
        required 
        autocomplete="email"
        aria-describedby="email-hint email-error"
        aria-invalid="false"
    >
    <small class="fp-error" id="email-error" data-fp-resv-error="email" role="alert" aria-live="polite" hidden></small>
    <small class="fp-hint" id="email-hint" <?php echo empty($hints['email'] ?? '') ? 'hidden' : ''; ?>>
        <?php echo esc_html($hints['email'] ?? ''); ?>
    </small>
</label>

<!-- Telefono con prefisso -->
<label class="fp-resv-field fp-field fp-resv-field--phone">
    <span>
        <?php echo esc_html($strings['fields']['phone'] ?? ''); ?>
        <abbr class="fp-required" title="<?php echo esc_attr__('Obbligatorio', 'fp-restaurant-reservations'); ?>" aria-label="<?php echo esc_attr__('Campo obbligatorio', 'fp-restaurant-reservations'); ?>">*</abbr>
    </span>
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
            aria-describedby="phone-hint phone-error"
            aria-invalid="false"
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
    <small class="fp-error" id="phone-error" data-fp-resv-error="phone" role="alert" aria-live="polite" hidden></small>
    <small class="fp-hint" id="phone-hint" <?php echo empty($hints['phone'] ?? '') ? 'hidden' : ''; ?>>
        <?php echo esc_html($hints['phone'] ?? ''); ?>
    </small>
</label>

<!-- Occasione speciale -->
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html($strings['fields']['occasion'] ?? __('Occasione speciale (opzionale)', 'fp-restaurant-reservations')); ?></span>
    <select class="fp-input" name="fp_resv_occasion" data-fp-resv-field="occasion">
        <option value=""><?php echo esc_html__('Seleziona un\'occasione', 'fp-restaurant-reservations'); ?></option>
        <option value="birthday"><?php echo esc_html__('Compleanno', 'fp-restaurant-reservations'); ?></option>
        <option value="anniversary"><?php echo esc_html__('Anniversario', 'fp-restaurant-reservations'); ?></option>
        <option value="business"><?php echo esc_html__('Cena di lavoro', 'fp-restaurant-reservations'); ?></option>
        <option value="celebration"><?php echo esc_html__('Festa/Celebrazione', 'fp-restaurant-reservations'); ?></option>
        <option value="romantic"><?php echo esc_html__('Cena romantica', 'fp-restaurant-reservations'); ?></option>
        <option value="other"><?php echo esc_html__('Altro', 'fp-restaurant-reservations'); ?></option>
    </select>
    <?php if (!empty($hints['occasion'] ?? '')) : ?>
        <small class="fp-hint"><?php echo esc_html($hints['occasion']); ?></small>
    <?php endif; ?>
</label>

<!-- Note e Allergie -->
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html($strings['fields']['notes'] ?? __('Note aggiuntive', 'fp-restaurant-reservations')); ?></span>
    <textarea class="fp-textarea" name="fp_resv_notes" data-fp-resv-field="notes" rows="3" placeholder="<?php echo esc_attr__('Es. preferenza per un tavolo particolare, orario flessibile, ecc.', 'fp-restaurant-reservations'); ?>"></textarea>
    <?php if (!empty($hints['notes'] ?? '')) : ?>
        <small class="fp-hint"><?php echo esc_html($hints['notes']); ?></small>
    <?php endif; ?>
</label>

<label class="fp-resv-field fp-field">
    <span><?php echo esc_html($strings['fields']['allergies'] ?? __('Allergie o intolleranze', 'fp-restaurant-reservations')); ?></span>
    <textarea class="fp-textarea" name="fp_resv_allergies" data-fp-resv-field="allergies" rows="3" placeholder="<?php echo esc_attr__('Indica eventuali allergie o intolleranze alimentari', 'fp-restaurant-reservations'); ?>"></textarea>
    <?php if (!empty($hints['allergies'] ?? '')) : ?>
        <small class="fp-hint"><?php echo esc_html($hints['allergies']); ?></small>
    <?php endif; ?>
</label>

<!-- Richieste aggiuntive -->
<fieldset class="fp-resv-extra fp-fieldset">
    <legend class="fp-resv-extra__title"><?php echo esc_html($strings['extras']['title'] ?? __('Richieste aggiuntive', 'fp-restaurant-reservations')); ?></legend>
    <div class="fp-resv-fields fp-resv-fields--grid fp-resv-fields--extras">
        <label class="fp-resv-field fp-field">
            <span><?php echo esc_html($strings['extras']['high_chair'] ?? __('Seggioloni necessari', 'fp-restaurant-reservations')); ?></span>
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
            <span><?php echo esc_html($strings['extras']['wheelchair_table'] ?? __('Tavolo accessibile per sedia a rotelle', 'fp-restaurant-reservations')); ?></span>
        </label>
        <label class="fp-resv-field fp-field fp-resv-field--checkbox">
            <input class="fp-checkbox" type="checkbox" name="fp_resv_pets" value="1" data-fp-resv-field="pets">
            <span><?php echo esc_html($strings['extras']['pets'] ?? __('Accompagnato da animale domestico', 'fp-restaurant-reservations')); ?></span>
        </label>
    </div>
</fieldset>

<!-- Consensi GDPR -->
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
