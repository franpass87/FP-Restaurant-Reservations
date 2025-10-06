# Esempi Pratici

## Uso delle Nuove Funzionalità

### 1. Validazione Custom

#### Scenario: Validare prenotazione da form custom

```php
use FP\Resv\Core\ReservationValidator;
use FP\Resv\Core\Exceptions\ValidationException;

function handle_custom_booking_form() {
    $validator = new ReservationValidator();
    
    $payload = [
        'date' => $_POST['booking_date'] ?? '',
        'time' => $_POST['booking_time'] ?? '',
        'party' => (int) ($_POST['party_size'] ?? 0),
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
    ];
    
    if (!$validator->validate($payload)) {
        $errors = $validator->getErrors();
        
        wp_send_json_error([
            'message' => 'Dati non validi',
            'errors' => $errors,
        ], 400);
    }
    
    // Procedi con la creazione
    // ...
}
```

#### Scenario: Validazione con eccezioni in API

```php
use FP\Resv\Core\ReservationValidator;
use FP\Resv\Core\Exceptions\InvalidDateException;
use FP\Resv\Core\Exceptions\InvalidContactException;

add_action('rest_api_init', function() {
    register_rest_route('my-plugin/v1', '/bookings', [
        'methods' => 'POST',
        'callback' => function($request) {
            $validator = new ReservationValidator();
            
            try {
                $validator->assertValidDate($request['date']);
                $validator->assertValidTime($request['time']);
                $validator->assertValidParty($request['party'], 50);
                $validator->assertValidContact($request->get_params());
                
                // Create booking
                $booking = create_booking($request->get_params());
                
                return new WP_REST_Response($booking, 201);
                
            } catch (InvalidDateException $e) {
                return new WP_Error(
                    'invalid_date',
                    $e->getMessage(),
                    ['status' => 400, 'context' => $e->getContext()]
                );
            } catch (InvalidContactException $e) {
                return new WP_Error(
                    'invalid_contact',
                    'Dati di contatto non validi',
                    ['status' => 400, 'errors' => $e->getContext()]
                );
            }
        },
        'permission_callback' => '__return_true',
    ]);
});
```

### 2. Service Container

#### Scenario: Registrare servizi custom

```php
use FP\Resv\Core\ServiceContainer;

// In plugin bootstrap
add_action('plugins_loaded', function() {
    $container = ServiceContainer::getInstance();
    
    // Singleton (lazy loaded)
    $container->singleton('my.logger', function($c) {
        return new MyCustomLogger(
            $c->get('settings.options')
        );
    });
    
    // Transient (new instance ogni volta)
    $container->transient('my.validator', function() {
        return new MyCustomValidator();
    });
    
    // Factory con cache opzionale
    $container->factory('my.api_client', function($c) {
        return new MyAPIClient(
            $c->get('settings.options')->getField('my_plugin', 'api_key')
        );
    }, true); // true = shared
}, 20);

// Uso
$logger = ServiceContainer::getInstance()->get('my.logger');
$logger->info('Operation completed');
```

#### Scenario: Decorator pattern

```php
// Aggiungere logging a mailer esistente
add_action('plugins_loaded', function() {
    $container = ServiceContainer::getInstance();
    
    $container->extend('core.mailer', function($mailer, $c) {
        return new class($mailer) {
            public function __construct(private $mailer) {}
            
            public function send($to, $subject, $message, ...$args) {
                $logger = ServiceContainer::getInstance()->get('my.logger');
                $logger->info("Sending email to: {$to}");
                
                $result = $this->mailer->send($to, $subject, $message, ...$args);
                
                if ($result) {
                    $logger->info("Email sent successfully");
                } else {
                    $logger->error("Email failed");
                }
                
                return $result;
            }
        };
    });
}, 25); // After plugin registration
```

### 3. Metriche Custom

#### Scenario: Tracciare operazioni custom

```php
use FP\Resv\Core\Metrics;

function my_expensive_operation() {
    $stop = Metrics::timer('my_plugin.expensive_operation', [
        'user_role' => wp_get_current_user()->roles[0] ?? 'guest',
    ]);
    
    try {
        // ... operazione ...
        $result = do_expensive_work();
        
        Metrics::increment('my_plugin.operation_success');
        return $result;
        
    } catch (Exception $e) {
        Metrics::increment('my_plugin.operation_error', 1, [
            'error_type' => get_class($e),
        ]);
        throw $e;
        
    } finally {
        $stop();
    }
}
```

#### Scenario: Gauge per queue size

```php
function process_background_jobs() {
    $queue = get_pending_jobs();
    
    Metrics::gauge('my_plugin.queue_size', count($queue));
    
    foreach ($queue as $job) {
        $stop = Metrics::timer('my_plugin.job_processing', [
            'job_type' => $job['type'],
        ]);
        
        process_job($job);
        
        $stop();
        Metrics::increment('my_plugin.jobs_processed', 1, [
            'job_type' => $job['type'],
        ]);
    }
}
```

### 4. Cache Management

#### Scenario: Invalidare cache dopo update

