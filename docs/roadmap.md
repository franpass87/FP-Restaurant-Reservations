# Roadmap Implementazioni Future

| Priorità | Funzionalità | Descrizione sintetica |
| --- | --- | --- |
| Alta | Notifiche SMS/WhatsApp per clienti e staff | Integrare un canale di messaggistica istantanea per conferme, liste d'attesa e avvisi di pagamento, affiancando l'email. |
| Alta | Connettore OTA e channel manager | Sincronizzare disponibilità e prenotazioni con OTA/partner esterni per ridurre overbooking e ampliare la visibilità. |
| Alta | Promemoria automatici pre-visita | Pianificare invii automatici (email/SMS) 24–48 ore prima dell'arrivo per ridurre i no-show. |
| Media | Gift card digitali e voucher integrati al booking | Permettere emissione, riscatto e reporting di gift card direttamente dal flusso di prenotazione. |
| Media | Portale self-service per gestione prenotazioni | Permettere ai clienti di modificare o annullare autonomamente sfruttando l'URL firmato `fp_resv_manage`. |

## Dettagli di implementazione

### Notifiche SMS/WhatsApp per clienti e staff (Priorità Alta)
- Estendere `src/Domain/Settings/AdminPages.php` con una sezione dedicata a credenziali e toggle di invio.
- Implementare `src/Domain/Notifications/SmsService.php` per l'invio, il retry e il logging degli errori.
- Integrare il servizio all'interno di `Service::sendCustomerEmail` e `sendStaffNotifications`.
- Aggiornare la documentazione utenti con requisiti, limiti di rate e best practice sui messaggi automatici.

### Connettore OTA e channel manager (Priorità Alta)
- Creare il dominio `src/Domain/Integrations/ChannelManager/` con servizi per mappare slot, restrizioni e tradurre gli stati delle prenotazioni.
- Estendere `Availability::findSlots` con un adapter che serializzi gli slot per partner esterni applicando regole di buffer per canale.
- Configurare job in `src/Core/Scheduler.php` per pubblicare aggiornamenti di disponibilità e importare prenotazioni provenienti dalle OTA, loggando gli esiti in `Diagnostics`.
- Aggiungere opzioni di configurazione (credenziali, blackout, regole di sincronizzazione) in `src/Domain/Settings/AdminPages.php` e documentarle in `docs/`.

### Promemoria automatici pre-visita (Priorità Alta)
- Ampliare `src/Core/Scheduler.php` registrando un evento ricorrente che accoda i promemoria.
- Creare un `ReminderService` nel dominio prenotazioni per estrarre le prenotazioni eleggibili e segnare quelle notificate.
- Riutilizzare `Mailer` (e il servizio SMS, se disponibile) per l'invio con copy personalizzabile e nuove stringhe in `Language`.
- Esporre nelle impostazioni generali un pannello per attivare/disattivare la funzione, definire anticipo e canali.

### Gift card digitali e voucher integrati al booking (Priorità Media)
- Introdurre il dominio `src/Domain/GiftCards/` con modelli, repository e servizio per emissione, consumo e controllo saldo (tabella `fp_gift_cards`).
- Collegare il repository pagamenti (`src/Domain/Payments/Repository.php`) per tracciare movimenti gift card associati alle prenotazioni e agli export contabili.
- Aggiornare `templates/frontend/form.php` e `assets/js/fe/onepage.js` con un campo di riscatto voucher, gestione errori e feedback sul saldo residuo.
- Creare interfacce amministrative (`src/Admin/Views/giftcards.php`) e report su vendite/scadenze, includendo log eventi in `Diagnostics`.

### Portale self-service per gestione prenotazioni (Priorità Media)
- Aggiungere in `src/Domain/Reservations/REST.php` un endpoint protetto che consenta cancellazioni e richieste di cambio orario.
- Creare una pagina front-end in `src/Frontend` che intercetti `fp_resv_manage` e mostri stato, azioni disponibili e conferme.
- Estendere `Service::generateManageUrl` e la verifica del token per supportare scadenze temporali e limiti di operazioni.
- Aggiornare le email cliente e la documentazione spiegando l'uso del portale e come lo staff può monitorare le richieste.

## Legend
- **Priorità Alta**: interventi strategici a breve termine con impatto diretto su no-show e customer experience.
- **Priorità Media**: iniziative importanti ma pianificabili dopo il completamento delle attività ad alta priorità.
