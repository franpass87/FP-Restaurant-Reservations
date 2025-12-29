# 📚 Indice Guide Sviluppatore

**Versione:** 0.9.0-rc11  
**Data:** 14 Dicembre 2025

---

## 🎯 Guide Disponibili

### Architettura
- [Clean Architecture](./ARCHITETTURA-CLEAN.md) - Guida completa all'architettura del plugin
- [Using Use Cases](./USING-USE-CASES.md) - Come utilizzare i Use Cases

### Sviluppo
- [Cache Guide](./CACHE-GUIDE.md) - Gestione cache
- [Cache Refresh Guide](./CACHE-REFRESH-GUIDE.md) - Refresh cache
- [GitHub Auto Deploy](./GITHUB-AUTO-DEPLOY.md) - Deploy automatico
- [Metrics Guide](./METRICS-GUIDE.md) - Metriche e monitoring
- [README Build](./README-BUILD.md) - Build del plugin

---

## 🚀 Quick Start

### Per Nuovi Sviluppatori

1. **Leggi l'Architettura**
   - Inizia da [Clean Architecture](./ARCHITETTURA-CLEAN.md)
   - Capisci la struttura dei layer

2. **Impara i Use Cases**
   - Leggi [Using Use Cases](./USING-USE-CASES.md)
   - Esplora gli esempi

3. **Esplora il Codice**
   - Inizia da `src/Application/` per i Use Cases
   - Poi `src/Domain/` per la business logic
   - Infine `src/Presentation/` per gli endpoint

---

## 📖 Documentazione Principale

- [README Refactoring](../../README-REFACTORING.md) - Documentazione refactoring completo
- [Migration Guide](../../MIGRATION-GUIDE.md) - Guida migrazione
- [Executive Summary](../../EXECUTIVE-SUMMARY-MIGLIORAMENTI.md) - Riepilogo esecutivo

---

## 🔧 Tools e Utilities

### Container
```php
use FP\Resv\Kernel\Bootstrap;

$container = Bootstrap::container();
$service = $container->get(ServiceClass::class);
```

### Use Cases
```php
$useCase = $container->get(CreateReservationUseCase::class);
$result = $useCase->execute($data);
```

### Logging
```php
$logger = $container->get(LoggerInterface::class);
$logger->info('Message', ['context' => $data]);
```

---

## 🎯 Convenzioni

### Naming
- **Use Cases:** `*UseCase.php`
- **Models:** `*Model.php` o solo nome (es. `Reservation.php`)
- **Services:** `*Service.php`
- **Repositories:** `*Repository.php`
- **Endpoints:** `*Endpoint.php`

### Namespace
- **Application:** `FP\Resv\Application\*`
- **Domain:** `FP\Resv\Domain\*`
- **Infrastructure:** `FP\Resv\Infrastructure\*`
- **Presentation:** `FP\Resv\Presentation\*`
- **Kernel:** `FP\Resv\Kernel\*`

---

## 📝 Contribuire

### Prima di Iniziare
1. Leggi le guide
2. Esplora il codice esistente
3. Segui le convenzioni
4. Scrivi test

### Processo
1. Crea branch da `main`
2. Implementa feature
3. Scrivi test
4. Aggiorna documentazione
5. Crea PR

---

**Ultimo aggiornamento:** 14 Dicembre 2025  
**Versione:** 0.9.0-rc11







