# Architecture Extensions - Events & Integrations

## Overview

This document describes the extensions made to the core architecture to support additional modules and integrations.

## Events Module

### Structure

```
Domain/Events/
├── Models/
│   └── Event.php                    # Event domain model
└── Repositories/
    └── EventRepositoryInterface.php # Event repository contract
```

### Event Model

The `Event` model represents restaurant events (special dinners, wine tastings, etc.) with:
- Title and description
- Start and end dates
- Capacity management (max capacity, current bookings)
- Active/inactive status

### Usage Example

```php
use FP\Resv\Domain\Events\Models\Event;
use DateTimeImmutable;

$event = new Event(
    'Wine Tasting Evening',
    'A special evening with wine pairings',
    new DateTimeImmutable('2025-12-15 19:00:00'),
    new DateTimeImmutable('2025-12-15 23:00:00'),
    30 // max capacity
);

if ($event->hasAvailability()) {
    $available = $event->getAvailableCapacity();
    // Book the event...
}
```

## Integrations Module

### Structure

```
Domain/Integrations/
├── EmailProviderInterface.php       # Email service contract
└── CalendarProviderInterface.php    # Calendar service contract

Infrastructure/External/
├── Brevo/
│   └── BrevoEmailProvider.php      # Brevo implementation
├── GoogleCalendar/
│   └── GoogleCalendarProvider.php   # Google Calendar implementation
├── NoOpEmailProvider.php            # Null implementation
└── NoOpCalendarProvider.php         # Null implementation
```

### Email Provider

The `EmailProviderInterface` abstracts email sending functionality:

```php
use FP\Resv\Domain\Integrations\EmailProviderInterface;

// Injected via DI
$emailProvider->send(
    'customer@example.com',
    'Reservation Confirmation',
    '<html>Email body</html>',
    ['sender_name' => 'Restaurant']
);
```

**Implementations:**
- `BrevoEmailProvider`: Uses Brevo (Sendinblue) API
- `NoOpEmailProvider`: Null implementation when not configured

### Calendar Provider

The `CalendarProviderInterface` abstracts calendar integration:

```php
use FP\Resv\Domain\Integrations\CalendarProviderInterface;
use DateTimeImmutable;

// Injected via DI
$calendarProvider->createEvent(
    'Reservation - John Doe',
    'Customer details...',
    new DateTimeImmutable('2025-12-15 19:00:00'),
    new DateTimeImmutable('2025-12-15 21:00:00'),
    ['timezone' => 'Europe/Rome']
);
```

**Implementations:**
- `GoogleCalendarProvider`: Uses Google Calendar API
- `NoOpCalendarProvider`: Null implementation when not configured

## Integration Service Provider

The `IntegrationServiceProvider` registers integration services conditionally:

- If API keys are configured → Real implementations
- If not configured → No-op implementations (graceful degradation)

This ensures the plugin works even without external integrations configured.

## Use Case Example: NotifyReservationUseCase

The `NotifyReservationUseCase` demonstrates how to use integration services:

```php
use FP\Resv\Application\Reservations\NotifyReservationUseCase;

// Injected via DI
$notifyUseCase = $container->get(NotifyReservationUseCase::class);

// Send confirmation email
$notifyUseCase->sendConfirmationEmail($reservation);

// Create calendar event
$notifyUseCase->createCalendarEvent($reservation);
```

## Configuration

Integration services are configured via WordPress options:

- `brevo_api_key`: Brevo API key
- `google_calendar_access_token`: Google Calendar OAuth token
- `google_calendar_id`: Google Calendar ID (default: 'primary')

## Benefits

1. **Separation of Concerns**: Domain interfaces are WordPress-agnostic
2. **Testability**: Easy to mock integration services
3. **Flexibility**: Can swap implementations without changing domain code
4. **Graceful Degradation**: No-op implementations ensure plugin works without integrations
5. **Extensibility**: Easy to add new providers (Outlook, Mailchimp, etc.)

## Future Extensions

### Payment Providers

```
Domain/Integrations/
└── PaymentProviderInterface.php

Infrastructure/External/
├── Stripe/
│   └── StripePaymentProvider.php
└── PayPal/
    └── PayPalPaymentProvider.php
```

### SMS Providers

```
Domain/Integrations/
└── SmsProviderInterface.php

Infrastructure/External/
├── Twilio/
│   └── TwilioSmsProvider.php
└── MessageBird/
    └── MessageBirdSmsProvider.php
```

## Migration Path

To migrate existing integration code:

1. **Extract Interface**: Define interface in `Domain/Integrations/`
2. **Create Implementation**: Move existing code to `Infrastructure/External/`
3. **Register in Provider**: Add to `IntegrationServiceProvider`
4. **Update Use Cases**: Inject interface instead of concrete class
5. **Test**: Verify integration still works

## Best Practices

1. **Always use interfaces** in domain and application layers
2. **Implement no-op versions** for optional integrations
3. **Log all integration calls** for debugging
4. **Handle errors gracefully** - don't break the main flow
5. **Use dependency injection** - never instantiate directly










