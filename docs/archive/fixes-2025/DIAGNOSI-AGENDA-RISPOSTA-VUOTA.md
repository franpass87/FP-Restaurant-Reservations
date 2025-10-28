# Diagnosi: Agenda restituisce risposta vuota (null)

## Problema identificato

L'API endpoint `/wp-json/fp-resv/v1/agenda` restituisce:
- Status HTTP: **200 OK**
- Contenuto della risposta: **0 bytes (vuoto)**
- Il JavaScript riceve: **null**

Questo causa la visualizzazione "nessuna prenotazione" anche quando ci sono prenotazioni nel database.

## Modifiche implementate

### 1. **CORREZIONE CRITICA: Output Buffering** ⚠️

Ho identificato e corretto un bug critico nella gestione dell'output buffering:

**Problema:** Il codice usava `ob_get_clean()` che chiude il buffer e potrebbe causare una risposta vuota perché WordPress REST API si aspetta un buffer attivo.

**Soluzione applicata:**
- Uso `ob_get_contents()` + `ob_clean()` invece di `ob_get_clean()`
- Il buffer rimane aperto durante la creazione della risposta
- Chiudo il buffer solo DOPO aver creato `WP_REST_Response`
- Verifico sempre il livello del buffer prima di operare su di esso

Questa correzione **dovrebbe risolvere il problema della risposta vuota**.

### 2. Logging estensivo in AdminREST.php

Ho aggiunto logging dettagliato in tre punti critici:

#### a) Registrazione endpoint
```php
// File: src/Domain/Reservations/AdminREST.php - linea 67-91
// Verifica che l'endpoint venga registrato correttamente
```

#### b) Verifica permessi
```php
// File: src/Domain/Reservations/AdminREST.php - linea 823-843
// Log ogni volta che checkPermissions() viene chiamato
```

#### c) Esecuzione handleAgenda
```php
// File: src/Domain/Reservations/AdminREST.php - linea 401-596
// Log dettagliato di ogni fase del caricamento
```

### 2. File di log dedicato

Tutti i log vengono scritti in:
```
wp-content/agenda-endpoint-calls.log
```

Questo file conterrà:
- Timestamp di ogni chiamata
- Parametri della richiesta
- Permessi utente
- Numero di prenotazioni trovate
- Eventuali errori

### 3. Endpoint di test diagnostico

Ho creato un endpoint di test semplificato:
```
/wp-json/fp-resv/v1/agenda-test
```

Questo endpoint:
- Non richiede autenticazione
- Non accede al database
- Restituisce sempre una risposta valida
- Serve a verificare che il sistema REST API funzioni

## Come testare

### Test 1: Verifica endpoint di base

1. Apri il browser e vai a:
   ```
   https://www.villadianella.it/wp-json/fp-resv/v1/agenda-test
   ```

2. Dovresti vedere una risposta JSON tipo:
   ```json
   {
     "success": true,
     "message": "Endpoint test funziona!",
     "timestamp": "2025-10-12 10:30:00",
     "user_id": 1
   }
   ```

**Se questo non funziona:** Il problema è nella configurazione generale di WordPress REST API.

**Se funziona:** Il problema è specifico dell'endpoint `/agenda`.

### Test 2: Verifica endpoint agenda

1. Accedi al backend di WordPress come amministratore

2. Vai alla pagina agenda:
   ```
   https://www.villadianella.it/wp-admin/admin.php?page=fp-restaurant-reservations-agenda
   ```

3. Apri la console del browser (F12)

4. Cerca i log che iniziano con `[Agenda]` e `[API]`

5. Verifica se l'API viene chiamata e cosa risponde

### Test 3: Verifica il file di log

1. Scarica/leggi il file:
   ```
   wp-content/agenda-endpoint-calls.log
   ```

2. Cerca le seguenti informazioni:
   - `registerRoutes chiamato` - conferma che l'endpoint è registrato
   - `checkPermissions chiamato` - conferma che la richiesta arriva
   - `handleAgenda CHIAMATO` - conferma che la funzione viene eseguita
   - `Creazione risposta con N prenotazioni` - conferma che i dati vengono recuperati

