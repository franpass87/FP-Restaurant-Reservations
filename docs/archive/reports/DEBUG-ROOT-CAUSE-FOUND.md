# Root Cause Identificato - Errore Fatale PHP

**Data**: 2025-01-10  
**Problema**: Richiesta AJAX Closures restituisce errore 500

## 🔴 ROOT CAUSE IDENTIFICATO

### Errore Fatale PHP in Plugin FP-Performance

**Evidenza dal log** (line 43):
```json
{
  "error": {
    "type": 1,
    "message": "Uncaught TypeError: FP\\PerfSuite\\Http\\Ajax\\RecommendationsAjax::__construct(): Argument #1 ($container) must be of type FP\\PerfSuite\\ServiceContainer, FP\\PerfSuite\\ServiceContainerAdapter given, called in C:\\Users\\franc\\OneDrive\\Desktop\\FP-Performance\\src\\Providers\\RestServiceProvider.php on line 84",
    "file": "C:\\Users\\franc\\OneDrive\\Desktop\\FP-Performance\\src\\Http\\Ajax\\RecommendationsAjax.php",
    "line": 29
  },
  "has_action": true
}
```

### Analisi

1. **L'hook è registrato correttamente**: `has_action: true`
2. **La richiesta AJAX arriva al server**: Log "AJAX REQUEST DETECTED in init"
3. **Errore fatale durante `init`**: Un errore fatale PHP in FP-Performance impedisce l'esecuzione
4. **L'hook non viene mai eseguito**: WordPress non può raggiungere `do_action('wp_ajax_fp_resv_closures_list')` a causa dell'errore fatale
5. **Server risponde 500**: WordPress restituisce errore 500 a causa dell'errore fatale

### Stack Trace

L'errore si verifica in:
- `FP-Performance/src/Http/Ajax/RecommendationsAjax.php:29`
- Chiamato da: `FP-Performance/src/Providers/RestServiceProvider.php:84`
- Durante l'hook `init` (linea #6 nello stack trace)

### Conclusione

**Il problema NON è nel plugin FP Restaurant Reservations**, ma in **FP-Performance** che causa un errore fatale durante l'inizializzazione, impedendo l'esecuzione di tutti gli hook AJAX successivi.

### Soluzione

1. **Fix immediato**: Correggere l'errore di tipo in FP-Performance
   - File: `FP-Performance/src/Http/Ajax/RecommendationsAjax.php:29`
   - Problema: Il costruttore si aspetta `ServiceContainer` ma riceve `ServiceContainerAdapter`
   - Fix: Modificare il type hint o passare il tipo corretto

2. **Workaround temporaneo**: Disabilitare temporaneamente FP-Performance per testare FP Restaurant Reservations

3. **Prevenzione futura**: Aggiungere error handling più robusto per evitare che errori fatali in un plugin blocchino altri plugin

### Evidenze Runtime

- ✅ Hook registrato: `has_action('wp_ajax_fp_resv_closures_list')` = `true`
- ✅ Richiesta arriva: Log "AJAX REQUEST DETECTED in init"
- ❌ Errore fatale: TypeError in FP-Performance durante `init`
- ❌ Hook non eseguito: Nessun log da `wp_ajax_fp_resv_closures_list`
- ❌ Server risponde 500: A causa dell'errore fatale

### File da Correggere

**FP-Performance** (non FP Restaurant Reservations):
- `src/Http/Ajax/RecommendationsAjax.php:29` - Correggere type hint
- `src/Providers/RestServiceProvider.php:84` - Passare il tipo corretto

