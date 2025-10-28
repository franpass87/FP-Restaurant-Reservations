# 🐛 Sistema Debug FP Reservations

## 🎯 Cosa fa

Il sistema di debug cattura automaticamente tutti gli errori del plugin e li mostra nel **Debug Banner** nella pagina Manager.

## 🔧 Come attivare

### Opzione 1: Query SQL (più veloce)

Esegui nel database:
```sql
INSERT INTO wp_options (option_name, option_value, autoload) 
VALUES ('fp_resv_debug', 'a:1:{s:19:"manager_debug_panel";b:1;}', 'yes')
ON DUPLICATE KEY UPDATE option_value = 'a:1:{s:19:"manager_debug_panel";b:1;}';
```

### Opzione 2: Automatico con WP_DEBUG

Se hai `WP_DEBUG = true` in `wp-config.php`, il debug si attiva automaticamente.

## 📋 Cosa vedrai nel Debug Banner

- ✅ **Info richieste API**: URL, status, headers, dati ricevuti
- 🔴 **Errori del plugin**: Ultimi 10 errori con timestamp e dettagli
- 🔍 **Context**: Informazioni aggiuntive (ID prenotazione, file, riga, ecc.)

## 🧹 Pulizia errori

Per cancellare tutti gli errori registrati:
```sql
DELETE FROM wp_options WHERE option_name = 'fp_resv_error_log';
```

## 🔴 Disattivare il debug

```sql
UPDATE wp_options 
SET option_value = 'a:1:{s:19:"manager_debug_panel";b:0;}' 
WHERE option_name = 'fp_resv_debug';
```

## 📝 Errori registrati

Il sistema salva automaticamente:
- Errori di eliminazione prenotazioni
- Problemi con REST API filters
- Errori di creazione/modifica prenotazioni (da implementare)
- Max 50 errori in memoria (FIFO)

## 🔧 Per sviluppatori

### Aggiungere nuovo logging

```php
use FP\Resv\Core\ErrorLogger;

ErrorLogger::log('Descrizione errore', [
    'context_key' => 'valore',
    'altro_dato' => $variabile,
]);
```

### Leggere errori via codice

```php
$errors = ErrorLogger::getRecentErrors(10); // Ultimi 10
$count = ErrorLogger::count(); // Totale
ErrorLogger::clear(); // Pulisci tutti
```

## 🎨 Personalizzazione

Il debug banner appare in alto a sinistra quando:
1. `debugMode = true` nelle impostazioni
2. Oppure `WP_DEBUG = true` in wp-config.php

Si chiude automaticamente o cliccando "Chiudi".

