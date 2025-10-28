# Changelog: Nuovo Ruolo Reservations Viewer

**Data:** 15 Ottobre 2025  
**Versione:** Da includere nella prossima release (0.1.7+)  
**Tipo:** Feature  
**Breaking Changes:** No

## 📋 Sommario

Aggiunto un nuovo ruolo WordPress `Reservations Viewer` che permette agli utenti di accedere **solo al Manager delle Prenotazioni** senza poter visualizzare o modificare le impostazioni del plugin o accedere ad altre aree di WordPress.

## ✨ Cosa è stato aggiunto

### 1. Nuovo Ruolo: `fp_reservations_viewer`

Un ruolo WordPress con accesso limitato ideale per:
- Receptionist del ristorante
- Staff che deve solo gestire prenotazioni
- Personale con permessi limitati

### 2. Nuova Capability: `view_fp_reservations_manager`

Una capability specifica che permette l'accesso al Manager senza dare accesso completo al plugin.

### 3. Differenziazione Menu Admin

- **Utenti con accesso completo**: Vedono il Manager come submenu sotto "FP Reservations"
- **Utenti Viewer**: Vedono "Prenotazioni" come menu principale standalone

## 🔧 Modifiche Tecniche

### File Modificati

#### 1. `src/Core/Roles.php`

**Aggiunte:**
- Costante `VIEW_RESERVATIONS_MANAGER = 'view_fp_reservations_manager'`
- Costante `RESERVATIONS_VIEWER = 'fp_reservations_viewer'`
- Metodo `getReservationsViewerCapabilities(): array`

**Modifiche:**
- `create()`: Ora crea anche il ruolo Reservations Viewer
- `remove()`: Rimuove anche il ruolo Reservations Viewer
- `addCapabilityToAdministrators()`: Aggiunge anche la capability VIEW_RESERVATIONS_MANAGER agli admin
- `ensureAdminCapabilities()`: Verifica anche la capability VIEW_RESERVATIONS_MANAGER

#### 2. `src/Domain/Reservations/AdminController.php`

**Modifiche:**
- `registerMenu()`: 
  - Crea un menu principale per gli utenti Viewer
  - Mantiene il submenu per gli utenti con accesso completo
  - Gestisce le capability in modo gerarchico
- Aggiunto import per `add_menu_page`

#### 3. `src/Domain/Reservations/AdminREST.php`

**Modifiche:**
- `checkPermissions()`: Ora accetta anche la capability `VIEW_RESERVATIONS_MANAGER`
- Logging migliorato per includere la verifica della nuova capability

### Capabilities per Ruolo

#### Administrator
```php
'manage_options' => true,
'manage_fp_reservations' => true,
'view_fp_reservations_manager' => true,
// ... altre capabilities WordPress
```

#### Restaurant Manager
```php
'manage_fp_reservations' => true,
'view_fp_reservations_manager' => true,
'read' => true,
'upload_files' => true,
```

#### Reservations Viewer (NUOVO)
```php
'view_fp_reservations_manager' => true,
'read' => true,
```

## 📚 Documentazione Creata

1. **NUOVO-RUOLO-RESERVATIONS-VIEWER.md**
   - Guida completa al nuovo ruolo
   - Casi d'uso
   - Istruzioni per l'assegnazione
   - Configurazione avanzata
   - Troubleshooting

2. **test-reservations-viewer-role.php**
   - Script di test per verificare il ruolo
   - Verifica capabilities
   - Simulazione permessi
   - Istruzioni di test

3. **create-viewer-user.php**
   - Script helper per creare rapidamente utenti di test
   - Configurabile
   - Gestisce conflitti
   - Include istruzioni di cleanup

4. **CHANGELOG-RESERVATIONS-VIEWER-ROLE.md** (questo file)
   - Changelog dettagliato della feature

## 🎯 Casi d'Uso

### Caso 1: Receptionist del Ristorante
**Scenario:** Maria è la receptionist e deve gestire le prenotazioni telefoniche e modificare gli stati, ma non deve accedere alle impostazioni.

**Soluzione:** Assegnare il ruolo `Reservations Viewer`

**Risultato:**
- ✅ Può vedere tutte le prenotazioni
- ✅ Può creare nuove prenotazioni
- ✅ Può modificare lo stato delle prenotazioni
- ✅ Può vedere le statistiche nel Manager
- ❌ Non può modificare orari di servizio
- ❌ Non può vedere i report
- ❌ Non può accedere alle impostazioni

### Caso 2: Staff Temporaneo
**Scenario:** Durante l'alta stagione, viene assunto staff temporaneo che deve solo consultare le prenotazioni del giorno.

**Soluzione:** Assegnare il ruolo `Reservations Viewer`

**Risultato:**
- ✅ Accesso immediato senza formazione complessa
- ✅ Zero rischio di modificare configurazioni critiche
- ✅ Facile da rimuovere a fine stagione

### Caso 3: Proprietario Multisite
**Scenario:** Il proprietario ha più ristoranti e vuole dare accesso limitato ai singoli manager.

**Soluzione:** Usare `Restaurant Manager` per i manager senior e `Reservations Viewer` per lo staff operativo

**Risultato:**
- ✅ Gerarchia chiara dei permessi
- ✅ Sicurezza migliorata
- ✅ Audit trail più semplice

