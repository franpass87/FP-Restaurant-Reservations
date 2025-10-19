# 🔍 Analisi Layout Step - Colonne, Spazi e Margini

**Data:** 2025-10-19  
**Focus:** Struttura colonne e spacing nel form

---

## 📊 Struttura Trovata

### Layout Colonne negli Step

#### **step-details.php** (step più complesso)

```html
<!-- GRIGLIA 2 COLONNE: Nome e Cognome -->
<div class="fp-resv-fields fp-resv-fields--grid fp-resv-fields--2col">
    <label class="fp-resv-field fp-field">
        <span>Nome</span>
        <input class="fp-input" type="text" ...>
    </label>
    <label class="fp-resv-field fp-field">
        <span>Cognome</span>
        <input class="fp-input" type="text" ...>
    </label>
</div>

<!-- CAMPO SINGOLO: Email -->
<label class="fp-resv-field fp-field fp-resv-field--email">
    <span>Email</span>
    <input class="fp-input" type="email" ...>
</label>

<!-- CAMPO SINGOLO: Telefono -->
<label class="fp-resv-field fp-field fp-resv-field--phone">
    <span>Telefono</span>
    <div class="fp-resv-phone-input">
        <input type="tel" ...>
        <select ...>Prefissi</select>
    </div>
</label>

<!-- GRIGLIA EXTRAS: Checkbox -->
<fieldset class="fp-resv-extra fp-fieldset">
    <div class="fp-resv-fields fp-resv-fields--grid fp-resv-fields--extras">
        <label>Seggioloni</label>
        <label>Sedia a rotelle</label>
        <label>Animali</label>
    </div>
</fieldset>
```

---

## 🎨 CSS Trovati per Layout

### ⚠️ PROBLEMA: Definizioni Duplicate!

| File CSS | Regola | Valore Grid |
|----------|--------|-------------|
| **form-thefork-bw.css** | `.fp-resv-fields--grid` | `gap: var(--fp-space-lg)` |
| **form-thefork-bw.css** | `.fp-resv-fields--2col` | `grid-template-columns: repeat(2, 1fr)` |
| **form/_layout.css** | `.fp-resv-fields--grid` | `gap: var(--fp-space-md)` ← DIVERSO! |
| **form/_layout.css** | `.fp-resv-fields--2col` | `grid-template-columns: repeat(2, 1fr)` |
| **SPAZIATURE-AUMENTATE.css** | `.fp-resv-fields--grid` | `gap: 1rem !important` ← OVERRIDE! |

### 🔴 Conflitto Trovato:

```css
/* form-thefork-bw.css */
.fp-resv-fields--grid {
  gap: var(--fp-space-lg);  /* 24px */
}

/* form/_layout.css */
.fp-resv-fields--grid {
  gap: var(--fp-space-md);  /* 16px */
}

/* SPAZIATURE-AUMENTATE.css */
.fp-resv-fields--grid {
  gap: 1rem !important;     /* 16px FORZATO */
}
```

**Risultato:** SPAZIATURE-AUMENTATE.css vince con `!important`  
**Gap effettivo:** 16px invece di 24px

---

## 📏 Spacing Verticale (margin-bottom)

### Problemi Trovati:

```css
/* form-thefork-bw.css */
.fp-resv-field {
  margin-bottom: var(--fp-space-lg);  /* 24px */
}

/* SPAZIATURE-AUMENTATE.css */
.fp-field {
  margin-bottom: 1rem !important;     /* 16px FORZATO */
}
```

**Mobile:**
```css
/* SPAZIATURE-AUMENTATE.css */
@media (max-width: 640px) {
  .fp-field {
    margin-bottom: 0.5rem !important;  /* 8px TROPPO POCO! */
  }
  
  .fp-resv-fields--grid {
    gap: 0.5rem !important;            /* 8px TROPPO POCO! */
  }
}
```

---

## 📐 Breakpoint Responsivi

### Multiple definizioni responsive:

| Breakpoint | File | Regola |
|------------|------|--------|
| 640px | form-thefork-bw.css | `.fp-resv-fields--2col` → 1 col |
| 640px | form/_layout.css | `.fp-resv-fields--2col` → 1 col |
| 640px | SPAZIATURE-AUMENTATE.css | Gap ridotto a 8px |
| 768px | form/_responsive.css | Grid settings |
| 1024px | form/_responsive.css | Desktop settings |

---

## 🐛 Problemi Identificati

### 1. ❌ **SPAZIATURE-AUMENTATE.css** sovrascrive tutto
- Usa `!important` ovunque
- Spacing troppo ridotti su mobile (8px)
- Conflitto con design system variabili

### 2. ❌ **Definizioni Duplicate**
- `fp-resv-fields--grid` definito in 3+ file diversi
- Valori gap diversi (16px vs 24px)
- Confusione su quale viene applicato

### 3. ❌ **Margin-bottom inconsistenti**
- `.fp-resv-field` vs `.fp-field`
- Variabili CSS vs valori hardcoded
- Mobile gap troppo stretto (8px)

### 4. ⚠️ **CSS Import Order**
```css
/* form.css */
@import './form/_variables-bridge.css';
@import './form-thefork-bw.css';  ← Ha le regole giuste

/* Ma poi form-thefork-bw.css importa: */
@import './form/_variables-thefork-bw.css';
/* Non importa _layout.css o altri! */
```

---

## ✅ Spacing Corretto (Design System)

Secondo form/_variables-thefork-bw.css:

```css
--fp-space-xs: 0.25rem;   /* 4px */
--fp-space-sm: 0.5rem;    /* 8px */
--fp-space-md: 1rem;      /* 16px */
--fp-space-lg: 1.5rem;    /* 24px */
--fp-space-xl: 2rem;      /* 32px */
--fp-space-2xl: 3rem;     /* 48px */
```

**Dovrebbe essere:**
- Gap grid desktop: `var(--fp-space-lg)` = 24px
- Gap grid mobile: `var(--fp-space-md)` = 16px
- Margin tra field: `var(--fp-space-lg)` = 24px
- Margin mobile: `var(--fp-space-md)` = 16px

**Invece è (con SPAZIATURE-AUMENTATE):**
- Gap grid desktop: 16px (ridotto!)
- Gap grid mobile: 8px (troppo stretto!)
- Margin tra field: 16px (ridotto!)
- Margin mobile: 8px (troppo stretto!)

---

## 🎯 Raccomandazioni

### 1. **Rimuovere/Disabilitare SPAZIATURE-AUMENTATE.css**
- Causa override forzati con !important
- Spacing troppo stretti
- Conflitto con design system

### 2. **Consolidare definizioni grid**
- Mantenere solo in form-thefork-bw.css
- Rimuovere da form/_layout.css (non viene usato)
- Un solo source of truth

### 3. **Usare variabili CSS coerenti**
- `var(--fp-space-lg)` per gap desktop
- `var(--fp-space-md)` per gap mobile
- No hardcoded 1rem/0.5rem

### 4. **Breakpoint standard**
- Mobile: < 640px
- Tablet: 640-1024px
- Desktop: > 1024px

---

**Status:** ⚠️ SPACING PROBLEMATICI  
**Causa:** SPAZIATURE-AUMENTATE.css con !important  
**Fix Necessario:** Rimuovere file o disabilitare import
