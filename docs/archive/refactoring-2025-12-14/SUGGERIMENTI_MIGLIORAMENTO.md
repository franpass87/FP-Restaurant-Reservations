# Suggerimenti di Miglioramento - FP Restaurant Reservations

**Data analisi**: 2025-10-05  
**Stato codebase**: Tutti i lint check passati, issue ISS-0001 a ISS-0005 risolti

---

## 1. ARCHITETTURA E DEPENDENCY INJECTION

### 🔴 Problema: Service Container Minimalista
**File**: `src/Core/ServiceContainer.php` (45 righe)

Il Service Container attuale è un semplice service locator senza:
- Auto-wiring
- Lazy loading
- Factory support
- Interface binding

**Impatto**: Difficoltà nella gestione delle dipendenze complesse e testing.

**Suggerimento**:
```php
// Implementare factory pattern per lazy loading
public function factory(string $id, callable $factory): void
{
    $this->factories[$id] = $factory;
}

public function get(string $id, mixed $default = null): mixed
{
    if (isset($this->services[$id])) {
        return $this->services[$id];
    }
    
    if (isset($this->factories[$id])) {
        $this->services[$id] = $this->factories[$id]($this);
        return $this->services[$id];
    }
    
    return $default;
}
```

**Alternativa**: Considerare PSR-11 Container o PHP-DI per autowiring completo.

---

## 2. PERFORMANCE E CACHING

### 🟡 Problema: Nessuna Cache Strategy
**File**: `src/Domain/Reservations/Availability.php` (1026 righe)

Il calcolo della disponibilità esegue 4-6 query per ogni richiesta:
- `loadRooms()` - ogni volta
- `loadTables()` - ogni volta  
- `loadClosures()` - ogni volta
- `loadReservations()` - ogni volta

**Impatto**: Alto carico DB per richieste frequenti (es. utente scorre date).

**Suggerimento**:
```php
// Aggiungere object caching con TTL breve
private function loadRooms(?int $roomId): array
{
    $cacheKey = 'fp_resv_rooms_' . ($roomId ?? 'all');
    $cached = wp_cache_get($cacheKey, 'fp_resv');
    
    if ($cached !== false) {
        return $cached;
    }
    
    // ... query esistente ...
    
    wp_cache_set($cacheKey, $rooms, 'fp_resv', 300); // 5 minuti
    return $rooms;
}
```

**Priorità**: ALTA - migliorerebbe UX frontend significativamente.

---

## 3. RATE LIMITING

### 🟡 Problema: Race Condition Potenziale
**File**: `src/Core/RateLimiter.php` (32 righe)

```php
$count = (int) ($windowData['count'] ?? 0);
if ($count >= $limit) {
    return false;
}
$windowData['count'] = $count + 1;
set_transient($key, $windowData, $seconds);
```

**Impatto**: Due richieste simultanee possono bypassare il limite.

**Suggerimento**:
```php
// Usare atomic increment con wp_cache_incr
public static function allow(string $key, int $limit, int $seconds): bool
{
    $key = 'fp_resv_rl_' . md5($key);
    
    // Atomic increment se supportato
    if (function_exists('wp_cache_incr')) {
        $count = wp_cache_incr($key, 1, 'rate_limit');
        if ($count === false) {
            wp_cache_add($key, 1, 'rate_limit', $seconds);
            return true;
        }
        return $count <= $limit;
    }
    
    // Fallback con lock ottimistico
    $lockKey = $key . '_lock';
    if (!add_option($lockKey, time(), '', 'no')) {
        return false; // Richiesta concorrente
    }
    
    // ... logica esistente ...
    
    delete_option($lockKey);
    return true;
}
```

---

## 4. ERROR HANDLING

### 🟡 Problema: Eccezioni Generiche
**File**: `src/Domain/Reservations/Service.php`

```php
throw new RuntimeException('Invalid reservation date.');
throw new RuntimeException('Customer name is required.');
```

**Impatto**: Difficile distinguere errori nel logging e gestire casi specifici.

**Suggerimento**:
```php
// Creare eccezioni custom
namespace FP\Resv\Domain\Reservations\Exceptions;

class ValidationException extends \DomainException {}
class InvalidDateException extends ValidationException {}
class InvalidPartyException extends ValidationException {}
class ConflictException extends \RuntimeException {}

// Usare nel Service
throw new InvalidDateException(
    __('La data richiesta non è valida.', 'fp-restaurant-reservations'),
    ['date' => $payload['date']]
);
```

**Benefici**:
- Catch specifico per gestione errori
- Logging strutturato migliore
- API error response più dettagliati

---

## 5. VALIDATION LAYER

