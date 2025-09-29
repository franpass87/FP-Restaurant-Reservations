<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\ServiceContainer;
use function __;
use function add_action;
use function add_menu_page;
use function add_settings_error;
use function add_settings_field;
use function add_settings_section;
use function add_submenu_page;
use function array_key_first;
use function current_user_can;
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
use function preg_match;
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
use function sprintf;
use function str_contains;
use function strtolower;
use function submit_button;
use function timezone_identifiers_list;
use function trim;
use function wp_add_inline_style;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_strip_all_tags;
use const FILTER_FLAG_ALLOW_FRACTION;
use const FILTER_SANITIZE_SPECIAL_CHARS;
use const FILTER_VALIDATE_URL;
use const INPUT_GET;

final class AdminPages
{
    private const CAPABILITY = 'manage_options';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $pages;

    public function __construct()
    {
        $this->pages = $this->definePages();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
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
        wp_enqueue_style($handle, Plugin::$url . 'assets/css/admin-style.css', [], Plugin::VERSION);
        wp_enqueue_script($handle, Plugin::$url . 'assets/js/admin/style-preview.js', [], Plugin::VERSION, true);
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
            ],
        ]);
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

        foreach ($this->pages as $pageKey => $page) {
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

        $value = $options[$fieldKey] ?? ($field['default'] ?? ($field['type'] === 'checkbox' ? '0' : ''));

        $inputName   = $optionKey . '[' . $fieldKey . ']';
        $description = (string) ($field['description'] ?? '');

        switch ($field['type']) {
            case 'checkbox':
                $checked = $value === '1' || $value === 1 || $value === true;
                echo '<label><input type="checkbox" value="1" name="' . esc_attr($inputName) . '"' . ($checked ? ' checked="checked"' : '') . '> ' . esc_html((string) ($field['checkbox_label'] ?? '')) . '</label>';
                break;
            case 'textarea':
                $rows = (int) ($field['rows'] ?? 5);
                echo '<textarea class="large-text" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($inputName) . '">';
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

        echo '<div class="wrap">';
        echo '<h1>' . esc_html((string) $page['page_title']) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_errors($optionGroup);
        settings_fields($optionGroup);
        do_settings_sections($page['slug']);
        submit_button();
        echo '</form>';
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

        echo '<div class="wrap fp-resv-style-admin">';
        echo '<h1>' . esc_html((string) $page['page_title']) . '</h1>';
        echo '<div class="fp-resv-style-admin__layout">';
        echo '<div class="fp-resv-style-admin__form">';
        echo '<form method="post" action="options.php" id="fp-resv-style-form">';
        settings_errors($optionGroup);
        settings_fields($optionGroup);
        do_settings_sections($page['slug']);
        submit_button();
        echo '</form>';
        echo '</div>';
        echo '<div class="fp-resv-style-admin__preview">';
        $previewData = $preview;
        include Plugin::$dir . 'src/Admin/Views/style.php';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
                if (!empty($options['service_hours_definition'])) {
                    $this->validateServiceHoursDefinition($pageKey, (string) $options['service_hours_definition']);
                }
                if (!empty($options['table_turnover_minutes']) && !empty($options['slot_interval_minutes'])) {
                    $turnover = (int) $options['table_turnover_minutes'];
                    $slot     = (int) $options['slot_interval_minutes'];
                    if ($turnover < $slot) {
                        $this->addError(
                            $pageKey,
                            'turnover_too_short',
                            __('La durata del turno tavolo deve essere maggiore o uguale all\'intervallo slot.', 'fp-restaurant-reservations')
                        );
                    }
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

    private function validateServiceHoursDefinition(string $pageKey, string $definition): void
    {
        $lines       = preg_split('/\n/', $definition) ?: [];
        $allowedDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $invalid     = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                $invalid[] = $line;
                continue;
            }

            [$day, $ranges] = array_map('trim', explode('=', $line, 2));
            $day            = strtolower($day);

            if (!in_array($day, $allowedDays, true)) {
                $invalid[] = $line;
                continue;
            }

            $segments = preg_split('/[|,]/', $ranges) ?: [];
            if ($segments === []) {
                $invalid[] = $line;
                continue;
            }

            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    $invalid[] = $line;
                    continue 2;
                }

                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    $invalid[] = $line;
                    continue 2;
                }

                $startMinutes = ((int) $matches[1] * 60) + (int) $matches[2];
                $endMinutes   = ((int) $matches[3] * 60) + (int) $matches[4];

                if ($endMinutes <= $startMinutes) {
                    $invalid[] = $line;
                    continue 2;
                }
            }
        }

        if ($invalid !== []) {
            $this->addError(
                $pageKey,
                'invalid_service_hours',
                sprintf(
                    __('Alcune righe non rispettano il formato richiesto per gli orari di servizio: %s', 'fp-restaurant-reservations'),
                    implode(', ', array_map('wp_strip_all_tags', $invalid))
                )
            );
        }
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
                        'description' => __('Definisci gli orari di servizio, la durata dei turni e i buffer usati dal motore disponibilità.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'service_hours_definition' => [
                                'label'       => __('Orari di servizio', 'fp-restaurant-reservations'),
                                'type'        => 'textarea',
                                'rows'        => 5,
                                'default'     => "mon=19:00-23:00\ntue=19:00-23:00\nwed=19:00-23:00\nthu=19:00-23:00\nfri=19:00-23:30\nsat=12:30-15:00|19:00-23:30\nsun=12:30-15:00",
                                'description' => __('Formato: giorno=HH:MM-HH:MM separando i turni con la barra verticale. Giorni ammessi: mon,tue,wed,thu,fri,sat,sun.', 'fp-restaurant-reservations'),
                            ],
                            'slot_interval_minutes' => [
                                'label'       => __('Intervallo slot (minuti)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '15',
                                'min'         => 5,
                                'max'         => 120,
                            ],
                            'table_turnover_minutes' => [
                                'label'       => __('Durata turno tavolo (minuti)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '120',
                                'min'         => 30,
                                'max'         => 300,
                            ],
                            'buffer_before_minutes' => [
                                'label'       => __('Buffer cambio tavolo (minuti)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '15',
                                'min'         => 0,
                                'max'         => 120,
                                'description' => __('Tempo minimo tra una prenotazione e la successiva sullo stesso tavolo.', 'fp-restaurant-reservations'),
                            ],
                            'max_parallel_parties' => [
                                'label'       => __('Prenotazioni parallele per slot', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '8',
                                'min'         => 1,
                                'max'         => 40,
                                'description' => __('Limite di richieste contemporanee quando i tavoli non sono assegnati.', 'fp-restaurant-reservations'),
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
                            'brevo_list_id' => [
                                'label'       => __('ID lista contatti', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '',
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
                    'style-appearance' => [
                        'title'       => __('Aspetto', 'fp-restaurant-reservations'),
                        'description' => __('Imposta palette, tipografia e angoli del widget.', 'fp-restaurant-reservations'),
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
                            'style_font_family' => [
                                'label'       => __('Font preferito', 'fp-restaurant-reservations'),
                                'type'        => 'text',
                                'default'     => '"Inter", sans-serif',
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
                            'style_enable_dark_mode' => [
                                'label'          => __('Dark mode automatica', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Adatta i colori al tema scuro del dispositivo.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'style_custom_css' => [
                                'label'       => __('CSS aggiuntivo', 'fp-restaurant-reservations'),
                                'type'        => 'textarea',
                                'rows'        => 6,
                                'default'     => '',
                                'description' => __('Snippet opzionale applicato al widget (senza tag <style>).', 'fp-restaurant-reservations'),
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
                'menu_title'   => __('Chiusure', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-closures',
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
            'rooms' => [
                'page_title'   => __('Sale & Tavoli', 'fp-restaurant-reservations'),
                'menu_title'   => __('Sale & Tavoli', 'fp-restaurant-reservations'),
                'slug'         => 'fp-resv-rooms',
                'option_group' => 'fp_resv_rooms',
                'option_name'  => 'fp_resv_rooms',
                'sections'     => [
                    'rooms-defaults' => [
                        'title'       => __('Layout e suggerimenti', 'fp-restaurant-reservations'),
                        'description' => __('Preferenze usate dal layout editor e dal motore suggerimenti tavoli.', 'fp-restaurant-reservations'),
                        'fields'      => [
                            'layout_unit' => [
                                'label'       => __('Unità di misura layout', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'meters' => __('Metri', 'fp-restaurant-reservations'),
                                    'feet'   => __('Piedi', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'meters',
                            ],
                            'default_room_capacity' => [
                                'label'       => __('Capienza sala predefinita', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '40',
                                'min'         => 1,
                                'max'         => 200,
                            ],
                            'merge_strategy' => [
                                'label'       => __('Strategia merge tavoli', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'manual' => __('Solo manuale', 'fp-restaurant-reservations'),
                                    'smart'  => __('Suggerisci automaticamente combinazioni', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'smart',
                            ],
                            'split_confirmation' => [
                                'label'          => __('Conferma separazione tavoli', 'fp-restaurant-reservations'),
                                'type'           => 'checkbox',
                                'checkbox_label' => __('Richiedi conferma prima di dividere tavoli uniti.', 'fp-restaurant-reservations'),
                                'default'        => '1',
                            ],
                            'grid_size' => [
                                'label'       => __('Dimensione griglia (px)', 'fp-restaurant-reservations'),
                                'type'        => 'integer',
                                'default'     => '20',
                                'min'         => 5,
                                'max'         => 80,
                            ],
                            'suggestion_strategy' => [
                                'label'       => __('Suggeritore tavolo', 'fp-restaurant-reservations'),
                                'type'        => 'select',
                                'options'     => [
                                    'capacity'  => __('Priorità capienza', 'fp-restaurant-reservations'),
                                    'distance'  => __('Distanza da ingressi/uscite', 'fp-restaurant-reservations'),
                                    'hybrid'    => __('Bilanciato', 'fp-restaurant-reservations'),
                                ],
                                'default'     => 'hybrid',
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

