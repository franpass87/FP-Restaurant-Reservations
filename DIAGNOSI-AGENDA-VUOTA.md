# Diagnosi: Agenda mostra "Nessuna prenotazione" con dati presenti

## 📋 Problema

L'agenda mostra lo stato "Nessuna prenotazione" nonostante ci siano prenotazioni nel database (2 prenotazioni arrivate ieri).

## 🔍 Analisi Tecnica

### Flusso di caricamento dati

1. **Frontend** (`assets/js/admin/agenda-app.js`):
   - Chiama GET `/wp-json/fp-resv/v1/agenda?date=YYYY-MM-DD`
   - Riceve array di prenotazioni o array vuoto
   - Se array vuoto → mostra empty state

2. **Backend** (`src/Domain/Reservations/AdminREST.php::handleAgenda`):
   - Riceve parametri `date` e `range` (day/week/month)
   - Chiama `Repository::findAgendaRange($startDate, $endDate)`
   - Restituisce array di prenotazioni mappate

3. **Database** (`src/Domain/Reservations/Repository.php::findAgendaRange`):
   ```sql
   SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang 
   FROM wp_fp_reservations r 
   LEFT JOIN wp_fp_customers c ON r.customer_id = c.id 
   WHERE r.date BETWEEN %s AND %s 
   ORDER BY r.date ASC, r.time ASC
   ```

### Possibili Cause

#### 1. ⚠️ Data selezionata errata
**CAUSA PIÙ PROBABILE**: Le prenotazioni sono di ieri ma l'agenda mostra oggi.

**Soluzione**:
- Verifica la data mostrata nel date picker in alto
- Clicca su "←" per andare al giorno precedente
- Oppure seleziona manualmente la data di ieri

#### 2. 🔐 Problema di autenticazione/nonce

**Sintomi**:
- Console browser mostra errore 403 o 401
- Messaggio "Accesso negato" nell'empty state

**Verifica**:
```javascript
// Apri DevTools (F12) → Console
console.log(window.fpResvAgendaSettings);
// Deve mostrare:
// {
//   restRoot: "/wp-json/fp-resv/v1",
//   nonce: "abc123...",  // ← deve essere presente
//   ...
// }
```

**Soluzione**:
- Ricarica la pagina con Ctrl+Shift+R (hard refresh)
- Fai logout e login di nuovo
- Verifica di avere i permessi `manage_fp_reservations`

#### 3. 📦 Cache del browser

**Sintomi**:
- I dati non si aggiornano mai
- Le modifiche non si vedono

**Soluzione**:
```bash
# 1. Hard refresh
Ctrl + Shift + R (Windows/Linux)
Cmd + Shift + R (Mac)

# 2. Cancella cache del browser per questo sito
# 3. Prova in modalità incognito
```

#### 4. 🌐 Errore API o rete

**Sintomi**:
- Console mostra errori di rete
- Empty state mostra "Errore di connessione"

**Verifica con DevTools**:
1. Apri DevTools (F12)
2. Tab "Network"
3. Ricarica la pagina
4. Cerca richiesta a `/wp-json/fp-resv/v1/agenda`
5. Verifica:
   - Status: deve essere 200 OK
   - Response: deve essere array JSON

**Esempio response corretta**:
```json
[
  {
    "id": 101,
    "date": "2025-10-10",
    "time": "19:00",
    "slot_start": "2025-10-10 19:00",
    "status": "confirmed",
    "party": 2,
    "customer": {
      "first_name": "Mario",
      "last_name": "Rossi",
      "email": "mario@example.com"
    }
  }
]
```

#### 5. 🗓️ Filtro servizio attivo

**Sintomi**:
- Select "Tutti i servizi" è impostato su "Pranzo" o "Cena"
- Le prenotazioni ci sono ma per l'altro servizio

**Soluzione**:
- Verifica il dropdown "Tutti i servizi" sia selezionato
- Cambia filtro e riprova

#### 6. 💾 Prenotazioni non committate nel database

**Verifica con SQL**:
```sql
-- Conta tutte le prenotazioni
SELECT COUNT(*) FROM wp_fp_reservations;

-- Prenotazioni di ieri
SELECT id, date, time, status, party, customer_first_name, customer_last_name
FROM wp_fp_reservations
WHERE date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
ORDER BY time;

-- Prenotazioni di oggi
SELECT id, date, time, status, party, customer_first_name, customer_last_name
FROM wp_fp_reservations
WHERE date = CURDATE()
ORDER BY time;
```

## 🛠️ Script di Debug

### 1. Script Debug via Browser (RACCOMANDATO)

**ISTRUZIONI**:

1. Lo script `debug-agenda.php` è già pronto nella root del plugin
2. Accedi come amministratore WordPress
3. Vai all'URL:
   ```
   https://tuosito.com/wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
   ```
4. Lo script mostrerà:
   - ✅ Tutte le prenotazioni nel database
   - ✅ Prenotazioni di ieri in dettaglio
   - ✅ Test del Repository
   - ✅ Test dell'endpoint API
   - ✅ Permessi utente
   - ✅ Diagnosi completa del problema

5. **IMPORTANTE**: Elimina il file dopo il debug!
   ```bash
   rm wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
   ```

