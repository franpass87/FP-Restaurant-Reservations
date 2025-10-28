# 🎯 Processo Build ZIP - Configurazione Definitiva

**Data:** 11 Ottobre 2025  
**Stato:** ✅ CONFIGURATO E FUNZIONANTE

## 🏗️ Sistema di Build Aggiornato

Ho completamente ristrutturato il sistema di build per garantire che **lo ZIP venga sempre creato correttamente** con tutti i file aggiornati.

## 📋 Workflow Attivi

### 1. `build-artifact.yml` - Build Automatica su Ogni Push

**Trigger:** Ogni push su qualsiasi branch  
**Scopo:** Generare ZIP di test per verificare le modifiche

**Funzionalità:**
- ✅ Build automatica su OGNI push (qualsiasi branch)
- ✅ Verifica file critici (plugin principale, agenda-app.js, vendor, assets)
- ✅ **Controllo versione agenda-app.js** - Verifica che contenga `class AgendaApp` (versione ES6)
- ✅ Nome ZIP con versione, branch e timestamp
- ✅ Artifact disponibile per 30 giorni
- ✅ Trigger manuale abilitato (workflow_dispatch)

**Come usarlo:**
```bash
# ZIP generato automaticamente ad ogni push
git push origin tuo-branch

# O trigger manuale da GitHub Actions
# Actions → Build plugin ZIP on every push → Run workflow
```

**Download Artifact:**
1. Vai su GitHub Actions
2. Seleziona l'ultima run del workflow
3. Scarica "plugin-zip-[branch]-[commit]"

### 2. `deploy-on-merge.yml` - Release Automatica su Main

**Trigger:** Push su branch `main`  
**Scopo:** Creare release ufficiali su GitHub

**Funzionalità:**
- ✅ Attivato solo su push/merge a `main`
- ✅ Estrae versione dal file plugin
- ✅ Controlla se la release esiste già (skip se sì)
- ✅ Verifica build completa prima di pubblicare
- ✅ **Verifica agenda-app.js aggiornato**
- ✅ Crea GitHub Release con note dettagliate
- ✅ Upload ZIP come asset della release
- ✅ Artifact disponibile per 90 giorni

**Come usarlo:**
```bash
# 1. Merge su main (automatico o via PR)
git checkout main
git merge tuo-branch
git push origin main

# 2. Vai su GitHub Releases
# 3. Troverai la nuova release v0.1.X
```

### 3. Altri Workflow

- `build-plugin-zip.yml` - **DISABILITATO** (legacy)
- `release-zip.yml` - **DISABILITATO** (legacy)
- `ci.yml` - Test automatici (non toccato)

## 🔍 Verifiche Automatiche Integrate

Ogni build esegue queste verifiche per garantire l'integrità:

### 1. File Critici
```bash
✅ fp-restaurant-reservations.php
✅ assets/js/admin/agenda-app.js
✅ vendor/ (dipendenze Composer)
✅ assets/dist/ (assets compilati)
```

### 2. Versione Agenda-App.js
```bash
# Verifica che contenga la versione ES6 aggiornata
grep -q "class AgendaApp" agenda-app.js
```

Se la verifica fallisce, il workflow si interrompe con errore! 🚫

### 3. Dimensione e Contenuto
```bash
# Mostra statistiche del file
📊 Dimensione agenda-app.js: XXX righe
📦 Dimensione ZIP: XX MB
```

## 📦 Cosa Viene Incluso nello ZIP

### ✅ File Inclusi
- `fp-restaurant-reservations.php` - File principale
- `src/` - Codice sorgente PHP
- `assets/` - CSS, JS compilati, immagini
  - `assets/js/admin/agenda-app.js` ← **VERIFICATO**
  - `assets/dist/` - Build produzione
  - `assets/css/` - Stili
- `vendor/` - Dipendenze Composer (solo production)
- `templates/` - Template PHP
- `languages/` - Traduzioni
- `uninstall.php` - Script disinstallazione

### ❌ File Esclusi
- `.git/`, `.github/` - Git
- `tests/` - Test
- `docs/` - Documentazione
- `node_modules/` - Dipendenze NPM
- `*.md` - File markdown
- `.idea/`, `.vscode/` - IDE
- `build/` - Directory build
- `package.json`, `composer.json` - Config dev
- `phpcs.xml`, `phpstan.neon` - Linting
- `scripts/`, `tools/` - Script sviluppo
- `assets/js/fe/` - Sorgenti frontend (incluso solo dist)

## 🚀 Come Generare un Build

### Opzione 1: Build Automatica (Consigliata)

**Per Test:**
```bash
git add .
git commit -m "feat: La mia modifica"
git push origin mio-branch
```
→ ZIP disponibile in GitHub Actions dopo ~3-5 minuti

**Per Release:**
```bash
# Crea PR e mergia su main
gh pr create --title "Release v0.1.X" --base main
gh pr merge
```
→ Release automatica su GitHub dopo ~5 minuti

### Opzione 2: Build Locale

```bash
# Usa lo script build.sh
bash build.sh --zip-name=plugin-test.zip

# ZIP in: build/plugin-test.zip
```

### Opzione 3: Trigger Manuale

