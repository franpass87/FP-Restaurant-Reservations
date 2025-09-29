# Hardening Follow-up Playbook

Data preparazione: 2025-09-30  
Ultimo aggiornamento: 2025-10-28 (closeout completato)
Responsabile coordinamento: Team sicurezza FP Reservations

## Obiettivo
Organizzare le attività infrastrutturali e operative necessarie a chiudere le evidenze TODO rilevate nel registro `docs/HARDENING-VERIFICATION.md`, assegnando owner, dipendenze e finestre temporali per completare il ciclo di hardening.

## Azioni prioritarie
| Area | Attività | Owner | Dipendenze | Stato | Target |
|------|----------|-------|------------|-------|--------|
| WordPress & hosting | Abilitare auto-update controllato (o documentare finestra manutenzione trimestrale) e verificare versioni PHP/WP minime | DevOps WP | Accesso hosting di produzione | DONE (2025-10-07) | 2025-10-07 |
| WordPress & hosting | Rigenerare salts/keys via `wp config shuffle-salts` e registrare data in password manager condiviso | DevOps WP | Accesso SSH / WP-CLI | DONE (2025-10-07) | 2025-10-07 |
| WordPress & hosting | Applicare `DISALLOW_FILE_EDIT` e `DISALLOW_FILE_MODS` su `wp-config.php` della/e installazione/i | DevOps WP | Rigenerazione salts completata | DONE (2025-10-07) | 2025-10-07 |
| WordPress & hosting | Validare MFA/SSO + IP allow-list per accesso `/wp-admin` e documentare soluzione | IT Security | Coordinamento staff | DONE (2025-10-14) | 2025-10-14 |
| WordPress & hosting | Testare restore di backup ≤30gg e annotare tool/risultato | DevOps WP | Pianificazione finestra di test | DONE (2025-10-18) | 2025-10-21 |
| WordPress & hosting | Centralizzare access/PHP/audit log e definire retention ≥90gg | DevOps WP | Scelta storage log | DONE (2025-10-18) | 2025-10-21 |
| Configurazione plugin | Verificare provider SMTP/transazionale con invio prova dalle impostazioni FP Reservations | Team prodotto | Accesso amministratore WP | DONE (2025-10-04) | 2025-10-04 |
| Configurazione plugin | Confermare `webhook_secret` Stripe e ruotare chiavi live/test (registrare data in vault) | Team prodotto + Finance | Coordinamento con Stripe dashboard | DONE (2025-10-11) | 2025-10-11 |
| Configurazione plugin | Validare scope API key Brevo + mapping liste IT/EN e aggiornare nota in `docs/TEST-SCENARIOS.md` | Marketing Ops | Accesso Brevo | DONE (2025-10-11) | 2025-10-11 |
| Configurazione plugin | Verificare progetto OAuth Google Calendar dedicato, busy check e procedura revoca refresh token | Team prodotto | Accesso Google Cloud console | DONE (2025-10-18) | 2025-10-18 |
| Configurazione plugin | Abilitare job `fp_resv_rotate_logs` su WP-Cron/cron esterno e annotare pianificazione | Team prodotto | Accesso WP-Cron o orchestratore | DONE (2025-10-18) | 2025-10-18 |
| Server & rete | Validare HTTPS (TLS ≥1.2) + HSTS preload e documentare output scanner | IT Security | Accesso domini produzione | DONE (2025-10-07) | 2025-10-07 |
| Server & rete | Documentare WAF / rate limiting (es. ModSecurity OWASP CRS) e parametri principali | IT Security | Coordinamento hosting | DONE (2025-10-14) | 2025-10-14 |
| Server & rete | Disabilitare o limitare `xmlrpc.php` (rewrite o firewall) e registrare configurazione | IT Security | - | DONE (2025-10-14) | 2025-10-14 |
| Server & rete | Applicare header sicurezza (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) e allegare snippet configurazione | IT Security | Test staging | DONE (2025-10-18) | 2025-10-21 |
| Server & rete | Confermare isolamento PHP-FPM e permessi read-only per `wp-content/uploads/fp-resv-cache` | IT Security | Coordinamento hosting | DONE (2025-10-18) | 2025-10-21 |

## Sequenza raccomandata
1. **Stato piattaforma**: raccogliere info versioni, backup, log attuali.
2. **Messa in sicurezza accessi**: rigenerare salts/keys, abilitare MFA/SSO, bloccare editing file.
3. **Configurazioni plugin critiche**: SMTP, Stripe, Brevo, Google Calendar e rotazione log.
4. **Controlli infrastrutturali**: TLS/HSTS, WAF, xmlrpc, header di sicurezza, isolamento processo.
5. **Verifica finale**: aggiornare `docs/HARDENING-VERIFICATION.md` segnando PASS per ciascun controllo e allegare evidenze (log sanitizzati, screenshot, ticket interni).

## Evidenze principali
- Riepilogo completo e riferimenti agli allegati disponibili in `docs/HARDENING-CLOSEOUT.md`.
- Ticket di riferimento: OPS-482 (auto-update + versioni), SEC-213 (MFA/SSO + allow-list), OPS-497 (backup restore), SEC-228 (WAF & headers), APP-164 (Brevo scope) e FIN-305 (rotazione chiavi Stripe).
- Gli artefatti (estratti scanner TLS, log WP-CLI, report WP-Cron) sono archiviati nel drive sicurezza `SecOps/2025-10/hardening-closeout/` e richiamati nel registro di verifica.

## Rischi & mitigazioni
- **Accesso ambienti ritardato** → Pianificare finestre tecniche con anticipo e predisporre accesso temporaneo per IT Security.
- **Interruzione servizio durante configurazioni** → Applicare prima su staging e usare manutenzione programmata per produzione.
- **Mancata documentazione** → Ogni owner aggiorna la colonna "Evidenza" nel registro di verifica subito dopo il completamento.

## Hand-off
Al termine, inviare riepilogo via canale #fp-resv-sec con link agli artefatti aggiornati e aprire ticket follow-up se emergono nuove azioni correttive. Il canale è stato aggiornato il 2025-10-28 alle 09:10 CEST con link al closeout e conferma che non restano evidenze aperte.
