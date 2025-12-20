# Fix: Accesso Menu Amministratore

## Problema

Gli amministratori non avevano accesso completo al menu del plugin "FP Restaurant Reservations". Questo problema si verificava quando:

1. Il plugin veniva installato ma non attivato correttamente
2. Le capability venivano rimosse accidentalmente dal ruolo administrator
3. C'erano problemi durante l'upgrade del plugin

## Causa Principale

Il menu amministrativo del plugin richiede la capability `manage_fp_reservations` per essere visualizzato. Questa capability dovrebbe essere automaticamente aggiunta al ruolo `administrator` durante l'attivazione del plugin, ma in alcuni casi questo processo poteva fallire o la capability poteva essere rimossa.

### File Coinvolti

1. **`src/Domain/Settings/AdminPages.php`** (riga 75)
   - Definisce la capability richiesta: `private const CAPABILITY = Roles::MANAGE_RESERVATIONS;`
   - Questa costante viene usata in `add_menu_page()` per controllare l'accesso

2. **`src/Core/Roles.php`**
   - Gestisce la creazione e l'assegnazione delle capability
   - Metodo `addCapabilityToAdministrators()` aggiunge `manage_fp_reservations` agli admin

## Soluzione Implementata

### 1. Metodo di Verifica e Riparazione Automatica

Aggiunto il metodo `Roles::ensureAdminCapabilities()` che:
- Verifica se gli amministratori hanno la capability `manage_fp_reservations`
- Se mancante, la aggiunge automaticamente
- Viene chiamato ad ogni caricamento del plugin (hook `plugins_loaded`)

```php
// src/Core/Roles.php
public static function ensureAdminCapabilities(): void
{
    $adminRole = get_role('administrator');
    if ($adminRole !== null && !$adminRole->has_cap(self::MANAGE_RESERVATIONS)) {
        $adminRole->add_cap(self::MANAGE_RESERVATIONS);
    }
}
```

### 2. Integrazione nell'Inizializzazione

Modificato `src/Core/Plugin.php` per chiamare il metodo di verifica:

```php
// src/Core/Plugin.php (onPluginsLoaded)
// Garantisce che gli amministratori abbiano sempre le capability necessarie
Roles::ensureAdminCapabilities();
```

### 3. Script di Riparazione Manuale

Creato lo script `tools/fix-admin-capabilities.php` per:
- Diagnosticare problemi con le capability
- Riparare manualmente il ruolo administrator
- Mostrare tutte le capability relative al plugin

**Uso dello script:**
```bash
wp eval-file tools/fix-admin-capabilities.php
```

## Test della Soluzione

### Verifica Automatica

1. Il plugin ora verifica automaticamente le capability ad ogni caricamento
2. Se un amministratore non ha la capability, questa viene aggiunta immediatamente
3. Non è più necessaria alcuna azione manuale

### Verifica Manuale

Per verificare che il problema sia risolto:

1. **Login come amministratore**
2. **Verifica accesso al menu**
   - Il menu "FP Reservations" dovrebbe essere visibile
   - Tutte le sottopagine dovrebbero essere accessibili

3. **Verifica capability (via codice o plugin di debug):**
   ```php
   $admin = get_role('administrator');
   var_dump($admin->has_cap('manage_fp_reservations')); // Dovrebbe essere true
   ```

## Prevenzione

Per evitare che il problema si ripresenti:

1. ✅ **Controllo automatico ad ogni caricamento** - Il metodo `ensureAdminCapabilities()` viene eseguito automaticamente
2. ✅ **Verifica prima di aggiungere** - Il codice controlla se la capability esiste già prima di aggiungerla
3. ✅ **Script di riparazione disponibile** - Lo script manuale può essere eseguito in caso di emergenza

## File Modificati

1. `src/Core/Roles.php` - Aggiunto metodo `ensureAdminCapabilities()`
2. `src/Core/Plugin.php` - Aggiunta chiamata a `ensureAdminCapabilities()` in `onPluginsLoaded()`
3. `tools/fix-admin-capabilities.php` - Nuovo script di riparazione manuale
4. `docs/BUGFIX-ADMIN-MENU-ACCESS.md` - Questa documentazione

## Compatibility

- WordPress: 5.0+
- PHP: 7.4+
- Non richiede modifiche al database
- Compatibile con installazioni multisite

## Note Aggiuntive

### Capability del Plugin

Il plugin definisce una capability principale:
- `manage_fp_reservations` - Permette l'accesso completo al pannello amministrativo

### Ruoli Personalizzati

Il plugin crea anche un ruolo personalizzato:
- `fp_restaurant_manager` - Ha solo la capability `manage_fp_reservations` (più `read` e `upload_files`)

Gli amministratori ricevono automaticamente tutte le capability, inclusa `manage_fp_reservations`.
