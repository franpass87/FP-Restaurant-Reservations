# Remediation Checklist

## High Severity
- [x] ISS-0001 — Stripe and Google Calendar integrations load via dynamic import of non-module scripts (assets/js/fe/onepage.js) (commit: f7f5948)

## Medium Severity
- [x] ISS-0002 — Survey submission form lacks nonce-based CSRF protection (templates/survey/form.php, src/Domain/Surveys/REST.php) (commit: 38c27ee)
- [x] ISS-0003 — Reservation form has no server fallback when JavaScript is disabled (templates/frontend/form.php) (commit: 35a00ce)
- [x] ISS-0004 — Admin JS bundles contain hard-coded Italian fallbacks (assets/js/admin/agenda-app.js, assets/js/admin/style-preview.js, assets/js/admin/reports-dashboard.js) (commit: 9c8bae0)
- [x] ISS-0005 — npm lint:js script fails because ESLint config is missing (package.json) (commit: 7b4b36d)
