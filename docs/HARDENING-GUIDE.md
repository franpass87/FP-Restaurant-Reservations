# Hardening configurazioni FP Restaurant Reservations

Questo vademecum riepiloga le azioni consigliate per rafforzare la postura di sicurezza del plugin FP Restaurant Reservations dopo il cleanup del repository. Le checklist sono organizzate per livello (WordPress, server, plugin) così da agevolare la verifica periodica.

## 1. WordPress & hosting

| Controllo | Azione raccomandata | Note |
|-----------|--------------------|------|
| Aggiornamenti core/plugin | Abilita auto-update o programma finestre di manutenzione regolari | Evita versioni non supportate (WP ≥ 6.5, PHP ≥ 8.1) |
| Salts & keys | Rigenera `AUTH_KEY` & co. dopo l'onboarding | Usa `wp config shuffle-salts` o script CI |
| Modifica file da dashboard | Imposta `DISALLOW_FILE_EDIT` e `DISALLOW_FILE_MODS` | Riduce rischio upload malevoli |
| Accesso admin | Enforce MFA/SSO e IP allow-list su `/wp-admin` | Valuta integrazione reverse proxy |
| Backup & restore | Pianifica snapshot automatici con retention ≥ 30 giorni | Test periodici dei restore |
| Registri | Centralizza access log, PHP error log e audit plugin | Supporta incident response |

## 2. Configurazione plugin

| Area | Hardening | Dettagli |
|------|-----------|----------|
| REST pubblico | Mantieni CAPTCHA/nonce attivi in produzione | Conferma `rate_limit` ≠ 0 e aggiorna API key CAPTCHA |
| Notifiche | Usa SMTP autenticato o provider transazionale | Verifica TLS e mittente DMARC aligned |
| Tracking & consensi | Precompila privacy URL e double opt-in marketing | Informa i clienti su retention dati |
| Pagamenti Stripe | Attiva `webhook_secret` e limita accesso dashboard | Ruota chiavi live/test ciclicamente |
| Brevo | Limita scope API key alle liste necessarie | Abilita logging `wp_fp_brevo_log` e retention |
| Google Calendar | Crea progetto OAuth dedicato e applica domain verification | Revoca refresh token al cambio staff |
| Report & log | Configura rotazione log (es. `wp_cli cron event run fp_resv_rotate_logs`) | Mantieni log solo per il periodo legale |

## 3. Server & rete

1. HTTPS obbligatorio con TLS ≥ 1.2, HSTS (preload opzionale) e certificati rinnovati automatici.
2. Firewall applicativo con regole OWASP CRS, rate limiting IP e protezione bot.
3. Disabilita `xmlrpc.php` se non strettamente necessario.
4. Imposta header di sicurezza: `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
5. Isola processi PHP con utente dedicato e file-system read-only su `/wp-content/uploads/fp-resv-cache` ove possibile.

## 4. Procedure operative

- **Verifica trimestrale**: esegui `docs/QA-AUDIT.md` sezione 15 (Sicurezza & GDPR) e aggiorna questo documento con eventuali variazioni.
- **Onboarding staff**: condividi policy su gestione prenotazioni, accesso CRM, trattamento dati personali.
- **Incident response**: definisci playbook per compromissione account, leak dati o indisponibilità infrastruttura.
- **Revisione permessi**: rivedi ruoli WordPress e accessi API dopo turnover.

## 5. Checklist rapida (stampabile)

- [ ] Aggiornamenti core/plugin automatici
- [ ] Salts/chiavi rigenerate negli ultimi 12 mesi
- [ ] File editing disabilitato
- [ ] MFA obbligatoria per admin/editor
- [ ] Backup giornalieri con test restore
- [ ] SMTP/transazionale configurato con TLS
- [ ] Consensi privacy/marketing verificati
- [ ] Chiavi API Stripe/Brevo/GCal aggiornate
- [ ] Log ruotati e retention impostata
- [ ] Firewall & HTTPS monitorati

Aggiorna la checklist ad ogni rilascio maggiore del plugin o modifica infrastrutturale, documentando le evidenze in `docs/QA-AUDIT.md` e nel registro `docs/HARDENING-VERIFICATION.md`; per il coordinamento operativo delle attività pendenti utilizza il playbook `docs/HARDENING-FOLLOWUP.md`.
