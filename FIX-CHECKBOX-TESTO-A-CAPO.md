# 🔧 FIX: Testo Checkbox Va a Capo
**Data:** 3 Novembre 2025  
**Issue:** Il testo dei checkbox va a capo SOTTO il quadratino invece di stare ACCANTO

---

## 🐛 **PROBLEMA**

**ATTESO:**
```
☐ Accetto la Privacy Policy e il trattamento...
☐ Acconsento al trattamento dei dati...
```

**REALE:**
```
☐
Accetto la Privacy Policy e il trattamento...

☐
Acconsento al trattamento dei dati...
```

Il testo è su una **nuova riga sotto il checkbox** invece che **accanto**.

---

## ❌ **CAUSA**

Il tema **Salient** probabilmente sovrascrive con:

```css
/* Tema potrebbe forzare */
.fp-checkbox-wrapper {
    flex-direction: column;  /* ❌ Verticale invece orizzontale */
}

.fp-checkbox-wrapper label {
    display: block;  /* ❌ Block forza nuova riga */
}
```

---

## ✅ **CORREZIONE APPLICATA**

### Specificità MASSIMA + !important ovunque

```css
/* PRIMA (Tema sovrascriveva) */
.fp-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* DOPO (FORZA layout orizzontale) */
.fp-checkbox-wrapper {
    display: flex !important;
    flex-direction: row !important;  /* ✅ Orizzontale forzato */
    align-items: flex-start !important;
    gap: 10px !important;
}

.fp-checkbox-wrapper label {
    display: inline-block !important;  /* ✅ Inline, non block */
    flex: 1 !important;                /* ✅ Occupa spazio disponibile */
    line-height: 1.5 !important;
    padding-top: 1px !important;       /* ✅ Allineamento fine */
}
```

---

## 🎯 **PROPRIETÀ CHIAVE**

| Proprietà | Valore | Scopo |
|-----------|--------|-------|
| `flex-direction` | `row !important` | Checkbox e label ORIZZONTALI |
| `align-items` | `flex-start !important` | Allineamento top |
| `gap` | `10px !important` | Spazio tra checkbox e testo |
| `display` (label) | `inline-block !important` | Non va a capo |
| `flex` (label) | `1 !important` | Occupa spazio rimanente |
| `padding-top` (label) | `1px !important` | Allineamento fine con checkbox |

---

## ✅ **RISULTATO ATTESO**

```
☐ Accetto la Privacy Policy e il trattamento dei miei dati personali *
   (checkbox e testo sulla STESSA riga)

☐ Acconsento al trattamento dei dati per comunicazioni marketing (opzionale)
   (checkbox e testo sulla STESSA riga)
```

---

## 🧪 **TEST**

**Ricarica (CTRL+F5)** e verifica:

1. ✅ Checkbox a **SINISTRA**
2. ✅ Testo a **DESTRA** sulla **STESSA RIGA**
3. ✅ Gap 10px tra checkbox e testo
4. ✅ Testo allineato al top del checkbox

---

## 🔍 **SE ANCORA VA A CAPO**

Console browser (F12):

```javascript
const wrapper = document.querySelector('.fp-checkbox-wrapper');
const styles = window.getComputedStyle(wrapper);
console.log({
    display: styles.display,
    flexDirection: styles.flexDirection,
    alignItems: styles.alignItems
});
```

**Deve mostrare:**
```
display: "flex"
flexDirection: "row"  ← IMPORTANTE!
alignItems: "flex-start"
```

Se mostra `flexDirection: "column"` → tema ancora sovrascrive!

---

**Ricarica e dimmi se ora il testo sta ACCANTO al checkbox!** 🎯

