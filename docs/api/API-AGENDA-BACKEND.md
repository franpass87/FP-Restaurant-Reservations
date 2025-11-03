# API Backend Agenda - Documentazione

## Panoramica

Il backend dell'agenda è stato completamente riscritto in stile **TheFork** per fornire una risposta strutturata e ottimizzata che riduce il carico computazionale sul frontend.

## Endpoint

### 1. GET `/wp-json/fp-resv/v1/agenda`

Recupera le prenotazioni organizzate per la vista selezionata.

#### Parametri Query

| Parametro | Tipo | Obbligatorio | Default | Descrizione |
|-----------|------|--------------|---------|-------------|
| `date` | string | No | Oggi | Data in formato `YYYY-MM-DD` |
| `range` | string | No | `day` | Modalità vista: `day`, `week`, `month` |
| `service` | string | No | - | Filtro servizio (non ancora implementato) |

#### Esempi di Richieste

```http
GET /wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day
GET /wp-json/fp-resv/v1/agenda?date=2025-10-10&range=week
GET /wp-json/fp-resv/v1/agenda?date=2025-10-01&range=month
```

#### Struttura Risposta

La risposta è un oggetto JSON con la seguente struttura:

```json
{
  "meta": {
    "range": "day|week|month",
    "start_date": "2025-10-10",
    "end_date": "2025-10-10",
    "current_date": "2025-10-10"
  },
  "stats": {
    "total_reservations": 15,
    "total_guests": 42,
    "by_status": {
      "pending": 3,
      "confirmed": 10,
      "visited": 1,
      "no_show": 0,
      "cancelled": 1
    },
    "confirmed_percentage": 66.7
  },
  "data": {
    "slots": [...],      // Per vista 'day'
    "days": [...],       // Per vista 'week' o 'month'
    "timeline": [...]    // Alias di 'slots'
  },
  "reservations": [...]  // Array piatto per compatibilità
}
```

### Oggetto `meta`

Contiene metadati sulla richiesta e il range temporale.

```json
{
  "range": "week",
  "start_date": "2025-10-06",
  "end_date": "2025-10-12",
  "current_date": "2025-10-10"
}
```

### Oggetto `stats`

Statistiche aggregate calcolate server-side.

```json
{
  "total_reservations": 15,
  "total_guests": 42,
  "by_status": {
    "pending": 3,
    "confirmed": 10,
    "visited": 1,
    "no_show": 0,
    "cancelled": 1
  },
  "confirmed_percentage": 66.7
}
```

### Oggetto `data`

Dati organizzati in base alla vista richiesta.

#### Vista Giornaliera (`range=day`)

```json
{
  "slots": [
    {
      "time": "12:00",
      "reservations": [...],
      "total_guests": 8,
      "capacity_used": 0
    },
    {
      "time": "19:30",
      "reservations": [...],
      "total_guests": 12,
      "capacity_used": 0
    }
  ],
  "timeline": [...]  // Alias di 'slots'
}
```

Gli slot vengono generati automaticamente per:
- **Pranzo**: 12:00 - 15:00 (ogni 15 minuti)
- **Cena**: 19:00 - 23:00 (ogni 15 minuti)

Le prenotazioni vengono raggruppate nello slot più vicino.

#### Vista Settimanale/Mensile (`range=week` o `range=month`)

```json
{
  "days": [
    {
      "date": "2025-10-06",
      "day_name": "Lunedì",
      "day_number": 6,
      "reservations": [...],
      "total_guests": 15,
      "reservation_count": 5
    },
    {
      "date": "2025-10-07",
      "day_name": "Martedì",
      "day_number": 7,
      "reservations": [...],
      "total_guests": 18,
      "reservation_count": 6
    }
  ]
}
```

### Array `reservations`

Array piatto di tutte le prenotazioni nel range richiesto, mantenuto per compatibilità con il frontend esistente.

```json
[
  {
    "id": 123,
    "status": "confirmed",
    "date": "2025-10-10",
    "time": "19:30",
    "slot_start": "2025-10-10 19:30",
    "party": 4,
    "notes": "Allergia ai crostacei",
    "allergies": "",
    "room_id": null,
    "table_id": null,
    "customer": {
      "id": 45,
      "first_name": "Mario",
      "last_name": "Rossi",
      "email": "mario.rossi@example.com",
      "phone": "+39 123 456 7890",
      "language": "it"
    }
  }
]
```

