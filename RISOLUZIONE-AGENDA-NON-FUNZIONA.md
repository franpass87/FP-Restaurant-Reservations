# 🔧 Guida Rapida: Agenda Non Funziona

## Il Problema

La pagina **WordPress Admin → FP Reservations → Agenda** non funziona correttamente:
- ❌ La pagina è bianca/vuota
- ❌ Non vedi le prenotazioni
- ❌ Non riesci a creare nuove prenotazioni
- ❌ La pagina si carica all'infinito

## Soluzione Rapida (5 minuti)

### Step 1: Esegui lo Script di Debug

```bash
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-agenda-page.php
```

Lo script ti dirà esattamente cosa non va.

### Step 2: Controlla la Console del Browser

**Questo è il passaggio più importante!**

1. Apri **WordPress Admin → FP Reservations → Agenda**
2. Premi **F12** per aprire gli strumenti per sviluppatori
3. Vai sulla tab **Console**
4. Cerca messaggi **in rosso** (errori JavaScript)
5. Copia e condividi gli errori che vedi

**Screenshot utili:**
- Console senza errori = ✅
- Console con errori rossi = ❌ (questo è il problema!)

### Step 3: Controlla il Network

1. Con DevTools aperto (F12), vai sulla tab **Network**
2. **Ricarica la pagina** (Ctrl+R)
3. Verifica che questi file si carichino correttamente (status **200**):
   - `agenda-app.js` ✅
   - `admin-agenda.css` ✅
   - `admin-shell.css` ✅
4. Verifica che la chiamata API funzioni:
   - Cerca: `/wp-json/fp-resv/v1/agenda`
   - Status dovrebbe essere **200**
   - Se è **403** → Problema di permessi
   - Se è **404** → Endpoint non registrato
   - Se è **500** → Errore server

## Problemi Comuni e Soluzioni

### 1. Errore JavaScript: "fpResvAgendaSettings is not defined"

**Causa**: Il file JavaScript si carica ma le impostazioni non vengono passate.

**Soluzione**:
1. Svuota la cache del browser e del plugin di caching
2. Verifica che il file `src/Domain/Reservations/AdminController.php` sia presente
3. Reinstalla il plugin se necessario

### 2. Errore API: Status 403 (Forbidden)

**Causa**: L'utente non ha i permessi per accedere all'Agenda.

**Soluzione**:
1. Verifica di essere loggato come **Amministratore**
2. Vai su **Utenti → Il tuo profilo**
3. Verifica che il ruolo sia **Amministratore**
4. Se il problema persiste, esegui:
   ```bash
   wp eval 'FP\Resv\Core\Roles::ensureAdminCapabilities();'
   ```

### 3. Errore API: Status 404 (Not Found)

**Causa**: Gli endpoint REST non sono registrati.

**Soluzione**:
1. Vai su **Impostazioni → Permalink**
2. Clicca su **Salva** (anche senza modificare nulla)
3. Ricarica la pagina Agenda
4. Se non funziona, reinstalla il plugin

### 4. Pagina Bianca/Vuota

**Causa**: Errore PHP critico durante il caricamento.

**Soluzione**:
1. Attiva il debug di WordPress:
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
2. Ricarica la pagina Agenda
3. Controlla il file `wp-content/debug.log` per errori
4. Condividi gli errori trovati

### 5. Caricamento Infinito

**Causa**: La chiamata API non risponde o risponde troppo lentamente.

**Soluzione**:
1. Controlla il Network (F12 → Network)
2. Cerca la chiamata a `/wp-json/fp-resv/v1/agenda`
3. Se la chiamata è in attesa (status pending) per più di 30 secondi:
   - Controlla i log PHP per errori
   - Verifica che il database sia raggiungibile
   - Aumenta i timeout di PHP (memory_limit, max_execution_time)

### 6. File JavaScript/CSS Non Trovati (404)

**Causa**: I file asset non esistono o non sono nella posizione corretta.

**Soluzione**:
1. Verifica che questi file esistano:
   - `wp-content/plugins/fp-restaurant-reservations/assets/js/admin/agenda-app.js`
   - `wp-content/plugins/fp-restaurant-reservations/assets/css/admin-agenda.css`
2. Se mancano, **reinstalla il plugin**:
   - Disattiva il plugin
   - Elimina la cartella del plugin
   - Reinstalla da zero (da file ZIP o repository)

### 7. Vedo "Nessuna prenotazione" ma dovrebbero esserci

**Causa**: La pagina funziona, ma non ci sono prenotazioni nel database o il filtro è impostato su una data sbagliata.

