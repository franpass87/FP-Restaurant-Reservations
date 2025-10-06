<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\ServiceContainer;
use FP\Resv\Domain\Settings\MealPlan;
use function __;
use function add_action;
use function add_query_arg;
use function add_menu_page;
use function add_settings_error;
use function add_settings_field;
use function add_settings_section;
use function add_submenu_page;
use function array_key_first;
use function current_user_can;
use function admin_url;
use function check_admin_referer;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function esc_url_raw;
use function explode;
use function filter_input;
use function filter_var;
use function get_option;
use function implode;
use function in_array;
use function is_array;
use function is_email;
use function json_decode;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function register_setting;
use function sanitize_email;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function selected;
use function settings_errors;
use function settings_fields;
use function str_replace;
use function sprintf;
use function str_contains;
use function strpos;
use function strtolower;
use function submit_button;
use function timezone_identifiers_list;
use function trim;
use function substr;
use function wp_add_inline_style;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_kses_post;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_strip_all_tags;
use const FILTER_FLAG_ALLOW_FRACTION;
use const FILTER_SANITIZE_SPECIAL_CHARS;
use const FILTER_VALIDATE_URL;
use const INPUT_GET;

final class AdminPages
{
    private const CAPABILITY = 'manage_options';
    private const DEFAULT_PHONE_PREFIX_MAP = ['+39' => 'IT'];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $pages;
    private bool $settingsRegistered = false;

    public function __construct()
    {
        $this->pages = $this->definePages();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_fp_resv_style_reset', [$this, 'handleStyleReset']);
    }

    public function enqueueAssets(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!is_string($page) || $page === '') {
            return;
        }

        $baseHandle = 'fp-resv-admin-shell';
        $version    = Plugin::assetVersion();