1. Vai su GitHub → Actions
2. Seleziona "Build plugin ZIP on every push"
3. Click "Run workflow"
4. Seleziona branch
5. Click "Run workflow"

## 🔧 Risoluzione Problemi

### Problema: "agenda-app.js sembra essere una versione vecchia"

**Causa:** Il branch contiene ancora la versione IIFE invece della classe ES6

**Soluzione:**
```bash
# Verifica il contenuto
head -20 assets/js/admin/agenda-app.js

# Dovrebbe mostrare:
# class AgendaApp {
#     constructor() {

# Se vedi invece:
# (function() {
#     'use strict';

# Allora hai la versione vecchia!
```

Assicurati che le modifiche dell'agenda siano nel branch prima del build.

### Problema: "File principale mancante"

**Causa:** Build non completato correttamente

**Soluzione:**
1. Verifica che `composer install` e `npm run build` completino senza errori
2. Controlla i log del workflow GitHub Actions
3. Riprova il build

### Problema: "ZIP troppo grande"

**Causa:** File non esclusi correttamente

**Soluzione:**
1. Verifica che `node_modules/` sia escluso
2. Controlla che `assets/js/fe/` sia escluso (solo dist incluso)
3. Rimuovi file di log e temporanei

## 📊 Metriche di Build Attese

### Tempi
- **Build completo:** 3-5 minuti
- **Composer install:** 30-60 secondi
- **NPM build:** 20-40 secondi
- **Creazione ZIP:** 5-10 secondi

### Dimensioni
- **ZIP finale:** ~2-5 MB
- **Directory vendor:** ~1-2 MB
- **Assets compilati:** ~500 KB
- **agenda-app.js:** ~600-700 righe (versione ES6)

### Artifact Retention
- **Build branch:** 30 giorni
- **Release main:** 90 giorni

## ✅ Checklist Pre-Release

Prima di mergare su main:

- [ ] Tutti i test passano (`npm test`)
- [ ] Linting OK (`npm run lint:js`, `npm run lint:php`)
- [ ] Build locale funziona (`bash build.sh`)
- [ ] `agenda-app.js` contiene `class AgendaApp`
- [ ] Versione incrementata in `fp-restaurant-reservations.php`
- [ ] CHANGELOG aggiornato (se presente)
- [ ] Nessun file di debug o log committato

## 🎯 Benefici della Nuova Configurazione

### Prima ❌
- Build solo su merge a main
- Nessuna verifica contenuto
- ZIP poteva contenere versioni vecchie
- Debug difficile

### Ora ✅
- Build su ogni push (test immediato)
- Verifica automatica file critici
- **Controllo versione agenda-app.js**
- Release automatiche
- Trigger manuale disponibile
- Summary dettagliato in GitHub Actions
- Nomi file descrittivi (branch + versione + timestamp)

## 📱 Notifiche e Monitoring

### GitHub Actions
- Ricevi email su fallimento build
- Summary visibile direttamente in Actions
- Badge di stato disponibile

### Come Aggiungere Badge
```markdown
![Build Status](https://github.com/[username]/[repo]/workflows/Build%20plugin%20ZIP%20on%20every%20push/badge.svg)
```

## 🔄 Processo Completo End-to-End

```
1. Sviluppo
   ↓
2. git push origin feature-branch
   ↓
3. GitHub Actions: build-artifact.yml
   ├─ Composer install
   ├─ NPM build
   ├─ Verifica file
   ├─ Verifica agenda-app.js ⭐
   └─ Crea ZIP di test
   ↓
4. Download ZIP da Artifacts (test locale)
   ↓
5. Crea PR → merge su main
   ↓
6. GitHub Actions: deploy-on-merge.yml
   ├─ Build completo
   ├─ Verifica integrità
   ├─ Verifica agenda-app.js ⭐
   ├─ Crea GitHub Release
   └─ Upload ZIP asset
   ↓
7. ZIP finale disponibile in Releases! 🎉
```

## 🆘 Supporto

### Log Utili
```bash
# Workflow GitHub Actions
# Actions → Seleziona run → Click su step → Espandi log

# Build locale
bash build.sh --zip-name=test.zip

# Verifica contenuto ZIP
unzip -l build/test.zip | grep agenda-app.js
unzip -p build/test.zip */agenda-app.js | head -20
```

### File di Configurazione
- `.github/workflows/build-artifact.yml` - Build ogni push
- `.github/workflows/deploy-on-merge.yml` - Release su main
- `build.sh` - Script build locale
- `composer.json` - Dipendenze PHP (fixato!)
- `package.json` - Build frontend

## 🎉 Conclusione

Il sistema di build è ora **completamente automatizzato** e **verificato**:

✅ ZIP creato correttamente ad ogni push  
✅ Verifica automatica file critici  
✅ **Controllo versione agenda-app.js integrato**  
✅ Release automatiche su main  
✅ Trigger manuali disponibili  
✅ Documentazione completa  

**Non dovrai più preoccuparti di avere versioni vecchie nello ZIP!** 🚀

---

**Ultimo aggiornamento:** 11 Ottobre 2025  
**Versione configurazione:** 2.0 (definitiva)
