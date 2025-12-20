# Test Scenarios

Questa guida descrive i percorsi manuali suggeriti per validare le funzionalità core del plugin FP Restaurant Reservations dopo aver popolato l'ambiente con il seed di esempio.

## 1. Preparazione

1. Attiva il plugin FP Restaurant Reservations e assicurati che le migrazioni siano state eseguite (attivazione plugin o `wp fp-resv migrate`).
2. Esegui il seed demo: `wp eval-file scripts/seed.php`.
3. Verifica che la sala "Sala Principale" e i tavoli T1–T3 siano visibili in **Prenotazioni → Sale & Tavoli**.
4. Se il frontend one-page deve mostrare le pill dei pasti, collega l'array `fp_resv_seed_meals` al contesto via filtro:
   ```php
   add_filter('fp_resv_frontend_form_context', function (array $context) {
       $context['meals'] = get_option('fp_resv_seed_meals', []);

       return $context;
   });
   ```

## 2. Frontend – One-page progressive

1. Aggiungi la shortcode `[fp_reservations layout="onepage"]` a una pagina pubblica.
2. Apri la pagina in modalità anonima.
3. Seleziona il pasto "Pranzo" e verifica la comparsa della notice configurata.
4. Controlla che la legenda colori mostri le etichette per Disponibile/Quasi pieno/Completo/Sconosciuto e che le pill cambino colore al variare degli slot.
5. Scegli una data con disponibilità (es. tra due giorni) e seleziona lo slot 12:30.
6. Imposta persone = 2 e compila i campi anagrafici con l'email `anna.rossi@example.com` (il campo deve occupare tutta la riga su desktop).
7. Verifica che la select del prefisso telefonico non contenga duplicati per lo stesso codice.
8. Compila note/allergie, accetta la privacy e, facoltativamente, i consensi marketing/profilazione (i badge Obbligatorio/Opzionale devono comparire sotto il testo senza disallineare la checkbox).
9. Premi "Prenota ora" e verifica:
   - banner di conferma,
   - evento `reservation_submit` + `reservation_confirmed` nel `dataLayer`.
10. Ripeti il percorso con dati mancanti per controllare le validazioni inline (email errata, consenso privacy non spuntato, slot pieno).

## 3. Agenda – Drag & Drop

1. Dal backend apri **Prenotazioni → Agenda**.
2. Filtra per data odierna e successivi due giorni per visualizzare le prenotazioni seed.
3. Trascina la prenotazione di `ben.thomas@example.com` su un altro orario libero → verifica che venga proposta la riassegnazione tavolo coerente.
4. Usa l'azione rapida "Check-in" per `claire.dupont@example.com` e controlla l'aggiornamento dello stato.
5. Apri lo stesso record in due schede admin per validare il lock di concorrenza.

## 4. Brevo – Dual list & attributi

1. Configura una API key valida in **Impostazioni → Brevo** (sostituisci il placeholder del seed).
2. Prenota dal frontend con prefisso `+39` e una seconda prenotazione con prefisso `+33`.
3. In Brevo verifica che i contatti finiscano rispettivamente nelle liste `brevo-list-it-demo` e `brevo-list-en-demo`.
4. Controlla che gli attributi personalizzati (FIRSTNAME, LASTNAME, EMAIL, PHONE, LANG, CONSENTS, MEAL, UTM) siano popolati.

## 5. Post-visita & Survey

1. Dal backend marca la prenotazione `claire.dupont@example.com` come "Visited".
2. Verifica in **Strumenti → Cron** che sia stato pianificato un job `fp_resv_run_postvisit_jobs` a +24h.
3. Dopo l'esecuzione, controlla il log `wp_fp_brevo_log` per l'entry `post_visit_24h`.
4. Completa la survey dal link email simulato e conferma il calcolo NPS + CTA recensione solo per punteggi ≥9.

## 6. Stripe – OFF/ON

1. Con Stripe disattivato (default del seed), conferma che `reservation_confirmed` generi anche `purchase` stimato (`value_is_estimated=true`).
2. Abilita Stripe in **Impostazioni → Pagamenti** inserendo chiavi test e scegliendo "Caparra fissa".
3. Ripeti una prenotazione: deve comparire il widget di pagamento e il `purchase` avere `value_is_estimated=false` con amount reale.
4. Processa un rimborso dal backend e verifica l'aggiornamento dello status pagamento + log `wp_fp_payments`.

## 7. Google Calendar

1. Completa l'OAuth in **Impostazioni → Google Calendar** (usa il calendario demo valorizzato dal seed se necessario).
2. Conferma una nuova prenotazione e controlla che l'evento `fp-resv-{id}` appaia nel calendario selezionato.
3. Modifica data/orario e verifica l'aggiornamento dell'evento senza duplicati.
4. Cancella la prenotazione assicurandoti che l'evento venga eliminato (o marcato come cancellato, in base all'opzione scelta).

## 8. Reports & Channel mix

1. Apri **Prenotazioni → Report** e seleziona un intervallo che includa le date seed.
2. Controlla i KPI: numero prenotazioni, coperti e valore stimato devono riflettere i tre record demo.
3. Analizza il grafico "Channel mix": le UTM del seed devono essere assegnate ai canali Google Ads, Meta Ads e Email/Direct.
4. Esporta il CSV e verifica la presenza dei campi `utm_source`, `utm_medium`, `utm_campaign` e `value`.

## 9. Appendice – Reset rapido

Per ripetere i test da zero:

```bash
wp db query "TRUNCATE TABLE wp_fp_reservations"
wp db query "TRUNCATE TABLE wp_fp_customers"
wp db query "TRUNCATE TABLE wp_fp_events"
wp eval-file scripts/seed.php
```

Aggiorna i prefissi `wp_` se la tua installazione utilizza un prefix diverso.
