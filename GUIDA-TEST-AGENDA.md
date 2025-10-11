# 🧪 Guida Test Agenda - Verifica Funzionamento

## ✅ Checklist Rapida

Segui questi passaggi per verificare che l'agenda funzioni correttamente:

### 1. Svuota la Cache
```bash
# Se usi WP-CLI
wp cache flush

# Oppure manualmente
# - Svuota cache browser (Ctrl+Shift+Del)
# - Svuota cache WordPress (se hai plugin di cache)
```

### 2. Accedi alla Pagina Agenda

1. Vai su **WordPress Admin**
2. Nel menu laterale cerca **FP Reservations**
3. Clicca su **Agenda**

### 3. Verifica Caricamento Iniziale ⏱️

**Cosa dovrebbe succedere**:
- ✅ La pagina si carica **immediatamente** (meno di 1 secondo)
- ✅ Vedi la toolbar in alto (data, filtri, viste)
- ✅ Vedi la vista giornaliera di default
- ✅ **NESSUN** loading spinner infinito
- ✅ Se non ci sono prenotazioni, vedi "Nessuna prenotazione"

**Cosa NON dovrebbe succedere**:
- ❌ Loading spinner che gira all'infinito
- ❌ Pagina bianca
- ❌ Errori JavaScript in console

### 4. Apri Console Browser (IMPORTANTE!) 🔍

**Come fare**:
- Premi `F12` (Windows/Linux) o `Cmd+Option+I` (Mac)
- Vai sulla tab **Console**

**Cosa dovresti vedere**:
```
[Agenda] Inizializzazione...
[Agenda] Inizializzazione completata
[Agenda] Caricamento prenotazioni...
[API] GET https://tuosito.it/wp-json/fp-resv/v1/agenda?date=2025-10-11
[API] Status: 200
[Agenda] Caricate X prenotazioni
```

**Se vedi errori rossi**: Copia tutto il messaggio e condividilo

### 5. Testa le Viste 👁️

Clicca sui pulsanti in alto a destra:

#### Vista Giornaliera (Giorno)
- ✅ Mostra slot orari verticali (12:00, 12:15, 12:30...)
- ✅ Prenotazioni raggruppate per orario
- ✅ Card con nome, coperti, telefono

#### Vista Settimanale (Settimana)  
- ✅ Mostra 7 colonne (Lun-Dom)
- ✅ Ogni giorno mostra le sue prenotazioni
- ✅ Cambio settimana con frecce funziona

#### Vista Mensile (Mese)
- ✅ Mostra calendario del mese
- ✅ Contatore prenotazioni per giorno
- ✅ Click su giorno mostra prenotazioni

#### Vista Lista (Lista)
- ✅ Mostra tabella con tutte le prenotazioni
- ✅ Colonne: Data, Ora, Cliente, Coperti, Telefono, Stato, Note
- ✅ Click su riga apre dettagli

### 6. Testa Navigazione 🗓️

#### Frecce Avanti/Indietro
- Clicca sulla freccia sinistra ⬅️
  - ✅ Vai al giorno/settimana/mese precedente
  - ✅ Data aggiornata immediatamente
  - ✅ Prenotazioni caricate per quel periodo

- Clicca sulla freccia destra ➡️
  - ✅ Vai al giorno/settimana/mese successivo

#### Pulsante "Oggi"
- Clicca su **"Oggi"**
  - ✅ Torna alla data odierna
  - ✅ Carica prenotazioni di oggi

#### Date Picker
- Clicca sul campo data in alto
- Seleziona una data qualsiasi
  - ✅ Agenda si aggiorna immediatamente
  - ✅ Mostra prenotazioni per quella data

### 7. Testa Creazione Prenotazione ➕

1. Clicca su **"Nuova prenotazione"** (in alto a destra)
   - ✅ Si apre un modal (finestra popup)

2. Compila il form:
   - Data: scegli una data futura
   - Ora: scegli un orario (es: 19:30)
   - Nome: inserisci un nome
   - Coperti: scegli numero (es: 4)
   - Email e Telefono: opzionali
   - Note: opzionali

3. Clicca su **"Crea prenotazione"**
   - ✅ Modal si chiude
   - ✅ Agenda si ricarica automaticamente
   - ✅ Vedi la nuova prenotazione nell'agenda

**Se fallisce**: Guarda la Console per errori

### 8. Testa Dettagli Prenotazione 🔍

1. Clicca su una prenotazione esistente
   - ✅ Si apre modal con dettagli
   - ✅ Vedi tutte le informazioni
   - ✅ Vedi pulsanti: Chiudi, Modifica, Conferma

2. Clicca su **"Conferma"**
   - ✅ Stato cambia a "Confermata"
   - ✅ Modal si chiude
   - ✅ Agenda si aggiorna

