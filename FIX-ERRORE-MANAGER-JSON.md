# 🔧 Fix Errore Manager - JSON Parsing

## 📊 Problema Riscontrato

```
[Manager] Error loading overview: SyntaxError: Unexpected end of JSON input
[Manager] Error loading reservations: SyntaxError: Failed to execute 'json' on 'Response'
```

---

## ✅ Cosa Ho Fatto

### 1. **Aggiornato JavaScript** (`assets/js/admin/manager-app.js`)

✅ **Logging Dettagliato**
- Verifica configurazione all'avvio
- Log di ogni chiamata API
- Log dello status HTTP
- Log del contenuto della risposta
- Log della lunghezza della risposta

✅ **Gestione Errori Migliorata**
- Controlla se la risposta è vuota prima di parsare
- Usa `response.text()` invece di `response.json()` direttamente
- Parse JSON manuale con try/catch
- Gestisce risposte vuote senza errore

✅ **Verifica Configurazione**
- Controlla che `fpResvManagerSettings` sia presente
- Controlla che nonce sia presente
- Mostra errore chiaro se configurazione manca

### 2. **Creato Script di Test** (`test-manager-endpoints.php`)

Script PHP che testa tutti gli endpoint e mostra:
- Status code
- Content-Type
- JSON validità
- Preview dei dati

**Uso**: Apri `wp-admin/admin.php?page=fp-resv-manager&test-endpoints=1`

### 3. **Creato Guida Debug** (`DEBUG-MANAGER-ENDPOINTS.md`)

Guida completa passo-passo per diagnosticare il problema.

---

## 🔍 Come Verificare il Fix

### Passo 1: Ricarica la Pagina

1. Apri `/wp-admin/admin.php?page=fp-resv-manager`
2. Apri Console Browser (F12)
3. Shift + F5 (hard reload)

### Passo 2: Leggi i Log in Console

Ora vedrai log dettagliati:

```javascript
[Manager] 🚀 Inizializzazione...
[Manager] Config: {restRoot: "/wp-json/fp-resv/v1", nonce: "...", ...}
[Manager] REST Root: /wp-json/fp-resv/v1
[Manager] Nonce: Present

[Manager] Loading reservations from: /wp-json/fp-resv/v1/agenda?date=2025-10-12&range=day
[Manager] Reservations response status: 200
[Manager] Reservations response text length: 1234
[Manager] Reservations response preview: {"meta":{"range":"day"...
[Manager] Reservations data loaded: {meta: {...}, stats: {...}, ...}

[Manager] ✅ Inizializzazione completata
```

### Passo 3: Identifica il Problema

#### ✅ **Caso A: Tutto OK**
```
[Manager] Response status: 200
[Manager] Response text length: 1234
[Manager] Data loaded: {...}
```
→ Il problema è risolto!

#### ⚠️ **Caso B: Risposta Vuota**
```
[Manager] Response status: 200
[Manager] Response text length: 0
[Manager] Response is empty
```
→ L'endpoint non sta ritornando dati. Vai a "Diagnosi Backend" sotto.

#### ❌ **Caso C: Errore HTTP**
```
[Manager] Response status: 403
[Manager] Response error: Forbidden
```
→ Problema di permessi. Vai a "Fix Permessi" sotto.

#### ❌ **Caso D: Configurazione Mancante**
```
[Manager] ❌ Configuration missing!
```
→ JavaScript non caricato correttamente. Vai a "Fix Configurazione" sotto.

---

## 🛠️ Soluzioni Rapide

### Fix 1: Svuota Cache Browser

```
1. Ctrl + Shift + Delete
2. Cancella cache e cookies
3. Ricarica pagina (Shift + F5)
```

### Fix 2: Verifica Backend è Registrato

Controlla che nel file `src/Core/Plugin.php` alla riga ~530 ci sia:

```php
$adminRest = new ReservationsAdminREST($reservationsRepository, $reservationsService, $googleCalendar, $tablesLayout);
$adminRest->register();
```

