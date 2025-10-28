# Analisi: Perché il sistema non dovrebbe dare disponibilità

## Data: 2025-10-09
## Branch: cursor/check-why-availability-should-not-be-given-1891

## Problema Identificato

Il sistema mostra "Nessuna disponibilità per questo servizio" anche quando gli orari potrebbero essere configurati correttamente. Questa analisi identifica le possibili cause di **falsi negativi** nella logica di disponibilità.

---

## Flusso di Controllo della Disponibilità

### 1. Backend (PHP)

Il flusso nel file `src/Domain/Reservations/Availability.php`:

```
findSlots()
  ↓
resolveMealSettings(mealKey)
  ↓
resolveScheduleForDay(dayStart, schedule)
  ↓
calculateSlots() o return { has_availability: false }
```

### 2. Frontend (JavaScript)

Il flusso nel file `assets/js/fe/onepage.js`:

```
handleMealAvailabilitySummary()
  ↓
applyMealAvailabilityIndicator() → se state='full'
  ↓
applyMealAvailabilityNotice() → mostra mealFullNotice
  ↓
Button disabled + messaggio "Nessuna disponibilità"
```

---

## Possibili Cause di Falsi Negativi

### ⚠️ PROBLEMA 1: Mapping Giorno della Settimana

**File**: `src/Domain/Reservations/Availability.php:715-716`

```php
$dayKey = strtolower($dayStart->format('D'));
$schedule = $scheduleMap[$dayKey] ?? [];
```

**Issue**: 
- Il codice usa `format('D')` che restituisce un formato **3-letter abbreviato** (es. "Mon", "Tue", "Wed")
- Dopo `strtolower()` diventa: "mon", "tue", "wed", ecc.
- Questo **funziona correttamente** se la configurazione usa lo stesso formato

**Potenziale problema**:
- Se qualcuno ha configurato gli orari con nomi completi (es. "monday" invece di "mon")
- Se qualcuno usa il formato italiano (es. "lun", "mar", "mer")

**Verifica necessaria**: Controllare il formato effettivo nella configurazione del meal plan.

---

### ⚠️ PROBLEMA 2: Schedule Vuoto Ritorna Immediatamente

**File**: `src/Domain/Reservations/Availability.php:161-189` e `354-382`

```php
$schedule = $this->resolveScheduleForDay($dayStart, $mealSettings['schedule']);

if ($schedule === []) {
    // Ritorna immediatamente con has_availability: false
    return [
        'slots' => [],
        'meta' => [
            'has_availability' => false,
            'reason' => __('Nessun turno configurato per la data selezionata.'),
        ],
    ];
}
```

**Issue**:
Questo controllo avviene **PRIMA** di verificare:
- Se ci sono chiusure programmate
- Se ci sono tavoli disponibili
- Se la capacità della sala è sufficiente

**Scenario di falso negativo**:
1. Un pasto non ha `hours_definition` specifico
2. Il sistema usa gli orari di default da `service_hours_definition`
3. Se `service_hours_definition` è vuoto o mal formattato, `scheduleMap` sarà vuoto
4. Risultato: "Nessuna disponibilità" anche se il ristorante è aperto

---

### ⚠️ PROBLEMA 3: Parsing Fallback agli Orari Default

**File**: `src/Domain/Reservations/Availability.php:742-748` e `784-786`

```php
private function parseScheduleDefinition(string $raw): array
{
    // ... parsing logic ...
    
    if ($lines === false || $lines === []) {
        return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);  // ← Fallback
    }
    
    // ... more parsing ...
    
    if ($schedule === []) {
        return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);  // ← Fallback
    }
    
    return $schedule;
}
```

**Issue**:
- Se `service_hours_definition` è vuoto, il sistema usa `DEFAULT_SCHEDULE` (righe 44-52)
- Questo è **hardcoded** per tutti i giorni della settimana
- **MA**: se un meal ha un `hours_definition` vuoto o invalido, cosa succede?

**Scenario problematico**:
1. `service_hours_definition` (globale) = configurato correttamente
2. Meal "cena" ha `hours_definition` = "" (stringa vuota)
3. Il sistema chiama `parseScheduleDefinition("")`
4. Ritorna `DEFAULT_SCHEDULE` invece di usare gli orari globali

**Verifica nel codice** (`Availability.php:615-642`):
```php
if (!empty($meal['hours_definition'])) {
    $mealSchedule = $this->parseScheduleDefinition((string) $meal['hours_definition']);
    if ($mealSchedule !== []) {
        $scheduleMap = $mealSchedule;  // ← Sovrascrive gli orari di default!
    }
}
```

