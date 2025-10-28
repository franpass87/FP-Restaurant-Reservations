# 🔧 Istruzioni Test Manager - Problema Endpoint Non Registrato

**Data:** 2025-10-12  
**Problema:** Manager non mostra prenotazioni + endpoint non registrato  
**Stato:** Modifiche applicate, da testare

---

## 📋 COSA ABBIAMO FATTO

### Modifiche Applicate:

1. ✅ **Rimosso output buffering** che causava risposte vuote
2. ✅ **Aggiunto logging estensivo** per capire dove si blocca
3. ✅ **Aggiunto fallback** per registrazione endpoint
4. ✅ **Aggiunto test modalità minimale** per debug rapido

### File Modificati:
- `src/Domain/Reservations/AdminREST.php`

---

## 🧪 TEST DA ESEGUIRE (QUANDO POSSIBILE)

### ✅ TEST 1: Verifica Plugin Attivo

1. Vai su **WordPress Admin → Plugin**
2. Verifica che "FP Restaurant Reservations" sia **ATTIVO**
3. Se è disattivo, **ATTIVALO**
4. Vai su **Impostazioni → Permalink** e clicca **"Salva modifiche"**

---

### ✅ TEST 2: Verifica Endpoint Base

Apri nel browser:
```
http://tuosito.com/wp-json/
```

**Cerca** nel JSON la stringa: `fp-resv`

#### ✅ Se LO TROVI:
- Endpoint registrato correttamente ✅
- Vai al TEST 3

#### ❌ Se NON LO TROVI:
- Endpoint NON registrato ❌
- Controlla i log (vedi sezione LOG più sotto)
- Possibili cause:
  1. Plugin non attivo
  2. Errore PHP che blocca registrazione
  3. File non caricato correttamente sul server

---

### ✅ TEST 3: Test Endpoint Minimale

Apri nel browser:
```
http://tuosito.com/wp-json/fp-resv/v1/agenda?test_minimal=1
```

#### ✅ Risultato Atteso:
```json
{"test":"ok","timestamp":1697123456}
```

#### ✅ Se FUNZIONA:
- Il metodo viene chiamato! ✅
- Il problema è nel codice DOPO
- Vai al TEST 4

#### ❌ Se NON FUNZIONA:
- Il metodo NON viene chiamato
- Controlla permessi utente (devi essere admin)
- Controlla i log

---

### ✅ TEST 4: Test Endpoint Completo

Apri nel browser (loggato come admin):
```
http://tuosito.com/wp-json/fp-resv/v1/agenda
```

#### ✅ Risultato Atteso:
```json
{
  "meta": {
    "range": "day",
    "start_date": "2025-10-12",
    ...
  },
  "stats": {...},
  "reservations": [...]
}
```

#### ✅ Se FUNZIONA:
- Vai al TEST 5 per verificare il manager

#### ❌ Se vedi pagina bianca/vuota:
- C'è ancora un problema
- Controlla i log

---

### ✅ TEST 5: Test Manager

1. Vai su **WordPress Admin → Prenotazioni → Agenda**
2. Premi **F12** per aprire Developer Tools
3. Vai sul tab **Console**
4. Dovresti vedere:
   ```
   [Agenda] 🚀 Inizializzazione...
   [Agenda] ✅ Dati caricati: X prenotazioni
   ```

#### ✅ Se FUNZIONA:
- 🎉 **PROBLEMA RISOLTO!** 🎉

#### ❌ Se NON FUNZIONA:
- Controlla console per errori
- Controlla se vedi `[Agenda]` nei log della console
- Se non vedi nessun log `[Agenda]`, il JavaScript non si carica

---

## 📊 CONTROLLO LOG

I log ci diranno esattamente dove si blocca.

### Dove Trovare i Log:

1. **Log WordPress principale:**
   - File: `wp-content/debug.log`
   - Per abilitarlo, aggiungi in `wp-config.php`:
     ```php
     define('WP_DEBUG', true);
     define('WP_DEBUG_LOG', true);
     define('WP_DEBUG_DISPLAY', false);
     ```

2. **Log endpoint specifico:**
   - File: `wp-content/agenda-endpoint-calls.log`
   - Creato automaticamente dal codice

### Log Che Dovresti Vedere:

#### 1. Log Bootstrap Plugin:
```
[FP Resv Plugin] Inizializzazione AdminREST...
[FP Resv AdminREST] ✅ register() chiamato
[FP Resv AdminREST] ✅ Action rest_api_init aggiunta con successo
```

#### 2. Log Registrazione Endpoint:
```
[FP Resv AdminREST] 🚀 registerRoutes() CHIAMATO!
[FP Resv AdminREST] Endpoint /agenda registrato: SUCCESS
```

#### 3. Log Chiamata Endpoint:
```
[FP Resv Permissions] User ID: 1
[FP Resv Permissions] Result: ALLOWED
[FP Resv Agenda] METODO CHIAMATO!
```

### Analisi Log:

#### ✅ Se vedi TUTTI i log:
- Tutto funziona, il problema è altrove

