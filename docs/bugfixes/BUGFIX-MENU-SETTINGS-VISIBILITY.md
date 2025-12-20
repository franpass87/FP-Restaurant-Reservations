# Bugfix: Menu Impostazioni Non Visibile nel Backend WordPress

## Problema

Il menu "Impostazioni" e i relativi submenu (Agenda, Sale & Tavoli, Report & Analytics, Chiusure, Diagnostica) di FP Restaurant Reservations non erano visibili nel backend di WordPress per alcuni amministratori. Questo accadeva quando la capability personalizzata `manage_fp_reservations` non veniva correttamente aggiunta al ruolo `administrator`.

## Causa

Il plugin utilizza una capability personalizzata (`manage_fp_reservations`) definita in `Roles::MANAGE_RESERVATIONS` per controllare l'accesso alle pagine di amministrazione. Questa capability viene aggiunta agli amministratori durante:

1. L'attivazione del plugin (`Roles::create()`)
2. L'aggiornamento del plugin (`Roles::create()`)
3. Il caricamento del plugin (`Roles::ensureAdminCapabilities()`)

Tuttavia, in alcuni casi questa capability potrebbe non essere presente, causando l'invisibilità del menu.

## Soluzione Implementata

È stata implementata una **doppia protezione** nel metodo `registerMenu()` di tutti i controller di amministrazione:

### 1. Chiamata Esplicita a `ensureAdminCapabilities()`

All'inizio del metodo `registerMenu()` viene chiamato:

```php
Roles::ensureAdminCapabilities();
```

Questo garantisce che la capability venga aggiunta agli amministratori prima di registrare il menu.

### 2. Fallback Capability Dinamica

È stata implementata una logica di fallback che determina la capability appropriata:

```php
$capability = current_user_can('manage_options') && !current_user_can(self::CAPABILITY) 
    ? 'manage_options' 
    : self::CAPABILITY;
```

Questa logica funziona come segue:
- Se l'utente ha `manage_options` (amministratore) MA non ha `manage_fp_reservations`, usa `manage_options`
- Altrimenti usa `manage_fp_reservations` (comportamento normale)

### 3. Coerenza tra Menu Principale e Submenu

La stessa capability calcolata viene applicata sia al menu principale che ai submenu delle impostazioni, garantendo coerenza nell'accesso.

## Comportamento Atteso

### Per Amministratori
- **Con `manage_fp_reservations`**: Il menu è visibile tramite questa capability (comportamento standard)
- **Senza `manage_fp_reservations`**: Il menu è visibile tramite `manage_options` (fallback)
- In entrambi i casi, la capability viene automaticamente aggiunta al primo accesso

### Per Restaurant Manager
- Il menu è visibile solo se hanno la capability `manage_fp_reservations`
- Non hanno accesso tramite `manage_options` (che non possiedono)

## File Modificati

Tutti i seguenti controller sono stati aggiornati con la stessa logica di protezione:

- `src/Domain/Settings/AdminPages.php`:
  - Metodo `registerMenu()`: Aggiunta chiamata a `Roles::ensureAdminCapabilities()` e logica di fallback capability per il menu principale e i submenu delle impostazioni

- `src/Domain/Reservations/AdminController.php`:
  - Metodo `registerMenu()`: Aggiunta chiamata a `Roles::ensureAdminCapabilities()` e logica di fallback capability per il submenu "Agenda"

- `src/Domain/Tables/AdminController.php`:
  - Metodo `registerMenu()`: Aggiunta chiamata a `Roles::ensureAdminCapabilities()` e logica di fallback capability per il submenu "Sale & Tavoli"

- `src/Domain/Reports/AdminController.php`:
  - Metodo `registerMenu()`: Aggiunta chiamata a `Roles::ensureAdminCapabilities()` e logica di fallback capability per il submenu "Report & Analytics"

- `src/Domain/Closures/AdminController.php`:
  - Metodo `registerMenu()`: Aggiunta chiamata a `Roles::ensureAdminCapabilities()` e logica di fallback capability per il submenu "Chiusure"

- `src/Domain/Diagnostics/AdminController.php`:
  - Metodo `registerMenu()`: Aggiunta chiamata a `Roles::ensureAdminCapabilities()` e logica di fallback capability per il submenu "Diagnostica"

## Test

Per verificare il fix:

1. **Test manuale**: Accedere al backend WordPress come amministratore e verificare che:
   - Il menu principale "FP Reservations" sia visibile
   - Tutti i submenu siano visibili: Impostazioni, Agenda, Sale & Tavoli, Report & Analytics, Chiusure, Diagnostica
2. **Test con Restaurant Manager**: Accedere come utente con ruolo `fp_restaurant_manager` e verificare l'accesso a tutti i menu
3. **Test capability**: Eseguire lo script `tools/fix-admin-capabilities.php` per verificare che la capability sia presente

## Note Tecniche

- La soluzione è backward-compatible e non introduce breaking changes
- Il fix risolve anche eventuali problemi futuri di capability mancanti
- La capability `manage_options` è una capability standard di WordPress sempre presente negli amministratori

## Link Correlati

- Issue GitHub: #636f
- Branch: `cursor/fix-settings-submenu-visibility-636f`
- Commit: [da inserire dopo il commit]

## Cronologia

- **v1.0**: Fix iniziale su `AdminPages.php` (branch `cursor/fix-wordpress-menu-settings-visibility-6737`)
- **v2.0**: Estensione del fix a tutti i submenu controller (branch `cursor/fix-settings-submenu-visibility-636f`)
