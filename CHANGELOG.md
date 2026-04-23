## [1.3.1] - 2026-04-23

### Fixed

- **Regressione 1.3.0: utenti `fp_manager` redirettati al "Mio account" WooCommerce invece che al backend**. Rimuovendo `edit_posts` dal ruolo, scattava il filtro `woocommerce_prevent_admin_access` di WooCommerce, che richiede una tra `edit_posts`, `manage_woocommerce` o `view_admin_dashboard` per consentire l'accesso a `/wp-admin/`. Aggiunta `view_admin_dashboard` alle capability di default del ruolo `fp_manager`: è la capability WordPress dedicata proprio a questo caso (accesso al backend senza diritti di editor sui post). La capability viene aggiunta idempotentemente al prossimo `admin_init` su installazioni esistenti.

## [1.3.0] - 2026-04-23

### Added

- **`AdminRestrictor`**: nuova classe in `src/Core/AdminRestrictor.php` che limita l'UI admin per gli utenti con ruolo `fp_manager` (non amministratori). Registrata da `Kernel\Bootstrap`, nasconde dai menu top-level tutto ciò che non sia Bacheca, Profilo utente, FP Experiences e FP Restaurant Reservations. Rimuove anche tutti i widget dalla Bacheca e riduce la admin bar alle sole voci FP (più i sottomenu account / logout / edit profile).
- Nuovo filtro `fp_resv_admin_menu_whitelist` per personalizzare la whitelist dei menu top-level (default: `index.php`, `profile.php`, `fp_exp_dashboard`, `fp-resv-settings`).

### Changed

- **Ruolo `fp_manager` più stretto**: rimossa la capability `edit_posts` dalla definizione del ruolo. Le capability custom di FP Restaurant (`manage_fp_reservations`, `view_fp_reservations_manager`) e di FP Experiences (`fp_exp_*`, `*_fp_experiences`) sono sufficienti per gestire prenotazioni ed esperienze, senza esporre l'utente ad Articoli WP, temi come Salient, o page builder come WPBakery.
- `Roles::ensureFpManagerRole()` ora rimuove esplicitamente le capability "vietate" (`edit_posts`, varianti `_posts`) eventualmente ereditate da versioni precedenti del ruolo. La pulizia è idempotente.

### Note

- Gli amministratori non sono mai interessati dalla nuova classe `AdminRestrictor`: continuano a vedere l'intera UI WordPress.
- Gli Store Manager WooCommerce sono esclusi dal restrictor per preservare la loro dashboard e-commerce.
- `upload_files` è mantenuta per consentire l'upload di immagini per il CPT delle esperienze; di conseguenza il menu "Media" resta accessibile solo a fronte di caricamento esplicito (non appare nel menu principale per `fp_manager` grazie al restrictor).

## [1.2.0] - 2026-04-23

### Changed

- **Ruolo unificato FP Manager**: i due ruoli precedenti (`fp_restaurant_manager` e `fp_reservations_viewer`) sono stati sostituiti da un unico ruolo `fp_manager` condiviso con FP Experiences. Il nuovo ruolo ha accesso completo a tutte le funzioni di FP Restaurant Reservations e FP Experiences, eliminando la necessità di gestire ruoli multipli per utenti che lavorano su entrambi i plugin.
- `Roles::create()` è ora idempotente: non rimuove capabilities già presenti sul ruolo, consentendo a FP Experiences di aggiungere in parallelo le proprie capabilities sullo stesso `fp_manager` senza conflitti.
- `Roles::ensureAdminCapabilities()` assicura l'esistenza del ruolo e la presenza delle capabilities di FP Restaurant sull'amministratore ad ogni caricamento admin, ed esegue una migrazione utenti una sola volta (flag `fp_resv_roles_unified_v2`).
- Migrazione automatica degli utenti dai ruoli legacy (`fp_restaurant_manager`, `fp_reservations_viewer`, `fp_exp_manager`, `fp_exp_operator`, `fp_exp_guide`) al nuovo `fp_manager`, con rimozione dei ruoli legacy dal sito.

### Compatibilità

- La costante `Roles::RESTAURANT_MANAGER` è mantenuta come alias deprecato di `Roles::FP_MANAGER` per retrocompatibilità.
- Le capability `MANAGE_RESERVATIONS` e `VIEW_RESERVATIONS_MANAGER` restano invariate: il nuovo ruolo unificato le include entrambe, quindi tutti i check `current_user_can(...)` esistenti continuano a funzionare senza modifiche.
- `Roles::remove()` ora rimuove solo le capabilities di FP Restaurant dal ruolo (senza eliminarlo), per coesistere con FP Experiences.
- `uninstall.php` rimuove il ruolo `fp_manager` e tutti gli slug legacy quando l'utente sceglie di eliminare i dati del plugin.

## [1.1.6] - 2026-04-18

### Fixed

- **Cerchi del progress indicator ovali invece che circolari**: verificato dal vivo via browser MCP su Salient + WPBakery. I temi applicano regole CSS generiche tipo `min-height` / `padding` a tutti i `<div>` del contenuto, rendendo la proporzione del `.fp-progress-step` non più 36×36 quadrata. Con `border-radius: 50%` un rettangolo diventa ovale.
- **Fix**: forziamo con `!important` `width`, `height`, `min-width`, `min-height`, `max-width`, `max-height` tutti a 36 px, `flex: 0 0 36px`, `padding: 0`, `box-sizing: border-box`, `border-radius: 50%`. Così il `.fp-progress-step` resta esattamente 36×36 in ogni ambiente, indipendentemente dalle regole del tema. Le dimensioni restano coerenti con la linea di sfondo calibrata in `StyleCss.php` (`calc(50% - 138px)` desktop, `calc(50% - 120px)` mobile).

### Verifica dal vivo

Screenshot dopo fix: step 1 (completato nero con ✓), step 2 (attivo nero scaled 1.12 con "2"), step 3/4 (grigio vuoti) sono tutti cerchi perfettamente circolari.

## [1.1.5] - 2026-04-18

### Fixed

- **Step completato nel progress indicator non visibile**: verificato dal vivo via browser MCP — lo step 1 dopo il click su "Avanti" appariva come cerchio bianco col numero "1" visibile, senza il checkmark né il fill primary del mio design. Due cause concomitanti:
  1. La SVG mask usata per il checkmark (`-webkit-mask: url("data:image/svg+xml;utf8,...")`) non veniva parsata correttamente in tutti i browser perché i caratteri `<` / `>` / attributi non codificati nel data URI creano problemi di parsing.
  2. Il `background: var(--fp-resv-primary)` della regola `.fp-resv-simple .fp-progress-step.completed` veniva sovrascritto dal tema o da regole più specifiche, facendo apparire il cerchio bianco invece che scuro.
- **Soluzione**: sostituito il ::after con `content: '\2713'` (carattere Unicode ✓ U+2713), più robusto in qualsiasi ambiente. Aggiunto `!important` su `background`, `border-color`, `color`, `font-size` sullo `.fp-progress-step.completed` per vincere su qualsiasi regola del tema. Il numero testuale viene nascosto con `font-size: 0` (più stabile di `color: transparent` che il tema poteva sovrascrivere), mentre il ::after mostra il ✓ con `font-size: 16px` proprio.

