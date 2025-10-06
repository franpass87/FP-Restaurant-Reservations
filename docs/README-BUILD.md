# Guida al Sistema di Build

## Panoramica

Questo progetto utilizza uno script di build automatizzato (`build.sh`) che crea un pacchetto ZIP pronto per la distribuzione del plugin WordPress.

## Processo di Build

### 1. Pre-requisiti

```bash
# Assicurarsi di avere installato:
- Node.js (per npm)
- PHP CLI
- Composer
- rsync
- zip
```

### 2. Esecuzione del Build

```bash
# Build standard (incrementa versione patch automaticamente)
./build.sh

# Build con versione specifica
./build.sh --set-version=1.2.3

# Build con incremento versione
./build.sh --bump=minor    # incrementa versione minor
./build.sh --bump=major    # incrementa versione major
./build.sh --bump=patch    # incrementa versione patch (default)

# Build con nome ZIP personalizzato
./build.sh --zip-name=mio-plugin.zip
```

### 3. Cosa Fa lo Script di Build

Lo script esegue automaticamente i seguenti passaggi:

1. **Bump della versione** (se richiesto)
2. **Build JavaScript**
   ```bash
   npm install --silent
   npm run build
   ```
   - Compila i file JavaScript con Vite
   - Crea versioni ottimizzate per produzione

3. **Installazione Dipendenze PHP**
   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   composer dump-autoload -o --classmap-authoritative
   ```

4. **Creazione Pacchetto**
   - Copia i file necessari nella directory `build/`
   - Esclude file di sviluppo e test
   - Crea un file ZIP pronto per la distribuzione

5. **Output**
   - Il file ZIP viene creato in `build/`
   - Viene mostrata la versione finale e il percorso del file

## File Inclusi nel Build

### ✅ Inclusi

- **Codice PHP**: Tutti i file `.php` in `src/`, `templates/`
- **JavaScript Compilato**: `assets/dist/fe/*.js` (versioni ottimizzate)
- **JavaScript Admin**: `assets/js/admin/*.js` (necessari per il pannello admin)
- **CSS**: Tutti i file in `assets/css/`
- **Vendor**: Dipendenze Composer (solo produzione)
- **Assets**: Font, immagini, altri asset statici
- **File principali**: `fp-restaurant-reservations.php`, `readme.txt`, `LICENSE`, ecc.

### ❌ Esclusi

- **File Sorgente JavaScript**: `assets/js/fe/` (solo sorgenti, non compilati)
- **Dipendenze npm**: `node_modules/`
- **Test**: `tests/`
- **Documentazione**: `docs/`, `*.md`
- **Config Development**:
  - `package.json`, `package-lock.json`
  - `vite.config.js`
  - `eslint.config.js`, `.eslintrc.cjs`
  - `.prettierrc.json`
  - `phpcs.xml`, `phpstan.neon`
- **Git**: `.git/`, `.github/`, `.gitignore`, `.gitattributes`
- **IDE**: `.idea/`, `.vscode/`
- **Build System**: `build.sh`, `scripts/`, `build/` (directory)

## Sistema JavaScript

### Compilazione

Il JavaScript viene compilato con **Vite** da:
- **Sorgente**: `assets/js/fe/onepage.js`
- **Output**: 
  - `assets/dist/fe/onepage.esm.js` (ES Module, ~61 KB)
  - `assets/dist/fe/onepage.iife.js` (IIFE/legacy, ~49 KB)

### Caricamento nel Plugin

Il plugin (`src/Frontend/WidgetController.php`) carica automaticamente:
1. Se esistono file compilati → usa `onepage.iife.js` (produzione)
2. Se non esistono → usa `onepage.js` sorgente (sviluppo/fallback)

### Sviluppo vs Produzione

**Sviluppo**:
```bash
npm run dev     # Watcher Vite per sviluppo
```

**Produzione**:
```bash
npm run build   # Compila e ottimizza per produzione
```

## Risoluzione Problemi

### Il JavaScript non funziona dopo il build

**Causa**: I file JavaScript non sono stati compilati prima della creazione dello ZIP.

**Soluzione**: Lo script di build ora esegue automaticamente `npm run build`. Assicurarsi di eseguire `./build.sh` e non creare manualmente lo ZIP.

### Dimensione ZIP troppo grande

**Causa**: Potrebbero essere inclusi file non necessari.

**Soluzione**: Verificare che le esclusioni in `build.sh` (righe 63-88) siano corrette.

### Errore "vite: not found"

**Causa**: Dipendenze npm non installate.

**Soluzione**:
```bash
npm install
npm run build
```

### Errore "php: command not found"

**Causa**: PHP CLI non installato.

**Soluzione**: Installare PHP CLI per il sistema operativo in uso.

## Workflow Consigliato

### Per Sviluppo
```bash
# 1. Clonare il repository
git clone ...

# 2. Installare dipendenze
npm install
composer install

# 3. Sviluppare con hot reload
npm run dev

# 4. Testare
npm run test
```

### Per Release
```bash
# 1. Committare tutte le modifiche
git add .
git commit -m "Ready for release"

# 2. Eseguire build
./build.sh --set-version=1.2.3

# 3. Lo ZIP è pronto in build/
ls -lh build/*.zip

# 4. Testare lo ZIP in ambiente pulito
# 5. Distribuire
```

## Note Importanti

1. **Non modificare manualmente la directory `build/`**: Viene ricreata ad ogni build
2. **I file in `assets/dist/`**: Sono generati automaticamente, non modificarli manualmente
3. **Versioning**: Il bump automatico modifica:
   - Header del file principale PHP
   - readme.txt
   - Altri file di metadata

## Modifiche Recenti (2024-10-06)

### Aggiunte al Build Process

✅ **Build automatico JavaScript**: Aggiunto `npm install` e `npm run build` allo script di build

✅ **Esclusione sorgenti frontend**: I file in `assets/js/fe/` non vengono più inclusi nello ZIP (solo le versioni compilate in `assets/dist/`)

✅ **Esclusione config eslint**: `eslint.config.js` ora escluso dal build di produzione

### Risultato

- ZIP più leggero (solo file necessari)
- JavaScript sempre aggiornato e ottimizzato
- Le ultime modifiche vengono sempre incluse
- Il form funziona correttamente dopo l'installazione dello ZIP
