# 🕐 Sistema Slot Orari - Documentazione Tecnica

**Plugin:** FP Restaurant Reservations  
**Versione:** 0.9.0-rc6  
**Data:** 2 Novembre 2025

---

## 📋 INDICE

1. [Panoramica Sistema](#panoramica-sistema)
2. [Flusso Completo](#flusso-completo)
3. [Configurazione Backend](#configurazione-backend)
4. [Generazione Slot](#generazione-slot)
5. [API REST](#api-rest)
6. [Frontend](#frontend)
7. [Timezone e Formattazione](#timezone-e-formattazione)
8. [Verifica e Testing](#verifica-e-testing)

---

## 🎯 PANORAMICA SISTEMA

Il sistema di slot orari garantisce che:

✅ Gli orari mostrati nel **form frontend** corrispondano **esattamente** agli orari configurati nel **backend**  
✅ Tutti gli orari siano nel timezone **Europe/Rome**  
✅ La configurazione backend sia centralizzata e facile da modificare  
✅ Gli slot siano generati dinamicamente in base a disponibilità reale

---

## 🔄 FLUSSO COMPLETO

```
┌─────────────────────┐
│ 1. Backend Config   │
│ (Impostazioni WP)   │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│ 2. Availability.php │
│ (Genera slot)       │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│ 3. REST API         │
│ (/availability)     │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│ 4. Frontend Form    │
│ (Mostra slot)       │
└─────────────────────┘
```

---

## ⚙️ CONFIGURAZIONE BACKEND

### 📍 Dove si Configurano gli Orari

**Admin → Restaurant Manager → Impostazioni**

#### 1. Orari di Servizio Generali

**Campo:** `service_hours_definition`  
**Formato:**
```
mon=19:00-23:00
tue=19:00-23:00
wed=19:00-23:00
thu=19:00-23:00
fri=19:00-23:30
sat=12:30-15:00|19:00-23:30
sun=12:30-15:00
```

**Sintassi:**
- `giorno=ora_inizio-ora_fine`
- Più fasce orarie separate da `|`
- Giorni: `mon`, `tue`, `wed`, `thu`, `fri`, `sat`, `sun`
- Formato ora: `HH:MM` (24h)

#### 2. Meal Plans (Opzionale)

**Campo:** `frontend_meals`  
**Formato:**
```
pranzo|Pranzo|12:00-15:00
cena|Cena|19:00-23:00
```

Ogni meal plan può sovrascrivere gli orari generali con il suo `hours_definition`.

#### 3. Parametri Slot

| Parametro | Campo | Default | Descrizione |
|-----------|-------|---------|-------------|
| Intervallo slot | `slot_interval_minutes` | 15 | Ogni quanti minuti generare uno slot |
| Turnover tavolo | `table_turnover_minutes` | 120 | Durata media occupazione tavolo |
| Buffer | `buffer_before_minutes` | 15 | Minuti di buffer tra prenotazioni |
| Max parallele | `max_parallel_parties` | 8 | Numero massimo prenotazioni contemporanee |

---

## 🔧 GENERAZIONE SLOT

### File: `src/Domain/Reservations/Availability.php`

#### Funzione Principale: `findSlots()`

```php
public function findSlots(array $criteria): array
{
    // 1. Risolve timezone (SEMPRE Europe/Rome)
    $timezone = $this->resolveTimezone(); // → Europe/Rome
    
    // 2. Crea DateTimeImmutable con timezone corretto
    $dayStart = new DateTimeImmutable($dateString . ' 00:00:00', $timezone);
    
    // 3. Carica meal settings (orari, intervalli, etc)
    $mealSettings = $this->resolveMealSettings($mealKey);
    
    // 4. Risolve schedule per il giorno (es. mon → 19:00-23:00)
    $schedule = $this->resolveScheduleForDay($dayStart, $mealSettings['schedule']);
    
    // 5. Genera slot per ogni finestra oraria
    foreach ($schedule as $window) {
        for ($minute = $startMinute; 
             $minute + $turnoverMinutes <= $endMinute; 
             $minute += $slotInterval) {
            
            // Crea slot aggiungendo minuti al dayStart (con timezone!)
            $slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
            $slotEnd = $slotStart->add(new DateInterval('PT' . $turnoverMinutes . 'M'));
            
            // ... calcola disponibilità ...
            
            $slots[] = $this->buildSlotPayload($slotStart, $slotEnd, ...);
        }
    }
    
    return $slots;
}
```

#### Funzione Formattazione: `buildSlotPayload()`

```php
private function buildSlotPayload(
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    ...
): array {
    return [
        // ✅ Formato ISO 8601 con timezone (per parsing preciso)
        'start' => $start->format(DateTimeInterface::ATOM),
        'end'   => $end->format(DateTimeInterface::ATOM),
        
        // ✅ Label per visualizzazione (orario locale)
        'label' => $start->format('H:i'), // Es: "19:00"
        
        'status'             => $status,
        'available_capacity' => $capacity,
        'requested_party'    => $party,
        'waitlist_available' => $waitlist && $status === 'full',
        'reasons'            => $reasons,
        'suggested_tables'   => $suggestions,
    ];
}
```

### 🎯 Punti Chiave

1. **Timezone SEMPRE esplicito**: Ogni `DateTimeImmutable` creato con `wp_timezone()` o `Europe/Rome`
2. **Nessuna conversione UTC**: Il timezone rimane costante per tutto il processo
3. **Label = orario locale**: Il formato `H:i` estrae automaticamente l'ora nel timezone dell'oggetto

---

## 🌐 API REST

### Endpoint: `/wp-json/fp-resv/v1/availability`

#### Request

```http
GET /wp-json/fp-resv/v1/availability?date=2025-11-02&party=2&meal=cena
```

**Parametri:**
- `date` (required): YYYY-MM-DD
- `party` (required): numero coperti (1-99)
- `meal` (optional): chiave meal plan
- `room` (optional): ID sala
- `event_id` (optional): ID evento

#### Response

```json
{
  "date": "2025-11-02",
  "timezone": "Europe/Rome",
  "criteria": {
    "date": "2025-11-02",
    "party": 2,
    "meal": "cena"
  },
  "slots": [
    {
      "start": "2025-11-02T19:00:00+01:00",
      "end": "2025-11-02T21:00:00+01:00",
      "label": "19:00",
      "status": "available",
      "available_capacity": 8,
      "requested_party": 2,
      "waitlist_available": false,
      "reasons": [],
      "suggested_tables": [1, 3, 5]
    },
    {
      "start": "2025-11-02T19:15:00+01:00",
      "end": "2025-11-02T21:15:00+01:00",
      "label": "19:15",
      "status": "available",
      "available_capacity": 8,
      "requested_party": 2,
      "waitlist_available": false,
      "reasons": [],
      "suggested_tables": [1, 3, 5]
    }
  ]
}
```

### 📝 Campi Slot

| Campo | Tipo | Descrizione | Usato da Frontend |
|-------|------|-------------|-------------------|
| `start` | string ISO 8601 | Data/ora inizio con timezone | No (solo per validazione) |
| `end` | string ISO 8601 | Data/ora fine con timezone | No |
| **`label`** | **string HH:MM** | **Orario da mostrare** | **✅ Sì (principale)** |
| `status` | string | available, full, blocked, limited | ✅ Sì (per colori) |
| `available_capacity` | int | Posti disponibili | ✅ Sì (per info) |
| `requested_party` | int | Coperti richiesti | No |
| `waitlist_available` | bool | Lista attesa disponibile | ✅ Sì (per UI) |
| `reasons` | array | Motivi blocco/limitazione | ✅ Sì (per tooltip) |
| `suggested_tables` | array | ID tavoli suggeriti | No (uso interno) |

---

## 💻 FRONTEND

### File: `assets/js/fe/components/slots-renderer.js`

```javascript
/**
 * Renderizza uno slot come bottone
 * @param {Object} slot - Oggetto slot dall'API
 * @param {string} slot.label - L'etichetta dello slot (es. "19:00")
 * @param {string} slot.status - Lo stato dello slot
 */
function renderSlot(slot) {
    const button = document.createElement('button');
    
    // ✅ Usa direttamente il label senza trasformazioni
    button.textContent = slot.label || '';
    
    // Applica classe CSS in base allo status
    button.className = `fp-slot-button fp-slot-${slot.status}`;
    
    return button;
}
```

### File: `assets/js/fe/onepage.js`

```javascript
// Quando l'utente seleziona uno slot
selectSlot(slot) {
    // ✅ Imposta il valore del campo time con il label
    timeField.value = slot && slot.label ? slot.label : '';
}
```

### 🎯 Punti Chiave Frontend

1. **Nessuna conversione**: Il frontend usa `slot.label` così com'è
2. **Nessun parsing Date**: Non si usa `new Date()` sugli orari (evita problemi timezone browser)
3. **Label già formattato**: Il backend restituisce già l'orario nel formato corretto

---

## 🌍 TIMEZONE E FORMATTAZIONE

### Costante Timezone

```php
// src/Domain/Reservations/Availability.php
private const DEFAULT_TIMEZONE = 'Europe/Rome';
```

### Funzione Resolve Timezone

```php
private function resolveTimezone(): DateTimeZone
{
    // Legge da impostazioni (con fallback a Europe/Rome)
    $tz = (string) $this->options->getField(
        'fp_resv_general', 
        'restaurant_timezone', 
        self::DEFAULT_TIMEZONE
    );

    try {
        return new DateTimeZone($tz !== '' ? $tz : self::DEFAULT_TIMEZONE);
    } catch (\Exception $e) {
        return new DateTimeZone(self::DEFAULT_TIMEZONE);
    }
}
```

### Tutti i Punti di Creazione DateTime

```php
// ✅ CORRETTO - Con timezone esplicito
$dayStart = new DateTimeImmutable($dateString . ' 00:00:00', $timezone);
$slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
$reservation->created = new DateTimeImmutable($row['created_at'], wp_timezone());

// ❌ EVITATO - Senza timezone (userebbe UTC o timezone PHP)
$date = new DateTimeImmutable($dateString); // ❌ NO!
```

### Formattazione Orari

```php
// ✅ Per visualizzazione (label slot)
$label = $start->format('H:i'); // → "19:00"

// ✅ Per API/storage (con timezone)
$isoDate = $start->format(DateTimeInterface::ATOM); // → "2025-11-02T19:00:00+01:00"

// ❌ MAI usare date() o gmdate()
$wrong = date('H:i'); // ❌ Timezone PHP di sistema
$wrong = gmdate('H:i'); // ❌ Sempre UTC
```

---

## 🧪 VERIFICA E TESTING

### Script di Verifica

```bash
# Esegui lo script di verifica
php wp-content/plugins/FP-Restaurant-Reservations/tools/verify-slot-times.php
```

Questo script verifica:
1. ✅ Timezone WordPress = Europe/Rome
2. ✅ Configurazione orari backend
3. ✅ Meal plans configurati
4. ✅ Generazione slot funzionante
5. ✅ Formato orari corretto

### Test Manuale

#### 1. Verifica Backend

1. Vai in **Admin → Restaurant Manager → Impostazioni**
2. Controlla sezione **Orari di Servizio**
3. Esempio: `sat=19:00-23:30`

#### 2. Verifica API

Apri browser e vai a:
```
https://tuosito.com/wp-json/fp-resv/v1/availability?date=2025-11-02&party=2
```

Controlla:
- ✅ Campo `timezone`: "Europe/Rome"
- ✅ Campo `label` degli slot: formato "HH:MM"
- ✅ Slot corrispondono agli orari configurati

#### 3. Verifica Frontend

1. Apri il form di prenotazione
2. Seleziona una data
3. Controlla gli slot visualizzati
4. Verifica che corrispondano agli orari configurati backend

### 📊 Esempio Completo

**Backend Config:**
```
sat=19:00-23:30
```

**API Response:**
```json
{
  "timezone": "Europe/Rome",
  "slots": [
    { "label": "19:00", "status": "available" },
    { "label": "19:15", "status": "available" },
    { "label": "19:30", "status": "available" },
    ...
    { "label": "23:00", "status": "available" },
    { "label": "23:15", "status": "available" }
  ]
}
```

**Frontend Display:**
```
[19:00] [19:15] [19:30] ... [23:00] [23:15]
```

✅ **Tutti gli orari corrispondono!**

---

## 📝 CHECKLIST SVILUPPATORI

### ✅ Best Practices

- [x] SEMPRE usare `wp_timezone()` o `new DateTimeZone('Europe/Rome')`
- [x] SEMPRE passare timezone esplicito a `DateTimeImmutable`
- [x] SEMPRE usare `wp_date()` o `current_time()` invece di `date()` / `gmdate()`
- [x] MAI convertire in UTC per la visualizzazione
- [x] MAI usare `toISOString()` in JavaScript per ottenere date
- [x] MAI manipolare orari come stringhe

### ❌ Anti-Patterns da Evitare

```php
// ❌ NO
$date = new DateTimeImmutable($str); // Timezone mancante
$time = gmdate('H:i'); // Sempre UTC
$time = date('H:i'); // Timezone PHP sistema

// ✅ SÌ
$date = new DateTimeImmutable($str, wp_timezone());
$time = wp_date('H:i');
$time = current_time('H:i');
```

```javascript
// ❌ NO
const dateStr = new Date().toISOString().split('T')[0]; // UTC!

// ✅ SÌ
const dateStr = formatLocalDate(new Date()); // Timezone locale
```

---

## 🎯 CONCLUSIONE

Il sistema di slot orari è:

✅ **Coerente**: Backend e frontend sempre allineati  
✅ **Corretto**: Timezone Europe/Rome in tutti i punti  
✅ **Testato**: Script di verifica disponibile  
✅ **Documentato**: Questa guida completa

**Non sono necessarie modifiche** - il sistema funziona correttamente! 

Per dubbi o problemi, verifica sempre:
1. Timezone WordPress = Europe/Rome
2. Configurazione orari backend popolata
3. API `/availability` restituisce slot con timezone corretto

---

**Autore:** Francesco Passeri  
**Data:** 2 Novembre 2025  
**Versione Doc:** 1.0