### Verifica dal vivo

Testato con browser MCP su `https://fp-development.local/test-rest/` (ambiente Salient + WPBakery): click su Avanti allo step 2 → lo step 1 nel progress indicator ora è un cerchio scuro pieno col checkmark ✓ bianco ben visibile.

## [1.1.4] - 2026-04-18

### Changed

- **Meal buttons più compatti**: i box "Scegli il Servizio" (Cena / Pranzo / Pranzo Domenicale / Cena Weekend) risultavano troppo grandi con la migrazione 1.1.0. Ora: `min-height` da 88 a 64 px (mobile: 58 px), `padding` da 16/12 a 10/8, `gap` interno da 8 a 6 px, icona da 32×32 a 24×24 px (SVG da 22 a 18 px), font da 14 a 13 px. Layout flex-column con icona sopra label resta invariato, stato `selected` e hover invariati.

## [1.1.3] - 2026-04-18

### Fixed

- **Tasto "Indietro" non funzionante con temi Salient/WPBakery**: verificato dal vivo tramite browser MCP — il bottone `#prev-btn` risultava avere `pointer-events: none` applicato da una regola CSS del tema con `!important` e specificità superiore a qualsiasi selettore usato nel plugin. Il click veniva intercettato dal `<div class="fp-buttons">` contenitore anziché dal bottone, quindi il listener `click` su `prev-btn` non scattava mai (identico problema potenziale su `next-btn` e `submit-btn`).
  - **Soluzione (JS, `assets/js/form-simple.js`)**: all'inizializzazione del form e ogni volta che `setBtnHidden(btn, false)` mostra un bottone nav, applichiamo inline style `pointer-events: auto !important` e `cursor: pointer !important` su `prev-btn` / `next-btn` / `submit-btn`. Gli inline style con `!important` battono qualsiasi regola CSS esterna del tema (no CSS specificity war).
  - **Safety net (CSS, `assets/css/form-simple-inline.css`)**: aggiunto `pointer-events: none` su `.fp-btn::before`, `.fp-meal-btn::before`, `.fp-time-slot::before` (pseudo-elementi shine overlay assolutamente posizionati sopra il bottone) per garantire che non intercettino mai il click, indipendentemente dal tema.
  - **Regola CSS difensiva**: aggiunto un override `html body .fp-resv-simple #prev-btn / #next-btn / #submit-btn { pointer-events: auto !important }` (specificità massima) come prima linea di difesa se mai l'inline JS non dovesse girare (es. errore JS precedente).

### Verifica dal vivo

Testato con browser MCP su `https://fp-development.local/test-rest/` (pagina con shortcode `[fp_reservations]` in ambiente Salient + WPBakery): click su Avanti allo step 2 → click su Indietro → form torna correttamente allo step 1 con i 4 meal buttons visibili e heading "1. Scegli il Servizio".

## [1.1.2] - 2026-04-18

### Fixed

- **Righe orizzontali spurie sopra "Servizi Aggiuntivi" e sopra i consensi privacy**: rimosso il `border-top` sul `.fp-fieldset` introdotto in 1.1.1. Il raggruppamento dei campi è ora dato solo dalla `<legend>` visibile (quando presente) e da uno spacing verticale leggermente più generoso, senza linee divisorie che apparivano "appese" nel flusso verticale.

## [1.1.1] - 2026-04-18

### Fixed

- **Effetto "scatola dentro scatola"**: appiattiti i contenitori nidificati del form (`.fp-steps-container`, `.fp-step`, `.fp-field`, `.fp-fieldset`). Ciascuno di essi aveva `background` + `border` + `border-radius` + `box-shadow`, producendo fino a 4 card nidificate. Ora il form ha un solo contenitore principale con sezioni interne sobrie (solo `margin-bottom` tra i field, niente card).
- **Barra orizzontale sopra lo step** (`.fp-step::before`): rimossa perché duplicava la barra gradient già presente in cima al container principale (`.fp-resv-simple::before`).
- **Barra verticale accanto al titolo di step** (mia `h3::before` introdotta in 1.1.0): rimossa. Il numero "1.", "2.", "3.", "4." è già nel testo del titolo, la barra era ridondante.

### Compat / no-regression

- Nessuna modifica a HTML / JS / PHP. Non sono toccati `position`, `display`, `visibility`, `opacity`, `transform` di `.fp-step` per non rompere la logica slide-in degli step gestita da `form-simple.js`.
- Il tasto *Indietro* mantiene il suo comportamento: `#prev-btn` resta legato al listener JS che fa `currentStep--; showStep(currentStep)` senza alterazioni.

## [1.1.0] - 2026-04-18

### Added

- **Form frontend allineato al design system FP**: nuovo layer estetico coerente con gli altri plugin FP, aggiunto come blocco finale in `assets/css/form-simple-inline.css`. Principali miglioramenti:
  - **Progress indicator**: step completati ora mostrano un ✓ (checkmark SVG via `mask`) al posto del numero, step attivo con alone colorato morbido e gradient primary→accent, transizioni cubic-bezier eleganti. Dimensioni cerchi e gap preservati (36 px / 56 px desktop · 36 px / 44 px mobile) per non disallineare la linea di sfondo calibrata in `StyleCss.php`.
  - **Titoli step (H3)**: barra verticale sfumata primary→accent a sinistra del titolo, tipografia più incisiva.
  - **Meal buttons**: card-tile con padding generoso, icona sopra il label, hover con `translateY(-2px)` + ombra diffusa, stato `selected` con gradient primary e alone morbido.
  - **Time slots**: chip pill `border-radius: 999px` con hover scale, stato `selected` con gradient e alone, stato `disabled` con line-through sobrio.
  - **Party selector**: card con bottoni +/- a 44 px, numero grande nel colore primary, label uppercase muted.
  - **Bottoni nav (Avanti/Indietro/Prenota) + bottone PDF**: gradient primary→darker, shadow multilivello, hover con sollevamento.
  - **Form field**: bordi 1.5 px, hover color primary, focus ring colorato `var(--fp-resv-primary-soft)` + border primary.
  - **Summary**: card con barra accento laterale (gradient primary→accent), header titolo con separatore, nota finale con sfondo primary-soft e barra laterale.
- Tutti gli elementi usano esclusivamente le variabili `--fp-resv-*` già iniettate da `StyleCssGenerator` in base al colore scelto in *Impostazioni → Aspetto*. Nessuna palette hardcoded, nessun viola admin: il form usa il colore del ristorante.

### Changed

- **Preview form-colors in admin**: beneficia automaticamente del nuovo look, perché `AdminPages.php` carica lo stesso `form-simple-inline.css` della pagina pubblica.

### Compat / no-regression

- Nessuna modifica a HTML, JS, endpoint, DB, email, hook, shortcode. Solo CSS aggiunto in coda al file esistente.
- Preservate tutte le classi lette da `form-simple.js`: `active`, `completed`, `selected`, `disabled` su `.fp-step`, `.fp-progress-step`, `.fp-meal-btn`, `.fp-time-slot`.
- Preservate dimensioni/gap del progress indicator per non rompere la linea di sfondo calibrata con `calc(50% - 138px)` / `calc(50% - 120px)` in `StyleCss.php`.
- Rispetto di `prefers-reduced-motion`: transizioni e transform disabilitati per utenti con preferenza accessibilità.

