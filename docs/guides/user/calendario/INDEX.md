# 📅 Documentazione Calendario

Questa cartella contiene tutta la documentazione relativa al sistema di calendario del plugin.

---

## 📚 DOCUMENTI

### 1. Sistema Date Disponibili
- **[CALENDARIO-DATE-DISPONIBILI.md](../CALENDARIO-DATE-DISPONIBILI.md)** - Come funziona il sistema di date disabilitate (tecnico)

### 2. UX e Ottimizzazioni
- **[CALENDARIO-DATE-DISABILITATE-UX.md](CALENDARIO-DATE-DISABILITATE-UX.md)** - Ottimizzazioni UX implementate (colori, loading, tooltip)

---

## 🎯 OVERVIEW

Il sistema di calendario del plugin include:

### Funzionalità Base
1. **Date Disabilitate** - Le date non disponibili non sono cliccabili
2. **Filtro Servizio** - Date cambiano in base a pranzo/cena
3. **Range 90 Giorni** - Carica 3 mesi di date disponibili
4. **Timezone Italia** - Gestione corretta timezone Europe/Rome

### Ottimizzazioni UX (v0.9.0-rc8)
1. **Styling Evidenziato** - Verde/Grigio/Blu per chiarezza visiva
2. **Loading Indicator** - Feedback durante caricamento
3. **Tooltip Informativi** - Info al passaggio mouse
4. **Legenda Permanente** - Sempre visibile sotto il campo
5. **Error Handling** - Gestione errori con auto-hide

---

## 🎨 COLORI

| Colore | Significato | Cliccabile |
|--------|-------------|------------|
| 🟢 Verde chiaro | Disponibile | ✅ Sì |
| ⚪ Grigio barrato | Non disponibile | ❌ No |
| 🔵 Blu | Oggi | ✅ Se disponibile |
| 🟢 Verde pieno | Selezionata | ✅ Sì |

---

## 📁 STRUTTURA FILES

```
calendario/
├── INDEX.md                                    ← Questo file
├── CALENDARIO-DATE-DISABILITATE-UX.md         ← Ottimizzazioni UX
└── ../CALENDARIO-DATE-DISPONIBILI.md          ← Sistema tecnico

src/
└── Domain/Reservations/
    ├── AvailabilityService.php                ← Logica disponibilità
    └── REST.php                               ← Endpoint /available-days

assets/
├── css/
│   └── form.css                               ← Stili calendario
└── js/fe/
    └── onepage.js                             ← Logica JS calendario
```

---

## 🔗 LINK UTILI

### Per Utenti
- [Guida Uso Calendario](../CALENDARIO-DATE-DISPONIBILI.md#come-funziona-per-lutente)
- [Colori e Significati](CALENDARIO-DATE-DISABILITATE-UX.md#-legenda-colori-completa)

### Per Sviluppatori
- [Architettura Sistema](../CALENDARIO-DATE-DISPONIBILI.md#-architettura)
- [Modifiche UX](CALENDARIO-DATE-DISABILITATE-UX.md#-modifiche-applicate)
- [Testing](CALENDARIO-DATE-DISABILITATE-UX.md#-come-testare)

### Per Admin
- [Configurazione Backend](../../../ORARI-SERVIZIO.md)
- [Troubleshooting](../CALENDARIO-DATE-DISPONIBILI.md#troubleshooting)

---

**Ultimo aggiornamento:** 2 Novembre 2025  
**Versione:** 0.9.0-rc8

