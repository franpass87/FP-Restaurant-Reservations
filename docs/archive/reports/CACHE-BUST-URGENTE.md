# 🚨 PROBLEMA CACHE - URGENTE

**Data:** 3 Novembre 2025  
**Problema:** User reports che modifiche CSS/JS non visibili

---

## ❌ **SINTOMI**

1. **Date e orari:** Continuano a caricare lentamente (10-15s)
2. **Asterischi:** Continuano a non essere inline
3. **Checkbox:** Continuano a non essere allineati

---

## 🔍 **DIAGNOSI**

### Modifiche CONFERMATE nei file:
✅ `form-simple.js` - Fix async PRESENTE (linea 636, 700)  
✅ `form-simple-inline.css` - CSS asterischi PRESENTE (linea 1522-1552)  
✅ `form-simple-inline.css` - CSS checkbox PRESENTE (linea 1382-1403)

### Il problema è: **CACHE**

Il browser/WordPress sta caricando versioni vecchie dei file!

---

## 🚀 **SOLUZIONE IMMEDIATA**

### Step 1: Cambia versione plugin
```php
// fp-restaurant-reservations.php
Version: 0.9.0-rc10.4-cache-bust  ← Cambio versione
```

Questo forza WordPress a rigenerare gli asset hash.

### Step 2: PULISCI TUTTA LA CACHE

#### A. Cache Browser (CRITICO)
```
1. Chrome/Edge: Ctrl + Shift + Delete
   - Seleziona "Immagini e file memorizzati nella cache"
   - Intervallo: "Tutto"
   - Cancella

2. OPPURE: Ctrl + F5 (hard refresh)
   - Ripeti 3-4 volte per sicurezza
```

#### B. Cache WordPress
Se hai plugin di cache attivi (es. WP Rocket, W3 Total Cache):
```
1. Vai in Dashboard WordPress
2. Trova il plugin cache
3. Click su "Purge All Cache" o "Elimina tutta la cache"
```

#### C. Cache Salient Theme
```
1. Dashboard → Salient → General Settings
2. Cerca "Clear Cache" o simile
3. Click per pulire cache tema
```

#### D. Cache Local by Flywheel
```
1. Apri Local by Flywheel
2. Right-click sul sito
3. "Restart Site" per pulire cache server
```

---

## 📊 **VERIFICA FIX APPLICATO**

### Test 1: Verifica versione CSS caricata

**Apri Developer Tools (F12) → Network → Ricarica pagina**

Cerca file che contiene il CSS del form:
- Se vedi `?ver=0.9.0-rc10.3` ← VECCHIA versione (CACHE!)
- Se vedi `?ver=0.9.0-rc10.4` ← NUOVA versione (OK!)

### Test 2: Inspect asterisco

**F12 → Elements → Seleziona asterisco rosso**

Verifica CSS applicato:
```css
/* DEVE ESSERE: */
.fp-required {
    display: inline !important;
    white-space: nowrap !important;
    overflow: visible !important;
}

/* Se vedi altro = CACHE! */
```

### Test 3: Console JavaScript

**F12 → Console**

Ricarica pagina e cerca:
```javascript
[FALLBACK] Generando date di default per pranzo
```

Se NON vedi questo log = JavaScript vecchio (CACHE!)

---

## 🎯 **SEQUENCE FIX**

### Sequenza COMPLETA (da fare in ordine):

1. ✅ Cambia versione plugin (già fatto)
2. ⏳ Riavvia sito in Local by Flywheel
3. ⏳ Pulisci cache WordPress (se presente plugin)
4. ⏳ Pulisci cache browser (Ctrl + Shift + Delete)
5. ⏳ Hard refresh pagina (Ctrl + F5) x3
6. ⏳ Verifica con F12 → Network
7. ⏳ Test funzionamento

---

## 🔧 **CACHE PERMANENTE FIX**

### Per evitare problemi futuri:

#### Opzione A: Disable cache in development
```php
// wp-config.php
define('WP_CACHE', false);
```

#### Opzione B: Query string hash
```php
// WidgetController.php (già implementato)
$version = Plugin::assetVersion();  // usa git hash o timestamp
```

#### Opzione C: Cache-Control headers
```php
// .htaccess
<filesMatch "\\.(css|js)$">
  Header set Cache-Control "no-cache, must-revalidate"
</filesMatch>
```

---

## ⚠️ **SE CONTINUA A NON FUNZIONARE**

### Debug avanzato:

1. **Verifica file fisico:**
```bash
cd "C:\Users\franc\Local Sites\fp-development\app\public\wp-content\plugins\FP-Restaurant-Reservations"
cat assets/css/form-simple-inline.css | grep "white-space: nowrap"
```

Se NON trova = file non salvato!

2. **Verifica WordPress load:**
```php
// Aggiungi in WidgetController.php dopo linea 315
error_log('[FP-RESV] CSS length: ' . strlen($formSimpleCss));
error_log('[FP-RESV] CSS hash: ' . md5($formSimpleCss));
```

Poi controlla error log di WordPress.

3. **Force inline style:**
Ultima risorsa, aggiungi CSS direttamente in template PHP.

---

## 📝 **CHECKLIST VELOCE**

- [ ] Versione plugin cambiata a 0.9.0-rc10.4
- [ ] Sito riavviato in Local
- [ ] Cache WordPress pulita
- [ ] Cache browser pulita (Ctrl + Shift + Del)
- [ ] Hard refresh x3 (Ctrl + F5)
- [ ] F12 → Network: verificata versione CSS
- [ ] F12 → Elements: verificato CSS asterisco
- [ ] F12 → Console: verificato JavaScript log
- [ ] Test: asterischi inline
- [ ] Test: date caricano < 1s
- [ ] Test: checkbox allineati

---

## 🚀 **ASPETTATIVA POST-FIX**

### Date e orari:
- **Prima:** 10-15 secondi
- **Dopo:** < 100 millisecondi
- **Log console:** `[FALLBACK] Generando date di default`

### Asterischi:
- **Prima:** A capo sotto il testo
- **Dopo:** Inline accanto al testo
- **CSS:** `white-space: nowrap !important`

### Checkbox:
- **Prima:** Testo sotto il checkbox
- **Dopo:** Testo accanto al checkbox
- **CSS:** `display: flex; flex-direction: row`

---

**Autore:** AI Assistant  
**Urgenza:** 🔴 ALTA  
**Priorità:** Pulire cache IMMEDIATAMENTE

