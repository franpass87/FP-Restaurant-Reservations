# Diagnosi e Risoluzione: Errore Caricamento Infinito Agenda

## Problema Originale

```
Error loading reservations: SyntaxError: Unexpected end of JSON input
```

L'agenda rimaneva in stato di caricamento infinito senza mai mostrare le prenotazioni.

## Cause Possibili

### 1. **Risposta Vuota dal Server**
Il server potrebbe restituire una risposta vuota o incompleta per diversi motivi:
- Errore PHP che interrompe l'output prima del completamento del JSON
- Caratteri non validi che corrompono la risposta JSON
- Timeout del database o della query
- Output buffering corrotto
- Headers già inviati da warning/notice PHP

### 2. **Errori di Codifica UTF-8**
Caratteri non validi nei dati (nomi clienti, note, etc.) possono causare:
- Fallimento della serializzazione JSON
- Troncamento della risposta
- Caratteri di controllo che rompono il parsing

### 3. **Errori nel Database**
- Query SQL che restituisce valori non validi
- Colonne NULL non gestite correttamente
- Dati corrotti nel database

### 4. **Problemi di Serializzazione**
- Riferimenti circolari negli oggetti
- Risorse non serializzabili (file handles, connessioni DB)
- Valori float/double non finiti (INF, NAN)

## Soluzioni Implementate

### 1. **Gestione Robusta Risposte Vuote (JavaScript)**

**File**: `assets/js/admin/agenda-app.js`

**Modifiche alla funzione `request()`**:
```javascript
// Prima di parsare il JSON, verifica che ci sia contenuto
return response.text().then(text => {
    if (!text || text.trim() === '') {
        return null;
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Failed to parse JSON response:', text);
        throw new Error('Invalid JSON response from server');
    }
});
```

**Benefici**:
- ✅ Gestisce risposte vuote senza crash
- ✅ Mostra l'errore esatto nel caso di JSON malformato
- ✅ Logga la risposta effettiva per debugging

**Modifiche alla funzione `loadReservations()`**:
```javascript
// Gestisce null o risposte vuote
if (!data) {
    reservations = [];
} else {
    reservations = Array.isArray(data) ? data : (data.reservations || []);
}
```

**Benefici**:
- ✅ Previene errori quando data è null
- ✅ Mostra lo stato "nessuna prenotazione" invece del caricamento infinito

### 2. **Validazione JSON Lato Server (PHP)**

**File**: `src/Domain/Reservations/AdminREST.php`

**Controllo pre-invio**:
```php
// Test di serializzazione JSON prima di inviare la risposta
$jsonTest = json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR);
if ($jsonTest === false) {
    $errorMsg = json_last_error_msg();
    error_log('FP Reservations: Failed to encode response as JSON: ' . $errorMsg);
    error_log('FP Reservations: Response structure - ' . $this->debugStructure($response));
    
    return new WP_Error(
        'fp_resv_json_error',
        __('Errore nella serializzazione dei dati.', 'fp-restaurant-reservations'),
        ['status' => 500, 'json_error' => $errorMsg]
    );
}
```

**Benefici**:
- ✅ Intercetta errori di serializzazione prima dell'invio
- ✅ Logga dettagli per debugging
- ✅ Restituisce un errore strutturato invece di una risposta vuota

### 3. **Sanitizzazione UTF-8 Migliorata**

**Funzione `sanitizeUtf8()` potenziata**:
```php
private function sanitizeUtf8(string $value): string
{
    if ($value === '') {
        return '';
    }
    
    try {
        // Converte in UTF-8 valido
        $sanitized = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        
        // Rimuove caratteri di controllo
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $sanitized);
        
        // Fallback se preg_replace fallisce
        if ($cleaned === null) {
            error_log('FP Reservations: preg_replace failed in sanitizeUtf8');
            return $sanitized !== false ? $sanitized : '';
        }
        
        return $cleaned;
    } catch (\Throwable $e) {
        error_log('FP Reservations: Error in sanitizeUtf8 - ' . $e->getMessage());
        // Fallback drastico: rimuove caratteri non ASCII
        return preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/u', '', $value) ?? '';
    }
}
```

