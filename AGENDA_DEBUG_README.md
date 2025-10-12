# Debug Agenda - Guida alla risoluzione problemi

## Problema identificato
L'API `/wp-json/fp-resv/v1/agenda` ritorna status 200 ma con corpo null/vuoto, causando un'agenda vuota anche quando ci sono prenotazioni nel database.

## Modifiche apportate

### 1. Logging dettagliato in `AdminREST.php`
Aggiunto logging estensivo per tracciare:
- Registrazione dell'endpoint REST
- Chiamate al metodo `handleAgenda()`
- Parametri della richiesta
- Query al database
- Generazione della risposta
- Eventuali errori

### 2. Logging in `Plugin.php`
Aggiunto logging per verificare che `AdminREST` venga inizializzato correttamente durante il bootstrap del plugin.

### 3. Fallback per risposta vuota
Aggiunto controllo che garantisce che l'API ritorni sempre una risposta strutturata, anche quando `responseData` è vuoto.

## Come diagnosticare il problema

### Passo 1: Verificare i log del server
Cerca nel file di log di WordPress (solitamente `wp-content/debug.log`) i seguenti messaggi:

```
[FP Resv Plugin] Inizializzazione AdminREST...
[FP Resv Plugin] Chiamata register() su AdminREST...
[FP Resv Plugin] AdminREST registrato con successo
[FP Resv AdminREST] register() chiamato - aggiungendo hook rest_api_init
[FP Resv AdminREST] === REGISTERING ROUTES ===
[FP Resv AdminREST] Registering /fp-resv/v1/agenda endpoint
[FP Resv AdminREST] /fp-resv/v1/agenda registered successfully
```

Se questi log non appaiono, significa che il plugin non si sta inizializzando correttamente.

### Passo 2: Verificare la chiamata API
Quando accedi all'agenda, cerca nei log:

```
[FP Resv Agenda] === INIZIO handleAgenda ===
[FP Resv Agenda] Request method: GET
[FP Resv Agenda] Request route: /fp-resv/v1/agenda
[FP Resv Agenda] Parametri richiesta: Array(...)
```

Se questi log non appaiono, significa che l'endpoint non viene chiamato (problema di routing).

### Passo 3: Verificare il database
Controlla se ci sono prenotazioni nel database:

```sql
SELECT COUNT(*) FROM wp_fp_reservations;
```

Se non ci sono prenotazioni, l'agenda sarà giustamente vuota.

### Passo 4: Verificare la risposta
Cerca nei log la risposta generata:

```
[FP Resv Agenda] Response data creato. Keys: meta, stats, data, reservations
[FP Resv Agenda] Response data JSON length: XXXX
```

Se il JSON length è 0 o molto piccolo, c'è un problema nella generazione della risposta.

## Possibili cause e soluzioni

### Causa 1: Endpoint non registrato
**Sintomo**: Nessun log di registrazione nel file di log
**Soluzione**: 
1. Verifica che `vendor/autoload.php` esista
2. Flush permalink: Impostazioni > Permalink > Salva
3. Riattiva il plugin

### Causa 2: Database vuoto
**Sintomo**: Log mostra "0 prenotazioni mappate"
**Soluzione**: Questo è normale se non ci sono prenotazioni. Crea una prenotazione di test.

### Causa 3: Errore PHP non catturato
**Sintomo**: Log si interrompe improvvisamente
**Soluzione**: 
1. Abilita `WP_DEBUG` in `wp-config.php`
2. Controlla il log per errori PHP

### Causa 4: Problema di serializzazione JSON
**Sintomo**: Log mostra "Response data creato" ma il frontend riceve null
**Soluzione**: Verifica che non ci siano output inattesi prima della risposta JSON

## Script di test

Usa lo script `test-agenda-endpoint.php` nella root del plugin per testare l'endpoint direttamente:

```bash
# Da WordPress admin
php wp-content/plugins/fp-restaurant-reservations/test-agenda-endpoint.php
```

Oppure da browser:
```
https://tuosito.com/wp-content/plugins/fp-restaurant-reservations/test-agenda-debug.php
```

## Verifica frontend

Nel browser, apri la console (F12) e controlla i log dell'agenda:

```javascript
[Agenda] Inizializzazione...
[Agenda] Elementi DOM caricati
[Agenda] Cambio vista: day
[Agenda] Caricamento prenotazioni...
[Agenda] ✓ Risposta ricevuta
```

Se vedi "Risposta API vuota" ma hai prenotazioni nel database, il problema è nell'endpoint REST.

## Checklist finale

- [ ] Il plugin è attivo
- [ ] vendor/autoload.php esiste
- [ ] Permalink sono stati salvati di recente
- [ ] WP_DEBUG è abilitato
- [ ] I log mostrano la registrazione dell'endpoint
- [ ] I log mostrano le chiamate a handleAgenda
- [ ] La console browser non mostra errori 403/404
- [ ] fpResvAgendaSettings è definito in console

## Contatti

Se il problema persiste dopo aver seguito questi passaggi, fornisci:
1. Contenuto del file debug.log
2. Screenshot della console browser
3. Output dello script test-agenda-endpoint.php
