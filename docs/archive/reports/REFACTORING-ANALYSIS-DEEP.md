# 🔧 Analisi Approfondita Modularizzazione e Refactoring

**Data:** 19 Novembre 2025  
**Plugin:** FP Restaurant Reservations v0.9.0-rc10.3  
**Obiettivo:** Analisi approfondita con metodi lunghi, pattern ripetuti e ulteriori opportunità

---

## 📊 METODI LUNGHI IDENTIFICATI

### 🔴 Metodi Critici (>100 righe)

| File | Metodo | Righe | Complessità | Priorità |
|------|--------|-------|-------------|----------|
| **Service.php** | `create()` | ~320 | Alta | 🔴 Alta |
| **Service.php** | `sanitizePayload()` | ~135 | Media | 🔴 Alta |
| **Service.php** | `sendStaffNotifications()` | ~125 | Media | 🔴 Alta |
| **Service.php** | `sendCustomerEmail()` | ~115 | Media | 🔴 Alta |
| **Service.php** | `buildReservationContext()` | ~90 | Bassa | 🟡 Media |
| **Availability.php** | `findSlots()` | ~220 | Alta | 🔴 Alta |
| **Availability.php** | `calculateSlotsForDay()` | ~195 | Alta | 🔴 Alta |
| **Availability.php** | `resolveMealSettings()` | ~85 | Media | 🟡 Media |
| **Availability.php** | `evaluateClosures()` | ~45 | Alta | 🟡 Media |
| **AdminREST.php** | `handleAgendaV2()` | ~160 | Alta | 🔴 Alta |
| **AdminREST.php** | `handleCreateReservation()` | ~170 | Alta | 🔴 Alta |
| **AdminREST.php** | `handleUpdateReservation()` | ~115 | Media | 🟡 Media |
| **AdminREST.php** | `calculateDetailedStats()` | ~80 | Alta | 🟡 Media |
| **AdminPages.php** | `sanitizePageOptions()` | ~40 | Alta | 🟡 Media |
| **AdminPages.php** | `sanitizeField()` | ~110 | Alta | 🔴 Alta |
| **AdminPages.php** | `validatePage()` | ~135 | Alta | 🔴 Alta |

---

## 🔄 PATTERN RIPETUTI IDENTIFICATI

### 1. **Sanitizzazione Campi** (Ripetuto in 5+ file)

**Pattern attuale:**
```php
$payload['field'] = sanitize_text_field((string) $payload['field']);
$payload['email'] = sanitize_email((string) $payload['email']);
$payload['notes'] = sanitize_textarea_field((string) $payload['notes']);
```

**Proposta:**
```php
// Nuovo: Core/Sanitizer.php
class Sanitizer {
    public static function sanitizeField(mixed $value, string $type): mixed
    public static function sanitizePayload(array $payload, array $rules): array
    public static function sanitizeEmail(string $email): string
    public static function sanitizePhone(string $phone): string
}
```

**Benefici:**
- Riduce duplicazione in Service.php, AdminREST.php, AdminPages.php
- Validazione centralizzata
- Più facile mantenere e testare

---

### 2. **Validazione Date/Time** (Ripetuto in 3+ file)

**Pattern attuale:**
```php
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    throw new InvalidDateException(...);
}
$dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, $timezone);
```

**Proposta:**
```php
// Nuovo: Core/DateTimeValidator.php
class DateTimeValidator {
    public static function validateDate(string $date, ?DateTimeZone $tz = null): DateTimeImmutable
    public static function validateTime(string $time): array
    public static function validateDateTime(string $date, string $time, ?DateTimeZone $tz = null): DateTimeImmutable
    public static function isPast(DateTimeImmutable $dt): bool
}
```

**Benefici:**
- Riduce duplicazione in Service.php, Availability.php, AdminREST.php
- Logica timezone centralizzata
- Test più semplici

---

