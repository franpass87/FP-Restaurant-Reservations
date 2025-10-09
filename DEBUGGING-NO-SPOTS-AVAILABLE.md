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

Ho aggiunto logging di debug al sistema. Per abilitarlo:

### 1. Abilita WP_DEBUG
Modifica il file `wp-config.php` e aggiungi/modifica:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 2. Controlla i Log
I log verranno salvati in `wp-content/debug.log`. Cerca le righe che iniziano con `[FP-RESV]`:

```
[FP-RESV] resolveMealSettings - mealKey: pranzo, default schedule raw: ...
[FP-RESV] resolveMealSettings - meal plan: {...}
[FP-RESV] resolveMealSettings - selected meal: {...}
[FP-RESV] resolveMealSettings - WARNING: meal has no hours_definition, using default schedule
[FP-RESV] resolveScheduleForDay - date: 2025-10-10, dayKey: fri, schedule: ...
```

### 3. Analizza i Log

**Se vedi:**
- `"meal has no hours_definition"` → Il pasto non ha orari configurati, sta usando quelli di default
- `"meal key not found in meal plan"` → La chiave del pasto non esiste nella configurazione
- `"schedule: empty"` o `"schedule: []"` → Non ci sono orari configurati per quel giorno
- `"full scheduleMap: {}"` → La configurazione degli orari è completamente vuota

## Test Rapido

Per verificare rapidamente quale sia il problema:

1. **Test con altro pasto**: Prova a selezionare un altro pasto (es. cena invece di pranzo)
2. **Test con altro giorno**: Prova a selezionare un altro giorno della settimana
3. **Test con meno coperti**: Prova a ridurre il numero di persone

Se uno di questi test funziona, hai identificato il problema!

## Richiesta di Supporto

Se il problema persiste, invia i seguenti dettagli:

1. Log completi dal file `debug.log` (filtrati per `[FP-RESV]`)
2. Screenshot della configurazione del pasto in **Prenotazioni > Impostazioni > Pasti**
3. Screenshot degli orari di default in **Prenotazioni > Impostazioni > Generale**
4. Data, ora e numero coperti che l'utente sta cercando di prenotare
5. Nome del pasto selezionato

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

---

**File modificati per il debugging:**
- `/workspace/src/Domain/Reservations/Availability.php` - Aggiunti log nelle funzioni `resolveMealSettings()` e `resolveScheduleForDay()`
