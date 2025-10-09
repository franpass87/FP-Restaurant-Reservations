<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use DateTimeImmutable;
use DateTimeZone;
use function apply_filters;
use function array_key_exists;
use function array_map;
use function array_unique;
use function determine_locale;
use function in_array;
use function explode;
use function function_exists;
use function get_locale;
use function is_array;
use function is_string;
use function preg_split;
use function sanitize_key;
use function str_replace;
use function substr;
use function sprintf;
use function strtolower;
use function trim;
use function wp_parse_args;

final class Language
{
    private const STRINGS_FILTER = 'fp_resv_language_strings';

    public function __construct(private readonly Options $options)
    {
    }

    public function getFallbackLocale(): string
    {
        $settings = $this->options->getGroup('fp_resv_language', [
            'language_fallback_locale' => 'it_IT',
        ]);

        return $this->normalizeLocale((string) ($settings['language_fallback_locale'] ?? 'it_IT'));
    }

    public function getDefaultLanguage(): string
    {
        return $this->languageFromLocale($this->getFallbackLocale());
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedLocales(): array
    {
        $settings = $this->options->getGroup('fp_resv_language', [
            'language_supported_locales' => "it_IT\nen_US",
        ]);

        return $this->parseLocaleList($settings['language_supported_locales'] ?? '', $this->getFallbackLocale());
    }

    /**
     * @param array<string, mixed> $hints
     *
     * @return array{locale: string, language: string, source: string}
     */
    public function detect(array $hints = []): array
    {
        $supported = $this->getSupportedLocales();
        $fallback  = $this->getFallbackLocale();

        // Rileva la lingua corrente da WPML
        // ICL_LANGUAGE_CODE è la costante più affidabile fornita da WPML per ottenere la lingua corrente.
        // Se non è disponibile, usa il filtro wpml_current_language come fallback.
        $wpmlLanguage = null;
        if (defined('ICL_LANGUAGE_CODE')) {
            $wpmlLanguage = ICL_LANGUAGE_CODE;
        } elseif (apply_filters('wpml_current_language', null) !== null) {
            $wpmlLanguage = apply_filters('wpml_current_language', null);
        }

        $candidates = [
            ['value' => $hints['lang'] ?? '', 'source' => 'manual'],
            ['value' => $hints['locale'] ?? '', 'source' => 'attribute'],
            ['value' => apply_filters('fp_resv_language_hint', null, $hints), 'source' => 'filter'],
            ['value' => $wpmlLanguage, 'source' => 'wpml'],
            ['value' => function_exists('pll_current_language') ? \pll_current_language('slug') : null, 'source' => 'polylang'],
        ];

        foreach ($candidates as $candidate) {
            $match = $this->matchLanguage($candidate['value'], $supported);
            if ($match !== null) {
                $match['source'] = $candidate['source'];

                return $match;
            }
        }

        $wpLocale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $match    = $this->matchLanguage($wpLocale, $supported);
        if ($match !== null) {
            $match['source'] = 'wordpress';

            return $match;
        }

        $fallbackMatch = $this->matchLanguage($fallback, $supported);
        if ($fallbackMatch !== null) {
            $fallbackMatch['source'] = 'fallback';

            return $fallbackMatch;
        }

        $first = $supported[0] ?? $fallback;

        return [
            'locale'  => $this->normalizeLocale($first),
            'language'=> $this->languageFromLocale($first),
            'source'  => 'default',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStrings(string $language): array
    {
        $language = $this->ensureLanguage($language);
        $dictionary = $this->baseDictionary();

        $strings = $dictionary[$language] ?? $dictionary['it'];
        $filtered = apply_filters(self::STRINGS_FILTER, $strings, $language);
        if (is_array($filtered)) {
            $strings = $filtered;
        }

        return $strings;
    }

    public function ensureLanguage(string $language): string
    {
        $language = sanitize_key($language);
        if ($language === '') {
            return $this->getDefaultLanguage();
        }

        $supported = array_map([$this, 'languageFromLocale'], $this->getSupportedLocales());
        if (!in_array($language, $supported, true)) {
            return $this->getDefaultLanguage();
        }

        return $language;
    }

    public function normalizeLocale(string $locale): string
    {
        $locale = str_replace('-', '_', trim($locale));
        $parts  = explode('_', $locale);

        if ($parts === []) {
            return 'it_IT';
        }

        $primary = strtolower($parts[0]);
        $region  = $parts[1] ?? '';

        if ($region !== '') {
            return $primary . '_' . strtoupper($region);
        }

        return $primary;
    }

    public function languageFromLocale(string $locale): string
    {
        $locale = $this->normalizeLocale($locale);
        $parts  = explode('_', $locale);

        return sanitize_key($parts[0] ?? $locale);
    }

    public function formatDate(string $date, string $language): string
    {
        $language = $this->ensureLanguage($language);
        $formats  = $this->getFormats($language);

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', trim($date))
            ?: DateTimeImmutable::createFromFormat('d/m/Y', trim($date));

        if (!$dateTime instanceof DateTimeImmutable) {
            return $date;
        }

        return $dateTime->format($formats['date']);
    }

    public function formatTime(string $time, string $language): string
    {
        $language = $this->ensureLanguage($language);
        $formats  = $this->getFormats($language);

        $normalized = substr(trim($time), 0, 5);
        $dateTime   = DateTimeImmutable::createFromFormat('H:i', $normalized)
            ?: DateTimeImmutable::createFromFormat('H:i:s', trim($time));

        if (!$dateTime instanceof DateTimeImmutable) {
            return $time;
        }

        return $dateTime->format($formats['time']);
    }

    public function formatDateTime(string $date, string $time, string $language, ?string $timezone = null): string
    {
        $language = $this->ensureLanguage($language);
        $formats  = $this->getFormats($language);

        $normalizedTime = substr(trim($time), 0, 5);
        $dateTime       = DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($date) . ' ' . $normalizedTime);

        if (!$dateTime instanceof DateTimeImmutable) {
            return trim($date . ' ' . $time);
        }

        if ($timezone !== null && $timezone !== '') {
            try {
                $tz = new DateTimeZone($timezone);
                $dateTime = $dateTime->setTimezone($tz);
            } catch (\Exception $exception) {
                unset($exception);
            }
        }

        return $dateTime->format($formats['datetime']);
    }

    public function formatDateTimeObject(DateTimeImmutable $dateTime, string $language, ?string $timezone = null): string
    {
        $language = $this->ensureLanguage($language);
        $formats  = $this->getFormats($language);

        if ($timezone !== null && $timezone !== '') {
            try {
                $tz = new DateTimeZone($timezone);
                $dateTime = $dateTime->setTimezone($tz);
            } catch (\Exception $exception) {
                unset($exception);
            }
        }

        return $dateTime->format($formats['datetime']);
    }

    public function statusLabel(string $status, string $language): string
    {
        $language = $this->ensureLanguage($language);
        $strings  = $this->getStrings($language);
        $statuses = $strings['statuses'] ?? [];

        if (is_array($statuses) && array_key_exists($status, $statuses)) {
            return (string) $statuses[$status];
        }

        return $status;
    }

    /**
     * @param mixed $value
     * @param array<int, string> $supported
     *
     * @return array{locale: string, language: string}|null
     */
    private function matchLanguage(mixed $value, array $supported): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $normalized = $this->normalizeLocale($value);
        $slug       = $this->languageFromLocale($normalized);

        foreach ($supported as $candidate) {
            $candidateLocale = $this->normalizeLocale($candidate);
            if ($candidateLocale === $normalized || $this->languageFromLocale($candidateLocale) === $slug) {
                return [
                    'locale'   => $candidateLocale,
                    'language' => $this->languageFromLocale($candidateLocale),
                ];
            }
        }

        return null;
    }

    /**
     * @param mixed $raw
     *
     * @return array<int, string>
     */
    private function parseLocaleList(mixed $raw, string $fallback): array
    {
        $candidates = [];
        if (is_array($raw)) {
            $candidates = $raw;
        } elseif (is_string($raw)) {
            $candidates = preg_split('/[\n,;]+/', $raw) ?: [];
        }

        $locales = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $locales[] = $this->normalizeLocale($candidate);
        }

        if ($locales === []) {
            $locales[] = $this->normalizeLocale($fallback);
        }

        return array_values(array_unique($locales));
    }

