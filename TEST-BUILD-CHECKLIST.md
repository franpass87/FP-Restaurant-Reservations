# ✅ Checklist Test Build - Guida Rapida

## Pre-Build Checklist

Esegui questi controlli PRIMA di creare il build:

```bash
# 1. Verifica che i file compilati esistano e siano recenti
ls -lh assets/dist/fe/
# Dovresti vedere onepage.iife.js e onepage.esm.js

# 2. Se non esistono o sono vecchi, ricompila
npm run build

# 3. Verifica sintassi build.sh
bash -n build.sh
# Non deve mostrare errori

# 4. Controlla che npm e vite siano installati
npm --version
npx vite --version
```

## Durante il Build

```bash
# Esegui il build con output dettagliato
./build.sh 2>&1 | tee build-log.txt

# Il processo dovrebbe mostrare:
# ✓ npm install completato
# ✓ vite build completato
# ✓ composer install completato
# ✓ rsync completato
# ✓ zip creato
```

## Post-Build Checklist

### 1. Verifica contenuto ZIP

```bash
# Vai nella directory build
cd build

# Lista il contenuto dello ZIP
unzip -l fp-restaurant-reservations-*.zip > zip-contents.txt

# Verifica file JavaScript
grep "assets/dist/fe" zip-contents.txt
# DEVE mostrare: onepage.iife.js e onepage.esm.js

grep "assets/js/fe" zip-contents.txt
# NON DEVE mostrare nulla (sorgenti esclusi)

# Verifica che node_modules NON sia incluso
grep "node_modules" zip-contents.txt
# NON DEVE mostrare nulla
```

### 2. Test Installazione WordPress (Simulato)

```bash
# Estrai lo ZIP in una directory temporanea
unzip fp-restaurant-reservations-*.zip -d /tmp/plugin-test

# Verifica struttura
tree -L 3 /tmp/plugin-test/fp-restaurant-reservations/ | head -50

# Verifica file JavaScript
cat /tmp/plugin-test/fp-restaurant-reservations/assets/dist/fe/onepage.iife.js | head -1
# Dovrebbe iniziare con: (function(){"use strict";
```

### 3. Test JavaScript

```bash
# Verifica che il bundle sia valido
node -e "console.log('Testing JavaScript syntax...')"
node --check /tmp/plugin-test/fp-restaurant-reservations/assets/dist/fe/onepage.iife.js
# Non dovrebbe mostrare errori di sintassi

# Verifica presenza global FPResv
grep -o "window.FPResv" /tmp/plugin-test/fp-restaurant-reservations/assets/dist/fe/onepage.iife.js | head -1
# Dovrebbe mostrare: window.FPResv
```

## Test su WordPress Reale

### 1. Backup

```bash
# SEMPRE fare backup prima di installare
# - Database WordPress
# - Directory wp-content/plugins/
```

### 2. Installazione

1. WordPress Admin → Plugin → Aggiungi nuovo
2. Carica il file ZIP
3. Attiva il plugin
4. Verifica che non ci siano errori PHP

### 3. Test Funzionalità

**Crea una pagina di test:**

```
[fp_reservations]
```

**Apri la pagina con Chrome DevTools:**

1. F12 → Console tab
2. Ricarica la pagina
3. Verifica messaggi console

**✅ Messaggi CORRETTI:**
```
[FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active
[FP-RESV] Found widgets: 1
[FP-RESV] Widget initialized: ...
```

**❌ Messaggi di ERRORE da investigare:**
```
Uncaught ReferenceError: FPResv is not defined
Failed to load resource: .../onepage.iife.js 404
Uncaught SyntaxError: Unexpected token
```

### 4. Test Interazioni Form

Testa TUTTE queste funzionalità:

