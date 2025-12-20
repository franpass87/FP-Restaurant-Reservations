# 🏗️ Guida: Clean Architecture nel Plugin

**Versione:** 0.9.0-rc11  
**Data:** 14 Dicembre 2025

---

## 🎯 Introduzione

Il plugin segue i principi della **Clean Architecture**, organizzando il codice in layer ben definiti con dipendenze unidirezionali.

---

## 📐 Struttura Layer

```
┌─────────────────────────────────────┐
│     Presentation Layer              │
│  (REST Endpoints, Admin Controllers)│
└──────────────┬──────────────────────┘
               │ usa
┌──────────────▼──────────────────────┐
│     Application Layer               │
│  (Use Cases - Business Logic)       │
└──────────────┬──────────────────────┘
               │ usa
┌──────────────▼──────────────────────┐
│     Domain Layer                    │
│  (Models, Services, Interfaces)    │
└──────────────┬──────────────────────┘
               │ usa
┌──────────────▼──────────────────────┐
│     Infrastructure Layer            │
│  (Repositories, Adapters)           │
└─────────────────────────────────────┘
```

---

## 📁 Organizzazione Directory

```
src/
├── Application/          # Application Layer
│   ├── Reservations/
│   │   └── *UseCase.php
│   ├── Availability/
│   └── Events/
│
├── Domain/              # Domain Layer
│   ├── Reservations/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Repositories/
│   └── Events/
│
├── Infrastructure/      # Infrastructure Layer
│   ├── Persistence/
│   │   └── *Repository.php
│   └── Services/
│
├── Presentation/        # Presentation Layer
│   ├── API/
│   │   └── REST/
│   └── Admin/
│       └── Controllers/
│
└── Kernel/             # Core Infrastructure
    ├── Container.php
    ├── Bootstrap.php
    └── LegacyBridge.php
```

---

## 🔄 Flusso di Dati

### Esempio: Creazione Prenotazione

```
1. REST Endpoint (Presentation)
   ↓ riceve request
   
2. ReservationsEndpoint::create()
   ↓ chiama
   
3. CreateReservationUseCase (Application)
   ↓ valida e orchestrazione
   
4. ReservationService (Domain)
   ↓ business logic
   
5. ReservationRepository (Infrastructure)
   ↓ persiste
   
6. Database
```

---

## 📋 Regole di Dipendenza

### ✅ Permesso
- **Presentation** → **Application** ✅
- **Application** → **Domain** ✅
- **Infrastructure** → **Domain** ✅
- **Tutti** → **Kernel** ✅

### ❌ Vietato
- **Domain** → **Application** ❌
- **Domain** → **Presentation** ❌
- **Domain** → **Infrastructure** ❌
- **Application** → **Presentation** ❌

---

## 🎯 Layer Details

### Presentation Layer
**Responsabilità:**
- Gestione HTTP requests/responses
- Sanitizzazione input
- Formattazione output
- Autenticazione/autorizzazione

**Esempi:**
- `Presentation\API\REST\ReservationsEndpoint`
- `Presentation\Admin\Controllers\ReservationsController`

**Non deve:**
- Contenere business logic
- Accedere direttamente al database
- Conoscere dettagli di implementazione

---

### Application Layer
**Responsabilità:**
- Orchestrazione business logic
- Validazione input
- Coordinamento tra Domain services
- Logging operazioni

**Esempi:**
- `Application\Reservations\CreateReservationUseCase`
- `Application\Availability\GetAvailabilityUseCase`

**Non deve:**
- Contenere logica business complessa
- Accedere direttamente al database
- Conoscere dettagli di presentazione

---

### Domain Layer
**Responsabilità:**
- Business logic core
- Domain models
- Domain services
- Business rules

**Esempi:**
- `Domain\Reservations\Models\Reservation`
- `Domain\Reservations\Services\ReservationService`
- `Domain\Reservations\Services\AvailabilityService`

