# ✅ Fase 0: Foundation Utilities - COMPLETATA

**Data:** 19 Novembre 2025  
**Status:** ✅ Completata

---

## 📦 UTILITY CREATE

### 1. ✅ Core/Sanitizer.php
**Scopo:** Sanitizzazione centralizzata di dati  
**Metodi principali:**
- `sanitizeField()` - Sanitizza campo per tipo
- `sanitizePayload()` - Sanitizza payload completo
- `sanitizeEmail()`, `sanitizeUrl()`, `sanitizeInteger()`, etc.
- `validateEmail()` - Validazione email con risultato strutturato

**Benefici:**
- Elimina duplicazione in Service.php, AdminREST.php, AdminPages.php
- API consistente per sanitizzazione
- Type safety migliorata

---

### 2. ✅ Core/DateTimeValidator.php
**Scopo:** Validazione centralizzata di date e orari  
**Metodi principali:**
- `validateDate()` - Valida e crea DateTimeImmutable da stringa
- `validateTime()` - Valida formato HH:MM
- `validateDateTime()` - Valida combinazione data+ora
- `isPast()`, `isFuture()`, `isToday()` - Verifiche temporali
- `assertNotPast()`, `assertDateTimeNotPast()` - Validazioni con eccezioni

**Benefici:**
- Elimina duplicazione in Service.php, Availability.php, AdminREST.php
- Gestione timezone centralizzata
- Validazione consistente

---

### 3. ✅ Core/REST/ResponseBuilder.php
**Scopo:** Costruzione risposte REST API standardizzate  
**Metodi principali:**
- `success()` - Risposta di successo
- `error()` - Risposta di errore
- `paginated()` - Risposta paginata
- `validationError()` - Errori di validazione
- `unauthorized()`, `notFound()`, `serverError()`, `conflict()` - Errori HTTP specifici

**Benefici:**
- Standardizzazione risposte API
- Elimina duplicazione in REST.php, AdminREST.php, Reports/REST.php
- Formattazione consistente

---

### 4. ✅ Core/ErrorHandler.php
**Scopo:** Gestione errori consistente con logging automatico  
**Metodi principali:**
- `handle()` - Gestisce eccezione con logging e lancia RuntimeException
- `wrap()` - Esegue funzione con gestione errori automatica
- `logAndThrow()` - Logga e lancia eccezione
- `log()` - Logga senza lanciare (errori non critici)
- `toArray()` - Converte eccezione in array per API

**Benefici:**
- Gestione errori consistente
- Logging automatico
- Meno boilerplate

---

### 5. ✅ Domain/Settings/SettingsReader.php
**Scopo:** Wrapper type-safe per Options  
**Metodi principali:**
- `getString()` - Ottiene campo come stringa
- `getInt()` - Ottiene campo come intero
- `getFloat()` - Ottiene campo come float
- `getBool()` - Ottiene campo come booleano
- `getArray()` - Ottiene campo come array
- `has()` - Verifica esistenza campo

**Benefici:**
- Type safety migliorata
- Meno casting manuale
- API più pulita

---

## 📝 ESEMPI DI UTILIZZO

### Esempio 1: Sanitizzazione in Service.php

**Prima:**
```php
$payload['date'] = sanitize_text_field((string) $payload['date']);
$payload['email'] = sanitize_email((string) $payload['email']);
$payload['party'] = max(1, absint($payload['party']));
$payload['currency'] = strtoupper(substr((string) $payload['currency'], 0, 3));
```

**Dopo:**
```php
use FP\Resv\Core\Sanitizer;

$payload['date'] = Sanitizer::sanitizeDate($payload['date']);
$payload['email'] = Sanitizer::sanitizeEmail($payload['email']);
$payload['party'] = Sanitizer::sanitizeInteger($payload['party'], ['min' => 1]);
$payload['currency'] = Sanitizer::sanitizeCurrency($payload['currency']);
```

---

### Esempio 2: Validazione Date/Time

**Prima:**
```php
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    throw new InvalidDateException(...);
}
$dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, $timezone);
if (!$dt instanceof DateTimeImmutable) {
    throw new InvalidDateException(...);
}
```

**Dopo:**
```php
use FP\Resv\Core\DateTimeValidator;

$dt = DateTimeValidator::validateDate($date, $timezone);
DateTimeValidator::assertDateTimeNotPast($date, $time, $timezone);
```

---

### Esempio 3: Risposte REST

**Prima:**
```php
return rest_ensure_response([
    'success' => true,
    'data' => $data,
]);
```

**Dopo:**
```php
use FP\Resv\Core\REST\ResponseBuilder;

return ResponseBuilder::success($data);
// o per errori:
return ResponseBuilder::error('Messaggio errore', 400);
```

---

### Esempio 4: Gestione Errori

**Prima:**
```php
try {
    // operation
} catch (Throwable $e) {
    Logging::log('context', 'message', ['error' => $e->getMessage()]);
    throw new RuntimeException('User message');
}
```

**Dopo:**
```php
use FP\Resv\Core\ErrorHandler;

ErrorHandler::wrap(function() {
    // operation
}, 'context', 'User message');
```

---

### Esempio 5: Settings Type-Safe

**Prima:**
```php
$value = $this->options->getField('fp_resv_general', 'field_name', 'default');
$int = (int) $value;
$bool = ($value === '1' || $value === true);
```

**Dopo:**
```php
use FP\Resv\Domain\Settings\SettingsReader;

$reader = new SettingsReader($this->options);
$string = $reader->getString('fp_resv_general', 'field_name', 'default');
$int = $reader->getInt('fp_resv_general', 'field_name', 0);
$bool = $reader->getBool('fp_resv_general', 'field_name', false);
```

---

## 🎯 PROSSIMI PASSI

### Fase 1: Service.php Refactoring
- [ ] Usare Sanitizer per sanitizePayload()
- [ ] Usare DateTimeValidator per validazione date/time
- [ ] Estrarre EmailService
- [ ] Estrarre PaymentService
- [ ] Estrarre AvailabilityGuard

### Fase 2: Availability.php Refactoring
- [ ] Usare DateTimeValidator
- [ ] Estrarre DataLoader
- [ ] Estrarre ClosureEvaluator
- [ ] Estrarre SlotCalculator

### Fase 3: AdminREST.php Refactoring
- [ ] Usare ResponseBuilder
- [ ] Implementare Command Pattern
- [ ] Estrarre handlers

---

## ✅ VERIFICA

- [x] Tutte le utility create
- [x] Nessun errore di linting
- [x] Namespace corretti
- [x] Type hints completi
- [x] Documentazione inline
- [ ] Test unitari (da creare)
- [ ] Esempi di utilizzo in file esistenti (da implementare)

---

**Completato:** 19 Novembre 2025  
**Prossima fase:** Refactoring Service.php
















