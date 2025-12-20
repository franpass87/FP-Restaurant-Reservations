# 🔧 Analisi Modularizzazione e Refactoring

**Data:** 19 Novembre 2025  
**Plugin:** FP Restaurant Reservations v0.9.0-rc10.3  
**Obiettivo:** Identificare file grandi e opportunità di modularizzazione

---

## 📊 FILE GRANDI IDENTIFICATI

### 🔴 Critici (>1000 righe)

| File | Righe | Responsabilità Principale | Priorità |
|------|-------|--------------------------|----------|
| **AdminPages.php** | 1778 | Gestione pagine admin e settings | 🔴 Alta |
| **AdminREST.php** | 1658 | REST API per admin/agenda | 🔴 Alta |
| **PhonePrefixes.php** | 1575 | Gestione prefissi telefonici | 🟡 Media |
| **Availability.php** | 1513 | Calcolo disponibilità slot | 🔴 Alta |
| **Service.php** | 1442 | Business logic prenotazioni | 🔴 Alta |
| **PagesConfig.php** | 1127 | Configurazione pagine admin | 🟡 Media |
| **REST.php** | 1125 | REST API pubblico | 🔴 Alta |

### 🟡 Grandi (500-1000 righe)

| File | Righe | Responsabilità Principale | Priorità |
|------|-------|--------------------------|----------|
| **AutomationService.php** | 1030 | Automazione Brevo | 🟡 Media |
| **Diagnostics/Service.php** | 1079 | Diagnostica sistema | 🟢 Bassa |
| **Closures/Service.php** | 846 | Gestione chiusure | 🟡 Media |
| **StyleCss.php** | 827 | Generazione CSS dinamico | 🟢 Bassa |
| **FormContext.php** | 747 | Context form frontend | 🟡 Media |
| **Plugin.php** | 752 | Bootstrap plugin | 🔴 Alta |
| **Reports/Service.php** | 735 | Report e analytics | 🟡 Media |
| **GoogleCalendarService.php** | 733 | Integrazione Google Calendar | 🟡 Media |
| **Repository.php** | 535 | Database access | 🟡 Media |
| **StripeService.php** | 533 | Integrazione pagamenti | 🟡 Media |

---

## 🎯 OPPORTUNITÀ DI MODULARIZZAZIONE

### 1. **Service.php (Reservations)** - 1442 righe 🔴

**Responsabilità attuali:**
- Creazione prenotazioni
- Sanitizzazione payload
- Validazione disponibilità
- Invio email (cliente + staff)
- Gestione pagamenti Stripe
- Integrazione Brevo
- Generazione URL gestione
- Generazione ICS
- Build context email

**Proposte di modularizzazione:**

#### A. Estrarre Email Service
```php
// Nuovo: Domain/Reservations/EmailService.php
- sendCustomerEmail()
- sendStaffNotifications()
- buildReservationContext()
- buildNotificationHeaders()
- renderEmailTemplate()
- fallbackStaffMessage()
- generateIcsContent()
```

**Benefici:**
- Riduce Service.php di ~400 righe
- Separazione responsabilità (SRP)
- Più facile testare email logic

#### B. Estrarre Payment Service
```php
// Nuovo: Domain/Reservations/PaymentService.php
- handlePaymentCreation()
- resolvePaymentStatus()
- createStripeIntent()
```

**Benefici:**
- Riduce Service.php di ~100 righe
- Logica pagamenti isolata

#### C. Estrarre Sanitization Service
```php
// Nuovo: Domain/Reservations/SanitizationService.php
- sanitizePayload()
- detectLanguageFromPhone()
- normalizePhonePrefix()
- normalizePhoneNumber()
- resolveDefaultStatus()
- resolveDefaultCurrency()
```

**Benefici:**
- Riduce Service.php di ~200 righe
- Logica sanitizzazione riutilizzabile

#### D. Estrarre Availability Guard
```php
// Nuovo: Domain/Reservations/AvailabilityGuard.php
- guardAvailabilityForSlot()
- guardCalendarConflicts()
```

**Benefici:**
- Riduce Service.php di ~150 righe
- Logica disponibilità centralizzata

**Risultato atteso:** Service.php da 1442 → ~600 righe

---

### 2. **Availability.php** - 1513 righe 🔴

**Responsabilità attuali:**
- Calcolo slot disponibili
- Caricamento dati (rooms, tables, closures, reservations)
- Valutazione chiusure
- Suggerimenti tavoli
- Filtri slot passati
- Parsing schedule
- Normalizzazione capacità

**Proposte di modularizzazione:**

#### A. Estrarre Data Loaders
```php
// Nuovo: Domain/Reservations/Availability/DataLoader.php
- loadRooms()
- loadTables()
- loadClosures()
- loadReservations()
```

**Benefici:**
- Riduce Availability.php di ~200 righe
- Logica caricamento dati isolata
- Più facile testare e mockare

