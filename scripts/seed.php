<?php
declare(strict_types=1);

use FP\Resv\Core\Migrations;

if (!defined('ABSPATH')) {
    echo "[fp-resv] Questo script va eseguito dentro WordPress (es. wp eval-file)." . PHP_EOL;
    return;
}

require_once __DIR__ . '/../src/Core/Migrations.php';

Migrations::run();

global $wpdb;

if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
    echo "[fp-resv] wpdb non disponibile. Verifica l'ambiente WordPress." . PHP_EOL;
    return;
}

$seed = new class($wpdb) {
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function run(): void
    {
        $this->log('Avvio seed FP Restaurant Reservations...');

        $this->ensureOptions();
        $roomId   = $this->ensureRoom();
        $tableIds = $this->ensureTables($roomId);
        $this->ensureMealsOption();
        $customers = $this->ensureCustomers();
        $this->ensureReservations($roomId, $tableIds, $customers);
        $this->ensureEvent($roomId);

        $this->log('Seed completato.');
    }

    private function ensureOptions(): void
    {
        $this->mergeOption('fp_resv_general', [
            'restaurant_name'           => 'Trattoria Demo FP',
            'restaurant_timezone'       => 'Europe/Rome',
            'default_party_size'        => '2',
            'default_reservation_status'=> 'pending',
            'default_currency'          => 'EUR',
            'slot_interval_minutes'     => '15',
            'table_turnover_minutes'    => '120',
            'buffer_before_minutes'     => '15',
            'max_parallel_parties'      => '6',
            'service_hours_definition'  => implode("\n", [
                'mon=12:30-15:00|19:00-22:30',
                'tue=19:00-22:30',
                'wed=19:00-22:30',
                'thu=12:30-15:00|19:00-23:00',
                'fri=12:30-15:00|19:00-23:30',
                'sat=12:00-15:30|19:00-23:30',
                'sun=11:30-15:30'
            ]),
            'frontend_meals'            => implode("\n", [
                '*lunch|Pranzo|Disponibile dal lunedì al venerdì.|Include acqua e caffè.|22.00|Menu business',
                'aperitivo|Aperitivo|Drink + tapas dalle 18:00.|Prenotazione di 90 minuti.|18.50|Nuovo',
                'dinner|Cena|Percorso degustazione 4 portate.||34.00|Best seller',
                'brunch|Brunch|Sabato e domenica dalle 11:30.|Bevande calde illimitate.|28.00|Weekend',
            ]),
        ]);

        $this->mergeOption('fp_resv_rooms', [
            'default_room_capacity' => '40',
            'merge_strategy'        => 'smart',
        ]);

        $this->mergeOption('fp_resv_notifications', [
            'restaurant_emails' => ['sala@fp-resv.test'],
            'webmaster_emails'  => ['ops@fp-resv.test'],
            'sender_name'       => 'Trattoria Demo FP',
            'sender_email'      => 'noreply@fp-resv.test',
            'reply_to_email'    => 'prenotazioni@fp-resv.test',
            'attach_ics'        => '1',
        ]);

        $this->mergeOption('fp_resv_tracking', [
            'privacy_policy_url'               => home_url('/privacy-policy'),
            'privacy_policy_version'           => '1.0.0',
            'privacy_enable_marketing_consent' => '1',
            'privacy_enable_profiling_consent' => '1',
            'privacy_retention_months'         => '24',
        ]);

        $this->mergeOption('fp_resv_brevo', [
            'brevo_enabled'                     => '0',
            'brevo_api_key'                     => 'TODO-REPLACE-WITH-REAL-BREVO-KEY',
            'brevo_list_id_it'                  => 'brevo-list-it-demo',
            'brevo_list_id_en'                  => 'brevo-list-en-demo',
            'brevo_phone_prefix_map'            => wp_json_encode([
                ['prefix' => '+39', 'list' => 'brevo-list-it-demo'],
                ['prefix' => '+33', 'list' => 'brevo-list-en-demo'],
            ]),
            'brevo_list_id'                     => '',
            'brevo_followup_offset_hours'       => '24',
            'brevo_review_threshold'            => '4.5',
            'brevo_review_nps_threshold'        => '9',
            'brevo_review_place_id'             => '',
            // Attributi Brevo (valori di default)
            'brevo_attr_firstname'              => 'FIRSTNAME',
            'brevo_attr_lastname'               => 'LASTNAME',
            'brevo_attr_email'                  => 'EMAIL',
            'brevo_attr_phone'                  => 'PHONE',
            'brevo_attr_sms'                    => 'SMS',
            'brevo_attr_whatsapp'               => 'WHATSAPP',
            'brevo_attr_lang'                   => 'LANG',
            'brevo_attr_lingua'                 => 'LINGUA',
            'brevo_attr_reservation_date'       => 'RESERVATION_DATE',
            'brevo_attr_prenotazione_data'      => 'PRENOTAZIONE_DATA',
            'brevo_attr_reservation_time'       => 'RESERVATION_TIME',
            'brevo_attr_prenotazione_orario'    => 'PRENOTAZIONE_ORARIO',
            'brevo_attr_reservation_party'      => 'RESERVATION_PARTY',
            'brevo_attr_persone'                => 'PERSONE',
            'brevo_attr_reservation_status'     => 'RESERVATION_STATUS',
            'brevo_attr_note'                   => 'NOTE',
            'brevo_attr_notes'                  => 'NOTES',
            'brevo_attr_marketing_consent'      => 'MARKETING_CONSENT',
            'brevo_attr_resvid'                 => 'RESVID',
            'brevo_attr_reservation_id'         => 'RESERVATION_ID',
            'brevo_attr_reservation_location'   => 'RESERVATION_LOCATION',
            'brevo_attr_reservation_manage_link' => 'RESERVATION_MANAGE_LINK',
            'brevo_attr_utm_source'             => 'RESERVATION_UTM_SOURCE',
            'brevo_attr_utm_medium'             => 'RESERVATION_UTM_MEDIUM',
            'brevo_attr_utm_campaign'           => 'RESERVATION_UTM_CAMPAIGN',
            'brevo_attr_gclid'                  => 'GCLID',
            'brevo_attr_fbclid'                 => 'FBCLID',
            'brevo_attr_msclkid'                => 'MSCLKID',
            'brevo_attr_ttclid'                 => 'TTCLID',
            'brevo_attr_amount'                 => 'AMOUNT',
            'brevo_attr_value'                  => 'VALUE',
            'brevo_attr_currency'               => 'CURRENCY',
        ]);

        $this->mergeOption('fp_resv_google_calendar', [
            'calendar_id'       => 'demo-calendar@fp-resv.test',
            'synced_status'     => 'confirmed',
            'delete_on_cancel'  => '0',
        ]);

        $this->mergeOption('fp_resv_reports', [
            'channels_enabled' => ['organic', 'google_ads', 'meta_ads', 'direct', 'referral'],
        ]);
    }

    private function ensureRoom(): int
    {
        $table = $this->tableName('fp_rooms');
        $existingId = (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$table} WHERE name = %s LIMIT 1", 'Sala Principale')
        );

        $payload = [
            'name'        => 'Sala Principale',
            'description' => 'Sala principale con vetrate sulla piazza.',
            'color'       => '#C0392B',
            'capacity'    => 40,
            'order_index' => 1,
            'active'      => 1,
            'updated_at'  => current_time('mysql'),
        ];

        if ($existingId > 0) {
            $this->wpdb->update($table, $payload, ['id' => $existingId]);
            $this->log('Sala principale già presente, aggiornata.');

            return $existingId;
        }

        $payload['created_at'] = current_time('mysql');
        $this->wpdb->insert($table, $payload);
        $roomId = (int) $this->wpdb->insert_id;
        $this->log('Creata sala principale #' . $roomId . '.');

        return $roomId;
    }

    /**
     * @return array<int, int>
     */
    private function ensureTables(int $roomId): array
    {
        $table = $this->tableName('fp_tables');
        $definitions = [
            [
                'code'      => 'T1',
                'seats_min' => 2,
                'seats_std' => 2,
                'seats_max' => 2,
                'pos_x'     => 10,
                'pos_y'     => 12,
            ],
            [
                'code'      => 'T2',
                'seats_min' => 2,
                'seats_std' => 4,
                'seats_max' => 4,
                'pos_x'     => 26,
                'pos_y'     => 18,
            ],
            [
                'code'      => 'T3',
                'seats_min' => 4,
                'seats_std' => 6,
                'seats_max' => 6,
                'pos_x'     => 42,
                'pos_y'     => 15,
            ],
        ];

        $ids = [];
        foreach ($definitions as $index => $definition) {
            $existingId = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$table} WHERE code = %s AND room_id = %d LIMIT 1",
                    $definition['code'],
                    $roomId
                )
            );

            $payload = [
                'room_id'    => $roomId,
                'code'       => $definition['code'],
                'seats_min'  => $definition['seats_min'],
                'seats_std'  => $definition['seats_std'],
                'seats_max'  => $definition['seats_max'],
                'pos_x'      => $definition['pos_x'],
                'pos_y'      => $definition['pos_y'],
                'status'     => 'available',
                'active'     => 1,
                'updated_at' => current_time('mysql'),
            ];

            if ($existingId > 0) {
                $this->wpdb->update($table, $payload, ['id' => $existingId]);
                $ids[$index] = $existingId;
                continue;
            }

            $payload['created_at'] = current_time('mysql');
            $this->wpdb->insert($table, $payload);
            $ids[$index] = (int) $this->wpdb->insert_id;
        }

        $this->log('Tavoli di base sincronizzati: ' . implode(', ', array_map('strval', $ids)) . '.');

        return $ids;
    }

    private function ensureMealsOption(): void
    {
        $meals = [
            'lunch' => [
                'key'    => 'lunch',
                'label'  => 'Pranzo',
                'badge'  => 'Menu business',
                'hint'   => 'Disponibile dal lunedì al venerdì.',
                'price'  => 22.00,
                'notice' => 'Include acqua e caffè.',
                'active' => true,
            ],
            'aperitivo' => [
                'key'    => 'aperitivo',
                'label'  => 'Aperitivo',
                'badge'  => 'Nuovo',
                'hint'   => 'Drink + tapas dalle 18:00.',
                'price'  => 18.50,
                'notice' => 'Prenotazione di 90 minuti.',
            ],
            'dinner' => [
                'key'    => 'dinner',
                'label'  => 'Cena',
                'badge'  => 'Best seller',
                'hint'   => 'Percorso degustazione 4 portate.',
                'price'  => 34.00,
            ],
            'brunch' => [
                'key'    => 'brunch',
                'label'  => 'Brunch',
                'badge'  => 'Weekend',
                'hint'   => 'Sabato e domenica dalle 11:30.',
                'price'  => 28.00,
                'notice' => 'Bevande calde illimitate.',
            ],
        ];

        update_option('fp_resv_seed_meals', $meals);
    }

    /**
     * @return array<string, int>
     */
    private function ensureCustomers(): array
    {
        $table = $this->tableName('fp_customers');
        $now   = current_time('mysql');

        $customers = [
            'anna.rossi@example.com' => [
                'first_name'        => 'Anna',
                'last_name'         => 'Rossi',
                'phone'             => '+393331234567',
                'lang'              => 'it',
                'marketing_consent' => 1,
                'profiling_consent' => 0,
                'consent_ts'        => $now,
                'consent_version'   => '1.0.0',
            ],
            'ben.thomas@example.com' => [
                'first_name'        => 'Ben',
                'last_name'         => 'Thomas',
                'phone'             => '+447700900123',
                'lang'              => 'en',
                'marketing_consent' => 1,
                'profiling_consent' => 1,
                'consent_ts'        => $now,
                'consent_version'   => '1.0.0',
            ],
            'claire.dupont@example.com' => [
                'first_name'        => 'Claire',
                'last_name'         => 'Dupont',
                'phone'             => '+33612345678',
                'lang'              => 'fr',
                'marketing_consent' => 0,
                'profiling_consent' => 0,
                'consent_ts'        => $now,
                'consent_version'   => '1.0.0',
            ],
        ];

        $ids = [];
        foreach ($customers as $email => $data) {
            $existingId = (int) $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT id FROM {$table} WHERE email = %s LIMIT 1", $email)
            );

            $payload = array_merge($data, [
                'email'      => $email,
                'updated_at' => $now,
            ]);

            if ($existingId > 0) {
                $this->wpdb->update($table, $payload, ['id' => $existingId]);
                $ids[$email] = $existingId;
                continue;
            }

            $payload['created_at'] = $now;
            $this->wpdb->insert($table, $payload);
            $ids[$email] = (int) $this->wpdb->insert_id;
        }

        return $ids;
    }

    /**
     * @param array<int, int> $tableIds
     * @param array<string, int> $customers
     */
    private function ensureReservations(int $roomId, array $tableIds, array $customers): void
    {
        $table = $this->tableName('fp_reservations');
        $now   = current_time('mysql');

        $reservations = [
            [
                'customer_email' => 'anna.rossi@example.com',
                'status'         => 'confirmed',
                'date'           => gmdate('Y-m-d', strtotime('+2 days')),
                'time'           => '12:30:00',
                'party'          => 2,
                'notes'          => 'Richiesta tavolo vicino alla finestra.',
                'allergies'      => 'Noci',
                'utm_source'     => 'google',
                'utm_medium'     => 'cpc',
                'utm_campaign'   => 'lunch-campaign',
                'lang'           => 'it',
                'value'          => 44.00,
                'currency'       => 'EUR',
                'table_index'    => 0,
            ],
            [
                'customer_email' => 'ben.thomas@example.com',
                'status'         => 'pending',
                'date'           => gmdate('Y-m-d', strtotime('+5 days')),
                'time'           => '20:00:00',
                'party'          => 4,
                'notes'          => 'Festeggia anniversario, graditi fiori sul tavolo.',
                'allergies'      => '',
                'utm_source'     => 'meta',
                'utm_medium'     => 'paid_social',
                'utm_campaign'   => 'romantic-dinner',
                'lang'           => 'en',
                'value'          => 136.00,
                'currency'       => 'EUR',
                'table_index'    => 2,
            ],
            [
                'customer_email' => 'claire.dupont@example.com',
                'status'         => 'seated',
                'date'           => gmdate('Y-m-d', strtotime('-1 day')),
                'time'           => '11:30:00',
                'party'          => 3,
                'notes'          => 'Arriva con passeggino, richiede spazio extra.',
                'allergies'      => 'Glutine',
                'utm_source'     => 'newsletter',
                'utm_medium'     => 'email',
                'utm_campaign'   => 'brunch-welcome',
                'lang'           => 'fr',
                'value'          => 84.00,
                'currency'       => 'EUR',
                'table_index'    => 1,
                'visited_at'     => gmdate('Y-m-d H:i:s', strtotime('-1 day 13:30')), 
            ],
        ];

        foreach ($reservations as $reservation) {
            if (!isset($customers[$reservation['customer_email']])) {
                continue;
            }

            $customerId = $customers[$reservation['customer_email']];
            $tableId    = $tableIds[$reservation['table_index']] ?? null;

            $existingId = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$table} WHERE customer_id = %d AND date = %s AND time = %s LIMIT 1",
                    $customerId,
                    $reservation['date'],
                    $reservation['time']
                )
            );

            $payload = [
                'status'       => $reservation['status'],
                'date'         => $reservation['date'],
                'time'         => $reservation['time'],
                'party'        => $reservation['party'],
                'notes'        => $reservation['notes'],
                'allergies'    => $reservation['allergies'],
                'utm_source'   => $reservation['utm_source'],
                'utm_medium'   => $reservation['utm_medium'],
                'utm_campaign' => $reservation['utm_campaign'],
                'lang'         => $reservation['lang'],
                'location_id'  => 'default',
                'value'        => $reservation['value'],
                'currency'     => $reservation['currency'],
                'customer_id'  => $customerId,
                'room_id'      => $roomId,
                'table_id'     => $tableId,
                'updated_at'   => $now,
            ];

            if (!empty($reservation['visited_at'])) {
                $payload['visited_at'] = $reservation['visited_at'];
            }

            if ($existingId > 0) {
                $this->wpdb->update($table, $payload, ['id' => $existingId]);
                continue;
            }

            $payload['created_at'] = $now;
            $this->wpdb->insert($table, $payload);
        }
    }

    private function ensureEvent(int $roomId): void
    {
        $table = $this->tableName('fp_events');
        $slug  = 'degustazione-autunnale';

        $startTs = strtotime('+10 days 19:30');
        $endTs   = strtotime('+10 days 22:30');

        $payload = [
            'title'        => 'Degustazione Autunnale',
            'slug'         => $slug,
            'start_at'     => gmdate('Y-m-d H:i:s', $startTs),
            'end_at'       => gmdate('Y-m-d H:i:s', $endTs),
            'capacity'     => 24,
            'price'        => 55.00,
            'currency'     => 'EUR',
            'status'       => 'published',
            'lang'         => 'it',
            'settings_json'=> wp_json_encode([
                'location' => 'Sala Principale',
                'room_id'  => $roomId,
                'summary'  => 'Percorso di 5 portate con abbinamento vini locali.',
            ]),
            'updated_at'   => current_time('mysql'),
        ];

        $existingId = (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug)
        );

        if ($existingId > 0) {
            $this->wpdb->update($table, $payload, ['id' => $existingId]);
            $this->log('Evento degustazione aggiornato.');

            return;
        }

        $payload['created_at'] = current_time('mysql');
        $this->wpdb->insert($table, $payload);
        $this->log('Evento degustazione creato (#' . (int) $this->wpdb->insert_id . ').');
    }

    private function mergeOption(string $name, array $values): void
    {
        $current = get_option($name, []);
        if (!is_array($current)) {
            $current = [];
        }

        $merged = $this->deepMerge($current, $values);
        update_option($name, $merged);
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    private function deepMerge(array $original, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
                $original[$key] = $this->deepMerge($original[$key], $value);
            } else {
                $original[$key] = $value;
            }
        }

        return $original;
    }

    private function tableName(string $suffix): string
    {
        return $this->wpdb->prefix . $suffix;
    }

    private function log(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::log($message);

            return;
        }

        echo '[fp-resv] ' . $message . PHP_EOL;
    }
};

$seed->run();

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
    \WP_CLI::success('Seed completato.');
}
