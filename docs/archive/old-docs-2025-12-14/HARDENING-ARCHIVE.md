# Hardening Archive Handoff

Data archivio: 2025-10-28
Coordinamento: SecOps FP Reservations

## Sommario artefatti
| Artefatto | Posizione repository sicurezza | Note |
|-----------|--------------------------------|------|
| Registro verifica hardening | secops://fp-resv/2025/hardening/verification-log.pdf | Esportato da `docs/HARDENING-VERIFICATION.md` in PDF sanitizzato; contiene evidenze PASS per tutti i controlli. |
| Playbook follow-up | secops://fp-resv/2025/hardening/followup-playbook.md | Versione firmata digitalmente dopo la chiusura delle attività; include cronologia modifiche. |
| Report closeout | secops://fp-resv/2025/hardening/closeout-report.md | Copia del documento `docs/HARDENING-CLOSEOUT.md` con firme dei referenti. |
| Log allegati | secops://fp-resv/2025/hardening/logs.zip | Bundle cifrato (AES-256) con log sanitizzati, password nel vault "SecOps / FP-Reservations". |
| Ticket evidenze | secops://fp-resv/2025/hardening/tickets.csv | Estratto dal tracker interno (Jira) con ID, owner e data chiusura. |

## Procedura archiviazione
1. Esporta i documenti di verifica e closeout in formato testo/PDF, applicando la sanitizzazione dei dati sensibili (PII rimossa, token offuscati).
2. Carica gli artefatti nel repository sicuro `secops://fp-resv/2025/hardening/` mantenendo la struttura tabellare riportata.
3. Aggiorna il registro del vault con:
   - Password del bundle cifrato `logs.zip`.
   - Link permanenti (permalink) agli artefatti.
   - Data di upload e checksum SHA-256.
4. Invia comunicazione sul canale `#fp-resv-sec` con riepilogo: artefatti caricati, posizione, e owner per future consultazioni.
5. Conserva copia offline degli artefatti critici (log e report firmati) seguendo la policy "3-2-1" (3 copie, 2 supporti, 1 off-site).

## Verifiche post-archiviazione
- ✅ Accesso ai file nel repository sicurezza testato con account break-glass.
- ✅ Checksums registrati nel vault e verificati tramite `sha256sum`.
- ✅ Ticket interni collegati alle attività di follow-up riportano stato `Closed` con collegamento al percorso `secops://`.
- ✅ Reminder pianificato su calendario SecOps per revisione annuale (2026-10-01) e riesame policy hardening.

## TODO residui
- Pianificare post-mortem con team cross-funzionale (Prodotto, DevOps, SecOps) entro 2025-11-15.
- Aggiornare playbook hardening in caso emergano nuove lesson learned durante il post-mortem.

## Riferimenti
- `docs/HARDENING-CLOSEOUT.md`
- `docs/HARDENING-FOLLOWUP.md`
- `docs/HARDENING-VERIFICATION.md`
