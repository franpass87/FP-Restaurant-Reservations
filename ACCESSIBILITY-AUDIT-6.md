# 🔬 AUDIT ACCESSIBILITÀ - 6° Controllo
**Data:** 3 Novembre 2025  
**Tipo:** WCAG 2.1 AA Compliance - Contrast, Focus, Motion

---

## 🚨 **5 PROBLEMI CRITICI TROVATI**

### ❌ 1. MANCA `prefers-reduced-motion` (WCAG 2.3.3)

**PROBLEMA:**
```css
/* NESSUNA media query per ridurre animazioni! */
```

**IMPATTO:**
- Utenti con epilessia fotosensitiva: RISCHIO
- Utenti con disturbi vestibolari: NAUSEA
- Utenti con ADHD: DISTRAZIONE
- **WCAG 2.1 Level AAA:** Animation Interaction (2.3.3)

**ANIMAZIONI PERICOLOSE NEL FORM:**
```css
.fp-step { transition: all 0.4s; }
.fp-meal-btn::before { transition: left 0.5s; }  /* Effetto shine */
.fp-btn::before { transition: left 0.5s; }       /* Effetto shine */
.fp-time-slot::before { transition: left 0.5s; } /* Effetto shine */
@keyframes slideInDown { ... }
@keyframes slideOutUp { ... }
```

**CORREZIONE OBBLIGATORIA:**
```css
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

---

### ❌ 2. OPACITY 0.4 su DISABLED = CONTRAST FAIL (WCAG 1.4.3)

**PROBLEMA:**
```css
.fp-btn:disabled {
    opacity: 0.4;  /* ❌ TROPPO TRASPARENTE */
}

.fp-time-slot.disabled {
    opacity: 0.4;  /* ❌ TROPPO TRASPARENTE */
}
```

**CALCOLO CONTRAST:**
```
Button text: #374151 (grigio scuro)
Opacity: 0.4
Risultato visivo: #374151 @ 40% opacity
Background: #ffffff

Contrast ratio: ~2.1:1 ❌
WCAG AA richiede: 4.5:1 per testo normale
WCAG AAA richiede: 7:1 per testo normale
```

**VIOLAZIONE:** WCAG 1.4.3 Contrast (Minimum) - Level AA

**CORREZIONE:**
```css
.fp-btn:disabled {
    opacity: 0.6;          /* Minimo per 3:1 contrast */
    background: #f3f4f6;   /* Background visivo */
    color: #6b7280;        /* Colore più scuro */
    cursor: not-allowed;
}

.fp-time-slot.disabled {
    opacity: 1;            /* No opacity! */
    background: #f3f4f6;   /* Grigio chiaro */
    color: #9ca3af;        /* Grigio medio */
    cursor: not-allowed;
}
```

---

### ❌ 3. FOCUS OUTLINE MANCANTE (WCAG 2.4.7)

**PROBLEMA:**
```css
.fp-field input:focus,
.fp-field select:focus,
.fp-field textarea:focus {
    outline: none;  /* ❌ RIMOSSO COMPLETAMENTE */
    border-color: #374151;
    box-shadow: 0 0 0 3px rgba(55, 65, 81, 0.1);
}
```

**RISCHI:**
1. Box-shadow può essere disabilitato da High Contrast Mode
2. Utenti keyboard-only: perdono focus indicator
3. **WCAG 2.4.7:** Focus Visible - Level AA

**TEST:**
- Windows High Contrast Mode: box-shadow scompare!
- Rimane solo border-color change (insufficiente)

**CORREZIONE:**
```css
.fp-field input:focus-visible,
.fp-field select:focus-visible,
.fp-field textarea:focus-visible {
    outline: 2px solid #374151;      /* Outline solido */
    outline-offset: 2px;              /* Spaziato dal bordo */
    border-color: #374151;
    box-shadow: 0 0 0 3px rgba(55, 65, 81, 0.1);
}

/* Rimuovi outline solo per mouse users */
.fp-field input:focus:not(:focus-visible),
.fp-field select:focus:not(:focus-visible),
.fp-field textarea:focus:not(:focus-visible) {
    outline: none;
}
```

---

### ❌ 4. BOTTONI SENZA :focus-visible (WCAG 2.4.7)

**PROBLEMA:**
```css
.fp-meal-btn:hover { ... }
/* ❌ NESSUNO stile per :focus */

.fp-btn:hover { ... }
/* ❌ NESSUNO stile per :focus */

.fp-time-slot:hover { ... }
/* ❌ NESSUNO stile per :focus */
```

**VIOLAZIONE:** 
- Keyboard users non vedono quale bottone è in focus
- WCAG 2.4.7 Focus Visible

**CORREZIONE:**
```css
.fp-meal-btn:focus-visible,
.fp-btn:focus-visible,
.fp-time-slot:focus-visible {
    outline: 2px solid #374151;
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgba(55, 65, 81, 0.2);
}
```

---

### ❌ 5. PLACEHOLDER COLOR CONTRAST (WCAG 1.4.3)

**PROBLEMA:**
```css
.fp-field input::placeholder,
.fp-field textarea::placeholder {
    color: #9ca3af;  /* Grigio chiaro */
}
```

**CALCOLO CONTRAST:**
```
Placeholder: #9ca3af (grigio 400)
Background: #ffffff (bianco)
Contrast: 2.84:1 ❌