### 3. **Formattazione Risposte REST** (Ripetuto in 4+ file)

**Pattern attuale:**
```php
return rest_ensure_response([
    'success' => true,
    'data' => $data,
]);
```

**Proposta:**
```php
// Nuovo: Core/REST/ResponseBuilder.php
class ResponseBuilder {
    public static function success(mixed $data, int $code = 200): WP_REST_Response
    public static function error(string $message, int $code = 400, array $data = []): WP_Error
    public static function paginated(array $items, int $total, int $page, int $perPage): WP_REST_Response
}
```

**Benefici:**
- Standardizzazione risposte API
- Riduce duplicazione in REST.php, AdminREST.php, Reports/REST.php
- Formattazione consistente

---

### 4. **Gestione Errori e Logging** (Ripetuto ovunque)

**Pattern attuale:**
```php
try {
    // operation
} catch (Throwable $e) {
    Logging::log('context', 'message', ['error' => $e->getMessage()]);
    throw new RuntimeException('User message');
}
```

**Proposta:**
```php
// Nuovo: Core/ErrorHandler.php
class ErrorHandler {
    public static function handle(Throwable $e, string $context, ?string $userMessage = null): never
    public static function wrap(callable $fn, string $context, ?string $userMessage = null): mixed
    public static function logAndThrow(Throwable $e, string $context, string $userMessage): never
}
```

**Benefici:**
- Gestione errori consistente
- Logging automatico
- Meno boilerplate

---

### 5. **Caricamento Opzioni Settings** (Ripetuto in 10+ file)

**Pattern attuale:**
```php
$value = $this->options->getField('fp_resv_general', 'field_name', 'default');
$group = $this->options->getGroup('fp_resv_general', ['default' => 'value']);
```

**Proposta:**
```php
// Nuovo: Domain/Settings/SettingsReader.php (wrapper)
class SettingsReader {
    public function get(string $group, string $field, mixed $default = null): mixed
    public function getGroup(string $group, array $defaults = []): array
    public function getBool(string $group, string $field, bool $default = false): bool
    public function getInt(string $group, string $field, int $default = 0): int
    public function getString(string $group, string $field, string $default = ''): string
}
```

**Benefici:**
- Type safety migliorata
- Meno casting manuale
- API più pulita

---

## 🎯 OPPORTUNITÀ AGGIUNTIVE

### 6. **Value Objects per Dati Prenotazione**

**Problema attuale:**
- Array associativi passati ovunque
- Nessuna type safety
- Validazione sparsa

**Proposta:**
```php
// Nuovo: Domain/Reservations/ValueObjects/
class ReservationPayload {
    public function __construct(
        public readonly string $date,
        public readonly string $time,
        public readonly int $party,
        public readonly ContactInfo $contact,
        public readonly ?PaymentInfo $payment = null,
        // ...
    ) {}
}

class ContactInfo {
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        // ...
    ) {}
}
```

**Benefici:**
- Type safety
- Validazione centralizzata
- Meno errori runtime
- IDE support migliore

---

### 7. **Strategy Pattern per Calcolo Disponibilità**

**Problema attuale:**
- Logica complessa in `calculateSlotsForDay()`
- Difficile estendere con nuove strategie

**Proposta:**
```php
// Nuovo: Domain/Reservations/Availability/Strategies/
interface CapacityStrategy {
    public function calculate(int $baseCapacity, array $tables, array $reservations): int;
}

class DefaultCapacityStrategy implements CapacityStrategy { }
class TableBasedCapacityStrategy implements CapacityStrategy { }
class RoomBasedCapacityStrategy implements CapacityStrategy { }
```

**Benefici:**
- Estendibilità
- Testabilità
- Separazione logica

---

### 8. **Factory Pattern per Email Templates**

**Problema attuale:**
- Logica rendering email sparsa
- Difficile aggiungere nuovi template

