# ✅ Checklist Verifiche Build

## Modifiche Applicate

### 1. Script build.sh aggiornato

✅ Aggiunto `npm install --silent` (riga 54)
✅ Aggiunto `npm run build` (riga 55)
✅ Aggiunto `--exclude=eslint.config.js` (riga 83)
✅ Aggiunto `--exclude=assets/js/fe` (riga 87)

### 2. Documentazione creata

✅ README-BUILD.md - Guida completa al sistema di build
✅ VERIFICHE_BUILD.md - Questo file

## Come Verificare che Tutto Funzioni

### Verifica Locale (Prima del Build)

```bash
# 1. Controllare che i file compilati esistano
ls -lh assets/dist/fe/
# Dovresti vedere:
# - onepage.esm.js  (~61 KB)
# - onepage.iife.js (~49 KB)

# 2. Verificare che contengano il codice corretto
head -1 assets/dist/fe/onepage.iife.js | grep "FPResv"
# Dovrebbe mostrare "FPResv" (il nome globale della libreria)

# 3. Controllare la sintassi dello script di build
bash -n build.sh
# Non dovrebbe mostrare errori
```

### Eseguire il Build

```bash
# Con ambiente configurato (PHP, Composer, rsync, zip):
./build.sh

# Il processo dovrebbe:
# 1. Installare dipendenze npm
# 2. Compilare JavaScript con Vite
# 3. Installare dipendenze Composer
# 4. Creare la directory build/
# 5. Copiare i file necessari
# 6. Creare lo ZIP
```

### Verificare il Contenuto dello ZIP

```bash
# Estrarre e verificare il contenuto dello ZIP
cd build
unzip -l fp-restaurant-reservations-*.zip | grep "assets/"

# Dovresti vedere:
# ✅ assets/dist/fe/onepage.iife.js (file compilato)
# ✅ assets/dist/fe/onepage.esm.js (file compilato)
# ✅ assets/js/admin/*.js (file admin)
# ✅ assets/css/*.css (tutti i CSS)

# NON dovresti vedere:
# ❌ assets/js/fe/*.js (sorgenti frontend esclusi)
# ❌ node_modules/ (dipendenze npm escluse)
```

### Test dopo Installazione

1. **Carica lo ZIP su WordPress**
   - Vai in WordPress Admin → Plugin → Aggiungi nuovo
   - Carica il file ZIP
   - Attiva il plugin

2. **Verifica Console Browser**
   - Apri una pagina con il form di prenotazione
   - Apri Developer Tools (F12)
   - Vai nella tab Console
   - Dovresti vedere:
     ```
     [FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active
     [FP-RESV] Found widgets: 1
     [FP-RESV] Widget initialized: nome-widget
     ```

3. **Verifica Funzionalità JavaScript**
   - **Test navigazione step**: Clicca sui pulsanti "Avanti/Indietro" tra i vari step
   - **Test selezione meal**: Clicca sui pulsanti dei servizi (pranzo/cena)
   - **Test selezione data**: Seleziona una data
   - **Test selezione party**: Seleziona numero persone
   - **Test slot disponibilità**: Verifica che gli orari disponibili vengano caricati
   - **Test form submission**: Compila e invia il form

4. **Verifica Files Caricati**
   - In Developer Tools → Network tab
   - Ricarica la pagina
   - Cerca `onepage.iife.js`
   - Dovresti vedere che viene caricato da `assets/dist/fe/onepage.iife.js`
   - **Dimensione attesa**: ~49 KB
   - **Status**: 200 OK

## Problemi Comuni e Soluzioni

### Problema: JavaScript non funziona dopo installazione ZIP

**Causa Possibile**: Il file compilato non è presente nello ZIP

**Soluzione**:
1. Verifica che `npm run build` sia stato eseguito prima della creazione dello ZIP
2. Controlla che `assets/dist/fe/` non sia nella lista exclude del build.sh
3. Riesegui `./build.sh`

**Verifica**:
```bash
# Controlla contenuto ZIP
unzip -l build/*.zip | grep "assets/dist/fe"
# Dovresti vedere i file .js
```

### Problema: Errori in console "FPResv is not defined"

**Causa**: Il file JavaScript non viene caricato o è corrotto

**Soluzione**:
1. Verifica in Network tab che il file venga caricato
2. Controlla che non ci siano errori 404
3. Controlla che il file non sia vuoto
4. Ricompila con `npm run build` e ricrea lo ZIP

### Problema: "npm: command not found" durante build

**Causa**: Node.js/npm non installato

**Soluzione**:
```bash
# Installare Node.js (versione LTS raccomandata)
# Ubuntu/Debian:
sudo apt-get install nodejs npm

# Verificare installazione:
node --version
npm --version
```

### Problema: Widget inizializzato ma form non risponde

**Causa Possibile**: Conflitto con altro JavaScript o jQuery

**Verifica**:
1. Apri Console e cerca errori JavaScript
2. Verifica che non ci siano conflitti con altri plugin
3. Prova a disattivare temporaneamente altri plugin

**Soluzione**:
- Verifica versione jQuery compatibile
- Controlla che non ci siano script che bloccano l'inizializzazione

## Log di Debug Utili

### Console Browser

Quando il form funziona correttamente, dovresti vedere:

```
[FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active
[FP-RESV] Found widgets: 1
[FP-RESV] Widget initialized: fp-reservations-widget
[FP-RESV] Navigation click: next section: date
[FP-RESV] Navigation click: next section: party
# ... ecc
```

### Network Tab

File caricato correttamente:
```
Request URL: .../wp-content/plugins/fp-restaurant-reservations/assets/dist/fe/onepage.iife.js
Status Code: 200 OK
Content-Type: application/javascript
Content-Length: ~49000
```

## Risultato Atteso

Dopo aver seguito queste verifiche:

✅ JavaScript compilato presente nello ZIP
✅ File caricato correttamente nel browser
✅ Widget inizializzato senza errori
✅ Form funzionante e interattivo
✅ Navigazione tra step funzionante
✅ Selezione orari e invio form funzionante

## Contatti e Supporto

Se riscontri problemi dopo aver seguito queste verifiche:

1. Controlla i log della console browser
2. Verifica il contenuto dello ZIP
3. Assicurati che tutte le dipendenze siano installate
4. Ricompila con `npm run build` pulito

## Comandi Utili

```bash
# Pulizia completa e rebuild
rm -rf node_modules assets/dist build
npm install
npm run build
./build.sh

# Verifica veloce file compilati
ls -lh assets/dist/fe/ && head -1 assets/dist/fe/onepage.iife.js

# Test build senza creare ZIP
rsync -avn --exclude-from=<(grep exclude build.sh | cut -d'"' -f2) . /tmp/test-build/
```
