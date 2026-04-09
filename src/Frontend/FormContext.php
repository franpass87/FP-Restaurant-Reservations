<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\MealPlan;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Settings\Style;
use FP\Resv\Domain\Settings\Style\ColorCalculator;
use FP\Resv\Domain\Settings\Style\ContrastReporter;
use FP\Resv\Domain\Settings\Style\StyleCssGenerator;
use FP\Resv\Domain\Settings\Style\StyleTokenBuilder;
use FP\Resv\Frontend\AvailableDaysExtractor;
use FP\Resv\Frontend\PhonePrefixes;
use FP\Resv\Frontend\PhonePrefixProcessor;
use FP\Resv\Integrations\FpPrivacyPolicyUrl;
use FP\Resv\Kernel\LegacyBridge;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
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
    public function __construct(
        Options $options,
        Language $language,
        PhonePrefixProcessor $phonePrefixProcessor,
        AvailableDaysExtractor $availableDaysExtractor,
        array $attributes = []
    ) {
        $this->options                = $options;
        $this->language               = $language;
        $this->phonePrefixProcessor   = $phonePrefixProcessor;
        $this->availableDaysExtractor  = $availableDaysExtractor;
        $this->attributes             = $attributes;
    }

    private PhonePrefixProcessor $phonePrefixProcessor;
    private AvailableDaysExtractor $availableDaysExtractor;

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
            'pdf_urls'                    => [],
        ];

        $languageDefaults = [
            'language_fallback_locale'   => 'it_IT',
            'language_supported_locales' => 'it_IT' . PHP_EOL . 'en_US',
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
        $customPrefixMap = $this->phonePrefixProcessor->parsePhonePrefixMap($brevoSettings['brevo_phone_prefix_map'] ?? null);
        $phonePrefixes   = $this->phonePrefixProcessor->mergePhonePrefixes(PhonePrefixes::getDefaults(), $customPrefixMap);
        $phonePrefixes   = $this->phonePrefixProcessor->condensePhonePrefixes($phonePrefixes);

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
        $meals = $this->filterMealsForBookingWindow($meals, $generalSettings);
        if ($meals !== []) {
            $defaultMeal = MealPlan::getDefaultKey($meals);
            if ($defaultMeal !== '') {
                $config['defaults']['meal'] = $defaultMeal;
            }
        }

        // Estrai i giorni disponibili dalla configurazione del servizio
        $availableDays = $this->availableDaysExtractor->extractAvailableDays($generalSettings, $meals);
        if ($availableDays !== []) {
            $config['available_days'] = $availableDays;
        }

        // Arricchisci ogni meal con i suoi giorni disponibili specifici
        $meals = $this->availableDaysExtractor->enrichMealsWithAvailableDays($meals, $generalSettings);
        
        // Aggiungi aperture speciali come meals temporanei
        if (class_exists(SpecialOpeningsProvider::class)) {
            try {
                $specialOpeningsProvider = new SpecialOpeningsProvider();
                $specialMeals = $specialOpeningsProvider->getSpecialOpeningsAsMeals();
                foreach ($specialMeals as $specialMeal) {
                    // Non sovrascrivere se esiste già un meal con la stessa chiave
                    $exists = false;
                    foreach ($meals as $existingMeal) {
                        if (($existingMeal['key'] ?? '') === ($specialMeal['key'] ?? '')) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $meals[] = $specialMeal;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore errors loading special openings
            }
        }

        $dictionary  = $this->language->getStrings($languageData['language']);
        $formStrings = is_array($dictionary['form'] ?? null) ? $dictionary['form'] : [];

        $strings = $this->buildStrings(
            $formStrings,
            (string) ($generalSettings['restaurant_name'] ?? '')
        );

        $policyUrl = trim((string) ($trackingSettings['privacy_policy_url'] ?? ''));
        if ($policyUrl === '') {
            $policyUrl = FpPrivacyPolicyUrl::resolveUrl(
                isset($languageData['locale']) ? (string) $languageData['locale'] : null
            );
        }

        $privacy = [
            'policy_url'        => esc_url_raw($policyUrl),
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
        $pdfSettings = $generalSettings;
        if (empty($pdfSettings['pdf_urls']) || !is_array($pdfSettings['pdf_urls'])) {
            $legacyPdf = $languageSettings['pdf_urls'] ?? [];
            if (is_array($legacyPdf) && $legacyPdf !== []) {
                $pdfSettings['pdf_urls'] = $legacyPdf;
            }
        }

        $pdfUrl = $this->resolvePdfUrl(
            $languageData['language'],
            $pdfSettings,
            $supportedLocales,
            $fallbackLocale
        );

        $styleService = $this->getStyleService();
        $stylePayload = $styleService->buildFrontend($config['formId']);

        $pdfMapKeys = [];
        if (isset($pdfSettings['pdf_urls']) && is_array($pdfSettings['pdf_urls'])) {
            $pdfMapKeys = array_keys($pdfSettings['pdf_urls']);
        }

        // booking_form_view è sparato da TrackingBridge via fp_resv_form_rendered.
        $dataLayer = [];

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

    /**
     * Esclude dal form i pasti la cui finestra date non interseca l'intervallo di prenotazione (min/max advance).
     *
     * @param array<int, array<string, mixed>> $meals
     * @param array<string, mixed> $generalSettings
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterMealsForBookingWindow(array $meals, array $generalSettings): array
    {
        if ($meals === []) {
            return [];
        }

        $tzName = $this->normalizeTimezone((string) ($generalSettings['restaurant_timezone'] ?? 'Europe/Rome'));
        try {
            $tz = new DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tz = new DateTimeZone('Europe/Rome');
        }

        $minAdvance = max(0, (int) ($generalSettings['min_advance_days'] ?? 0));
        $maxAdvance = max(1, (int) ($generalSettings['max_advance_days'] ?? 365));

        $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $windowStart = $today->add(new DateInterval('P' . $minAdvance . 'D'));
        $windowEnd   = $today->add(new DateInterval('P' . $maxAdvance . 'D'));

        $filtered = [];
        foreach ($meals as $meal) {
            if (!is_array($meal)) {
                continue;
            }
            if (MealPlan::mealOverlapsBookingWindow($meal, $windowStart, $windowEnd)) {
                $filtered[] = $meal;
            }
        }

        return $filtered;
    }

    private function toInt(mixed $value, int $fallback): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $fallback;
    }

    /**
     * Normalizza le chiavi PDF (es. IT/EN da salvataggio admin → it/en come nel form).
     *
     * @param array<mixed, mixed> $map
     *
     * @return array<string, string>
     */
    private function normalizePdfUrlMapKeys(array $map): array
    {
        $out = [];
        foreach ($map as $langKey => $url) {
            if (!is_string($langKey) && !is_int($langKey)) {
                continue;
            }
            $k = sanitize_key((string) $langKey);
            if ($k === '') {
                continue;
            }
            $out[$k] = is_scalar($url) ? (string) $url : '';
        }

        return $out;
    }

    /**
     * @param array<int, string> $supportedLocales
     */
    private function resolvePdfUrl(string $languageSlug, array $languageSettings, array $supportedLocales, string $fallbackLocale): string
    {
        $raw = $languageSettings['pdf_urls'] ?? [];
        if (!is_array($raw)) {
            return '';
        }

        $map = $this->normalizePdfUrlMapKeys($raw);

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
     * Ottiene il servizio Style dal container o crea un'istanza con le dipendenze.
     */
    private function getStyleService(): Style
    {
        $container = LegacyBridge::getContainer();
        
        // Prova a ottenere dal container
        if ($container && $container->has(Style::class)) {
            $style = $container->get(Style::class);
            if ($style instanceof Style) {
                return $style;
            }
        }
        
        // Se non disponibile nel container, crea le dipendenze manualmente
        $colorCalculator = new ColorCalculator();
        $tokenBuilder = new StyleTokenBuilder();
        $cssGenerator = new StyleCssGenerator();
        $contrastReporter = new ContrastReporter();
        
        return new Style(
            $this->options,
            $colorCalculator,
            $tokenBuilder,
            $cssGenerator,
            $contrastReporter
        );
    }

}