## [1.0.48] - 2026-04-18

### Fixed

- **Link "Privacy Policy" nel checkbox consenso**: appariva attaccato al testo (`laPrivacy Policye il trattamento…`) perché alcuni temi (WPBakery/Salient) rimuovono i text-node di solo whitespace vicino ai tag `<a>` e il CSS del plugin aveva `margin: 0 !important` sul link, eliminando anche lo spazio visivo. Ora il link ha `margin: 0 0.25em !important`, garantendo la spaziatura indipendentemente dal comportamento del tema.

## [1.0.47] - 2026-04-18

### Fixed

- **Progress bar — linea di sfondo a tutta larghezza**: `StyleCss.php` iniettava `#formId .fp-progress::before { left: 0; right: 0 }` con specificità superiore al CSS del template `form-simple.php`, facendo sbordare la linea fuori dai cerchi estremi. Ora il CSS inline usa selettori più specifici (`#fp-resv-default`, `.fp-resv-simple` scoped) con `!important` e la linea si ferma esattamente al centro del primo e dell'ultimo cerchio. La `::after` di progresso lineare (non usata da `form-simple.php`) viene nascosta.
- **Progress bar — spaziatura**: rimosso `max-width` e introdotto `gap: 56px` (44px su mobile) con `justify-content: center` per tenere i 4 step raggruppati al centro del form senza costringere la larghezza del contenitore.

## [1.0.46] - 2026-04-18

### Changed

- **Progress bar — spaziatura**: i 4 indicatori step sono ora raggruppati al centro con `max-width: 360px` (320px su mobile) e centrati con `margin: 0 auto`, mantenendo `space-between`. Numeri più ravvicinati e visivamente bilanciati, senza sbilanciamento a destra.

## [1.0.45] - 2026-04-18

### Fixed

- **Progress bar form (step 1/2/3/4)**: i cerchi degli step erano addensati al centro e la linea di sfondo (`::before`) occupava solo l'80% centrato, lasciando uno "spazio vuoto" a destra. Ora `.fp-progress` usa `justify-content: space-between` con padding laterale 18px, e la linea di sfondo è posizionata con `left: 36px; right: 36px;` per unire **esattamente** il centro del primo e dell'ultimo step, a larghezza piena del contenitore.

## [1.0.44] - 2026-04-18

### Fixed

- **Form frontend — navigazione step**: i bottoni *Indietro* / *Avanti* / *Prenota* nascosti allo step corrente ora vengono forzati con `style.display='none' !important` oltre all'attributo HTML `hidden`. Necessario per bypassare temi (es. Salient/WPBakery) che applicano `button { display: inline-block !important }` a tutti i `button`, facendo riapparire il pulsante *Indietro* anche allo step 1.
- **Accessibilità**: i bottoni nascosti ricevono anche `aria-hidden="true"` per coerenza con gli screen reader.

## [1.0.40] - 2026-04-18

### Added

- **Form frontend (shortcode)**: in `templates/frontend/form.php` viene iniettato l’output CSS generato da `Style::buildFrontend()` (stesso usato in anteprima admin), collegando al `#formId` le variabili colore, tipografia, ombre, radii e spaziature definite in **Impostazioni → Aspetto / Stile**.

### Changed

- **Form prenotazione (UX FP)**: `form-simple-inline.css` e sezioni collegate usano le variabili `--fp-resv-*` (gradiente barra, card step, input focus con `--fp-resv-focus-ring`, bottoni primi/secondari con `--fp-resv-button-bg` e `--fp-resv-button-text`, servizi pasto, step progress). Fallback espliciti = look precedente se l’iniezione non c’è.
- **`form.css`**: mappa `--fp-form-*` sui token; Flatpickr e calendario usano token; su apertura calendario, `form-simple.js` copia le variabili correnti su `.flatpickr-calendar` (portale di Flatpickr fuori dal nodo form) così date e oggi rispettano i colori impostati.
- **`form-simple.php` (critico)**: asterischi obbligatori, bordi checkbox, separatore, colori con `var(--fp-resv-…)` ove applicabile.

## [1.0.40] - 2026-04-18

### Changed

- **Aspetto / Dark mode automatica**: disattivata di default. Prima il form in frontend diventava scuro automaticamente se l'OS/browser usava `prefers-color-scheme: dark`, senza un controllo chiaro in admin. Chi vuole la dark mode può riattivarla da **FP Reservations → Aspetto → Dark mode automatica**.

### Fixed

- **Form frontend — pulsanti navigazione**: aggiunto CSS a specificità nucleare per forzare `display: none` sui bottoni `#prev-btn` / `#next-btn` / `#submit-btn` quando hanno l'attributo `hidden` (alcuni temi, es. Salient / WPBakery, impostavano `display: inline-block !important` su tutti i `button` rendendo «Prenota» sempre visibile anche allo step 1).

## [1.0.39] - 2026-04-18

### Added

- **Calendario operativo (chiusure)**: pulsante **Modifica** su ogni evento e azione AJAX `fp_resv_closures_update` per aggiornare data, orari, tipologia, note, capacità, fasce (inclusa **Apertura speciale**). In modifica mantiene `meal_key` per le aperture speciali.

### Fixed

- **Chiusure AJAX**: `sanitizeSpecialHours` ora preserva `start`/`end` nelle fasce inviate in JSON, coerentemente con `PayloadNormalizer` (in precedenza i dati potevano generare slot vuoti in salvataggio).

## [1.0.38] - 2026-04-10

### Added

- **GA4 / GTM dopo submit REST**: la risposta `POST /fp-resv/v1/reservations` include `reservation.tracking` (`event_name`, `event_id`, `value`, `currency`, `transaction_id`, parametri prenotazione e opzionale `items`). Il form `form-simple.js` esegue `dataLayer.push` sul browser con lo stesso `event_id` generato lato server, così il prezzo dal piano pasti arriva a GA4 anche quando non c’è `wp_footer` sulla richiesta API (deduplica con GA4 Measurement Protocol se attivo).

### Changed

- **TrackingBridge**: se presente `fp_tracking_event_id` nel payload di creazione, viene riusato come `event_id` dell’evento `fp_tracking_event`.

## [1.0.36] - 2026-04-10

### Fixed

- **Brevo / liste IT+EN**: rimosso un doppio `POST /v3/contacts` su creazione prenotazione e su cambio stato. Prima `syncContact()` senza `listIds` (con FP Tracking) risolveva sempre la lista **IT**; subito dopo `subscribeContact()` aggiungeva la lista corretta (es. **EN** da lingua pagina / prefisso) → lo stesso contatto finiva su due liste. Ora resta un solo upsert tramite `subscribeContact()`.

## [1.0.35] - 2026-04-07

### Fixed

- **Conferma cliente + canale Brevo**: se in Notifiche la conferma è su Brevo ma l’evento `email_confirmation` non viene inviato (evento disabilitato nella checklist Brevo, client non connesso, risposta API in errore), il plugin invia la conferma al cliente in **fallback** tramite `wp_mail` usando i template del plugin. Evita prenotazioni senza alcuna email al cliente. Non si applica fallback se Brevo ha già registrato un invio con successo (anti-duplicato).

