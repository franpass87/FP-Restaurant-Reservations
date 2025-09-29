# UI One-page – WOW Patch Scenarios

## Scenario 1 – CTA smart e validazioni
- **Given** il form caricato senza input
- **When** compilo solo il campo email
- **Then** il pulsante principale mostra "Completa i campi richiesti" e resta disabilitato
- **When** compilo tutti i campi obbligatori
- **Then** il pulsante mostra "Prenota ora" e diventa attivo
- **And** premendo Enter in un campo testo non viene inviato il form

## Scenario 2 – Maschera telefono e normalizzazione
- **Given** il campo telefono vuoto
- **When** digito `+39 3331234567`
- **Then** l'input viene formattato con spazi ogni 3/4 cifre e la validazione passa
- **When** lascio meno di 6 cifre
- **Then** il campo è marcato `aria-invalid="true"` e viene emesso `phone_validation_error`

## Scenario 3 – Disponibilità con debounce e skeleton
- **Given** data e party compilati
- **When** cambio il numero di coperti rapidamente due volte
- **Then** la chiamata REST parte dopo ~250 ms con skeleton visibile e dataLayer `ui_latency`
- **And** in caso di errore temporaneo viene ri-tentata 3 volte con `availability_retry`

## Scenario 4 – Focus management wizard
- **Given** passo dalla sezione data alla sezione dettagli
- **When** la sezione successiva si sblocca
- **Then** il focus si sposta automaticamente sul primo campo della sezione
- **And** dopo un submit positivo il focus va sul banner di successo

## Scenario 5 – Error boundary submit
- **Given** un errore 500 simulato sul REST `reservations`
- **When** invio il form
- **Then** compare l'alert `.fp-alert--error` con messaggio e bottone "Riprova"
- **And** il dataLayer riceve `submit_error` con codice e latenza
