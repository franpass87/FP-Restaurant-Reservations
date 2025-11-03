# 📅 Calendario Date Disponibili - Guida Utente

**Plugin:** FP Restaurant Reservations  
**Funzionalità:** Disabilitazione automatica date non disponibili

---

## 🎯 COSA FA IL CALENDARIO

Il calendario mostra **solo le date disponibili** per prenotazioni, disabilitando automaticamente:
- ❌ Date senza orari configurati
- ❌ Giorni di chiusura
- ❌ Date nel passato
- ❌ Giorni senza il servizio selezionato (pranzo/cena)

---

## 🖱️ COME FUNZIONA

### 1. Apri il Form di Prenotazione

Il form si trova nella pagina dove hai inserito lo shortcode `[fp_reservations]`

---

### 2. Seleziona il Servizio (Opzionale)

Se hai configurato più servizi (es. Pranzo, Cena):
- Seleziona prima il servizio
- Il calendario si aggiornerà automaticamente

---

### 3. Clicca sul Campo Data

Il calendario Flatpickr si apre mostrando:

```
✅ Date DISPONIBILI    → Cliccabili (sfondo bianco/verde)
❌ Date NON DISPONIBILI → Grigie, non cliccabili, stile barrato
```

---

### 4. Seleziona una Data Disponibile

- Solo le date **verdi/bianche** sono cliccabili
- Le date **grigie** non possono essere selezionate

---

## 🎨 COME RICONOSCERE LE DATE

### ✅ Date Disponibili
```
Aspetto:
- Sfondo chiaro/verde chiaro
- Testo nero leggibile
- Cliccabile (cursore pointer)
- Bordo verde al passaggio mouse
```

### ❌ Date Non Disponibili
```
Aspetto:
- Sfondo grigio chiaro
- Testo grigio sbiadito
- NON cliccabile (cursore not-allowed)
- Possibile linea di cancellazione
```

### 📅 Data Odierna
```
Aspetto:
- Bordo colorato/evidenziato
- Se disponibile: cliccabile
- Se non disponibile: grigio come le altre
```

---

## ❓ DOMANDE FREQUENTI

### "Perché tutte le date sono grigie?"

**Possibili cause:**

1. **Nessun orario configurato**
   - Soluzione: Vai in Admin → Restaurant Manager → Impostazioni
   - Configura "Orari di Servizio"

2. **Servizio selezionato chiuso**
   - Es: Hai selezionato "Pranzo" ma il pranzo non è configurato
   - Soluzione: Seleziona "Cena" o configura il pranzo

3. **Periodo di chiusura**
   - Il ristorante è in chiusura per il periodo visualizzato
   - Soluzione: Verifica le chiusure programmate

---

### "Alcune date che dovrebbero essere disponibili sono grigie"

**Verifica:**

1. **Orari configurati per quel giorno**
   - Es: Lunedì ha orari configurati?
   - Admin → Impostazioni → `mon=19:00-23:00`

2. **Chiusure eccezionali**
   - Verifica se hai impostato chiusure
   - Admin → Chiusure

3. **Meal plan**
   - Verifica configurazione meal plan
   - Admin → Impostazioni → Meal Plans

---

### "Il calendario non si aggiorna quando cambio servizio"

**Soluzione:**
- Ricarica la pagina
- Verifica che JavaScript sia attivo
- Controlla console browser (F12)

---

## 🔧 CONFIGURAZIONE BACKEND

### Per Rendere Date Disponibili:

1. **Configura Orari di Servizio**
   ```
   Admin → Restaurant Manager → Impostazioni
   
   Esempio:
   mon=19:00-23:00
   tue=19:00-23:00
   wed=19:00-23:00
   thu=19:00-23:00
   fri=19:00-23:30
   sat=12:30-15:00|19:00-23:30
   sun=12:30-15:00
   ```

2. **Verifica Meal Plans** (se usi pranzo/cena separati)
   ```
   Admin → Impostazioni → Meal Plans
   
   Esempio:
   pranzo|Pranzo|12:00-15:00
   cena|Cena|19:00-23:00
   ```

3. **Salva e Testa**
   - Salva impostazioni
   - Apri il form
   - Verifica che le date siano ora disponibili

---

## 💡 TIPS

### Date Weekend
Se vuoi aprire solo sabato/domenica:
```
sat=12:00-15:00|19:00-23:00
sun=12:00-15:00|19:00-23:00
```
Le altre date saranno automaticamente grigie!

### Solo Cena
Se apri solo a cena:
```
mon=19:00-23:00
tue=19:00-23:00
wed=19:00-23:00
thu=19:00-23:00
fri=19:00-23:30
sat=19:00-23:30
sun=chiuso
```

### Chiusure Temporanee
Per disabilitare un periodo:
```
Admin → Chiusure → Aggiungi Chiusura
Data inizio: 24/12/2025
Data fine: 26/12/2025
```
Le date 24, 25, 26 dicembre saranno automaticamente grigie!

---

## 🎯 COMPORTAMENTO ATTESO

### Scenario 1: Hai solo "Cena" configurata

```
Calendario mostra:
✅ Martedì 5 Nov (cena configurata)
✅ Mercoledì 6 Nov (cena configurata)
❌ Lunedì 4 Nov (cena non configurata)
```

### Scenario 2: Hai "Pranzo" e "Cena"

**Selezioni "Pranzo":**
```
✅ Sabato 9 Nov (pranzo: 12:00-15:00)
✅ Domenica 10 Nov (pranzo: 12:00-15:00)
❌ Lunedì 11 Nov (pranzo non configurato)
```

**Selezioni "Cena":**
```
✅ Lunedì 11 Nov (cena: 19:00-23:00)
✅ Martedì 12 Nov (cena: 19:00-23:00)
❌ (nessuna se non configurata)
```

**Il calendario si aggiorna automaticamente!** 🎉

---

## 🚨 TROUBLESHOOTING

### Problema: "Tutte grigie"

1. Verifica orari configurati
2. Test API: `/wp-json/fp-resv/v1/available-days?from=oggi&to=+90`
3. Controlla console browser (F12)

### Problema: "Tutte cliccabili"

1. Verifica che JavaScript sia attivo
2. Controlla che Flatpickr si carichi
3. Verifica console per errori

### Problema: "Date sbagliate"

1. Verifica timezone: Europe/Rome
2. Controlla configurazione orari
3. Verifica mapping giorni settimana

---

## 📚 DOCUMENTAZIONE

### Completa
👉 [PIANO-CALENDARIO-DATE-DISABILITATE.md](../../PIANO-CALENDARIO-DATE-DISABILITATE.md)

### Sistema Slot
👉 [SLOT-TIMES-SYSTEM.md](../SLOT-TIMES-SYSTEM.md)

### Configurazione
👉 [MEALS-CONFIGURATION.md](../MEALS-CONFIGURATION.md)

---

## ✅ CONCLUSIONE

**Non devi fare nulla!** Il sistema di disabilitazione date è:
- ✅ Già implementato
- ✅ Già funzionante
- ✅ Già ottimizzato
- ✅ Già testato

**Devi solo:**
1. Configurare gli orari nel backend
2. Testare il form
3. Goderti il calendario intelligente! 🎉

---

**Creato:** 2 Novembre 2025  
**Status:** ✅ Sistema già presente e funzionante