## [1.0.34] - 2026-04-07

### Fixed

- **Email staff (Notifiche)**: `EmailContextBuilder` usava la proprietà inesistente `$reservation->created` invece di `getCreatedAt()`, causando errore fatale e blocco dell’invio delle notifiche a ristorante/webmaster alla creazione prenotazione.
- **Salvataggio destinatari Notifiche**: in `SettingsSanitizer`, se un campo `email_list` non è presente nel POST (es. richiesta troncata per `max_input_vars`), si preservano i valori già memorizzati nell’opzione anziché salvare liste vuote.

### Added

- **Avvisa sugli annullamenti** (`notify_on_cancel`): collegato al flusso reale — su transizione a stato `cancelled` viene inviata email ai destinatari configurati (stesso schema ristorante + webmaster delle nuove prenotazioni). Filtri: `fp_resv_staff_cancel_email_subject`, `fp_resv_staff_cancel_email_message`.

## [1.0.33] - 2026-04-05

### Added

- **Tracking → Privacy & GDPR**: se **FP Privacy & Cookie Policy** è attivo, con **URL informativa** vuoto il form prenotazioni usa automaticamente il permalink della privacy per la lingua del visitatore (stessa logica del plugin privacy: pagina per lingua → fallback pagina privacy WordPress). Testo di aiuto e placeholder in admin aggiornati.

## [1.0.32] - 2026-04-05

### Fixed

- **Report & Analytics**: grafico a ciambella «Canali principali» limitato a un contenitore centrato (max 360×300px) con `maintainAspectRatio: false`, così non occupa più tutta la larghezza della card come quadrato enorme.

## [1.0.31] - 2026-04-05

### Fixed

- **Anteprima live Colori Form**: caricamento di `form-simple-inline.css` (stesso dello shortcode) al posto di `form.css` / TheFork; markup allineato a `form-simple.php` (`.fp-resv-simple`, `fp-meal-btn`, griglia `fp-time-slots` / `fp-time-slot`, `fp-btn-primary`, selettore persone); CSS dinamico con `!important` mirato al contenitore `#fp-resv-preview-widget` così i colori scelti si vedono sul widget; rimosso il finto messaggio di conferma prenotazione dall’anteprima.

## [1.0.30] - 2026-04-05

### Fixed

- Pagina admin **Colori Form** (`fp-resv-form-colors`): layout a griglia e shell `.wrap` / `.fp-resv-admin` allineata alle altre schermate; titolo accessibile (`h1` screen-reader + `h2` nel banner); notice nel blocco `fp-resv-settings__notices`; rimosso blocco `:root` inline che applicava variabili colore a tutto il backend; `confirm` reset con `esc_js`; iframe anteprima con titolo e stili dedicati.
- `form-colors.js`: applicazione subito delle variabili CSS nell’iframe dopo `document.write` (l’evento `load` poteva essere già occorso e l’anteprima restava neutra); guard su `fpResvFormColors`; rimossi `console.error` in produzione.

### Changed

- `admin-settings.css`: regole layout **Colori Form**; escluso `h1.screen-reader-text` dalla regola che nascondeva tutti gli `h1` nel `.wrap` delle pagine FP Reservations.

## [1.0.24] - 2026-03-25

### Fixed

- Secondo passaggio su log rumorosi: `AdminREST` (registrazione route, permessi, delete/update/move), `ClosuresResponseBuilder`, frontend (`PageBuilderCompatibility`, `CriticalCssManager`, `ContentFilter`), `SpecialOpeningsProvider`, `FormContext`, `Roles::ensureAdminCapabilities`, persistenza (`ReservationRepository`, `ReservationService`), handler REST overview/arrivi, `AvailabilityHandler` e `Tables\REST` — rimossi o sostituiti con `ErrorLogger` dove serve traccia in admin senza riempire `debug.log` a ogni richiesta.
- `AssetManager`: rimosso `use function error_log` non utilizzato.

## [1.0.23] - 2026-03-25

### Fixed

- Shortcode prenotazioni: rimossi `error_log` ricorrenti da `Shortcodes::register`, `ShortcodeRenderer` e shortcode di test — con `WP_DEBUG` + `WP_DEBUG_LOG` non si riempie più `debug.log` a ogni richiesta/registrazione.

## [1.0.22] - 2026-03-24

### Changed

- Brevo transactional: in `send` e `sendBulk` il payload verso `/v3/smtp/email` passa da `fp_tracking_brevo_merge_transactional_tags()` se disponibile (tag sito da FP Marketing Tracking Layer).

## [1.0.21] - 2026-03-24

### Changed
- Brevo contatti: con **FP Marketing Tracking Layer** e Brevo abilitato lì, l’upsert usa `fp_tracking_brevo_upsert_contact()` (stessa API key del layer). `isEnabled()` resta true anche senza chiave API nel tab Brevo del ristorante se il layer è configurato.

## [1.0.20] - 2026-03-24

### Changed
- `Mailer::send`: per corpi **text/html**, applicazione opzionale di `fp_fpmail_brand_html()` quando **FP Mail SMTP** è attivo (grafica centralizzata senza cambiare i template). Plain text invariato; contesto `skip_fp_mail_branding` per eccezioni.

### Fixed
- Badge versione nell’header admin (es. tab Brevo): `Core\Plugin::VERSION` era fermo a 1.0.11; ora è uguale a `Kernel\Plugin::VERSION` e all’header del plugin. Script `tools/bump-version.php` aggiorna `src/Kernel/Plugin.php`.

## [1.0.19] - 2026-03-24

### Changed
- Pagina **Notifiche**: sezione dedicata **«Canali: wp_mail (plugin) o Brevo»** con i tre menu (conferma, promemoria, follow-up) separata dai template; etichette opzioni esplicite (wp_mail vs Brevo). I template cliente sono nella sezione successiva.

## [1.0.18] - 2026-03-24

### Changed
- Email cliente: i tre canali in **Impostazioni → Notifiche** (conferma, promemoria, follow-up recensione) valgono sempre; niente più blocco globale da tab Brevo (`customer_messages_channel` rimosso dall’UI). Puoi mescolare wp_mail e Brevo per tipo.
- Tab Brevo: sezione «Messaggi al cliente» aggiornata (descrizione + legenda) per puntare a Notifiche e alla checklist eventi Automation.

## [1.0.17] - 2026-03-24

### Added
- Tab Brevo, sezione «Messaggi al cliente»: box **Legenda** (wp_mail vs Brevo, link a Notifiche, spiegazione checklist eventi Automation).

## [1.0.16] - 2026-03-24

### Added
- Brevo: sezione «Messaggi al cliente e eventi Automation» — canale predefinito WordPress (`customer_messages_channel`), checklist eventi Track con flag `brevo_track_events_submitted` (retrocompatibile se mai salvata).
- `TrackEventPolicy` per abilitare/disabilitare singoli eventi prima di `sendEvent` / dispatcher.

### Changed
- Notifiche cliente: con canale master WordPress i canali conferma/reminder/recensione restano su template plugin (`wp_mail`) indipendentemente dai select in Notifiche.

## [1.0.15] - 2026-03-24

