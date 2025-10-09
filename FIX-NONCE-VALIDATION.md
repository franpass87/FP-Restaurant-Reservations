# Fix Validazione Nonce - Risoluzione Errore 403

## Problema Risolto
Errore 403 "rest_cookie_invalid_nonce" durante la creazione di prenotazioni.

## Causa Root
WordPress intercettava l'header `X-WP-Nonce` inviato dal frontend e tentava di autenticare la richiesta REST API usando il sistema di autenticazione dei cookie. Il nonce nell'header era stato creato con l'azione `fp_resv_submit`, ma WordPress si aspettava un nonce con azione `wp_rest`. Questo causava il fallimento dell'autenticazione **prima** che il nostro handler venisse eseguito.

## Soluzione Implementata

### 1. Modifiche JavaScript Frontend
**File modificati:**
- `assets/js/fe/onepage.js`
- `assets/js/fe/form-app-optimized.js`

**Cambiamento:** Rimosso l'header `X-WP-Nonce` dalle richieste POST all'endpoint `/wp-json/fp-resv/v1/reservations`

**Prima:**
```javascript
headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-WP-Nonce': payload.fp_resv_nonce || '',
}
```

**Dopo:**
```javascript
headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    // X-WP-Nonce rimosso
}
```

### 2. Test Bootstrap Aggiornato
**File modificato:** `tests/bootstrap.php`

**Aggiunte:**
- `get_json_params()` al mock `WP_REST_Request`
- `is_user_logged_in()` - restituisce `false` nei test
- `wp_create_nonce()` - genera nonce di test validi
- `wp_cache_get()`, `wp_cache_set()` - stub per caching
- `wp_cache_incr()`, `wp_cache_add()` - stub per rate limiter
- `wp_rand()` - wrapper per `rand()`

### 3. Asset Build
**Comando eseguito:**
```bash
npm install
npm run build
```

**File generati:**
- `assets/dist/fe/onepage.esm.js`
- `assets/dist/fe/onepage.iife.js`

## Come Funziona Ora

1. **Frontend**: Invia il nonce solo nel body JSON come `fp_resv_nonce`
2. **WordPress**: Non vede l'header `X-WP-Nonce`, quindi non tenta di autenticare
3. **Backend**: Legge il nonce dal body JSON e lo valida con l'azione `fp_resv_submit`
4. **Validazione**: Successo ✅

## Flusso di Lettura Nonce (Backend)
Il backend (`src/Domain/Reservations/REST.php`) legge il nonce in questo ordine:

1. Body JSON: `$request->get_json_params()['fp_resv_nonce']` ← **USATO ORA**
2. Parametri: `$request->get_param('fp_resv_nonce')`
3. Parametro alternativo: `$request->get_param('_wpnonce')`
4. Header: `$request->get_header('X-WP-Nonce')` (fallback, non più usato dal frontend)

## Test

### Verifica Manuale
1. Aprire Developer Tools → Network
2. Compilare il form di prenotazione
3. Inviare la richiesta
4. Verificare che:
   - La richiesta POST a `/wp-json/fp-resv/v1/reservations` **non** contenga l'header `X-WP-Nonce`
   - Il body JSON contenga `"fp_resv_nonce": "..."`
   - La risposta sia 201 Created con il messaggio di successo

### Test Automatizzati
```bash
vendor/bin/phpunit tests/Integration/Reservations/RestTest.php
```

I test verificano:
- Validazione del consenso obbligatorio
- Creazione prenotazione con successo
- Lettura corretta del nonce dai parametri della richiesta

## File NON Modificati

### Area Admin
I file JavaScript dell'area admin continuano ad usare `X-WP-Nonce`:
- `assets/js/admin/tables-layout.js`
- `assets/js/admin/reports-dashboard.js`
- `assets/js/admin/closures-app.js`
- `assets/js/admin/agenda-app.js`

**Motivo**: Le richieste admin sono sempre autenticate e usano correttamente il nonce standard WordPress con azione `wp_rest`.

## Documentazione Aggiornata
- `DIAGNOSI-COOKIE-NONCE.md` - Aggiornato con la soluzione completa

## Commit Suggerito
```
fix: risolvi errore 403 rest_cookie_invalid_nonce nelle prenotazioni

WordPress intercettava l'header X-WP-Nonce e falliva l'autenticazione
perché il nonce era stato creato con azione 'fp_resv_submit' invece di 'wp_rest'.

Soluzione: inviare il nonce solo nel body JSON, non nell'header HTTP.
Il backend lo legge correttamente dal body e lo valida con l'azione corretta.

Modifiche:
- Rimosso header X-WP-Nonce dalle richieste frontend
- Aggiornato test bootstrap con funzioni mancanti
- Rigenerati asset distribuiti

Fixes: errore 403 "Controllo cookie fallito"
```

## Note Tecniche

- L'endpoint `/reservations` ha `permission_callback => '__return_true'` (accesso pubblico)
- Il nonce è validato manualmente nel nostro handler con azione `fp_resv_submit`
- Il retry automatico del nonce è preservato (500ms + 200ms di attesa)
- I file admin continuano correttamente ad usare `X-WP-Nonce` per richieste autenticate
