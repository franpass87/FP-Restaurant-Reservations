# Migration Guide - Nuova Architettura

Questa guida spiega come migrare gradualmente il codice esistente alla nuova architettura.

---

## 🎯 Strategia di Migrazione

### Principi
1. **Coesistenza**: Nuova e vecchia architettura possono coesistere
2. **Migrazione Incrementale**: Un modulo alla volta
3. **Backward Compatibility**: Nessun breaking change
4. **Testing Continuo**: Verificare dopo ogni migrazione

---

## 📋 Checklist Migrazione

### Step 1: Verificare Foundation
- [ ] Plugin si attiva correttamente
- [ ] Container funziona
- [ ] Service Providers si registrano
- [ ] Nessun errore fatale

### Step 2: Migrare Logging
- [ ] Sostituire `error_log()` con `LegacyServiceAdapter::logDebug()`
- [ ] Sostituire log critici con `LegacyServiceAdapter::logError()`
- [ ] Verificare che i log appaiano correttamente

### Step 3: Migrare Cache
- [ ] Identificare usi di `get_transient()` / `set_transient()`
- [ ] Sostituire con `CacheInterface` dal container
- [ ] Verificare che la cache funzioni

### Step 4: Migrare Options
- [ ] Identificare usi di `get_option()` / `update_option()`
- [ ] Sostituire con `OptionsInterface` dal container
- [ ] Verificare che le opzioni funzionino

### Step 5: Migrare REST Endpoints
- [ ] Identificare endpoint REST esistenti
- [ ] Creare nuovi endpoint usando `ReservationsEndpoint` come esempio
- [ ] Mantenere vecchi endpoint durante transizione
- [ ] Testare nuovi endpoint
- [ ] Deprecare vecchi endpoint
- [ ] Rimuovere vecchi endpoint

### Step 6: Migrare Admin Controllers
- [ ] Identificare controller admin esistenti
- [ ] Refactorare per usare `ReservationsController` come esempio
- [ ] Usare Use Cases invece di logica diretta
- [ ] Testare funzionalità admin

### Step 7: Migrare Shortcodes
- [ ] Identificare shortcodes esistenti
- [ ] Refactorare per usare `ReservationsShortcode` come esempio
- [ ] Usare Use Cases per business logic
- [ ] Testare rendering frontend

---

## 🔧 Esempi di Migrazione

### Esempio 1: Sostituire error_log()

**Prima:**
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[FP Resv] Debug message');
}
```

**Dopo:**
```php
use FP\Resv\Core\Adapters\LegacyServiceAdapter;

LegacyServiceAdapter::logDebug('Debug message', ['context' => 'data']);
```

**Oppure (se hai accesso al container):**
```php
$logger = \FP\Resv\Kernel\LegacyBridge::get(\FP\Resv\Core\Services\LoggerInterface::class);
$logger->debug('Debug message', ['context' => 'data']);
```

### Esempio 2: Sostituire get_transient()

**Prima:**
```php
$cached = get_transient('fp_resv_cache_key');
if ($cached === false) {
    $cached = expensive_operation();
    set_transient('fp_resv_cache_key', $cached, 3600);
}
```

**Dopo:**
```php
$cache = \FP\Resv\Kernel\LegacyBridge::get(\FP\Resv\Core\Services\CacheInterface::class);
$cached = $cache->get('cache_key');
if ($cached === null) {
    $cached = expensive_operation();
    $cache->set('cache_key', $cached, 3600);
}
```

### Esempio 3: Sostituire get_option()

**Prima:**
```php
$value = get_option('fp_resv_setting_name', 'default');
update_option('fp_resv_setting_name', $newValue);
```

**Dopo:**
```php
$options = \FP\Resv\Kernel\LegacyBridge::get(\FP\Resv\Core\Services\OptionsInterface::class);
$value = $options->get('setting_name', 'default');
$options->set('setting_name', $newValue);
```

### Esempio 4: Migrare REST Endpoint

**Prima (vecchio codice):**
```php
class REST {
    public function handleCreate(WP_REST_Request $request) {
        // Business logic qui
        $data = $request->get_json_params();
        // Validazione
        // Salvataggio
        // ...
    }
}
```

**Dopo (nuova architettura):**
```php
class ReservationsEndpoint extends BaseEndpoint {
    public function __construct(
        private readonly CreateReservationUseCase $createUseCase,
        // ...
    ) {}
    
