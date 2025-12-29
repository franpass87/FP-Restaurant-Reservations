# 📖 Guida: Utilizzo dei Use Cases

**Versione:** 0.9.0-rc11  
**Data:** 14 Dicembre 2025

---

## 🎯 Introduzione

I Use Cases sono il punto di ingresso principale per la business logic nel plugin. Questa guida spiega come utilizzarli correttamente.

---

## 📚 Use Cases Disponibili

### Reservations

#### CreateReservationUseCase
Crea una nuova prenotazione.

```php
use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Kernel\Bootstrap;

$container = Bootstrap::container();
$useCase = $container->get(CreateReservationUseCase::class);

$data = [
    'date' => '2025-12-25',
    'time' => '20:00',
    'party' => 4,
    'first_name' => 'Mario',
    'last_name' => 'Rossi',
    'email' => 'mario.rossi@example.com',
    'phone' => '+39 123 456 7890',
    'meal' => 'dinner',
];

try {
    $reservation = $useCase->execute($data);
    // $reservation è un Reservation model
    echo "Prenotazione creata con ID: " . $reservation->getId();
} catch (ValidationException $e) {
    // Gestisci errori di validazione
    foreach ($e->getErrors() as $field => $error) {
        echo "$field: $error";
    }
}
```

#### GetReservationUseCase
Recupera una prenotazione per ID.

```php
use FP\Resv\Application\Reservations\GetReservationUseCase;

$useCase = $container->get(GetReservationUseCase::class);

try {
    $reservation = $useCase->execute(123);
    // Usa $reservation
} catch (ValidationException $e) {
    // Prenotazione non trovata o ID invalido
}
```

#### UpdateReservationUseCase
Aggiorna una prenotazione esistente.

```php
use FP\Resv\Application\Reservations\UpdateReservationUseCase;

$useCase = $container->get(UpdateReservationUseCase::class);

$updates = [
    'date' => '2025-12-26',
    'time' => '21:00',
    'party' => 6,
];

try {
    $reservation = $useCase->execute(123, $updates);
    // Prenotazione aggiornata
} catch (ValidationException $e) {
    // Gestisci errori
}
```

#### DeleteReservationUseCase
Elimina una prenotazione.

```php
use FP\Resv\Application\Reservations\DeleteReservationUseCase;

$useCase = $container->get(DeleteReservationUseCase::class);

try {
    $success = $useCase->execute(123);
    if ($success) {
        echo "Prenotazione eliminata";
    }
} catch (ValidationException $e) {
    // Prenotazione non trovata
}
```

#### CancelReservationUseCase
Cancella una prenotazione (imposta status a 'cancelled').

```php
use FP\Resv\Application\Reservations\CancelReservationUseCase;

$useCase = $container->get(CancelReservationUseCase::class);

try {
    $reservation = $useCase->execute(123);
    // Status ora è 'cancelled'
} catch (ValidationException $e) {
    // Gestisci errori
}
```

#### UpdateReservationStatusUseCase
Aggiorna solo lo status di una prenotazione.

```php
use FP\Resv\Application\Reservations\UpdateReservationStatusUseCase;

$useCase = $container->get(UpdateReservationStatusUseCase::class);

try {
    $reservation = $useCase->execute(123, 'confirmed');
    // Status aggiornato
} catch (ValidationException $e) {
    // Status invalido o prenotazione non trovata
}
```

#### ListReservationsUseCase
Lista prenotazioni con filtri opzionali.

```php
use FP\Resv\Application\Reservations\ListReservationsUseCase;

$useCase = $container->get(ListReservationsUseCase::class);

$criteria = [
    'date' => '2025-12-25',
    'status' => 'confirmed',
];

$reservations = $useCase->execute($criteria, $limit = 100, $offset = 0);
// Array di Reservation models
```

---

### Availability

#### GetAvailabilityUseCase
Recupera disponibilità per una data.

```php
use FP\Resv\Application\Availability\GetAvailabilityUseCase;

$useCase = $container->get(GetAvailabilityUseCase::class);

$criteria = [
    'date' => '2025-12-25',
    'party' => 4,
    'meal' => 'dinner',
];

$availability = $useCase->execute($criteria);
// Array con slots disponibili
```

---

### Events

#### CreateEventUseCase
Crea un nuovo evento.

