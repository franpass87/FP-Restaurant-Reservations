# 🚀 Guida Deployment per Git Updater

Per far funzionare il plugin con **Git Updater**, le dipendenze Composer (`vendor/`) devono essere incluse nel repository Git.

## ✅ Setup Iniziale

```bash
# 1. Installa le dipendenze
composer install --no-dev --prefer-dist

# 2. Aggiungi vendor/ al repository
git add vendor/

# 3. Commit
git commit -m "Include vendor dependencies for Git Updater"

# 4. Push
git push
```

## 📦 Build per Release

Quando crei una nuova release:

```bash
# Lo script build.sh include automaticamente vendor/ nel pacchetto ZIP
bash build.sh --bump=patch
```

Il file ZIP generato in `build/` include già `vendor/` e può essere caricato come GitHub Release.

## ⚠️ Importante

- **NON** fare `git add vendor/` se stai usando `.gitignore` standard (esclude vendor/)
- **SÌ** fai `git add vendor/` se hai modificato `.gitignore` per includere vendor/ (come fatto in questo progetto)
- Le dipendenze DEV non sono necessarie in produzione, usa `--no-dev`

## 🔄 Aggiornamento Dipendenze

Se aggiorni `composer.json`:

```bash
# 1. Aggiorna dipendenze
composer update --no-dev --prefer-dist

# 2. Aggiungi al repository
git add vendor/ composer.lock

# 3. Commit e push
git commit -m "Update Composer dependencies"
git push
```

