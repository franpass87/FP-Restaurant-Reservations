# Quick Start - Nuova Architettura

Guida rapida per iniziare a usare la nuova architettura.

---

## 🚀 Accesso Rapido

### Metodo 1: Container Helper (Più Semplice)

```php
use FP\Resv\Core\Helpers\ContainerHelper;

// Logger
ContainerHelper::logger()->info('Message');

// Cache
ContainerHelper::cache()->set('key', 'value');
$value = ContainerHelper::cache()->get('key');

// Options
ContainerHelper::options()->set('setting', 'value');

// Validator
if (ContainerHelper::validator()->isEmail($email)) {
    // Valid
}
```

### Metodo 2: Container Diretto

```php
$container = \FP\Resv\Kernel\Bootstrap::container();
$logger = $container->get(\FP\Resv\Core\Services\LoggerInterface::class);
```

### Metodo 3: Legacy Bridge (Per Codice Esistente)

```php
$logger = \FP\Resv\Kernel\LegacyBridge::get(\FP\Resv\Core\Services\LoggerInterface::class);
```

---

## 📝 Esempi Pratici

### Creare una Prenotazione

```php
use FP\Resv\Core\Helpers\ContainerHelper;

$createUseCase = ContainerHelper::get(
    \FP\Resv\Application\Reservations\CreateReservationUseCase::class
);

try {
    $reservation = $createUseCase->execute([
        'date' => '2025-01-15',
        'time' => '20:00',
        'party' => 4,
        'meal' => 'dinner',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario@example.com',
        'phone' => '+39 123 456 7890',
    ]);
    
    echo "Created: " . $reservation->getId();
} catch (\FP\Resv\Core\Exceptions\ValidationException $e) {
    foreach ($e->getErrors() as $field => $message) {
        echo "$field: $message\n";
    }
}
```

### Logging

```php
use FP\Resv\Core\Helpers\ContainerHelper;

// Debug (solo in WP_DEBUG)
ContainerHelper::logger()->debug('Debug message', ['data' => $data]);

// Info
ContainerHelper::logger()->info('Info message');

// Warning
ContainerHelper::logger()->warning('Warning message');

// Error
ContainerHelper::logger()->error('Error message', ['exception' => $e]);
```

### Cache

```php
use FP\Resv\Core\Helpers\ContainerHelper;

$cache = ContainerHelper::cache();

// Set
$cache->set('my_key', $data, 3600); // 1 hour

// Get
$data = $cache->get('my_key', 'default');

// Check
if ($cache->has('my_key')) {
    // Exists
}

// Delete
$cache->delete('my_key');
```

### Options

```php
use FP\Resv\Core\Helpers\ContainerHelper;

$options = ContainerHelper::options();

// Get (prefisso automatico)
$value = $options->get('setting_name', 'default');

// Set
$options->set('setting_name', 'value');

// Check
if ($options->has('setting_name')) {
    // Exists
}

// Get all with prefix
$all = $options->getAll('prefix_');
```

### Validazione

```php
use FP\Resv\Core\Helpers\ContainerHelper;

$validator = ContainerHelper::validator();

// Email
if ($validator->isEmail($email)) {
    // Valid
}

// Date
if ($validator->isDate($date, 'Y-m-d')) {
    // Valid
}

// Time
if ($validator->isTime($time, 'H:i')) {
    // Valid
}

// Required
if ($validator->isRequired($value)) {
    // Not empty
}
```

### Sanitizzazione

```php
use FP\Resv\Core\Helpers\ContainerHelper;

$sanitizer = ContainerHelper::sanitizer();

// Text field
$clean = $sanitizer->textField($input);

// Email
$clean = $sanitizer->email($email);

// Array (recursive)
$clean = $sanitizer->array($data);

// Escape HTML
$safe = $sanitizer->escapeHtml($html);

// Escape attribute
$safe = $sanitizer->escapeAttr($attr);
```

---

## 🔄 Migrazione da Codice Esistente

### Sostituire error_log()

**Prima:**
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[FP Resv] Message');
}
```

**Dopo:**
```php
use FP\Resv\Core\Helpers\ContainerHelper;
ContainerHelper::logger()->debug('Message');
```

### Sostituire get_transient()

**Prima:**
```php
$value = get_transient('fp_resv_key');
```

**Dopo:**
```php
use FP\Resv\Core\Helpers\ContainerHelper;
$value = ContainerHelper::cache()->get('key');
```

### Sostituire get_option()

**Prima:**
```php
$value = get_option('fp_resv_setting', 'default');
```

**Dopo:**
```php
use FP\Resv\Core\Helpers\ContainerHelper;
$value = ContainerHelper::options()->get('setting', 'default');
```

---

## 📚 Risorse

- **Migration Guide Completo:** `MIGRATION-GUIDE.md`
- **Status Implementation:** `REFACTORING-IMPLEMENTATION-STATUS.md`
- **Documentazione Completa:** `REFACTORING-COMPLETE.md`

---

## ⚡ Quick Reference

| Servizio | Helper Method | Interface |
|----------|---------------|-----------|
| Logger | `ContainerHelper::logger()` | `LoggerInterface` |
| Cache | `ContainerHelper::cache()` | `CacheInterface` |
| Options | `ContainerHelper::options()` | `OptionsInterface` |
| Validator | `ContainerHelper::validator()` | `ValidatorInterface` |
| Sanitizer | `ContainerHelper::sanitizer()` | `SanitizerInterface` |
| HTTP | `ContainerHelper::http()` | `HttpClientInterface` |

---

**Pronto per iniziare!** 🚀










