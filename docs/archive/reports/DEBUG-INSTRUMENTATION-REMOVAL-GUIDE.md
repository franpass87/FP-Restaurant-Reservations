# Guida Rimozione Strumentazione Debug

**Data**: 2025-01-10  
**Stato**: Strumentazione ancora presente (da rimuovere dopo conferma utente)

## 📋 File con Strumentazione Debug

La strumentazione debug è presente in **4 file** con **60 occorrenze** totali:

1. `src/Domain/Closures/AjaxHandler.php` - ~20 occorrenze
2. `src/Domain/Reservations/Admin/AgendaHandler.php` - ~14 occorrenze
3. `src/Domain/Reservations/Repository.php` - ~8 occorrenze
4. `src/Domain/Reservations/Service.php` - ~18 occorrenze

## 🔍 Come Identificare la Strumentazione

La strumentazione debug è racchiusa tra commenti:
```php
// #region agent log
// ... codice di logging ...
// #endregion
```

### Esempio di Strumentazione

```php
// #region agent log
$logFile = (defined('ABSPATH') ? ABSPATH : dirname(...)) . '.cursor/debug.log';
$logData = json_encode([
    'id' => 'log_' . time() . '_' . uniqid(),
    'timestamp' => time() * 1000,
    'location' => __FILE__ . ':' . __LINE__,
    'message' => '...',
    'data' => [...],
    'sessionId' => 'debug-session',
    'runId' => 'run1',
    'hypothesisId' => 'A'
]) . "\n";
@file_put_contents($logFile, $logData, FILE_APPEND);
// #endregion
```

## ✅ Procedura di Rimozione

### Passo 1: Verifica Funzionamento
Prima di rimuovere, verificare che tutto funzioni:
```bash
cd tests/e2e
npx playwright test --reporter=list
```

**Atteso**: 13/13 test passati

### Passo 2: Rimozione Strumentazione

Per ogni file, rimuovere tutti i blocchi:
```php
// #region agent log
... codice ...
// #endregion
```

**Nota**: NON rimuovere i log `error_log()` normali, solo la strumentazione debug.

### Passo 3: Verifica Post-Rimozione

Dopo la rimozione, eseguire nuovamente i test:
```bash
npx playwright test --reporter=list
```

**Atteso**: 13/13 test ancora passati

## 📝 File da Modificare

### 1. `src/Domain/Closures/AjaxHandler.php`
- Rimuovere tutti i blocchi `#region agent log` / `#endregion`
- Mantenere i log `error_log()` normali (es. line 155, 191, 212, 253)

### 2. `src/Domain/Reservations/Admin/AgendaHandler.php`
- Rimuovere tutti i blocchi `#region agent log` / `#endregion`
- Mantenere i log `error_log()` normali (es. line 83, 120, 162, 195)

### 3. `src/Domain/Reservations/Repository.php`
- Rimuovere tutti i blocchi `#region agent log` / `#endregion`

### 4. `src/Domain/Reservations/Service.php`
- Rimuovere tutti i blocchi `#region agent log` / `#endregion`

## ⚠️ Attenzione

**NON rimuovere**:
- Log `error_log()` normali (produzione)
- Commenti esplicativi
- Codice funzionale

**Rimuovere SOLO**:
- Blocchi `#region agent log` / `#endregion`
- Codice di logging con `sessionId`, `runId`, `hypothesisId`
- Scrittura su `.cursor/debug.log`

## 🔄 File Opzionali da Rimuovere

Dopo rimozione strumentazione, questi file possono essere rimossi (opzionale):
- `src/Domain/Closures/AjaxDebug.php` - Classe debug temporanea
- File report debug nella root (opzionale, possono essere archiviati)

## ✅ Checklist Rimozione

- [ ] Eseguire test E2E prima della rimozione
- [ ] Rimuovere strumentazione da `AjaxHandler.php`
- [ ] Rimuovere strumentazione da `AgendaHandler.php`
- [ ] Rimuovere strumentazione da `Repository.php`
- [ ] Rimuovere strumentazione da `Service.php`
- [ ] Eseguire test E2E dopo la rimozione
- [ ] Verificare che tutti i test passino ancora
- [ ] (Opzionale) Rimuovere `AjaxDebug.php`
- [ ] (Opzionale) Archiviare report debug

## 📊 Statistiche Strumentazione

- **File con strumentazione**: 4
- **Occorrenze totali**: ~60
- **Tempo stimato rimozione**: 10-15 minuti
- **Rischio**: Basso (solo rimozione commenti/logging)

## 🎯 Quando Rimuovere

Rimuovere la strumentazione solo dopo:
1. ✅ Conferma utente che tutto funziona
2. ✅ Test E2E passati
3. ✅ Verifica manuale pagine admin
4. ✅ Nessun problema rilevato

---

**Nota**: La strumentazione non impatta le performance in produzione, ma è buona pratica rimuoverla dopo il debug.