```php
use FP\Resv\Application\Events\CreateEventUseCase;

$useCase = $container->get(CreateEventUseCase::class);

$data = [
    'title' => 'Evento Speciale',
    'start_date' => '2025-12-25 19:00:00',
    'end_date' => '2025-12-25 23:00:00',
    'max_capacity' => 50,
];

try {
    $event = $useCase->execute($data);
} catch (ValidationException $e) {
    // Gestisci errori
}
```

---

### Closures

#### CreateClosureUseCase
Crea una nuova chiusura.

```php
use FP\Resv\Application\Closures\CreateClosureUseCase;

$useCase = $container->get(CreateClosureUseCase::class);

$data = [
    'title' => 'Chiusura Festività',
    'start_date' => '2025-12-25',
    'end_date' => '2025-12-25',
    'scope' => 'all',
];

try {
    $closure = $useCase->execute($data);
} catch (ValidationException $e) {
    // Gestisci errori
}
```

---

## 🔧 Best Practices

### 1. Sempre Usa Use Cases
❌ **Non fare:**
```php
$service = $container->get(ReservationService::class);
$reservation = $service->create($data);
```

✅ **Fai:**
```php
$useCase = $container->get(CreateReservationUseCase::class);
$reservation = $useCase->execute($data);
```

### 2. Gestisci le Eccezioni
Sempre gestisci `ValidationException`:

```php
try {
    $reservation = $useCase->execute($data);
} catch (ValidationException $e) {
    // Log errori
    $logger->warning('Validation failed', ['errors' => $e->getErrors()]);
    
    // Restituisci errore all'utente
    return new WP_Error('validation_failed', $e->getMessage(), [
        'errors' => $e->getErrors(),
    ]);
}
```

### 3. Usa i Models
I Use Cases restituiscono Domain Models, non array:

```php
// ✅ Corretto
$reservation = $useCase->execute($data);
$id = $reservation->getId();
$date = $reservation->getDate();

// ❌ Non fare
$result = $useCase->execute($data);
$id = $result['id']; // Non funziona!
```

### 4. Dependency Injection
Sempre inietta i Use Cases, non crearli direttamente:

```php
// ✅ Corretto
public function __construct(
    private readonly CreateReservationUseCase $createUseCase
) {}

// ❌ Non fare
$useCase = new CreateReservationUseCase(...); // Troppe dipendenze!
```

---

## 🎯 Esempi Pratici

### REST Endpoint
```php
final class ReservationsEndpoint
{
    public function __construct(
        private readonly CreateReservationUseCase $createUseCase
    ) {}
    
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $data = $request->get_json_params();
            $reservation = $this->createUseCase->execute($data);
            
            return new WP_REST_Response([
                'id' => $reservation->getId(),
                'message' => 'Reservation created',
            ], 201);
        } catch (ValidationException $e) {
            return new WP_Error('validation_failed', $e->getMessage(), [
                'status' => 400,
                'errors' => $e->getErrors(),
            ]);
        }
    }
}
```

### Admin Controller
```php
final class ReservationsController
{
    public function __construct(
        private readonly UpdateReservationUseCase $updateUseCase,
        private readonly GetReservationUseCase $getUseCase
    ) {}
    
    public function update(int $id, array $data): array
    {
        try {
            // Verifica esistenza
            $this->getUseCase->execute($id);
            
            // Aggiorna
            $reservation = $this->updateUseCase->execute($id, $data);
            
            return [
                'success' => true,
                'reservation' => $reservation->toArray(),
            ];
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'errors' => $e->getErrors(),
            ];
        }
    }
}
```

---

## 📝 Note Importanti

1. **I Use Cases validano automaticamente** i dati in input
2. **I Use Cases loggano** le operazioni automaticamente
3. **I Use Cases gestiscono** la business logic
4. **Non chiamare direttamente** i servizi Domain
5. **Usa sempre** i Use Cases per operazioni business

---

## 🔄 Migrazione da Codice Legacy

### Prima (Legacy)
```php
$service = ServiceContainer::getInstance()->get(Service::class);
$result = $service->create($data);
```

### Dopo (Nuovo)
```php
$container = Bootstrap::container();
$useCase = $container->get(CreateReservationUseCase::class);
$reservation = $useCase->execute($data);
```

---

**Ultimo aggiornamento:** 14 Dicembre 2025  
**Versione:** 0.9.0-rc11







