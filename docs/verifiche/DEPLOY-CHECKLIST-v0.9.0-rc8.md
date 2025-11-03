# ✅ DEPLOY CHECKLIST - v0.9.0-rc8

**Versione:** 0.9.0-rc7 → 0.9.0-rc8  
**Data:** 2 Novembre 2025  
**Feature:** Ottimizzazioni UX Calendario

---

## 📦 FILES DA CARICARE

### Frontend Assets (2)
- [x] `assets/css/form.css` (+141 righe - stili calendario)
- [x] `assets/js/fe/onepage.js` (+60 righe - loading, tooltip, legenda)

### Core Files (2)
- [x] `fp-restaurant-reservations.php` (versione → 0.9.0-rc8)
- [x] `src/Core/Plugin.php` (VERSION → 0.9.0-rc8)

### Documentazione (1)
- [x] `CHANGELOG.md` (aggiunta release 0.9.0-rc8)

**Totale:** 5 files

---

## 🧪 PRE-DEPLOY CHECKS

### ✅ Sintassi
- [x] PHP sintassi OK (tutti i file)
- [x] JavaScript sintassi OK
- [x] Nessun linter error
- [x] Health check superato

### ✅ Versioning
- [x] Versione aggiornata a 0.9.0-rc8
- [x] VERSION const allineata
- [x] CHANGELOG aggiornato

### ✅ Compatibilità
- [x] Nessun breaking change
- [x] Backward compatible
- [x] Nessuna modifica DB
- [x] Cache auto-refresh

---

## 🚀 POST-DEPLOY TEST

### Test 1: Calendario Base
1. [ ] Vai su pagina con form prenotazioni
2. [ ] Clicca campo data
3. [ ] **Verifica:** Calendario si apre
4. [ ] **Verifica:** Vedi date con colori (verde/grigio/blu)

### Test 2: Colori e Stili
5. [ ] **Verifica:** Date NON disponibili sono grigie con pattern a righe
6. [ ] **Verifica:** Date NON disponibili hanno X rossa piccola
7. [ ] **Verifica:** Date NON disponibili sono barrate
8. [ ] **Verifica:** Date DISPONIBILI sono verde chiaro con bordo
9. [ ] **Verifica:** Data OGGI è blu evidenziata
10. [ ] **Verifica:** Legenda visibile sotto campo ("Verde = Disponibile...")

### Test 3: Loading Indicator
11. [ ] Ricarica pagina
12. [ ] **Verifica:** Vedi brevemente "⏳ Caricamento date disponibili..." (0.5s)
13. [ ] **Verifica:** Spinner rotante verde animato

### Test 4: Tooltip
14. [ ] Apri calendario
15. [ ] Passa mouse su data GRIGIA
16. [ ] **Verifica:** Tooltip "Data non disponibile"
17. [ ] Passa mouse su data VERDE
18. [ ] **Verifica:** Tooltip "Disponibile: cena" (o pranzo)

### Test 5: Interazioni
19. [ ] Prova cliccare data GRIGIA
20. [ ] **Verifica:** NON selezionabile (cursore "not-allowed")
21. [ ] Clicca data VERDE
22. [ ] **Verifica:** Si seleziona (diventa verde pieno)
23. [ ] Passa mouse su data VERDE
24. [ ] **Verifica:** Effetto zoom (scale 1.05)

### Test 6: Cambio Servizio
25. [ ] Seleziona "Pranzo"
26. [ ] **Verifica:** Loading indicator appare
27. [ ] **Verifica:** Date si aggiornano
28. [ ] Cambia a "Cena"
29. [ ] **Verifica:** Date diverse disponibili

### Test 7: Mobile
30. [ ] Apri su mobile/tablet
31. [ ] **Verifica:** Calendario funziona
32. [ ] **Verifica:** Stili visibili
33. [ ] **Verifica:** Legenda leggibile

### Test 8: Browser
34. [ ] Test Chrome/Edge
35. [ ] Test Firefox
36. [ ] Test Safari (se disponibile)

---

## 🎨 ESEMPIO VISIVO ATTESO

Quando apri il calendario dovresti vedere:

