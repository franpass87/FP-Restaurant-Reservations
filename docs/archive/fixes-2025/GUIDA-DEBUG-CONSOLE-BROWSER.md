# 🔍 Guida Debug Console Browser - Agenda FP Reservations

## FONDAMENTALE: Questa è la chiave per capire il problema!

### Come aprire la Console del Browser

1. **Vai alla pagina dell'agenda in WordPress**
   - WordPress Admin → Prenotazioni → Agenda

2. **Apri gli Strumenti Sviluppatore**
   - **Windows/Linux**: Premi `F12` oppure `Ctrl + Shift + I`
   - **Mac**: Premi `Cmd + Option + I`

3. **Vai al tab "Console"**
   - Clicca sulla tab "Console" (o "Consolle" in italiano)

### Cosa cercare nella Console

#### ✅ TUTTO OK - Dovresti vedere questi messaggi:

```javascript
[Agenda] 🚀 Inizializzazione nuova agenda...
[Agenda] 📊 Cambio vista: day
[Agenda] 📥 Caricamento dati... { date: "2025-10-12", view: "day" }
[Agenda] ✅ Dati caricati: 5 prenotazioni
[Agenda] 🎨 Rendering vista: day
```

#### ❌ PROBLEMI COMUNI - Cerca questi errori:

**1. Configurazione mancante:**
```javascript
Uncaught TypeError: Cannot read property 'restRoot' of undefined
// SOLUZIONE: Problema con wp_localize_script
```

**2. Endpoint non trovato:**
```javascript
GET http://tuosito.com/wp-json/fp-resv/v1/agenda 404 (Not Found)
// SOLUZIONE: Vai su Impostazioni > Permalink > Salva modifiche
```

**3. Permessi negati:**
```javascript
GET http://tuosito.com/wp-json/fp-resv/v1/agenda 403 (Forbidden)
// SOLUZIONE: Verifica permessi utente o ricarica pagina (nonce scaduto)
```

**4. File JavaScript non trovato:**
```javascript
GET http://tuosito.com/wp-content/plugins/.../agenda-app.js 404 (Not Found)
// SOLUZIONE: npm run build
```

**5. Nessun messaggio [Agenda]:**
```javascript
// Console completamente vuota
// SOLUZIONE: JavaScript non è caricato o non è eseguito
```

### Test Manuale nella Console

**Test 1: Verifica configurazione**
```javascript
console.log(window.fpResvAgendaSettings);
```

**Risultato atteso:**
```javascript
{
  restRoot: "http://tuosito.com/wp-json/fp-resv/v1",
  nonce: "abc123def456...",
  activeTab: "agenda",
  links: {...},
  strings: {...}
}
```

**Se vedi "undefined"**: Problema con PHP che non passa la configurazione

---

**Test 2: Verifica endpoint API manuale**
```javascript
fetch(window.fpResvAgendaSettings.restRoot + '/agenda?date=2025-10-12', {
  headers: {
    'X-WP-Nonce': window.fpResvAgendaSettings.nonce
  }
})
.then(r => r.json())
.then(d => console.log('Risposta API:', d))
.catch(e => console.error('Errore API:', e));
```

**Risultato atteso:**
```javascript
Risposta API: {
  meta: {...},
  stats: {...},
  data: {...},
  reservations: [...]
}
```

**Se vedi errore 404/403/500**: Problema lato server

---

**Test 3: Verifica elementi DOM**
```javascript
console.log({
  datePicker: !!document.querySelector('[data-role="date-picker"]'),
  viewButtons: document.querySelectorAll('[data-action="set-view"]').length,
  dayView: !!document.querySelector('[data-view="day-timeline"]'),
  weekView: !!document.querySelector('[data-view="week-grid"]'),
  monthView: !!document.querySelector('[data-view="month-calendar"]')
});
```

**Risultato atteso:**
```javascript
{
  datePicker: true,
  viewButtons: 4,
  dayView: true,
  weekView: true,
  monthView: true
}
```

**Se vedi "false" o 0**: Template HTML non caricato correttamente

---

## 📸 Come condividere i risultati

1. **Fai screenshot della console** con tutti i messaggi visibili
2. **Oppure copia tutto il testo** dalla console:
   - Click destro nella console → Salva con nome
   - Oppure seleziona tutto e copia

3. **Condividi:**
   - Screenshot
   - Testo copiato
   - Output dei test manuali sopra

---

## 🔧 Soluzioni Rapide per Problemi Comuni

### Problema: "fpResvAgendaSettings is not defined"
**Causa**: JavaScript caricato prima del wp_localize_script
**Soluzione**:
1. Verifica che AdminController.php carichi lo script correttamente
2. Controlla che il plugin sia attivo
3. Svuota cache del browser (Ctrl+Shift+R)

### Problema: "404 Not Found" sull'endpoint
**Causa**: Permalink non rigenerati
**Soluzione**:
1. WordPress Admin → Impostazioni → Permalink
2. Clicca "Salva modifiche" (senza cambiare nulla)
3. Ricarica la pagina agenda

### Problema: "403 Forbidden" sull'endpoint
**Causa**: Permessi o nonce scaduto
**Soluzione**:
1. Verifica di essere amministratore o avere ruolo con permessi
2. Ricarica la pagina (Ctrl+Shift+R) per ottenere nuovo nonce
3. Verifica che REST API sia abilitata

### Problema: Console completamente vuota
**Causa**: JavaScript non caricato
**Soluzione**:
1. Verifica che file `assets/js/admin/agenda-app.js` esista
2. Esegui `npm run build` nella cartella del plugin
3. Verifica che non ci siano errori di compilazione

### Problema: "Network Error" o "Failed to fetch"
**Causa**: REST API disabilitata o firewall
**Soluzione**:
1. Verifica che `/wp-json/` funzioni: apri `http://tuosito.com/wp-json/`
2. Controlla .htaccess per regole che bloccano API
3. Verifica firewall o plugin sicurezza

---

## 💡 Prossimi Passi

Dopo aver eseguito questi test, condividi:

1. ✅ Output della console (screenshot o testo)
2. ✅ Risultati dei 3 test manuali
3. ✅ Output di `DIAGNOSTICA-AGENDA-COMPLETA.php`

Con queste informazioni potrò **identificare esattamente** il problema e darti la **soluzione definitiva**!

---

**Creato**: 2025-10-12
**Tipo**: Guida Debug
**Priorità**: MASSIMA (questo è il passo fondamentale)

