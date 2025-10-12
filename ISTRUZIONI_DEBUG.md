# 🔍 ISTRUZIONI RAPIDE PER DEBUG AGENDA

## ⚡ Test Rapido (5 minuti)

### Step 1: Test Database
Vai a questo URL nel browser:
```
https://tuosito.com/wp-content/plugins/fp-restaurant-reservations/test-direct-sql.php
```

**Cosa aspettarsi:**
- ✓ Se vedi "Prenotazioni trovate per oggi!" → Il database ha i dati
- ⚠️ Se vedi "Nessuna prenotazione per oggi" → Controlla le date future
- ✗ Se vedi errori SQL → Problema nel database

### Step 2: Abilita i Log
In `wp-config.php` aggiungi:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 3: Accedi all'Agenda
1. Vai su WordPress Admin > FP Reservations > Agenda
2. Apri console browser (F12)
3. Guarda cosa appare nella console

### Step 4: Controlla i Log
Scarica il file `wp-content/debug.log` e cerca:

#### 🟢 Log Positivi (tutto OK):
```
[FP Resv AdminREST] === REGISTERING ROUTES ===
[FP Resv Agenda] === INIZIO handleAgenda ===
[FP Resv Repository] Numero righe trovate: 5
[FP Resv Agenda] Mapping completato: 5 prenotazioni mappate
[FP Resv Agenda] Numero prenotazioni nella risposta: 5
```

#### 🔴 Log Problematici:
```
[FP Resv Repository] Numero righe trovate: 0
```
→ La query non trova prenotazioni (problema range date?)

```
[FP Resv Agenda] Errore mapping row #X
```
→ Problema nel convertire i dati dal database

```
[FP Resv Agenda] ERRORE: json_encode ha fallito!
```
→ I dati non possono essere convertiti in JSON

```
ERRORE SQL: [qualsiasi errore]
```
→ Problema query database

## 🎯 Scenari Comuni

### Scenario A: "Ho 10 prenotazioni ma l'agenda è vuota"

**Segui questi step:**

1. **Verifica che test-direct-sql.php trovi le prenotazioni**
   - Se SÌ: vai al punto 2
   - Se NO: le prenotazioni sono per date diverse da oggi

2. **Controlla il log per il range di date**
   Cerca: `[FP Resv Repository] Start date: 2025-10-12`
   
   Le date cercate corrispondono alle tue prenotazioni?
   - Se SÌ: vai al punto 3
   - Se NO: c'è un problema nel calcolo del range

3. **Controlla se le righe vengono trovate**
   Cerca: `[FP Resv Repository] Numero righe trovate: X`
   
   - Se X > 0: vai al punto 4
   - Se X = 0: problema nella query SQL o range date

4. **Controlla il mapping**
   Cerca: `[FP Resv Agenda] Mapping completato: X prenotazioni mappate`
   
   - Se X > 0: vai al punto 5
   - Se X = 0 ma righe > 0: problema nel mapping

5. **Controlla la risposta finale**
   Cerca: `[FP Resv Agenda] Numero prenotazioni nella risposta: X`
   
   - Se X > 0: problema nel frontend (JavaScript)
   - Se X = 0: problema nella serializzazione

### Scenario B: "L'agenda era vuota, ora mostra errore"

Cerca nel log:
```
[FP Resv Agenda] === ERRORE CRITICO ===
```

Leggi il messaggio di errore e lo stack trace.

### Scenario C: "L'agenda carica all'infinito"

1. Apri console browser (F12)
2. Guarda se ci sono errori JavaScript
3. Controlla che l'API ritorni 200 (non 404 o 403)

## 📋 Checklist Completa

Usa questa checklist per verificare tutto:

- [ ] WP_DEBUG è abilitato
- [ ] test-direct-sql.php trova prenotazioni
- [ ] Log mostra "REGISTERING ROUTES"
- [ ] Log mostra "INIZIO handleAgenda"
- [ ] Log mostra "Numero righe trovate: X" dove X > 0
- [ ] Log mostra "X prenotazioni mappate" dove X > 0
- [ ] Log mostra "Numero prenotazioni nella risposta: X" dove X > 0
- [ ] Nessun "ERRORE" nei log
- [ ] Console browser non mostra errori 403/404
- [ ] L'API ritorna JSON valido (non null)

## 🆘 Se Tutto Fallisce

Se hai seguito tutti gli step e il problema persiste:

1. **Raccogli questi dati:**
   - Output completo di `test-direct-sql.php`
   - Ultimi 200 righe di `wp-content/debug.log`
   - Screenshot console browser con errori
   - Risposta dell'API (da Network tab in console)

2. **Verifica informazioni base:**
   - Versione WordPress
   - Versione PHP
   - Tema attivo
   - Altri plugin attivi

3. **Test di isolamento:**
   - Disattiva tutti gli altri plugin
   - Attiva tema predefinito (Twenty Twenty-Four)
   - Riprova

## 💡 Tips Utili

- **Log troppo grandi?** Svuota `debug.log` prima di testare
- **Non vedi log?** Controlla permessi file (644)
- **Log mancanti?** Verifica che WP_DEBUG_LOG sia `true`
- **Troppi log?** Cerca "[FP Resv" per filtrare solo i nostri

## 📞 Supporto

Se serve aiuto, fornisci:
1. Output `test-direct-sql.php`
2. Sezione rilevante del `debug.log`
3. Screenshot dell'agenda vuota
4. Conferma: "Ho seguito tutti gli step della checklist"
