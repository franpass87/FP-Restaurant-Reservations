# Architecture Overview - FP Restaurant Reservations

**Versione:** 0.9.0-rc10.3  
**Architettura:** Clean Architecture + Service Provider Pattern

---

## 🏗️ Architettura Generale

Il plugin segue **Clean Architecture** con separazione in layer:

```
┌─────────────────────────────────────────┐
│         Presentation Layer              │
│  (REST, Admin, Frontend, CLI)          │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│        Application Layer                │
│      (Use Cases / Orchestration)        │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│          Domain Layer                   │
│   (Business Logic - WordPress-free)     │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│       Infrastructure Layer              │
│  (WordPress-specific implementations)  │
└─────────────────────────────────────────┘

         ┌──────────────┐
         │  Core Layer  │
         │  (Services,  │
         │   Adapters)  │
         └──────────────┘
```

---

## 📦 Layer Details

### 1. Kernel Layer
**Responsabilità:** Bootstrap, container, lifecycle

**Componenti:**
- `Container` - PSR-11 service container
- `Bootstrap` - Plugin initialization
- `Plugin` - Main plugin class
- `Lifecycle` - Activation/deactivation
- `LegacyBridge` - Backward compatibility

### 2. Providers Layer
**Responsabilità:** Service registration

**Componenti:**
- `CoreServiceProvider` - Core services
- `AdminServiceProvider` - Admin features
- `FrontendServiceProvider` - Frontend features
- `RESTServiceProvider` - REST API
- `CLIServiceProvider` - WP-CLI
- `DataServiceProvider` - Data layer

### 3. Core Layer
**Responsabilità:** Cross-cutting services

**Servizi:**
- Logger, Cache, Options, Validator, Sanitizer, HTTP Client

**Adapters:**
- WordPressAdapter, DatabaseAdapter, HooksAdapter

### 4. Domain Layer
**Responsabilità:** Business logic (WordPress-agnostic)

**Componenti:**
- Models (Reservation, Closure, etc.)
- Repository Interfaces
- Service Interfaces
- Value Objects

### 5. Application Layer
**Responsabilità:** Use cases / orchestration

**Use Cases:**
- CreateReservationUseCase
- UpdateReservationUseCase
- DeleteReservationUseCase
- GetAvailabilityUseCase

### 6. Infrastructure Layer
**Responsabilità:** WordPress-specific implementations

**Componenti:**
- Repository implementations
- Service adapters (bridges to legacy code)
- External API clients

### 7. Presentation Layer
**Responsabilità:** UI and API (thin controllers)

**Componenti:**
- REST Endpoints
- Admin Controllers
- Frontend Shortcodes
- WP-CLI Commands

---

## 🔄 Dependency Flow

```
Presentation → Application → Domain ← Infrastructure
     ↓              ↓           ↓
   Core Services (Logger, Cache, etc.)
```

**Regole:**
- Presentation dipende da Application
- Application dipende da Domain
- Infrastructure implementa Domain interfaces
- Domain NON dipende da WordPress
- Core è indipendente

---

## 🎯 Principi Architetturali

### 1. Dependency Inversion
- Le interfacce sono nel Domain
- Le implementazioni sono nell'Infrastructure
- I controller dipendono da interfacce, non implementazioni

### 2. Single Responsibility
- Ogni classe ha una responsabilità
- Use Cases orchestrano, non implementano
- Controller sono thin (solo HTTP concerns)

### 3. Open/Closed
- Aperti per estensione (nuovi Use Cases)
- Chiusi per modifica (interfacce stabili)

### 4. Interface Segregation
- Interfacce piccole e focalizzate
- Nessuna dipendenza da metodi non usati

---

## 🔌 Service Container

### Registrazione Servizi

I servizi sono registrati nei Service Providers:

```php
// CoreServiceProvider
$container->singleton(LoggerInterface::class, Logger::class);
$container->singleton(CacheInterface::class, Cache::class);

// DataServiceProvider
$container->singleton(
    ReservationRepositoryInterface::class,
    ReservationRepository::class
);
```

### Accesso Servizi

```php
// Via Container
$container = Bootstrap::container();
$service = $container->get(ServiceInterface::class);

// Via Helper
$service = ContainerHelper::get(ServiceInterface::class);

// Via Legacy Bridge
$service = LegacyBridge::get(ServiceInterface::class);
```

---

## 📝 Esempi di Utilizzo

### Use Case Pattern

```php
// 1. Controller riceve request
// 2. Sanitizza input
// 3. Chiama Use Case
// 4. Use Case orchestrazione
// 5. Use Case chiama Domain Service
// 6. Domain Service usa Repository
// 7. Repository (Infrastructure) accede DB
```

### Repository Pattern

```php
// Domain Interface
interface ReservationRepositoryInterface {
    public function findById(int $id): ?Reservation;
}

// Infrastructure Implementation
class ReservationRepository implements ReservationRepositoryInterface {
    // WordPress-specific implementation
}
```

---

## 🚀 Estendibilità

### Aggiungere un Nuovo Modulo

1. **Domain Layer:**
   - Crea Model
   - Crea Repository Interface
   - Crea Service Interface

2. **Application Layer:**
   - Crea Use Cases

3. **Infrastructure Layer:**
   - Implementa Repository Interface

4. **Presentation Layer:**
   - Crea Controller/Endpoint

5. **Providers:**
   - Registra nel DataServiceProvider
   - Registra nel ServiceProvider appropriato

---

## 📚 Documentazione Correlata

- **Migration Guide:** `MIGRATION-GUIDE.md`
- **Quick Start:** `QUICK-START-NEW-ARCHITECTURE.md`
- **Status:** `REFACTORING-IMPLEMENTATION-STATUS.md`
- **Complete:** `REFACTORING-COMPLETE.md`

---

**Architettura pronta per crescita e manutenzione!** 🎯










