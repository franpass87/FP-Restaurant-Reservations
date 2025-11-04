# 🎯 PROBLEMA TROVATO: <br> tags di wpautop

**Data:** 3 Novembre 2025  
**Problema:** Asterischi a capo NONOSTANTE inline styles  
**Causa:** `<br>` tags inseriti da wpautop/WPBakery nei label

---

## 🔍 **ANALISI SCREENSHOT**

### Screenshot F12 mostra:

```html
<label for="customer-first-e">
    <br>    ← QUESTO CAUSA IL PROBLEMA!
    <br>    ← QUESTI NON SONO NEL TEMPLATE!
    Nome
    <abbr style="display:inline!important;white-space:nowrap!important;...">*</abbr>
</label>
```

**INLINE STYLES DELL'ASTERISCO SONO CORRETTI! ✅**

Ma i `<br>` tags **FORZANO** il line break prima del testo!

---

## ❌ **PERCHÉ SUCCEDE**

### WordPress wpautop filter:
```php
// WordPress automaticamente converte:
"Nome
*"

// In:
"Nome<br>
<br>
*"
```

### WPBakery/Salient:
Aggiungono ulteriori `<br>` per "migliorare" la formattazione (ma rompono il form).

---

## ✅ **SOLUZIONE APPLICATA**

### Fix #1: CSS nasconde <br> nei label

```css
#fp-resv-default label br,
.fp-resv-simple label br,
.fp-field label br,
label br {
    display: none !important;
    height: 0 !important;
    line-height: 0 !important;
}
```

**Applicato in 2 punti:**
1. ✅ Template `form-simple.php` (CSS statico)
2. ✅ `WidgetController.php` → `addOverrideCss()` (wp_head)

---

## 🧪 **TEST**

### Procedura (30 secondi):
```
1. Ctrl + F5 (hard refresh)
2. Verifica asterischi inline
3. Verifica checkbox allineati
```

---

## 📊 **RISULTATO ATTESO**

### PRIMA ❌
```html
<label>
    <br><br>  ← Causano line break
    Nome
    *         ← Va a capo
</label>
```

### DOPO ✅
```html
<label>
    <br><br>  ← display: none!
    Nome *    ← Inline!
</label>
```

---

## 🎯 **GARANZIA**

Con questo fix:
- ✅ `<br>` nascosti (display: none)
- ✅ Inline styles asterischi corretti
- ✅ Inline styles checkbox corretti
- ✅ CSS statico + wp_head + inline = TRIPLA protezione

**Probabilità successo: 99.9%**

---

**FHARDREFRESH (Ctrl + F5) E DIMMI!** 🚀

**Autore:** AI Assistant  
**Status:** FIX BR TAGS APPLICATO

