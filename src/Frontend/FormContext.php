<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\MealPlan;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Settings\Style;
use FP\Resv\Frontend\PhonePrefixes;
use function apply_filters;
use function array_key_exists;
use function array_keys;
use function array_map;
use function explode;
use function esc_url_raw;
use function json_decode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_split;
use function preg_replace;
use function implode;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function sort;
use function trim;
use function strtoupper;
use function ucwords;
use function wp_strip_all_tags;

final class FormContext
{
    private Options $options;
    private Language $language;

    /**
     * @var array<string, mixed>
     */
    private array $attributes;


    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(Options $options, Language $language, array $attributes = [])
    {
        $this->options    = $options;
        $this->language   = $language;
        $this->attributes = $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $generalDefaults = [
            'restaurant_name'             => '',
            'restaurant_timezone'         => 'Europe/Rome',
            'default_party_size'          => '2',
            'default_reservation_status'  => 'pending',
            'default_currency'            => 'EUR',
            'enable_waitlist'             => '0',
            'data_retention_months'       => '24',
        ];

        $languageDefaults = [
            'language_fallback_locale'   => 'it_IT',
            'language_supported_locales' => 'it_IT' . PHP_EOL . 'en_US',
            'pdf_urls'                   => [],
            'language_cookie_days'       => '30',
        ];

        $trackingDefaults = [
            'privacy_policy_url'               => '',
            'privacy_policy_version'           => '1.0',
            'privacy_enable_marketing_consent' => '0',
            'privacy_enable_profiling_consent' => '0',
            'privacy_retention_months'         => '24',
        ];

        $generalSettings  = $this->options->getGroup('fp_resv_general', $generalDefaults);
        $languageSettings = $this->options->getGroup('fp_resv_language', $languageDefaults);
        $trackingSettings = $this->options->getGroup('fp_resv_tracking', $trackingDefaults);

        $supportedLocales = $this->language->getSupportedLocales();
        $languageData     = $this->language->detect([
            'lang'   => $this->attributes['lang'] ?? '',
            'locale' => $languageSettings['language_fallback_locale'] ?? '',
        ]);
        $fallbackLocale   = $this->language->getFallbackLocale();

        $mealDefinition = isset($generalSettings['frontend_meals']) ? (string) $generalSettings['frontend_meals'] : '';
        $rawMeals       = MealPlan::parse($mealDefinition);

        $config = [
            'formId'          => $this->resolveFormId(),
            'location'        => $this->resolveLocation(),
            'locale'          => $languageData['locale'],
            'language'        => $languageData['language'],
            'language_source' => $languageData['source'],
            'timezone'        => $this->normalizeTimezone($generalSettings['restaurant_timezone'] ?? 'Europe/Rome'),
            'defaults'        => [
                'partySize'       => $this->toInt($generalSettings['default_party_size'] ?? 2, 2),
                'status'          => (string) ($generalSettings['default_reservation_status'] ?? 'pending'),
                'currency'        => (string) ($generalSettings['default_currency'] ?? 'EUR'),
                'waitlistEnabled' => ($generalSettings['enable_waitlist'] ?? '0') === '1',
            ],
        ];

        $brevoSettings  = $this->options->getGroup('fp_resv_brevo', []);
        $customPrefixMap = $this->parsePhonePrefixMap($brevoSettings['brevo_phone_prefix_map'] ?? null);
        $phonePrefixes   = $this->mergePhonePrefixes(PhonePrefixes::getDefaults(), $customPrefixMap);
        $phonePrefixes   = $this->condensePhonePrefixes($phonePrefixes);

        if ($phonePrefixes !== []) {
            $config['phone_prefixes'] = $phonePrefixes;
            $defaultPhoneCode = (string) ($phonePrefixes[0]['value'] ?? '39');

            foreach ($phonePrefixes as $prefixOption) {
                if (($prefixOption['value'] ?? '') === '39') {
                    $defaultPhoneCode = '39';
                    break;
                }
            }

            $config['defaults']['phone_country_code'] = $defaultPhoneCode;
        } else {
            $config['defaults']['phone_country_code'] = '39';
        }

        $meals = MealPlan::normalizeList(apply_filters('fp_resv_form_meals', $rawMeals, $config));
        if ($meals !== []) {
            $defaultMeal = MealPlan::getDefaultKey($meals);
            if ($defaultMeal !== '') {
                $config['defaults']['meal'] = $defaultMeal;
            }
        }

        // Estrai i giorni disponibili dalla configurazione del servizio
        $availableDays = $this->extractAvailableDays($generalSettings, $meals);
        if ($availableDays !== []) {
            $config['available_days'] = $availableDays;
        }

        // Arricchisci ogni meal con i suoi giorni disponibili specifici
        $meals = $this->enrichMealsWithAvailableDays($meals, $generalSettings);

        $dictionary  = $this->language->getStrings($languageData['language']);
        $formStrings = is_array($dictionary['form'] ?? null) ? $dictionary['form'] : [];

        $strings = $this->buildStrings(
            $formStrings,
            (string) ($generalSettings['restaurant_name'] ?? '')
        );

        $privacy = [
            'policy_url'        => esc_url_raw((string) ($trackingSettings['privacy_policy_url'] ?? '')),
            'policy_version'    => trim((string) ($trackingSettings['privacy_policy_version'] ?? '1.0')),
            'marketing_enabled' => ($trackingSettings['privacy_enable_marketing_consent'] ?? '0') === '1',
            'profiling_enabled' => ($trackingSettings['privacy_enable_profiling_consent'] ?? '0') === '1',
            'retention_months'  => (int) ($trackingSettings['privacy_retention_months'] ?? 0),
        ];

        if ($privacy['policy_version'] === '') {
            $privacy['policy_version'] = '1.0';
        }

        $steps = $this->buildSteps(
            is_array($formStrings['step_content'] ?? null) ? $formStrings['step_content'] : [],
            is_array($formStrings['step_order'] ?? null) ? $formStrings['step_order'] : ['date', 'party', 'slots', 'details', 'confirm']
        );
        $pdfUrl = $this->resolvePdfUrl(
            $languageData['language'],
            $languageSettings,
            $supportedLocales,
            $fallbackLocale
        );

        $styleService = new Style($this->options);
        $stylePayload = $styleService->buildFrontend($config['formId']);

        $pdfMapKeys = [];
        if (isset($languageSettings['pdf_urls']) && is_array($languageSettings['pdf_urls'])) {
            $pdfMapKeys = array_keys($languageSettings['pdf_urls']);
        }

        $viewEvent = DataLayer::push([
            'event'       => 'reservation_view',
            'reservation' => [
                'language' => $config['language'],
                'locale'   => $config['locale'],
                'location' => $config['location'],
            ],
            'ga4' => [
                'name'   => 'reservation_view',
                'params' => [
                    'reservation_language' => $config['language'],
                    'reservation_locale'   => $config['locale'],
                    'reservation_location' => $config['location'],
                ],
            ],
        ]);

        $dataLayer = [
            'view'   => $viewEvent,
            'events' => [
                'start'            => 'reservation_start',
                'pdf'              => 'pdf_download_click',
                'submit'           => 'reservation_submit',
                'confirmed'        => 'reservation_confirmed',
                'waitlist'         => 'waitlist_joined',
                'payment_required' => 'reservation_payment_required',
                'cancelled'        => 'reservation_cancelled',
                'modified'         => 'reservation_modified',
                'meal_selected'    => 'meal_selected',
                'section_unlocked' => 'section_unlocked',
                'form_valid'       => 'form_valid',
                'purchase'         => 'purchase',
            ],
        ];

        return [
            'config'      => $config,
            'strings'     => $strings,
            'steps'       => $steps,
            'pdf_url'     => $pdfUrl,
            'data_layer'  => $dataLayer,
            'style'       => $stylePayload,
            'privacy'     => $privacy,
            'meals'       => $meals,
            'meta'        => [
                'supported_locales' => $supportedLocales,
                'pdf_locales'       => $pdfMapKeys,
            ],
        ];
    }

