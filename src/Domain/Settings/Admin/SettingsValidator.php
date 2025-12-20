<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings\Admin;

use function __;
use function add_settings_error;
use function filter_var;
use function in_array;
use function preg_match;
use function sprintf;
use function strlen;
use function timezone_identifiers_list;
use const FILTER_VALIDATE_URL;

/**
 * Gestisce la validazione delle opzioni delle impostazioni.
 * Estratto da AdminPages.php per migliorare modularità.
 */
final class SettingsValidator
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $pages;

    private SettingsSanitizer $sanitizer;

    public function __construct(SettingsSanitizer $sanitizer)
    {
        $this->pages = \FP\Resv\Domain\Settings\PagesConfig::getPages();
        $this->sanitizer = $sanitizer;
    }

    public function validatePage(string $pageKey, array $options): void
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
                    $this->sanitizer->validateMealPlanDefinition((string) $options['frontend_meals']);
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
                    if (!empty($options['brevo_review_place_id']) && !$this->sanitizer->isValidPlaceId((string) $options['brevo_review_place_id'])) {
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
                if (!empty($options['ga4_api_secret']) && strlen((string) $options['ga4_api_secret']) < 20) {
                    $this->addError(
                        $pageKey,
                        'invalid_ga4_api_secret',
                        __('Il GA4 API Secret sembra non essere valido. Verifica di aver copiato correttamente il token.', 'fp-restaurant-reservations')
                    );
                }
                if (!empty($options['meta_access_token']) && strlen((string) $options['meta_access_token']) < 50) {
                    $this->addError(
                        $pageKey,
                        'invalid_meta_access_token',
                        __('Il Meta Access Token sembra non essere valido. Verifica di aver copiato correttamente il token.', 'fp-restaurant-reservations')
                    );
                }
                break;
        }
    }

    private function addError(string $pageKey, string $code, string $message): void
    {
        $page = $this->pages[$pageKey] ?? null;
        if ($page === null) {
            return;
        }

        add_settings_error((string) $page['option_name'], $code, $message, 'error');
    }
}
















