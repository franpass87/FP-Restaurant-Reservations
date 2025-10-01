<?php

declare(strict_types=1);

namespace Tests\Integration\Reservations;

use FP\Resv\Core\Consent;
use FP\Resv\Core\Mailer;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Payments\StripeService;
use FP\Resv\Domain\Reservations\Availability;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\Service;
use FP\Resv\Domain\Tables\Repository as TablesRepository;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

final class ServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb      = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['__wp_tests_mail_log'] = [];

        update_option('fp_resv_general', [
            'default_reservation_status' => 'confirmed',
            'default_currency'           => 'EUR',
            'restaurant_name'            => 'La Pergola',
            'restaurant_timezone'        => 'Europe/Rome',
            'service_hours_definition'   => "sat=19:00-23:00\nsun=19:00-23:00\nmon=19:00-23:00",
            'slot_interval_minutes'      => '15',
            'table_turnover_minutes'     => '120',
            'buffer_before_minutes'      => '0',
            'max_parallel_parties'       => '6',
            'enable_waitlist'            => '0',
        ]);

        update_option('fp_resv_rooms', [
            'merge_strategy'        => 'smart',
            'default_room_capacity' => '4',
        ]);

        update_option('fp_resv_notifications', [
            'restaurant_emails' => ['staff@example.test'],
            'webmaster_emails'  => ['webmaster@example.test'],
            'attach_ics'        => '0',
            'sender_name'       => 'FP Restaurant',
            'sender_email'      => 'booking@example.test',
        ]);

        update_option('fp_resv_language', [
            'language_supported_locales' => "it_IT\nen_US",
            'language_fallback_locale'   => 'it_IT',
        ]);

        update_option('fp_resv_payments', [
            'stripe_enabled' => '0',
        ]);

        $this->wpdb->insert('wp_fp_rooms', [
            'id'          => 1,
            'name'        => 'Sala Principale',
            'capacity'    => 4,
            'active'      => 1,
            'order_index' => 0,
        ]);
    }

    public function testCreateReservationPersistsDataAndSendsEmails(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';

        $options      = new Options();
        Consent::init($options);

        $language     = new Language($options);
        $mailer       = new Mailer();

        $reservations = new ReservationsRepository($this->wpdb);
        $customers    = new CustomersRepository($this->wpdb);
        $paymentsRepo = new PaymentsRepository($this->wpdb);
        $stripe       = new StripeService($options, $paymentsRepo);
        $tables       = new TablesRepository($this->wpdb);
        $availability = new Availability($options, $this->wpdb);

        $service = new Service(
            $reservations,
            $options,
            $language,
            $mailer,
            $customers,
            $stripe,
            $tables,
            $availability,
            null
        );

        $result = $service->create([
            'date'        => '2024-05-04',
            'time'        => '20:15',
            'party'       => 2,
            'first_name'  => 'Ada',
            'last_name'   => 'Lovelace',
            'email'       => 'ada@example.test',
            'phone'       => '+39 055 123456',
            'phone_e164'  => '+39055123456',
            'phone_country' => 'IT',
            'phone_national'=> '055 123456',
            'notes'       => 'Niente glutine',
            'allergies'   => 'Glutine',
            'language'    => 'it',
            'locale'      => 'it_IT',
            'location'    => 'firenze',
            'currency'    => 'EUR',
            'utm_source'  => 'newsletter',
            'utm_medium'  => 'email',
            'utm_campaign'=> 'spring',
        ]);

        self::assertArrayHasKey('id', $result);
        self::assertSame('confirmed', $result['status']);
        self::assertStringContainsString('fp_resv_manage=', $result['manage_url']);

        $reservationsTable = array_values($this->wpdb->get_table($reservations->tableName()));
        self::assertCount(1, $reservationsTable);
        $stored = $reservationsTable[0];
        self::assertSame('confirmed', $stored['status']);
        self::assertSame('20:15:00', $stored['time']);
        self::assertSame('newsletter', $stored['utm_source']);
        self::assertSame('EUR', $stored['currency']);

        $customersTable = array_values($this->wpdb->get_table($customers->tableName()));
        self::assertCount(1, $customersTable);
        $customer = $customersTable[0];
        self::assertSame('ada@example.test', $customer['email']);
        self::assertSame('it', $customer['lang']);
        self::assertSame('+39055123456', $customer['phone_e164']);
        self::assertSame('IT', $customer['phone_country']);
        self::assertSame('055 123456', $customer['phone_national']);

        $mailLog = array_values($GLOBALS['__wp_tests_mail_log']);
        self::assertNotEmpty($mailLog);

        $customerMail = $mailLog[0];
        self::assertSame('ada@example.test', $customerMail['to']);
        self::assertStringContainsString('La tua prenotazione', $customerMail['subject']);
    }

    public function testDuplicateReservationIsRejected(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.50';

        $options      = new Options();
        Consent::init($options);

        $language     = new Language($options);
        $mailer       = new Mailer();

        $reservations = new ReservationsRepository($this->wpdb);
        $customers    = new CustomersRepository($this->wpdb);
        $paymentsRepo = new PaymentsRepository($this->wpdb);
        $stripe       = new StripeService($options, $paymentsRepo);
        $tables       = new TablesRepository($this->wpdb);
        $availability = new Availability($options, $this->wpdb);

        $service = new Service(
            $reservations,
            $options,
            $language,
            $mailer,
            $customers,
            $stripe,
            $tables,
            $availability,
            null
        );

        $payload = [
            'date'        => '2024-05-04',
            'time'        => '20:15',
            'party'       => 2,
            'first_name'  => 'Grace',
            'last_name'   => 'Hopper',
            'email'       => 'grace@example.test',
            'phone'       => '+39 06 1234567',
            'phone_e164'  => '+39061234567',
            'phone_country' => 'IT',
            'phone_national'=> '06 1234567',
            'language'    => 'it',
            'locale'      => 'it_IT',
            'location'    => 'roma',
        ];

        $service->create($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Esiste già una prenotazione');

        $service->create($payload);
    }

    public function testReservationRejectedWhenSlotIsFull(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.61';

        $options      = new Options();
        Consent::init($options);

        $language     = new Language($options);
        $mailer       = new Mailer();

        $reservations = new ReservationsRepository($this->wpdb);
        $customers    = new CustomersRepository($this->wpdb);
        $paymentsRepo = new PaymentsRepository($this->wpdb);
        $stripe       = new StripeService($options, $paymentsRepo);
        $tables       = new TablesRepository($this->wpdb);
        $availability = new Availability($options, $this->wpdb);

        // Saturate the slot by creating a reservation that consumes all capacity.
        $reservations->insert([
            'status'   => 'confirmed',
            'date'     => '2024-05-04',
            'time'     => '20:15:00',
            'party'    => 4,
            'customer_id' => null,
            'room_id'  => null,
            'location_id' => 'default',
        ]);

        $service = new Service(
            $reservations,
            $options,
            $language,
            $mailer,
            $customers,
            $stripe,
            $tables,
            $availability,
            null
        );

        $result = $availability->findSlots([
            'date'     => '2024-05-04',
            'party'    => 2,
            'location' => 'default',
        ]);

        $targetSlot = null;
        foreach ($result['slots'] as $slot) {
            if (($slot['label'] ?? '') === '20:15') {
                $targetSlot = $slot;
                break;
            }
        }

        self::assertIsArray($targetSlot);
        self::assertSame('full', $targetSlot['status']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Il turno selezionato');

        $service->create([
            'date'        => '2024-05-04',
            'time'        => '20:15',
            'party'       => 2,
            'first_name'  => 'Maria',
            'last_name'   => 'Rossi',
            'email'       => 'maria@example.test',
            'phone'       => '+39 055 111111',
        ]);
    }

    public function testManageTokenExpires(): void
    {
        $service = $this->makeService();

        $filter = static function (): int {
            return 60;
        };

        add_filter('fp_resv_manage_token_ttl', $filter);

        $result = $service->create([
            'date'        => '2024-05-06',
            'time'        => '19:30',
            'party'       => 3,
            'first_name'  => 'Laura',
            'last_name'   => 'Verdi',
            'email'       => 'laura@example.test',
            'phone'       => '+39 02 654321',
        ]);

        remove_filter('fp_resv_manage_token_ttl', $filter);

        $reservationId = (int) ($result['id'] ?? 0);
        self::assertGreaterThan(0, $reservationId);

        $parsed = wp_parse_url((string) ($result['manage_url'] ?? ''));
        parse_str($parsed['query'] ?? '', $params);
        $token = (string) ($params['fp_resv_token'] ?? '');

        self::assertNotSame('', $token);
        self::assertTrue($service->verifyManageToken($reservationId, 'laura@example.test', $token));

        $store = get_option('fp_resv_manage_tokens', []);
        $store[$token]['expires_at'] = time() - 10;
        update_option('fp_resv_manage_tokens', $store);

        self::assertFalse($service->verifyManageToken($reservationId, 'laura@example.test', $token));

        delete_option('fp_resv_manage_tokens');
    }

    private function makeService(): Service
    {
        $options      = new Options();
        Consent::init($options);
        $language     = new Language($options);
        $mailer       = new Mailer();
        $reservations = new ReservationsRepository($this->wpdb);
        $customers    = new CustomersRepository($this->wpdb);
        $paymentsRepo = new PaymentsRepository($this->wpdb);
        $stripe       = new StripeService($options, $paymentsRepo);
        $tables       = new TablesRepository($this->wpdb);
        $availability = new Availability($options, $this->wpdb);

        return new Service(
            $reservations,
            $options,
            $language,
            $mailer,
            $customers,
            $stripe,
            $tables,
            $availability,
            null
        );
    }
}