```
┌────────────────────────────┐
│   Seleziona Data           │
│                            │
│  [    20/11/2025    ]      │  ← Campo data
│                            │
│  📅 Legenda calendario:    │  ← Sempre visibile!
│  ● Verde = Disponibile     │
│  ● Grigio barrato = Non    │
│  ● Blu = Oggi              │
└────────────────────────────┘

Calendario aperto:
┌────────────────────────────┐
│     Novembre 2025          │
│                            │
│  L  M  M  G  V  S  D       │
│ ❌ ❌ ❌  4  5  6  7        │  ← Grigie barrate con X
│  8  9 10 11 12 13 14       │  ← Tutte verdi
│ 15 16 17 18 🔵 20 21       │  ← 19 = Oggi (blu)
│ 22 23 24 25 26 27 28       │
└────────────────────────────┘
```

---

## ❌ PROBLEMI COMUNI

### Problema: Colori non si vedono
**Causa:** Cache browser  
**Soluzione:** CTRL+F5 (hard refresh)

### Problema: Loading non appare
**Causa:** API troppo veloce (cache)  
**Soluzione:** Normale! Se API < 100ms non si vede

### Problema: Tooltip non funzionano
**Causa:** Conflitto JS  
**Soluzione:** Verifica console browser (F12)

### Problema: X rossa non appare
**Causa:** Conflitto CSS  
**Soluzione:** Verifica ispettore CSS (F12)

---

## 🔄 ROLLBACK (Se Necessario)

Se qualcosa non funziona, ripristina versione precedente:

```bash
# Ripristina questi 2 file:
assets/css/form.css        → Versione precedente (38 righe)
assets/js/fe/onepage.js    → Versione precedente (no loading/tooltip)
```

---

## 📊 METRICHE DA MONITORARE

Dopo deploy, monitora:

### UX Metrics
- [ ] Tasso errori prenotazione (dovrebbe diminuire)
- [ ] Tempo compilazione form (dovrebbe rimanere stabile)
- [ ] Conversioni (dovrebbero aumentare)

### Technical Metrics
- [ ] Console errors (dovrebbero essere 0)
- [ ] API response time /available-days (dovrebbe < 500ms)
- [ ] Page load time (dovrebbe rimanere stabile)

---

## 🎯 SUCCESS CRITERIA

Deploy considerato di successo se:

✅ Calendario si apre correttamente  
✅ Date colorate (verde/grigio/blu) visibili  
✅ Date grigie NON cliccabili  
✅ Date verdi cliccabili  
✅ Legenda sempre visibile  
✅ Loading indicator appare (se API > 100ms)  
✅ Tooltip funzionano  
✅ Nessun console error  
✅ Nessun errore PHP  
✅ Mobile funziona  

**Se tutti i criteri sono ✅ → DEPLOY OK! 🎉**

---

## 📞 SUPPORTO

### Se Serve Aiuto

**Documentazione completa:**
- `docs/guides/user/calendario/CALENDARIO-DATE-DISABILITATE-UX.md` (500+ righe)
- `docs/guides/user/calendario/INDEX.md`
- `RIEPILOGO-OTTIMIZZAZIONI-CALENDARIO.md`

**Quick reference:**
```css
/* Date NON disponibili */
.flatpickr-day.flatpickr-disabled {
    background: repeating-linear-gradient(...);  /* Pattern */
    text-decoration: line-through;               /* Barrato */
}

/* Date disponibili */
.flatpickr-day:not(.flatpickr-disabled) {
    background: #f0fdf4;  /* Verde */
}
```

---

## ✅ CHECKLIST FINALE

Prima di chiudere:

- [x] Files caricati (5)
- [x] Versione aggiornata (0.9.0-rc8)
- [x] CHANGELOG aggiornato
- [ ] Test calendario (10 step)
- [ ] Test mobile
- [ ] Test browser multipli
- [ ] Metriche monitorate

---

**Ready for deploy! 🚀**

**Versione:** 0.9.0-rc8  
**Status:** ✅ PRONTO  
**Rischio:** BASSO (solo CSS/JS frontend)  
**Rollback:** FACILE (2 files)

