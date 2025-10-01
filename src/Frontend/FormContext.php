<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Settings\Style;
use function apply_filters;
use function array_key_exists;
use function array_keys;
use function array_map;
use function explode;
use function esc_url_raw;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_split;
use function preg_replace;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
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
        $rawMeals       = $this->parseMealDefinitions($mealDefinition);

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

        $meals = $this->normalizeMealList(apply_filters('fp_resv_form_meals', $rawMeals, $config));
        if ($meals !== []) {
            $defaultMeal = $this->getDefaultMealKey($meals);
            if ($defaultMeal !== '') {
                $config['defaults']['meal'] = $defaultMeal;
            }
        }

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseMealDefinitions(string $definition): array
    {
        if ($definition === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\r|\n/", $definition);
        if (!is_array($lines)) {
            return [];
        }

        $meals = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if ($parts === []) {
                continue;
            }

            $rawKey = array_shift($parts);
            if (!is_string($rawKey) || $rawKey === '') {
                continue;
            }

            $isActive = false;
            if (str_starts_with($rawKey, '*')) {
                $isActive = true;
                $rawKey   = ltrim($rawKey, '* ');
            }

            if ($rawKey === '') {
                continue;
            }

            $entry = [
                'key'   => $rawKey,
                'label' => $parts[0] ?? '',
            ];

            if (isset($parts[1])) {
                $entry['hint'] = $parts[1];
            }
            if (isset($parts[2])) {
                $entry['notice'] = $parts[2];
            }
            if (isset($parts[3])) {
                $entry['price'] = $parts[3];
            }
            if (isset($parts[4])) {
                $entry['badge'] = $parts[4];
            }
            if (isset($parts[5])) {
                $entry['badge_icon'] = $parts[5];
            }

            if ($isActive) {
                $entry['active'] = true;
            }

            $meals[] = $entry;
        }

        return $meals;
    }

    /**
     * @param mixed $value
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMealList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $meals          = [];
        $defaultAssigned = false;

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $key = isset($entry['key']) ? sanitize_key((string) $entry['key']) : '';
            if ($key === '') {
                continue;
            }

            $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
            if ($label === '') {
                $label = ucwords(str_replace(['_', '-'], ' ', $key));
            }

            $meal = [
                'key'   => $key,
                'label' => $label,
            ];

            if (isset($entry['hint'])) {
                $hint = sanitize_text_field((string) $entry['hint']);
                if ($hint !== '') {
                    $meal['hint'] = $hint;
                }
            }

            if (isset($entry['notice'])) {
                $notice = sanitize_text_field((string) $entry['notice']);
                if ($notice !== '') {
                    $meal['notice'] = $notice;
                }
            }

            if (isset($entry['badge'])) {
                $badge = sanitize_text_field((string) $entry['badge']);
                if ($badge !== '') {
                    $meal['badge'] = $badge;
                }
            }

            if (isset($entry['badge_icon'])) {
                $badgeIcon = sanitize_key((string) $entry['badge_icon']);
                if ($badgeIcon !== '') {
                    $meal['badge_icon'] = $badgeIcon;
                }
            }

            if (isset($entry['price'])) {
                $priceValue = $this->toFloat($entry['price']);
                if ($priceValue !== null) {
                    $meal['price'] = number_format($priceValue, 2, '.', '');
                }
            }

            if (!$defaultAssigned && !empty($entry['active'])) {
                $meal['active'] = true;
                $defaultAssigned = true;
            }

            $meals[] = $meal;
        }

        if ($meals === []) {
            return [];
        }

        if (!$defaultAssigned) {
            $meals[0]['active'] = true;
        }

        return array_values($meals);
    }

    /**
     * @param array<int, array<string, mixed>> $meals
     */
    private function getDefaultMealKey(array $meals): string
    {
        foreach ($meals as $meal) {
            if (!empty($meal['active']) && isset($meal['key'])) {
                return (string) $meal['key'];
            }
        }

        return '';
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '') {
            return null;
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
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
}