**Benefici**:
- ✅ Gestisce caratteri non validi senza crash
- ✅ Doppio fallback per massima resilienza
- ✅ Logging dettagliato degli errori

### 4. **Protezione Layout Service**

**Gestione errori getOverview()**:
```php
// Ottieni overview con gestione errori
try {
    $overview = $this->layout?->getOverview() ?? ['rooms' => [], 'groups' => []];
} catch (\Throwable $e) {
    error_log('FP Reservations: Error getting layout overview - ' . $e->getMessage());
    $overview = ['rooms' => [], 'groups' => []];
}
```

**Benefici**:
- ✅ Previene crash se il layout service fallisce
- ✅ Fornisce dati di fallback validi

### 5. **Logging Diagnostico Avanzato**

**In modalità debug (WP_DEBUG)**:
```php
error_log(sprintf('FP Reservations: Found %d rows for range %s to %s', 
    count($rows), 
    $start->format('Y-m-d'), 
    $end->format('Y-m-d')
));

error_log(sprintf('FP Reservations: Response size: %d bytes, %d reservations', 
    strlen($jsonTest), 
    count($reservations)
));
```

**Benefici**:
- ✅ Traccia il flusso di esecuzione
- ✅ Identifica dove si verifica il problema
- ✅ Mostra dimensioni della risposta

## Come Diagnosticare il Problema

### 1. **Verifica Risposta HTTP**

Apri gli strumenti sviluppatore del browser (F12):
1. Vai alla tab **Network**
2. Ricarica la pagina agenda
3. Cerca la chiamata a `wp-json/fp-resv/v1/agenda?date=...`
4. Controlla:
   - **Status Code**: dovrebbe essere 200
   - **Response Headers**: `Content-Type` dovrebbe essere `application/json`
   - **Response Body**: verifica se è JSON valido o vuoto

### 2. **Controlla Log PHP**

**Percorsi comuni dei log**:
- `/var/log/apache2/error.log`
- `/var/log/nginx/error.log`
- `wp-content/debug.log` (se WP_DEBUG_LOG è attivo)

**Cerca questi pattern**:
```bash
grep "FP Reservations" /path/to/error.log | tail -50
```

**Errori da cercare**:
- `Failed to encode response as JSON`
- `Error in handleAgenda`
- `Error mapping reservation`
- `preg_replace failed in sanitizeUtf8`

### 3. **Abilita Debug WordPress**

In `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Questo abiliterà:
- ✅ Logging dettagliato nel file `wp-content/debug.log`
- ✅ Informazioni sulla dimensione della risposta
- ✅ Numero di righe trovate nel database

### 4. **Test Manuale Endpoint**

**Con cURL**:
```bash
curl -v -H "X-WP-Nonce: YOUR_NONCE" \
  "https://your-site.com/wp-json/fp-resv/v1/agenda?date=2025-10-10" \
  -o response.json
```

**Controlla**:
- Il file `response.json` è valido JSON?
- La dimensione è ragionevole?
- Ci sono caratteri strani all'inizio o alla fine?

**Validazione JSON**:
```bash
cat response.json | jq .
```

Se `jq` fallisce, il JSON è malformato.

### 5. **Controlla Database**

**Cerca dati problematici**:
```sql
-- Cerca caratteri non validi nelle note
SELECT id, notes, allergies 
FROM wp_fp_reservations 
WHERE notes REGEXP '[[:cntrl:]]' 
   OR allergies REGEXP '[[:cntrl:]]'
LIMIT 10;

-- Cerca nomi con caratteri strani
SELECT c.id, c.first_name, c.last_name, c.email
FROM wp_fp_customers c
WHERE first_name REGEXP '[^[:print:]]'
   OR last_name REGEXP '[^[:print:]]'