#### B. Estrarre Closure Evaluator
```php
// Nuovo: Domain/Reservations/Availability/ClosureEvaluator.php
- evaluateClosures()
- closureApplies()
- recurringClosureApplies()
```

**Benefici:**
- Riduce Availability.php di ~200 righe
- Logica chiusure complessa isolata

#### C. Estrarre Slot Calculator
```php
// Nuovo: Domain/Reservations/Availability/SlotCalculator.php
- calculateSlotsForDay()
- buildSlotPayload()
- determineStatus()
- filterOverlappingReservations()
```

**Benefici:**
- Riduce Availability.php di ~300 righe
- Logica calcolo slot isolata

#### D. Estrarre Table Suggester
```php
// Nuovo: Domain/Reservations/Availability/TableSuggester.php
- suggestTables()
- sortSuggestions()
- sortTablesByCapacity()
```

**Benefici:**
- Riduce Availability.php di ~100 righe
- Logica suggerimenti isolata

#### E. Estrarre Schedule Parser
```php
// Nuovo: Domain/Reservations/Availability/ScheduleParser.php
- parseScheduleDefinition()
- normalizeSchedule()
- resolveScheduleForDay()
```

**Benefici:**
- Riduce Availability.php di ~150 righe
- Parsing schedule isolato e testabile

**Risultato atteso:** Availability.php da 1513 → ~500 righe

---

### 3. **AdminREST.php** - 1658 righe 🔴

**Responsabilità attuali:**
- REST endpoints per agenda
- CRUD prenotazioni
- Move/drag&drop prenotazioni
- Filtri e ricerca
- Export dati
- Bulk operations

**Proposte di modularizzazione:**

#### A. Estrarre Agenda Handlers
```php
// Nuovo: Domain/Reservations/AdminREST/AgendaHandler.php
- handleAgendaV2()
- handleAgendaFilters()
- formatAgendaResponse()
```

**Benefici:**
- Riduce AdminREST.php di ~400 righe
- Logica agenda isolata

#### B. Estrarre Reservation Handlers
```php
// Nuovo: Domain/Reservations/AdminREST/ReservationHandler.php
- handleCreateReservation()
- handleUpdateReservation()
- handleDeleteReservation()
- handleMoveReservation()
```

**Benefici:**
- Riduce AdminREST.php di ~500 righe
- CRUD operations isolate

#### C. Estrarre Export Handler
```php
// Nuovo: Domain/Reservations/AdminREST/ExportHandler.php
- handleExport()
- formatExportData()
- generateCSV()
- generateJSON()
```

**Benefici:**
- Riduce AdminREST.php di ~200 righe
- Export logic isolata

**Risultato atteso:** AdminREST.php da 1658 → ~500 righe

---

### 4. **AdminPages.php** - 1778 righe 🔴

**Responsabilità attuali:**
- Registrazione menu admin
- Rendering pagine settings
- Validazione form
- Salvataggio opzioni
- Gestione tabs
- Enqueue assets

**Proposte di modularizzazione:**

#### A. Estrarre Page Renderers
```php
// Nuovo: Domain/Settings/AdminPages/PageRenderer.php
- renderGeneralPage()
- renderNotificationsPage()
- renderRoomsPage()
- renderTrackingPage()
- renderIntegrationsPage()
```

**Benefici:**
- Riduce AdminPages.php di ~600 righe
- Rendering isolato per pagina

#### B. Estrarre Form Validators
```php
// Nuovo: Domain/Settings/AdminPages/FormValidator.php
- validateGeneralSettings()
- validateNotificationSettings()
- validateRoomSettings()
- validateTrackingSettings()
```

**Benefici:**
- Riduce AdminPages.php di ~300 righe
- Validazione centralizzata

#### C. Estrarre Settings Handlers
```php
// Nuovo: Domain/Settings/AdminPages/SettingsHandler.php
- handleSaveGeneral()
- handleSaveNotifications()
- handleSaveRooms()
- handleSaveTracking()
```

**Benefici:**
- Riduce AdminPages.php di ~400 righe
- Logica salvataggio isolata

**Risultato atteso:** AdminPages.php da 1778 → ~500 righe

---

### 5. **REST.php (Reservations)** - 1125 righe 🔴

**Responsabilità attuali:**
- REST endpoints pubblici
- Rate limiting
- Caching
- Validazione richieste
- Gestione disponibilità
- Gestione prenotazioni

**Proposte di modularizzazione:**

#### A. Estrarre Request Validators
```php
// Nuovo: Domain/Reservations/REST/RequestValidator.php
- validateAvailabilityRequest()
- validateReservationRequest()
- validateDateRange()
```

**Benefici:**
- Riduce REST.php di ~200 righe
- Validazione centralizzata

#### B. Estrarre Response Formatters
```php
// Nuovo: Domain/Reservations/REST/ResponseFormatter.php
- formatAvailabilityResponse()
- formatReservationResponse()
- formatErrorResponse()
```

**Benefici:**
- Riduce REST.php di ~200 righe
- Formattazione risposte isolata

