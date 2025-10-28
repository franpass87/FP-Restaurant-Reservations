# 🚀 Istruzioni Test Fix Agenda

## ✅ Il Fix è Pronto

Ho corretto il bug critico dell'output buffering che causava risposte vuote dall'API. Ora devi solo verificare che funzioni.

## 📋 Test da Eseguire (in Ordine)

### Test 1: Endpoint Diagnostico (2 minuti)

**Cosa fare:**
1. Apri il browser
2. Vai a: `https://www.villadianella.it/wp-json/fp-resv/v1/agenda-test`
3. Dovresti vedere qualcosa tipo:
   ```json
   {
     "success": true,
     "message": "Endpoint test funziona!",
     "timestamp": "2025-10-12 10:30:00",
     "user_id": 1
   }
   ```

**Risultato atteso:**
- ✅ **Se vedi il JSON:** Il sistema REST API funziona
- ❌ **Se vedi errore 404:** Gli endpoints non sono registrati → Riavvia WordPress/svuota cache

---

### Test 2: Script di Verifica Completo (3 minuti)

**Cosa fare:**
1. Apri: `https://www.villadianella.it/test-agenda-endpoint-verification.php`
2. Vedrai un JSON con tutti i test
3. Controlla la sezione `"summary"`:
   ```json
   {
     "summary": {
       "total": 8,
       "passed": 8,
       "failed": 0
     }
   }
   ```

**Risultato atteso:**
- ✅ **Tutti i test passati:** Tutto funziona perfettamente
- ⚠️ **Alcuni test falliti:** Guarda i dettagli per capire il problema

---

### Test 3: Agenda Backend (5 minuti)

**Cosa fare:**
1. Accedi al backend WordPress
2. Vai su: Menu → Prenotazioni → Agenda
3. Apri la **Console del browser** (F12)
4. Cerca questi messaggi:
   ```
   [Agenda] Caricamento prenotazioni...
   [API] GET https://www.villadianella.it/wp-json/fp-resv/v1/agenda?date=...
   [API] Status: 200
   [API] Response length: XXX bytes  ← Deve essere > 0
   [Agenda] ✓ Caricate N prenotazioni con successo
   ```

**Risultato atteso:**
- ✅ **Vedi "Response length: XXX bytes" con XXX > 0:** L'API restituisce dati!
- ✅ **Vedi le prenotazioni nell'agenda:** FUNZIONA! 🎉
- ❌ **Vedi ancora "Response length: 0 bytes":** Leggi il file di log (Test 4)

---

### Test 4: Verifica Log (se serve)

**Cosa fare:**
1. Scarica/leggi il file: `wp-content/agenda-endpoint-calls.log`
2. Cerca le righe più recenti
3. Dovresti vedere:
   ```
   2025-10-12 10:30:00 - handleAgenda CHIAMATO
   2025-10-12 10:30:00 - checkPermissions chiamato: result=true
   2025-10-12 10:30:00 - Creazione risposta con N prenotazioni
   2025-10-12 10:30:00 - Risposta creata con successo, status: 200
   ```

**Cosa cercare:**
- ✅ **"handleAgenda CHIAMATO"** → L'endpoint viene eseguito
- ✅ **"checkPermissions: result=true"** → Hai i permessi
- ✅ **"Creazione risposta con N prenotazioni"** → Trova le prenotazioni
- ✅ **"Risposta creata con successo"** → Tutto OK!

**Problemi comuni:**
- ❌ **"checkPermissions: result=false"** → Non hai i permessi admin
- ❌ **"0 prenotazioni"** → Non ci sono prenotazioni per quella data (normale)
- ❌ **"ERRORE"** → Leggi il messaggio di errore nel log

---

## 🐛 Cosa Fare se Non Funziona

### Problema: Ancora "Response length: 0 bytes"

**Possibili cause:**

1. **Cache non svuotata**
   - Svuota cache WordPress
   - Svuota cache browser (Ctrl+Shift+Delete)
   - Prova in incognito

2. **Plugin interferenti**
   - Controlla se altri plugin modificano REST API
   - Disabilita temporaneamente altri plugin per test

3. **Permessi file**
   - Verifica che `wp-content/` sia scrivibile
   - Controlla il log di errori PHP: `wp-content/debug.log`

4. **Configurazione WordPress**
   - Verifica che REST API sia abilitata
   - Controlla permalink (Impostazioni → Permalink → Salva)

### Problema: Errore 403 o 401

**Soluzione:**
- Sei loggato come amministratore?
- Il log mostrerà: `checkPermissions: result=false`
- Verifica ruoli e capability dell'utente

### Problema: Errore 500

**Soluzione:**
- C'è un errore PHP fatale
- Controlla: `wp-content/debug.log`
- Il log agenda mostrerà lo stack trace

---

## 📞 Cosa Riportare se Hai Problemi

Se qualcosa non funziona, mandami:

1. **Console del browser** - Screenshot o testo dei messaggi `[Agenda]` e `[API]`
2. **File di log agenda** - Ultime 20-30 righe di `wp-content/agenda-endpoint-calls.log`
3. **Risultato test** - Output di `test-agenda-endpoint-verification.php`
4. **Descrizione** - Cosa vedi vs cosa ti aspetti

---

## 🎉 Se Tutto Funziona

Ottimo! Il fix ha risolto il problema. Puoi:

1. ✅ Rimuovere gli script di test:
   - `test-agenda-endpoint-verification.php`
   - Endpoint `/agenda-test` (opzionale, non dà fastidio)

2. ✅ Mantenere il logging (utile per debug futuro)
   - Il file di log si sovrascrive automaticamente
   - Puoi svuotarlo quando vuoi

3. ✅ Fare commit e push delle modifiche

---

## 📚 File Modificati (per riferimento)

- `src/Domain/Reservations/AdminREST.php` - Fix output buffering + logging

## 🔑 La Correzione Chiave

```php
// PRIMA (causava risposta vuota):
ob_get_clean(); // ❌ Chiudeva il buffer troppo presto

// DOPO (funziona):
ob_get_contents(); // ✅ Legge
ob_clean(); // ✅ Pulisce ma mantiene aperto  
// ... crea risposta ...
ob_end_clean(); // ✅ Chiude DOPO
```

Questo assicura che WordPress REST API possa restituire correttamente la risposta JSON.

---

**Hai bisogno di aiuto?** Segui i test in ordine e mandami i risultati! 🚀
