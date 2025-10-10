# Esempi Test API Agenda

## Esempi cURL per Testing Manuale

### 1. Endpoint Agenda - Vista Giornaliera

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" \
  -H "Content-Type: application/json" | jq
```

**Risposta Attesa:**
```json
{
  "meta": {
    "range": "day",
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
    "slots": [
      {
        "time": "12:00",
        "reservations": [...],
        "total_guests": 8,
        "capacity_used": 0
      }
    ],
    "timeline": [...]
  },
  "reservations": [...]
}
```

### 2. Endpoint Agenda - Vista Settimanale

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=week" \
  -H "Content-Type: application/json" | jq
```

**Risposta Attesa:**
```json
{
  "meta": {
    "range": "week",
    "start_date": "2025-10-06",
    "end_date": "2025-10-12",
    "current_date": "2025-10-10"
  },
  "stats": {...},
  "data": {
    "days": [
      {
        "date": "2025-10-06",
        "day_name": "Lunedì",
        "day_number": 6,
        "reservations": [...],
        "total_guests": 15,
        "reservation_count": 5
      },
      ...
    ]
  },
  "reservations": [...]
}
```

### 3. Endpoint Agenda - Vista Mensile

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-01&range=month" \
  -H "Content-Type: application/json" | jq
```

### 4. Endpoint Statistiche Dettagliate

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda/stats?date=2025-10-10&range=week" \
  -H "Content-Type: application/json" | jq
```

**Risposta Attesa:**
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
    "by_status": {...},
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
      ...
    }
  }
}
```

### 5. Endpoint Overview Dashboard

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda/overview" \
  -H "Content-Type: application/json" | jq
```

**Risposta Attesa:**
```json
{
  "today": {
    "date": "2025-10-10",
    "stats": {
      "total_reservations": 15,
      "total_guests": 42,
      ...
    }
  },
  "week": {
    "start": "2025-10-06",
    "end": "2025-10-12",
    "stats": {...}
  },
  "month": {
    "start": "2025-10-01",
    "end": "2025-10-31",
    "stats": {...}
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

## Test con Autenticazione WordPress

Se l'endpoint richiede autenticazione:

```bash
# Con cookie di sessione
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" \
  -H "Content-Type: application/json" \
  -b "wordpress_logged_in_xxx=..." | jq

# Con Application Password
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" \
  -H "Content-Type: application/json" \
  -u "username:application_password" | jq
```

## Validazione Risposte

### Verifica Struttura JSON

```bash
# Verifica che meta esiste
curl -s "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" | \
  jq '.meta'

# Verifica conteggio slots
curl -s "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" | \
  jq '.data.slots | length'

# Verifica numero giorni settimana
curl -s "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=week" | \
  jq '.data.days | length'  # Dovrebbe essere 7

# Verifica trend
curl -s "http://localhost:8080/wp-json/fp-resv/v1/agenda/overview" | \
  jq '.trends.daily.trend'
```

## Test Errori

### Data Invalida

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=invalid&range=day" \
  -H "Content-Type: application/json" | jq
```

### Range Invalido

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=invalid" \
  -H "Content-Type: application/json" | jq
```

### Senza Autenticazione (se richiesta)

```bash
curl -X GET "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day" \
  -H "Content-Type: application/json" | jq
# Dovrebbe restituire 401 Unauthorized o 403 Forbidden
```

## Performance Testing

### Test con Apache Bench

```bash
# 100 richieste, 10 concorrenti
ab -n 100 -c 10 "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day"
```

### Test con wrk

```bash
# 10 secondi, 2 thread, 10 connessioni
wrk -t2 -c10 -d10s "http://localhost:8080/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day"
```

## Esempi JavaScript (Frontend)

### Fetch API

```javascript
// Vista giornaliera
fetch('/wp-json/fp-resv/v1/agenda?date=2025-10-10&range=day', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
})
.then(response => response.json())
.then(data => {
  console.log('Statistiche:', data.stats);
  console.log('Slots:', data.data.slots);
  console.log('Prenotazioni:', data.reservations);
});

// Overview
fetch('/wp-json/fp-resv/v1/agenda/overview', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
})
.then(response => response.json())
.then(data => {
  console.log('Trend giornaliero:', data.trends.daily);
  console.log('Statistiche oggi:', data.today.stats);
});
```

### jQuery

```javascript
$.ajax({
  url: '/wp-json/fp-resv/v1/agenda/stats',
  method: 'GET',
  data: {
    date: '2025-10-10',
    range: 'week'
  },
  beforeSend: function(xhr) {
    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
  },
  success: function(response) {
    console.log('Statistiche dettagliate:', response.stats);
  }
});
```

## Note

- Tutti gli endpoint richiedono permessi `manage_fp_reservations` o `manage_options`
- Le date devono essere in formato `YYYY-MM-DD`
- Il range può essere: `day`, `week`, `month`
- Le risposte sono sempre in formato JSON
- HTTP Status 200 = successo
- HTTP Status 400 = richiesta invalida
- HTTP Status 401/403 = non autorizzato
- HTTP Status 500 = errore server
