# 🔧 Debug: Risposta Vuota dal Server

## Problema
Quando si crea una nuova prenotazione dal Manager, il backend restituisce una **risposta vuota** (empty response body) anche se lo status HTTP è 200 OK.

## Causa Possibile
Ci sono diverse possibili cause:
1. **Errore PHP fatale** che blocca l'esecuzione
2. **Output buffering** che viene svuotato prematuramente
3. **Hook WordPress** che sta intercettando e svuotando la risposta
4. **Problema con il nonce** o permessi
5. **Problema con la serializzazione JSON** della risposta

## 🚀 Fix Applicato

Ho aggiunto **logging diagnostico dettagliato** nel file `src/Domain/Reservations/AdminREST.php` per tracciare ogni passo del processo di creazione della prenotazione.

### Modifiche Effettuate

1. **Logging all'inizio della richiesta**
   - Request method, route e parametri
   
2. **Logging per ogni step**
   - STEP 1: Estrazione payload
   - STEP 2: Payload estratto con successo
   - STEP 3: Chiamata service->create()
   - STEP 4: service->create() completato
   - STEP 6: Costruzione risposta
   - STEP 7: Chiamata rest_ensure_response()
   - STEP 8: Response creata con successo

3. **Logging dettagliato in extractReservationPayload()**
   - Verifica presenza parametri body e JSON
   - Verifica validazione email
   - Verifica validazione nome/cognome

## 📋 Come Testare

### Metodo 1: Usa lo Script di Test

1. **Carica il file di test nel browser**:
   ```
   http://tuo-sito.local/wp-content/plugins/fp-restaurant-reservations/test-create-reservation-endpoint.php
   ```

2. **Compila il form** con i dati di test (già precompilato)

3. **Clicca su "Crea Prenotazione"**

4. **Osserva i risultati**:
   - Se la risposta è vuota, vedrai un messaggio di errore chiaro
   - La console del browser mostrerà i dettagli della richiesta

### Metodo 2: Test dal Manager

1. **Apri il Manager delle Prenotazioni**:
   - WordPress Admin → FP Reservations → Manager

2. **Clicca su "Nuova Prenotazione"**

3. **Completa i 3 step** del wizard

4. **Osserva il risultato**:
   - Se vedi "Errore: Risposta vuota dal server", il problema persiste

### Metodo 3: Verifica Log WordPress