**Non deve:**
- Dipendere da altri layer
- Conoscere WordPress
- Conoscere database

---

### Infrastructure Layer
**Responsabilità:**
- Persistenza dati
- Adattatori esterni
- Implementazioni concrete

**Esempi:**
- `Infrastructure\Persistence\ReservationRepository`
- `Infrastructure\Services\AvailabilityServiceAdapter`

**Non deve:**
- Contenere business logic
- Essere usato direttamente da Presentation

---

## 🔧 Dependency Injection

### Container PSR-11
Il plugin usa `Kernel\Container` (PSR-11 compatible):

```php
use FP\Resv\Kernel\Bootstrap;

$container = Bootstrap::container();
$useCase = $container->get(CreateReservationUseCase::class);
```

### Service Providers
I servizi sono registrati tramite Service Providers:

- `CoreServiceProvider` - Servizi core
- `DataServiceProvider` - Repositories e Use Cases
- `BusinessServiceProvider` - Business services
- `AdminServiceProvider` - Admin services
- `RESTServiceProvider` - REST endpoints
- `FrontendServiceProvider` - Frontend services

---

## 📝 Esempi Pratici

### ✅ Corretto: Presentation → Application
```php
// Presentation\API\REST\ReservationsEndpoint
final class ReservationsEndpoint
{
    public function __construct(
        private readonly CreateReservationUseCase $createUseCase
    ) {}
    
    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        $reservation = $this->createUseCase->execute($data);
        // ...
    }
}
```

### ✅ Corretto: Application → Domain
```php
// Application\Reservations\CreateReservationUseCase
final class CreateReservationUseCase
{
    public function __construct(
        private readonly ReservationServiceInterface $service
    ) {}
    
    public function execute(array $data): Reservation
    {
        $this->validate($data);
        return $this->service->create($data);
    }
}
```

### ❌ Sbagliato: Domain → Application
```php
// Domain\Reservations\Service
// ❌ NON FARE QUESTO
use FP\Resv\Application\Reservations\CreateReservationUseCase;

class Service
{
    public function create(array $data)
    {
        $useCase = new CreateReservationUseCase(...); // ❌
    }
}
```

---

## 🎯 Best Practices

### 1. Usa le Interfacce
Sempre usa interfacce nel Domain layer:

```php
// ✅ Corretto
interface ReservationRepositoryInterface
{
    public function findById(int $id): ?Reservation;
}

// Infrastructure implementa
class ReservationRepository implements ReservationRepositoryInterface
{
    // ...
}
```

### 2. Dependency Injection
Sempre inietta dipendenze, non crearle:

```php
// ✅ Corretto
public function __construct(
    private readonly ReservationServiceInterface $service
) {}

// ❌ Sbagliato
public function __construct()
{
    $this->service = new ReservationService(); // ❌
}
```

### 3. Use Cases per Business Logic
Tutta la business logic passa attraverso Use Cases:

```php
// ✅ Corretto
$useCase = $container->get(CreateReservationUseCase::class);
$reservation = $useCase->execute($data);

// ❌ Sbagliato
$service = $container->get(ReservationService::class);
$reservation = $service->create($data); // Bypassa Application layer
```

---

## 🔄 Migrazione da Codice Legacy

### Pattern Legacy
```php
// ❌ Vecchio pattern
$container = ServiceContainer::getInstance();
$service = $container->get(Service::class);
$result = $service->create($data);
```

### Pattern Nuovo
```php
// ✅ Nuovo pattern
$container = Bootstrap::container();
$useCase = $container->get(CreateReservationUseCase::class);
$reservation = $useCase->execute($data);
```

---

## 📚 Risorse

- `README-REFACTORING.md` - Documentazione refactoring
- `USING-USE-CASES.md` - Guida Use Cases
- `MIGRATION-GUIDE.md` - Guida migrazione

---

**Ultimo aggiornamento:** 14 Dicembre 2025  
**Versione:** 0.9.0-rc11




