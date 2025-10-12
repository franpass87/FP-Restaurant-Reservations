# 🐛 Debug Manager Endpoints

## Problema Riscontrato

```
[Manager] Error loading overview: SyntaxError: Unexpected end of JSON input
[Manager] Error loading reservations: SyntaxError: Failed to execute 'json' on 'Response': Unexpected end of JSON input
```

**Causa**: Gli endpoint REST API non stanno ritornando JSON valido o la risposta è vuota.

---

## 🔍 Passo 1: Verifica Configurazione JavaScript

Apri la **Console del Browser** (F12) nella pagina del Manager e verifica:

```javascript
// Verifica configurazione globale
console.log(window.fpResvManagerSettings);

// Output atteso:
{
  restRoot: "/wp-json/fp-resv/v1",
  nonce: "abc123...",
  links: {...},
  strings: {...}
}
```

### ✅ Se la configurazione è presente:
Passa al Passo 2.

### ❌ Se la configurazione manca:
Il problema è nel caricamento dello script PHP. Verifica:
1. File `src/Domain/Reservations/AdminController.php` esiste
2. Funzione `enqueueAssets()` è chiamata correttamente
3. Non ci sono errori PHP che impediscono il caricamento

---

## 🔍 Passo 2: Test Endpoint Manuale

### Test con fetch() in console:

```javascript
// Test endpoint overview
fetch('/wp-json/fp-resv/v1/agenda/overview', {
    headers: {
        'X-WP-Nonce': window.fpResvManagerSettings.nonce
    }
})
.then(r => r.text())
.then(text => {
    console.log('Response length:', text.length);
    console.log('Response preview:', text.substring(0, 500));
    try {
        const json = JSON.parse(text);
        console.log('✅ JSON valido:', json);
    } catch(e) {
        console.error('❌ JSON non valido:', e);
        console.log('Risposta completa:', text);
    }
});
```

### Possibili output:

#### ✅ **Output OK** (JSON valido):
```json
{
  "today": {...},
  "week": {...},
  "month": {...},
  "trends": {...}
}
```
→ L'endpoint funziona! Il problema è nel codice JavaScript.

#### ❌ **Output vuoto**:
```
Response length: 0
```
→ L'endpoint non sta ritornando nulla. Vai al Passo 3.

#### ❌ **Output HTML o testo**:
```html
<!DOCTYPE html>...
```
→ Stai ricevendo una pagina di errore invece di JSON. Vai al Passo 4.

#### ❌ **Output corrotto**:
```
{"today":...}Warning: Something...
```
→ C'è output PHP indesiderato. Vai al Passo 5.

---

## 🔍 Passo 3: Verifica Registrazione Endpoint

Verifica che l'endpoint sia registrato:

```javascript
// Test se l'endpoint esiste
fetch('/wp-json/fp-resv/v1/')
.then(r => r.json())
.then(data => {
    console.log('Endpoint disponibili:', data.routes);
    console.log('Cerca /agenda/overview:', data.routes['/fp-resv/v1/agenda/overview']);
});
```

### ✅ Se l'endpoint è presente:
Vai al Passo 4.

### ❌ Se l'endpoint manca:
L'endpoint non è registrato. Verifica:

1. **File**: `src/Domain/Reservations/AdminREST.php`
2. **Metodo**: `registerRoutes()`
3. **Registrazione**:
   ```php
   register_rest_route(
       'fp-resv/v1',
       '/agenda/overview',
       [
           'methods'             => WP_REST_Server::READABLE,
           'callback'            => [$this, 'handleOverview'],
           'permission_callback' => [$this, 'checkPermissions'],
       ]
   );
   ```

4. **Hook WordPress**:
   ```php
   add_action('rest_api_init', [$this, 'registerRoutes']);
   ```

**Soluzione**: Assicurati che la classe `AdminREST` sia istanziata nel bootstrap del plugin.

---

## 🔍 Passo 4: Verifica Permessi

Testa se hai i permessi corretti:

```javascript
// Test con user_can
fetch('/wp-json/fp-resv/v1/agenda/overview', {
    headers: {
        'X-WP-Nonce': window.fpResvManagerSettings.nonce
    }
})
.then(r => {
    console.log('Status:', r.status);
    if (r.status === 403) {
        console.error('❌ Permessi insufficienti');
    } else if (r.status === 401) {
        console.error('❌ Non autenticato');
    }
    return r.text();
})
.then(text => console.log('Response:', text));
```

### ❌ Se ricevi 403 Forbidden:

Verifica nel file `src/Domain/Reservations/AdminREST.php`:

```php
private function checkPermissions(): bool
{
    $canManage = current_user_can(Roles::MANAGE_RESERVATIONS);
    $canManageOptions = current_user_can('manage_options');
    return $canManage || $canManageOptions;
}
```

**Soluzione**: Assicurati che il tuo utente abbia la capability `manage_fp_reservations` o `manage_options` (amministratore).