## Possibili cause e soluzioni

### Causa 1: Problema di permessi

**Sintomo:** Nel log trovi `checkPermissions` con `result=false`

**Soluzione:** L'utente non ha i permessi necessari. Verifica che sia un amministratore o abbia la capability `manage_fp_reservations`.

### Causa 2: Database vuoto

**Sintomo:** Nel log trovi `Creazione risposta con 0 prenotazioni`

**Soluzione:** Non è un bug del codice, non ci sono effettivamente prenotazioni per quella data. Verifica la data richiesta e i filtri applicati.

### Causa 3: Endpoint non registrato

**Sintomo:** Nel log NON trovi `registerRoutes chiamato`

**Soluzione:** Il plugin non viene inizializzato correttamente. Verifica che AdminREST sia registrato nel dependency injection container.

### Causa 4: Funzione non viene chiamata

**Sintomo:** Nel log trovi `registerRoutes` e `checkPermissions` ma NON trovi `handleAgenda CHIAMATO`

**Soluzione:** Potrebbe esserci un problema con il routing di WordPress. Prova a svuotare la cache e i permalink.

### Causa 5: Output buffering problematico

**Sintomo:** Nel log trovi `Output inatteso` con contenuto

**Soluzione:** C'è del codice (plugin o tema) che sta emettendo output prima della risposta JSON. Questo corrompe la risposta.

### Causa 6: Fatal error PHP

**Sintomo:** Nel log trovi `handleAgenda CHIAMATO` ma non `Risposta creata con successo`

**Soluzione:** C'è un errore PHP durante l'esecuzione. Controlla il file `wp-content/debug.log` per errori PHP.

## Query SQL diretta per verifica

Per escludere problemi di codice e verificare se ci sono effettivamente prenotazioni, esegui questa query SQL:

```sql
SELECT COUNT(*) as total 
FROM wp_fp_reservations 
WHERE date = '2025-10-12';
```

Se il risultato è > 0, allora ci sono prenotazioni e il bug è nel codice.
Se il risultato è 0, allora non ci sono prenotazioni per quella data.

## Prossimi passi

1. **Esegui i test sopra descritti**
2. **Leggi il file di log** `wp-content/agenda-endpoint-calls.log`
3. **Riporta i risultati** con:
   - Cosa vedi nel browser per `/agenda-test`
   - Cosa vedi nella console per la pagina agenda
   - Contenuto del file di log (almeno le ultime 50 righe)

Con queste informazioni potrò identificare la causa esatta e implementare la soluzione definitiva.

## File modificati

- `src/Domain/Reservations/AdminREST.php` - **CORREZIONE OUTPUT BUFFERING**, logging estensivo e endpoint di test
- `DIAGNOSI-AGENDA-RISPOSTA-VUOTA.md` - Questo documento

## ⚡ Correzione applicata

La modifica più importante è la correzione della gestione dell'output buffering che **molto probabilmente era la causa del problema**:

```php
// PRIMA (ERRATO):
$unexpectedOutput = ob_get_clean(); // Chiude il buffer!

// DOPO (CORRETTO):
$unexpectedOutput = ob_get_contents(); // Legge senza chiudere
ob_clean(); // Pulisce ma mantiene aperto
// ... crea risposta ...
ob_end_clean(); // Chiude solo alla fine
```

Questo assicura che WordPress REST API possa gestire correttamente l'output della risposta JSON.

## Note tecniche

### Perché la risposta è vuota?

Una risposta HTTP con status 200 ma 0 bytes può verificarsi quando:

1. **La callback non viene mai eseguita** - WordPress non trova l'endpoint o i permessi falliscono silenziosamente
2. **La callback ritorna null** - Ma nel nostro caso non è possibile perché restituiamo sempre un array
3. **Output buffering** - `ob_get_clean()` potrebbe "mangiare" la risposta se non gestito correttamente
4. **Fatal error catturato** - PHP fatal error che viene soppresso e non permette l'output
5. **Plugin interferenti** - Altri plugin che modificano le risposte REST API

Il logging ci aiuterà a identificare quale di questi casi si verifica.
