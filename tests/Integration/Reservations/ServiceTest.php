<?php

declare(strict_types=1);

namespace Tests\Integration\Reservations;

use FP\Resv\Core\Consent;
use FP\Resv\Core\Mailer;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Payments\StripeService;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\Service;
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
    }

    public function testCreateReservationPersistsDataAndSendsEmails(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';

        $options      = new Options();
        Consent::init($options);

        $language     = new Language($options);
        $mailer       = new class extends Mailer {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            public function send(
                string $to,
                string $subject,
                string $message,
                array $headers = [],
                array $attachments = [],
                array $context = []
            ): bool {
                $this->sent[] = compact('to', 'subject', 'message', 'headers', 'attachments', 'context');

                return true;
            }
        };

        $reservations = new ReservationsRepository($this->wpdb);
        $customers    = new CustomersRepository($this->wpdb);
        $paymentsRepo = new PaymentsRepository($this->wpdb);
        $stripe       = new StripeService($options, $paymentsRepo);

        $service = new Service(
            $reservations,
            $options,
            $language,
            $mailer,
            $customers,
            $stripe,
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

        $reservationsTable = $this->wpdb->get_table($reservations->tableName());
        self::assertCount(1, $reservationsTable);
        $stored = $reservationsTable[1];
        self::assertSame('confirmed', $stored['status']);
        self::assertSame('20:15:00', $stored['time']);
        self::assertSame('newsletter', $stored['utm_source']);
        self::assertSame('EUR', $stored['currency']);

        $customersTable = $this->wpdb->get_table($customers->tableName());
        self::assertCount(1, $customersTable);
        $customer = $customersTable[1];
        self::assertSame('ada@example.test', $customer['email']);
        self::assertSame('it', $customer['lang']);

        self::assertNotEmpty($mailer->sent);
        $customerMail = $mailer->sent[0];
        self::assertSame('ada@example.test', $customerMail['to']);
        self::assertStringContainsString('La tua prenotazione', $customerMail['subject']);

        $staffMail = $mailer->sent[1];
        self::assertSame('staff@example.test', $staffMail['to']);
        self::assertSame('restaurant_notification', $staffMail['context']['channel']);
    }
}