### 2. Test query SQL diretta

Se non puoi accedere via browser, esegui nel database WordPress (phpMyAdmin, Adminer, ecc.):

```sql
-- Verifica tabelle
SHOW TABLES LIKE '%fp_%';

-- Conta prenotazioni totali
SELECT COUNT(*) as totale FROM wp_fp_reservations;

-- Prenotazioni di ieri
SELECT r.id, r.date, r.time, r.status, r.party, 
       c.first_name, c.last_name, c.email, r.created_at
FROM wp_fp_reservations r
LEFT JOIN wp_fp_customers c ON r.customer_id = c.id
WHERE r.date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
ORDER BY r.time;

-- Prenotazioni per data (ultimi 7 giorni)
SELECT date, COUNT(*) as count 
FROM wp_fp_reservations 
WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY date 
ORDER BY date DESC;

-- Ultime 10 prenotazioni create
SELECT id, date, time, status, party, created_at
FROM wp_fp_reservations
ORDER BY created_at DESC
LIMIT 10;
```

### 2. Test endpoint API

```bash
# Con wp-cli (da shell del server)
wp eval '
$repo = new \FP\Resv\Domain\Reservations\Repository($GLOBALS["wpdb"]);
$today = date("Y-m-d");
$results = $repo->findAgendaRange($today, $today);
echo "Prenotazioni oggi: " . count($results) . "\n";
var_dump($results);
'

# O con curl (sostituisci URL e cookie)
curl 'https://tuosito.com/wp-json/fp-resv/v1/agenda?date=2025-10-10' \
  -H 'Cookie: wordpress_logged_in_xxx=...' \
  -H 'X-WP-Nonce: xxx'
```

### 3. Script PHP di diagnosi

Usa gli script creati:

```bash
# Se hai accesso CLI
php tools/diagnose-agenda-empty.php
php tools/test-agenda-query.php

# Oppure caricali via browser
# Crea file: wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
# Accedi a: https://tuosito.com/wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
```

## ✅ Checklist di Risoluzione

- [ ] **Verificare data selezionata** - È impostata su ieri quando sono arrivate le prenotazioni?
- [ ] **Aprire DevTools Console** - Ci sono errori JavaScript?
- [ ] **Controllare Network tab** - La chiamata API restituisce 200 OK?
- [ ] **Verificare response API** - Contiene l'array di prenotazioni?
- [ ] **Controllare filtro servizio** - È impostato su "Tutti i servizi"?
- [ ] **Hard refresh** - Ctrl+Shift+R per eliminare cache
- [ ] **Verificare nonce** - `window.fpResvAgendaSettings.nonce` è presente?
- [ ] **Query database diretta** - Le prenotazioni esistono realmente?

## 📞 Prossimi Passi

### Scenario A: Le prenotazioni sono di ieri

**Soluzione immediata**:
1. Clicca sul pulsante "←" (prev-period) nell'agenda
2. Oppure seleziona la data di ieri manualmente nel date picker

### Scenario B: Errore API (403/401)

**Soluzione**:
1. Fai logout
2. Fai login di nuovo
3. Svuota cache browser
4. Ricarica la pagina

### Scenario C: Prenotazioni non in database

**Verifica**:
1. Le prenotazioni sono state create correttamente?
2. C'è stato un errore durante la creazione?
3. Controlla i log di WordPress per errori database

### Scenario D: Bug JavaScript

**Soluzione**:
1. Svuota cache JavaScript: `rm -rf wp-content/cache/*`
2. Se usi cache plugin (WP Rocket, W3 Total Cache), svuota anche quella
3. Rigenera asset: `cd wp-content/plugins/fp-restaurant-reservations && npm run build`

## 🔧 Codice di Debug Console

Esegui nella Console DevTools della pagina agenda:

```javascript
// Mostra configurazione
console.log('Settings:', window.fpResvAgendaSettings);

// Test chiamata API manuale
fetch(window.fpResvAgendaSettings.restRoot + '/agenda?date=2025-10-10', {
  headers: {
    'X-WP-Nonce': window.fpResvAgendaSettings.nonce,
    'Content-Type': 'application/json'
  },
  credentials: 'same-origin'
})
.then(r => r.json())
.then(data => {
  console.log('API Response:', data);
  console.log('Count:', data.length);
})
.catch(err => console.error('API Error:', err));
```

## 📋 Log da Raccogliere

Se il problema persiste, raccogli questi log:

1. **Console Browser** (F12 → Console)
   - Screenshot di eventuali errori rossi

2. **Network Tab** (F12 → Network)
   - Request a `/wp-json/fp-resv/v1/agenda`
   - Headers (Request + Response)
   - Response body

3. **PHP Error Log**
   ```bash
   tail -f /path/to/wp-content/debug.log
   ```

4. **Query Database**
   ```sql
   SELECT COUNT(*) as total FROM wp_fp_reservations;
   SELECT date, COUNT(*) as count FROM wp_fp_reservations GROUP BY date ORDER BY date DESC LIMIT 10;
   ```

---

**Creato**: 2025-10-11  
**Branch**: `cursor/no-booking-found-handler-2d10`  
**Stato**: In diagnosi
