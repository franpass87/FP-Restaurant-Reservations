# 🚨 FIX URGENTE: Checkbox Invisibili
**Data:** 3 Novembre 2025  
**Issue:** I checkbox non si vedono affatto - solo campi input vuoti

---

## 🐛 **PROBLEMA CRITICO**

**ATTESO:**
```
☐ Accetto la Privacy Policy *
☐ Acconsento al marketing (opzionale)
```

**REALE:**
```
[campo vuoto] Accetto la Privacy Policy *
[campo vuoto] Acconsento al marketing
```

I checkbox sono **COMPLETAMENTE INVISIBILI**!

---

## ❌ **CAUSA**

Il tema **Salient** o altri CSS sovrascrivono gli stili checkbox con specificità più alta.

**Problemi possibili:**
1. Tema nasconde checkbox con `opacity: 0`
2. Tema setta `width: 0; height: 0`
3. Tema usa `position: absolute; left: -9999px`
4. Stili `.fp-checkbox` non applicati (specificità bassa)

---

## ✅ **CORREZIONE APPLICATA**

### Specificità MASSIMA + Tutti i !important

**PRIMA:**
```css
.fp-field input.fp-checkbox {
    width: 20px !important;
    /* ... */
}
```

**DOPO:**
```css
/* SPECIFICITÀ MASSIMA - 4 selettori combinati */
.fp-resv-simple .fp-field input[type="checkbox"].fp-checkbox,
.fp-resv-simple input[type="checkbox"].fp-checkbox,
.fp-field input[type="checkbox"].fp-checkbox,
input[type="checkbox"].fp-checkbox {
    width: 20px !important;
    height: 20px !important;
    opacity: 1 !important;           /* ✅ Forza visibilità */
    visibility: visible !important;  /* ✅ Forza visibilità */
    display: inline-block !important; /* ✅ Forza display */
    position: relative !important;    /* ✅ Previeni absolute */
    z-index: 1 !important;           /* ✅ Sopra altri elementi */
    vertical-align: middle !important;
    /* ... altri */
}
```

### Proprietà critiche aggiunte:
- ✅ `opacity: 1 !important` - Forza visibilità
- ✅ `visibility: visible !important` - Forza visibilità
- ✅ `display: inline-block !important` - Forza display
- ✅ `position: relative !important` - Previene position absolute
- ✅ `z-index: 1 !important` - Sopra altri elementi
- ✅ `vertical-align: middle !important` - Allineamento

---

## 🎯 **COSA HO FATTO**

1. ✅ Aggiunto `input[type="checkbox"]` al selettore (specificità +++)
2. ✅ Aggiunto 4 varianti del selettore (massima coverage)
3. ✅ Aggiunto `opacity: 1 !important`
4. ✅ Aggiunto `visibility: visible !important`
5. ✅ Aggiunto `display: inline-block !important`
6. ✅ Aggiunto `z-index: 1 !important`
7. ✅ Tutti gli stili con `!important`

---

## 🧪 **TEST**

**Ricarica pagina** (CTRL+F5) e verifica:

1. ✅ Checkbox devono essere **VISIBILI** (quadratini 20x20px)
2. ✅ **Non** devono essere campi input rettangolari
3. ✅ Quando clicchi: diventano **neri con ✓ bianco**
4. ✅ Label sulla stessa riga

---

## 🔍 **SE ANCORA NON SI VEDONO**

Apri console e copia:

```javascript
const checkbox = document.querySelector('.fp-checkbox');
const styles = window.getComputedStyle(checkbox);
console.log({
    width: styles.width,
    height: styles.height,
    opacity: styles.opacity,
    visibility: styles.visibility,
    display: styles.display,
    position: styles.position
});
```

**Deve mostrare:**
```
width: "20px"
height: "20px"
opacity: "1"
visibility: "visible"
display: "inline-block"
position: "relative"
```

Se mostra altro, c'è un CSS del tema che sovrascrive con specificità ancora più alta!

---

**Ricarica e dimmi se ora i checkbox si vedono!** 🎯