**Proposta:**
```php
// Nuovo: Domain/Notifications/EmailFactory.php
class EmailFactory {
    public function createConfirmationEmail(Reservation $reservation): Email
    public function createStaffNotificationEmail(Reservation $reservation): Email
    public function createCancellationEmail(Reservation $reservation): Email
}
```

**Benefici:**
- Centralizzazione creazione email
- Facile aggiungere nuovi tipi
- Test più semplici

---

### 9. **Repository Pattern Migliorato**

**Problema attuale:**
- Repository.php ha metodi molto specifici
- Query SQL sparse

**Proposta:**
```php
// Nuovo: Domain/Reservations/Repository/QueryBuilder.php
class ReservationQueryBuilder {
    public function whereDate(string $date): self
    public function whereStatus(array $statuses): self
    public function whereRoom(?int $roomId): self
    public function whereTimeRange(string $start, string $end): self
    public function get(): array
    public function count(): int
}
```

**Benefici:**
- Query più leggibili
- Riutilizzabilità
- Test più semplici

---

### 10. **Command Pattern per Operazioni Admin**

**Problema attuale:**
- AdminREST.php ha molti handler lunghi
- Logica business mescolata con HTTP

**Proposta:**
```php
// Nuovo: Domain/Reservations/Commands/
interface Command {
    public function execute(): CommandResult;
}

class CreateReservationCommand implements Command { }
class UpdateReservationCommand implements Command { }
class MoveReservationCommand implements Command { }
class DeleteReservationCommand implements Command { }

// AdminREST.php diventa più semplice:
public function handleCreateReservation(WP_REST_Request $request) {
    $command = new CreateReservationCommand($request->get_params());
    $result = $command->execute();
    return ResponseBuilder::success($result);
}
```

**Benefici:**
- Separazione concerns
- Testabilità
- Riutilizzabilità
- Logging/audit più semplice

---

## 📋 PIANO DI REFACTORING AGGIORNATO

### Fase 0: Foundation (2-3 giorni) - NUOVO
- [ ] Creare Core/Sanitizer.php
- [ ] Creare Core/DateTimeValidator.php
- [ ] Creare Core/REST/ResponseBuilder.php
- [ ] Creare Core/ErrorHandler.php
- [ ] Creare Domain/Settings/SettingsReader.php
- [ ] Aggiornare file esistenti per usare nuove utility

**Benefici immediati:**
- Riduce duplicazione
- Migliora type safety
- Facilita refactoring successivi

---

### Fase 1: Service.php (3-4 giorni) - AGGIORNATO
- [ ] Usare Core/Sanitizer per sanitizePayload()
- [ ] Estrarre EmailService
- [ ] Estrarre PaymentService
- [ ] Estrarre AvailabilityGuard
- [ ] Creare ReservationPayload Value Object
- [ ] Refactor create() per usare Value Object
- [ ] Test completi

**Risultato atteso:** Service.php da 1442 → ~400 righe

---

### Fase 2: Availability.php (3-4 giorni) - AGGIORNATO
- [ ] Usare Core/DateTimeValidator
- [ ] Estrarre DataLoader
- [ ] Estrarre ClosureEvaluator
- [ ] Estrarre SlotCalculator
- [ ] Implementare Strategy Pattern per capacità
- [ ] Estrarre ScheduleParser
- [ ] Refactor findSlots() e calculateSlotsForDay()
- [ ] Test completi

**Risultato atteso:** Availability.php da 1513 → ~400 righe

---

### Fase 3: AdminREST.php (3-4 giorni) - AGGIORNATO
- [ ] Implementare Command Pattern
- [ ] Estrarre AgendaHandler
- [ ] Estrarre ReservationHandler (usa Commands)
- [ ] Estrarre ExportHandler
- [ ] Usare ResponseBuilder
- [ ] Refactor handlers per essere più semplici
- [ ] Test completi

**Risultato atteso:** AdminREST.php da 1658 → ~400 righe

---