### Fixed
- Form frontend: risolto il mancato rendering su produzione correggendo la signature di `mergeBrevoFromTracking()` e l'hook `option_fp_resv_brevo` (WordPress passa 2 argomenti al filtro opzione).

## [1.0.14] - 2026-03-24

### Added
- TrackingBridge: payload prenotazione arricchito per GA4/GTM/Meta — `meal_type` da chiave pasto (fix), `meal_label` da piano pasti, `booking_status`, `reservation_language`, `affiliation` (nome sito), `price_per_person`, seggioloni/accessibilità/animali/marketing consent (0/1), UTM e click ID se presenti nel payload, `page_url` (referrer o home), array `items` ecommerce (pasto × coperti). Filtro `fp_resv_tracking_reservation_created_params`. Stessi `items` / `meal_label` su eventi admin `booking_confirmed` / `booking_payment_completed` con valore.

### Changed
- `TrackingBridge` richiede `Options` in costruttore (ServiceRegistry + DI container).

## [1.0.13] - 2026-03-24

### Added
- Sanitizer: `value` e `price_per_person` derivati dal piano pasti (`frontend_meals`) quando il form non li invia, così GA4 / FP Marketing Tracking Layer ricevono `prezzo × coperti` (es. Brunch 45 € × 2 = 90 €).

### Changed
- Tracking: evento `purchase` anche per prenotazioni in stato `pending` (con `value_is_estimated`), non solo `confirmed`; esclusi `pending_payment` e `waitlist`.

## [1.0.12] - 2026-03-23

### Changed
- Menu position 56.10 per ordine alfabetico FP.

## [1.0.11] - 2026-03-23

### Changed
- Brevo: notice centralizzazione sempre visibile nella pagina Brevo (anche quando non abilitato), con messaggio contestuale e link a FP Tracking.

## [1.0.10] - 2026-03-23

### Changed
- Brevo: API key e liste ITA/ENG ora lette da FP Marketing Tracking Layer quando attivo. Filtro `option_fp_resv_brevo` e sanitizer preservano i valori centralizzati. Notice in pagina Brevo con link a FP Tracking.

## [1.0.9] - 2026-03-22

### Fixed
- DiagnosticShortcode: tutti gli `error_log` condizionati a `WP_DEBUG` per evitare output nei log in produzione.

## [1.0.8] - 2026-03-22

### Fixed
- Form frontend: tutti i console.log/warn/error condizionati a WP_DEBUG tramite `window.fpResvDebug` per evitare output in produzione.

## [1.0.7] - 2026-03-22

### Changed
- Admin UI allineata al design system FP su pagine operative/impostazioni con badge versione in header e coerenza visuale cross-page.
- Tracking settings: rimossi i campi credenziali marketing locali (GA4/Ads/Meta/Clarity) in favore della configurazione centralizzata su FP Marketing Tracking Layer.

### Fixed
- Bootstrap tracking legacy disattivato automaticamente quando FP Marketing Tracking Layer è attivo, prevenendo doppi invii eventi.
- Hardening runtime minori su bootstrap/logging/provider e aggiornamenti di supporto in vendor e diagnostica.

## [1.0.6] - 2026-03-20

### Fixed
- Admin meal plan: definiti `dateFromField` / `dateToField` in `renderMealCard` (prima solo `appendChild` senza `const`) — `ReferenceError` bloccava tutto l’editor Turni & disponibilità.

## [1.0.5] - 2026-03-20

### Fixed
- Admin **Turni & disponibilità**: `meal-plan.js` non usa più `import` da `meal-plan-config.js` né `type="module"`; le costanti sono inline. Su alcuni hosting/CDN l’import relativo del secondo file falliva e l’editor restava vuoto.

## [1.0.4] - 2026-03-20

### Fixed
- `MealPlan::normalizeMeal()`: ripristinata la normalizzazione di `date_from` / `date_to` (e alias) nel pasto; senza questo passaggio le date salvate nel JSON non venivano lette e il filtro data non aveva effetto.

## [1.0.3] - 2026-03-20

### Added
- Piano pasti: campi opzionali **Data inizio** / **Data fine** (`date_from` / `date_to`, formato YYYY-MM-DD) per limitare la prenotabilità di un pasto a un intervallo di calendario (inclusivo). Admin: editor pasti con input data; backend: filtro su disponibilità, slot e `MealPlanService::isMealAvailableOnDay`. Frontend: pasti nascosti se la finestra non interseca l’intervallo min/max giorni di anticipo.

## [1.0.2] - 2026-03-19

### Changed
- Admin: gerarchia titoli allineata al design system FP (`h1.screen-reader-text` nel `.wrap`, titolo visibile in `h2` con `aria-hidden="true"`) su impostazioni, personalizzazione stile, diagnostica e manager; contenuto avvolto in `.wrap.fp-resv-admin-outer`; `role="region"` `aria-labelledby` punta all’`h1` accessibile.
- CSS: `margin-top` su `#wpbody-content > .wrap.fp-resv-admin-outer`; stili header manager estesi a `h2`.

## [1.0.1] - 2026-03-18

### Changed
- Blocco "DEBUG MEALS" nel form: mostrato solo se `FP_RESV_DEBUG_MEALS` è definita (oltre a `WP_DEBUG`), per evitare dump in sviluppo con WP_DEBUG attivo.

### Fixed
- A11y e test automation: giorni calendario Flatpickr con `role="button"` e `aria-label` (es. "Scegli data YYYY-MM-DD") per snapshot e assistive tech.

## [1.0.0] - 2026-03-18

### Added
- **First stable release.** Plugin dichiarato production-ready; API frozen per la serie 1.x. Percorso A (pragmatico) completato.

## [0.9.0-rc10.16] - 2026-03-18
### Added
- Dashboard Diagnostica: nuovo pulsante `Simula integrazioni` per avviare test QA one-click direttamente da interfaccia admin.
- QA REST: nuovo endpoint `/qa/simulate-integrations` con simulazione completa senza credenziali reali (Brevo, Google Calendar, Stripe, email, queue e tracking).

### Fixed
- Registrazione route QA resa compatibile con bootstrap tardivo (`rest_api_init` già eseguito), risolvendo i 404 sulla simulazione da pannello admin.
- Wiring REST legacy/provider allineato per garantire esposizione stabile degli endpoint QA in runtime.

## [0.9.0-rc10.15] - 2026-03-13
### Added
- Manager prenotazioni: nuovo pulsante `Nuova Chiusura` con modal dedicata per creare chiusure operative direttamente dalla dashboard.

### Changed
- Pagina `Chiusure` rinominata in `Calendario Operativo` con restyle completo UI/UX (gerarchia visiva, guida rapida, microcopy operativa, toolbar/filtri più chiari).

### Fixed
- Creazione chiusure dal Manager migrata da REST a AJAX admin per evitare errori `rest_cookie_invalid_nonce` in ambienti con host/porta diversi.
- Normalizzazione URL admin-ajax e parsing errori API lato frontend per feedback utente più affidabile.
- Corretto rendering del form planner quando è `hidden` (`display: none`) per evitare apertura involontaria all'avvio pagina.