## 🧪 Come Testare

### Test 1: Verifica Creazione Ruolo

```bash
# Da WordPress CLI
wp role list --fields=name,role

# Output atteso:
# - fp_restaurant_manager
# - fp_reservations_viewer
```

### Test 2: Verifica Capabilities

```php
// Esegui questo codice in un file PHP temporaneo
$role = get_role('fp_reservations_viewer');
var_dump($role->capabilities);

// Output atteso:
// array(2) {
//   ["view_fp_reservations_manager"] => bool(true)
//   ["read"] => bool(true)
// }
```

### Test 3: Verifica Accesso Menu

1. Crea un utente con ruolo `Reservations Viewer`
2. Fai login con quell'utente
3. Verifica che nel menu laterale appaia SOLO "Prenotazioni"
4. Verifica che il Manager funzioni correttamente

### Test 4: Verifica Endpoint REST

```javascript
// Dalla console del browser (loggato come Viewer)
fetch('/wp-json/fp-resv/v1/agenda?date=2025-10-15')
  .then(r => r.json())
  .then(console.log)

// Output atteso: Lista delle prenotazioni (non errore 403)
```

### Test 5: Verifica Protezione

1. Loggato come Viewer, prova ad accedere a:
   - `/wp-admin/admin.php?page=fp-resv-settings` → ❌ Accesso negato
   - `/wp-admin/admin.php?page=fp-resv-closures-app` → ❌ Accesso negato
   - `/wp-admin/admin.php?page=fp-resv-reports` → ❌ Accesso negato
   - `/wp-admin/admin.php?page=fp-resv-manager` → ✅ Accesso consentito

## 🔄 Migrazione e Backward Compatibility

### ✅ Completamente Backward Compatible

- Gli utenti esistenti non sono influenzati
- I ruoli esistenti mantengono tutte le capabilities
- Nessuna modifica al database delle prenotazioni
- Le capability esistenti continuano a funzionare

### Aggiornamento da Versioni Precedenti

1. **Disattiva il plugin** (opzionale, ma raccomandato)
2. **Aggiorna i file del plugin**
3. **Riattiva il plugin**
4. Il nuovo ruolo verrà creato automaticamente

### Se il Ruolo Non Appare

```php
// Forza la ricreazione dei ruoli
\FP\Resv\Core\Roles::create();
```

O semplicemente:
1. Disattiva il plugin
2. Riattiva il plugin

## 📊 Impact Analysis

### Sicurezza: ✅ Migliorata
- Principio del minimo privilegio applicato
- Separazione dei ruoli operativi da quelli amministrativi
- Audit trail più chiaro

### Performance: ✅ Nessun Impatto
- Nessuna query aggiuntiva
- Nessun overhead di processing
- Cache non influenzata

### Compatibilità: ✅ 100%
- Backward compatible al 100%
- Nessuna breaking change
- Plugin di terze parti non influenzati

### User Experience: ✅ Migliorata
- Menu più pulito per gli utenti limitati
- Meno confusione
- Onboarding più veloce

## 🎨 UI/UX Changes

### Menu Admin - Utente con Accesso Completo
```
FP Reservations
├── Impostazioni
├── Manager ← submenu
├── Chiusure
├── Sale & Tavoli
├── Report
└── Diagnostica
```

### Menu Admin - Reservations Viewer
```
Prenotazioni ← menu principale standalone
```

## 🚀 Deployment

### Checklist Pre-Deploy

- [x] Codice scritto e testato
- [x] Linting passato (PHPCS, PHPStan)
- [x] Documentazione creata
- [x] Script di test creati
- [x] README aggiornato
- [x] Changelog creato
- [ ] Test su staging
- [ ] Test E2E con Playwright
- [ ] Approvazione code review

### Note per il Deploy

1. **Non richiede migrazione database**
2. **Non richiede flush rewrite rules**
3. **Richiede riattivazione plugin** (per creare il ruolo)
4. **Comunicare agli utenti**: Nuovo ruolo disponibile per staff limitato

## 📋 TODO per la Prossima Release

- [ ] Aggiungere traduzioni italiane per "Reservations Viewer"
- [ ] Aggiungere test E2E Playwright per il ruolo
- [ ] Aggiornare la documentazione utente finale
- [ ] Creare video tutorial (opzionale)
- [ ] Aggiungere al changelog principale (CHANGELOG.md)

## 🔗 Link Utili

- [Guida Completa](NUOVO-RUOLO-RESERVATIONS-VIEWER.md)
- [Script di Test](test-reservations-viewer-role.php)
- [Script Helper Creazione Utente](create-viewer-user.php)
- [WordPress Roles & Capabilities](https://wordpress.org/support/article/roles-and-capabilities/)

## 👥 Contributors

- **Francesco** - Implementazione e documentazione

## 📝 Note Finali

Questa feature è stata richiesta per permettere un accesso più granulare alle funzionalità del plugin, specialmente utile per ristoranti con team numerosi dove solo alcuni membri dovrebbero avere accesso alle configurazioni critiche.

La implementazione segue le best practices di WordPress per la gestione dei ruoli e delle capabilities, garantendo sicurezza e facilità d'uso.

---

**Status:** ✅ Completato e pronto per il merge  
**Next Step:** Testing su staging environment e inclusione nella prossima release