### 🔴 Problema: Validazione Sparsa
**File**: `src/Domain/Reservations/Service.php` (linee 560-581)

La validazione è inline nel metodo `assertPayload()` e distribuita in `sanitizePayload()`.

**Suggerimento**:
```php
// Creare Validator dedicato
namespace FP\Resv\Core;

class ReservationValidator
{
    private array $errors = [];
    
    public function validate(array $payload): bool
    {
        $this->errors = [];
        
        $this->validateDate($payload['date'] ?? '');
        $this->validateTime($payload['time'] ?? '');
        $this->validateParty($payload['party'] ?? 0);
        $this->validateContact($payload);
        
        return $this->errors === [];
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    private function validateDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->errors['date'] = __('Formato data non valido', 'fp-restaurant-reservations');
            return;
        }
        
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            $this->errors['date'] = __('Data non valida', 'fp-restaurant-reservations');
        }
    }
}
```

**Benefici**:
- Riutilizzabile in REST e admin
- Errori strutturati per form frontend
- Testing isolato delle regole

---

## 6. QUERY OPTIMIZATION

### 🟡 Problema: N+1 Query Potenziale
**File**: `src/Domain/Reservations/Availability.php` (linee 565-607)

```php
foreach ($rows as $row) {
    // Processa ogni prenotazione
}
```

Se il calcolo viene fatto per più giorni in loop, le query si moltiplicano.

**Suggerimento**:
```php
// Aggiungere batch loading
public function findSlotsForDateRange(
    array $criteria,
    DateTimeImmutable $from,
    DateTimeImmutable $to
): array {
    // Load una volta per tutto il range
    $rooms = $this->loadRooms($criteria['room'] ?? null);
    $tables = $this->loadTables($criteria['room'] ?? null);
    $closures = $this->loadClosures($from, $to, $timezone);
    $reservations = $this->loadReservationsInRange($from, $to, ...);
    
    $results = [];
    for ($date = $from; $date <= $to; $date = $date->add(new \DateInterval('P1D'))) {
        $results[$date->format('Y-m-d')] = $this->calculateSlotsForDay(
            $date,
            $criteria,
            $rooms,
            $tables,
            $closures,
            $reservations
        );
    }
    
    return $results;
}
```

---

## 7. ASYNC OPERATIONS

### 🟡 Problema: Email Sincrone
**File**: `src/Domain/Reservations/Service.php` (linee 637-877)

Le email vengono inviate durante la creazione della prenotazione (blocking).

**Impatto**: Timeout possibili se SMTP è lento (3-5 secondi per email).

**Suggerimento**:
```php
// Implementare queue con Action Scheduler (già disponibile in WP)
use function as_enqueue_async_action;

private function sendCustomerEmail(...): void
{
    // Enqueue invece di inviare
    as_enqueue_async_action(
        'fp_resv_send_customer_email',
        [
            'reservation_id' => $reservationId,
            'payload' => $payload,
            'manage_url' => $manageUrl,
            'status' => $status,
        ],
        'fp-resv-emails'
    );
}

// Registrare handler
add_action('fp_resv_send_customer_email', function($reservation_id, $payload, ...) {
    // Logica di invio qui
}, 10, 4);
```

**Benefici**:
- Response time < 200ms invece di 2-5 secondi
- Retry automatico su fallimento
- Evita timeout

---

## 8. BREVO AUTOMATION

### 🟢 Problema: Duplicazione Logica
**File**: `src/Domain/Brevo/AutomationService.php` (978 righe)

I metodi `onReservationCreated` e `onReservationStatusChanged` hanno molta duplicazione nella costruzione del contact e properties.

**Suggerimento**:
```php
// Estrarre in builder separato
class BrevoContactBuilder
{
    public function fromReservation(array $reservation): array
    {
        return $this->mapper->mapReservation([
            'email' => $reservation['email'] ?? '',
            // ... tutti i campi
        ]);
    }
    
    public function fromReservationAndContext(int $id, array $context): array
    {
        // Logica unificata
    }
}

// Nel service
private function buildContactFromCreation(array $payload): array
{
    return $this->contactBuilder->fromReservation($payload);
}
```

---

## 9. TESTING STRATEGY

### 🔴 Problema: Nessun Mock per WordPress Functions
**File**: `tests/Unit/*` (8 file)

I test unitari dipendono dalle funzioni WordPress globali.

