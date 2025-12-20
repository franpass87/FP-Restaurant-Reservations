# 🚨 PROBLEMI CRITICI TEMPLATE PHP - 7° Controllo
**Data:** 3 Novembre 2025  
**Tipo:** Inline Styles, Accessibility, Semantic HTML

---

## ❌ **10 PROBLEMI CRITICI TROVATI**

### 1. **INLINE STYLES MASSIVI (Linee 339, 343, 349-360, 367-375)**

**PROBLEMA GRAVISSIMO:**
```html
<!-- Linea 339 - DISASTRO -->
<textarea style="width: 100%; padding: 12px 14px; border: 1.5px solid #e8e8e8; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #ffffff; color: #000000; transition: all 0.2s ease; font-family: inherit; resize: vertical;"></textarea>

<!-- Linea 343 - UGUALE -->
<textarea style="width: 100%; padding: 12px 14px; ..."></textarea>

<!-- Linee 349-360 - Checkbox e container -->
<div style="display: flex; flex-direction: column; gap: 12px; align-items: flex-start;">
<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
<input style="width: 16px; height: 16px; margin: 0; cursor: pointer;">
<span style="color: #1f2937;">...</span>

<!-- Linea 360 - High-chair input -->
<input style="width: 70px; padding: 8px 10px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 13px; background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%); color: #374151; ...">

<!-- Linee 367-375 - Privacy checkbox -->
<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
<input style="width: 16px; height: 16px; margin: 0; margin-top: 2px; cursor: pointer;">
<span style="color: #1f2937;">...</span>
<a style="color: #2563eb; text-decoration: underline;">...</a>
```

**PROBLEMI:**
1. Inline styles duplicati (textarea x2 identici)
2. Non si possono customizzare via CSS
3. Difficili da mantenere
4. Performance peggiore (no cache CSS)
5. No responsive (font-size 14px fisso, no 16px mobile iOS!)
6. Specificità altissima (difficile override)

**CORREZIONE:** Creare classi CSS pulite

---

### 2. **HIGH-CHAIR INPUT: Padding 8px < 13px minimo**

**Linea 360:**
```html
<input type="number" style="padding: 8px 10px;">
```

**CALCOLO HEIGHT:**
```
padding: 8px + 8px = 16px
font: 13px → 15.6px
border: 3px
TOTALE: 16 + 15.6 + 3 = 34.6px ❌❌❌
```

**GRAVISSIMO:** 34.6px < 44px WCAG minimum (-9.4px!)

---

### 3. **CHECKBOX 16px SENZA LABEL ASSOCIATION**

**Linee 351, 355:**
```html
<input type="checkbox" name="fp_resv_wheelchair_table" value="1" style="...">
```

**PROBLEMI:**
1. No ID
2. Label wrappa checkbox ma no `for=` attribute
3. Width/height 16px hardcoded inline
4. No min-width: 44px touch target!

**CORREZIONE:**
```html
<input type="checkbox" id="wheelchair-table" name="fp_resv_wheelchair_table">
<label for="wheelchair-table">Tavolo accessibile</label>
```

---

### 4. **PRIVACY POLICY LINK A "#" (Non funzionale)**

**Linea 369:**
```html
<a href="#" target="_blank">Privacy Policy</a>
```

**PROBLEMI:**
1. Link va a "#" (non funziona!)
2. target="_blank" senza rel="noopener"
3. Dovrebbe venire da $context['privacy']['policy_url']

---

### 5. **LABEL SENZA for= ATTRIBUTE**

**PROBLEMI:**
```html
<!-- Linea 188 -->
<label>Persone</label>  <!-- ❌ No for= -->

<!-- Linea 202 -->
<label>Orario</label>  <!-- ❌ No for= -->

<!-- Linea 348 -->
<label>Servizi Aggiuntivi</label>  <!-- ❌ No for= -->
```

**IMPATTO:** Screen reader non associano label a controllo

---

### 6. **MANCANO AUTOCOMPLETE**

**PROBLEMI:**
```html
<!-- Linea 326 - Occasion -->
<select id="occasion" name="fp_resv_occasion">
<!-- ❌ NO autocomplete -->

<!-- Linea 339 - Notes -->
<textarea id="notes" name="fp_resv_notes">
<!-- ❌ NO autocomplete -->

<!-- Linea 343 - Allergies -->
<textarea id="allergies" name="fp_resv_allergies">
<!-- ❌ NO autocomplete -->

<!-- Linea 360 - High-chair -->
<input type="number" id="high-chair-count">
<!-- ❌ NO autocomplete -->

<!-- Linea 274 - Phone prefix -->
<select name="fp_resv_phone_prefix">
<!-- ❌ NO autocomplete="tel-country-code" -->
```

