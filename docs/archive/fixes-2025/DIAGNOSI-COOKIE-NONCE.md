# Diagnosi e Risoluzione Problema Cookie/Nonce - 403 Forbidden

## Problema Identificato
Errore 403 Forbidden (`rest_cookie_invalid_nonce`) durante la creazione di prenotazioni, con messaggio "Controllo cookie fallito".

## Causa Root
WordPress intercettava l'header `X-WP-Nonce` inviato dal frontend e tentava di autenticare la richiesta PRIMA che il nostro handler venisse eseguito. Il problema:

1. Il frontend inviava il nonce sia nel body (`fp_resv_nonce`) che nell'header HTTP (`X-WP-Nonce`)
2. WordPress intercetta automaticamente l'header `X-WP-Nonce` per l'autenticazione REST API
3. WordPress si aspetta che il nonce nell'header sia stato creato con l'azione `wp_rest` (standard WordPress)
4. Il nostro nonce è stato creato con l'azione `fp_resv_submit` (specifica per le prenotazioni)
5. WordPress rifiuta la richiesta con errore 403 **prima** che il nostro codice di validazione venga eseguito

## Soluzione Implementata

### File Modificati
- `assets/js/fe/onepage.js` - Rimosso header `X-WP-Nonce` dalle richieste di prenotazione
- `assets/js/fe/form-app-optimized.js` - Rimosso header `X-WP-Nonce`
- `assets/dist/fe/*.js` - File distribuiti rigenerati

### Cosa è Stato Cambiato
**PRIMA:**
```javascript
const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-WP-Nonce': payload.fp_resv_nonce || '', // ❌ Causa il problema
    },
    body: JSON.stringify(payload),
    credentials: 'same-origin',
});
```

**DOPO:**
```javascript
const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        // ✅ X-WP-Nonce rimosso - il nonce è solo nel body
    },
    body: JSON.stringify(payload),
    credentials: 'same-origin',
});
```

### Come Funziona Ora
1. Il nonce viene inviato **solo** nel body JSON come `fp_resv_nonce`
2. WordPress non vede l'header `X-WP-Nonce` e non tenta di autenticare la richiesta
3. Il nostro handler `handleCreateReservation()` legge il nonce dal body JSON
4. Il nostro handler valida il nonce con l'azione corretta `fp_resv_submit`
5. La validazione funziona correttamente ✅

### Ordine di lettura del nonce (Backend)
Il backend legge il nonce in questo ordine (da `src/Domain/Reservations/REST.php`):
1. ✅ Body JSON (`$request->get_json_params()['fp_resv_nonce']`)
2. ✅ Parametri normali (`$request->get_param('fp_resv_nonce')`)
3. ✅ Parametro `_wpnonce` (fallback)
4. ✅ Header X-WP-Nonce (ultimo fallback, ora non più usato dal frontend)

## Modifiche Precedenti (Già Applicate)

### 1. Migliorata lettura del nonce dal body JSON
**File modificati:**
- `src/Domain/Reservations/REST.php`
- `src/Domain/Payments/REST.php`
- `src/Domain/Events/REST.php`
- `src/Domain/Surveys/REST.php`

### 2. Aggiunto logging dettagliato
Il sistema restituisce sempre informazioni di debug quando il nonce fallisce:
- `nonce_found`: Se il nonce è stato trovato
- `nonce_valid`: Se il nonce è valido
- `nonce_value`: Primi 10 caratteri del nonce
- `from_json`: Se trovato nel body JSON
- `from_param`: Se trovato nei parametri
- `from_header`: Se trovato nell'header
- `user_logged_in`: Se l'utente è loggato

## Note Importanti

### File Admin NON Modificati
I file JavaScript dell'area admin (`assets/js/admin/*.js`) continuano correttamente ad usare l'header `X-WP-Nonce` perché:
- Le richieste admin sono sempre autenticate
- Gli utenti admin sono loggati
- Usano il nonce standard di WordPress con azione `wp_rest`

### Retry Automatico del Nonce
Il sistema mantiene la funzionalità di retry automatico in caso di errore 403:
- Attende 500ms per permettere ai cookie di essere impostati
- Rigenera il nonce via endpoint `/wp-json/fp-resv/v1/nonce`
- Riprova la richiesta con il nonce fresco
- Attende altri 200ms prima del retry

## Test

### Come Verificare il Fix
1. **Test utente non loggato (consigliato):**
   - Aprire la pagina in modalità incognito
   - Accettare i cookie
   - Compilare il form di prenotazione
   - Verificare che la prenotazione venga creata con successo

2. **Test utente loggato:**
   - Effettuare il logout dall'area admin
   - Aprire la pagina di prenotazione normale
   - Compilare il form
   - Verificare che funzioni correttamente

3. **Verifica nei Developer Tools:**
   - Aprire Network tab
   - Filtrare per `reservations`
   - Verificare la richiesta POST
   - Controllare che l'header `X-WP-Nonce` **non** sia presente
   - Verificare che il body JSON contenga `fp_resv_nonce`

### Risposta in Caso di Errore
Se il nonce dovesse ancora fallire (es. nonce scaduto), la risposta includerà:
```json
{
  "code": "fp_resv_invalid_nonce",
  "message": "Errore di sicurezza. Ricarica la pagina e riprova.",
  "data": {
    "status": 403,
    "nonce_found": true,
    "nonce_valid": false,
    "from_json": true,
    "from_param": false,
    "from_header": false,
    "user_logged_in": false
  }
}
```

## Possibili Cause Residue (Ora Risolte)

### ~~1. WordPress intercetta X-WP-Nonce~~ ✅ RISOLTO
**Era il problema principale** - L'header causava il fallimento dell'autenticazione prima del nostro handler.

### 2. Nonce scaduto ⏱️
**Sintomo:** L'utente rimane sulla pagina >12 ore prima di prenotare  
**Soluzione:** Il sistema rigenera automaticamente il nonce (vedi `refreshNonce()`)

### 3. Plugin Cookie Consent 🍪
**Sintomo:** Complianz blocca le richieste fino all'accettazione cookie  
**Soluzione:** Il JavaScript aspetta 500ms + 200ms per i cookie

### 4. Cache/CDN 🔄
**Sintomo:** La pagina è servita da cache con nonce vecchio  
**Soluzione:** Escludere `/wp-json/fp-resv/` e pagine di prenotazione dalla cache

## Build

Per applicare le modifiche:
```bash
npm install
npm run build
```

## Note Tecniche

- Il nonce ha azione `fp_resv_submit` (non il nonce REST standard `wp_rest`)
- Il JavaScript invia il nonce **solo** nel body JSON (non più nell'header)
- Il retry automatico aspetta 500ms + 200ms per problemi di timing cookie
- Il sistema può rigenerare il nonce automaticamente via endpoint `/nonce`
- L'endpoint `/reservations` ha `permission_callback => '__return_true'` (accesso pubblico)