WCAG AA Text: 4.5:1 richiesto
WCAG AA Large Text: 3:1 richiesto
```

**VIOLAZIONE:** WCAG 1.4.3 Contrast (Minimum)

**NOTA:** 
- Placeholder NON è testo persistente
- WCAG Success Criterion 1.4.3 si applica a "testo" non placeholder
- Tuttavia, best practice è >= 3:1

**CORREZIONE BEST PRACTICE:**
```css
.fp-field input::placeholder,
.fp-field textarea::placeholder {
    color: #6b7280;  /* Grigio più scuro */
    /* Contrast: 4.6:1 ✅ */
}
```

---

## 📊 **TABELLA CONTRAST RATIOS**

### Testo Normale (WCAG AA: 4.5:1)

| Elemento | Foreground | Background | Ratio | Status |
|----------|-----------|------------|-------|--------|
| **H2 Title** | #111827 | #ffffff | 16.9:1 | ✅ AAA |
| **H3 Step** | #111827 | #ffffff | 16.9:1 | ✅ AAA |
| **Label** | #374151 | rgba(255,255,255,0.7) | ~12:1 | ✅ AAA |
| **Input text** | #374151 | #ffffff | 8.9:1 | ✅ AAA |
| **Button text** | #ffffff | #374151 | 8.9:1 | ✅ AAA |
| **Placeholder** | #9ca3af | #ffffff | 2.84:1 | ❌ FAIL |
| **Disabled (0.4)** | #374151 @ 40% | #ffffff | ~2.1:1 | ❌ FAIL |
| **Notice Success** | #166534 | #f0fdf4 | 6.1:1 | ✅ AA |
| **Notice Error** | #dc2626 | #fef2f2 | 5.8:1 | ✅ AA |

**PROBLEMI:**
- ❌ Placeholder: 2.84:1 < 4.5:1 (ma accettabile, non è testo persistente)
- ❌ Disabled: 2.1:1 < 4.5:1 (CRITICO - testo persistente)

---

## 🎯 **ALTRI PROBLEMI TROVATI**

### 6. No High Contrast Mode Support

**PROBLEMA:**
```css
/* NESSUNA media query per forced-colors */
```

**IMPATTO:**
- Windows High Contrast Mode ignora box-shadow
- Utenti ipovedenti perdono indicatori focus
- Custom colors potrebbero essere illeggibili

**CORREZIONE:**
```css
@media (forced-colors: active) {
    .fp-btn,
    .fp-meal-btn,
    .fp-time-slot {
        border: 2px solid;  /* Bordo più spesso */
    }
    
    .fp-btn:focus,
    .fp-meal-btn:focus,
    .fp-time-slot:focus {
        outline: 3px solid;  /* Outline molto visibile */
    }
}
```

---

### 7. No Print Styles

**PROBLEMA:**
```css
/* NESSUN @media print */
```

**IMPATTO:**
- Utente stampa pagina: form stampato inutilmente
- Spreco carta/inchiostro

**CORREZIONE:**
```css
@media print {
    .fp-resv-simple {
        display: none;  /* Nascondi form in stampa */
    }
}
```

---

### 8. No Landscape Orientation

**PROBLEMA:**
```css
/* NESSUNA gestione landscape mobile */
```

**IMPATTO:**
- iPhone landscape: form potrebbe essere troppo alto
- Poco spazio verticale

**CORREZIONE:**
```css
@media (max-width: 640px) and (orientation: landscape) {
    .fp-step {
        padding: 8px;  /* Ridotto verticalmente */
    }
    
    .fp-field {
        margin-bottom: 8px;  /* Più compatto */
    }
}
```

---

## 📊 **WCAG 2.1 COMPLIANCE PRIMA DI FIX**

| Criterio | Livello | Status |
|----------|---------|--------|
| **1.4.3 Contrast (Minimum)** | AA | ❌ FAIL (disabled, placeholder) |
| **2.3.3 Animation from Interactions** | AAA | ❌ FAIL (no prefers-reduced-motion) |
| **2.4.7 Focus Visible** | AA | ⚠️ RISK (outline removed) |
| **1.4.12 Text Spacing** | AA | ✅ PASS |
| **2.5.5 Target Size** | AAA | ⚠️ 90% (progress 36px) |
| **1.3.1 Info and Relationships** | A | ✅ PASS (aria-describedby) |
| **4.1.2 Name, Role, Value** | A | ✅ PASS |

**Score WCAG AA:** 80% (5/7 passing)  
**Score WCAG AAA:** 50% (2/4 passing)

---

## ✅ **DOPO CORREZIONI**

| Criterio | Livello | Status |
|----------|---------|--------|
| **1.4.3 Contrast** | AA | ✅ PASS |
| **2.3.3 Animation** | AAA | ✅ PASS |
| **2.4.7 Focus Visible** | AA | ✅ PASS |
| **2.5.5 Target Size** | AAA | ⚠️ 90% |

**Score WCAG AA:** 100% ✅  
**Score WCAG AAA:** 75% ✅

---

## 🎯 **PRIORITY FIX**

### 🔴 CRITICAL (WCAG AA Blockers)
1. ❌ `prefers-reduced-motion` media query
2. ❌ Disabled opacity 0.4 → 0.6 + color change
3. ❌ Focus-visible explicit su bottoni

### 🟠 HIGH (Best Practice)
4. ⚠️ Placeholder color #9ca3af → #6b7280
5. ⚠️ High Contrast Mode support

### 🟡 MEDIUM (Nice to have)
6. Print styles
7. Landscape orientation
8. Zoom 200% test

---

**Conclusione:** Trovati **5 problemi accessibilità** che violano WCAG AA/AAA!