**Soluzione**:
1. ✅ La pagina funziona! Non è un problema tecnico.
2. Verifica la data selezionata (in alto)
3. Clicca su **"Oggi"** per tornare alla data odierna
4. Prova a creare una nuova prenotazione dal pulsante **"Nuova prenotazione"**
5. Controlla che ci siano effettivamente prenotazioni nel database:
   ```sql
   SELECT COUNT(*) FROM wp_fp_reservations;
   ```

## Checklist di Debug Completa

Segui questa checklist nell'ordine per diagnosticare il problema:

- [ ] **Step 1**: Esegui lo script di debug `tools/debug-agenda-page.php`
- [ ] **Step 2**: Apri la Console del browser (F12 → Console) e cerca errori
- [ ] **Step 3**: Apri il Network del browser (F12 → Network) e verifica i caricamenti
- [ ] **Step 4**: Verifica di essere Amministratore
- [ ] **Step 5**: Svuota cache browser e WordPress
- [ ] **Step 6**: Prova in finestra incognito
- [ ] **Step 7**: Controlla i log PHP (`wp-content/debug.log`)
- [ ] **Step 8**: Verifica che i file esistano (js/css)
- [ ] **Step 9**: Rigenera i permalink (Impostazioni → Permalink → Salva)
- [ ] **Step 10**: Se nulla funziona, reinstalla il plugin

## Informazioni da Raccogliere

Se chiedi supporto, fornisci queste informazioni:

1. **Errori Console JavaScript** (copia il testo completo)
2. **Status code delle chiamate API** (dalla tab Network)
3. **Output dello script di debug** (tutto)
4. **Versione PHP** (`php -v`)
5. **Versione WordPress** (Dashboard → Aggiornamenti)
6. **Altri plugin attivi** (soprattutto cache/security)
7. **Cosa vedi esattamente sulla pagina** (screenshot)

## Comandi Utili per Debug

```bash
# Esegui lo script di debug completo
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-agenda-page.php

# Assicura permessi admin
wp eval 'FP\Resv\Core\Roles::ensureAdminCapabilities();'

# Controlla se utente ha permessi
wp eval 'var_dump(current_user_can("manage_fp_reservations"));'

# Rigenera permalink
wp rewrite flush

# Test chiamata API manuale
wp eval '
$req = new WP_REST_Request("GET", "/fp-resv/v1/agenda");
$req->set_query_params(["date" => date("Y-m-d")]);
$res = rest_do_request($req);
var_dump($res->get_data());
'

# Verifica tabelle database
wp eval 'global $wpdb; var_dump($wpdb->get_var("SHOW TABLES LIKE \"{$wpdb->prefix}fp_reservations\""));'
```

## Test Manuale in Browser

Se non hai accesso a WP-CLI, testa direttamente nel browser:

1. **Test endpoint API** (copia e incolla nel browser):
   ```
   https://tuosito.it/wp-json/fp-resv/v1/agenda?date=2025-10-11
   ```
   Dovresti vedere un JSON con le prenotazioni o un array vuoto `[]`

2. **Test file JavaScript**:
   ```
   https://tuosito.it/wp-content/plugins/fp-restaurant-reservations/assets/js/admin/agenda-app.js
   ```
   Dovresti vedere il codice JavaScript

3. **Test file CSS**:
   ```
   https://tuosito.it/wp-content/plugins/fp-restaurant-reservations/assets/css/admin-agenda.css
   ```
   Dovresti vedere il codice CSS

## Quando Reinstallare il Plugin

Reinstalla il plugin se:
- ✅ File mancanti (js/css non si caricano)
- ✅ Endpoint API non registrati (404 su /wp-json/fp-resv/v1/*)
- ✅ Errori PHP strani o inspiegabili
- ✅ Hai modificato manualmente dei file per errore

**Come reinstallare senza perdere dati:**
1. Fai un backup del database (importante!)
2. Disattiva il plugin
3. Elimina la cartella del plugin
4. Reinstalla il plugin (da ZIP o repository)
5. Attiva il plugin
6. Le tue prenotazioni e impostazioni sono salvate nel database ✅

## Link Utili

- 🔧 [Script di Debug Agenda](./tools/debug-agenda-page.php)
- 📚 [Documentazione REST API](./src/Domain/Reservations/AdminREST.php)
- 🎨 [Documentazione View Agenda](./src/Admin/Views/agenda.php)

---

**Tempo stimato per la risoluzione**: 5-15 minuti  
**Difficoltà**: ⭐⭐ Media (richiede uso DevTools browser)

**Ultimo aggiornamento**: 2025-10-11
