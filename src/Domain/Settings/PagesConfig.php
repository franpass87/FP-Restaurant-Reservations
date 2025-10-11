<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use function __;
use function implode;
use function wp_json_encode;

/**
 * Definizione configurazione pagine delle impostazioni admin.
 * Estratto da AdminPages per migliorare la manutenibilità.
 */
final class PagesConfig
{
    private const DEFAULT_PHONE_PREFIX_MAP = ['+39' => 'IT'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getPages(): array
    {
        return [
            'general' => self::getGeneralPage(),
            'notifications' => self::getNotificationsPage(),
            'payments' => self::getPaymentsPage(),
            'brevo' => self::getBrevoPage(),
            'google-calendar' => self::getGoogleCalendarPage(),
            'style' => self::getStylePage(),
            'language' => self::getLanguagePage(),
            'closures' => self::getClosuresPage(),
            'tracking' => self::getTrackingPage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getGeneralPage(): array
    {
        return [
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
                    'fields'      => self::getGeneralDefaultsFields(),
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
                    'fields'      => self::getLayoutPreferencesFields(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getGeneralDefaultsFields(): array
    {
        return [
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
                'description'    => __('Se disabilitata, i link "Gestisci prenotazione" non mostreranno la pagina.', 'fp-restaurant-reservations'),
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
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getLayoutPreferencesFields(): array
    {
        return [
            'tables_enabled' => [
                'label'          => __('Abilita Sale & Tavoli', 'fp-restaurant-reservations'),
                'type'           => 'checkbox',
                'checkbox_label' => __('Attiva il planner sale e la gestione tavoli. Se disattivato, il sistema usa solo la capienza.', 'fp-restaurant-reservations'),
                'default'        => '0',
                'legacy_option'  => 'fp_resv_rooms',
            ],
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getNotificationsPage(): array
    {
        return [
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
                    'fields' => self::getNotificationPreferencesFields(),
                ],
                'notifications-customer' => [
                    'title'       => __('Email cliente', 'fp-restaurant-reservations'),
                    'description' => __('Configura conferme, promemoria e follow-up gestiti direttamente dal plugin.', 'fp-restaurant-reservations'),
                    'fields'      => self::getCustomerEmailFields(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getNotificationPreferencesFields(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getCustomerEmailFields(): array
    {
        return [
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
            // Reminder fields
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
            // Review fields
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getPaymentsPage(): array
    {
        return [
            'page_title'   => __('Pagamenti Stripe', 'fp-restaurant-reservations'),
            'menu_title'   => __('Pagamenti', 'fp-restaurant-reservations'),
            'slug'         => 'fp-resv-payments',
            'option_group' => 'fp_resv_payments',
            'option_name'  => 'fp_resv_payments',
            'sections'     => [
                'payments-stripe' => [
                    'title'       => __('Configurazione Stripe', 'fp-restaurant-reservations'),
                    'description' => __('I pagamenti sono facoltativi e disattivati di default.', 'fp-restaurant-reservations'),
                    'fields'      => self::getStripeFields(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getStripeFields(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getBrevoPage(): array
    {
        return [
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
                            'description' => __('Logica automatica: +39 → Lista IT, tutti gli altri prefissi → Lista EN. Campo disponibile per configurazioni personalizzate.', 'fp-restaurant-reservations'),
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getGoogleCalendarPage(): array
    {
        return [
            'page_title'   => __('Google Calendar', 'fp-restaurant-reservations'),
            'menu_title'   => __('Google Calendar', 'fp-restaurant-reservations'),
            'slug'         => 'fp-resv-google-calendar',
            'option_group' => 'fp_resv_google_calendar',
            'option_name'  => 'fp_resv_google_calendar',
            'sections'     => [
                'google-oauth' => [
                    'title'       => __('OAuth & calendario', 'fp-restaurant-reservations'),
                    'description' => __('Configura l\'app Google Cloud e seleziona il calendario di destinazione.', 'fp-restaurant-reservations'),
                    'fields'      => self::getGoogleCalendarFields(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getGoogleCalendarFields(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getStylePage(): array
    {
        return [
            'page_title'   => __('Stile del form', 'fp-restaurant-reservations'),
            'menu_title'   => __('Stile', 'fp-restaurant-reservations'),
            'slug'         => 'fp-resv-style',
            'option_group' => 'fp_resv_style',
            'option_name'  => 'fp_resv_style',
            'sections'     => [
                'style-foundations' => [
                    'title'       => __('Colori & superfici', 'fp-restaurant-reservations'),
                    'description' => __('Imposta palette, raggio, ombre e focus ring del widget.', 'fp-restaurant-reservations'),
                    'fields'      => self::getStyleFoundationFields(),
                ],
                'style-typography' => [
                    'title'       => __('Tipografia & gerarchie', 'fp-restaurant-reservations'),
                    'description' => __('Scegli font, dimensione base e peso titoli del form.', 'fp-restaurant-reservations'),
                    'fields'      => self::getTypographyFields(),
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
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getStyleFoundationFields(): array
    {
        return [
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
                'description' => __('Spessore visibile dell\'anello di focus per pulsanti e campi.', 'fp-restaurant-reservations'),
            ],
            'style_enable_dark_mode' => [
                'label'          => __('Dark mode automatica', 'fp-restaurant-reservations'),
                'type'           => 'checkbox',
                'checkbox_label' => __('Adatta i colori al tema scuro del dispositivo.', 'fp-restaurant-reservations'),
                'default'        => '1',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getTypographyFields(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getLanguagePage(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getClosuresPage(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getTrackingPage(): array
    {
        return [
            'page_title'   => __('Tracking & Consent', 'fp-restaurant-reservations'),
            'menu_title'   => __('Tracking', 'fp-restaurant-reservations'),
            'slug'         => 'fp-resv-tracking',
            'option_group' => 'fp_resv_tracking',
            'option_name'  => 'fp_resv_tracking',
            'sections'     => [
                'tracking-integrations' => [
                    'title'       => __('Integrazioni marketing', 'fp-restaurant-reservations'),
                    'description' => __('Configura GA4, Google Ads, Meta Pixel, Clarity e preferenze di consenso.', 'fp-restaurant-reservations'),
                    'fields'      => self::getTrackingIntegrationFields(),
                ],
                'privacy-controls' => [
                    'title'       => __('Privacy & GDPR', 'fp-restaurant-reservations'),
                    'description' => __('Configura informativa, consensi opzionali e politiche di retention.', 'fp-restaurant-reservations'),
                    'fields'      => self::getPrivacyFields(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getTrackingIntegrationFields(): array
    {
        return [
            'ga4_measurement_id' => [
                'label'       => __('GA4 Measurement ID', 'fp-restaurant-reservations'),
                'type'        => 'text',
                'default'     => '',
            ],
            'ga4_api_secret' => [
                'label'       => __('GA4 API Secret (per invii server-side)', 'fp-restaurant-reservations'),
                'type'        => 'text',
                'default'     => '',
                'description' => __('Necessario per inviare eventi tramite Measurement Protocol API', 'fp-restaurant-reservations'),
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
            'meta_access_token' => [
                'label'       => __('Meta Access Token (per invii server-side)', 'fp-restaurant-reservations'),
                'type'        => 'text',
                'default'     => '',
                'description' => __('Necessario per inviare eventi tramite Conversions API', 'fp-restaurant-reservations'),
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
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getPrivacyFields(): array
    {
        return [
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
        ];
    }
}