### 2. GET `/wp-json/fp-resv/v1/agenda/stats`

Endpoint dedicato per statistiche dettagliate con breakdown temporale.

#### Parametri Query

| Parametro | Tipo | Obbligatorio | Default | Descrizione |
|-----------|------|--------------|---------|-------------|
| `date` | string | No | Oggi | Data in formato `YYYY-MM-DD` |
| `range` | string | No | `day` | Modalità vista: `day`, `week`, `month` |

#### Esempio Richiesta

```http
GET /wp-json/fp-resv/v1/agenda/stats?date=2025-10-10&range=week
```

#### Struttura Risposta

```json
{
  "range": {
    "mode": "week",
    "start": "2025-10-06",
    "end": "2025-10-12"
  },
  "stats": {
    "total_reservations": 42,
    "total_guests": 156,
    "by_status": {
      "pending": 8,
      "confirmed": 30,
      "visited": 2,
      "no_show": 1,
      "cancelled": 1
    },
    "confirmed_percentage": 71.4,
    "by_service": {
      "lunch": {
        "count": 18,
        "guests": 65
      },
      "dinner": {
        "count": 22,
        "guests": 85
      },
      "other": {
        "count": 2,
        "guests": 6
      }
    },
    "average_party_size": 3.7,
    "median_party_size": 4,
    "by_day_of_week": {
      "Monday": {"count": 5, "guests": 18},
      "Tuesday": {"count": 6, "guests": 22},
      "Wednesday": {"count": 7, "guests": 25},
      "Thursday": {"count": 6, "guests": 21},
      "Friday": {"count": 8, "guests": 30},
      "Saturday": {"count": 7, "guests": 26},
      "Sunday": {"count": 3, "guests": 14}
    }
  }
}
```

#### Statistiche Calcolate

- **by_service**: Breakdown per servizio (pranzo: 12:00-17:00, cena: 19:00-24:00)
- **average_party_size**: Media coperti per prenotazione
- **median_party_size**: Mediana coperti per prenotazione
- **by_day_of_week**: Breakdown per giorno della settimana (solo per `week` e `month`)

### 3. GET `/wp-json/fp-resv/v1/agenda/overview`

Overview dashboard con metriche aggregate per oggi, settimana e mese, includendo trend.

#### Parametri Query

Nessun parametro richiesto. L'endpoint restituisce automaticamente dati per:
- Oggi
- Questa settimana (lunedì - domenica)
- Questo mese

#### Esempio Richiesta

```http
GET /wp-json/fp-resv/v1/agenda/overview
```

#### Struttura Risposta

```json
{
  "today": {
    "date": "2025-10-10",
    "stats": {
      "total_reservations": 15,
      "total_guests": 42,
      "by_status": {...},
      "confirmed_percentage": 66.7
    }
  },
  "week": {
    "start": "2025-10-06",
    "end": "2025-10-12",
    "stats": {
      "total_reservations": 42,
      "total_guests": 156,
      "by_status": {...},
      "confirmed_percentage": 71.4
    }
  },
  "month": {
    "start": "2025-10-01",
    "end": "2025-10-31",
    "stats": {
      "total_reservations": 180,
      "total_guests": 650,
      "by_status": {...},
      "confirmed_percentage": 68.9
    }
  },
  "trends": {
    "daily": {
      "trend": "up",
      "vs_week_average": 12.5
    },
    "weekly": {
      "trend": "stable",
      "average_per_day": 6.0,
      "vs_month_average": 3.2
    },
    "monthly": {
      "average_per_day": 5.8,
      "total": 180
    }
  }
}
```

#### Trend Indicators

I trend vengono calcolati confrontando periodi diversi:

- **trend**: `up`, `down`, o `stable` (soglia ±10%)
- **vs_week_average**: Percentuale differenza oggi vs media settimanale
- **vs_month_average**: Percentuale differenza settimana vs media mensile

## Vantaggi della Nuova Struttura

### 1. **Performance**
- Riduzione del carico computazionale sul client
- Dati già organizzati per slot/giorni
- Statistiche pre-calcolate server-side

