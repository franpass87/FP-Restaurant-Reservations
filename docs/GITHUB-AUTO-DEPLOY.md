# Sistema di Deployment Automatico GitHub → WordPress

## Panoramica

Il plugin è ora configurato per il deployment automatico su GitHub e l'auto-aggiornamento su WordPress quando si effettua un merge sulla branch `main`.

## Come Funziona

### 1. Workflow GitHub Actions (`.github/workflows/deploy-on-merge.yml`)

Quando effettui un **merge su main** (o push diretto su main), il workflow automaticamente:

1. ✅ Estrae la versione corrente dal file `fp-restaurant-reservations.php`
2. ✅ Verifica se esiste già una release con quella versione
3. ✅ Se la release non esiste:
   - Installa le dipendenze (Composer e NPM)
   - Crea il build ottimizzato del plugin
   - Genera il file ZIP `fp-restaurant-reservations-X.Y.Z.zip`
   - Crea una **GitHub Release** con tag `vX.Y.Z`
   - Carica il file ZIP come asset della release

### 2. Sistema di Auto-aggiornamento WordPress

Il plugin include ora la libreria **Plugin Update Checker** di Yahnis Elsts che:

- 🔍 Controlla automaticamente se ci sono nuove release su GitHub
- 📦 Mostra la notifica di aggiornamento nel pannello WordPress
- ⬇️ Permette l'installazione diretta dalla dashboard di WordPress
- 🔄 Funziona come un normale aggiornamento di plugin WordPress

## Configurazione Repository GitHub

### Requisiti

1. Il repository deve essere **pubblico** oppure devi configurare un token di accesso
2. Le GitHub Actions devono avere i permessi per creare release

### Permessi GitHub Actions

Il workflow richiede il permesso `contents: write` che è già configurato. Verifica che sia abilitato:

1. Vai su **Settings** → **Actions** → **General**
2. Sotto "Workflow permissions" seleziona **Read and write permissions**
3. Salva le modifiche

## Come Usare il Sistema

### Processo di Deployment

1. **Aggiorna la versione** del plugin:
   ```bash
   # Modifica manualmente la versione in fp-restaurant-reservations.php
   # oppure usa lo script bump-version:
   php tools/bump-version.php --patch  # 0.1.9 → 0.1.10
   php tools/bump-version.php --minor  # 0.1.9 → 0.2.0
   php tools/bump-version.php --major  # 0.1.9 → 1.0.0
   ```

2. **Committa le modifiche**:
   ```bash
   git add fp-restaurant-reservations.php
   git commit -m "Bump version to X.Y.Z"
   ```

3. **Pusha su main** (o fai merge di una pull request):
   ```bash
   git push origin main
   ```

4. **Il workflow si attiva automaticamente**:
   - Vai su **Actions** nel repository GitHub
   - Vedrai il workflow "Deploy plugin on merge to main" in esecuzione
   - Dopo qualche minuto, la release sarà pubblicata

5. **Verifica la release**:
   - Vai su **Releases** nel repository GitHub
   - Dovresti vedere la nuova release `vX.Y.Z` con il file ZIP allegato

### Auto-aggiornamento su WordPress

Una volta pubblicata la release su GitHub:

1. **Su WordPress**, vai su **Dashboard** → **Aggiornamenti**
2. Il plugin **FP Restaurant Reservations** mostrerà la notifica di aggiornamento disponibile
3. Clicca su **Aggiorna** per installare automaticamente la nuova versione
4. WordPress scaricherà il file ZIP da GitHub e installerà l'aggiornamento

## Repository Privato (Opzionale)

Se il repository è **privato**, devi configurare un token di accesso:

1. **Crea un Personal Access Token** su GitHub:
   - Vai su **Settings** → **Developer settings** → **Personal access tokens** → **Tokens (classic)**
   - Genera un nuovo token con scope `repo`
   - Copia il token

2. **Modifica il file del plugin** (`fp-restaurant-reservations.php`):
   ```php
   $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
       'https://github.com/francescopasseri/fp-restaurant-reservations/',
       __FILE__,
       'fp-restaurant-reservations'
   );
   
   // Aggiungi autenticazione per repository privato
   $updateChecker->setAuthentication('TUO_TOKEN_GITHUB');
   
   $updateChecker->getVcsApi()->enableReleaseAssets();
   ```

## Vantaggi del Sistema

✅ **Deployment automatico**: Nessuna azione manuale richiesta  
✅ **Versionamento controllato**: Le release sono create solo quando cambia la versione  
✅ **Tracciabilità**: Ogni release è associata a un commit specifico  
✅ **Distribuzione semplice**: Gli utenti ricevono gli aggiornamenti direttamente da WordPress  
✅ **Nessun costo**: Non serve WordPress.org o servizi esterni  

## Risoluzione Problemi

### La release non viene creata

- **Verifica che la versione sia cambiata** rispetto all'ultima release
- **Controlla i permessi** delle GitHub Actions (vedi sopra)
- **Vedi i log** del workflow in **Actions** → **Deploy plugin on merge to main**

### WordPress non mostra l'aggiornamento

- **Verifica che le dipendenze Composer siano installate** nel plugin
- **Controlla che il file `vendor/autoload.php` esista** nel plugin installato
- **Verifica la connettività** del sito WordPress verso GitHub
- **Attiva WP_DEBUG** per vedere eventuali errori nel log

### Il download fallisce

- **Verifica che il file ZIP sia stato caricato** nella release GitHub
- **Per repository privati**, configura il token di accesso (vedi sopra)
- **Verifica la dimensione del file ZIP**: deve essere < 100 MB

## File Modificati

- ✅ `.github/workflows/deploy-on-merge.yml` - Workflow di deployment
- ✅ `composer.json` - Aggiunta dipendenza Plugin Update Checker
- ✅ `fp-restaurant-reservations.php` - Integrazione auto-aggiornamento

## Prossimi Passi

1. Fai il commit di questi cambiamenti
2. Pusha su main (se non l'hai già fatto)
3. Verifica che il workflow funzioni
4. Aggiorna la versione del plugin quando sei pronto per una nuova release

## Risorse

- [Plugin Update Checker Documentation](https://github.com/YahnisElsts/plugin-update-checker)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [GitHub Releases Documentation](https://docs.github.com/en/repositories/releasing-projects-on-github)