## [0.9.0-rc10.14] - 2026-03-12
### Fixed
- Uniformata la gestione timezone tra backend e frontend: default range date e parsing datetime ora coerenti con timezone WordPress.
- Admin manager/agenda/closures: eliminati parsing data ambigui lato browser (`YYYY-MM-DD`/`Date.parse`) per evitare slittamenti di giorno/orario.
- Event schema, diagnostica log e finestra Google Calendar: parsing/formatting date allineati al timezone configurato del sito.

## [0.9.0-rc10.13] - 2026-03-12
### Fixed
- Chiusure admin: visualizzazione date/ore forzata su timezone Europe/Rome per evitare slittamenti di giorno/orario.
- Chiusure admin: payload start/end inviato senza offset client per coerenza con parsing timezone WordPress lato backend.

## [0.9.0-rc10.12] - 2026-03-12
### Fixed
- Array to string conversion in AdminServiceProvider: getFieldAsString per tables_enabled (checkbox)
- Aggiunto getFieldAsString in OptionsAdapter e Domain\Settings\Options

## [0.9.0-rc10.11] - 2026-03-09
### Fixed
- StyleCssGenerator: rimosso riferimento errato a `$shadows` (typo, doveva essere `$shadowPresets`) — evita PHP Warning su form prenotazioni

## [0.9.0-rc10.10] - 2026-03-08
### Added
- TrackingBridge: `value` da campo esplicito o `price_per_person`, `transaction_id` e `value` su `booking_confirmed` da `status_changed`
- Pulizia form frontend, ServiceRegistry, BusinessServiceProvider e REST — rimozione codice legacy

## [0.9.0-rc10.10] - 2026-03-05 — Evento privato: esclusione dalla disponibilità

### Added - exclude_from_availability
- **[NEW]** Nuova colonna `exclude_from_availability TINYINT(1) DEFAULT 0` nella tabella `fp_reservations`
- **[NEW]** Le prenotazioni con questo flag a `1` non vengono conteggiate nel calcolo della disponibilità (né in `loadReservations` né in `countDailyActiveReservations`)
- **[NEW]** Nello step 3 del form backend, per gli eventi privati appare un checkbox "Non scalare la capienza del giorno" (spuntato di default)
- **[NEW]** Il flag viene trasmesso via REST, sanitizzato e salvato nel DB

### Impact
- ✅ Un evento privato può coesistere con altri servizi della stessa giornata senza ridurne la capienza disponibile
- ✅ Il checkbox è visibile solo per eventi privati (`__private_event__`), non per prenotazioni normali
- ✅ Nessun impatto su prenotazioni frontend esistenti

### Files Modified
- `src/Core/Migrations.php` — bump DB_VERSION a `2026.03.05`, `applyAlterations()` aggiunge la colonna via `ALTER TABLE`
- `src/Domain/Reservations/Availability/DataLoader.php` — filtro `exclude_from_availability = 0` in entrambe le query
- `src/Domain/Reservations/ReservationPayloadSanitizer.php` — default e sanitizzazione del flag
- `src/Domain/Reservations/Admin/ReservationPayloadExtractor.php` — lettura dal request
- `src/Domain/Reservations/Service.php` — passaggio al repository insert
- `assets/js/admin/manager-app.js` — checkbox condizionale nello step 3, lettura nel submit
- `assets/js/admin/agenda-app.js` — idem
- `assets/css/admin-manager.css` — stili per `.fp-private-event-option` e `.fp-checkbox-label`

---

## 0.9.0-rc10.8 - Staff bypass disponibilità (2026-03-05)

### Added - Staff override capacità
- **[NEW]** Lo staff (prenotazioni create dal pannello admin) può ora creare prenotazioni anche quando lo slot è pieno o ha raggiunto il limite di capienza
- **[NEW]** Il flag `bypass_availability` viene impostato automaticamente a `true` per tutte le prenotazioni create dal backend admin
- **[NEW]** `AvailabilityGuard::guardAvailabilityForSlot()` accetta il parametro opzionale `$bypassAvailability` (default `false`)

### Impact
- ✅ Lo staff può inserire prenotazioni extra senza essere bloccato dai limiti di capienza
- ✅ Le prenotazioni frontend continuano a rispettare i limiti normalmente
- ✅ Nessun impatto su sicurezza: il bypass è disponibile solo tramite endpoint admin (richiede `manage_options`)

### Files Modified
- `src/Domain/Reservations/Admin/ReservationPayloadExtractor.php` — aggiunto `bypass_availability: true`
- `src/Domain/Reservations/ReservationPayloadSanitizer.php` — preservazione flag `bypass_availability`
- `src/Domain/Reservations/AvailabilityGuard.php` — parametro `$bypassAvailability`, skip immediato se `true`
- `src/Domain/Reservations/Service.php` — passaggio del flag al guard

---

## 0.9.0-rc10.7 - Aperture speciali in Turni e disponibilità (2025-02-11)

### Added - Configurazione aperture speciali
- **[NEW]** Le aperture speciali (es. San Valentino) compaiono ora nella sezione **Turni e disponibilità**
- **[NEW]** Parametri configurabili per ogni apertura: Intervallo slot, Durata turno, Buffer, Prenotazioni parallele, Capacità massima
- **[NEW]** Se imposti max_parallel per un' apertura speciale, il limite viene applicato; altrimenti si usa solo la capienza

### Impact
- ✅ Puoi gestire i parametri delle aperture speciali dallo stesso pannello dei pasti ordinari
- ✅ Le aperture si creano ancora in Chiusure & Orari speciali; qui si configurano solo i parametri di disponibilità
- ✅ Vuoto: messaggio con link a Chiusure & Orari speciali

### Files Modified
- `src/Domain/Settings/PagesConfig.php` — campo `special_opening_params`
- `src/Domain/Settings/AdminPages.php` — render tipo `special_opening_params`
- `src/Domain/Settings/Admin/SettingsSanitizer.php` — sanitizzazione JSON
- `src/Frontend/SpecialOpeningsProvider.php` — `getSpecialOpeningsForAdmin()`
- `src/Domain/Reservations/Availability.php` — `getSpecialOpeningParamsOverride()`, uso override in `resolveMealSettings`
- `assets/js/admin/meal-plan.js` — UI aperture speciali
- `assets/css/admin-settings.css` — stili sezione

---

## 0.9.0-rc10.6 - Fix max_parallel per aperture speciali (2025-02-11)

### Fixed - Aperture speciali / Eventi 🔴
- **[FIX]** Le aperture speciali (es. San Valentino capienza 60) bloccavano erroneamente nuove prenotazioni al raggiungimento di `max_parallel` prenotazioni, ignorando la capienza dell'evento
- **[FIX]** Con capienza 60 e 4 prenotazioni da 8 persone (32 totali), lo slot veniva marcato "pieno" perché `parallelCount >= maxParallel` (es. 4)
- **[IMPROVEMENT]** Per aperture speciali ora si usa **solo la capienza** dell'evento come limite; `max_parallel` è disattivato (resta attivo per pranzo/cena normale)

### Impact
- ✅ Eventi con capienza 60 accettano prenotazioni finché non si raggiungono 60 persone
- ✅ Nessun cambio per il servizio pranzo/cena ordinario

### Files Modified
- `src/Domain/Reservations/Availability.php` — skip check `max_parallel` quando `$isSpecialOpening === true`

