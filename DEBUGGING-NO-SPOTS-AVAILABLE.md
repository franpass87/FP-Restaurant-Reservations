# Debug: "Nessuna disponibilità per questo servizio"

## Problema
Il sistema mostra il messaggio "Nessuna disponibilità per questo servizio. Scegli un altro giorno." anche se gli orari sono configurati correttamente nel backend.

## Cause Possibili

### 1. **Orari Non Configurati per il Pasto Selezionato**
Il pasto selezionato (`pranzo`, `cena`, `aperitivo`, `brunch`) potrebbe non avere orari configurati specifici.

**Come Verificare:**
1. Vai nel pannello admin WordPress
2. Naviga in **Prenotazioni > Impostazioni > Pasti**
3. Controlla che il pasto selezionato abbia:
   - Una configurazione degli orari (`hours_definition`)
   - Oppure che utilizzi gli orari di default del sistema

**Soluzione:**
- Se il pasto non ha orari specifici, aggiungi la configurazione degli orari nel formato:
  ```
  mon=19:00-23:00
  tue=19:00-23:00
  wed=19:00-23:00
  thu=19:00-23:00
  fri=19:00-23:30
  sat=12:30-15:00|19:00-23:30
  sun=12:30-15:00
  ```
- Oppure, assicurati che gli orari di default siano configurati in **Prenotazioni > Impostazioni > Generale**

### 2. **Giorno della Settimana Non Configurato**
Gli orari potrebbero non essere configurati per il giorno della settimana selezionato dall'utente.

**Come Verificare:**
1. Controlla che il giorno della settimana (es. domenica) sia incluso nella configurazione degli orari
2. Verifica che il formato sia corretto: `dom=12:30-15:00` (per domenica)

**Formato Giorni:**
- `mon` = Lunedì
- `tue` = Martedì  
- `wed` = Mercoledì
- `thu` = Giovedì
- `fri` = Venerdì
- `sat` = Sabato
- `sun` = Domenica

### 3. **Chiusure Programmate Attive**
Potrebbe esserci una chiusura programmata che blocca tutti gli slot per quel giorno.

**Come Verificare:**
1. Vai in **Prenotazioni > Chiusure**
2. Controlla se ci sono chiusure attive per la data selezionata
3. Verifica le chiusure ricorrenti (settimanali, mensili)

**Soluzione:**
- Disattiva o elimina la chiusura se non è più necessaria
- Modifica l'orario della chiusura se copre troppi slot

### 4. **Limite di Capacità del Pasto**
Il pasto potrebbe avere un limite di capacità (`capacity`) impostato troppo basso.

**Come Verificare:**
1. Controlla la configurazione del pasto
2. Verifica se c'è un campo `capacity` impostato
3. Confronta con il numero di coperti richiesti dall'utente

**Soluzione:**
- Aumenta il limite di capacità del pasto
- Oppure rimuovi il limite se non necessario

### 5. **Mancanza di Tavoli o Capacità Sala**
La sala potrebbe non avere tavoli configurati o capacità sufficiente.

**Come Verificare:**
1. Vai in **Prenotazioni > Tavoli**
2. Verifica che ci siano tavoli attivi
3. Controlla la capacità totale della sala in **Prenotazioni > Sale**

**Soluzione:**
- Aggiungi tavoli se mancano
- Aumenta la capacità della sala se è insufficiente
- Verifica che i tavoli siano attivi (non disattivati)

## Debugging con Logs

Ho aggiunto un sistema di logging diagnostico completo che è **sempre attivo** (non richiede WP_DEBUG).

### 1. Controlla la Console del Browser (Modo più facile)

Quando il sistema dice "Nessuna disponibilità", la risposta API ora include informazioni diagnostiche dettagliate:

1. Apri gli Strumenti per Sviluppatori del browser (F12)
2. Vai alla scheda "Network" / "Rete"
3. Riproduci il problema selezionando data, pasto e numero persone
4. Cerca la richiesta API (di solito `/wp-json/fp-resv/v1/...`)
5. Guarda la risposta, nella sezione `meta.debug` troverai:
   ```json
   {
     "meta": {
       "has_availability": false,
       "reason": "Nessun turno configurato...",
       "debug": {
         "day_key": "fri",
         "meal_key": "cena",
         "schedule_map_keys": ["mon", "tue", "wed"],
         "schedule_empty": false,
         "message": "Lo schedule per il giorno fri è vuoto. Giorni configurati: mon, tue, wed"
       }
     }
   }
   ```

Questo ti dice **esattamente** quale giorno stai cercando e quali giorni sono effettivamente configurati!

### 2. Controlla i Log di Sistema

I log vengono automaticamente salvati usando il sistema di logging di WordPress:

**Se hai WP_DEBUG abilitato:**
- I log verranno salvati in `wp-content/debug.log`
- Cerca le righe che iniziano con `[fp-resv][availability]`

