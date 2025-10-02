# FP Restaurant Reservations — Audit Report

## Executive Summary
- **Total issues:** 5 (1 high, 4 medium)
- **Breakdown by area:** frontend (2), security (1), i18n (1), tooling (1)
- Critical user journeys affected: payment integrations, survey feedback intake, reservation submission accessibility.

## Top Risks
1. **Stripe/Google integrations fail to load** — Dynamic `import()` of CDN scripts rejects, leaving payment and calendar features inoperative on the booking form. 【F:assets/js/fe/onepage.js†L1560-L1583】
2. **Survey CSRF exposure** — Customer feedback endpoint accepts forged POSTs because the form never prints or verifies a nonce. 【F:templates/survey/form.php†L21-L57】【F:src/Domain/Surveys/REST.php†L52-L120】
3. **Reservation form breaks without JavaScript** — The shortcode outputs a form with `action=""` and no server handler, so bookings silently fail when scripts are blocked. 【F:templates/frontend/form.php†L84-L135】

## Frontend / Backend Flow Status
| Flow | Frontend | Backend |
| --- | --- | --- |
| Reservation submission | ❌ Requires JS; no graceful degradation. 【F:templates/frontend/form.php†L84-L135】 | ✅ REST create endpoint validates and processes payloads. |
| Payment confirmation | ❌ Stripe loader fails to execute, so checkout UI never appears. 【F:assets/js/fe/onepage.js†L1560-L1570】 | ✅ REST payment handlers enforce nonce and repository updates. |
| Survey feedback | ⚠️ Form renders but lacks nonce/CSRF protection. 【F:templates/survey/form.php†L21-L57】 | ⚠️ REST route validates token but does not enforce nonce. 【F:src/Domain/Surveys/REST.php†L52-L120】 |

## Security Findings
| ID | Severity | CWE | Location | Notes |
| --- | --- | --- | --- | --- |
| ISS-0002 | Medium | CWE-352 | `templates/survey/form.php`, `src/Domain/Surveys/REST.php` | Survey submissions can be forged without nonce protection. |

## Performance / Reliability
- Payment and Google Calendar loaders rely on module imports for classic scripts, causing runtime rejection and leaving integrations unavailable. 【F:assets/js/fe/onepage.js†L1560-L1583】

## Internationalization
- Admin dashboards ship Italian fallback strings (`'Prenotazione aggiornata.'`, `'Ripristinare lo stile di default?'`, `'Prenotazioni'`) outside of translation functions; untranslated text surfaces whenever localized data is missing. 【F:assets/js/admin/agenda-app.js†L854-L909】【F:assets/js/admin/style-preview.js†L46-L54】【F:assets/js/admin/reports-dashboard.js†L329-L329】

## Tooling Gaps
- `npm run lint:js` fails because no ESLint configuration is present, preventing automated JS linting. 【F:package.json†L8-L14】【a6f012†L1-L20】

## Assumptions & Unknowns
- Audit relied on static inspection; no live WordPress instance was spun up. Stripe/Google loaders were not exercised against live services but failure is inferred from module semantics.
- Survey token secrecy was assumed; if reservation IDs/emails leak, lack of nonce magnifies impact.