        if (strpos($page, 'fp-resv-') === 0) {
            wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], $version);
        }

        if ($page === 'fp-resv-settings') {
            wp_enqueue_style('fp-resv-admin-settings', Plugin::$url . 'assets/css/admin-settings.css', [$baseHandle], $version);
            wp_enqueue_script('fp-resv-service-hours', Plugin::$url . 'assets/js/admin/service-hours.js', [], $version, true);
            wp_enqueue_script('fp-resv-meal-plan', Plugin::$url . 'assets/js/admin/meal-plan.js', [], $version, true);
        }

        if ($page !== 'fp-resv-style') {
            return;
        }

        $container = ServiceContainer::getInstance();
        $style     = $container->get(Style::class);
        if (!$style instanceof Style) {
            $options = $container->get(Options::class);
            if (!$options instanceof Options) {
                $options = new Options();
            }

            $style = new Style($options);
            $container->register(Style::class, $style);
        }

        $preview = $style->getPreviewData('fp-resv-style-preview-widget');

        $handle = 'fp-resv-style-preview';
        wp_enqueue_style($handle, Plugin::$url . 'assets/css/admin-style.css', [$baseHandle], $version);
        wp_enqueue_script($handle, Plugin::$url . 'assets/js/admin/style-preview.js', [], $version, true);
        wp_add_inline_style($handle, (string) ($preview['css'] ?? ''));

        wp_localize_script($handle, 'fpResvStylePreview', [
            'initial'  => [
                'settings' => $preview['settings'],
                'tokens'   => $preview['tokens'],
                'contrast' => $preview['contrast'],
                'cssParts' => $preview['css_parts'],
                'formId'   => 'fp-resv-style-preview-widget',
            ],
            'palettes' => $preview['palettes'],
            'defaults' => $preview['defaults'],
            'shadows'  => $preview['shadows'],
            'i18n'     => [
                'ratio'        => __('Rapporto', 'fp-restaurant-reservations'),
                'grade'        => __('Livello', 'fp-restaurant-reservations'),
                'compliant'    => __('Conforme', 'fp-restaurant-reservations'),
                'nonCompliant' => __('Da migliorare', 'fp-restaurant-reservations'),
                'primary'      => __('Bottone principale', 'fp-restaurant-reservations'),
                'surface'      => __('Testo su superficie', 'fp-restaurant-reservations'),
                'muted'        => __('Testo secondario', 'fp-restaurant-reservations'),
                'badge'        => __('Badge slot', 'fp-restaurant-reservations'),
                'resetConfirm' => __('Ripristinare i valori di default? Questa azione sovrascrive i token salvati.', 'fp-restaurant-reservations'),
            ],
        ]);
    }

    public function handleStyleReset(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_safe_redirect(admin_url());
            exit;
        }

        check_admin_referer('fp_resv_style_reset');

        $container = ServiceContainer::getInstance();
        $style     = $container->get(Style::class);
        if (!$style instanceof Style) {
            $options = $container->get(Options::class);
            if (!$options instanceof Options) {
                $options = new Options();
            }

            $style = new Style($options);
            $container->register(Style::class, $style);
        }

        $style->resetToDefaults();

        $redirect = add_query_arg(
            ['fp_resv_style_reset' => '1'],
            admin_url('admin.php?page=fp-resv-style')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function registerMenu(): void
    {
        if ($this->pages === []) {
            return;
        }

        $firstKey = array_key_first($this->pages);
        if ($firstKey === null) {
            return;
        }

        $firstPage = $this->pages[$firstKey];

        add_menu_page(
            (string) $firstPage['page_title'],
            __('FP Reservations', 'fp-restaurant-reservations'),
            self::CAPABILITY,
            $firstPage['slug'],
            function () use ($firstKey): void {
                $this->renderSettingsPage($firstKey);
            },
            'dashicons-clipboard',
            56
        );

        // Aggiunge anche il sottomenu per la pagina principale per compatibilità con alcuni setup WP
        add_submenu_page(
            $firstPage['slug'],
            (string) $firstPage['page_title'],
            (string) $firstPage['menu_title'],
            self::CAPABILITY,
            $firstPage['slug'],
            function () use ($firstKey): void {
                $this->renderSettingsPage($firstKey);
            }
        );

        foreach ($this->pages as $pageKey => $page) {
            if ($pageKey === $firstKey) {
                // già aggiunto sopra
                continue;
            }

            add_submenu_page(
                $firstPage['slug'],
                (string) $page['page_title'],
                (string) $page['menu_title'],
                self::CAPABILITY,
                $page['slug'],
                function () use ($pageKey): void {
                    $this->renderSettingsPage($pageKey);
                }
            );
        }
    }

    public function registerSettings(): void
    {
        if ($this->settingsRegistered) {
            return;
        }

        $this->settingsRegistered = true;

        foreach ($this->pages as $pageKey => $page) {
            $optionGroup = (string) $page['option_group'];
            $optionName  = (string) $page['option_name'];

            register_setting(
                $optionGroup,
                $optionName,
                [
                    'type'              => 'array',
                    'sanitize_callback' => function ($input) use ($pageKey) {
                        return $this->sanitizePageOptions($pageKey, is_array($input) ? $input : []);
                    },
                ]
            );

            foreach ($page['sections'] as $sectionKey => $section) {
                add_settings_section(
                    $sectionKey,
                    (string) $section['title'],
                    function () use ($section): void {
                        if (!empty($section['description'])) {
                            echo '<p class="description">' . esc_html((string) $section['description']) . '</p>';
                        }
                    },
                    $page['slug']
                );

                foreach ($section['fields'] as $fieldKey => $field) {
                    add_settings_field(
                        $fieldKey,
                        (string) $field['label'],
                        [$this, 'renderField'],
                        $page['slug'],
                        $sectionKey,
                        [
                            'page'       => $pageKey,
                            'field'      => $fieldKey,
                            'optionName' => $optionName,
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function renderField(array $args): void
    {
        $pageKey   = (string) $args['page'];
        $fieldKey  = (string) $args['field'];
        $optionKey = (string) $args['optionName'];
        $field     = $this->getFieldDefinition($pageKey, $fieldKey);

        if ($field === null) {
            return;
        }

        $options = get_option($optionKey, []);
        if (!is_array($options)) {
            $options = [];
        }

        if (!array_key_exists($fieldKey, $options) && isset($field['legacy_option'])) {
            $legacyOption = get_option((string) $field['legacy_option'], []);
            if (is_array($legacyOption) && array_key_exists($fieldKey, $legacyOption)) {
                $options[$fieldKey] = $legacyOption[$fieldKey];
            }
        }

        $value = $options[$fieldKey] ?? ($field['default'] ?? ($field['type'] === 'checkbox' ? '0' : ''));
        $phonePrefixPreview = null;

        $inputName   = $optionKey . '[' . $fieldKey . ']';
        $description = (string) ($field['description'] ?? '');

        switch ($field['type']) {
            case 'checkbox':
                $checked = $value === '1' || $value === 1 || $value === true;
                echo '<label><input type="checkbox" value="1" name="' . esc_attr($inputName) . '"' . ($checked ? ' checked="checked"' : '') . '> ' . esc_html((string) ($field['checkbox_label'] ?? '')) . '</label>';
                break;
            case 'phone_prefix_map':
                $map  = $this->decodePhonePrefixMapValue($value, $field);
                $rows = (int) ($field['rows'] ?? 4);
                $display = $this->formatPhonePrefixMapValue($map);
                echo '<textarea class="large-text code" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($inputName) . '">';
                echo esc_textarea($display);
                echo '</textarea>';
                $phonePrefixPreview = $map;
                break;
            case 'service_hours':
                $inputId   = $optionKey . '-' . $fieldKey . '-service-hours';
                $state     = $this->decodeServiceHoursDefinition($value);
                $stateJson = wp_json_encode($state);
                if (!is_string($stateJson)) {
                    $stateJson = '{}';
                }

                $configJson = wp_json_encode($this->getServiceHoursConfig());
                if (!is_string($configJson)) {
                    $configJson = '{}';
                }

                $rawValue = is_string($value) ? $value : $this->encodeServiceHoursDefinition($state);

                echo '<textarea'
                    . ' id="' . esc_attr($inputId) . '"'
                    . ' name="' . esc_attr($inputName) . '"'
                    . ' data-service-hours-input'
                    . ' hidden'
                    . '>' . esc_textarea($rawValue) . '</textarea>';
                echo '<div'
                    . ' class="fp-resv-service-hours"'
                    . ' data-service-hours'
                    . ' data-target="#' . esc_attr($inputId) . '"'
                    . ' data-value="' . esc_attr($stateJson) . '"'
                    . ' data-config="' . esc_attr($configJson) . '"'
                    . '></div>';
                break;
            case 'meal_plan':
                $inputId = $optionKey . '-' . $fieldKey . '-meal-plan';
                $meals   = MealPlan::parse(is_string($value) ? $value : '');
                $stateJson = wp_json_encode($meals);
                if (!is_string($stateJson)) {
                    $stateJson = '[]';
                }

                $hoursConfig = $this->getServiceHoursConfig();

                $strings = [
                    'addMeal'        => __('Aggiungi pasto', 'fp-restaurant-reservations'),
                    'defaultLabel'   => __('Predefinito', 'fp-restaurant-reservations'),
                    'keyLabel'       => __('Chiave', 'fp-restaurant-reservations'),
                    'labelLabel'     => __('Etichetta', 'fp-restaurant-reservations'),
                    'hintLabel'      => __('Hint (opzionale)', 'fp-restaurant-reservations'),
                    'noticeLabel'    => __('Messaggio (opzionale)', 'fp-restaurant-reservations'),
                    'priceLabel'     => __('Costo a persona', 'fp-restaurant-reservations'),
                    'badgeLabel'     => __('Badge (opzionale)', 'fp-restaurant-reservations'),
                    'badgeIconLabel' => __('Icona badge (opzionale)', 'fp-restaurant-reservations'),
                    'hoursLabel'     => __('Orari personalizzati', 'fp-restaurant-reservations'),
                    'hoursHint'      => __('Seleziona i giorni e configura le fasce orarie dedicate a questo pasto.', 'fp-restaurant-reservations'),
                    'slotLabel'      => __('Intervallo slot (minuti)', 'fp-restaurant-reservations'),
                    'turnLabel'      => __('Durata turno (minuti)', 'fp-restaurant-reservations'),
                    'bufferLabel'    => __('Buffer (minuti)', 'fp-restaurant-reservations'),
                    'parallelLabel'  => __('Prenotazioni parallele', 'fp-restaurant-reservations'),
                    'capacityLabel'  => __('Capacità massima', 'fp-restaurant-reservations'),
                    'removeMeal'     => __('Rimuovi pasto', 'fp-restaurant-reservations'),
                    'emptyState'     => __('Nessun pasto configurato. Aggiungine uno per iniziare.', 'fp-restaurant-reservations'),
                ];

                $stringsJson = wp_json_encode($strings);
                if (!is_string($stringsJson)) {
                    $stringsJson = '{}';
                }

                $hoursConfigJson = wp_json_encode($hoursConfig);
                if (!is_string($hoursConfigJson)) {
                    $hoursConfigJson = '{}';
                }

                echo '<textarea'
                    . ' id="' . esc_attr($inputId) . '"'
                    . ' name="' . esc_attr($inputName) . '"'
                    . ' data-meal-plan-input'
                    . ' hidden'
                    . '>' . esc_textarea($stateJson) . '</textarea>';
                echo '<div'
                    . ' class="fp-resv-meal-plan"'
                    . ' data-meal-plan'
                    . ' data-target="#' . esc_attr($inputId) . '"'
                    . ' data-value="' . esc_attr($stateJson) . '"'
                    . ' data-strings="' . esc_attr($stringsJson) . '"'
                    . ' data-hours-config="' . esc_attr($hoursConfigJson) . '"'
                    . '></div>';
                break;
            case 'textarea':
                $rows = (int) ($field['rows'] ?? 5);
                echo '<textarea class="large-text" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($inputName) . '">';
                echo esc_textarea((string) $value);
                echo '</textarea>';
                break;
            case 'textarea_html':
                $rows = (int) ($field['rows'] ?? 6);
                echo '<textarea class="large-text code" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($inputName) . '">';
                echo esc_textarea((string) $value);
                echo '</textarea>';
                break;
            case 'email_list':
                $rows = (int) ($field['rows'] ?? 3);
                $displayValue = is_array($value) ? implode("\n", $value) : (string) $value;
                echo '<textarea class="large-text" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($inputName) . '">';
                echo esc_textarea($displayValue);
                echo '</textarea>';
                break;
            case 'language_map':
                $rows = (int) ($field['rows'] ?? 3);
                $lines = [];
                if (is_array($value)) {
                    foreach ($value as $lang => $url) {
                        $lines[] = $lang . '=' . $url;
                    }
                }
                $displayValue = implode("\n", $lines);
                echo '<textarea class="large-text code" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($inputName) . '">';
                echo esc_textarea($displayValue);
                echo '</textarea>';
                break;
            case 'select':
                echo '<select name="' . esc_attr($inputName) . '">';
                foreach ($field['options'] as $optionValue => $optionLabel) {
                    $selectedAttr = selected((string) $value, (string) $optionValue, false);
                    echo '<option value="' . esc_attr((string) $optionValue) . '" ' . $selectedAttr . '>' . esc_html((string) $optionLabel) . '</option>';
                }
                echo '</select>';
                break;
            case 'color':
                echo '<input type="text" class="regular-text" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $value) . '" placeholder="#bb2649" pattern="#?[0-9a-fA-F]{3,6}">';
                break;
            case 'number':
                $min = isset($field['min']) ? ' min="' . esc_attr((string) $field['min']) . '"' : '';
                $max = isset($field['max']) ? ' max="' . esc_attr((string) $field['max']) . '"' : '';
                $step = isset($field['step']) ? ' step="' . esc_attr((string) $field['step']) . '"' : '';
                echo '<input type="number" class="small-text" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $value) . '"' . $min . $max . $step . '>';
                break;
            case 'integer':
                $min = isset($field['min']) ? ' min="' . esc_attr((string) $field['min']) . '"' : '';
                $max = isset($field['max']) ? ' max="' . esc_attr((string) $field['max']) . '"' : '';
                echo '<input type="number" class="small-text" step="1" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $value) . '"' . $min . $max . '>';
                break;
            case 'url':
                echo '<input type="url" class="regular-text" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $value) . '" placeholder="https://">';
                break;
            case 'password':
                echo '<input type="password" class="regular-text" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $value) . '">';
                break;
            default:
                echo '<input type="text" class="regular-text" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $value) . '">';
                break;
        }

        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }

        if ($phonePrefixPreview !== null) {
            echo $this->renderPhonePrefixPreview($phonePrefixPreview);
        }
    }

    private function renderSettingsPage(string $pageKey): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return;
        }

        $optionGroup = (string) $page['option_group'];

        if ($pageKey === 'style') {
            $this->renderStylePage($page);

            return;
        }

        $menuTitle = (string) ($page['menu_title'] ?? $page['page_title']);
        $breadcrumbLabel = (string) ($page['breadcrumb'] ?? $menuTitle);
        $subtitle  = $this->describePage($pageKey);
        $headingId = $page['slug'] . '-title';
        $formId    = 'fp-resv-settings-form-' . $pageKey;

        $reportsUrl = admin_url('admin.php?page=fp-resv-reports');

        ob_start();
        settings_errors($optionGroup);
        $notices = trim((string) ob_get_clean());

        $resetFlag = filter_input(INPUT_GET, 'fp_resv_style_reset', FILTER_SANITIZE_SPECIAL_CHARS);
        if (is_string($resetFlag) && $resetFlag !== '') {
            $resetNotice = '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Stile ripristinato ai valori di default.', 'fp-restaurant-reservations')
                . '</p></div>';
            $notices = $resetNotice . $notices;
        }

        echo '<div class="fp-resv-admin fp-resv-admin--settings" role="region" aria-labelledby="' . esc_attr($headingId) . '">';
        echo '<header class="fp-resv-admin__topbar">';
        echo '<div class="fp-resv-admin__identity">';
        echo '<nav class="fp-resv-admin__breadcrumbs" aria-label="' . esc_attr__('Percorso', 'fp-restaurant-reservations') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp-resv-settings')) . '">' . esc_html__('FP Reservations', 'fp-restaurant-reservations') . '</a>';
        echo '<span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>';
        echo '<span class="fp-resv-admin__breadcrumb-current">' . esc_html($breadcrumbLabel) . '</span>';
        echo '</nav>';
        echo '<div>';
        echo '<h1 class="fp-resv-admin__title" id="' . esc_attr($headingId) . '">' . esc_html((string) $page['page_title']) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="fp-resv-admin__subtitle">' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="fp-resv-admin__actions">';
        echo '<a class="button" href="' . esc_url($reportsUrl) . '">' . esc_html__('Vai ai report', 'fp-restaurant-reservations') . '</a>';
        echo '<button type="submit" class="button button-primary" form="' . esc_attr($formId) . '">';
        esc_html_e('Salva impostazioni', 'fp-restaurant-reservations');
        echo '</button>';
        echo '</div>';
        echo '</header>';

        echo '<main class="fp-resv-admin__main">';
        echo '<section class="fp-resv-surface">';
        if ($notices !== '') {
            echo '<div class="fp-resv-settings__notices">' . $notices . '</div>';
        }
        echo '<form method="post" action="options.php" id="' . esc_attr($formId) . '" class="fp-resv-settings__form">';
        settings_fields($optionGroup);
        echo '<div class="fp-resv-settings__sections">';
        do_settings_sections($page['slug']);
        echo '</div>';
        echo '<div class="fp-resv-settings__actions">';
        submit_button(__('Salva modifiche', 'fp-restaurant-reservations'));
        echo '</div>';
        echo '</form>';
        echo '</section>';
        echo '</main>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $page
     */
    private function renderStylePage(array $page): void
    {
        $container = ServiceContainer::getInstance();
        $style     = $container->get(Style::class);
        if (!$style instanceof Style) {
            $options = $container->get(Options::class);
            if (!$options instanceof Options) {
                $options = new Options();
            }

            $style = new Style($options);
            $container->register(Style::class, $style);
        }

        $preview     = $style->getPreviewData('fp-resv-style-preview-widget');
        $optionGroup = (string) $page['option_group'];

        $headingId = 'fp-resv-style-title';

        ob_start();
        settings_errors($optionGroup);
        $notices = trim((string) ob_get_clean());

        echo '<div class="fp-resv-admin fp-resv-admin--style" role="region" aria-labelledby="' . esc_attr($headingId) . '">';
        echo '<header class="fp-resv-admin__topbar">';
        echo '<div class="fp-resv-admin__identity">';
        echo '<nav class="fp-resv-admin__breadcrumbs" aria-label="' . esc_attr__('Percorso', 'fp-restaurant-reservations') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp-resv-settings')) . '">' . esc_html__('FP Reservations', 'fp-restaurant-reservations') . '</a>';
        echo '<span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>';
        echo '<span class="fp-resv-admin__breadcrumb-current">' . esc_html($page['menu_title'] ?? $page['page_title']) . '</span>';
        echo '</nav>';
        echo '<div>';
        echo '<h1 class="fp-resv-admin__title" id="' . esc_attr($headingId) . '">' . esc_html((string) $page['page_title']) . '</h1>';
        echo '<p class="fp-resv-admin__subtitle">' . esc_html__('Personalizza palette, tipografia e focus ring con anteprima live del form.', 'fp-restaurant-reservations') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="fp-resv-admin__actions">';
        echo '<button type="submit" class="button button-primary" form="fp-resv-style-form">';
        esc_html_e('Salva stile', 'fp-restaurant-reservations');
        echo '</button>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="fp-resv-admin__reset-form" data-style-reset-form>';
        wp_nonce_field('fp_resv_style_reset');
        echo '<input type="hidden" name="action" value="fp_resv_style_reset">';
        echo '<button type="submit" class="button-link fp-resv-admin__reset" data-style-reset>';
        esc_html_e('Ripristina default', 'fp-restaurant-reservations');
        echo '</button>';
        echo '</form>';
        echo '</div>';
        echo '</header>';

        echo '<main class="fp-resv-admin__main">';
        if ($notices !== '') {
            echo '<div class="fp-resv-settings__notices">' . $notices . '</div>';
        }
        echo '<div class="fp-resv-style-admin__layout">';
        echo '<section class="fp-resv-surface fp-resv-style-admin__form">';
        echo '<form method="post" action="options.php" id="fp-resv-style-form" class="fp-resv-settings__form">';
        settings_fields($optionGroup);
        echo '<div class="fp-resv-settings__sections">';
        do_settings_sections($page['slug']);
        echo '</div>';
        echo '<div class="fp-resv-settings__actions">';
        submit_button(__('Aggiorna stile', 'fp-restaurant-reservations'));
        echo '</div>';
        echo '</form>';
        echo '</section>';
        echo '<section class="fp-resv-surface fp-resv-style-admin__preview">';
        $previewData = $preview;
        include Plugin::$dir . 'src/Admin/Views/style.php';
        echo '</section>';
        echo '</div>';
        echo '</main>';
        echo '</div>';
    }

    private function describePage(string $pageKey): string
    {
        switch ($pageKey) {
            case 'general':
                return __('Preferenze globali e dati anagrafici del ristorante.', 'fp-restaurant-reservations');
            case 'notifications':
                return __('Configura mittenti, destinatari e frequenza delle notifiche.', 'fp-restaurant-reservations');
            case 'payments':
                return __('Imposta Stripe e le politiche di incasso per il form.', 'fp-restaurant-reservations');
            case 'brevo':
                return __('Collega automazioni Brevo per email e recensioni post visita.', 'fp-restaurant-reservations');
            case 'google-calendar':
                return __('Sincronizza turni e prenotazioni con Google Calendar in modo sicuro.', 'fp-restaurant-reservations');
            case 'language':
                return __('Personalizza testi, PDF multilingua e copy del widget.', 'fp-restaurant-reservations');
            case 'tracking':
                return __('Definisci eventi e integrazioni analytics per marketing e attribuzione.', 'fp-restaurant-reservations');
            default:
                return __('Aggiorna le impostazioni avanzate per questa sezione.', 'fp-restaurant-reservations');
        }
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function sanitizePageOptions(string $pageKey, array $input): array
    {
        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return $input;
        }

        $sanitized = [];
        foreach ($page['sections'] as $section) {
            foreach ($section['fields'] as $fieldKey => $field) {
                $value = $input[$fieldKey] ?? null;
                $sanitized[$fieldKey] = $this->sanitizeField($pageKey, $fieldKey, $field, $value);
            }
        }

        if ($pageKey === 'general') {
            if (isset($sanitized['default_currency'])) {
                $sanitized['default_currency'] = strtoupper(substr((string) $sanitized['default_currency'], 0, 3));
            }
            if (!empty($sanitized['restaurant_timezone']) && !in_array($sanitized['restaurant_timezone'], timezone_identifiers_list(), true)) {
                $sanitized['restaurant_timezone'] = 'Europe/Rome';
            }
        }

        if ($pageKey === 'payments' && isset($sanitized['stripe_currency'])) {
            $sanitized['stripe_currency'] = strtoupper(substr((string) $sanitized['stripe_currency'], 0, 3));
        }


        $this->validatePage($pageKey, $sanitized);

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function sanitizeField(string $pageKey, string $fieldKey, array $field, mixed $value): mixed
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return (!empty($value) && $value !== '0') ? '1' : '0';
            case 'integer':
                $int = (int) ($value ?? 0);
                if (isset($field['min'])) {
                    $int = max((int) $field['min'], $int);
                }
                if (isset($field['max'])) {
                    $int = min((int) $field['max'], $int);
                }

                return (string) $int;
            case 'number':
                $raw = is_scalar($value) ? (string) $value : '';
                $normalized = filter_var($raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($normalized === false || $normalized === null || $normalized === '') {
                    return '';
                }

                return $normalized;
            case 'url':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }

                $sanitized = esc_url_raw($raw);
                if ($sanitized === '' || filter_var($sanitized, FILTER_VALIDATE_URL) === false) {
                    $this->addError($pageKey, $fieldKey . '_invalid_url', sprintf(
                        __('L\'URL fornito per %s non è valido.', 'fp-restaurant-reservations'),
                        $field['label']
                    ));

                    return '';
                }

                return $sanitized;
            case 'email':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }

                $sanitized = sanitize_email($raw);
                if ($sanitized === '' || !is_email($sanitized)) {
                    $this->addError($pageKey, $fieldKey . '_invalid_email', sprintf(
                        __('L\'indirizzo email per %s non è valido.', 'fp-restaurant-reservations'),
                        $field['label']
                    ));

                    return '';
                }

                return $sanitized;
            case 'email_list':
                return $this->sanitizeEmailList($pageKey, $fieldKey, $value, $field);
            case 'language_map':
                return $this->sanitizeLanguageMap($pageKey, $fieldKey, $value);
            case 'phone_prefix_map':
                return $this->sanitizePhonePrefixMap($pageKey, $fieldKey, $value);
            case 'select':
                $allowed = $field['options'] ?? [];
                $raw     = is_scalar($value) ? (string) $value : '';
                if (array_key_exists($raw, $allowed)) {
                    return $raw;
                }

                return (string) ($field['default'] ?? (array_key_first($allowed) ?? ''));
            case 'color':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }

                $sanitized = sanitize_hex_color($raw);
                if ($sanitized === null) {
                    $this->addError($pageKey, $fieldKey . '_invalid_color', sprintf(
                        __('Il colore specificato per %s non è valido.', 'fp-restaurant-reservations'),
                        $field['label']
                    ));

                    return '';
                }

                return $sanitized;
            case 'textarea':
                $raw = is_scalar($value) ? (string) $value : '';

                return sanitize_textarea_field($raw);
            case 'textarea_html':
                $raw = is_scalar($value) ? (string) $value : '';

                return wp_kses_post($raw);
            case 'password':
                $raw = is_scalar($value) ? (string) $value : '';

                return trim($raw);
            default:
                $raw = is_scalar($value) ? (string) $value : '';

                return sanitize_text_field($raw);
        }
    }

    private function validatePage(string $pageKey, array $options): void
    {
        switch ($pageKey) {
            case 'general':
                if (!empty($options['restaurant_timezone']) && !in_array($options['restaurant_timezone'], timezone_identifiers_list(), true)) {
                    $this->addError(
                        $pageKey,
                        'invalid_timezone',
                        __('La timezone indicata non è riconosciuta. Usa un identificativo come Europe/Rome.', 'fp-restaurant-reservations')
                    );
                }
                if (!empty($options['default_currency']) && !preg_match('/^[A-Z]{3}$/', (string) $options['default_currency'])) {
                    $this->addError(
                        $pageKey,
                        'invalid_currency',
                        __('Il codice valuta deve contenere 3 lettere maiuscole.', 'fp-restaurant-reservations')
                    );
                }
                if (!empty($options['frontend_meals'])) {
                    $this->validateMealPlanDefinition((string) $options['frontend_meals']);
                }
                break;
            case 'notifications':
                if (empty($options['restaurant_emails'])) {
                    $this->addError(
                        $pageKey,
                        'missing_restaurant_emails',
                        __('Specifica almeno un indirizzo email per le notifiche al ristorante.', 'fp-restaurant-reservations')
                    );
                }
                if (empty($options['webmaster_emails'])) {
                    $this->addError(
                        $pageKey,
                        'missing_webmaster_emails',
                        __('Specifica almeno un indirizzo email per le notifiche al webmaster.', 'fp-restaurant-reservations')
                    );
                }
                break;
            case 'payments':
                if (($options['stripe_enabled'] ?? '0') === '1') {
                    if (empty($options['stripe_publishable_key']) || empty($options['stripe_secret_key'])) {
                        $this->addError(
                            $pageKey,
                            'missing_stripe_keys',
                            __('Chiavi Stripe obbligatorie quando i pagamenti sono abilitati.', 'fp-restaurant-reservations')
                        );
                    }
                    if (($options['stripe_capture_type'] ?? '') === 'deposit' && empty($options['stripe_deposit_amount'])) {
                        $this->addError(
                            $pageKey,
                            'missing_deposit_amount',
                            __('Specifica l\'importo della caparra quando la modalità caparra è attiva.', 'fp-restaurant-reservations')
                        );
                    }
                }
                break;
            case 'brevo':
                if (($options['brevo_enabled'] ?? '0') === '1') {
                    if (empty($options['brevo_api_key'])) {
                        $this->addError(
                            $pageKey,
                            'missing_brevo_key',
                            __('Specifica la chiave API Brevo per abilitare l\'integrazione.', 'fp-restaurant-reservations')
                        );
                    }
                    if (!empty($options['brevo_review_place_id']) && !$this->isValidPlaceId((string) $options['brevo_review_place_id'])) {
                        $this->addError(
                            $pageKey,
                            'invalid_place_id',
                            __('Il Place ID Google specificato non sembra valido.', 'fp-restaurant-reservations')
                        );
                    }
                }
                break;
            case 'google-calendar':
                if (($options['google_calendar_enabled'] ?? '0') === '1') {
                    if (empty($options['google_calendar_client_id']) || empty($options['google_calendar_client_secret'])) {
                        $this->addError(
                            $pageKey,
                            'missing_google_credentials',
                            __('Client ID e Client Secret sono obbligatori per Google Calendar.', 'fp-restaurant-reservations')
                        );
                    }
                    if (!empty($options['google_calendar_redirect_uri']) && filter_var((string) $options['google_calendar_redirect_uri'], FILTER_VALIDATE_URL) === false) {
                        $this->addError(
                            $pageKey,
                            'invalid_redirect_uri',
                            __('L\'URL di redirect OAuth non è valido.', 'fp-restaurant-reservations')
                        );
                    }
                }
                break;
            case 'language':
                if (!empty($options['pdf_urls']) && !is_array($options['pdf_urls'])) {
                    $this->addError(
                        $pageKey,
                        'invalid_pdf_map',
                        __('Il formato degli URL PDF per lingua non è valido.', 'fp-restaurant-reservations')
                    );
                }
                break;
            case 'tracking':
                if (!empty($options['ga4_measurement_id']) && !preg_match('/^G-[A-Z0-9]{4,}$/', (string) $options['ga4_measurement_id'])) {
                    $this->addError(
                        $pageKey,
                        'invalid_ga4_id',
                        __('Il Measurement ID GA4 deve iniziare con G- seguito da lettere maiuscole e numeri.', 'fp-restaurant-reservations')
                    );
                }
                break;
        }
    }
    private function sanitizeEmailList(string $pageKey, string $fieldKey, mixed $value, array $field): array
    {
        $rawList = [];
        if (is_string($value)) {
            $rawList = preg_split('/[\n,;]/', $value) ?: [];
        } elseif (is_array($value)) {
            $rawList = $value;
        }

        $valid   = [];
        $invalid = [];

        foreach ($rawList as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }

            $sanitized = sanitize_email($email);
            if ($sanitized === '' || !is_email($sanitized)) {
                $invalid[] = $email;
                continue;
            }

            $valid[] = $sanitized;
        }

        $valid = array_values(array_unique($valid));

        if ($invalid !== []) {
            $this->addError(
                $pageKey,
                $fieldKey . '_invalid_emails',
                sprintf(
                    __('Alcune email sono state ignorate perché non valide: %s', 'fp-restaurant-reservations'),
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }

        if (!empty($field['required']) && $valid === []) {
            $this->addError(
                $pageKey,
                $fieldKey . '_required',
                sprintf(
                    __('Il campo %s richiede almeno un indirizzo email valido.', 'fp-restaurant-reservations'),
                    $field['label']
                )
            );
        }

        return $valid;
    }

    private function sanitizeLanguageMap(string $pageKey, string $fieldKey, mixed $value): array
    {
        $lines = [];
        if (is_string($value)) {
            $lines = preg_split('/\n/', $value) ?: [];
        } elseif (is_array($value)) {
            foreach ($value as $lang => $url) {
                $lines[] = $lang . '=' . $url;
            }
        }

        $map     = [];
        $invalid = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                $invalid[] = $line;
                continue;
            }

            [$lang, $url] = array_map('trim', explode('=', $line, 2));
            $lang = strtolower($lang);
            $lang = sanitize_key($lang);
            if ($lang === '') {
                $invalid[] = $line;
                continue;
            }

            $sanitizedUrl = esc_url_raw($url);
            if ($sanitizedUrl === '' || filter_var($sanitizedUrl, FILTER_VALIDATE_URL) === false) {
                $invalid[] = $line;
                continue;
            }

            $map[$lang] = $sanitizedUrl;
        }

        if ($invalid !== []) {
            $this->addError(
                $pageKey,
                $fieldKey . '_invalid_map',
                sprintf(
                    __('Alcune righe non sono state accettate per il campo %s: %s', 'fp-restaurant-reservations'),
                    $field['label'] ?? $fieldKey,
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }

        return $map;
    }

    private function sanitizePhonePrefixMap(string $pageKey, string $fieldKey, mixed $value): string
    {
        $raw      = is_scalar($value) ? (string) $value : '';
        $segments = preg_split('/[\r\n,]+/', $raw) ?: [];
        $map      = [];
        $invalid  = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            if (!str_contains($segment, '=')) {
                $invalid[] = $segment;
                continue;
            }

            [$prefixRaw, $langRaw] = array_map('trim', explode('=', $segment, 2));
            if ($prefixRaw === '' || $langRaw === '') {
                $invalid[] = $segment;
                continue;
            }

            $normalizedPrefix = str_replace(' ', '', $prefixRaw);
            if (strpos($normalizedPrefix, '00') === 0) {
                $normalizedPrefix = '+' . substr($normalizedPrefix, 2);
            }
            if (strpos($normalizedPrefix, '+') !== 0) {
                $normalizedPrefix = '+' . ltrim($normalizedPrefix, '+');
            }
            if ($normalizedPrefix === '+') {
                $invalid[] = $segment;
                continue;
            }

            $languageCode = $this->normalizeLanguageCode($langRaw, true);
            if ($languageCode === '') {
                $languageCode = 'INT';
            }

            $map[$normalizedPrefix] = $languageCode;
        }

        if ($invalid !== []) {
            $definition = $this->getFieldDefinition($pageKey, $fieldKey);
            $fieldLabel = is_array($definition) && isset($definition['label']) ? (string) $definition['label'] : $fieldKey;

            $this->addError(
                $pageKey,
                $fieldKey . '_invalid_prefix_map',
                sprintf(
                    __('Alcune righe non sono state accettate per il campo %s: %s', 'fp-restaurant-reservations'),
                    $fieldLabel,
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }

        if ($map === []) {
            $map = self::DEFAULT_PHONE_PREFIX_MAP;
        }

        $encoded = wp_json_encode($map);
        if (!is_string($encoded)) {
            $encoded = wp_json_encode(self::DEFAULT_PHONE_PREFIX_MAP);
        }

        return (string) $encoded;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, string>
     */
    private function decodePhonePrefixMapValue(mixed $value, array $field): array
    {
        $raw = '';
        if (is_string($value) && $value !== '') {
            $raw = $value;
        } elseif (is_string($field['default'] ?? null) && $field['default'] !== '') {
            $raw = (string) $field['default'];
        } else {
            $raw = (string) wp_json_encode(self::DEFAULT_PHONE_PREFIX_MAP);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::DEFAULT_PHONE_PREFIX_MAP;
        }

        $map = [];
        foreach ($decoded as $prefix => $language) {
            if (!is_string($prefix) || !is_string($language)) {
                continue;
            }

            $prefix = trim($prefix);
            if ($prefix === '') {
                continue;
            }

            if (strpos($prefix, '+') !== 0) {
                $prefix = '+' . ltrim($prefix, '+');
            }

            if ($prefix === '+') {
                continue;
            }

            $langCode = $this->normalizeLanguageCode($language, true);
            if ($langCode === '') {
                $langCode = 'INT';
            }

            $map[$prefix] = $langCode;
        }

        if ($map === []) {
            $map = self::DEFAULT_PHONE_PREFIX_MAP;
        }

        return $map;
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function decodeServiceHoursDefinition(mixed $value): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $result = array_fill_keys($days, []);

        if (is_array($value)) {
            foreach ($value as $day => $ranges) {
                $day = strtolower((string) $day);
                if (!isset($result[$day]) || !is_array($ranges)) {
                    continue;
                }

                foreach ($ranges as $range) {
                    if (!is_array($range)) {
                        continue;
                    }

                    $start = isset($range['start']) ? (string) $range['start'] : '';
                    $end   = isset($range['end']) ? (string) $range['end'] : '';
                    if ($start === '' || $end === '' || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                        continue;
                    }

                    $result[$day][] = ['start' => $start, 'end' => $end];
                }
            }
        }

        $source = '';
        if (is_string($value)) {
            $source = $value;
        }

        if ($source !== '') {
            $lines = preg_split('/\n/', $source) ?: [];
            foreach ($lines as $line) {
                $entries = $this->splitServiceHoursEntries((string) $line);
                foreach ($entries as $entry) {
                    if ($entry === '' || !str_contains($entry, '=')) {
                        continue;
                    }

                    [$day, $ranges] = array_map('trim', explode('=', $entry, 2));
                    $day            = strtolower($day);

                    if (!isset($result[$day])) {
                        continue;
                    }

                    $segments = preg_split('/[|,]/', $ranges) ?: [];
                    foreach ($segments as $segment) {
                        $segment = trim((string) $segment);
                        if ($segment === '') {
                            continue;
                        }

                        if (preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $segment, $matches) !== 1) {
                            continue;
                        }

                        $result[$day][] = ['start' => $matches[1], 'end' => $matches[2]];
                    }
                }
            }
        }

        foreach ($result as &$ranges) {
            $unique = [];
            foreach ($ranges as $range) {
                $key = $range['start'] . '-' . $range['end'];
                $unique[$key] = $range;
            }
            $ranges = array_values($unique);
        }
        unset($ranges);

        return $result;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $map
     */
    private function encodeServiceHoursDefinition(array $map): string
    {
        $order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $lines = [];

        foreach ($order as $day) {
            if (!isset($map[$day]) || !is_array($map[$day]) || $map[$day] === []) {
                continue;
            }

            $ranges = [];
            foreach ($map[$day] as $range) {
                if (!is_array($range)) {
                    continue;
                }

                $start = isset($range['start']) ? (string) $range['start'] : '';
                $end   = isset($range['end']) ? (string) $range['end'] : '';
                if ($start === '' || $end === '' || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                    continue;
                }

                $ranges[] = $start . '-' . $end;
            }

            if ($ranges !== []) {
                $lines[] = $day . '=' . implode('|', $ranges);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function getServiceHoursConfig(): array
    {
        return [
            'days'    => [
                ['key' => 'mon', 'label' => __('Lunedì', 'fp-restaurant-reservations'), 'short' => __('Lun', 'fp-restaurant-reservations')],
                ['key' => 'tue', 'label' => __('Martedì', 'fp-restaurant-reservations'), 'short' => __('Mar', 'fp-restaurant-reservations')],
                ['key' => 'wed', 'label' => __('Mercoledì', 'fp-restaurant-reservations'), 'short' => __('Mer', 'fp-restaurant-reservations')],
                ['key' => 'thu', 'label' => __('Giovedì', 'fp-restaurant-reservations'), 'short' => __('Gio', 'fp-restaurant-reservations')],
                ['key' => 'fri', 'label' => __('Venerdì', 'fp-restaurant-reservations'), 'short' => __('Ven', 'fp-restaurant-reservations')],
                ['key' => 'sat', 'label' => __('Sabato', 'fp-restaurant-reservations'), 'short' => __('Sab', 'fp-restaurant-reservations')],
                ['key' => 'sun', 'label' => __('Domenica', 'fp-restaurant-reservations'), 'short' => __('Dom', 'fp-restaurant-reservations')],
            ],
            'strings' => [
                'addRange'    => __('Aggiungi fascia', 'fp-restaurant-reservations'),
                'removeRange' => __('Rimuovi', 'fp-restaurant-reservations'),
                'from'        => __('Dalle', 'fp-restaurant-reservations'),
                'to'          => __('Alle', 'fp-restaurant-reservations'),
                'closed'      => __('Chiuso', 'fp-restaurant-reservations'),
            ],
        ];
    }

    /**
     * @param array<string, string> $map
     */
    private function renderPhonePrefixPreview(array $map): string
    {
        $title = '<strong>' . esc_html(__('Anteprima mapping attivo:', 'fp-restaurant-reservations')) . '</strong> ';
        if ($map === []) {
            return '<p class="description">' . $title . esc_html(__('Nessun mapping disponibile.', 'fp-restaurant-reservations')) . '</p>';
        }

        $items = [];
        foreach ($map as $prefix => $language) {
            $items[] = '<code>' . esc_html($prefix . ' → ' . $language) . '</code>';
        }

        return '<p class="description">' . $title . implode(', ', $items) . '</p>';
    }

    /**
     * @param array<string, string> $map
     */
    private function formatPhonePrefixMapValue(array $map): string
    {
        if ($map === []) {
            return '';
        }

        $pairs = [];
        foreach ($map as $prefix => $language) {
            $pairs[] = $prefix . '=' . $language;
        }

        return implode(', ', $pairs);
    }

    private function normalizeLanguageCode(string $value, bool $allowInternational = false): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return '';
        }

        if (strpos($upper, 'IT') === 0) {
            return 'IT';
        }

        if (strpos($upper, 'EN') === 0) {
            return 'EN';
        }

        if ($allowInternational && strpos($upper, 'INT') === 0) {
            return 'INT';
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function collectInvalidServiceHoursEntries(string $definition): array
    {
        $lines       = preg_split('/\n/', $definition) ?: [];
        $allowedDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $invalid     = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $entries = $this->splitServiceHoursEntries($line);

            foreach ($entries as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }

                if (!str_contains($entry, '=')) {
                    $invalid[] = $entry;
                    continue;
                }

                [$day, $ranges] = array_map('trim', explode('=', $entry, 2));
                $day            = strtolower($day);

                if (!in_array($day, $allowedDays, true)) {
                    $invalid[] = $entry;
                    continue;
                }

                $segments = preg_split('/[|,]/', $ranges) ?: [];
                if ($segments === []) {
                    $invalid[] = $entry;
                    continue;
                }

                $invalidEntry = false;
                foreach ($segments as $segment) {
                    $segment = trim($segment);
                    if ($segment === '') {
                        $invalidEntry = true;
                        break;
                    }

                    if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                        $invalidEntry = true;
                        break;
                    }

                    $startMinutes = ((int) $matches[1] * 60) + (int) $matches[2];
                    $endMinutes   = ((int) $matches[3] * 60) + (int) $matches[4];

                    if ($endMinutes <= $startMinutes) {
                        $invalidEntry = true;
                        break;
                    }
                }

                if ($invalidEntry) {
                    $invalid[] = $entry;
                }
            }
        }

        return $invalid;
    }

    private function validateMealPlanDefinition(string $definition): void
    {
        if (trim($definition) === '') {
            return;
        }

        $meals = MealPlan::parse($definition);
        if ($meals === []) {
            return;
        }

        foreach ($meals as $meal) {
            if (!is_array($meal)) {
                continue;
            }

            $hoursDefinition = isset($meal['hours_definition']) ? (string) $meal['hours_definition'] : '';
            if ($hoursDefinition === '') {
                continue;
            }

            $invalid = $this->collectInvalidServiceHoursEntries($hoursDefinition);
            if ($invalid === []) {
                continue;
            }

            $label = isset($meal['label']) ? (string) $meal['label'] : '';
            $key   = isset($meal['key']) ? sanitize_key((string) $meal['key']) : '';

            $this->addError(
                'general',
                'invalid_service_hours_meal_' . ($key !== '' ? $key : md5($hoursDefinition)),
                sprintf(
                    __('Gli orari di servizio configurati per %1$s non sono validi: %2$s', 'fp-restaurant-reservations'),
                    $label !== '' ? $label : ($key !== '' ? strtoupper($key) : __('Servizio', 'fp-restaurant-reservations')),
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }
    }

    /**
     * @return list<string>
     */
    private function splitServiceHoursEntries(string $line): array
    {
        $normalized = trim(str_replace("\xc2\xa0", ' ', $line));
        if ($normalized === '') {
            return [];
        }

        $matches = [];
        if (preg_match_all('/(?:^|\s+)([A-Za-z]{3})\s*=\s*(.+?)(?=\s*$|\s+[A-Za-z]{3}\s*=)/u', $normalized, $matches, PREG_SET_ORDER) > 0) {
            $entries = [];
            foreach ($matches as $match) {
                if (!is_array($match) || !isset($match[1], $match[2])) {
                    continue;
                }

                $entries[] = $match[1] . '=' . trim($match[2]);
            }

            if ($entries !== []) {
                return $entries;
            }
        }

        return [$normalized];
    }

    private function isValidPlaceId(string $placeId): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{10,}$/', $placeId);
    }

    private function addError(string $pageKey, string $code, string $message): void
    {
        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return;
        }

        add_settings_error((string) $page['option_name'], $code, $message, 'error');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFieldDefinition(string $pageKey, string $fieldKey): ?array
    {
        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return null;
        }

        foreach ($page['sections'] as $section) {
            if (isset($section['fields'][$fieldKey])) {
                return $section['fields'][$fieldKey];
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definePages(): array
    {
        return [
            'general' => [
                'page_title'   => __('Impostazioni generali', 'fp-restaurant-reservations'),
                'menu_title'   => __('Generali', 'fp-restaurant-reservations'),
                'breadcrumb'   => __('Generali', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-settings',
                'option_group' => 'fp_resv_general',
                'option_name'  => 'fp_resv_general',
                'sections'     => [
                    'general-defaults' => [
                        'title'       => __('Preferenze di base', 'fp-restaurant-reservations'),
                        'description' => __('Configura i dati principali del ristorante e le preferenze predefinite per le prenotazioni.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'restaurant_name' => [
                                'label'       => __('Nome del ristorante', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                                'description' => __('Comparirà nelle email e nel form di prenotazione.', 'fp-restaurant-reservations'),
                            ],
                            'enable_manage_page' => [
                                'label'          => __('Pagina gestione prenotazione', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Abilita la pagina self-service (link dalle email)', 'fp-restaurant-reservations'),
                                'default'        => '1',
                                'description'    => __('Se disabilitata, i link “Gestisci prenotazione” non mostreranno la pagina.', 'fp-restaurant-reservations'),
                            ],
                            'enable_manage_requests' => [
                                'label'          => __('Richieste dal cliente (annullo/modifica)', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Permetti invio richieste allo staff dalla pagina gestione', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'manage_requests_notice' => [
                                'label'       => __('Testo informativo pagina gestione', 'fp-restaurant-reservations'),
                                'type'        => 'textarea',
                                'rows'        => 3,
                                'default'     => '',
                                'description' => __('Mostrato sotto il form (es. privacy, tempi di risposta).', 'fp-restaurant-reservations'),
                            ],
                            'restaurant_timezone' => [
                                'label'       => __('Timezone predefinita', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => 'Europe/Rome',
                                'description' => __('Inserisci un identificativo valido (es. Europe/Rome).', 'fp-restaurant-reservations'),
                            ],
                            'default_party_size' => [
                                'label'       => __('Coperti predefiniti', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '2',
                                'min'         => 1,
                                'max'         => 20,
                                'description' => __('Numero di persone proposto nel form.', 'fp-restaurant-reservations'),
                            ],
                            'default_reservation_status' => [
                                'label'       => __('Stato prenotazione di default', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'pending'   => __('In attesa', 'fp-restaurant-reservations'),
                                    'confirmed' => __('Confermata', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'pending',
                                'description' => __('Stato assegnato automaticamente alle nuove richieste manuali.', 'fp-restaurant-reservations'),
                            ],
                            'default_currency' => [
                                'label'       => __('Valuta principale', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => 'EUR',
                                'description' => __('Codice ISO a 3 lettere (es. EUR).', 'fp-restaurant-reservations'),
                            ],
                            'enable_waitlist' => [
                                'label'          => __('Lista d\'attesa', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Abilita la gestione delle richieste in lista d\'attesa', 'fp-restaurant-reservations'),
                                'default'        => '0',
                            ],
                            'data_retention_months' => [
                                'label'       => __('Conservazione dati (mesi)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '24',
                                'min'         => 1,
                                'max'         => 120,
                                'description' => __('I dati delle prenotazioni verranno anonimizzati dopo il periodo indicato.', 'fp-restaurant-reservations'),
                            ],
                        ],
                    ],
                    'general-service-hours' => [
                        'title'       => __('Turni & disponibilità', 'fp-restaurant-reservations'),
                        'description' => __('Definisci i pulsanti di selezione pasto mostrati nel primo step del form e la relativa disponibilità.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'frontend_meals' => [
                                'label'       => __('Pasti disponibili', 'fp-restaurant-reservations'),
                                'type'        => 'meal_plan',
                                'default'     => '',
                                'description' => __('Configura i pasti mostrati nel form, selezionando orari, durata dei turni, buffer, capacità e costo a persona senza ricordare la sintassi testuale.', 'fp-restaurant-reservations'),
                            ],
                        ],
                    ],
                    'general-layout-preferences' => [
                        'title'       => __('Sale & tavoli', 'fp-restaurant-reservations'),
                        'description' => __('Configura le preferenze del planner sale, delle combinazioni tavoli e dei suggerimenti automatici.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'layout_unit' => [
                                'label'          => __('Unità di misura layout', 'fp-restaurant-reservations'),
                                'type'           => 'select',
                                'options'        => [
                                    'meters' => __('Metri', 'fp-restaurant-reservations'),
                                    'feet'   => __('Piedi', 'fp-restaurant-reservations'),
                                ],
                                'default'        => 'meters',
                                'legacy_option'  => 'fp_resv_rooms',
                            ],
                            'default_room_capacity' => [
                                'label'          => __('Capienza sala predefinita', 'fp-restaurant-reservations'),
                                'type'           => 'integer',
                                'default'        => '40',
                                'min'            => 1,
                                'max'            => 200,
                                'legacy_option'  => 'fp_resv_rooms',
                            ],
                            'merge_strategy' => [
                                'label'          => __('Strategia merge tavoli', 'fp-restaurant-reservations'),
                                'type'           => 'select',
                                'options'        => [
                                    'manual' => __('Solo manuale', 'fp-restaurant-reservations'),
                                    'smart'  => __('Suggerisci automaticamente combinazioni', 'fp-restaurant-reservations'),
                                ],
                                'default'        => 'smart',
                                'legacy_option'  => 'fp_resv_rooms',
                            ],
                            'split_confirmation' => [
                                'label'          => __('Conferma separazione tavoli', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Richiedi conferma prima di dividere tavoli uniti.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                                'legacy_option'  => 'fp_resv_rooms',
                            ],
                            'grid_size' => [
                                'label'          => __('Dimensione griglia (px)', 'fp-restaurant-reservations'),
                                'type'           => 'integer',
                                'default'        => '20',
                                'min'            => 5,
                                'max'            => 80,
                                'legacy_option'  => 'fp_resv_rooms',
                            ],
                            'suggestion_strategy' => [
                                'label'          => __('Suggeritore tavolo', 'fp-restaurant-reservations'),
                                'type'           => 'select',
                                'options'        => [
                                    'capacity' => __('Priorità capienza', 'fp-restaurant-reservations'),
                                    'distance' => __('Distanza da ingressi/uscite', 'fp-restaurant-reservations'),
                                    'hybrid'   => __('Bilanciato', 'fp-restaurant-reservations'),
                                ],
                                'default'        => 'hybrid',
                                'legacy_option'  => 'fp_resv_rooms',
                            ],
                        ],
                    ],
                ],
            ],
            'notifications' => [
                'page_title'   => __('Notifiche email', 'fp-restaurant-reservations'),
                'menu_title'   => __('Notifiche', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-notifications',
                'option_group' => 'fp_resv_notifications',
                'option_name'  => 'fp_resv_notifications',
                'sections'     => [
                    'notifications-recipients' => [
                        'title'       => __('Destinatari', 'fp-restaurant-reservations'),
                        'description' => __('Email separate da virgola o nuova riga.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'restaurant_emails' => [
                                'label'       => __('Email ristorante', 'fp-restaurant-reservations'),
                                'type'        => 'email_list',
                                'rows'        => 3,
                                'default'     => ['info@francescopasseri.com'],
                                'required'    => true,
                                'description' => __('Notifiche operative inviate allo staff del ristorante.', 'fp-restaurant-reservations'),
                            ],
                            'webmaster_emails' => [
                                'label'       => __('Email webmaster', 'fp-restaurant-reservations'),
                                'type'        => 'email_list',
                                'rows'        => 3,
                                'default'     => ['info@francescopasseri.com'],
                                'required'    => true,
                                'description' => __('Riceve copie delle notifiche e degli errori critici.', 'fp-restaurant-reservations'),
                            ],
                        ],
                    ],
                    'notifications-preferences' => [
                        'title'  => __('Preferenze di invio', 'fp-restaurant-reservations'),
                        'fields' => [
                            'sender_name' => [
                                'label'       => __('Nome mittente', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => 'FP Restaurant Reservations',
                                'description' => __('Comparirà come mittente nelle email di sistema.', 'fp-restaurant-reservations'),
                            ],
                            'sender_email' => [
                                'label'       => __('Email mittente', 'fp-restaurant-reservations'),
                                'type'        => 'email',
                                'default'     => 'info@francescopasseri.com',
                            ],
                            'reply_to_email' => [
                                'label'   => __('Reply-To', 'fp-restaurant-reservations'),
                                'type'    => 'email',
                                'default' => '',
                            ],
                            'customer_template_logo_url' => [
                                'label'       => __('Logo email', 'fp-restaurant-reservations'),
                                'type'        => 'url',
                                'default'     => '',
                                'description' => __('URL dell\'immagine da mostrare nell\'header delle email personalizzate.', 'fp-restaurant-reservations'),
                            ],
                            'customer_template_header' => [
                                'label'       => __('Header email (HTML)', 'fp-restaurant-reservations'),
                                'type'        => 'textarea_html',
                                'rows'        => 4,
                                'default'     => '',
                                'description' => __('Puoi usare HTML e segnaposto come {{restaurant.name}}, {{restaurant.logo_img}} o {{emails.year}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_template_footer' => [
                                'label'       => __('Footer email (HTML)', 'fp-restaurant-reservations'),
                                'type'        => 'textarea_html',
                                'rows'        => 4,
                                'default'     => '',
                                'description' => __('Contenuto mostrato dopo il messaggio principale. Usa i segnaposto per personalizzare firma e contatti.', 'fp-restaurant-reservations'),
                            ],
                            'attach_ics' => [
                                'label'          => __('Allega ICS', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Allega il calendario ICS alle conferme.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'notify_on_cancel' => [
                                'label'          => __('Avvisa sugli annullamenti', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Invia un avviso immediato quando una prenotazione viene annullata.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                        ],
                    ],
                    'notifications-customer' => [
                        'title'       => __('Email cliente', 'fp-restaurant-reservations'),
                        'description' => __('Configura conferme, promemoria e follow-up gestiti direttamente dal plugin.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'customer_confirmation_channel' => [
                                'label'       => __('Canale conferma', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'plugin' => __('Invia dal plugin', 'fp-restaurant-reservations'),
                                    'brevo'  => __('Usa Brevo (se configurato)', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'plugin',
                                'description' => __('Scegli se inviare la conferma interna o delegarla a Brevo.', 'fp-restaurant-reservations'),
                            ],
                            'customer_confirmation_subject' => [
                                'label'       => __('Oggetto conferma', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => __('La tua prenotazione per {{reservation.formatted_date}}', 'fp-restaurant-reservations'),
                                'description' => __('Segnaposto disponibili: {{customer.first_name}}, {{customer.last_name}}, {{reservation.formatted_date}}, {{reservation.formatted_time}}, {{reservation.party}}, {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_confirmation_body' => [
                                'label'       => __('Corpo conferma', 'fp-restaurant-reservations'),
                                'type'        => 'textarea_html',
                                'rows'        => 8,
                                'default'     => implode("\n\n", [
                                    __('Ciao {{customer.first_name}} {{customer.last_name}},', 'fp-restaurant-reservations'),
                                    __('ti confermiamo la prenotazione per {{reservation.party}} persone il {{reservation.formatted_date}} alle {{reservation.formatted_time}}.', 'fp-restaurant-reservations'),
                                    __('Puoi gestire o annullare la prenotazione qui: {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                                    __('A presto!', 'fp-restaurant-reservations'),
                                ]),
                                'description' => __('Segnaposto disponibili: {{customer.first_name}}, {{customer.last_name}}, {{reservation.formatted_date}}, {{reservation.formatted_time}}, {{reservation.party}}, {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_reminder_channel' => [
                                'label'       => __('Canale promemoria', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'plugin' => __('Invia dal plugin', 'fp-restaurant-reservations'),
                                    'brevo'  => __('Usa Brevo (se configurato)', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'plugin',
                            ],
                            'customer_reminder_enabled' => [
                                'label'          => __('Invia promemoria', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Invia un promemoria automatico prima dell\'arrivo.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'customer_reminder_offset_hours' => [
                                'label'       => __('Anticipo promemoria (ore)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '4',
                                'min'         => 1,
                                'max'         => 168,
                            ],
                            'customer_reminder_subject' => [
                                'label'       => __('Oggetto promemoria', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => __('Promemoria: prenotazione del {{reservation.formatted_date}} alle {{reservation.formatted_time}}', 'fp-restaurant-reservations'),
                                'description' => __('Segnaposto disponibili: {{customer.first_name}}, {{customer.last_name}}, {{reservation.formatted_date}}, {{reservation.formatted_time}}, {{reservation.party}}, {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_reminder_body' => [
                                'label'       => __('Corpo promemoria', 'fp-restaurant-reservations'),
                                'type'        => 'textarea_html',
                                'rows'        => 6,
                                'default'     => implode("\n\n", [
                                    __('Ciao {{customer.first_name}} {{customer.last_name}},', 'fp-restaurant-reservations'),
                                    __('ti aspettiamo il {{reservation.formatted_date}} alle {{reservation.formatted_time}} per {{reservation.party}} persone.', 'fp-restaurant-reservations'),
                                    __('Se hai bisogno di modificare la prenotazione puoi farlo qui: {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                                ]),
                                'description' => __('Segnaposto disponibili: {{customer.first_name}}, {{customer.last_name}}, {{reservation.formatted_date}}, {{reservation.formatted_time}}, {{reservation.party}}, {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_review_channel' => [
                                'label'       => __('Canale follow-up recensione', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'plugin' => __('Invia dal plugin', 'fp-restaurant-reservations'),
                                    'brevo'  => __('Usa Brevo (se configurato)', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'plugin',
                            ],
                            'customer_review_enabled' => [
                                'label'          => __('Chiedi una recensione', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Invia un follow-up dopo la visita per richiedere una recensione.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'customer_review_delay_hours' => [
                                'label'       => __('Invio follow-up (ore dopo la visita)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '24',
                                'min'         => 1,
                                'max'         => 168,
                            ],
                            'customer_review_subject' => [
                                'label'       => __('Oggetto follow-up', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => __('Com\'è andata la tua visita da {{restaurant.name}}?', 'fp-restaurant-reservations'),
                                'description' => __('Segnaposto disponibili: {{customer.first_name}}, {{customer.last_name}}, {{restaurant.name}}, {{review.link}}, {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_review_body' => [
                                'label'       => __('Corpo follow-up', 'fp-restaurant-reservations'),
                                'type'        => 'textarea_html',
                                'rows'        => 6,
                                'default'     => implode("\n\n", [
                                    __('Ciao {{customer.first_name}} {{customer.last_name}},', 'fp-restaurant-reservations'),
                                    __('grazie per averci fatto visita. Raccontaci com\'è andata lasciando una recensione: {{review.link}}.', 'fp-restaurant-reservations'),
                                    __('Il tuo feedback è prezioso per noi!', 'fp-restaurant-reservations'),
                                ]),
                                'description' => __('Segnaposto disponibili: {{customer.first_name}}, {{customer.last_name}}, {{restaurant.name}}, {{review.link}}, {{reservation.manage_link}}.', 'fp-restaurant-reservations'),
                            ],
                            'customer_review_url' => [
                                'label'       => __('URL recensione', 'fp-restaurant-reservations'),
                                'type'        => 'url',
                                'default'     => '',
                                'description' => __('Link alla pagina recensioni (es. Google, TripAdvisor).', 'fp-restaurant-reservations'),
                            ],
                        ],
                    ],
                ],
            ],
            'payments' => [
                'page_title'   => __('Pagamenti Stripe', 'fp-restaurant-reservations'),
                'menu_title'   => __('Pagamenti', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-payments',
                'option_group' => 'fp_resv_payments',
                'option_name'  => 'fp_resv_payments',
                'sections'     => [
                    'payments-stripe' => [
                        'title'       => __('Configurazione Stripe', 'fp-restaurant-reservations'),
                        'description' => __('I pagamenti sono facoltativi e disattivati di default.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'stripe_enabled' => [
                                'label'          => __('Pagamenti Stripe', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Abilita la richiesta di pagamento nel form di prenotazione.', 'fp-restaurant-reservations'),
                                'default'        => '0',
                            ],
                            'stripe_mode' => [
                                'label'       => __('Modalità', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'test' => __('Test', 'fp-restaurant-reservations'),
                                    'live' => __('Live', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'test',
                            ],
                            'stripe_capture_type' => [
                                'label'       => __('Strategia di incasso', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'authorization' => __('Pre-autorizzazione', 'fp-restaurant-reservations'),
                                    'capture'       => __('Incasso immediato', 'fp-restaurant-reservations'),
                                    'deposit'       => __('Caparra fissa', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'authorization',
                                'description' => __('Scegli come gestire il pagamento al momento della prenotazione.', 'fp-restaurant-reservations'),
                            ],
                            'stripe_deposit_amount' => [
                                'label'       => __('Importo caparra', 'fp-restaurant-reservations'),
                                'type'        => 'number',
                                'default'     => '',
                                'min'         => 0,
                                'step'        => '0.01',
                                'description' => __('Obbligatorio se la strategia è impostata su caparra.', 'fp-restaurant-reservations'),
                            ],
                            'stripe_currency' => [
                                'label'       => __('Valuta Stripe', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => 'EUR',
                                'description' => __('Codice ISO supportato dal tuo account Stripe.', 'fp-restaurant-reservations'),
                            ],
                            'stripe_publishable_key' => [
                                'label'   => __('Publishable key', 'fp-restaurant-reservations'),
                                'type'    => 'text',
                                'default' => '',
                            ],
                            'stripe_secret_key' => [
                                'label'   => __('Secret key', 'fp-restaurant-reservations'),
                                'type'    => 'password',
                                'default' => '',
                            ],
                            'stripe_webhook_secret' => [
                                'label'       => __('Webhook secret', 'fp-restaurant-reservations'),
                                'type'        => 'password',
                                'default'     => '',
                                'description' => __('Utilizzato per validare gli eventi webhook.', 'fp-restaurant-reservations'),
                            ],
                        ],
                    ],
                ],
            ],
            'brevo' => [
                'page_title'   => __('Brevo & Follow-up', 'fp-restaurant-reservations'),
                'menu_title'   => __('Brevo', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-brevo',
                'option_group' => 'fp_resv_brevo',
                'option_name'  => 'fp_resv_brevo',
                'sections'     => [
                    'brevo-settings' => [
                        'title'       => __('Configurazione Brevo', 'fp-restaurant-reservations'),
                        'description' => __('Automatizza follow-up, survey e invio recensioni Google.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'brevo_enabled' => [
                                'label'          => __('Abilita Brevo', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Attiva sincronizzazione contatti e automazioni.', 'fp-restaurant-reservations'),
                                'default'        => '0',
                            ],
                            'brevo_api_key' => [
                                'label'       => __('API key Brevo', 'fp-restaurant-reservations'),
                                'type'        => 'password',
                                'default'     => '',
                                'description' => __('Chiave privata con permessi marketing + transactional.', 'fp-restaurant-reservations'),
                            ],
                            'brevo_list_id_it' => [
                                'label'       => __('ID lista contatti IT', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                                'description' => __('Lista per i contatti con lingua italiana.', 'fp-restaurant-reservations'),
                            ],
                            'brevo_list_id_en' => [
                                'label'       => __('ID lista contatti EN', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                                'description' => __('Lista per i contatti con lingua inglese o internazionale.', 'fp-restaurant-reservations'),
                            ],
                            'brevo_phone_prefix_map' => [
                                'label'       => __('Mappa prefissi telefono → lingua', 'fp-restaurant-reservations'),
                                'type'        => 'phone_prefix_map',
                                'rows'        => 4,
                                'default'     => (string) wp_json_encode(self::DEFAULT_PHONE_PREFIX_MAP),
                                'description' => __('Usa formato +39=IT,+33=EN oppure righe separate. I valori ammessi sono IT, EN o INT.', 'fp-restaurant-reservations'),
                            ],
                            'brevo_list_id' => [
                                'label'       => __('ID lista contatti (fallback)', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                                'description' => __('Lista di ripiego se non viene determinata una lingua specifica.', 'fp-restaurant-reservations'),
                            ],
                            'brevo_followup_offset_hours' => [
                                'label'       => __('Invio follow-up (ore dalla visita)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '24',
                                'min'         => 1,
                                'max'         => 72,
                            ],
                            'brevo_review_threshold' => [
                                'label'       => __('Soglia recensione (media stelle)', 'fp-restaurant-reservations'),
                                'type'        => 'number',
                                'default'     => '4.5',
                                'min'         => 0,
                                'max'         => 5,
                                'step'        => '0.1',
                            ],
                            'brevo_review_nps_threshold' => [
                                'label'       => __('Soglia recensione (NPS)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '9',
                                'min'         => 0,
                                'max'         => 10,
                            ],
                            'brevo_review_place_id' => [
                                'label'       => __('Google Place ID', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                                'description' => __('Utilizzato per generare il link diretto alle recensioni Google.', 'fp-restaurant-reservations'),
                            ],
                        ],
                    ],
                ],
            ],
            'google-calendar' => [
                'page_title'   => __('Google Calendar', 'fp-restaurant-reservations'),
                'menu_title'   => __('Google Calendar', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-google-calendar',
                'option_group' => 'fp_resv_google_calendar',
                'option_name'  => 'fp_resv_google_calendar',
                'sections'     => [
                    'google-oauth' => [
                        'title'       => __('OAuth & calendario', 'fp-restaurant-reservations'),
                        'description' => __('Configura l\'app Google Cloud e seleziona il calendario di destinazione.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'google_calendar_enabled' => [
                                'label'          => __('Sincronizzazione Google Calendar', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Crea e aggiorna eventi nel calendario collegato.', 'fp-restaurant-reservations'),
                                'default'        => '0',
                            ],
                            'google_calendar_client_id' => [
                                'label'   => __('Client ID', 'fp-restaurant-reservations'),
                                'type'    => 'text',
                                'default' => '',
                            ],
                            'google_calendar_client_secret' => [
                                'label'   => __('Client Secret', 'fp-restaurant-reservations'),
                                'type'    => 'password',
                                'default' => '',
                            ],
                            'google_calendar_redirect_uri' => [
                                'label'       => __('Redirect URI', 'fp-restaurant-reservations'),
                                'type'        => 'url',
                                'default'     => '',
                                'description' => __('Copia l\'URL della pagina di autorizzazione generata dal plugin.', 'fp-restaurant-reservations'),
                            ],
                            'google_calendar_calendar_id' => [
                                'label'       => __('ID calendario', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                                'description' => __('Lascia vuoto per usare il calendario principale.', 'fp-restaurant-reservations'),
                            ],
                            'google_calendar_privacy' => [
                                'label'       => __('Dettaglio eventi', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'private' => __('Solo staff (senza ospiti)', 'fp-restaurant-reservations'),
                                    'guests'  => __('Includi il cliente come guest', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'private',
                            ],
                            'google_calendar_overbooking_guard' => [
                                'label'          => __('Controllo slot occupati', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Blocca la conferma se Google Calendar segnala occupato.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                        ],
                    ],
                ],
            ],
            'style' => [
                'page_title'   => __('Stile del form', 'fp-restaurant-reservations'),
                'menu_title'   => __('Stile', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-style',
                'option_group' => 'fp_resv_style',
                'option_name'  => 'fp_resv_style',
                'sections'     => [
                    'style-foundations' => [
                        'title'       => __('Colori & superfici', 'fp-restaurant-reservations'),
                        'description' => __('Imposta palette, raggio, ombre e focus ring del widget.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'style_palette' => [
                                'label'       => __('Palette di base', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'brand'   => __('Brand', 'fp-restaurant-reservations'),
                                    'neutral' => __('Neutra', 'fp-restaurant-reservations'),
                                    'dark'    => __('Dark mode', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'brand',
                            ],
                            'style_primary_color' => [
                                'label'       => __('Colore principale', 'fp-restaurant-reservations'),
                                'type'        => 'color',
                                'default'     => '#bb2649',
                            ],
                            'style_border_radius' => [
                                'label'       => __('Raggio bordi (px)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '8',
                                'min'         => 0,
                                'max'         => 32,
                            ],
                            'style_shadow_level' => [
                                'label'       => __('Intensità ombre', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'none'   => __('Nessuna', 'fp-restaurant-reservations'),
                                    'soft'   => __('Morbida', 'fp-restaurant-reservations'),
                                    'strong' => __('Decisa', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'soft',
                            ],
                            'style_spacing_scale' => [
                                'label'       => __('Spaziatura layout', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'compact'     => __('Compatta', 'fp-restaurant-reservations'),
                                    'cozy'        => __('Standard', 'fp-restaurant-reservations'),
                                    'comfortable' => __('Aria', 'fp-restaurant-reservations'),
                                    'spacious'    => __('Lounge', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'cozy',
                                'description' => __('Controlla la densità di spaziatura per carte, step e moduli.', 'fp-restaurant-reservations'),
                            ],
                            'style_focus_ring_width' => [
                                'label'       => __('Focus ring (px)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '3',
                                'min'         => 1,
                                'max'         => 6,
                                'description' => __('Spessore visibile dell’anello di focus per pulsanti e campi.', 'fp-restaurant-reservations'),
                            ],
                            'style_enable_dark_mode' => [
                                'label'          => __('Dark mode automatica', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Adatta i colori al tema scuro del dispositivo.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                        ],
                    ],
                    'style-typography' => [
                        'title'       => __('Tipografia & gerarchie', 'fp-restaurant-reservations'),
                        'description' => __('Scegli font, dimensione base e peso titoli del form.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'style_font_family' => [
                                'label'       => __('Font preferito', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '"Inter", sans-serif',
                            ],
                            'style_font_size' => [
                                'label'       => __('Dimensione base testo', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    '15' => __('Compatta (15px)', 'fp-restaurant-reservations'),
                                    '16' => __('Standard (16px)', 'fp-restaurant-reservations'),
                                    '17' => __('Aumentata (17px)', 'fp-restaurant-reservations'),
                                    '18' => __('Ampia (18px)', 'fp-restaurant-reservations'),
                                ],
                                'default'     => '16',
                            ],
                            'style_heading_weight' => [
                                'label'       => __('Peso titoli', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    '500' => __('Media (500)', 'fp-restaurant-reservations'),
                                    '600' => __('Semibold (600)', 'fp-restaurant-reservations'),
                                    '700' => __('Bold (700)', 'fp-restaurant-reservations'),
                                ],
                                'default'     => '600',
                            ],
                        ],
                    ],
                    'style-custom' => [
                        'title'       => __('Personalizzazioni avanzate', 'fp-restaurant-reservations'),
                        'description' => __('CSS opzionale applicato al widget (senza tag <style>).', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'style_custom_css' => [
                                'label'       => __('CSS aggiuntivo', 'fp-restaurant-reservations'),
                                'type'        => 'textarea',
                                'rows'        => 6,
                                'default'     => '',
                            ],
                        ],
                    ],
                ],
            ],
            'language' => [
                'page_title'   => __('Lingua & Localizzazione', 'fp-restaurant-reservations'),
                'menu_title'   => __('Lingua', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-language',
                'option_group' => 'fp_resv_language',
                'option_name'  => 'fp_resv_language',
                'sections'     => [
                    'language-settings' => [
                        'title'       => __('Preferenze lingua', 'fp-restaurant-reservations'),
                        'description' => __('Gestisci auto-detect, localizzazioni e risorse multilingua.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'language_auto_detect' => [
                                'label'          => __('Auto rilevamento lingua', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Usa WPML/Polylang o get_locale() per impostare il form.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'language_default_locale' => [
                                'label'       => __('Lingua di fallback', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'it_IT' => __('Italiano', 'fp-restaurant-reservations'),
                                    'en_US' => __('Inglese', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'it_IT',
                            ],
                            'language_supported_locales' => [
                                'label'       => __('Lingue abilitate', 'fp-restaurant-reservations'),
                                'type'        => 'textarea',
                                'rows'        => 3,
                                'default'     => "it_IT\nen_US",
                                'description' => __('Uno per riga, formato locale WordPress.', 'fp-restaurant-reservations'),
                            ],
                            'pdf_urls' => [
                                'label'       => __('URL PDF per lingua', 'fp-restaurant-reservations'),
                                'type'        => 'language_map',
                                'rows'        => 3,
                                'default'     => [],
                                'description' => __('Formato: it=https://... Una riga per lingua.', 'fp-restaurant-reservations'),
                            ],
                            'language_cookie_days' => [
                                'label'       => __('Durata cookie lingua (giorni)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '30',
                                'min'         => 0,
                                'max'         => 365,
                            ],
                        ],
                    ],
                ],
            ],
            'closures' => [
                'page_title'   => __('Chiusure & Orari speciali', 'fp-restaurant-reservations'),
                'menu_title'   => __('Orari speciali', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-orari-speciali',
                'option_group' => 'fp_resv_closures',
                'option_name'  => 'fp_resv_closures',
                'sections'     => [
                    'closures-defaults' => [
                        'title'       => __('Regole di default', 'fp-restaurant-reservations'),
                        'description' => __('Imposta le preferenze usate dalla pianificazione automatica di chiusure e riduzioni.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'closure_default_scope' => [
                                'label'       => __('Ambito predefinito', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'restaurant' => __('Intero locale', 'fp-restaurant-reservations'),
                                    'room'       => __('Singola sala', 'fp-restaurant-reservations'),
                                    'table'      => __('Tavolo specifico', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'restaurant',
                            ],
                            'closure_lead_time_days' => [
                                'label'       => __('Preavviso minimo (giorni)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '2',
                                'min'         => 0,
                                'max'         => 30,
                            ],
                            'closure_capacity_override' => [
                                'label'       => __('Riduzione capacità (%)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '100',
                                'min'         => 0,
                                'max'         => 100,
                                'description' => __('Percentuale di capienza da applicare quando si crea una riduzione.', 'fp-restaurant-reservations'),
                            ],
                            'closure_allow_recurring' => [
                                'label'          => __('Permetti ricorrenze', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Abilita eventi ricorrenti settimanali/mensili per le chiusure.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                        ],
                    ],
                ],
            ],
            'tracking' => [
                'page_title'   => __('Tracking & Consent', 'fp-restaurant-reservations'),
                'menu_title'   => __('Tracking', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-tracking',
                'option_group' => 'fp_resv_tracking',
                'option_name'  => 'fp_resv_tracking',
                'sections'     => [
                    'tracking-integrations' => [
                        'title'       => __('Integrazioni marketing', 'fp-restaurant-reservations'),
                        'description' => __('Configura GA4, Google Ads, Meta Pixel, Clarity e preferenze di consenso.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'ga4_measurement_id' => [
                                'label'       => __('GA4 Measurement ID', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
                            ],
                            'google_ads_conversion_id' => [
                                'label'   => __('ID conversione Google Ads', 'fp-restaurant-reservations'),
                                'type'    => 'text',
                                'default' => '',
                            ],
                            'meta_pixel_id' => [
                                'label'   => __('Meta Pixel ID', 'fp-restaurant-reservations'),
                                'type'    => 'text',
                                'default' => '',
                            ],
                            'clarity_project_id' => [
                                'label'   => __('Microsoft Clarity Project ID', 'fp-restaurant-reservations'),
                                'type'    => 'text',
                                'default' => '',
                            ],
                            'consent_mode_default' => [
                                'label'       => __('Stato Consent Mode predefinito', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'denied'    => __('Negato', 'fp-restaurant-reservations'),
                                    'granted'   => __('Concesso', 'fp-restaurant-reservations'),
                                    'auto'      => __('Determina automaticamente', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'auto',
                            ],
                            'tracking_cookie_ttl_days' => [
                                'label'       => __('Durata cookie tracciamento (giorni)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '180',
                                'min'         => 0,
                                'max'         => 730,
                            ],
                            'tracking_utm_cookie_days' => [
                                'label'       => __('Durata cookie UTM (giorni)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '90',
                                'min'         => 0,
                                'max'         => 365,
                            ],
                            'tracking_enable_debug' => [
                                'label'          => __('Modalità debug', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Abilita log dettagliato nel browser (solo per sviluppo).', 'fp-restaurant-reservations'),
                                'default'        => '0',
                            ],
                    ],
                ],
                'privacy-controls' => [
                    'title'       => __('Privacy & GDPR', 'fp-restaurant-reservations'),
                    'description' => __('Configura informativa, consensi opzionali e politiche di retention.', 'fp-restaurant-reservations'),
                    'fields'      => [
                        'privacy_policy_url' => [
                            'label'       => __('URL informativa privacy', 'fp-restaurant-reservations'),
                            'type'        => 'url',
                            'default'     => '',
                        ],
                        'privacy_policy_version' => [
                            'label'       => __('Versione informativa', 'fp-restaurant-reservations'),
                            'type'        => 'text',
                            'default'     => '1.0',
                            'description' => __('Indicata nelle registrazioni di consenso dei clienti.', 'fp-restaurant-reservations'),
                        ],
                        'privacy_enable_marketing_consent' => [
                            'label'          => __('Consenso marketing', 'fp-restaurant-reservations'),
                            'type'           => 'checkbox',
                            'checkbox_label' => __('Mostra una checkbox opzionale per comunicazioni promozionali.', 'fp-restaurant-reservations'),
                            'default'        => '0',
                        ],
                        'privacy_enable_profiling_consent' => [
                            'label'          => __('Consenso profilazione', 'fp-restaurant-reservations'),
                            'type'           => 'checkbox',
                            'checkbox_label' => __('Mostra una checkbox opzionale per offerte personalizzate.', 'fp-restaurant-reservations'),
                            'default'        => '0',
                        ],
                        'privacy_retention_months' => [
                            'label'       => __('Anonimizza dati dopo (mesi)', 'fp-restaurant-reservations'),
                            'type'        => 'integer',
                            'default'     => '24',
                            'min'         => 0,
                            'max'         => 120,
                            'description' => __('Imposta 0 per disattivare la pulizia automatica.', 'fp-restaurant-reservations'),
                        ],
                    ],
                ],
            ],
        ],
    ];
    }
}