✅ **Questo è gestito correttamente**: se `hours_definition` è presente ma vuoto, `empty()` ritorna true e non viene processato.

---

### ⚠️ PROBLEMA 4: Configurazione Meal Plan

**File**: `src/Domain/Settings/MealPlan.php:260-350`

La funzione `normalizeAvailability()` processa la configurazione del meal, incluso `hours_definition`.

**Possibile issue**:
Se il meal plan JSON ha una struttura non standard, potrebbe non essere parsato correttamente.

---

### ⚠️ PROBLEMA 5: Frontend Caching Aggressivo

**File**: `assets/js/fe/availability.js:323-328`

```javascript
const cacheKey = JSON.stringify([params.date, params.meal, params.party]);
const cached = cache.get(cacheKey);
if (cached && Date.now() - cached.timestamp < CACHE_TTL_MS && attempt === 0) {
    renderSlots(cached.payload, params, requestToken);
    return;  // ← Non fa una nuova richiesta!
}
```

**Issue**:
- Cache TTL è 60 secondi (riga 4)
- Se l'utente ha ricevuto "nessuna disponibilità" e poi la configurazione viene corretta, deve aspettare 60 secondi o ricaricare la pagina

**Scenario**:
1. Admin configura male gli orari
2. Utente vede "Nessuna disponibilità"
3. Admin corregge gli orari
4. Utente riprova entro 60 secondi → vede ancora "Nessuna disponibilità" (dalla cache)

---

### ⚠️ PROBLEMA 6: Stato "full" Permanente nei Meal Buttons

**File**: `assets/js/fe/onepage.js:797-815`

```javascript
const storedState = this.state.mealAvailability ? this.state.mealAvailability[mealKey] : '';
// ...
if (storedState === 'full') {
    const defaultNotice = button.getAttribute('data-meal-default-notice') || '';
    const notice = this.copy.mealFullNotice || defaultNotice;
    if (notice !== '') {
        button.setAttribute('data-meal-notice', notice);
    }
}
// ...
if (storedState === 'full') {
    return;  // ← NON schedula nemmeno l'aggiornamento!
}

this.scheduleAvailabilityUpdate({ immediate: true });
```

**PROBLEMA CRITICO**:
- Se un pasto è stato marcato come `full` in precedenza
- E l'utente clicca di nuovo su quel pasto
- Il sistema **NON verifica nuovamente** la disponibilità!
- Usa lo stato cached in `this.state.mealAvailability[mealKey]`

**Scenario di falso negativo**:
1. Utente seleziona "Cena" per oggi → nessuna disponibilità (es. tutto prenotato)
2. Stato salvato: `mealAvailability['cena'] = 'full'`
3. Utente cambia data a domani
4. Seleziona di nuovo "Cena"
5. **BUG**: Il sistema usa lo stato cached "full" e non verifica la disponibilità per domani!

---

## Raccomandazioni per il Fix

### Fix Priorità ALTA

#### 1. ⚠️ Rimuovere il return anticipato quando meal state = 'full'

**File**: `assets/js/fe/onepage.js:814-816`

```javascript
// PRIMA (BUG):
if (storedState === 'full') {
    return;  // ← Non aggiorna mai la disponibilità!
}
this.scheduleAvailabilityUpdate({ immediate: true });

// DOPO (FIX):
// Rimuovere il return, sempre schedulare l'aggiornamento
this.scheduleAvailabilityUpdate({ immediate: true });
```

**Ragionamento**:
- Lo stato 'full' potrebbe essere cambiato se l'utente ha modificato la data o il numero di persone
- Bisogna **sempre** verificare la disponibilità quando cambia un parametro

#### 2. ⚠️ Invalidare cache meal availability quando cambiano parametri critici

**File**: `assets/js/fe/onepage.js` (aggiungere logica)

```javascript
// Quando cambia la data o il party:
handleDateChange() {
    // Resetta tutti gli stati di availability dei meal
    this.state.mealAvailability = {};
    // ... resto della logica
}

handlePartyChange() {
    // Resetta tutti gli stati di availability dei meal
    this.state.mealAvailability = {};
    // ... resto della logica
}
```

---

### Fix Priorità MEDIA

#### 3. Aggiungere validazione robusta del meal schedule

**File**: `src/Domain/Reservations/Availability.php:556-706`

Aggiungere controlli:
- Se meal ha `hours_definition` esplicitamente vuoto, usa gli orari globali
- Log warning se il parsing fallisce