✅ **Verificato**: AdminREST è correttamente registrato nel bootstrap.

### Fix 3: Test Endpoint Manualmente

In Console Browser:

```javascript
// Test rapido endpoint
fetch('/wp-json/fp-resv/v1/agenda?date=2025-10-12', {
    headers: { 'X-WP-Nonce': window.fpResvManagerSettings.nonce }
})
.then(r => r.text())
.then(text => console.log('Response:', text));
```

### Fix 4: Verifica Permessi Utente

```javascript
// In console
console.log('User ID:', window.fpResvManagerSettings.userId);

// Oppure apri direttamente
window.location = '/wp-json/fp-resv/v1/agenda/overview';
```

Se vedi JSON → OK  
Se vedi errore 403 → Problema permessi  
Se vedi HTML → Problema routing

---

## 🐛 Diagnosi Backend

Se la risposta è vuota o corrotta, verifica:

### 1. **WP_DEBUG Attivo**

In `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 2. **Controlla Error Log**

```bash
tail -f wp-content/debug.log
```

Cerca errori tipo:
- PHP Fatal error
- PHP Warning
- PHP Notice

### 3. **Verifica Database**

```sql
-- Ci sono prenotazioni?
SELECT COUNT(*) FROM wp_fp_reservations;

-- Prenotazioni recenti
SELECT * FROM wp_fp_reservations 
WHERE date >= CURDATE() 
LIMIT 5;
```

### 4. **Test Endpoint PHP Diretto**

Crea file `test-endpoint.php` nella root:

```php
<?php
require_once('wp-load.php');

$request = new WP_REST_Request('GET', '/fp-resv/v1/agenda/overview');
$server = rest_get_server();
$response = $server->dispatch($request);

echo json_encode($response->get_data(), JSON_PRETTY_PRINT);
```

Poi apri: `https://tuosito.com/test-endpoint.php`

---

## 📋 Checklist Finale

Prima di contattare supporto, verifica:

- [x] JavaScript aggiornato con logging
- [ ] Cache browser svuotata
- [ ] Hard reload fatto (Shift + F5)
- [ ] Console aperta e log controllati
- [ ] `fpResvManagerSettings` presente in console
- [ ] Nonce presente
- [ ] User autenticato (verifica in alto a destra WP Admin)
- [ ] AdminREST registrato (verificato ✅)
- [ ] Endpoint esistono (testa manualmente)
- [ ] Permessi utente OK
- [ ] WP_DEBUG attivo
- [ ] Error log controllato
- [ ] Database ha dati

---

## 🚀 Prossimi Passi

1. **Ricarica la pagina manager**
2. **Apri console (F12)**
3. **Leggi i nuovi log dettagliati**
4. **Identifica esattamente dove fallisce**
5. **Segui la guida DEBUG-MANAGER-ENDPOINTS.md** per diagnosi approfondita

---

## 📞 Se Continua a Non Funzionare

Fornisci queste informazioni:

```
1. Screenshot console completa
2. Output di: /wp-json/fp-resv/v1/
3. Output di: /wp-json/fp-resv/v1/agenda/overview
4. Output di: console.log(window.fpResvManagerSettings)
5. wp-content/debug.log (ultime 50 righe)
```

---

## ✅ Riepilogo Fix Applicati

| File | Modifica | Stato |
|------|----------|-------|
| `assets/js/admin/manager-app.js` | Logging dettagliato + error handling | ✅ Aggiornato |
| `test-manager-endpoints.php` | Script di test | ✅ Creato |
| `DEBUG-MANAGER-ENDPOINTS.md` | Guida debug completa | ✅ Creato |
| `FIX-ERRORE-MANAGER-JSON.md` | Questo documento | ✅ Creato |

---

**Con il nuovo JavaScript, vedrai ESATTAMENTE cosa sta succedendo e potrai identificare il problema specifico.**

🎯 **Ricarica la pagina ora e controlla la console!**