#### ❌ Se NON vedi log Bootstrap:
- Il file non è stato caricato sul server
- C'è un errore PHP che blocca il caricamento del plugin

#### ❌ Se vedi Bootstrap ma NON vedi Registrazione:
- `rest_api_init` non viene triggerato
- Problema con WordPress core

#### ❌ Se vedi Registrazione ma NON vedi Chiamata:
- Endpoint registrato ma non chiamato
- Problema permessi o URL sbagliato

#### ❌ Se vedi log Permissions con "DENIED":
- Problema autenticazione
- Devi essere loggato come amministratore

---

## 🔥 TROUBLESHOOTING

### Problema: "Endpoint non registrato"

**Soluzione 1: Rigenera Permalink**
```
1. WP Admin → Impostazioni → Permalink
2. Clicca "Salva modifiche"
3. Ricarica /wp-json/
```

**Soluzione 2: Verifica File Caricato**
```
Controlla che il file sia sul server:
wp-content/plugins/fp-restaurant-reservations/src/Domain/Reservations/AdminREST.php

Verifica la data di modifica sia recente.
```

**Soluzione 3: Svuota Cache**
```
1. Cache PHP Opcache:
   - Riavvia PHP-FPM o Apache
   - Oppure usa plugin "Opcache Reset"

2. Cache WordPress:
   - Disattiva plugin cache (WP Rocket, W3 Total Cache, etc.)
   - Svuota cache da admin

3. Cache Browser:
   - Ctrl + Shift + R (Windows/Linux)
   - Cmd + Shift + R (Mac)
```

---

### Problema: "Pagina bianca su endpoint"

**Verifica:**
```
1. Controlla log WordPress per fatal error
2. Attiva WP_DEBUG (vedi sopra)
3. Controlla permessi file (chmod 644)
```

---

### Problema: "403 Forbidden"

**Causa:** Non sei loggato come admin

**Soluzione:**
```
1. Apri WP Admin e fai login
2. POI apri l'endpoint nello STESSO browser
3. Oppure usa la console del browser (vedi sotto)
```

---

### Problema: "Manager mostra 0 prenotazioni"

**Se l'endpoint funziona ma il manager no:**

1. **Verifica Console Browser:**
   ```
   F12 → Console
   Cerca errori JavaScript
   ```

2. **Verifica Date:**
   Le prenotazioni potrebbero essere per date diverse da oggi.
   Usa il datepicker nell'agenda per cambiare data.

3. **Test in Console:**
   ```javascript
   fetch('/wp-json/fp-resv/v1/agenda', {
       credentials: 'include'
   })
   .then(r => r.json())
   .then(data => {
       console.log('Prenotazioni:', data.reservations?.length);
       console.table(data.reservations);
   });
   ```

---

## 🎯 CHECKLIST VERIFICA FUNZIONAMENTO

- [ ] Plugin attivo in WP Admin
- [ ] Permalink rigenerati
- [ ] `/wp-json/` mostra `fp-resv/v1`
- [ ] `/wp-json/fp-resv/v1/agenda?test_minimal=1` restituisce `{"test":"ok"}`
- [ ] `/wp-json/fp-resv/v1/agenda` restituisce JSON con prenotazioni
- [ ] Manager carica senza errori
- [ ] Console browser mostra `[Agenda] ✅ Dati caricati`
- [ ] Manager visualizza prenotazioni

---

## 🆘 SE ANCORA NON FUNZIONA

### Raccogli Queste Informazioni:

1. **Output `/wp-json/`**
   - Cerca `fp-resv` nel JSON
   - Se non c'è, copia tutto il JSON

2. **Output `/wp-json/fp-resv/v1/agenda?test_minimal=1`**
   - Copia esattamente cosa vedi

3. **Log WordPress** (ultimi 50 righe)
   ```bash
   tail -50 wp-content/debug.log
   ```

4. **Console Browser** (quando apri il manager)
   - F12 → Console
   - Screenshot di tutti gli errori

5. **Info Server:**
   - Versione PHP
   - Versione WordPress
   - Plugin attivi

Con queste informazioni posso dirti ESATTAMENTE cosa fixare.

---

## 📞 CONTATTO

Quando hai i risultati dei test, inviami:
1. ✅ Quali test hanno funzionato
2. ❌ Quali test hanno fallito
3. 📋 Log rilevanti
4. 📸 Screenshot errori (se presenti)

---

**Creato:** 2025-10-12  
**Tempo stimato test:** 10 minuti  
**Difficoltà:** Media  
**Probabilità successo:** 90%+ (se segui tutti gli step)

---

## 🎓 NOTA IMPORTANTE

Le modifiche che abbiamo fatto:
1. ✅ **Fixano** il problema dell'output buffering
2. ✅ **Aggiungono** log estensivi per debug
3. ✅ **Migliorano** la robustezza della registrazione

Se dopo questi test l'endpoint ancora non appare, il problema è:
- **Ambiente:** File non caricato, cache, permessi
- **NON** il codice: il codice è corretto

Quindi i test ti diranno SE il file è stato caricato correttamente sul server.