---

## 🔍 Passo 5: Verifica Output Indesiderato

Se la risposta è corrotta (es: `{"data":...}Warning: ...`), c'è output PHP prima/dopo la risposta REST.

### Trova la fonte dell'output:

1. **Apri**: `wp-content/debug.log` (se `WP_DEBUG_LOG` è attivo)
2. **Cerca**: Warning, Notice, Echo statements
3. **File sospetti**:
   - `src/Domain/Reservations/AdminREST.php`
   - Plugin di terze parti che fanno output

### Output buffering check:

Nel file `AdminREST.php`, verifica che ci sia:

```php
public function handleOverview(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    // Output buffering
    ob_start();
    
    try {
        // ... codice ...
        
        // Pulisci output inatteso
        if (ob_get_level() > 0) {
            $unexpectedOutput = ob_get_contents();
            if (!empty(trim($unexpectedOutput))) {
                error_log('[AdminREST] Output inatteso: ' . $unexpectedOutput);
            }
            ob_clean();
        }
        
        $response = rest_ensure_response($data);
        
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        return $response;
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        // ...
    }
}
```

---

## 🔍 Passo 6: Test Diretto con WP-CLI (Avanzato)

Se hai accesso al terminale:

```bash
# Test endpoint direttamente
wp eval "
\$request = new WP_REST_Request('GET', '/fp-resv/v1/agenda/overview');
\$server = rest_get_server();
\$response = \$server->dispatch(\$request);
echo json_encode(\$response->get_data(), JSON_PRETTY_PRINT);
"
```

---

## 🔧 Soluzioni Rapide

### Soluzione 1: Svuota Cache

```bash
# Da WordPress Admin
- Vai in Plugins > FP Reservations
- Disattiva e riattiva il plugin
- Svuota cache (se usi plugin di caching)
```

### Soluzione 2: Verifica File Modificati

Assicurati che questi file esistano e siano corretti:

```bash
✅ src/Admin/Views/manager.php
✅ assets/js/admin/manager-app.js (versione aggiornata con logging)
✅ assets/css/admin-manager.css
✅ src/Domain/Reservations/AdminController.php
✅ src/Domain/Reservations/AdminREST.php (NON modificato)
```

### Soluzione 3: Forza Ricaricamento Asset

Nel browser:
1. Apri DevTools (F12)
2. Tab Network
3. Shift + F5 (hard reload)
4. Verifica che `manager-app.js` sia caricato con la versione più recente

### Soluzione 4: Debug Logging Avanzato

Aggiungi questo all'inizio di `handleOverview()` in `AdminREST.php`:

```php
error_log('[AdminREST] handleOverview chiamato');
error_log('[AdminREST] User ID: ' . get_current_user_id());
error_log('[AdminREST] Can manage: ' . (current_user_can('manage_fp_reservations') ? 'yes' : 'no'));
```

Poi controlla `wp-content/debug.log`.

---

## 📋 Checklist Completa

Prima di procedere, verifica:

- [ ] Sei su `/wp-admin/admin.php?page=fp-resv-manager`
- [ ] La pagina non ha errori PHP visibili
- [ ] Console browser aperta (F12)
- [ ] `window.fpResvManagerSettings` è presente
- [ ] Nonce è presente in fpResvManagerSettings
- [ ] User è autenticato e ha permessi admin
- [ ] `WP_DEBUG` è attivo (per vedere errori PHP)
- [ ] Cache del browser svuotata
- [ ] Plugin di cache disabilitato temporaneamente

---

## 🆘 Se Nulla Funziona

### Opzione A: Usa Test Script

Apri questo URL nel browser (dopo aver caricato il file test):

```
/wp-admin/admin.php?page=fp-resv-manager&test-endpoints=1
```

Questo caricherà `test-manager-endpoints.php` che testa tutti gli endpoint.

### Opzione B: Verifica Database

```sql
-- Verifica che ci siano prenotazioni
SELECT COUNT(*) FROM wp_fp_reservations;

-- Verifica prenotazioni recenti
SELECT * FROM wp_fp_reservations 
WHERE date >= CURDATE() 
ORDER BY date ASC, time ASC 
LIMIT 10;
```

### Opzione C: Contatta Supporto

Fornisci queste informazioni:

1. Output console completo
2. `wp-content/debug.log` (ultime 50 righe)
3. Versione WordPress
4. Versione PHP
5. Plugin attivi
6. Output di `/wp-json/fp-resv/v1/` (screenshot)

---

## 💡 Note Finali

Il nuovo JavaScript aggiornato include:
- ✅ Logging dettagliato
- ✅ Gestione risposte vuote
- ✅ Error handling migliorato
- ✅ Verifica configurazione all'avvio

**Dopo aver caricato il JS aggiornato**, ricarica la pagina e controlla la console per vedere esattamente dove si blocca.

---

**Ultima modifica**: 12 Ottobre 2025

