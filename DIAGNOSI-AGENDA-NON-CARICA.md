# Diagnosi: Agenda Non Carica

## Problema
L'agenda delle prenotazioni non si carica correttamente.

## Come Diagnosticare

### 1. Apri la Console del Browser
1. Premi **F12** (o Cmd+Option+I su Mac)
2. Vai alla tab **Console**
3. Ricarica la pagina dell'agenda
4. Cerca i messaggi che iniziano con `[Agenda]`

### 2. Verifica l'Inizializzazione
L'agenda dovrebbe mostrare questi log nella console:

```
[Agenda] Inizializzazione...
[Agenda] Elementi DOM caricati:
[Agenda] Inizializzazione completata
[Agenda] Caricamento prenotazioni...
```

### 3. Errori Comuni

#### ❌ Errore: "Configurazione mancante"
**Log:**
```
[Agenda] Errore: Configurazione mancante!
```

**Causa:** Il nonce o l'URL REST API non sono stati caricati correttamente

**Soluzione:**
1. Verifica che il file `agenda-app.js` sia caricato correttamente
2. Controlla nella tab "Network" se `agenda-app.js` viene scaricato con status 200
3. Verifica che nella pagina HTML ci sia lo script `fpResvAgendaSettings`
4. Svuota la cache del browser e ricarica

---

#### ❌ Errore: "Elementi DOM non trovati"
**Log:**
```
[Agenda] Errore: Elementi DOM non trovati!
```

**Causa:** Il markup HTML dell'agenda non è stato caricato correttamente

**Soluzione:**
1. Verifica che il file `src/Admin/Views/agenda.php` esista
2. Controlla i permessi del file
3. Verifica che non ci siano errori PHP nel file

---

#### ❌ Errore API: 403 Forbidden
**Log:**
```
[API] Status: 403 Forbidden
[API] Errore permessi! Nonce: ...
```

**Causa:** Problema con il nonce o i permessi utente

**Soluzione:**
1. **Rigenera il nonce**:
   - Esci e rientra in WordPress
   - Svuota la cache del browser
   - Ricarica la pagina dell'agenda

2. **Verifica i permessi**:
   - Vai a **Utenti** → **Profilo**
   - Verifica che il tuo ruolo sia "Amministratore" o abbia il permesso `manage_fp_reservations`

3. **Fix temporaneo** (se sei amministratore):
   ```php
   // Aggiungi in functions.php temporaneamente
   add_action('admin_init', function() {
       if (current_user_can('manage_options')) {
           wp_get_current_user()->add_cap('manage_fp_reservations');
       }
   });
   ```

---

#### ❌ Errore API: 404 Not Found
**Log:**
```
[API] Status: 404 Not Found
```

**Causa:** L'endpoint REST API non è registrato correttamente

**Soluzione:**
1. Vai a **Impostazioni** → **Permalink**
2. Clicca su **Salva modifiche** (anche senza cambiare nulla)
3. Questo rigenera le regole di riscrittura degli URL
4. Ricarica la pagina dell'agenda

---

#### ❌ Errore: Response non valida
**Log:**
```
[Agenda] ✗ ERRORE: Tipo di dato non supportato
[Agenda] Tipo ricevuto: ...
```

**Causa:** La risposta dell'API non è nel formato atteso

**Soluzione:**
1. Verifica nella tab **Network** la risposta dell'endpoint `/wp-json/fp-resv/v1/admin/reservations`
2. La risposta dovrebbe essere un array o un oggetto con la chiave `reservations`
3. Se vedi HTML invece di JSON, c'è un errore PHP nel backend

---

#### ❌ Errore JavaScript
Se vedi errori JavaScript nella console che non iniziano con `[Agenda]`, potrebbero essere:

1. **Conflitto con altri plugin**: Disattiva temporaneamente gli altri plugin per test
2. **Tema incompatibile**: Prova con il tema Twenty Twenty-Four
3. **File JavaScript corrotto**: Svuota la cache e ricarica

---

### 4. Verifica l'Endpoint REST API

Puoi testare manualmente l'endpoint:

1. Apri una nuova tab del browser
2. Vai a: `https://tuosito.com/wp-json/fp-resv/v1/admin/reservations?date=2025-10-11`
3. Dovresti vedere un JSON con le prenotazioni
4. Se vedi un errore 403, il problema è il nonce/permessi
5. Se vedi un errore 404, rigenera i permalink

---

### 5. Log Completo da Condividere

Se il problema persiste, apri la console e copia **TUTTI** i messaggi che appaiono, inclusi:

```
[Agenda] Inizializzazione...
[Agenda] Elementi DOM caricati: ...
[Agenda] Caricamento prenotazioni...
[API] GET /wp-json/...
[API] Status: ...
```

E anche eventuali errori JavaScript rossi.

---

## Checklist Rapida

- [ ] Ho svuotato la cache del browser (Ctrl+Shift+R)
- [ ] Ho verificato che il file `agenda-app.js` si carichi nella tab Network
- [ ] Ho controllato la console per messaggi `[Agenda]`
- [ ] Ho verificato che non ci siano errori 403 o 404
- [ ] Ho rigenerato i permalink (Impostazioni → Permalink → Salva)
- [ ] Ho verificato i permessi utente
- [ ] Ho disattivato temporaneamente altri plugin per test

---

## File Coinvolti

Se serve modificare il codice:

1. **Controller PHP**: `src/Domain/Reservations/AdminController.php`
2. **Vista HTML**: `src/Admin/Views/agenda.php`
3. **JavaScript**: `assets/js/admin/agenda-app.js`
4. **REST API**: `src/Domain/Reservations/AdminREST.php`

---

## Comandi Utili per Debug

### Verifica che i file esistano:
```bash
ls -la src/Admin/Views/agenda.php
ls -la assets/js/admin/agenda-app.js
ls -la assets/css/admin-agenda.css
```

### Verifica i permessi:
```bash
ls -la src/Admin/Views/
```

### Cerca errori PHP nei log:
```bash
tail -f wp-content/debug.log
```

---

**Data**: 2025-10-11  
**Plugin**: FP Restaurant Reservations v0.1.10  
**File diagnostica**: Creato per risolvere problemi di caricamento agenda
