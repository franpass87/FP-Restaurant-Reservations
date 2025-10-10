# Risoluzione Errore Caricamento Agenda

## Problema
L'agenda rimaneva bloccata con il messaggio "Caricamento prenotazioni..." e mostrava l'errore in console:
```
Error loading reservations: SyntaxError: Unexpected end of JSON input
```

## Causa
Il JSON restituito dall'endpoint REST `/wp-json/fp-resv/v1/agenda` era malformato o incompleto. Possibili cause:
1. Errori nella query al database non gestiti
2. Caratteri non validi o non UTF-8 nei dati (note, allergie, nomi clienti, ecc.)
3. Eccezioni durante la mappatura dei dati non catturate
4. Dati mancanti o null non gestiti correttamente

## Soluzione Implementata

### 1. Gestione Errori nel Metodo `handleAgenda`
Aggiunto un blocco try-catch completo per catturare qualsiasi errore durante:
- Parsing dei parametri della richiesta
- Query al database
- Mappatura dei risultati
- Costruzione della risposta

Se si verifica un errore, ora viene restituito un `WP_Error` appropriato invece di un JSON malformato.

### 2. Validazione dei Dati dal Database
Aggiunta verifica che il risultato di `findAgendaRange()` sia effettivamente un array:
```php
if (!is_array($rows)) {
    error_log('FP Reservations: findAgendaRange returned non-array value');
    $rows = [];
}
```

### 3. Gestione Errori nella Mappatura delle Prenotazioni
Il metodo `mapAgendaReservation()` ora:
- Ha un blocco try-catch per gestire eccezioni durante la mappatura
- Restituisce un oggetto di fallback con valori minimi in caso di errore
- Logga i dettagli completi dell'errore per il debugging

### 4. Sanitizzazione UTF-8
Aggiunto il metodo `sanitizeUtf8()` che:
- Converte stringhe in UTF-8 valido
- Rimuove caratteri di controllo che potrebbero corrompere il JSON
- Mantiene newline, tab e carriage return legittimi

Applicato a tutti i campi stringa:
- `notes`
- `allergies`
- `first_name`
- `last_name`
- `email`
- `phone`
- `calendar_last_error`

### 5. Logging Dettagliato
Aggiunto logging completo per identificare problemi:
- Errori nella query al database
- Errori durante la mappatura
- Stack trace completo delle eccezioni
- Dati della riga che ha causato l'errore

## File Modificati
- `src/Domain/Reservations/AdminREST.php`

## Come Testare
1. Accedere alla pagina dell'agenda nel backend WordPress
2. Verificare che le prenotazioni si carichino correttamente
3. Se ci sono ancora problemi, controllare i log di errore di WordPress per identificare la causa specifica

## Log di Debug
In caso di problemi, cercare nei log questi messaggi:
- `FP Reservations: findAgendaRange returned non-array value`
- `FP Reservations: Error in handleAgenda`
- `FP Reservations: Error mapping reservation`

I log includeranno stack trace completi e dati dettagliati per il debugging.

## Note
Questa correzione è **robusta e fail-safe**: anche se ci sono dati malformati nel database, l'endpoint REST restituirà sempre un JSON valido, evitando che l'interfaccia rimanga bloccata.