**Per abilitare WP_DEBUG** (opzionale, modifica `wp-config.php`):
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 3. Esempi di Log che Potresti Vedere

I log mostrano il processo completo di risoluzione della disponibilità:

```
[fp-resv][availability] resolveMealSettings chiamato {"meal_key":"cena","default_schedule_raw":"mon=19:00-23:00...","default_schedule_map":{...}}
[fp-resv][availability] Meal plan caricato {"meal_key":"cena","plan_keys":["pranzo","cena"],"meal_exists":true}
[fp-resv][availability] Meal trovato {"meal_key":"cena","has_hours_definition":true,"hours_definition":"fri=19:00-23:00\nsat=19:00-23:30"}
[fp-resv][availability] Schedule del meal parsato {"meal_key":"cena","meal_schedule":{"fri":[...],"sat":[...]},"is_empty":false}
[fp-resv][availability] resolveScheduleForDay {"date":"2025-10-10","day_key":"fri","schedule_for_day":[{"start":1140,"end":1380}],"schedule_is_empty":false,"available_days":["fri","sat"]}
```

### 4. Analizza i Log e la Risposta Debug

**Nella risposta API (`meta.debug`):**
- `"schedule_empty": true` → La configurazione degli orari è completamente vuota
- `"schedule_map_keys": []` → Nessun giorno configurato
- `"day_key": "sun"` ma `"schedule_map_keys": ["mon","tue"]` → Il giorno cercato (domenica) non è tra quelli configurati

**Nei log di sistema:**
- `"meal_exists": false` → La chiave del pasto non esiste nella configurazione
- `"has_hours_definition": false` → Il pasto non ha orari specifici configurati
- `"meal_schedule": {}` o `"is_empty": true` → Non ci sono orari configurati per quel pasto
- `"schedule_for_day": []` → Non ci sono orari per il giorno specifico richiesto

## Test Rapido

Per verificare rapidamente quale sia il problema:

1. **Test con altro pasto**: Prova a selezionare un altro pasto (es. cena invece di pranzo)
2. **Test con altro giorno**: Prova a selezionare un altro giorno della settimana
3. **Test con meno coperti**: Prova a ridurre il numero di persone

Se uno di questi test funziona, hai identificato il problema!

## Richiesta di Supporto

Se il problema persiste dopo aver controllato i log e la configurazione, invia:

1. **Screenshot della risposta API** dalla console del browser (specialmente `meta.debug`)
2. **Screenshot della configurazione del pasto** in **Prenotazioni > Impostazioni > Pasti**
3. **Screenshot degli orari di default** in **Prenotazioni > Impostazioni > Generale**
4. **Parametri della ricerca**: data, ora, numero coperti e pasto selezionato
5. **Log di sistema** se disponibili (filtrati per `[fp-resv][availability]`)

La sezione `meta.debug` nella risposta API è la più importante e di solito rivela immediatamente il problema!

## Fix Temporaneo

Mentre investighi il problema, puoi:

1. **Usare solo orari di default**: Rimuovi le configurazioni specifiche dei pasti e usa solo gli orari globali
2. **Disabilitare i pasti multipli**: Se non necessario, configura un solo pasto senza opzioni multiple
3. **Aumentare la capacità**: Temporaneamente aumenta la capacità della sala per vedere se il problema è legato ai limiti

## Prossimi Passi

Una volta identificato il problema specifico nei log, posso aiutarti a:
1. Correggere la configurazione
2. Aggiungere migliori messaggi di errore per gli utenti
3. Implementare validazioni più robuste nel backend

## Esempio Pratico di Debugging

### Scenario: "Nessuna disponibilità per il venerdì sera"

1. **Apri la console del browser** (F12 → Network)
2. **Seleziona**: Venerdì 10 Ottobre, Cena, 2 persone
3. **Controlla la risposta API** e vedi:
   ```json
   {
     "meta": {
       "debug": {
         "day_key": "fri",
         "meal_key": "cena",
         "schedule_map_keys": ["mon", "tue", "wed", "thu"],
         "message": "Lo schedule per il giorno fri è vuoto. Giorni configurati: mon, tue, wed, thu"
       }
     }
   }
   ```

4. **Soluzione**: La cena è configurata solo da lunedì a giovedì! Devi:
   - Andare in **Prenotazioni > Impostazioni > Pasti**
   - Modificare il pasto "Cena"
   - Aggiungere gli orari del venerdì: `fri=19:00-23:00`

---

**File modificati per il debugging:**
- `/workspace/src/Domain/Reservations/Availability.php` - Aggiunti log dettagliati e informazioni debug nella risposta API
- `/workspace/DEBUGGING-NO-SPOTS-AVAILABLE.md` - Aggiornata documentazione con nuove funzionalità di debugging