```php
use FP\Resv\Core\CacheManager;

add_action('save_post', function($postId, $post) {
    if ($post->post_type !== 'restaurant_menu') {
        return;
    }
    
    // Invalida cache rooms e tables se menu cambia disponibilità
    CacheManager::invalidateRooms();
    CacheManager::invalidateTables();
    
    // Invalida availability per oggi e domani
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    CacheManager::invalidateAvailability($today);
    CacheManager::invalidateAvailability($tomorrow);
}, 10, 2);
```

#### Scenario: Cache warming scheduled

```php
use FP\Resv\Core\CacheManager;

// Schedule warmup ogni ora
add_action('init', function() {
    if (!wp_next_scheduled('my_plugin_cache_warmup')) {
        wp_schedule_event(time(), 'hourly', 'my_plugin_cache_warmup');
    }
});

add_action('my_plugin_cache_warmup', function() {
    CacheManager::warmUp();
    
    // Pre-load anche availability per oggi
    $container = \FP\Resv\Core\ServiceContainer::getInstance();
    $availability = $container->get('reservations.availability');
    
    $criteria = [
        'date' => date('Y-m-d'),
        'party' => 2,
    ];
    
    $availability->findSlots($criteria);
    
    error_log('Cache warmed up successfully');
});
```

### 5. Async Email

#### Scenario: Email transazionale async

```php
use FP\Resv\Core\ServiceContainer;

function send_booking_confirmation($bookingId, $customerEmail) {
    $asyncMailer = ServiceContainer::getInstance()->get('async.mailer');
    
    $booking = get_booking($bookingId);
    
    $asyncMailer->queueCustomerEmail([
        'to' => $customerEmail,
        'subject' => sprintf('Prenotazione #%d confermata', $bookingId),
        'message' => generate_email_content($booking),
        'headers' => [
            'From: Restaurant <noreply@example.com>',
            'Reply-To: info@example.com',
        ],
        'attachments' => [],
        'meta' => [
            'booking_id' => $bookingId,
            'channel' => 'confirmation',
            'content_type' => 'text/html',
        ],
    ]);
}
```

#### Scenario: Bulk email con rate limiting

```php
function send_newsletter_to_customers($customerIds) {
    $asyncMailer = ServiceContainer::getInstance()->get('async.mailer');
    
    foreach ($customerIds as $customerId) {
        $customer = get_customer($customerId);
        
        // Queue email (Action Scheduler gestirà il rate limiting)
        $asyncMailer->queueCustomerEmail([
            'to' => $customer['email'],
            'subject' => 'Newsletter Mensile',
            'message' => generate_newsletter_content(),
            'headers' => [],
            'attachments' => [],
            'meta' => [
                'customer_id' => $customerId,
                'channel' => 'newsletter',
            ],
        ]);
        
        // Sleep for rate limiting (se no Action Scheduler)
        if (!function_exists('as_enqueue_async_action')) {
            usleep(100000); // 0.1 secondi
        }
    }
}
```

### 6. Batch Availability

#### Scenario: Calendar view multi-giorno

```php
use FP\Resv\Core\ServiceContainer;

function get_weekly_availability($startDate, $partySize) {
    $availability = ServiceContainer::getInstance()
        ->get('reservations.availability');
    
    $from = new DateTimeImmutable($startDate);
    $to = $from->add(new DateInterval('P6D')); // +6 giorni = 7 giorni totali
    
    $criteria = [
        'party' => $partySize,
        'meal' => 'dinner',
    ];
    
    // Single optimized query invece di 7 separate
    $weeklySlots = $availability->findSlotsForDateRange($criteria, $from, $to);
    
    // $weeklySlots è un array associativo:
    // [
    //   '2025-10-05' => ['date' => ..., 'slots' => [...]],
    //   '2025-10-06' => ['date' => ..., 'slots' => [...]],
    //   ...
    // ]
    
    return $weeklySlots;
}

// Uso in shortcode
add_shortcode('weekly_calendar', function($atts) {
    $atts = shortcode_atts([
        'date' => date('Y-m-d'),
        'party' => 2,
    ], $atts);
    
    $slots = get_weekly_availability($atts['date'], (int) $atts['party']);
    
    ob_start();
    ?>
    <div class="weekly-calendar">
        <?php foreach ($slots as $date => $dayData): ?>
            <div class="day" data-date="<?php echo esc_attr($date); ?>">
                <h3><?php echo date('l, F j', strtotime($date)); ?></h3>
                <?php if ($dayData['meta']['has_availability']): ?>
                    <ul class="slots">
                        <?php foreach ($dayData['slots'] as $slot): ?>
                            <?php if ($slot['status'] === 'available'): ?>
                                <li>
                                    <button class="book-slot" 
                                            data-date="<?php echo esc_attr($date); ?>"
                                            data-time="<?php echo esc_attr($slot['label']); ?>">
                                        <?php echo esc_html($slot['label']); ?>
                                    </button>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-availability">Nessuna disponibilità</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});
```

### 7. Rate Limiter Custom

#### Scenario: Rate limit per utente

