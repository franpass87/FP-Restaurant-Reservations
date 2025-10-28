# Diagnosi: ZIP con Versione Vecchia dell'Agenda

**Data:** 11 Ottobre 2025  
**Branch analizzato:** `cursor/check-build-and-composer-versions-18ea`

## 🔴 Problema Identificato

Il file `agenda-app.js` nello ZIP di distribuzione contiene una versione obsoleta perché le modifiche più recenti non sono state mergeate nel branch `main`.

## 📊 Analisi Dettagliata

### Stato del File `agenda-app.js`

| Branch | Commit | Data | Righe | Tipo |
|--------|--------|------|-------|------|
| `main` | `f956e6b` | Vecchio | ~256 | IIFE Pattern |
| `HEAD` | `137594a` | 11 Ott 2025 | 619+ | ES6 Class |

### Differenze

```bash
$ git diff main HEAD -- assets/js/admin/agenda-app.js | wc -l
1446 linee modificate
```

**Commit recenti NON in main:**
- ✅ `137594a` - Fix agenda and reservation creation (#133)
- ✅ `78dd3d8` - Watchdog service operation (#132)
- ✅ `53501d2` - Fix: Correct GitHub repo URL for plugin updates (#131)
- ... e altri 156+ commit!

## 🎯 Causa Radice

Il workflow GitHub `.github/workflows/build-zip.yml` genera lo ZIP **solo su merge a main**:

```yaml
on:
  pull_request:
    types: [closed]

jobs:
  build-zip:
    if: github.event.pull_request.merged == true && 
        github.event.pull_request.base.ref == 'main'
```

**Problema:** Le modifiche sono su branch feature ma non sono state ancora mergeate!

## ✅ Soluzioni

### Soluzione 1: Merge Immediato (RACCOMANDATO)

```bash
# 1. Verifica che tutti i test passino
npm test

# 2. Crea Pull Request verso main
git push origin cursor/check-build-and-composer-versions-18ea

# 3. Fai merge su GitHub
# Lo ZIP verrà generato automaticamente dal workflow

# 4. Scarica il nuovo ZIP da GitHub Actions artifacts
```

### Soluzione 2: Build Manuale Locale

```bash
# Genera lo ZIP manualmente dal branch corrente
bash build.sh --zip-name=fp-restaurant-reservations-latest.zip

# Lo ZIP sarà in: build/fp-restaurant-reservations-latest.zip
```

**Nota:** Questa soluzione richiede PHP installato localmente.

### Soluzione 3: Fix del Workflow (Lungo Termine)

Modificare il workflow per generare ZIP anche per branch feature:

```yaml
# .github/workflows/build-zip.yml
on:
  workflow_dispatch:  # Permette trigger manuale
  push:
    branches:
      - main
      - 'cursor/**'  # Build anche per branch cursor
```

## 🔍 Verifica del Problema

### Check 1: Versione su Main
```bash
git checkout main
head -10 assets/js/admin/agenda-app.js
# Output: (function() { ... })  ← Versione VECCHIA
```

### Check 2: Versione su Branch Corrente
```bash
git checkout cursor/check-build-and-composer-versions-18ea
head -10 assets/js/admin/agenda-app.js
# Output: class AgendaApp { ... }  ← Versione NUOVA
```

### Check 3: File nel Build
```bash
# Se hai il file ZIP scaricato
unzip -p fp-restaurant-reservations.zip \
  fp-restaurant-reservations/assets/js/admin/agenda-app.js | head -10
```

## 🚨 Problema Secondario: Composer Script

Il `composer.json` contiene comandi Windows che non funzionano su Linux/Mac:

```json
"scripts": {
    "build": [
        "if exist vendor rmdir /s /q vendor",  ← Comando Windows!
        "@composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader",
        "composer dump-autoload -o --classmap-authoritative"
    ]
}
```

**Fix:**
```json
"scripts": {
    "build": [
        "@composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader",
        "composer dump-autoload -o --classmap-authoritative"
    ]
}
```

Il `build.sh` già gestisce correttamente la rimozione/rebuild su tutte le piattaforme.

## 📋 Checklist Azione Immediata

- [ ] Verificare che tutte le modifiche siano committate
- [ ] Creare Pull Request verso `main`
- [ ] Far partire i test automatici
- [ ] Mergare su `main`
- [ ] Verificare che GitHub Actions generi il nuovo ZIP
- [ ] Scaricare e testare il nuovo ZIP
- [ ] Verificare che l'agenda funzioni correttamente

## 🎯 Prevenzione Futura

1. **Workflow di Release:** Considerare l'uso di tag per le release
2. **Staging Branch:** Usare un branch `develop` per test prima di `main`
3. **Versioning:** Incrementare la versione nel file principale ad ogni modifica importante
4. **CI/CD:** Aggiungere test automatici che verificano il contenuto del ZIP

## 📝 Note

- Il processo di build (`build.sh` e workflow GitHub) è **corretto**
- Il problema è **organizzativo**, non tecnico
- Non ci sono problemi con Composer o rsync
- I file vengono copiati correttamente, ma dal branch sbagliato

---

**Conclusione:** Il sistema di build funziona correttamente. Il problema è che stai scaricando lo ZIP generato dall'ultimo merge su `main`, che non contiene le modifiche recenti. Devi mergare le modifiche su `main` o generare lo ZIP manualmente dal branch corrente.
