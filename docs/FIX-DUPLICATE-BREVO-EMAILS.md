# Fix: Email Duplicate Brevo (Una in ITA + Una in EN)

## 🐛 Problema Rilevato

Quando viene creata una prenotazione, il sistema invia **due email** al cliente:
- Una in **italiano**
- Una in **inglese**

## 🔍 Causa del Problema

Dopo un'analisi approfondita del codice, abbiamo verificato che:

### ✅ Il Plugin Funziona Correttamente

1. Il metodo `sendBrevoConfirmationEvent()` in `src/Domain/Reservations/Service.php` invia **UN SOLO evento** `email_confirmation` a Brevo
2. Esiste un **controllo anti-duplicazione** (righe 1279-1285) che previene l'invio multiplo dello stesso evento
3. Il log del database (`wp_fp_brevo_log`) conferma che viene inviato **un solo evento** per prenotazione

### ❌ Il Problema è nella Configurazione di Brevo

Il problema si verifica perché in Brevo ci sono probabilmente **DUE automazioni diverse** che ascoltano lo stesso evento `email_confirmation`:

1. **Automazione IT**: Configurata per inviare email in italiano
2. **Automazione EN**: Configurata per inviare email in inglese

Quando il plugin invia l'evento `email_confirmation`, **entrambe le automazioni si attivano** e inviano l'email nella loro lingua, risultando in due email inviate allo stesso cliente.

## 🔧 Soluzione

Ci sono **tre modi** per risolvere il problema:

### Opzione 1: Aggiungere Condizioni alle Automazioni (RACCOMANDATO)

Modifica le automazioni in Brevo per far sì che si attivino **solo per la lingua corretta**:

#### Automazione IT
1. Vai su **Automations** → Seleziona l'automazione per le email di conferma in italiano
2. Clicca su **Edit** → **Trigger conditions**
3. Aggiungi condizione: `Contact attribute "LANGUAGE" = "IT"` oppure `= "it"`
4. Salva

#### Automazione EN
1. Vai su **Automations** → Seleziona l'automazione per le email di conferma in inglese
2. Clicca su **Edit** → **Trigger conditions**
3. Aggiungi condizione: `Contact attribute "LANGUAGE" = "EN"` oppure `= "en"`
4. Salva

In questo modo:
- Se il contatto ha lingua IT → si attiva SOLO l'automazione IT
- Se il contatto ha lingua EN → si attiva SOLO l'automazione EN

---

### Opzione 2: Associare Automazioni a Liste Diverse

Se hai liste separate per IT e EN (`brevo_list_id_it` e `brevo_list_id_en`):

1. **Automazione IT**: Associala **solo** alla lista IT
2. **Automazione EN**: Associala **solo** alla lista EN

In questo modo ogni automazione si attiva solo per i contatti della lista corrispondente.

---

### Opzione 3: Usare un Template Multilingua

Invece di due automazioni separate, crea **UNA SOLA automazione** con un template che include **logica condizionale** per entrambe le lingue:

```handlebars
{% if contact.LANGUAGE == "IT" %}
    <!-- Contenuto in italiano -->
    Ciao {{contact.FIRSTNAME}},
    Grazie per la tua prenotazione...
{% else %}
    <!-- Contenuto in inglese -->
    Hi {{contact.FIRSTNAME}},
    Thank you for your booking...
{% endif %}
```

---

## 🛠️ Script di Debug

Per verificare cosa sta succedendo nel database, usa lo script di debug:

```bash
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-brevo-duplicate-emails.php
```

Lo script mostrerà:
- ✅ Quanti eventi `email_confirmation` sono stati inviati
- ✅ A quale lista è stato iscritto il contatto (IT/EN)
- ✅ La configurazione dei canali di notifica
- ✅ I log delle email inviate

Se lo script mostra che il plugin ha inviato **un solo evento**, il problema è sicuramente nella configurazione di Brevo.

---

## 📊 Verifica in Brevo

1. Vai su **Automations** nella dashboard di Brevo
2. Cerca le automazioni che usano l'evento `email_confirmation`
3. Verifica quante automazioni hai:
   - Se ne hai **due** (una IT e una EN), aggiungi le condizioni come descritto sopra
   - Se ne hai **una**, verifica che il template gestisca correttamente la multilingua

4. Controlla i **Logs** delle automazioni per vedere se entrambe si sono attivate

---

## ✅ Verifica della Soluzione

Dopo aver applicato la soluzione:

1. Crea una **nuova prenotazione di test**
2. Verifica di ricevere **una sola email** (nella lingua corretta)
3. Controlla i log in Brevo per confermare che si sia attivata **una sola automazione**

---

## 📝 Note Tecniche

### Come Funziona il Sistema

1. **Creazione prenotazione**: `Service.php::create()` crea la prenotazione
2. **Invio email**: `sendCustomerEmail()` verifica se usare Brevo
3. **Evento Brevo**: `sendBrevoConfirmationEvent()` invia l'evento `email_confirmation` a Brevo (UNA SOLA VOLTA)
4. **Controllo duplicati**: `hasSuccessfulLog()` verifica che l'evento non sia già stato inviato
5. **Sincronizzazione contatto**: `AutomationService::onReservationCreated()` sincronizza il contatto con la lista appropriata (IT o EN)
6. **Automazioni Brevo**: Le automazioni configurate in Brevo ricevono l'evento e inviano l'email

### Attributi del Contatto Inviati a Brevo

Il plugin invia questi attributi al contatto in Brevo:

```php
'LANGUAGE' => 'it' | 'en',  // Lingua della prenotazione
'RESERVATION_DATE' => '2025-10-11',
'RESERVATION_TIME' => '20:00',
// ... altri attributi
```

L'attributo `LANGUAGE` è quello che dovresti usare nelle condizioni delle automazioni.

---

## 🎯 Riepilogo

- ✅ **Il plugin funziona correttamente**: invia un solo evento
- ❌ **Il problema è in Brevo**: due automazioni si attivano per lo stesso evento
- 🔧 **Soluzione**: aggiungi condizioni alle automazioni per filtrare per lingua
- 🛠️ **Strumento**: usa lo script di debug per verificare

---

## 📖 Documentazione Correlata

- [Eventi Brevo per Automazioni Email](./BREVO-EMAIL-EVENTS.md)
- [Fix Eventi Brevo](./FIX-BREVO-EVENT-DELIVERY.md)
- [Test Scenarios: Brevo](./TEST-SCENARIOS.md#4-brevo--dual-list--attributi)
