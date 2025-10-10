# API Backend Agenda - Documentazione

## Panoramica

Il backend dell'agenda è stato completamente riscritto in stile **TheFork** per fornire una risposta strutturata e ottimizzata che riduce il carico computazionale sul frontend.

## Endpoint

### GET `/wp-json/fp-resv/v1/agenda`

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

## Modifiche Future Pianificate

1. **Filtri aggiuntivi**
   - Per servizio (pranzo/cena)
   - Per sala/tavolo
   - Per stato prenotazione

2. **Capacità**
   - Integrazione con gestione tavoli
   - Calcolo capacità utilizzata per slot

3. **Metriche avanzate**
   - Tasso di occupazione
   - Trend prenotazioni
   - Analisi no-show

4. **Cache**
   - Sistema di cache per ridurre query database
   - Invalidazione intelligente alla modifica prenotazioni

## Migrazione

Il sistema è retrocompatibile. Il frontend funziona sia con la vecchia che con la nuova struttura API:

- **Nuova API**: usa `data.slots`, `data.days`, `stats`
- **Fallback**: usa l'array `reservations` e raggruppa client-side

Non sono necessarie modifiche per migrare, ma si consiglia di testare tutte le viste dell'agenda.