- [ ] Selezione servizio (meal) - i pulsanti rispondono al click
- [ ] Selezione data - il date picker si apre
- [ ] Selezione numero persone - il campo accetta input
- [ ] Caricamento orari disponibili - gli slot vengono mostrati
- [ ] Selezione orario - il click funziona
- [ ] Navigazione step - i pulsanti Avanti/Indietro funzionano
- [ ] Inserimento dati personali - i campi accettano input
- [ ] Validazione telefono - mostra errori se numero non valido
- [ ] Validazione email - mostra errori se email non valida
- [ ] Checkbox consenso privacy - il click funziona
- [ ] Pulsante invio - diventa attivo quando form valido
- [ ] Invio form - mostra messaggio successo/errore

### 5. Test Browser Multipli

Testa almeno su:

- [ ] Chrome/Edge (browser moderni)
- [ ] Firefox
- [ ] Safari (se disponibile)
- [ ] Mobile browser (Chrome Android/Safari iOS)

### 6. Test Network

In DevTools → Network tab:

1. Filtra per JS
2. Ricarica pagina
3. Trova `onepage.iife.js`

**Verifica:**
- [ ] Status: 200 OK
- [ ] Size: ~49 KB
- [ ] Type: application/javascript
- [ ] Non ci sono errori 404

## Problemi Comuni e Soluzioni Rapide

### JavaScript non si carica

```bash
# 1. Verifica che il file esista nello ZIP
unzip -l build/*.zip | grep onepage.iife.js

# 2. Se manca, ricompila
npm run build
./build.sh

# 3. Reinstalla il plugin su WordPress
```

### Form non risponde

```bash
# Controlla console browser per errori
# Cerca messaggi "[FP-RESV]"
# Se non ci sono → JavaScript non inizializzato

# Soluzioni:
# 1. Svuota cache browser (Ctrl+Shift+R)
# 2. Svuota cache WordPress
# 3. Verifica che non ci siano conflitti con altri plugin
```

### Errori di sintassi JavaScript

```bash
# Ricompila con fresh install
rm -rf node_modules assets/dist
npm install
npm run build
./build.sh
```

## Checklist Finale Prima del Rilascio

- [ ] Tutte le funzionalità testate e funzionanti
- [ ] Nessun errore in console browser
- [ ] Form invia prenotazioni correttamente
- [ ] Email di conferma inviate
- [ ] Testato su almeno 2 browser diversi
- [ ] Testato su mobile
- [ ] Documentazione aggiornata
- [ ] Version number incrementato
- [ ] Changelog aggiornato

## Metriche di Successo

**Build**
- ✅ ZIP size < 5 MB
- ✅ JavaScript build < 100 KB
- ✅ No file development nel ZIP

**Runtime**
- ✅ Pagina carica in < 3 secondi
- ✅ Form risponde immediatamente
- ✅ No errori JavaScript in console

**Funzionalità**
- ✅ 100% delle funzionalità testate funzionano
- ✅ Validazione form funziona correttamente
- ✅ Invio prenotazione funziona

## Script Test Automatico

```bash
#!/bin/bash
# test-plugin.sh - Script di test rapido

echo "Testing plugin build..."

# Test 1: File esistono
[ -f "assets/dist/fe/onepage.iife.js" ] && echo "✓ IIFE exists" || echo "✗ IIFE missing"
[ -f "assets/dist/fe/onepage.esm.js" ] && echo "✓ ESM exists" || echo "✗ ESM missing"

# Test 2: File non vuoti
[ -s "assets/dist/fe/onepage.iife.js" ] && echo "✓ IIFE not empty" || echo "✗ IIFE empty"

# Test 3: Contiene codice atteso
grep -q "FPResv" assets/dist/fe/onepage.iife.js && echo "✓ Contains FPResv" || echo "✗ Missing FPResv"

# Test 4: Build.sh configurato correttamente
grep -q "npm run build" build.sh && echo "✓ build.sh runs npm" || echo "✗ build.sh missing npm"

echo ""
echo "Run './build.sh' to create production ZIP"
```

Salva questo script come `test-plugin.sh`, rendilo eseguibile con `chmod +x test-plugin.sh` e eseguilo prima di ogni build.