### 2. **Scalabilità**
- Supporta facilmente migliaia di prenotazioni
- Il raggruppamento avviene server-side in modo efficiente

### 3. **Mantenibilità**
- Logica di business centralizzata nel backend
- Frontend più semplice e leggibile
- Facile aggiungere nuove viste/filtri

### 4. **Compatibilità**
- Mantiene l'array `reservations` piatto per backward compatibility
- Il frontend può scegliere di usare i dati strutturati o fallback

## Implementazione Frontend

Il JavaScript dell'agenda controlla automaticamente se sono disponibili i dati strutturati:

```javascript
// Usa i dati preorganizzati dal backend se disponibili
const backendData = window.fpResvAgendaData || {};

if (backendData.slots && Array.isArray(backendData.slots)) {
    // Usa gli slot preorganizzati dal backend
    slots = backendData.slots;
} else {
    // Fallback: raggruppa client-side
    slots = groupByTimeSlot(reservations);
}
```

## Metodi Helper Backend

### `calculateStats(array $reservations): array`
Calcola statistiche aggregate sulle prenotazioni.

### `organizeByView(array $reservations, string $viewMode, ...): array`
Organizza le prenotazioni in base alla vista richiesta.

### `organizeByTimeSlots(array $reservations): array`
Organizza le prenotazioni per slot orari (vista giornaliera).

### `organizeByDays(array $reservations, DateTimeImmutable $startDate, int $numDays): array`
Organizza le prenotazioni per giorni (vista settimanale/mensile).

### `generateTimeSlots(): array`
Genera slot orari standard (12:00-15:00, 19:00-23:00, ogni 15 minuti).

### `findNearestSlot(string $time, array $slots): string`
Trova lo slot più vicino a un orario dato, arrotondando a 15 minuti.

### `getDayName(DateTimeImmutable $date): string`
Restituisce il nome del giorno in italiano.

## Test

### Script di Test Automatici

È disponibile uno script bash per testare tutti gli endpoint:

```bash
# Test su localhost
./tests/test-agenda-endpoints.sh http://localhost:8080

# Test su server remoto
./tests/test-agenda-endpoints.sh https://tuosito.com
```

Lo script verifica:
- Status code HTTP 200
- Validità JSON risposta
- Presenza chiavi richieste
- Struttura dati corretta

### Test Manuali con cURL

Vedi `tests/test-agenda-api-examples.md` per esempi completi di test con cURL, validazione risposte, test errori e performance testing.

Esempio rapido:

```bash
# Test endpoint agenda
curl "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" | jq

# Test statistiche
curl "http://localhost:8080/wp-json/fp-resv/v1/agenda/stats?date=2025-10-10&range=week" | jq

# Test overview
curl "http://localhost:8080/wp-json/fp-resv/v1/agenda/overview" | jq
```

## Modifiche Future Pianificate

1. **Filtri aggiuntivi** ✅ PARZIALMENTE IMPLEMENTATO
   - ✅ Per servizio (pranzo/cena) - disponibile in stats
   - ⏳ Filtro query parametro per sala/tavolo
   - ⏳ Filtro per stato prenotazione

2. **Capacità**
   - Integrazione con gestione tavoli
   - Calcolo capacità utilizzata per slot
   - Alert quando si supera capacità

3. **Metriche avanzate** ✅ IMPLEMENTATO
   - ✅ Breakdown per servizio
   - ✅ Trend giornalieri/settimanali
   - ✅ Analisi per giorno settimana
   - ⏳ Tasso di occupazione
   - ⏳ Analisi no-show dettagliata

4. **Cache**
   - Sistema di cache Redis/Transients
   - Invalidazione intelligente
   - TTL configurabile

5. **Export e Reporting**
   - Export CSV/Excel statistiche
   - Report PDF automatici
   - Dashboard grafici interattivi

## Migrazione

Il sistema è retrocompatibile. Il frontend funziona sia con la vecchia che con la nuova struttura API:

- **Nuova API**: usa `data.slots`, `data.days`, `stats`
- **Fallback**: usa l'array `reservations` e raggruppa client-side

Non sono necessarie modifiche per migrare, ma si consiglia di testare tutte le viste dell'agenda.
