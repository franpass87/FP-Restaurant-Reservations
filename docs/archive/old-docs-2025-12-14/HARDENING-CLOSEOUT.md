# Hardening Closeout Report

Data closeout: 2025-10-28  
Coordinamento: Team sicurezza FP Reservations (lead: L. Moretti)

## 1. Sintesi esecutiva
- Tutti i controlli elencati nel registro `docs/HARDENING-VERIFICATION.md` risultano **PASS** con evidenze archiviate nel drive `SecOps/2025-10/hardening-closeout/`.
- Le configurazioni infrastrutturali (TLS/HSTS, WAF, header di sicurezza, isolamento PHP-FPM) sono state validate con scanner esterni e audit manuali.
- Le integrazioni applicative (SMTP, Stripe, Brevo, Google Calendar) hanno eseguito test end-to-end e rotazioni chiavi/documentazione vault.
- Non sono emerse non conformità residue; le attività ricorrenti sono state programmate tramite cron esterno e reminder trimestrali.

## 2. Cronologia attività
| Data | Attività | Artefatti |
|------|----------|-----------|
| 2025-10-04 | Test invio SMTP e verifica logging WP (`wp_fp_mail_log`) | Ticket APP-158, log provider Brevo |
| 2025-10-07 | Auto-update, rigenerazione salts/keys, abilitazione HSTS preload | Ticket OPS-482, OPS-486, SEC-228 |
| 2025-10-11 | Rotazione chiavi Stripe e aggiornamento scope Brevo | Ticket FIN-305, APP-164 |
| 2025-10-14 | MFA/SSO Okta + allow-list, WAF rate limiting, hardening xmlrpc | Ticket SEC-213, SEC-224, SEC-228 |
| 2025-10-18 | Restore backup, header sicurezza, cron rotazione log, validazione OAuth GCal | Ticket OPS-497, OPS-503, APP-171 |
| 2025-10-21 | Convalida isolamento PHP-FPM e retention log centralizzati | Ticket OPS-501, OPS-512 |
| 2025-10-28 | Revisione finale, aggiornamento documentazione, notifica canali interni | Canale #fp-resv-sec, questo report |

## 3. Evidenze riassunte
### 3.1 WordPress & hosting
- **Auto-update & versioni** – Screenshot WP Admin (OPS-482) con core/plugin auto-update ON, PHP 8.1.12, WP 6.5.3.  
- **Salts/keys** – Output `wp config shuffle-salts` firmato e archiviato, con registrazione data nel vault condiviso.  
- **Hardening wp-config** – Diff che mostra `DISALLOW_FILE_EDIT`/`DISALLOW_FILE_MODS` e commento audit.  
- **MFA/SSO** – Policy Okta applicata con enforcement per gruppi Admin/Editor e IP allow-list Cloudflare Access.  
- **Backup & restore** – Report ripristino staging (OPS-497) con screenshot tavola tempi e checklist QA.  
- **Centralizzazione log** – Diagramma pipeline syslog → Logstash → S3 con retention 120gg e rotazione oraria.

### 3.2 Configurazione plugin
- **SMTP** – Log invio prova `fp_resv_send_test_email` + ricevuta casella monitor.
- **Stripe** – Nuove chiavi annotate in vault, screenshot webhook secret e log `payment_intent.succeeded` firmato.
- **Brevo** – API key con scope limitato (`contacts`, `automation`), mapping prefissi aggiornato e test seed `scripts/seed.php`.
- **Google Calendar** – Progetto OAuth dedicato, verifica busy check e procedura di revoca eseguita su account dimostrativo.
- **Rotazione log** – Cron esterno confermato con log `fp_resv_rotate_logs` (success in 2.1s) e retention su DB a 90 giorni.
- **Tracking & consensi** – DebugView GA4 e console `fpResvTracking` mostrano aggiornamento consensi post checkbox.

### 3.3 Server & rete
- **TLS/HSTS** – Report Qualys A+, HSTS preload valido, catena certificati aggiornata (Let's Encrypt 2025-12-01).  
- **WAF & rate limit** – Estratto regole ModSecurity + soglie 120 req/5min, bypass per IP allow-list staff.  
- **xmlrpc** – Rewrite Nginx 403 + allow-list API legacy; monitor grafana senza errori.  
- **Security headers** – Output scanner `securityheaders.com` grade A, CSP che limita a host ufficiali.  
- **Isolamento processi** – Config pool PHP-FPM `fpresv` con chroot `/var/www/fpresv`, volume cache read-only e test scrittura fallito come previsto.

## 4. Comunicazioni e hand-off
- Messaggio inviato sul canale Slack `#fp-resv-sec` (2025-10-28 09:10 CEST) con link a questo report, registro aggiornato e cartella allegati.  
- Promemoria trimestrale creato in Jira (SEC-TRI-2025Q4) per ripetere i controlli e aggiornare la documentazione.  
- Ownership mantenuta al team sicurezza; eventuali incidenti devono aprire ticket in coda SEC con riferimento "fp-resv-hardening".

## 5. Azioni future
- Monitorare i job `fp_resv_rotate_logs` e `fp_resv_retention_cleanup` tramite alert nel cron esterno.  
- Rieseguire lo scanner TLS dopo ogni rinnovo certificato.  
- Riesaminare il mapping Brevo in caso di nuove lingue o sedi.

## 6. Allegati
- `SecOps/2025-10/hardening-closeout/wp-cli.txt` – log rotazione salts.  
- `SecOps/2025-10/hardening-closeout/qualys-report.pdf` – estratto scanner TLS (consultazione interna).  
- `SecOps/2025-10/hardening-closeout/waf-rules.md` – regole ModSecurity e rate limit.  
- `SecOps/2025-10/hardening-closeout/cron-run-20251018.log` – esecuzione job rotazione log.  
- `SecOps/2025-10/hardening-closeout/debugview-ga4.png` – screenshot eventi tracking post-consenso.

## Archiviazione
- Gli artefatti elencati sono stati archiviati seguendo la procedura descritta in `docs/HARDENING-ARCHIVE.md`.
- I link SecOps e le checksum sono registrati nel vault SecOps insieme alle password del bundle cifrato.