**IMPATTO:** Browser non possono auto-compilare

---

### 7. **DISPLAY: NONE INLINE invece di HIDDEN**

**Linee 203, 209, 475, 477:**
```html
<div style="display: none;">...</div>
<button style="display: none;">...</button>
```

**PROBLEMA:**
- Inline style (non modificabile)
- Dovrebbe usare `hidden` attribute

**CORREZIONE:**
```html
<div hidden>...</div>
<button hidden>...</button>
```

---

### 8. **DEBUG BLOCK CON INLINE STYLE (Linea 108)**

```html
<div style="background:#f0f0f0;padding:10px;margin:10px 0;border:2px solid #333;font-size:11px;font-family:monospace;">
```

**PROBLEMI:**
1. Debug code in produzione (if WP_DEBUG è OK, ma style inline NO)
2. Font-size 11px (illeggibile, sotto WCAG!)
3. Inline style massiccio

---

### 9. **TEXTAREA ROWS="3" POTREBBE ESSERE TROPPO PICCOLO**

**Linee 339, 343:**
```html
<textarea rows="3">
```

**PROBLEMA:**
- 3 righe = ~45px height
- Per note/allergie potrebbe servire più spazio
- Dovrebbe essere 4-5 righe

---

### 10. **MANCANO aria-describedby SU ALCUNI CAMPI**

```html
<!-- Linea 170 - Date input -->
<input id="reservation-date" required>
<!-- ❌ NO aria-describedby -->

<!-- Linea 197 - Party size -->
<input id="party-size" required>
<!-- ❌ NO aria-describedby -->

<!-- Linea 326 - Occasion -->
<select id="occasion">
<!-- ❌ NO aria-describedby -->

<!-- Linea 339 - Notes -->
<textarea id="notes">
<!-- ❌ NO aria-describedby -->

<!-- Linea 343 - Allergies -->
<textarea id="allergies">
<!-- ❌ NO aria-describedby -->

<!-- Linea 351, 355, 368, 374 - Checkbox -->
<input type="checkbox">
<!-- ❌ NO aria-describedby -->
```

---

## 📊 **TABELLA PROBLEMI**

| # | Problema | Linee | Gravità | Impatto |
|---|----------|-------|---------|---------|
| 1 | Inline styles massivi | 339,343,349-375 | 🔴 CRITICAL | Manutenibilità, iOS zoom |
| 2 | High-chair padding 8px | 360 | 🔴 CRITICAL | WCAG fail (34.6px) |
| 3 | Checkbox no ID/label | 351,355,368,374 | 🔴 CRITICAL | A11y fail |
| 4 | Privacy link "#" | 369 | 🔴 CRITICAL | Non funzionale |
| 5 | Label no for= | 188,202,348 | 🟠 HIGH | A11y degraded |
| 6 | Manca autocomplete | 274,326,339,343,360 | 🟠 HIGH | UX degraded |
| 7 | display: none inline | 203,209,475,477 | 🟡 MEDIUM | Code quality |
| 8 | Debug inline style | 108 | 🟡 MEDIUM | Production leak |
| 9 | Textarea rows="3" | 339,343 | 🟡 MEDIUM | UX minor |
| 10 | Manca aria-describedby | 170,197,326,339,343 | 🟠 HIGH | A11y partial |

**CRITICAL:** 4  
**HIGH:** 3  
**MEDIUM:** 3

---

## 🎯 **IMPACT ANALYSIS**

### Inline Styles (CRITICAL)
- ❌ Textarea inline: **154 caratteri** di CSS duplicato
- ❌ Non responsive (font-size fisso 14px)
- ❌ iOS zoom problem (no 16px mobile)
- ❌ No customizzazione
- ❌ Performance overhead

### High-Chair Input (CRITICAL)
- ❌ 34.6px height (sotto 44px di -9.4px!)
- ❌ WCAG 2.5.5 Target Size FAIL
- ❌ Difficile cliccare/toccare

### Checkbox 16px (CRITICAL)
- ❌ Touch target 16px < 44px
- ❌ No label association (ID mancante)
- ❌ Screen reader non sanno cosa controllano

### Privacy Link (CRITICAL)
- ❌ Va a "#" (non funziona)
- ❌ Utenti non possono leggere privacy

---

## ✅ **CORREZIONI NECESSARIE**

### Priority 1: Rimuovere TUTTI inline styles
### Priority 2: High-chair input min-height 44px
### Priority 3: Checkbox con ID + label association
### Priority 4: Privacy link funzionale
### Priority 5: Autocomplete attributes
### Priority 6: aria-describedby completi

---

**Conclusione:** Il **template PHP ha 10 problemi gravi** che rendono inutili le correzioni CSS!

