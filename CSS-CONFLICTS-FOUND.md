# 🚨 CONFLITTI CSS E PROBLEMI INTEGRAZIONE - 8° Controllo
**Data:** 3 Novembre 2025  
**Tipo:** Conflict Detection, Integration Issues

---

## ❌ **4 PROBLEMI CRITICI TROVATI**

### 1. **DOPPI STILI CHECKBOX - CONFLITTO ❌❌**

**PROBLEMA:**
```css
/* VECCHI STILI (Linee 643-711) - ANCORA PRESENTI */
#fp-resv-default .fp-field input[type="checkbox"],
.fp-resv-simple .fp-field input[type="checkbox"] {
    width: 16px !important;
    height: 16px !important;
    margin: 0 !important;
    /* ... 20 righe di stili ... */
}

/* NUOVI STILI (Linee 1466+) - APPENA AGGIUNTI */
.fp-checkbox {
    width: 20px;
    height: 20px;
    /* ... */
}
```

**CONFLITTO:**
- I vecchi stili usano `input[type="checkbox"]` generico
- I nuovi usano `.fp-checkbox` classe
- I vecchi hanno `!important` e sono PIÙ SPECIFICI
- **RISULTATO:** Checkbox saranno 16px invece di 20px!

**SOLUZIONE:**
- Rimuovere vecchi stili checkbox
- O aumentare specificità nuovi stili
- O usare !important sui nuovi

---

### 2. **MANCA `.screen-reader-text` CLASS ❌❌**

**TEMPLATE USA:**
```html
<!-- Linee 82, 85, 88, 91, 96 -->
<span class="screen-reader-text">Step 1: </span>
<div class="screen-reader-text" data-fp-step-announcer>
```

**CSS:**
```css
/* ❌ CLASSE NON ESISTE! */
```

**PROBLEMA:**
- Template usa `.screen-reader-text` in 5 posti
- CSS NON definisce questa classe
- Testo sarà VISIBILE invece di nascosto!
- Screen reader text apparirà a tutti!

**CORREZIONE NECESSARIA:**
```css
.screen-reader-text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
```

---

### 3. **DEBUG BLOCK CON INLINE STYLE IN PRODUZIONE ❌**

**Linea 108:**
```html
<?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
<div style="background:#f0f0f0;padding:10px;margin:10px 0;border:2px solid #333;font-size:11px;font-family:monospace;">
    <strong>🔍 DEBUG MEALS:</strong>
    <pre style="margin:5px 0;white-space:pre-wrap;">...</pre>
</div>
<?php endif; ?>
```

**PROBLEMI:**
1. Inline styles anche se protetto da WP_DEBUG
2. Font-size 11px (WCAG fail)
3. Dovrebbe usare classe `.fp-debug`

**CORREZIONE:**
```html
<?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
<div class="fp-debug-block">
    <strong>🔍 DEBUG MEALS:</strong>
    <pre class="fp-debug-pre">...</pre>
</div>
<?php endif; ?>
```

---

### 4. **MANCA GESTIONE BUTTON HIDDEN/SHOW NEL CSS ❌**

**TEMPLATE:**
```html
<button id="prev-btn" hidden>← Indietro</button>
<button id="next-btn">Avanti →</button>
<button id="submit-btn" hidden>Prenota</button>
```

**JAVASCRIPT (Linee 275-277):**
```javascript
prevBtn.style.display = step > 1 ? 'block' : 'none';
nextBtn.style.display = step < totalSteps ? 'block' : 'none';
submitBtn.style.display = step === totalSteps ? 'block' : 'none';
```

**CONFLITTO:**
- Template usa `hidden` attribute
- JavaScript usa `style.display`
- Hidden attribute è SOVRASCRITTO da style.display

**PROBLEMA:**
- Dopo primo cambio step, `hidden` attribute è ignorato
- Meglio gestire tutto via JavaScript O tutto via attribute

**SOLUZIONE:**
```javascript
// Usare hidden attribute invece di style.display
prevBtn.hidden = step <= 1;
nextBtn.hidden = step >= totalSteps;
submitBtn.hidden = step < totalSteps;
```

---

## 📊 **TABELLA CONFLITTI**

| # | Problema | Linee | Gravità | Impatto |
|---|----------|-------|---------|---------|
| 1 | Doppi stili checkbox | CSS 643-711 vs 1466+ | 🔴 CRITICAL | Checkbox 16px invece 20px |
| 2 | `.screen-reader-text` mancante | PHP 82,85,88,91,96 | 🔴 CRITICAL | Testo visibile invece nascosto |
| 3 | Debug inline style | PHP 108-110 | 🟡 MEDIUM | Code quality |
| 4 | hidden vs display | PHP 486,488 vs JS 275-277 | 🟠 HIGH | Conflitto gestione visibility |

---

## 🎯 **IMPACT ANALYSIS**

### Checkbox Conflict (CRITICAL)
```
ATTESO: 20x20px checkbox moderni
REALE: 16x16px (vecchio stile vince per !important)
PROBLEMA: Touch target troppo piccolo
```

### Screen Reader Text (CRITICAL)
```
ATTESO: Testo nascosto, solo screen reader
REALE: Testo VISIBILE a tutti (brutto)
PROBLEMA: "Step 1: 1" appare visivamente
```

### Hidden Attribute (HIGH)
```
ATTESO: hidden attribute gestisce visibilità
REALE: JavaScript usa style.display (override)
PROBLEMA: Inconsistenza gestione stato
```

---

## ✅ **CORREZIONI NECESSARIE**

### Priority 1: Rimuovere vecchi stili checkbox (CRITICAL)
```css
/* RIMUOVERE (linee 643-720 circa) */
#fp-resv-default .fp-field input[type="checkbox"] { ... }
```

### Priority 2: Aggiungere .screen-reader-text (CRITICAL)
```css
.screen-reader-text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
```

### Priority 3: Rimuovere debug inline style (MEDIUM)
```php
<div class="fp-debug-block">
```

### Priority 4: Usare hidden attribute in JS (HIGH)
```javascript
prevBtn.hidden = step <= 1;
```

---

**Conclusione:** 4 problemi di **integrazione** che rendono inutili le correzioni fatte!