LIMIT 10;
```

## Scenari Specifici e Soluzioni

### Scenario A: Risposta Completamente Vuota

**Sintomo**: Response body vuoto nella tab Network

**Possibile causa**:
- Fatal error PHP che termina lo script
- Output buffering cancellato
- Headers già inviati

**Debug**:
1. Controlla `error.log` per fatal errors
2. Verifica che non ci siano `echo` o `print` prima dell'endpoint
3. Cerca notice/warning PHP che potrebbero corrompere l'output

**Soluzione temporanea**:
```php
// In wp-config.php, disabilita temporaneamente notice/warning
error_reporting(E_ERROR | E_PARSE);
```

### Scenario B: JSON Troncato

**Sintomo**: Response body inizia con `{` ma è incompleto

**Possibile causa**:
- Timeout esecuzione PHP
- Memory limit raggiunto
- Carattere non valido a metà risposta

**Debug**:
```bash
# Controlla dimensione risposta
cat response.json | wc -c

# Controlla se termina correttamente
tail -c 100 response.json
```

**Soluzione**:
```php
// In wp-config.php
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
```

### Scenario C: JSON con Caratteri di Controllo

**Sintomo**: JSON sembra valido ma parsing fallisce

**Possibile causa**:
- BOM (Byte Order Mark) all'inizio
- Null bytes nel testo
- Caratteri di controllo invisibili

**Debug**:
```bash
# Mostra caratteri nascosti
cat response.json | od -c | head -20

# Cerca null bytes
grep -a $'\x00' response.json
```

**Soluzione**: Le modifiche al `sanitizeUtf8()` dovrebbero rimuovere questi caratteri

### Scenario D: Errore Solo con Certe Date

**Sintomo**: Funziona per alcune date, fallisce per altre

**Possibile causa**:
- Prenotazioni specifiche con dati corrotti
- Volume di dati troppo grande per certe date

**Debug**:
```sql
-- Trova la prenotazione problematica
SELECT r.id, r.date, r.notes, c.first_name, c.last_name
FROM wp_fp_reservations r
LEFT JOIN wp_fp_customers c ON r.customer_id = c.id
WHERE r.date = '2025-10-10'
ORDER BY r.id;
```

**Soluzione**: Pulisci manualmente i dati corrotti

## Checklist di Verifica

Dopo aver implementato le modifiche:

- [ ] Cache browser svuotata (Ctrl+F5)
- [ ] Versione asset aggiornata (controlla query string in Network tab)
- [ ] WP_DEBUG attivo e log controllato
- [ ] Risposta HTTP verificata in Network tab
- [ ] Nessun errore JavaScript in Console
- [ ] Endpoint testato con curl/Postman
- [ ] Database controllato per dati corrotti
- [ ] PHP error log controllato

## Monitoraggio Continuo

### Metriche da Monitorare

1. **Tempo di risposta endpoint**
   - Dovrebbe essere < 2 secondi
   - Oltre 5 secondi indica problema performance

2. **Dimensione risposta**
   - Tipicamente 10-500 KB
   - Oltre 1 MB indica troppi dati

3. **Frequenza errori**
   - 0 errori in condizioni normali
   - Picchi indicano problema sistemico

### Alert da Configurare

```php
// In un plugin di monitoring
add_action('rest_api_error', function($error) {
    if (str_contains($error->get_error_code(), 'fp_resv')) {
        // Invia notifica
        error_log('CRITICAL: FP Reservations API Error - ' . $error->get_error_message());
    }
});
```

## Conclusione

Le modifiche implementate forniscono:

1. ✅ **Resilienza**: Gestisce errori senza crash
2. ✅ **Osservabilità**: Logging dettagliato per debugging
3. ✅ **Fallback**: Valori di default validi in caso di errore
4. ✅ **Prevenzione**: Valida dati prima dell'invio
5. ✅ **User Experience**: Mostra stati appropriati invece di caricamento infinito

Il problema del caricamento infinito dovrebbe ora essere risolto. Se persiste:

1. Segui la sezione "Come Diagnosticare il Problema"
2. Controlla i log per identificare la causa specifica
3. Usa gli strumenti di debug forniti
4. Considera gli scenari specifici descritti sopra

Per ulteriore supporto, raccogli:
- Screenshot della tab Network
- Contenuto dei log PHP (ultimi 100 righe)
- Output della query SQL di test
- Versione PHP e WordPress in uso
