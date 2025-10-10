# Soluzione: Agenda ancora visualizzata con struttura vecchia

## Problema Identificato

Il file JavaScript (`agenda-app.js`) **è stato correttamente aggiornato** con la nuova struttura semplificata, ma il browser sta ancora caricando la **versione vecchia dalla cache**.

## Verifica Effettuata

✅ **Backend (API)**: Aggiornato correttamente - restituisce array semplice di prenotazioni  
✅ **Frontend (JavaScript)**: Aggiornato correttamente - gestisce array semplice (linea 227)  
❌ **Cache del browser**: Sta servendo la versione vecchia del JavaScript

## Soluzione Rapida

### Opzione 1: Script Automatico (Consigliato)

Ho creato uno script `force-cache-refresh.php` nella root del repository. Per usarlo:

1. **Carica il file** `force-cache-refresh.php` nella root del tuo sito WordPress (dove si trova `wp-config.php`)

2. **Visita l'URL** nel browser (devi essere loggato come amministratore):
   ```
   https://tuosito.com/force-cache-refresh.php
   ```

3. **Segui le istruzioni** mostrate dalla pagina

4. **Elimina il file** `force-cache-refresh.php` dopo l'uso per sicurezza

### Opzione 2: Via WordPress Admin

Se hai accesso all'area amministrativa di WordPress:

1. Vai su **Impostazioni → FP Restaurant Reservations** (o qualsiasi pagina admin)

2. Aggiungi questo alla fine dell'URL e premi Invio:
   ```
   &fp_resv_refresh_cache=1&_wpnonce=XXXXX
   ```
   
   ⚠️ **Nota**: Serve un nonce valido. È più facile usare l'Opzione 1 o 3.

### Opzione 3: Via WP-CLI (Se hai accesso SSH)

```bash
wp eval-file tools/refresh-cache.php
```

### Opzione 4: Via REST API (Se hai un nonce valido)

```bash
curl -X POST "https://tuosito.com/wp-json/fp-resv/v1/diagnostics/refresh-cache" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

## Dopo il Refresh della Cache

Una volta aggiornata la cache del plugin, devi anche **svuotare la cache del browser**:

1. **Chrome/Edge/Firefox**:
   - Windows: `Ctrl + Shift + R` o `Ctrl + F5`
   - Mac: `Cmd + Shift + R`

2. **Safari**:
   - Mac: `Cmd + Option + R`

3. **Oppure**:
   - Apri Developer Tools (F12)
   - Vai alla tab Network
   - Clicca con il tasto destro sul pulsante Refresh
   - Seleziona "Empty Cache and Hard Reload"

## Verifica che Funzioni

1. Apri l'**Agenda** in WordPress Admin
2. Apri **Developer Tools** (F12)
3. Vai alla tab **Network**
4. Ricarica la pagina
5. Cerca il file `agenda-app.js`
6. Controlla il parametro `ver` nell'URL:
   ```
   /assets/js/admin/agenda-app.js?ver=0.1.9.1728567890
   ```
   Il numero dopo l'ultimo punto dovrebbe essere cambiato

7. L'agenda dovrebbe ora caricare correttamente senza l'errore "Caricamento prenotazioni..."

## Perché è Successo?

Il sistema di cache del plugin usa un timestamp salvato nel database (`fp_resv_last_upgrade`) per la versione degli asset. Quando aggiorni i file manualmente (senza passare dall'upgrader di WordPress), questo timestamp non viene aggiornato automaticamente, quindi il browser continua a caricare la versione vecchia.

## Per il Futuro

Per evitare questo problema in futuro:

1. **In sviluppo**: Attiva `WP_DEBUG` in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   ```
   Questo forza il refresh automatico degli asset ad ogni caricamento.

2. **In produzione**: Dopo ogni deploy manuale, esegui sempre uno degli script di refresh cache.

3. **Deploy automatico**: Integra il refresh cache nel tuo processo di deploy:
   ```yaml
   # GitHub Actions / deploy script
   - name: Refresh cache
     run: wp eval-file tools/refresh-cache.php
   ```

## File Modificati dalla Ristrutturazione

- ✅ `src/Domain/Reservations/AdminREST.php` - API semplificata
- ✅ `assets/js/admin/agenda-app.js` - Frontend aggiornato
- ✅ `tests/E2E/agenda-dnd.spec.ts` - Test aggiornati

Tutti i file sono correttamente aggiornati, serve solo il refresh della cache!

## Riferimenti

- Documentazione completa: `docs/CACHE-REFRESH-GUIDE.md`
- Script helper: `tools/refresh-cache.php`
- Dettagli ristrutturazione: `RISTRUTTURAZIONE-AGENDA-2025-10-10.md`
