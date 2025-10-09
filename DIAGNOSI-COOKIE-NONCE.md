# Diagnosi Problema Cookie/Nonce - 403 Forbidden

## Problema
Errore 403 Forbidden durante la creazione di prenotazioni, con messaggio "Controllo cookie fallito".

## Modifiche Applicate

### 1. Migliorata lettura del nonce dal body JSON
**File modificati:**
- `src/Domain/Reservations/REST.php`
- `src/Domain/Payments/REST.php`
- `src/Domain/Events/REST.php`
- `src/Domain/Surveys/REST.php`

**Cosa fa:** Il backend ora legge esplicitamente il nonce dal body JSON PRIMA di fare fallback all'header X-WP-Nonce.

**Ordine di lettura del nonce:**
1. ✅ Body JSON (`$request->get_json_params()['fp_resv_nonce']`)
2. ✅ Parametri normali (`$request->get_param('fp_resv_nonce')`)
3. ✅ Header X-WP-Nonce (solo come ultimo fallback)

### 2. Aggiunto logging dettagliato
Il sistema ora restituisce SEMPRE informazioni di debug quando il nonce fallisce:
- `nonce_found`: Se il nonce è stato trovato
- `nonce_valid`: Se il nonce è valido
- `nonce_value`: Primi 10 caratteri del nonce
- `from_json`: Se trovato nel body JSON
- `from_param`: Se trovato nei parametri
- `from_header`: Se trovato nell'header
- `user_logged_in`: Se l'utente è loggato

## Possibili Cause Rimanenti

### 1. ⏱️ Nonce scaduto
**Sintomo:** L'utente rimane sulla pagina troppo a lungo prima di prenotare
**Soluzione:** Il sistema già tenta di rigenerare automaticamente il nonce (vedi `refreshNonce()` in onepage.js)

### 2. 🍪 Plugin Cookie Consent
**Sintomo:** Complianz o altro plugin blocca le richieste fino all'accettazione cookie
**Nota:** Dai cookie HTTP si vede `cmplz_*` (Complianz Cookie Consent attivo)
**Soluzione già implementata:** Il JavaScript aspetta 500ms + 200ms per dare tempo ai cookie di essere impostati

### 3. 👤 Problema utente loggato vs non loggato
**Sintomo:** L'utente (Dianel_Admin nell'esempio) è loggato come amministratore
**Possibile causa:** WordPress genera nonce diversi per utenti loggati vs anonimi
**Come verificare:** Testare sia loggato che non loggato

### 4. 🔄 Cache/CDN
**Sintomo:** La pagina è servita da cache, il nonce è vecchio
**Soluzione:** Escludere le pagine di prenotazione dalla cache

## Come Testare

### 1. Verifica informazioni di debug
Quando si riceve l'errore 403, controllare la risposta JSON. Ora include:
```json
{
  "code": "fp_resv_invalid_nonce",
  "message": "Errore di sicurezza. Ricarica la pagina e riprova.",
  "data": {
    "status": 403,
    "nonce_found": true/false,
    "nonce_valid": true/false,
    "from_json": true/false,
    "from_param": true/false,
    "from_header": true/false,
    "user_logged_in": true/false
  }
}
```

### 2. Test manuali
1. **Test utente non loggato:**
   - Aprire pagina in incognito
   - Accettare cookie
   - Tentare prenotazione
   
2. **Test utente loggato:**
   - Logout da admin
   - Aprire pagina normale
   - Tentare prenotazione

3. **Test nonce scaduto:**
   - Aprire pagina
   - Aspettare >12 ore (durata default nonce WordPress)
   - Tentare prenotazione
   - Verificare che il sistema rigener automaticamente il nonce

### 3. Browser Console
Aprire Developer Tools → Console e cercare:
```
[fp-resv] Impossibile rigenerare il nonce
```

## Prossimi Passi se il Problema Persiste

1. **Verificare quale fonte viene usata:**
   - Controllare `from_json`, `from_param`, `from_header` nella risposta 403
   - Se usa sempre `from_header`, verificare che il payload JSON sia corretto

2. **Verificare cookie consent:**
   - Controllare se Complianz blocca le richieste prima dell'accettazione
   - Verificare che `fp_resv_consent` sia impostato nei cookie

3. **Verificare cache:**
   - Escludere `/wp-json/fp-resv/` dalla cache
   - Escludere pagine con form prenotazione dalla cache

4. **Test endpoint nonce:**
   ```bash
   curl -X GET https://www.villadianella.it/wp-json/fp-resv/v1/reservations/nonce
   ```
   Dovrebbe restituire: `{"nonce":"..."}`

## Note Tecniche

- Il nonce ha azione `fp_resv_submit` (non il nonce REST standard `wp_rest`)
- Il JavaScript invia il nonce sia nel body che nell'header
- Il retry automatico aspetta 500ms + 200ms per problemi di timing cookie
- Il sistema può rigenerare il nonce automaticamente via endpoint `/nonce`