    public function create(WP_REST_Request $request) {
        try {
            $data = $this->sanitizeData($request->get_json_params());
            $reservation = $this->createUseCase->execute($data);
            return $this->success($reservation->toArray(), 201);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        }
    }
}
```

### Esempio 5: Migrare Admin Function

**Prima:**
```php
function create_reservation($data) {
    global $wpdb;
    // Validazione
    // Inserimento DB
    // Logging
    // ...
}
```

**Dopo:**
```php
$controller = \FP\Resv\Kernel\LegacyBridge::get(
    \FP\Resv\Presentation\Admin\Controllers\ReservationsController::class
);
$result = $controller->create($data);
```

---

## 🚀 Utilizzo Use Cases

### Creare una Prenotazione

```php
$container = \FP\Resv\Kernel\Bootstrap::container();
$useCase = $container->get(\FP\Resv\Application\Reservations\CreateReservationUseCase::class);

try {
    $reservation = $useCase->execute([
        'date' => '2025-01-15',
        'time' => '20:00',
        'party' => 4,
        'meal' => 'dinner',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario@example.com',
        'phone' => '+39 123 456 7890',
    ]);
    
    echo "Reservation created: " . $reservation->getId();
} catch (\FP\Resv\Core\Exceptions\ValidationException $e) {
    foreach ($e->getErrors() as $field => $message) {
        echo "$field: $message\n";
    }
}
```

### Aggiornare una Prenotazione

```php
$useCase = $container->get(\FP\Resv\Application\Reservations\UpdateReservationUseCase::class);

$reservation = $useCase->execute(123, [
    'status' => 'confirmed',
    'notes' => 'Window seat preferred',
]);
```

### Eliminare una Prenotazione

```php
$useCase = $container->get(\FP\Resv\Application\Reservations\DeleteReservationUseCase::class);

$success = $useCase->execute(123);
```

---

## 🔍 Accesso ai Servizi

### Metodo 1: Via Container (Raccomandato)

```php
$container = \FP\Resv\Kernel\Bootstrap::container();
$service = $container->get(ServiceInterface::class);
```

### Metodo 2: Via Legacy Bridge

```php
$service = \FP\Resv\Kernel\LegacyBridge::get(ServiceInterface::class);
```

### Metodo 3: Via Legacy Adapter (per logging)

```php
\FP\Resv\Core\Adapters\LegacyServiceAdapter::logInfo('Message');
```

---

## ⚠️ Note Importanti

### Compatibilità
- Il codice esistente continua a funzionare
- La migrazione è opzionale e graduale
- Nessun breaking change introdotto

### Performance
- La nuova architettura non aggiunge overhead significativo
- I servizi sono singleton quando appropriato
- Cache e ottimizzazioni mantenute

### Testing
- La nuova architettura è più testabile
- Le interfacce permettono mock facili
- I Use Cases possono essere testati in isolamento

---

## 📚 Risorse

- **Status Implementation**: `REFACTORING-IMPLEMENTATION-STATUS.md`
- **Plan Originale**: `fp-restaurant-reservations-refactor-plan.plan.md`
- **Documentazione Core Services**: Vedi `src/Core/Services/`

---

## 🆘 Supporto

Se hai domande o problemi durante la migrazione:
1. Controlla gli esempi in questa guida
2. Vedi i file di esempio nella nuova architettura
3. Verifica i log per errori
4. Testa in ambiente di sviluppo prima di produzione