    /**
     * @return array<string, string>
     */
    private function getFormats(string $language): array
    {
        $dictionary = $this->baseDictionary();
        $language   = $language !== '' && array_key_exists($language, $dictionary) ? $language : 'it';

        $formats = $dictionary[$language]['formats'] ?? $dictionary['it']['formats'];

        return wp_parse_args($formats, [
            'date'     => 'Y-m-d',
            'time'     => 'H:i',
            'datetime' => 'Y-m-d H:i',
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function baseDictionary(): array
    {
        return [
            'it' => [
                'formats'  => [
                    'date'     => 'd/m/Y',
                    'time'     => 'H:i',
                    'datetime' => 'd/m/Y H:i',
                ],
                'statuses' => [
                    'pending'          => 'In attesa di conferma',
                    'pending_payment'  => 'In attesa di pagamento',
                    'confirmed'        => 'Confermata',
                    'waitlist'         => "Lista d'attesa",
                    'cancelled'        => 'Annullata',
                    'no-show'          => 'No-show',
                    'visited'          => 'Completata',
                ],
                'form' => [
                    'headline' => [
                        'default'   => 'Prenota il tuo tavolo',
                        'with_name' => 'Prenota da %s',
                    ],
                    'subheadline' => 'Completa i passaggi per inviare la tua richiesta di prenotazione.',
                    'pdf_label'   => 'Scarica PDF',
                    'pdf_tooltip' => 'Apri il menu o la brochure in una nuova scheda.',
                    'steps_labels' => [
                        'date'    => 'Data',
                        'party'   => 'Persone',
                        'slots'   => 'Orari disponibili',
                        'details' => 'Dati di contatto',
                        'confirm' => 'Conferma',
                    ],
                    'step_order' => ['date', 'party', 'slots', 'details', 'confirm'],
                    'step_content' => [
                        'date' => [
                            'title'       => 'Scegli la data',
                            'description' => 'Indica il giorno preferito per la prenotazione.',
                        ],
                        'party' => [
                            'title'       => 'Quante persone',
                            'description' => 'Specificaci il numero di ospiti e necessità particolari.',
                        ],
                        'slots' => [
                            'title'       => "Scegli l'orario",
                            'description' => 'Seleziona una fascia oraria tra quelle disponibili.',
                        ],
                        'details' => [
                            'title'       => 'I tuoi dati',
                            'description' => 'Inserisci le informazioni per contattarti e confermare.',
                        ],
                        'confirm' => [
                            'title'       => 'Riepilogo finale',
                            'description' => 'Controlla i dati e invia la richiesta.',
                        ],
                    ],
                    'fields' => [
                        'date'       => 'Data',
                        'time'       => 'Orario preferito',
                        'party'      => 'Numero di persone',
                        'first_name' => 'Nome',
                        'last_name'  => 'Cognome',
                        'email'      => 'Email',
                        'phone'      => 'Telefono',
                        'phone_prefix' => 'Prefisso',
                        'notes'      => 'Note',
                        'allergies'  => 'Allergie o intolleranze',
                        'consent'    => "Acconsento al trattamento dei dati secondo l'informativa privacy.",
                    ],
                    'meals' => [
                        'title'    => 'Scegli il servizio',
                        'subtitle' => '',
                    ],
                    'actions' => [
                        'next'     => 'Continua',
                        'previous' => 'Indietro',
                        'submit'   => 'Invia prenotazione',
                    ],
                    'summary' => [
                        'title'      => 'Riepilogo prenotazione',
                        'edit'       => 'Modifica',
                        'disclaimer' => "Ti invieremo un'email di conferma appena la prenotazione sarà processata.",
                        'labels'     => [
                            'date'    => 'Data',
                            'time'    => 'Orario',
                            'party'   => 'Coperti',
                            'name'    => 'Cliente',
                            'contact' => 'Contatti',
                            'notes'   => 'Note',
                            'extras'  => 'Richieste aggiuntive',
                        ],
                    ],
                    'extras' => [
                        'title'            => 'Richieste aggiuntive',
                        'high_chair'       => 'Quanti seggioloni servono?',
                        'wheelchair_table' => 'Serve un tavolo accessibile per sedia a rotelle',
                        'pets'             => 'Vengo con un animale domestico',
                    ],
                    'messages' => [
                        'slots_loading' => 'Caricamento disponibilità...',
                        'slots_empty'   => 'Nessun servizio disponibile, scegli un altro giorno.',
                        'cta_complete_fields' => 'Completa i campi richiesti',
                        'cta_book_now'        => 'Prenota ora',
                        'cta_sending'         => 'Invio…',
                        'submit_hint'         => 'Completa tutti i passaggi per prenotare.',
                        'submit_tooltip'      => 'Completa i campi obbligatori per abilitare la prenotazione.',
                        'msg_updating_slots'  => 'Aggiornamento disponibilità…',
                        'msg_slots_updated'   => 'Disponibilità aggiornata.',
                        'msg_slots_error'     => 'Impossibile aggiornare la disponibilità. Riprova tra qualche istante.',
                        'msg_select_meal'     => 'Seleziona un servizio per visualizzare gli orari disponibili.',
                        'msg_invalid_phone'   => 'Inserisci un numero di telefono valido (minimo 6 cifre).',
                        'msg_invalid_email'   => 'Inserisci un indirizzo email valido.',
                        'msg_submit_error'    => 'Non è stato possibile completare la prenotazione. Riprova.',
                        'msg_submit_success'  => 'Prenotazione inviata con successo.',
                        'meal_full_notice'    => 'Nessuna disponibilità per questo servizio. Scegli un altro giorno.',
                    ],
                    'consents_meta' => [
                        'required' => 'Obbligatorio',
                        'optional' => 'Opzionale',
                    ],
                    'consents' => [
                        'policy_link' => 'informativa privacy',
                        'marketing'   => 'Acconsento a ricevere comunicazioni promozionali.',
                        'profiling'   => 'Acconsento alla personalizzazione delle offerte in base alle mie preferenze.',
                    ],
                ],
                'emails' => [
                    'customer' => [
                        'subject' => 'La tua prenotazione per %s',
                        'intro'   => 'Ciao %1$s %2$s,',
                        'body'    => 'grazie per aver prenotato per %1$d persone il %2$s alle %3$s.',
                        'status'  => 'Stato prenotazione: %s.',
                        'manage'  => 'Puoi gestire o annullare la prenotazione da qui: %s',
                        'outro'   => 'Se hai bisogno di assistenza rispondi a questa email.',
                    ],
                    'confirmation' => [
                        'subject' => 'La tua prenotazione per {{reservation.formatted_date}}',
                        'body'    => "Ciao {{customer.first_name}} {{customer.last_name}},\nGrazie per aver prenotato per {{reservation.party}} persone il {{reservation.formatted_date}} alle {{reservation.formatted_time}}.\nStato prenotazione: {{reservation.status_label}}.\n\nPuoi gestire o annullare la prenotazione da qui: {{reservation.manage_link}}.\n\nSe hai bisogno di assistenza rispondi a questa email.",
                    ],
                    'reminder' => [
                        'subject' => 'Promemoria: prenotazione del {{reservation.formatted_date}} alle {{reservation.formatted_time}}',
                        'body'    => "Ciao {{customer.first_name}} {{customer.last_name}},\nTi aspettiamo il {{reservation.formatted_date}} alle {{reservation.formatted_time}} per {{reservation.party}} persone.\nSe hai bisogno di modificare la prenotazione puoi farlo qui: {{reservation.manage_link}}.",
                    ],
                    'review' => [
                        'subject' => 'Com\'è andata la tua visita da {{restaurant.name}}?',
                        'body'    => "Ciao {{customer.first_name}} {{customer.last_name}},\nGrazie per averci fatto visita. Raccontaci com\'è andata lasciando una recensione: {{review.link}}.\nIl tuo feedback è prezioso per noi!",
                    ],
                    'placeholders' => [
                        'manage_link' => 'Gestisci prenotazione',
                        'review_link' => 'Lascia una recensione',
                    ],
                    'layout' => [
                        'header' => '<div style="text-align:center;"><div style="margin-bottom:16px;">{{restaurant.logo_img}}</div><p style="margin:0;font-size:20px;font-weight:600;color:#111827;">{{restaurant.name}}</p></div>',
                        'footer' => '<p style="margin:0;text-align:center;font-size:12px;color:#6b7280;">© {{emails.year}} {{restaurant.name}} · Tutti i diritti riservati.</p><p style="margin:8px 0 0;text-align:center;font-size:12px;color:#9ca3af;">Hai ricevuto questa email perché hai una prenotazione attiva presso il nostro ristorante.</p>',
                    ],
                    'staff' => [
                        'restaurant_subject' => 'Nuova prenotazione #%1$d - %2$s',
                        'webmaster_subject'  => 'Copia webmaster prenotazione #%1$d - %2$s',
                        'headline_restaurant'=> 'Nuova prenotazione ricevuta',
                        'headline_webmaster' => 'Copia notifica prenotazione',
                        'lead_restaurant'    => 'Il ristorante %s ha ricevuto una nuova prenotazione.',
                        'lead_webmaster'     => 'Il sistema ha registrato una prenotazione per %s.',
                        'labels' => [
                            'reservation_id' => 'ID prenotazione',
                            'date_time'      => 'Data e ora',
                            'party'          => 'Coperti',
                            'customer'       => 'Cliente',
                            'phone'          => 'Telefono',
                            'location'       => 'Sede / Location',
                            'notes'          => 'Note',
                            'allergies'      => 'Allergie',
                            'status'         => 'Stato',
                            'received_at'    => 'Ricevuta il',
                            'recorded_at'    => 'Registrata il',
                            'utm'            => 'Attribution / UTM',
                            'utm_source'     => 'Sorgente: %s',
                            'utm_medium'     => 'Mezzo: %s',
                            'utm_campaign'   => 'Campagna: %s',
                            'manage'         => 'Gestisci prenotazione',
                            'open'           => 'Apri la scheda prenotazione',
                        ],
                        'fallback' => [
                            'reservation' => 'Prenotazione #%d',
                            'date_time'   => 'Data: %s alle %s',
                            'party'       => 'Coperti: %d',
                            'customer'    => 'Cliente: %s %s',
                            'email'       => 'Email: %s',
                            'phone'       => 'Telefono: %s',
                            'notes'       => 'Note: %s',
                            'allergies'   => 'Allergie: %s',
                            'manage'      => 'Gestione: %s',
                        ],
                    ],
                ],
                'survey' => [
                    'labels' => [
                        'headline'            => "Com'è andata la tua esperienza?",
                        'food'                => 'Cibo',
                        'service'             => 'Servizio',
                        'atmosphere'          => 'Atmosfera',
                        'nps'                 => 'Quanto consiglieresti il ristorante ad amici o colleghi? (0-10)',
                        'comment'             => 'Note aggiuntive',
                        'comment_placeholder' => "Raccontaci di più...",
                    ],
                    'actions' => [
                        'submit' => 'Invia feedback',
                    ],
                    'positive' => [
                        'headline' => 'Grazie per il tuo feedback!',
                        'body'     => "Siamo felici che la tua esperienza sia stata all'altezza delle aspettative.",
                        'cta'      => 'Lascia una recensione su Google',
                        'message'  => 'Grazie per aver condiviso la tua esperienza!',
                    ],
                    'negative' => [
                        'headline' => 'Grazie per il tuo feedback',
                        'body'     => 'Ci dispiace che qualcosa non sia andato per il meglio: il nostro staff ti ricontatterà al più presto.',
                        'message'  => 'Grazie per il tuo feedback, il nostro staff ti ricontatterà al più presto.',
                    ],
                ],
            ],
            'en' => [
                'formats'  => [
                    'date'     => 'm/d/Y',
                    'time'     => 'g:i A',
                    'datetime' => 'm/d/Y g:i A',
                ],
                'statuses' => [
                    'pending'          => 'Pending confirmation',
                    'pending_payment'  => 'Awaiting payment',
                    'confirmed'        => 'Confirmed',
                    'waitlist'         => 'Waitlist',
                    'cancelled'        => 'Cancelled',
                    'no-show'          => 'No-show',
                    'visited'          => 'Completed',
                ],
                'form' => [
                    'headline' => [
                        'default'   => 'Book your table',
                        'with_name' => 'Book at %s',
                    ],
                    'subheadline' => 'Complete the steps to send your reservation request.',
                    'pdf_label'   => 'Download PDF',
                    'pdf_tooltip' => 'Open the menu or brochure in a new tab.',
                    'steps_labels' => [
                        'date'    => 'Date',
                        'party'   => 'Guests',
                        'slots'   => 'Available times',
                        'details' => 'Contact details',
                        'confirm' => 'Confirm',
                    ],
                    'step_order' => ['date', 'party', 'slots', 'details', 'confirm'],
                    'step_content' => [
                        'date' => [
                            'title'       => 'Choose the date',
                            'description' => 'Select the day you prefer for your reservation.',
                        ],
                        'party' => [
                            'title'       => 'How many people',
                            'description' => 'Tell us the party size and any special requirements.',
                        ],
                        'slots' => [
                            'title'       => 'Pick a time',
                            'description' => 'Choose one of the available time slots.',
                        ],
                        'details' => [
                            'title'       => 'Your details',
                            'description' => 'Provide your contact information for confirmation.',
                        ],
                        'confirm' => [
                            'title'       => 'Final review',
                            'description' => 'Double-check the information and send your request.',
                        ],
                    ],
                    'fields' => [
                        'date'       => 'Date',
                        'time'       => 'Preferred time',
                        'party'      => 'Number of guests',
                        'first_name' => 'First name',
                        'last_name'  => 'Last name',
                        'email'      => 'Email',
                        'phone'      => 'Phone',
                        'phone_prefix' => 'Prefix',
                        'notes'      => 'Notes',
                        'allergies'  => 'Allergies or dietary needs',
                        'consent'    => 'I consent to the processing of my data according to the privacy policy.',
                    ],
                    'meals' => [
                        'title'    => 'Choose your service',
                        'subtitle' => '',
                    ],
                    'actions' => [
                        'next'     => 'Continue',
                        'previous' => 'Back',
                        'submit'   => 'Send reservation',
                    ],
                    'summary' => [
                        'title'      => 'Reservation summary',
                        'edit'       => 'Edit',
                        'disclaimer' => 'We will email you as soon as the reservation is processed.',
                        'labels'     => [
                            'date'    => 'Date',
                            'time'    => 'Time',
                            'party'   => 'Guests',
                            'name'    => 'Customer',
                            'contact' => 'Contact',
                            'notes'   => 'Notes',
                            'extras'  => 'Additional requests',
                        ],
                    ],
                    'extras' => [
                        'title'            => 'Additional requests',
                        'high_chair'       => 'How many high chairs are needed?',
                        'wheelchair_table' => 'Need a wheelchair-accessible table',
                        'pets'             => 'I will bring a pet',
                    ],
                    'messages' => [
                        'slots_loading' => 'Loading availability...',
                        'slots_empty'   => 'No service available, choose another day.',
                        'cta_complete_fields' => 'Complete required fields',
                        'cta_book_now'        => 'Book now',
                        'cta_sending'         => 'Sending…',
                        'submit_hint'         => 'Complete all steps to book.',
                        'submit_tooltip'      => 'Complete required fields to enable booking.',
                        'msg_updating_slots'  => 'Updating availability…',
                        'msg_slots_updated'   => 'Availability updated.',
                        'msg_slots_error'     => 'We could not update availability. Please try again in a moment.',
                        'msg_select_meal'     => 'Select a service to view available times.',
                        'msg_invalid_phone'   => 'Enter a valid phone number (minimum 6 digits).',
                        'msg_invalid_email'   => 'Enter a valid email address.',
                        'msg_submit_error'    => 'We could not complete your reservation. Please try again.',
                        'msg_submit_success'  => 'Reservation sent successfully.',
                        'meal_full_notice'    => 'No availability for this service. Please choose another day.',
                    ],
                    'consents_meta' => [
                        'required' => 'Required',
                        'optional' => 'Optional',
                    ],
                    'consents' => [
                        'policy_link' => 'privacy policy',
                        'marketing'   => 'I agree to receive promotional communications.',
                        'profiling'   => 'I agree to personalised offers based on my preferences.',
                    ],
                ],
                'emails' => [
                    'customer' => [
                        'subject' => 'Your reservation for %s',
                        'intro'   => 'Hi %1$s %2$s,',
                        'body'    => 'thank you for booking a table for %1$d guests on %2$s at %3$s.',
                        'status'  => 'Reservation status: %s.',
                        'manage'  => 'You can manage or cancel your reservation here: %s',
                        'outro'   => 'Reply to this email if you need assistance.',
                    ],
                    'confirmation' => [
                        'subject' => 'Your reservation for {{reservation.formatted_date}}',
                        'body'    => "Hi {{customer.first_name}} {{customer.last_name}},\nThanks for booking a table for {{reservation.party}} guests on {{reservation.formatted_date}} at {{reservation.formatted_time}}.\nReservation status: {{reservation.status_label}}.\n\nYou can manage or cancel your reservation here: {{reservation.manage_link}}.\n\nReply to this email if you need assistance.",
                    ],
                    'reminder' => [
                        'subject' => 'Reminder: reservation on {{reservation.formatted_date}} at {{reservation.formatted_time}}',
                        'body'    => "Hi {{customer.first_name}} {{customer.last_name}},\nWe look forward to seeing you on {{reservation.formatted_date}} at {{reservation.formatted_time}} for {{reservation.party}} guests.\nNeed to change something? Use this link: {{reservation.manage_link}}.",
                    ],
                    'review' => [
                        'subject' => 'How was your visit at {{restaurant.name}}?',
                        'body'    => "Hi {{customer.first_name}} {{customer.last_name}},\nThanks for dining with us. Share your experience with a quick review: {{review.link}}.\nYour feedback means a lot to us!",
                    ],
                    'placeholders' => [
                        'manage_link' => 'Manage reservation',
                        'review_link' => 'Leave a review',
                    ],
                    'layout' => [
                        'header' => '<div style="text-align:center;"><div style="margin-bottom:16px;">{{restaurant.logo_img}}</div><p style="margin:0;font-size:20px;font-weight:600;color:#111827;">{{restaurant.name}}</p></div>',
                        'footer' => '<p style="margin:0;text-align:center;font-size:12px;color:#6b7280;">© {{emails.year}} {{restaurant.name}}. All rights reserved.</p><p style="margin:8px 0 0;text-align:center;font-size:12px;color:#9ca3af;">You received this email because you have an active reservation with us.</p>',
                    ],
                    'staff' => [
                        'restaurant_subject' => 'New reservation #%1$d - %2$s',
                        'webmaster_subject'  => 'Reservation copy #%1$d - %2$s',
                        'headline_restaurant'=> 'New reservation received',
                        'headline_webmaster' => 'Reservation notification copy',
                        'lead_restaurant'    => '%s just received a new reservation.',
                        'lead_webmaster'     => 'A new reservation has been recorded for %s.',
                        'labels' => [
                            'reservation_id' => 'Reservation ID',
                            'date_time'      => 'Date & time',
                            'party'          => 'Guests',
                            'customer'       => 'Customer',
                            'phone'          => 'Phone',
                            'location'       => 'Location',
                            'notes'          => 'Notes',
                            'allergies'      => 'Allergies',
                            'status'         => 'Status',
                            'received_at'    => 'Received on',
                            'recorded_at'    => 'Logged on',
                            'utm'            => 'Attribution / UTM',
                            'utm_source'     => 'Source: %s',
                            'utm_medium'     => 'Medium: %s',
                            'utm_campaign'   => 'Campaign: %s',
                            'manage'         => 'Manage reservation',
                            'open'           => 'Open reservation',
                        ],
                        'fallback' => [
                            'reservation' => 'Reservation #%d',
                            'date_time'   => 'Date: %s at %s',
                            'party'       => 'Guests: %d',
                            'customer'    => 'Customer: %s %s',
                            'email'       => 'Email: %s',
                            'phone'       => 'Phone: %s',
                            'notes'       => 'Notes: %s',
                            'allergies'   => 'Allergies: %s',
                            'manage'      => 'Manage: %s',
                        ],
                    ],
                ],
                'survey' => [
                    'labels' => [
                        'headline'            => 'How was your experience?',
                        'food'                => 'Food',
                        'service'             => 'Service',
                        'atmosphere'          => 'Atmosphere',
                        'nps'                 => 'How likely are you to recommend us to a friend or colleague? (0-10)',
                        'comment'             => 'Additional notes',
                        'comment_placeholder' => 'Tell us more...',
                    ],
                    'actions' => [
                        'submit' => 'Send feedback',
                    ],
                    'positive' => [
                        'headline' => 'Thank you for your feedback!',
                        'body'     => "We're glad your experience lived up to expectations.",
                        'cta'      => 'Leave a Google review',
                        'message'  => 'Thanks for sharing your experience!',
                    ],
                    'negative' => [
                        'headline' => 'Thank you for your feedback',
                        'body'     => "We're sorry something went wrong — our team will get back to you shortly.",
                        'message'  => 'Thank you for your feedback, our team will get back to you shortly.',
                    ],
                ],
            ],
        ];
    }
}

