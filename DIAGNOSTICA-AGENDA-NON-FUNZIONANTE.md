# Diagnostica Agenda Non Funzionante - 2025-10-11

## 🔍 Problema
L'agenda non visualizza le prenotazioni.

## ✅ Verifiche Effettuate

### Codice
- ✅ File `agenda-app.js` presente e sintatticamente corretto
- ✅ File `admin-agenda.css` presente
- ✅ Endpoint REST API `/agenda` correttamente registrato
- ✅ Query database `findAgendaRange()` corretta
- ✅ Metodo `loadReservations()` con logging completo
- ✅ Autoloader Composer presente (`vendor/autoload.php`)

### Ambiente
- ✅ PHP 8.4 installato
- ✅ Composer installato e dipendenze caricate
- ✅ NPM packages installati
- ✅ Build frontend completato

## 🧪 Come Diagnosticare il Problema

### Opzione 1: Script di Test PHP (Raccomandato)

Ho creato uno script di test completo: `test-agenda-debug.php`

**Come eseguirlo:**

```bash
# Da terminale (se hai accesso SSH)
cd /path/to/wordpress/wp-content/plugins/fp-restaurant-reservations
php test-agenda-debug.php
```

**Oppure da browser:**

1. Carica il file `test-agenda-debug.php` nella root di WordPress
2. Apri nel browser: `https://tuosito.com/test-agenda-debug.php`
3. Leggi il report completo

**Lo script verifica:**
- ✅ Tabelle database esistono
- ✅ Numero prenotazioni nel database
- ✅ Permessi utente corrente
- ✅ Endpoint REST API registrato e funzionante
- ✅ File plugin presenti
- ✅ Risposta API con dati reali

### Opzione 2: Console Browser (F12)

1. Apri la pagina Agenda in WordPress
2. Premi **F12** per aprire la console sviluppatore
3. Vai al tab **Console**
4. Cerca i log con prefisso `[Agenda]`

**Cosa cercare:**

```javascript
// ✅ CORRETTO - Dovresti vedere:
[Agenda] Inizializzazione... { settings: {...}, restRoot: "...", hasNonce: true }
[Agenda] Elementi DOM caricati: { datePicker: true, ... }
[Agenda] Cambio vista: day
[Agenda] Caricamento prenotazioni... { date: "2025-10-11", view: "day", ... }
[API] GET /wp-json/fp-resv/v1/agenda?date=2025-10-11
[API] Status: 200 OK
[Agenda] ✓ Caricate N prenotazioni con successo

// ❌ PROBLEMI POSSIBILI:
[Agenda] Errore: Configurazione mancante!  // -> Problema con wp_localize_script
[Agenda] Errore: Elementi DOM non trovati! // -> Template HTML non caricato
[API] Status: 403 Forbidden              // -> Problema permessi/nonce
[API] Status: 404 Not Found              // -> Endpoint non registrato
[Agenda] ✓ Caricate 0 prenotazioni       // -> Database vuoto
```

### Opzione 3: Network Tab (F12)

1. Apri pagina Agenda
2. Premi **F12**
3. Vai al tab **Network**
4. Ricarica pagina (Ctrl+R)
5. Filtra per "agenda"
6. Clicca sulla richiesta `agenda?date=...`

**Cosa verificare:**

```
Request URL: https://tuosito.com/wp-json/fp-resv/v1/agenda?date=2025-10-11
Request Method: GET
Status Code: 200 OK  // ← Deve essere 200

Request Headers:
  X-WP-Nonce: xxxxx  // ← Deve essere presente

Response:
{
  "meta": {...},
  "stats": {...},
  "reservations": [...]  // ← Deve contenere array (anche vuoto)
}
```

## 🐛 Problemi Comuni e Soluzioni

### 1. "Nessuna prenotazione" ma ci sono prenotazioni nel DB

**Causa**: Date nel futuro lontano o nel passato lontano