    private function resolveFormId(): string
    {
        $formId = isset($this->attributes['form_id']) ? (string) $this->attributes['form_id'] : '';
        if ($formId === '') {
            $formId = 'fp-resv-' . $this->resolveLocation();
        }

        $sanitized = sanitize_html_class($formId);
        if ($sanitized === '') {
            return 'fp-resv-form';
        }

        return $sanitized;
    }

    private function resolveLocation(): string
    {
        $location = isset($this->attributes['location']) ? strtolower((string) $this->attributes['location']) : 'default';
        $location = preg_replace('/[^a-z0-9_-]+/', '-', $location) ?? 'default';
        $location = trim($location, '-_');

        return $location === '' ? 'default' : $location;
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);

        return $timezone === '' ? 'Europe/Rome' : $timezone;
    }

    private function toInt(mixed $value, int $fallback): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $fallback;
    }

    /**
     * @param array<int, string> $supportedLocales
     */
    private function resolvePdfUrl(string $languageSlug, array $languageSettings, array $supportedLocales, string $fallbackLocale): string
    {
        $map = $languageSettings['pdf_urls'] ?? [];
        if (!is_array($map)) {
            return '';
        }

        $languageSlug = sanitize_key($languageSlug);
        if ($languageSlug !== '' && array_key_exists($languageSlug, $map)) {
            return (string) $map[$languageSlug];
        }

        $fallbackSlug = $this->language->languageFromLocale($fallbackLocale);
        if ($fallbackSlug !== '' && array_key_exists($fallbackSlug, $map)) {
            return (string) $map[$fallbackSlug];
        }

        foreach ($supportedLocales as $locale) {
            $slug = $this->language->languageFromLocale($locale);
            if ($slug !== '' && array_key_exists($slug, $map)) {
                return (string) $map[$slug];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $formStrings
     *
     * @return array<string, mixed>
     */
    private function buildStrings(array $formStrings, string $restaurantName): array
    {
        $headline = $formStrings['headline']['default'] ?? '';
        if ($restaurantName !== '' && isset($formStrings['headline']['with_name'])) {
            $headline = sprintf((string) $formStrings['headline']['with_name'], wp_strip_all_tags($restaurantName));
        }

        return [
            'headline'    => $headline,
            'subheadline' => (string) ($formStrings['subheadline'] ?? ''),
            'pdf_label'   => (string) ($formStrings['pdf_label'] ?? ''),
            'pdf_tooltip' => (string) ($formStrings['pdf_tooltip'] ?? ''),
            'steps'       => is_array($formStrings['steps_labels'] ?? null) ? $formStrings['steps_labels'] : [],
            'fields'      => is_array($formStrings['fields'] ?? null) ? $formStrings['fields'] : [],
            'meals'       => is_array($formStrings['meals'] ?? null) ? $formStrings['meals'] : [],
            'actions'     => is_array($formStrings['actions'] ?? null) ? $formStrings['actions'] : [],
            'summary'     => is_array($formStrings['summary'] ?? null) ? $formStrings['summary'] : [],
            'messages'    => is_array($formStrings['messages'] ?? null) ? $formStrings['messages'] : [],
            'consents'    => is_array($formStrings['consents'] ?? null) ? $formStrings['consents'] : [],
        ];
    }

    /**
     * @param array<string, array<string, string>> $stepContent
     * @param array<int, string> $order
     *
     * @return array<int, array<string, string>>
     */
    private function buildSteps(array $stepContent, array $order): array
    {
        $steps = [];

        foreach ($order as $key) {
            $data = $stepContent[$key] ?? [];
            if (!is_array($data)) {
                $data = [];
            }

            $steps[] = [
                'key'         => (string) $key,
                'title'       => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
            ];
        }

        return $steps;
    }

    /**
     * Estrae la mappa prefisso => lingua dalla configurazione JSON.
     *
     * @return array<string, string>
     */
    private function parsePhonePrefixMap(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $prefix => $language) {
            if (!is_string($prefix)) {
                continue;
            }

            $normalizedPrefix = $this->normalizePhonePrefix($prefix);
            if ($normalizedPrefix === '') {
                continue;
            }

            $languageCode = $this->normalizePhoneLanguage(is_string($language) ? $language : '');
            $map[$normalizedPrefix] = $languageCode;
        }

        return $map;
    }

    /**
     * Integra i prefissi di default con le personalizzazioni custom.
     *
     * @param array<int, array<string, string>> $defaults
     * @param array<string, string> $customMap
     *
     * @return array<int, array<string, string>>
     */
    private function mergePhonePrefixes(array $defaults, array $customMap): array
    {
        if ($customMap === []) {
            return $defaults;
        }

        $merged = [];

        foreach ($defaults as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $prefix = isset($entry['prefix']) ? (string) $entry['prefix'] : '';
            $normalizedPrefix = $this->normalizePhonePrefix($prefix);

            if ($normalizedPrefix === '' || !isset($entry['value']) || !isset($entry['label'])) {
                continue;
            }

            // Sovrascrivi la lingua se presente nella mappa custom
            $language = $customMap[$normalizedPrefix] ?? ($entry['language'] ?? 'INT');

            $merged[] = [
                'prefix'   => $normalizedPrefix,
                'value'    => (string) $entry['value'],
                'language' => $language,
                'label'    => (string) $entry['label'],
            ];
        }

        return $merged;
    }

    /**
     * @param array<int, array<string, string>> $prefixes
     *
     * @return array<int, array<string, string>>
     */
    private function condensePhonePrefixes(array $prefixes): array
    {
        $groups = [];

        foreach ($prefixes as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rawPrefix = isset($entry['prefix']) ? (string) $entry['prefix'] : '';
            $prefix    = $this->normalizePhonePrefix($rawPrefix);
            if ($prefix === '') {
                continue;
            }

            $digits = preg_replace('/[^0-9]/', '', substr($prefix, 1));
            if (!is_string($digits) || $digits === '') {
                continue;
            }

            $language = isset($entry['language']) ? (string) $entry['language'] : 'INT';
            $label    = isset($entry['label']) ? (string) $entry['label'] : $prefix;

            $normalizedLabel = str_replace("\u{00A0}", ' ', $label);
            $name            = trim($normalizedLabel);
            $parts           = preg_split('/\s*·\s*/u', $normalizedLabel, 2);
            if (is_array($parts) && count($parts) === 2) {
                $name = trim(str_replace("\u{00A0}", ' ', $parts[1]));
            }

            if (!isset($groups[$digits])) {
                $groups[$digits] = [
                    'prefix'    => $prefix,
                    'value'     => $digits,
                    'language'  => $language,
                    'countries' => [],
                ];
            }

            if ($name !== '') {
                $groups[$digits]['countries'][$name] = true;
            }
        }

        if ($groups === []) {
            return [];
        }

        // Deduplica i paesi che compaiono con prefissi diversi: mantiene
        // la prima occorrenza e scarta le successive per evitare ripetizioni.
        // Si preferiscono gruppi con prefisso più corto (es. +1 rispetto a +1869),
        // in caso di pari lunghezza si usa l'ordinamento numerico del prefisso.

        // Ordina i gruppi per lunghezza prefisso e poi per valore numerico
        uasort(
            $groups,
            static function (array $a, array $b): int {
                $lenA = strlen((string) ($a['value'] ?? ''));
                $lenB = strlen((string) ($b['value'] ?? ''));
                if ($lenA !== $lenB) {
                    return $lenA <=> $lenB;
                }

                $numA = (int) ($a['value'] ?? 0);
                $numB = (int) ($b['value'] ?? 0);

                return $numA <=> $numB;
            }
        );

        $usedCountries = [];
        $options = [];

        foreach ($groups as $group) {
            $countries = array_keys($group['countries']);
            sort($countries, SORT_NATURAL | SORT_FLAG_CASE);

            // Filtra i paesi già utilizzati da un altro gruppo
            $countries = array_values(array_filter(
                $countries,
                static function (string $name) use (&$usedCountries): bool {
                    if ($name === '') {
                        return false;
                    }
                    if (isset($usedCountries[$name])) {
                        return false;
                    }
                    $usedCountries[$name] = true;
                    return true;
                }
            ));

            if ($countries === []) {
                // Tutti i paesi di questo gruppo erano duplicati di altri prefissi
                continue;
            }

            $label = $group['prefix'];
            if ($countries !== []) {
                $label .= ' · ' . implode(', ', $countries);
            }

            $options[] = [
                'prefix'   => $group['prefix'],
                'value'    => $group['value'],
                'language' => $group['language'],
                'label'    => $label,
            ];
        }

        usort(
            $options,
            static function (array $first, array $second): int {
                $firstLabel  = isset($first['label']) ? (string) $first['label'] : '';
                $secondLabel = isset($second['label']) ? (string) $second['label'] : '';

                return strnatcasecmp($firstLabel, $secondLabel);
            }
        );

        return $options;
    }

    private function normalizePhonePrefix(string $prefix): string
    {
        $normalized = str_replace(' ', '', trim($prefix));
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        } elseif (!str_starts_with($normalized, '+')) {
            $normalized = '+' . ltrim($normalized, '+');
        }

        $digits = preg_replace('/[^0-9]/', '', substr($normalized, 1));
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        return '+' . $digits;
    }

    private function normalizePhoneLanguage(string $value): string
    {
        $upper = strtoupper(trim($value));
        if ($upper === '') {
            return 'INT';
        }

        if (str_starts_with($upper, 'IT')) {
            return 'IT';
        }

        if (str_starts_with($upper, 'EN')) {
            return 'EN';
        }

        if (str_starts_with($upper, 'INT')) {
            return 'INT';
        }

        return 'INT';
    }

    /**
     * Estrae i giorni disponibili dalla configurazione del servizio.
     *
     * @param array<string, mixed> $generalSettings
     * @param array<int, array<string, mixed>> $meals
     * @return array<string>
     */
    private function extractAvailableDays(array $generalSettings, array $meals): array
    {
        $dayMapping = [
            'mon' => '1',
            'tue' => '2',
            'wed' => '3',
            'thu' => '4',
            'fri' => '5',
            'sat' => '6',
            'sun' => '0',
        ];

        $availableDays = [];

        // Se ci sono meal configurati, estrai i giorni da ciascun meal
        if ($meals !== []) {
            foreach ($meals as $meal) {
                if (!empty($meal['hours_definition'])) {
                    $days = $this->parseDaysFromSchedule((string) $meal['hours_definition']);
                    $availableDays = array_merge($availableDays, $days);
                }
            }
        }

        // Se non ci sono giorni dai meal, usa il service_hours_definition generale
        if ($availableDays === [] && !empty($generalSettings['service_hours_definition'])) {
            $availableDays = $this->parseDaysFromSchedule((string) $generalSettings['service_hours_definition']);
        }

        // Rimuovi duplicati e ordina
        $availableDays = array_unique($availableDays);
        sort($availableDays);

        // Converti i giorni in numeri ISO (0=domenica, 1=lunedì, ecc.)
        $dayNumbers = [];
        foreach ($availableDays as $day) {
            if (isset($dayMapping[$day])) {
                $dayNumbers[] = $dayMapping[$day];
            }
        }

        return $dayNumbers;
    }

    /**
     * Estrae i giorni dalla definizione dello schedule.
     *
     * @param string $scheduleDefinition
     * @return array<string>
     */
    private function parseDaysFromSchedule(string $scheduleDefinition): array
    {
        $days = [];
        $lines = preg_split('/\n/', $scheduleDefinition) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$day] = explode('=', $line, 2);
            $day = strtolower(trim($day));

            if ($day !== '') {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Arricchisce ogni meal con i giorni disponibili specifici.
     *
     * @param array<int, array<string, mixed>> $meals
     * @param array<string, mixed> $generalSettings
     * @return array<int, array<string, mixed>>
     */
    private function enrichMealsWithAvailableDays(array $meals, array $generalSettings): array
    {
        $dayMapping = [
            'mon' => '1',
            'tue' => '2',
            'wed' => '3',
            'thu' => '4',
            'fri' => '5',
            'sat' => '6',
            'sun' => '0',
        ];

        foreach ($meals as $index => $meal) {
            $days = [];

            // Se il meal ha hours_definition specifico, usalo
            if (!empty($meal['hours_definition'])) {
                $days = $this->parseDaysFromSchedule((string) $meal['hours_definition']);
            }
            // Altrimenti usa service_hours_definition generale come fallback
            elseif (!empty($generalSettings['service_hours_definition'])) {
                $days = $this->parseDaysFromSchedule((string) $generalSettings['service_hours_definition']);
            }

            // Converti i giorni in numeri ISO
            $dayNumbers = [];
            foreach ($days as $day) {
                if (isset($dayMapping[$day])) {
                    $dayNumbers[] = $dayMapping[$day];
                }
            }

            // Aggiungi i giorni disponibili al meal
            $meals[$index]['available_days'] = $dayNumbers;
        }

        return $meals;
    }
}