### Fase 4: AdminPages.php (2-3 giorni) - AGGIORNATO
- [ ] Usare Core/Sanitizer
- [ ] Estrarre PageRenderer
- [ ] Estrarre FormValidator (usa SettingsReader)
- [ ] Estrarre SettingsHandler
- [ ] Refactor sanitizeField() per usare Sanitizer
- [ ] Test completi

**Risultato atteso:** AdminPages.php da 1778 → ~500 righe

---

### Fase 5: REST.php (2 giorni) - AGGIORNATO
- [ ] Usare ResponseBuilder
- [ ] Estrarre RequestValidator (usa DateTimeValidator)
- [ ] Estrarre ResponseFormatter
- [ ] Estrarre CacheManager
- [ ] Test completi

**Risultato atteso:** REST.php da 1125 → ~400 righe

---

### Fase 6: Repository Pattern (2 giorni) - NUOVO
- [ ] Creare QueryBuilder
- [ ] Refactor Repository.php per usare QueryBuilder
- [ ] Migrare query SQL esistenti
- [ ] Test completi

**Benefici:**
- Query più leggibili
- Meno SQL hardcoded
- Più facile testare

---

### Fase 7: Testing e Cleanup (3-4 giorni) - AGGIORNATO
- [ ] Test end-to-end completi
- [ ] Verifica performance
- [ ] Code review approfondita
- [ ] Documentazione aggiornata
- [ ] Refactoring finale
- [ ] Merge in main

**Tempo totale stimato:** 20-25 giorni (incrementato per foundation)

---

## ✅ BENEFICI AGGIUNTIVI

### Type Safety
- ✅ Value Objects invece di array
- ✅ Type hints migliorati
- ✅ Meno errori runtime

### Testabilità
- ✅ Commands isolati e testabili
- ✅ Strategies testabili separatamente
- ✅ Mock più facili

### Manutenibilità
- ✅ Pattern ripetuti eliminati
- ✅ Utility centralizzate
- ✅ Codice più DRY

### Estendibilità
- ✅ Strategy Pattern per nuove logiche
- ✅ Command Pattern per nuove operazioni
- ✅ Factory Pattern per nuovi template

---

## 📊 METRICHE DI SUCCESSO AGGIORNATE

- [ ] Nessun file >1000 righe
- [ ] Nessun metodo >100 righe
- [ ] Complessità ciclomatica <10 per metodo
- [ ] Coverage test >85%
- [ ] Zero duplicazione pattern comuni
- [ ] Type safety migliorata (meno array, più Value Objects)
- [ ] Performance invariata o migliorata
- [ ] Code review positivo

---

## ⚠️ RISCHI AGGIUNTIVI

### Rischio 4: Over-abstraction
**Mitigazione:**
- Applicare pattern solo dove necessario
- Evitare astrazioni premature
- Mantenere semplicità

### Rischio 5: Breaking Changes Value Objects
**Mitigazione:**
- Implementare gradualmente
- Mantenere compatibilità backward
- Test estensivi

---

## 🎯 PRIORITÀ IMPLEMENTAZIONE

### Alta Priorità (Fondamentale)
1. ✅ Core utilities (Sanitizer, DateTimeValidator, ResponseBuilder)
2. ✅ Service.php refactoring
3. ✅ Availability.php refactoring
4. ✅ AdminREST.php refactoring

### Media Priorità (Importante)
5. ✅ AdminPages.php refactoring
6. ✅ REST.php refactoring
7. ✅ Repository QueryBuilder

### Bassa Priorità (Nice to have)
8. ⚪ Value Objects (se tempo disponibile)
9. ⚪ Command Pattern (se tempo disponibile)
10. ⚪ Strategy Pattern (se tempo disponibile)

---

**Creato:** 19 Novembre 2025  
**Status:** 📋 Analisi approfondita completata  
**Versione:** 2.0 (con pattern ripetuti e metodi lunghi)
