**Soluzione**:
- Naviga alla data corretta usando il date picker
- Verifica che la data sia nel formato YYYY-MM-DD
- Controlla che le prenotazioni abbiano date valide

### 2. Errore 403 (Forbidden)

**Causa**: Problema permessi o nonce scaduto

**Soluzione**:
```bash
# Verifica permessi utente corrente
# In wp-admin > Utenti > Il tuo utente
# Ruolo deve essere "Administrator" o avere "manage_fp_reservations"

# Se nonce scaduto: ricarica pagina (Ctrl+Shift+R)
```

### 3. Errore 404 (Not Found)

**Causa**: Endpoint REST API non registrato

**Soluzione**:
```bash
# 1. Verifica che vendor/autoload.php esista
ls -la vendor/autoload.php

# 2. Flush permalink
# WP Admin > Impostazioni > Permalink > Salva modifiche

# 3. Verifica plugin attivo
# WP Admin > Plugin > FP Restaurant Reservations deve essere attivo
```

### 4. Database vuoto (0 prenotazioni)

**Causa**: Nessuna prenotazione esistente

**Soluzione**:
- Crea una prenotazione di test dall'agenda (pulsante "Nuova prenotazione")
- Oppure crea dal database:
```sql
INSERT INTO wp_fp_reservations (date, time, party, status, customer_id)
VALUES ('2025-10-11', '19:30:00', 4, 'confirmed', NULL);

INSERT INTO wp_fp_customers (first_name, last_name, email, phone)
VALUES ('Test', 'Cliente', 'test@test.com', '1234567890');
```

### 5. "Elementi DOM non trovati"

**Causa**: Template HTML non caricato o script caricato troppo presto

**Soluzione**:
```bash
# Verifica che il file template esista
ls -la src/Admin/Views/agenda.php

# Verifica che lo script attenda DOMContentLoaded
# (già implementato in agenda-app.js)
```

### 6. fpResvAgendaSettings non definito

**Causa**: wp_localize_script non eseguito

**Soluzione**:
- Verifica in console: `console.log(window.fpResvAgendaSettings)`
- Deve mostrare: `{ restRoot: "...", nonce: "...", ... }`
- Se undefined: problema in `AdminController::enqueueAssets()`

## 📋 Checklist Completa

Prima di chiedere supporto, verifica:

- [ ] Database ha prenotazioni (`SELECT COUNT(*) FROM wp_fp_reservations`)
- [ ] Utente ha permessi admin o `manage_fp_reservations`
- [ ] File `vendor/autoload.php` esiste
- [ ] Plugin è attivo in WP Admin > Plugin
- [ ] Console browser non mostra errori rossi
- [ ] Network tab mostra status 200 per `/agenda`
- [ ] Risposta API contiene struttura corretta con `reservations`
- [ ] Date delle prenotazioni sono valide e recenti
- [ ] Cache browser svuotata (Ctrl+Shift+R)
- [ ] Plugin di cache WordPress disabilitati (se presenti)

## 🔧 Quick Fix

Se tutto il resto fallisce, prova:

```bash
# 1. Reinstalla dipendenze
rm -rf vendor node_modules
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 2. Flush cache WordPress
# WP Admin > Plugin > Disabilita/Abilita cache plugin

# 3. Ricarica pagina con hard refresh
# Chrome/Firefox: Ctrl + Shift + R
# Safari: Cmd + Shift + R

# 4. Verifica console (F12)
```

## 📞 Supporto

Se dopo tutti questi controlli l'agenda non funziona, raccogli:

1. **Output di `test-agenda-debug.php`** (completo)
2. **Screenshot console browser** (F12 > Console)
3. **Screenshot Network tab** (F12 > Network > richiesta agenda)
4. **Versione WordPress**: (WP Admin > Dashboard)
5. **Versione PHP**: Visibile in `test-agenda-debug.php` o in WP Admin > Salute del sito

---

**Data**: 2025-10-11  
**Branch**: cursor/fix-agenda-display-issues-3f8c  
**Tipo**: Troubleshooting Guide