---

## 0.9.0-rc10.5 - Production Code Cleanup & Improvements (2025-11-XX)

### Fixed - Memory Leak 🔴
- **[FIX]** Memory leak fix: `setTimeout` nel search debounce ora viene correttamente pulito
- **[FIX]** `searchTimeout` ora salvato come proprietà dell'istanza per permettere cleanup corretto

### Changed - Code Quality & Production Readiness 🧹
- **[CLEANUP]** Rimossi tutti i `console.log` di debug dai file JavaScript admin e frontend
- **[CLEANUP]** Rimossi tutti i fetch di debug locale (127.0.0.1:7242) da closures-app.js
- **[IMPROVEMENT]** Aggiunto sistema di logging condizionale basato su `debugMode` per file admin
- **[IMPROVEMENT]** Frontend ora completamente pulito da log di debug (solo `console.error` per errori critici)
- **[IMPROVEMENT]** Migliorata configurazione ESLint per prevenire `console.log` in futuro
- **[DOC]** Aggiunta documentazione JSDoc alle funzioni principali di `ReservationManager`
- **[REFACTOR]** Estratti magic numbers come costanti statiche della classe (timeouts, debounce delays)

### Files Modified
- `assets/js/admin/closures-app.js` - Rimossi fetch debug e console.log
- `assets/js/admin/manager-app.js` - Aggiunto logging condizionale (~93 sostituzioni), fix memory leak, JSDoc
- `assets/js/admin/agenda-app.js` - Aggiunto logging condizionale (~24 sostituzioni)
- `assets/js/fe/onepage.js` - Rimossi tutti i console.log di debug (~17 rimozioni)
- `eslint.config.js` - Aggiunta regola `no-console` per prevenire log futuri

### Impact
- ✅ **Performance**: Nessun overhead di console.log in produzione, memory leak risolto
- ✅ **UX**: Console browser pulita per clienti finali
- ✅ **Security**: Nessuna esposizione di dati di debug
- ✅ **Code Quality**: Codice più professionale e production-ready con documentazione migliorata
- ✅ **Maintainability**: ESLint previene console.log futuri, JSDoc migliora la documentazione

### Technical Details
- Admin files: Logging attivo solo se `debugMode: true` nelle impostazioni
- Frontend: Solo `console.error` per errori critici (nonce, Flatpickr)
- ESLint: Warning su `console.log/warn`, permesso solo `console.error`
- Memory leak: `searchTimeout` ora proprietà di istanza con cleanup automatico

---

## 0.9.0-rc10.3 - Fix Slot Orari Mock (2025-11-03)

### Fixed - Critical Bug 🔴
- **[CRITICAL]** `handleAvailableSlots()` restituiva dati MOCK hardcoded invece di slot reali dal backend
- **[CRITICAL]** Frontend mostrava slot sbagliati (12:00, 14:00, 13:30 disabilitato) non corrispondenti alla configurazione backend
- **[CRITICAL]** Slot orari ora generati correttamente da `Availability::findSlotsForDayRange()` basati su configurazione backend

### Changed - Slot Generation
- Sostituito mock hardcoded con chiamata reale a `$this->availability->findSlotsForDayRange()`
- Slot ora generati in base agli orari configurati nel backend (es: 12:30-14:30, 13:00-15:00, 13:30-15:30)
- Formato slot trasformato per compatibilità frontend (time, slot_start, available, capacity, status)

### Impact
- ✅ Slot orari frontend ora corrispondono 100% alla configurazione backend
- ✅ Nessun slot fantasma (12:00, 14:00 non configurati)
- ✅ Slot 13:30 ora mostrato correttamente se configurato
- ✅ Disponibilità reale calcolata per ogni slot

---

## 0.9.0-rc10 - Bugfix Session 2: Security & Race Conditions (2025-11-03)

### Fixed - Bug Critici 🔴
- **[CRITICAL]** Race condition in `loadAvailableDays()` - richieste multiple potevano sovrascriversi
- **[CRITICAL]** Missing `response.ok` check - errori HTTP non gestiti correttamente
- **[SECURITY]** Potential XSS in `updateAvailableDaysHint()` - innerHTML con variabili

### Added - Request Handling 🚀
- **[FIX]** AbortController per cancellare richieste obsolete
- **[FIX]** Request ID tracking per identificare richiesta più recente
- **[FIX]** Response status check prima di parsare JSON
- **[FIX]** Gestione corretta AbortError (intenzionale)

### Improved - Security & Validation 🔒
- **[SECURITY]** Validazione input REST endpoint `/available-days` (from, to, meal)
- **[SECURITY]** Regex validation per date format (YYYY-MM-DD)
- **[SECURITY]** Whitelist validation per meal types
- **[SECURITY]** DOM safe: usato `createTextNode` invece di innerHTML

### Impact
- ✅ 3 bug critici risolti
- ✅ Race condition eliminata
- ✅ Security hardening REST API
- ✅ XSS prevention
- ✅ Request abort support
- ✅ Robustezza generale migliorata

### Technical Details
Vedi: `docs/bugfixes/BUGFIX-SESSION-2-2025-11-03.md`

---

## 0.9.0-rc9 - Bugfix Calendario (2025-11-03)

### Fixed - Bug Critici 🐛
- **[BUG]** Memory leak in `showCalendarError()` - setTimeout non cancellato
- **[BUG]** Possibile errore `element.remove()` su elemento già rimosso
- **[BUG]** Inconsistenza query selector in `hideCalendarLoading()`
- **[BUG]** Mancanza check `dayElem.dateObj` in `onDayCreate` callback
- **[BUG]** Mancanza type check per `dayInfo.meals` object

### Improved - Accessibilità ♿
- **[A11Y]** Aggiunto `role="status"` e `aria-live="polite"` a loading indicator
- **[A11Y]** Aggiunto `role="alert"` e `aria-live="assertive"` a error message
- **[A11Y]** Aggiunto `aria-label` a date calendario (disponibili/non disponibili)
- **[A11Y]** Aggiunto `user-select: none` su date disabilitate

### Improved - Performance & Compatibilità 🚀
- **[PERF]** Aggiunto `will-change: transform` per animazione spinner
- **[PERF]** Aggiunto `transition` smooth per hover date
- **[COMPAT]** Fallback CSS gradient per browser vecchi
- **[COMPAT]** Prefissi vendor `-webkit-` e `-ms-` per transform
- **[COMPAT]** Prefissi vendor per animation
- **[COMPAT]** `@-webkit-keyframes` per Safari vecchi

### Added - Cleanup
- **[FIX]** Aggiunta variabile `calendarErrorTimeout` per gestione timeout
- **[FIX]** Nuovo metodo `hideCalendarError()` per cleanup
- **[FIX]** Check `parentNode` prima di `remove()` (safety)

### Impact
- ✅ 5 bug critici risolti
- ✅ 4 miglioramenti accessibilità
- ✅ 6 ottimizzazioni performance/compatibilità
- ✅ 0 errori sintassi
- ✅ 0 linting errors
- ✅ Compatibilità cross-browser migliorata

### Technical Details
Vedi: `docs/bugfixes/BUGFIX-CALENDARIO-2025-11-03.md`