#### 4. Ridurre il TTL della cache frontend

**File**: `assets/js/fe/availability.js:4`

```javascript
// PRIMA:
const CACHE_TTL_MS = 60000; // 60 secondi

// DOPO:
const CACHE_TTL_MS = 15000; // 15 secondi
```

---

### Fix Priorità BASSA

#### 5. Migliorare i messaggi di errore per l'utente

Invece di un generico "Nessuna disponibilità", mostrare:
- "Nessun orario configurato per questo giorno"
- "Tutto prenotato per questo giorno"
- "Servizio non disponibile per la data selezionata"

#### 6. Aggiungere pulsante "Riprova" nel messaggio di meal full

Permettere all'utente di forzare un refresh della disponibilità.

---

## Test Consigliati

1. **Test scenario A**: Meal senza `hours_definition` → deve usare orari globali
2. **Test scenario B**: Cambio data con meal già selezionato → deve ricalcolare disponibilità
3. **Test scenario C**: Cambio party con meal già selezionato → deve ricalcolare disponibilità
4. **Test scenario D**: Configurazione orari con formato non standard → deve gestire gracefully
5. **Test scenario E**: Cache invalidation quando admin aggiorna la configurazione

---

## Conclusione

Il problema principale è stato identificato nel **frontend JavaScript**.

### 🔴 BUG CONFERMATO

**File**: `assets/js/fe/onepage.js:814-816`

```javascript
if (storedState === 'full') {
    return;  // ← QUESTO È IL BUG!
}

this.scheduleAvailabilityUpdate({ immediate: true });
```

### Comportamento Errato

Quando un pasto ha lo stato `'full'` memorizzato in `this.state.mealAvailability[mealKey]`, il sistema:

1. ❌ NON schedula l'aggiornamento della disponibilità
2. ❌ Usa sempre lo stato cached, anche se i parametri sono cambiati
3. ❌ Impedisce all'utente di vedere la disponibilità reale per nuove date/party

### Scenario di Riproduzione del Bug

**Passi per riprodurre**:
1. Seleziona data: Oggi
2. Seleziona meal: "Cena"
3. Sistema verifica → Tutto prenotato → `state.mealAvailability['cena'] = 'full'`
4. Cambia data: Domani (che ha disponibilità)
5. **Riseleziona meal**: "Cena"
6. **BUG**: Sistema usa lo stato cached `'full'` e NON verifica la disponibilità per domani!

### Impatto

- ❌ Falsi negativi sulla disponibilità
- ❌ Utenti non possono prenotare anche quando ci sono posti disponibili
- ❌ Il problema persiste finché l'utente non ricarica la pagina

### Soluzione Immediata

**Rimuovere le righe 814-816** o modificarle per invalidare la cache quando cambiano i parametri:

```javascript
// OPZIONE 1: Rimuovere il return (soluzione più semplice)
// if (storedState === 'full') {
//     return;
// }
this.scheduleAvailabilityUpdate({ immediate: true });

// OPZIONE 2: Aggiungere logica di invalidazione
if (storedState === 'full') {
    // Se i parametri sono cambiati, invalida lo stato cached
    const paramsChanged = this.hasAvailabilityParamsChanged(mealKey);
    if (!paramsChanged) {
        return;
    }
}
this.scheduleAvailabilityUpdate({ immediate: true });
```

### Perché il Sistema NON Dovrebbe Dare Disponibilità (risposta alla domanda)

Il sistema **NON dovrebbe** dare disponibilità quando:

1. ✅ **Nessun orario configurato** per il giorno/pasto selezionato (comportamento corretto)
2. ✅ **Tutto effettivamente prenotato** per quella data/orario (comportamento corretto)
3. ✅ **Chiusura programmata** attiva per quel giorno (comportamento corretto)
4. ❌ **BUG**: Pasto precedentemente "full" anche se la data è cambiata (comportamento ERRATO)

### Raccomandazione Finale

**PRIORITÀ ALTA**: Correggere il bug nel file `assets/js/fe/onepage.js` rimuovendo il return anticipato quando `storedState === 'full'`.

---

## File Modificati in Questo Branch

Secondo `git diff`, i file modificati includono:
- `DEBUGGING-NO-SPOTS-AVAILABLE.md` - Documentazione debug (aggiunta)
- `templates/frontend/reservation-widget.php` - Template modificato
- Altri file di changelog e documentazione

**Nota**: Nessuna modifica alla logica PHP di availability in questo branch, quindi il problema è probabilmente nel JavaScript.