### 9. Testa Performance ⚡

**Con 0 prenotazioni**:
- ✅ Mostra "Nessuna prenotazione" immediatamente
- ✅ Nessun caricamento infinito

**Con 1-10 prenotazioni**:
- ✅ Caricamento istantaneo (<500ms)
- ✅ Cambio vista istantaneo

**Con 50+ prenotazioni**:
- ✅ Caricamento veloce (<1s)
- ✅ Nessun lag quando cambi vista
- ✅ Scroll fluido

**Con 100+ prenotazioni**:
- ✅ Dovrebbe comunque funzionare
- ✅ Se lento, potrebbe essere il server/database

### 10. Controlla Network Tab 🌐

Nella Console browser:
1. Vai su tab **Network**
2. Ricarica la pagina (Ctrl+R)
3. Cerca questi file:

#### File JavaScript
- `agenda-app.js` → Status **200** ✅
  - Se 404: Il file non esiste o path sbagliato

#### File CSS
- `admin-agenda.css` → Status **200** ✅
- `admin-shell.css` → Status **200** ✅

#### Chiamate API
- `/wp-json/fp-resv/v1/agenda?date=...` → Status **200** ✅
  - Se 403: Problema permessi utente
  - Se 404: Endpoint non registrato
  - Se 500: Errore server PHP

## 🐛 Problemi Comuni e Soluzioni

### Problema 1: Loading Infinito
**Sintomo**: Spinner che gira all'infinito

**Soluzione**:
```bash
# Svuota tutte le cache
wp cache flush

# Ricarica la pagina con Ctrl+Shift+R (hard reload)
```

### Problema 2: Errore JavaScript
**Sintomo**: Errori rossi in Console tipo "Uncaught..."

**Soluzioni**:
1. Svuota cache browser
2. Verifica che il file `agenda-app.js` si sia caricato
3. Controlla che non ci siano conflitti con altri plugin

### Problema 3: API 403 Forbidden
**Sintomo**: Chiamata API restituisce 403

**Soluzione**:
```bash
# Verifica permessi utente
wp eval 'var_dump(current_user_can("manage_fp_reservations"));'

# Se restituisce false, assicura permessi:
wp eval 'FP\Resv\Core\Roles::ensureAdminCapabilities();'
```

### Problema 4: API 404 Not Found
**Sintomo**: Endpoint `/wp-json/fp-resv/v1/agenda` non trovato

**Soluzione**:
```bash
# Rigenera permalink
wp rewrite flush

# Se non funziona, vai su:
# WordPress Admin → Impostazioni → Permalink → Salva
```

### Problema 5: Pagina Bianca
**Sintomo**: Niente viene mostrato

**Soluzione**:
1. Attiva debug WordPress:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Controlla `wp-content/debug.log` per errori PHP

### Problema 6: "Nessuna prenotazione" ma ce ne sono
**Sintomo**: Vedi empty state ma dovrebbero esserci prenotazioni

**Verifica**:
1. Sei sulla data giusta? Clicca su "Oggi"
2. Ci sono veramente prenotazioni nel database?
```bash
wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fp_reservations");'
```

## 📊 Test Risultati Attesi

| Test | Risultato Atteso | ✅/❌ |
|------|------------------|-------|
| Caricamento iniziale < 1s | Veloce | |
| Nessun loading infinito | OK | |
| Nessun errore in Console | OK | |
| Vista giornaliera funziona | OK | |
| Vista settimanale funziona | OK | |
| Vista mensile funziona | OK | |
| Vista lista funziona | OK | |
| Navigazione funziona | OK | |
| Creazione prenotazione funziona | OK | |
| Dettagli prenotazione funzionano | OK | |
| API restituisce 200 | OK | |
| Performance con 50+ prenotazioni | OK | |

## 🎯 Test Superato = Tutto Funziona!

Se tutti i test sopra passano, **l'agenda funziona perfettamente** ✅

## 📸 Screenshot Utili

Quando testi, fai screenshot di:
1. Console browser (per vedere log)
2. Network tab (per vedere chiamate API)
3. Agenda funzionante (per conferma visiva)

## 🔍 Debug Avanzato

Se nulla funziona, esegui:
```bash
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-agenda-page.php
```

Questo script controlla:
- ✅ File esistono
- ✅ Endpoint registrati
- ✅ Permessi utente
- ✅ Settings corretti
- ✅ Database funzionante

---

**Tempo stimato test completo**: 5-10 minuti  
**Difficoltà**: ⭐ Facile (segui i passaggi)

**Se tutto funziona**: 🎉 Congratulazioni! L'agenda è stata rifatta con successo!  
**Se qualcosa non funziona**: Condividi gli errori dalla Console per ricevere assistenza.