#### C. Estrarre Cache Manager
```php
// Nuovo: Domain/Reservations/REST/CacheManager.php
- getCachedAvailability()
- setCachedAvailability()
- invalidateCache()
```

**Benefici:**
- Riduce REST.php di ~150 righe
- Logica cache isolata

**Risultato atteso:** REST.php da 1125 → ~600 righe

---

## 📋 PIANO DI REFACTORING

### Fase 1: Preparazione (1-2 giorni)
- [ ] Creare branch `refactor/modularization`
- [ ] Documentare dipendenze tra classi
- [ ] Creare test di regressione
- [ ] Backup codice attuale

### Fase 2: Service.php (3-4 giorni)
- [ ] Estrarre EmailService
- [ ] Estrarre PaymentService
- [ ] Estrarre SanitizationService
- [ ] Estrarre AvailabilityGuard
- [ ] Aggiornare Service.php per usare nuove classi
- [ ] Test completi

### Fase 3: Availability.php (3-4 giorni)
- [ ] Estrarre DataLoader
- [ ] Estrarre ClosureEvaluator
- [ ] Estrarre SlotCalculator
- [ ] Estrarre TableSuggester
- [ ] Estrarre ScheduleParser
- [ ] Aggiornare Availability.php
- [ ] Test completi

### Fase 4: AdminREST.php (2-3 giorni)
- [ ] Estrarre AgendaHandler
- [ ] Estrarre ReservationHandler
- [ ] Estrarre ExportHandler
- [ ] Aggiornare AdminREST.php
- [ ] Test completi

### Fase 5: AdminPages.php (2-3 giorni)
- [ ] Estrarre PageRenderer
- [ ] Estrarre FormValidator
- [ ] Estrarre SettingsHandler
- [ ] Aggiornare AdminPages.php
- [ ] Test completi

### Fase 6: REST.php (2 giorni)
- [ ] Estrarre RequestValidator
- [ ] Estrarre ResponseFormatter
- [ ] Estrarre CacheManager
- [ ] Aggiornare REST.php
- [ ] Test completi

### Fase 7: Testing e Cleanup (2-3 giorni)
- [ ] Test end-to-end
- [ ] Verifica performance
- [ ] Code review
- [ ] Documentazione aggiornata
- [ ] Merge in main

**Tempo totale stimato:** 15-20 giorni

---

## ✅ BENEFICI ATTESI

### Manutenibilità
- ✅ File più piccoli e focalizzati
- ✅ Responsabilità chiare (SRP)
- ✅ Più facile trovare e modificare codice
- ✅ Riduzione complessità ciclomatica

### Testabilità
- ✅ Classi più piccole = test più semplici
- ✅ Mock più facili da creare
- ✅ Test isolati per ogni responsabilità

### Performance
- ✅ Autoload più efficiente (carica solo classi necessarie)
- ✅ Cache più granulare
- ✅ Meno memoria utilizzata

### Collaborazione
- ✅ Meno conflitti Git (file più piccoli)
- ✅ Code review più semplici
- ✅ Onboarding nuovi sviluppatori più facile

---

## ⚠️ RISCHI E MITIGAZIONI

### Rischio 1: Breaking Changes
**Mitigazione:**
- Test completi prima del refactoring
- Mantenere interfacce pubbliche identiche
- Refactoring incrementale

### Rischio 2: Regressioni
**Mitigazione:**
- Test di regressione completi
- Code review approfondita
- Testing manuale su staging

### Rischio 3: Over-engineering
**Mitigazione:**
- Modularizzare solo file >1000 righe
- Mantenere semplicità dove possibile
- Evitare astrazioni premature

---

## 📝 NOTE IMPLEMENTATIVE

### Convenzioni Naming
- Service classes: `*Service.php`
- Handler classes: `*Handler.php`
- Validator classes: `*Validator.php`
- Formatter classes: `*Formatter.php`

### Struttura Directory
```
src/Domain/Reservations/
├── Service.php (refactored)
├── EmailService.php (new)
├── PaymentService.php (new)
├── SanitizationService.php (new)
├── AvailabilityGuard.php (new)
└── Availability/
    ├── Availability.php (refactored)
    ├── DataLoader.php (new)
    ├── ClosureEvaluator.php (new)
    ├── SlotCalculator.php (new)
    ├── TableSuggester.php (new)
    └── ScheduleParser.php (new)
```

### Dependency Injection
- Mantenere constructor injection
- Usare ServiceContainer per risoluzione dipendenze
- Evitare service locator pattern

---

## 🎯 METRICHE DI SUCCESSO

- [ ] Nessun file >1000 righe
- [ ] Complessità ciclomatica <15 per metodo
- [ ] Coverage test >80%
- [ ] Zero regressioni funzionali
- [ ] Performance invariata o migliorata
- [ ] Code review positivo

---

**Creato:** 19 Novembre 2025  
**Status:** 📋 Pronto per implementazione
