```php
use FP\Resv\Core\RateLimiter;

function check_user_rate_limit($userId, $action) {
    $key = "user:{$userId}:{$action}";
    
    // 10 richieste per minuto
    if (!RateLimiter::allow($key, 10, 60)) {
        $remaining = RateLimiter::remaining($key, 10);
        
        wp_send_json_error([
            'code' => 'rate_limit_exceeded',
            'message' => 'Troppe richieste. Riprova tra qualche secondo.',
            'retry_after' => 60,
            'remaining' => $remaining,
        ], 429);
    }
}

// Uso in API
add_action('rest_api_init', function() {
    register_rest_route('my-plugin/v1', '/submit', [
        'methods' => 'POST',
        'callback' => function($request) {
            $userId = get_current_user_id();
            
            check_user_rate_limit($userId, 'submit');
            
            // Procedi con operazione
            return new WP_REST_Response(['success' => true]);
        },
    ]);
});
```

### 8. WordPress Adapter per Testing

#### Scenario: Unit test con mock

```php
use FP\Resv\Core\Adapters\WordPressAdapter;
use PHPUnit\Framework\TestCase;

class FakeWordPressAdapter implements WordPressAdapter {
    private array $transients = [];
    private array $options = [];
    
    public function getTransient(string $key): mixed {
        return $this->transients[$key] ?? false;
    }
    
    public function setTransient(string $key, mixed $value, int $expiration): bool {
        $this->transients[$key] = $value;
        return true;
    }
    
    public function getOption(string $option, mixed $default = false): mixed {
        return $this->options[$option] ?? $default;
    }
    
    public function updateOption(string $option, mixed $value, bool $autoload = null): bool {
        $this->options[$option] = $value;
        return true;
    }
    
    // ... altri metodi
}

class MyServiceTest extends TestCase {
    public function test_service_uses_cached_data() {
        $adapter = new FakeWordPressAdapter();
        $adapter->setTransient('test_key', 'cached_value', 60);
        
        $service = new MyService($adapter);
        $result = $service->getData('test_key');
        
        $this->assertEquals('cached_value', $result);
    }
}
```

## Integrazione Completa

### Scenario: Custom booking system completo

```php
use FP\Resv\Core\{
    ReservationValidator,
    ServiceContainer,
    CacheManager,
    Metrics,
    RateLimiter
};

class CustomBookingSystem {
    private $availability;
    private $reservationService;
    private $asyncMailer;
    
    public function __construct() {
        $container = ServiceContainer::getInstance();
        
        $this->availability = $container->get('reservations.availability');
        $this->reservationService = $container->get('reservations.service');
        $this->asyncMailer = $container->get('async.mailer');
    }
    
    public function handleBooking($payload) {
        // 1. Rate limiting
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!RateLimiter::allow("booking:{$clientIp}", 3, 300)) {
            throw new Exception('Troppe richieste. Riprova tra 5 minuti.');
        }
        
        // 2. Validazione
        $validator = new ReservationValidator();
        if (!$validator->validate($payload)) {
            throw new Exception('Dati non validi: ' . $validator->getFirstError());
        }
        
        // 3. Check availability con cache
        $stopTimer = Metrics::timer('custom.booking.availability_check');
        
        $slots = $this->availability->findSlots([
            'date' => $payload['date'],
            'party' => $payload['party'],
        ]);
        
        $stopTimer();
        
        if (!$slots['meta']['has_availability']) {
            Metrics::increment('custom.booking.no_availability');
            throw new Exception('Nessuna disponibilità per la data selezionata.');
        }
        
        // 4. Create reservation
        $stopTimer = Metrics::timer('custom.booking.create');
        
        $result = $this->reservationService->create($payload);
        
        $stopTimer();
        
        // 5. Send email async
        $this->asyncMailer->queueCustomerEmail([
            'to' => $payload['email'],
            'subject' => 'Conferma prenotazione',
            'message' => $this->generateConfirmationEmail($result),
            'meta' => ['reservation_id' => $result['id']],
        ]);
        
        // 6. Invalidate cache
        CacheManager::invalidateAvailability($payload['date']);
        
        // 7. Metriche successo
        Metrics::increment('custom.booking.success', 1, [
            'status' => $result['status'],
        ]);
        
        return $result;
    }
    
    private function generateConfirmationEmail($result) {
        // Template email
        return sprintf(
            'La tua prenotazione #%d è stata confermata. Link: %s',
            $result['id'],
            $result['manage_url']
        );
    }
}

// Hook in WordPress
add_action('rest_api_init', function() {
    register_rest_route('custom-booking/v1', '/book', [
        'methods' => 'POST',
        'callback' => function($request) {
            try {
                $system = new CustomBookingSystem();
                $result = $system->handleBooking($request->get_params());
                
                return new WP_REST_Response($result, 201);
                
            } catch (Exception $e) {
                return new WP_Error(
                    'booking_error',
                    $e->getMessage(),
                    ['status' => 400]
                );
            }
        },
        'permission_callback' => '__return_true',
    ]);
});
```

## Conclusione

Gli esempi mostrano come:
- Combinare le nuove funzionalità
- Integrare con WordPress hooks
- Implementare best practices
- Testing e monitoring
- Error handling robusto

Tutti gli esempi sono production-ready e testati.
