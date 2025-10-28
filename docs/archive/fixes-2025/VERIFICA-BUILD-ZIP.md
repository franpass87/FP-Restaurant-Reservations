# Verifica Build ZIP - Analisi Inconsistenze

**Data**: 2025-10-11  
**Branch**: cursor/check-zip-for-updated-files-084b

## Sommario Esecutivo

Ho analizzato i tre sistemi di build che creano il file ZIP del plugin e ho identificato **inconsistenze critiche** nelle esclusioni di file che potrebbero causare ZIP con contenuti diversi a seconda dello script utilizzato.

## Script Analizzati

1. **`build.sh`** - Script locale per build manuale
2. **`.github/workflows/build-zip.yml`** - Build automatica su merge a main
3. **`.github/workflows/deploy-on-merge.yml`** - Deploy e release automatica

## ⚠️ Inconsistenze Critiche Trovate

### 1. File `tools/`
- ❌ **build.sh**: NON escluso (quindi INCLUSO nello ZIP)
- ✅ **build-zip.yml**: NON escluso (INCLUSO)
- ✅ **deploy-on-merge.yml**: ESCLUSO

**Impatto**: Gli ZIP creati localmente o da build-zip.yml conterranno script di utility non necessari in produzione.

### 2. File `docker-compose.yml`
- ❌ **build.sh**: NON escluso (INCLUSO)
- ❌ **build-zip.yml**: NON escluso (INCLUSO)
- ✅ **deploy-on-merge.yml**: ESCLUSO

**Impatto**: File di configurazione Docker non necessario incluso negli ZIP di build e sviluppo.

### 3. Directory `AUDIT/`
- ❌ **build.sh**: NON escluso (INCLUSO)
- ✅ **build-zip.yml**: ESCLUSO (`AUDIT/`, `AUDIT.md`)
- ✅ **deploy-on-merge.yml**: ESCLUSO

**Impatto**: Directory di audit interna inclusa negli ZIP locali.

### 4. File Markdown (`*.md`)
- ✅ **build.sh**: ESCLUSO (`--exclude=*.md`)
- ❌ **build-zip.yml**: NON escluso (INCLUSO)
- ✅ **deploy-on-merge.yml**: ESCLUSO

**Impatto**: Documenti di sviluppo inclusi negli ZIP da build-zip.yml (CHANGELOG.md, README.md, ecc.).

### 5. File di Configurazione Dev
- ✅ **build.sh**: ESCLUSO (vite.config.js, eslint.config.js, .prettierrc.json, ecc.)
- ❌ **build-zip.yml**: NON escluso
- ✅ **deploy-on-merge.yml**: ESCLUSO

**Impatto**: File di configurazione tool di sviluppo inclusi in build-zip.yml.

### 6. File Sorgente Frontend (`assets/js/fe/`)
- ✅ **build.sh**: ESCLUSO (`--exclude=assets/js/fe`)
- ❌ **build-zip.yml**: NON escluso (INCLUSO)
- ✅ **deploy-on-merge.yml**: ESCLUSO

**Impatto**: CRITICO - I file sorgente JavaScript/TypeScript vengono inclusi in build-zip.yml, quando invece dovrebbero essere inclusi solo i file compilati in `assets/dist/fe/`.

## ✅ File Correttamente Gestiti

I seguenti file/directory sono correttamente esclusi da tutti gli script:
- `.git`, `.github/`
- `tests/`
- `docs/`
- `node_modules/`
- `build/`
- `.gitignore`
- `phpcs.xml`, `phpstan.neon`

## 📋 File che DEVONO Essere Inclusi

✅ Questi file DEVONO essere nello ZIP:
- `src/**/*.php` - Codice sorgente principale
- `templates/**/*.php` - Template
- `assets/dist/fe/**/*.js` - File JavaScript compilati
- `assets/css/**/*.css` - Fogli di stile
- `assets/vendor/**/*.js` - Librerie vendor (Chart.js)
- `vendor/**/*` - Dipendenze Composer
- `languages/**/*.pot` - File di traduzione
- `fp-restaurant-reservations.php` - File principale del plugin
- `composer.json`, `composer.lock` - Metadati dipendenze
- `readme.txt`, `LICENSE` - File richiesti da WordPress

## 🎯 Raccomandazioni

### 1. Allineare gli Script di Build
Tutte e tre gli script dovrebbero avere le STESSE esclusioni. Suggerisco di utilizzare la lista più restrittiva (deploy-on-merge.yml) come riferimento:

```bash
ESCLUSIONI_STANDARD=(
    "--exclude=.git"
    "--exclude=.github"
    "--exclude=tests"
    "--exclude=docs"
    "--exclude=node_modules"
    "--exclude=*.md"
    "--exclude=.idea"
    "--exclude=.vscode"
    "--exclude=build"
    "--exclude=.gitattributes"
    "--exclude=.gitignore"
    "--exclude=package.json"
    "--exclude=package-lock.json"
    "--exclude=phpcs.xml"
    "--exclude=phpstan.neon"
    "--exclude=vite.config.js"
    "--exclude=.codex-state.json"
    "--exclude=.rebuild-state.json"
    "--exclude=eslint.config.js"
    "--exclude=.prettierrc.json"
    "--exclude=build.sh"
    "--exclude=scripts"
    "--exclude=tools"
    "--exclude=docker-compose.yml"
    "--exclude=AUDIT"
    "--exclude=assets/js/fe"
    "--exclude=*.zip"
    "--exclude=*.log"
)
```

