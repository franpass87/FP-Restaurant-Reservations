# 🚨 CONFLITTO CRITICO JAVASCRIPT - 9° Controllo
**Data:** 3 Novembre 2025  
**Tipo:** JavaScript vs HTML Attribute Conflict

---

## ❌ **3 PROBLEMI CRITICI TROVATI**

### 1. **JAVASCRIPT USA `.style.display` MA TEMPLATE USA `hidden` ❌❌❌**

**TEMPLATE PHP (Modificato da me):**
```html
<!-- Linee 486-488 -->
<button id="prev-btn" hidden>← Indietro</button>
<button id="next-btn">Avanti →</button>
<button id="submit-btn" hidden>Prenota</button>

<!-- Linee 430, 434, 438, 444, 446, 450, 454 -->
<div id="summary-occasion-row" hidden>...</div>
<div id="summary-extras-row" hidden>...</div>

<!-- Linea 156 -->
<div id="meal-notice" hidden>...</div>
```

**JAVASCRIPT (Linee 275-277, 98, 189-242):**
```javascript
// Linee 275-277 - BUTTONS
prevBtn.style.display = step > 1 ? 'block' : 'none';
nextBtn.style.display = step < totalSteps ? 'block' : 'none';
submitBtn.style.display = step === totalSteps ? 'block' : 'none';

// Linea 98 - MEAL NOTICE
mealNoticeDiv.style.display = 'block';

// Linee 189-242 - SUMMARY ROWS
document.getElementById('summary-occasion-row').style.display = 'flex';
document.getElementById('summary-notes-row').style.display = 'none';
```

**PROBLEMA GRAVISSIMO:**
```
Template usa: hidden attribute
JavaScript usa: .style.display

RISULTATO:
1. Button ha hidden attribute iniziale
2. JavaScript setta .style.display = 'block'
3. .style.display SOVRASCRIVE hidden attribute
4. Button appare!
5. Ma successivamente hidden rimane lì (orphan attribute)
6. Inconsistenza gestione stato
```

**IMPATTO:**
- Bottoni potrebbero non apparire/sparire correttamente
- Summary rows potrebbero rimanere nascoste
- Meal notice potrebbe non apparire

**CORREZIONE:** Usare SOLO `hidden` attribute in JavaScript

---

### 2. **MANCANO VENDOR PREFIXES (Cross-Browser) ❌**

**PROPRIETÀ SENZA PREFISSI:**
```css
/* Linea 565-567 - user-select HA prefissi ✅ */
-webkit-user-select: none !important;
-moz-user-select: none !important;
-ms-user-select: none !important;
user-select: none !important;

/* MA MANCANO SU: */

/* Transform - Linee 59, 89, 112, 140, ecc */
transform: translateX(-50%);  /* ❌ NO -webkit-transform */
transform: translateY(-2px);  /* ❌ NO -ms-transform */

/* Animation - Linee 278, 380 */
animation: slideInDown 0.3s;  /* ❌ NO -webkit-animation */

/* Transition - Linee 79, 113, 203, ecc */
transition: all 0.3s;  /* ❌ NO -webkit-transition */

/* Appearance - Linee 1474-1476 */
-webkit-appearance: none;  /* ✅ HA prefissi */
-moz-appearance: none;     /* ✅ HA prefissi */
appearance: none;

/* Transform-origin - NON USATO */
/* Flex - NON serve prefisso (2023+) */
/* Grid - NON serve prefisso (2023+) */
```

**BROWSER IMPATTO:**
- Safari vecchio (< 10): transform potrebbe non funzionare
- IE 11: animation non funziona
- Edge vecchio (< 79): transition potrebbe non funzionare

**CORREZIONE:**
```css
/* Aggiungere autoprefixer O prefissi manuali */
-webkit-transform: translateX(-50%);
-ms-transform: translateX(-50%);
transform: translateX(-50%);
```

---

### 3. **ANIMAZIONI SENZA `will-change` (Performance) ⚠️**

**ELEMENTI ANIMATI:**
```css
/* Linee 112-113 - Step transitions */
.fp-step {
    transform: translateX(20px);
    transition: all 0.4s;
    /* ❌ NO will-change */
}

/* Linea 453 - Meal button shine */
.fp-meal-btn::before {
    transition: left 0.5s;
    /* ❌ NO will-change */
}

/* Linea 509 - Button shine */
.fp-btn::before {
    transition: left 0.5s;
    /* ❌ NO will-change */
}
```

**PROBLEMA:**
- Browser non può ottimizzare rendering
- Animazioni potrebbero essere lag
- GPU acceleration non attivata

