# Hardening Verification Log

Data verifica: 2025-10-28

Questa scheda riepiloga lo stato di applicazione delle misure elencate in `docs/HARDENING-GUIDE.md`. Le evidenze raccolte durante il closeout sono referenziate nel report `docs/HARDENING-CLOSEOUT.md` e negli allegati interni indicati di seguito.

## 1. WordPress & hosting

| Controllo | Stato | Evidenza / Azione successiva |
|-----------|-------|------------------------------|
| Aggiornamenti core/plugin | PASS | Ticket OPS-482 – screenshot WP Admin che conferma auto-update core/plugin e versioni PHP 8.1.12 / WP 6.5.3 (HARDENING-CLOSEOUT §2.1). |
| Salts & keys | PASS | Log WP-CLI `wp config shuffle-salts` del 2025-10-07 archiviato in `SecOps/2025-10/hardening-closeout/wp-cli.txt`; data registrata nel vault (HARDENING-CLOSEOUT §2.2). |
| `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` | PASS | Diff `wp-config.php` salvata nel ticket OPS-486 con screenshot impostazioni costanti; verificata su produzione e staging. |
| MFA / SSO admin | PASS | Ticket SEC-213 – policy Okta + IP allow-list su `/wp-admin` applicata via Cloudflare Access, con prova login MFA. |
| Backup & restore | PASS | Report OPS-497 – esito restore da snapshot del 2025-10-12 eseguito in ambiente isolato, include tempo ripristino e validazione integrità. |
| Centralizzazione log | PASS | Documento OPS-501 – shipping syslog verso stack ELK, retention 120gg; screenshot data retention su bucket S3. |

## 2. Configurazione plugin

| Area | Stato | Evidenza / Azione successiva |
|------|-------|------------------------------|
| REST pubblico | PASS | `docs/SECURITY-REPORT.md` conferma nonce/rate limit per `/fp-resv/v1/reservations`; ticket SEC-220 verifica CAPTCHA attivo e inforza score ≥0.7. |
| Notifiche SMTP | PASS | Test invio `wp fp_resv_send_test_email` del 2025-10-04 (ticket APP-158) con log provider Brevo SMTP e ricezione presso casella monitor. |
| Tracking & consensi | PASS | Registri QA `tracking-onepage-2025-10-05.md` con screenshot DebugView; allineato con `fpResvTracking.updateConsent` (HARDENING-CLOSEOUT §3.2). |
| Pagamenti Stripe | PASS | Ticket FIN-305 – rotazione chiavi live/test, `webhook_secret` aggiornato e memorizzato in vault condiviso; prova webhook firmato riuscita. |
| Brevo | PASS | Ticket APP-164 – screenshot configurazione API key con scope limitato, mapping prefissi aggiornato e sync liste IT/EN verificato con seed demo. |
| Google Calendar | PASS | Ticket APP-171 – progetto OAuth dedicato, busy-check loggato e procedura revoca refresh documentata; screenshot console Google Cloud. |
| Log & retention | PASS | Cron esterno orchestrato su `cron.fp-resv.local` (ticket OPS-503) esegue `wp cron event run fp_resv_rotate_logs` ogni notte; log di esecuzione allegato. |

## 3. Server & rete

| Controllo | Stato | Evidenza / Azione successiva |
|-----------|-------|------------------------------|
| HTTPS + HSTS | PASS | Scanner Qualys SSL Labs del 2025-10-07 (ticket SEC-228) – rating A+, HSTS preload confermato. |
| WAF / rate limiting | PASS | SEC-228 allega configurazione ModSecurity OWASP CRS + rate limit 120 req/5 min; screenshot dashboard WAF. |
| `xmlrpc.php` | PASS | Ticket SEC-224 – rewrite Nginx che blocca `xmlrpc.php` con allow-list IP di fallback e monitor 7gg senza falsi positivi. |
| Security headers | PASS | Rapporto `security-headers-2025-10-18.md` (SEC-228) mostra CSP, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin, Permissions-Policy personalizzata. |
| Isolamento processi | PASS | Ticket OPS-512 – PHP-FPM pool dedicato all'utenza `fpresv`, directory cache montata read-only; screenshot configurazione chroot + test scrittura fallito atteso. |

## 4. Stato follow-up

Tutte le azioni pianificate sono concluse. Nessun ulteriore follow-up richiesto; monitoraggio ordinario demandato ai riesami trimestrali di sicurezza.

## 5. Collegamenti

- Closeout completo: `docs/HARDENING-CLOSEOUT.md`.
- Playbook operativo: `docs/HARDENING-FOLLOWUP.md`.
- Checklist di riferimento: `docs/HARDENING-GUIDE.md`.
- Esiti sicurezza REST: `docs/SECURITY-REPORT.md`.
- Audit QA completo: `docs/QA-AUDIT.md`.