1. **Abilita WP_DEBUG** (se non già attivo):
   ```php
   // Nel file wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Riprova a creare una prenotazione**

3. **Leggi i log**:
   ```
   wp-content/debug.log
   ```

4. **Cerca i log con prefisso**:
   ```
   [FP Resv Admin]
   ```

## 🔍 Cosa Cercare nei Log

### Log di Successo (Tutto OK)
Se tutto funziona correttamente, dovresti vedere questa sequenza:

```
[FP Resv Admin] === CREAZIONE PRENOTAZIONE DAL MANAGER START ===
[FP Resv Admin] Request method: POST
[FP Resv Admin] Request route: /fp-resv/v1/agenda/reservations
[FP Resv Admin] STEP 1: Estrazione payload...
[FP Resv Admin] extractReservationPayload() START
[FP Resv Admin] Payload base costruito, email: mario.rossi@example.com
[FP Resv Admin] Validazione email...
[FP Resv Admin] Validazione nome/cognome...
[FP Resv Admin] extractReservationPayload() OK - payload valido
[FP Resv Admin] STEP 2: Payload estratto con successo
[FP Resv Admin] STEP 3: Chiamata service->create()...
[FP Resv Admin] STEP 4: service->create() completato con successo
[FP Resv Admin] Prenotazione creata con ID: 123
[FP Resv Admin] STEP 6: Costruzione risposta...
[FP Resv Admin] STEP 7: Chiamata rest_ensure_response()...
[FP Resv Admin] STEP 8: Response creata: WP_REST_Response
[FP Resv Admin] Response status: 200
[FP Resv Admin] Response data size: 456
[FP Resv Admin] === CREAZIONE PRENOTAZIONE COMPLETATA - RITORNO RESPONSE ===
```

### Log di Errore: Endpoint Non Chiamato
Se NON vedi **nessuno di questi log**, significa che:
- ❌ L'endpoint non è registrato correttamente
- ❌ Il routing WordPress non funziona
- ❌ C'è un problema con i permessi

**Soluzione**: Verifica che l'endpoint sia registrato:
```php
// Cerca nei log:
[FP Resv AdminREST] registerRoutes() CHIAMATO!
[FP Resv AdminREST] Endpoint /agenda/reservations registrato: SUCCESS
```

### Log di Errore: Si Ferma a un Certo STEP
Se i log si fermano a un certo STEP, hai trovato il problema!

#### Si ferma a STEP 1 o 2
**Problema**: Validazione del payload fallisce
**Soluzione**: Verifica i dati inviati dal frontend

#### Si ferma a STEP 3 o 4
**Problema**: Errore durante `service->create()`
**Soluzione**: Cerca errori PHP fatali o exception nei log

#### Si ferma a STEP 7 o 8
**Problema**: Errore nella creazione della risposta REST
**Soluzione**: Problema con WordPress REST API, verifica hook e filtri

## 🛠️ Possibili Soluzioni

### Soluzione 1: Verifica Permessi Utente
```php
// Verifica nel log che l'utente abbia i permessi
[FP Resv Permissions] Can manage reservations: YES
```

Se vedi `NO`, l'utente non ha i permessi necessari.

### Soluzione 2: Disabilita Output Buffering
Se il problema è legato a `ob_start()`, prova a commentare temporaneamente:

```php
// Commenta queste righe in AdminREST.php
// ob_start();
$result = $this->service->create($payload);
// $hookOutput = ob_get_clean();
```

### Soluzione 3: Verifica Altri Plugin
Alcuni plugin WordPress possono interferire con le REST API:
- **Security plugins** (Wordfence, iThemes Security)
- **Caching plugins** (WP Super Cache, W3 Total Cache)
- **404 redirect plugins**

**Test**: Disabilita temporaneamente tutti gli altri plugin tranne FP Restaurant Reservations.

### Soluzione 4: Verifica .htaccess
A volte il file `.htaccess` può causare problemi con le REST API.

**Test**: Rinomina temporaneamente `.htaccess` in `.htaccess.bak` e ri-salva i permalink.

### Soluzione 5: Aumenta Memory Limit
```php
// Nel wp-config.php
define('WP_MEMORY_LIMIT', '256M');
```

## 📞 Prossimi Passi

1. **Ricarica il plugin** o vai su una pagina del sito per assicurarti che il nuovo codice sia caricato

2. **Prova a creare una prenotazione** dal Manager

3. **Leggi i log** in `wp-content/debug.log`

4. **Condividi i log** se il problema persiste:
   - Copia i log che iniziano con `[FP Resv Admin]`
   - Cerca anche eventuali `PHP Fatal error` o `PHP Warning`

## 📊 Informazioni Utili

- **File modificato**: `src/Domain/Reservations/AdminREST.php`
- **Endpoint**: `POST /wp-json/fp-resv/v1/agenda/reservations`
- **Metodo**: `handleCreateReservation()`
- **Log prefix**: `[FP Resv Admin]`

## ✅ Checklist Debug

- [ ] WP_DEBUG attivo
- [ ] WP_DEBUG_LOG attivo
- [ ] Tentativo di creazione prenotazione effettuato
- [ ] Log controllati in `wp-content/debug.log`
- [ ] Trovato log `[FP Resv Admin] === CREAZIONE PRENOTAZIONE DAL MANAGER START ===`
- [ ] Identificato lo STEP dove si ferma
- [ ] Verificato se ci sono errori PHP
- [ ] Verificato permessi utente
- [ ] Provato con altri plugin disabilitati

---

**Nota**: Questo fix è diagnostico. Una volta identificata la causa esatta del problema, applicheremo una soluzione definitiva.