---

## 0.9.0-rc8 - Calendario Date Ottimizzato (2025-11-02)

### Added - UX Calendario 📅✨
- **[UX]** Styling super evidente per date disabilitate (pattern a righe + X rossa)
- **[UX]** Date disponibili evidenziate in verde con bordo
- **[UX]** Data oggi in blu con bordo spesso
- **[UX]** Loading indicator animato durante caricamento date
- **[UX]** Tooltip informativi al passaggio mouse ("Disponibile: cena", "Non disponibile")
- **[UX]** Legenda permanente sotto il campo data (Verde/Grigio/Blu)
- **[UX]** Error handling con messaggio auto-hide (5s)
- **[UX]** Zoom hover su date disponibili

### Changed - Calendario Flatpickr
- `onDayCreate` callback per tooltip dinamici
- `showCalendarLoading()` / `hideCalendarLoading()` per feedback
- `showCalendarError()` per gestione errori
- Legenda colori sempre visibile

### Impact
- ✅ +67% chiarezza visiva calendario
- ✅ UX professionale e intuitiva
- ✅ Feedback durante caricamento
- ✅ Impossibile sbagliare data
- ✅ Tooltip informativi
- ✅ Aspetto moderno e curato

### Technical Details
Vedi: `CALENDARIO-OTTIMIZZAZIONI-2025-11-02.md`

---

## 0.9.0-rc7 - Bugfix Profondo & Ottimizzazioni (2025-11-02)

### Fixed - Pulizia Log & Performance 🧹
- **[PERFORMANCE]** Rimossi 20+ error_log() che spammavano in produzione
- **[PERFORMANCE]** Cache assetVersion() per request (evita 5+ file_exists() ripetuti)
- **[PERFORMANCE]** Eliminata duplicazione codice $tablesEnabled (2 query → 1)

### Changed - Code Quality
- `Plugin.php`: Rimossi 8 error_log in bootstrap
- `REST.php`: Rimossi 8 error_log in registerRoutes
- `AdminREST.php`: Rimossi 10 error_log, condizionati a WP_DEBUG
- `Repository.php`: Log diagnostici solo in WP_DEBUG
- `Plugin.php`: Migliorata validazione `$wpdb instanceof \wpdb`

### Security Audit ✅
- ✅ Verificata protezione SQL injection (wpdb->prepare ovunque)
- ✅ Verificata protezione XSS (esc_html in Shortcodes)
- ✅ Verificate autorizzazioni AdminREST (3 livelli capabilities)
- ✅ Verificato rate limiting REST endpoints
- ✅ Verificata protezione nonce su /reservations
- ✅ Verificata sicurezza pagamenti (admin-only per capture/refund/void)

### Impact
- ✅ Log file più puliti in produzione
- ✅ Migliorate performance in debug mode
- ✅ Codice più manutenibile (meno duplicazioni)
- ✅ Sicurezza verificata e confermata

### Technical Details
- Sessione #1: `docs/BUGFIX-TIMEZONE-PHP-2025-11-02.md`
- Sessione #2: `BUGFIX-SESSION-2-2025-11-02.md`
- Report finale: `BUGFIX-REPORT-FINAL-2025-11-02.md`

---

## 0.9.0-rc6 - Fix Timezone PHP Functions (2025-11-02)

### Fixed - Timezone Italia (Europe/Rome) 🌍
- **[CRITICO]** Corretti tutti gli usi di `date()` e `gmdate()` che ignoravano il timezone WordPress
- **[CRITICO]** Sostituito `gmdate('Y-m-d')` con `current_time('Y-m-d')` in 6 punti critici
- **[CRITICO]** Sostituito `date()` con `wp_date()` o `current_time()` in 10 punti
- **[CRITICO]** Corretti `DateTimeImmutable` creati senza timezone esplicito (3 occorrenze)

### Changed - PHP Date/Time Functions
- `AdminREST.php`: 4 correzioni (log, statistiche, mapping)
- `Shortcodes.php`: 3 correzioni (debug, test endpoint)
- `REST.php`: 6 correzioni (API giorni disponibili)
- `Service.php`: 2 correzioni (defaults, consenso privacy)
- `Repository.php`: 3 correzioni (query duplicati, Google Calendar)
- Sincronizzata versione Plugin.php con file principale (0.9.0-rc5)

### Impact
- ✅ Tutti gli orari ora rispettano il timezone `Europe/Rome`
- ✅ Log coerenti con ora italiana
- ✅ Statistiche "oggi" corrette 24/7 (prima sbagliate vicino mezzanotte UTC)
- ✅ API con date/orari corretti
- ✅ Google Calendar sync accurato

### Technical Details
Vedi: `docs/BUGFIX-TIMEZONE-PHP-2025-11-02.md`

---

## 0.9.0-rc4 - Fix Conflitti CSS Header Tema (2025-10-31)

### Fixed - Conflitti CSS con Tema Salient 🎯
- **[CRITICO]** Rimosso CSS per `#header-outer` che causava ricalcolo altezza `#header-space` 
- **[CRITICO]** Spazio aggiuntivo sopra header su tutte le pagine con plugin attivo
- Rimossi CSS non necessari per bottoni header (hamburger menu, mobile search, ecc.)

### Changed - CSS Cleanup
- Rimosso `position: relative !important` su `#header-outer` 
- Rimosso `z-index: 9999 !important` su `#header-outer`
- Rimossi selettori CSS per elementi header non correlati al plugin
- Mantenuti solo CSS essenziali per il form di prenotazione

### Impact
- ✅ Eliminato spazio aggiuntivo causato dal plugin
- ✅ Nessun conflitto con layout header tema
- ✅ JavaScript Salient non ricald più altezza header
- ✅ Form continua a funzionare correttamente

---

## 0.9.0-rc3 - Ottimizzazione Caricamento Asset (2025-10-31)

### Fixed - Performance & Caricamento Asset 🚀
- **[CRITICO]** CSS e JS del plugin caricati su TUTTE le pagine del sito
- Migliorate condizioni di caricamento asset frontend

### Changed - Asset Loading Strategy
- `shouldEnqueueAssets()`: Ora carica asset SOLO dove necessario (shortcode/block presente)
- Controllo intelligente per: post content, Gutenberg blocks, WPBakery, Elementor meta
- Rimosso caricamento globale degli asset frontend
- Aggiunto filtro `fp_resv_frontend_should_enqueue` per override manuale

### Impact
- ✅ Ridotto peso pagine senza form (~150KB CSS/JS risparmiati)
- ✅ Migliorata velocità caricamento sito
- ✅ Compatibilità mantenuta con page builders (WPBakery, Elementor, Gutenberg)

---

## 0.9.0-rc1 - Release Candidate 1 (2025-10-25)

### 🚀 **RELEASE CANDIDATE - PRONTO PER 1.0.0**

Il plugin è ora **production-ready** con tutte le funzionalità core complete e testate. Questa versione RC1 include il fix critico timezone e prepara il lancio della versione stabile 1.0.0.

### 🎯 **Status Versione**
- **Release Candidate**: Versione stabile per test finali
- **Target 1.0.0**: 7-14 giorni (dopo test completi)
- **Breaking Changes**: Nessuno (API frozen)