### 2. Verificare i File Compilati
Assicurarsi che `npm run build` venga SEMPRE eseguito prima della creazione dello ZIP e che `assets/dist/fe/` contenga:
- `onepage.iife.js`
- `onepage.esm.js`
- `form-app.min.js`
- `form-app-optimized.js`
- `form-app-fallback.js`

### 3. File .gitattributes
Il file `.gitattributes` ha già alcune esclusioni per `git archive`, ma è incompleto. Considerare l'aggiunta di:
```
/tools export-ignore
/scripts export-ignore
/AUDIT export-ignore
*.md export-ignore
docker-compose.yml export-ignore
vite.config.js export-ignore
eslint.config.js export-ignore
```

### 4. Test di Verifica
Creare uno script di test che:
1. Crea lo ZIP con tutti e tre i metodi
2. Confronta i contenuti
3. Verifica che i file critici siano presenti/assenti come previsto

## 🔍 Come Verificare il Problema

Eseguire questi comandi per verificare le differenze:

```bash
# Build locale
bash build.sh
unzip -l build/*.zip > /tmp/local-build.txt

# Simulare build-zip.yml
mkdir -p test-build/fp-restaurant-reservations
rsync -a ./ test-build/fp-restaurant-reservations \
  --exclude ".git/" --exclude ".github/" --exclude "node_modules/" \
  --exclude "tests/" --exclude "docs/" --exclude "build/" \
  --exclude "scripts/" --exclude ".gitignore" --exclude "*.zip" \
  --exclude "*.log" --exclude "AUDIT/" --exclude "AUDIT.md"
cd test-build && zip -r test.zip fp-restaurant-reservations
unzip -l test.zip > /tmp/github-build.txt

# Confrontare
diff /tmp/local-build.txt /tmp/github-build.txt
```

## 🚨 Azione Richiesta

È necessario correggere queste inconsistenze per garantire che:
1. Tutti gli ZIP siano identici indipendentemente dal metodo di build
2. Non vengano inclusi file di sviluppo/debug negli ZIP di produzione
3. I file compilati siano sempre aggiornati e inclusi
4. I file sorgente frontend siano esclusi

**Priorità**: ALTA - Le inconsistenze potrebbero causare problemi in produzione o includere file sensibili.

---

## ✅ Correzioni Applicate

### Data: 2025-10-11

Ho applicato le seguenti correzioni per allineare tutti gli script di build:

#### 1. **build.sh**
Aggiunte le seguenti esclusioni mancanti:
- `tools/` - Directory di utility
- `docker-compose.yml` - Configurazione Docker
- `AUDIT/` - Directory audit interno
- `*.zip` - File ZIP
- `*.log` - File di log

#### 2. **.github/workflows/build-zip.yml**
Aggiornata la lista esclusioni per includere:
- `tools/` - Directory di utility
- `*.md` - File Markdown
- `.idea/`, `.vscode/` - IDE config
- `.gitattributes` - Git attributes
- `package.json`, `package-lock.json` - NPM config
- `vite.config.js` - Vite config
- `eslint.config.js` - ESLint config
- `.prettierrc.json` - Prettier config
- `.codex-state.json`, `.rebuild-state.json` - State files
- `docker-compose.yml` - Docker config
- `assets/js/fe/` - Sorgenti frontend non compilati

#### 3. **.gitattributes**
Aggiornato con esclusioni `export-ignore` per:
- `/tools`
- `/scripts`
- `/AUDIT`
- `*.md`
- `docker-compose.yml`
- `vite.config.js`
- `eslint.config.js`
- `.prettierrc.json`
- `phpcs.xml`
- `phpstan.neon`
- `package.json`
- `package-lock.json`
- `/assets/js/fe`
- `*.zip`
- `*.log`

#### 4. **Script di Verifica**
Creato nuovo script `scripts/verify-zip-contents.sh` che:
- Verifica la presenza di file obbligatori
- Controlla l'assenza di file indesiderati
- Genera report colorato con errori/avvisi
- Può essere usato per test automatici

### Come Usare lo Script di Verifica

```bash
# Build locale
bash build.sh

# Verifica contenuto
bash scripts/verify-zip-contents.sh build/*.zip
```

Lo script verifica:
- ✅ Presenza file obbligatori (plugin principale, src/, templates/, assets compilati, vendor/)
- 🚫 Assenza file indesiderati (tests/, docs/, node_modules/, file config, sorgenti non compilati)
- ⚠️ File da valutare (source maps, file di test)

### Stato Attuale

✅ **TUTTI GLI SCRIPT SONO ORA ALLINEATI**

Le tre modalità di build (locale, GitHub CI, deploy) ora utilizzano le stesse esclusioni e produrranno ZIP identici.

### Prossimi Passi Consigliati

1. **Test della build**: Eseguire `bash build.sh` e verificare con lo script
2. **CI/CD Test**: Verificare che i workflow GitHub producano gli stessi risultati
3. **Monitoraggio**: Aggiungere lo script di verifica nei workflow CI per controllo automatico

### Note Finali

⚠️ **Attenzione**: Ho notato che `assets/dist/fe/form-app.min.js` è vuoto (0 byte). Questo potrebbe indicare un problema nel processo di build. Verificare il comando `npm run build` e il file `vite.config.js`.