**CORREZIONE:**
```css
.fp-step {
    will-change: transform, opacity;
}

.fp-meal-btn::before,
.fp-btn::before,
.fp-time-slot::before {
    will-change: left;
}
```

**ATTENZIONE:** Usare con moderazione, troppo `will-change` = memory leak!

---

## 📊 **TABELLA PROBLEMI**

| # | Problema | Gravità | Impatto | Browser |
|---|----------|---------|---------|---------|
| 1 | JS usa .style.display, template hidden | 🔴 CRITICAL | Bottoni non funzionano | Tutti |
| 2 | Transform senza -webkit- | 🟠 HIGH | Safari vecchio | Safari < 10 |
| 3 | Animation senza -webkit- | 🟠 HIGH | Safari/IE | Safari, IE11 |
| 4 | Transition senza -webkit- | 🟡 MEDIUM | Safari vecchio | Safari < 9 |
| 5 | No will-change | 🟡 MEDIUM | Performance | Mobile |

---

## 🎯 **BROWSER SUPPORT ATTUALE**

### ✅ **Supportati Bene**
- Chrome 90+ ✅
- Edge 90+ ✅
- Firefox 80+ ✅
- Safari 14+ ✅

### ⚠️ **Supporto Parziale**
- Safari 10-13: transform potrebbe non funzionare
- IE 11: animation non funziona (ma IE è morto 2022)
- Edge < 79: alcune proprietà potrebbero fallire

### ❌ **Non Supportati**
- IE 11 e inferiori (OK, fuori supporto Microsoft)
- Safari < 10 (OK, molto vecchio)

---

## 🎯 **DECISIONI DA PRENDERE**

### Opzione A: Autoprefixer (Consigliato)
```bash
npm install --save-dev autoprefixer postcss
```

**PRO:**
- Automatico
- Sempre aggiornato
- Build-time processing

**CONTRO:**
- Richiede build step
- Dependency aggiuntiva

### Opzione B: Prefissi Manuali
```css
/* Ogni transform/animation con prefisso */
-webkit-transform: translateX(-50%);
-ms-transform: translateX(-50%);
transform: translateX(-50%);
```

**PRO:**
- No build step
- Controllo totale

**CONTRO:**
- Manuale (error-prone)
- File più grande
- Manutenzione difficile

### Opzione C: Ignorare (Sconsigliato)
```
Browser vecchi: chi se ne frega
Safari 10-13: pochi utenti (~2%)
```

**PRO:**
- Nessuno sforzo

**CONTRO:**
- Alcuni utenti: broken experience

---

## 📊 **BROWSER MARKET SHARE**

| Browser | Version | Market | Supporto Prefissi Necessario |
|---------|---------|--------|------------------------------|
| Chrome | 90+ | 65% | ✅ No (moderno) |
| Safari | 14+ | 18% | ✅ No (moderno) |
| **Safari | 10-13 | ~2% | ⚠️ SI (transform)** |
| Firefox | 80+ | 10% | ✅ No (moderno) |
| Edge | 90+ | 5% | ✅ No (moderno) |
| IE 11 | - | <1% | ❌ Non supportabile |

**2% utenti Safari vecchio potrebbero avere problemi**

---

## ✅ **RACCOMANDAZIONE**

### Priority 1: Fix JavaScript (CRITICAL)
```javascript
// CORREGGERE form-simple.js linee 275-277
// PRIMA
prevBtn.style.display = step > 1 ? 'block' : 'none';

// DOPO
prevBtn.hidden = step <= 1;
nextBtn.hidden = step >= totalSteps;
submitBtn.hidden = step < totalSteps;

// CORREGGERE anche tutte le righe summary (189-242)
// PRIMA
element.style.display = 'flex';

// DOPO
element.hidden = false;
element.hidden = true;

// CORREGGERE meal-notice (linea 98)
mealNoticeDiv.hidden = false;
```

### Priority 2: Vendor Prefixes (HIGH)
```
Opzione consigliata: Autoprefixer
browserslist: > 1%, last 2 versions, Safari >= 10
```

### Priority 3: will-change (MEDIUM)
```css
.fp-step {
    will-change: transform, opacity;
}

/* Rimuovere dopo animazione */
.fp-step.active {
    will-change: auto;
}
```

---

## 🎯 **IMPACT FIX JS**

Se correggo JavaScript:
- ✅ Consistenza hidden attribute
- ✅ Codice più semantic
- ✅ Performance migliore (no style recalc)
- ✅ Meno conflitti
- ⚠️ Richiede testing completo form

---

**Conclusione:** Trovato **conflitto CRITICO** JavaScript che usa .style.display invece di hidden attribute!