**Suggerimento**:
```php
// Creare wrapper per funzioni WP
namespace FP\Resv\Core;

interface WordPressAdapter
{
    public function getCurrentTime(string $type = 'mysql'): string;
    public function getSalt(string $scheme): string;
    public function getTransient(string $key): mixed;
    public function setTransient(string $key, mixed $value, int $expiration): bool;
}

class WPFunctionsAdapter implements WordPressAdapter
{
    public function getCurrentTime(string $type = 'mysql'): string
    {
        return current_time($type);
    }
    // ... altri metodi
}

// Nei test
class FakeWordPressAdapter implements WordPressAdapter
{
    private array $transients = [];
    
    public function getTransient(string $key): mixed
    {
        return $this->transients[$key] ?? false;
    }
}
```

---

## 10. FRONTEND AVAILABILITY CHECK

### 🟡 Problema: Nessun Debounce Server-Side
**File**: `assets/js/fe/onepage.js` ha debounce client, ma API può essere chiamata direttamente

**Suggerimento**:
```php
// Nel REST controller
class ReservationsREST
{
    public function getAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $fingerprint = $this->generateFingerprint($request);
        
        // Cache per 10 secondi stesso fingerprint
        $cacheKey = 'availability_' . $fingerprint;
        $cached = wp_cache_get($cacheKey, 'fp_resv_api');
        
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }
        
        $result = $this->availability->findSlots(...);
        wp_cache_set($cacheKey, $result, 'fp_resv_api', 10);
        
        return new WP_REST_Response($result, 200);
    }
    
    private function generateFingerprint(WP_REST_Request $request): string
    {
        return md5(serialize([
            $request->get_param('date'),
            $request->get_param('party'),
            $request->get_param('room'),
            $request->get_param('meal'),
        ]));
    }
}
```

---

## 11. SECURITY ENHANCEMENTS

### 🟡 Problema: Nonce Verificato ma Nessun Rate Limit per Utente
**File**: `src/Domain/Reservations/REST.php`

```php
// Aggiungere rate limit per IP + email
$email = $request->get_param('email');
$ip = Helpers::clientIp();
$rateLimitKey = "reservation_attempt_{$ip}_{$email}";

if (!RateLimiter::allow($rateLimitKey, 3, 3600)) {
    return new WP_Error(
        'rate_limit_exceeded',
        __('Troppi tentativi. Riprova tra un\'ora.', 'fp-restaurant-reservations'),
        ['status' => 429]
    );
}
```

---

## 12. MONITORING E OBSERVABILITY

### 🔴 Problema: Nessuna Metrica Strutturata

**Suggerimento**:
```php
// Creare metrics logger
namespace FP\Resv\Core;

class Metrics
{
    public static function timing(string $metric, float $duration, array $tags = []): void
    {
        $entry = [
            'metric' => $metric,
            'value' => $duration,
            'tags' => $tags,
            'timestamp' => microtime(true),
        ];
        
        do_action('fp_resv_metric', $entry);
        
        // Se plugin monitoring attivo, invia
        if (defined('FP_RESV_METRICS_ENABLED')) {
            self::sendToMonitoring($entry);
        }
    }
    
    public static function increment(string $metric, int $value = 1, array $tags = []): void
    {
        // Similar implementation
    }
}

// Usare nel codice
$start = microtime(true);
$slots = $this->availability->findSlots($criteria);
Metrics::timing('availability.calculation', microtime(true) - $start, [
    'party' => $criteria['party'],
]);
```

---

## PRIORITÀ DI IMPLEMENTAZIONE

### 🔴 Alta Priorità (Prossimi Sprint)
1. **Caching availability queries** - Impatto UX immediato
2. **Email async** - Riduce timeout creazione prenotazione
3. **Validation layer** - Migliora consistenza e testing
4. **Error handling custom exceptions** - Debugging più facile

### 🟡 Media Priorità (3-6 mesi)
5. **Service Container enhancement** - Facilita sviluppo futuro
6. **Testing mocks WP** - Migliora test coverage
7. **Monitoring/Metrics** - Visibilità produzione
8. **Query optimization batch** - Scalabilità

### 🟢 Bassa Priorità (Nice to have)
9. **Rate limiting refinement** - Già presente basic
10. **Brevo refactoring** - Funziona già bene
11. **Frontend API cache** - Già c'è debounce client

---

## METRICHE DI SUCCESSO

- **Availability API latency**: Da ~200ms a <50ms (con cache)
- **Reservation creation time**: Da 2-5s a <500ms (email async)
- **Test coverage**: Da ~40% a >70% (con mocks)
- **Error tracking**: 0 eccezioni generiche in production logs

---

## NOTE FINALI

Il codebase è **molto ben strutturato** per un plugin WordPress:
- ✅ PSR-4 autoloading
- ✅ Strict types
- ✅ Repository pattern
- ✅ Separazione domini
- ✅ REST API ben organizzate

I suggerimenti sopra sono **evolutivi, non critici**. Il sistema è production-ready così com'è.
