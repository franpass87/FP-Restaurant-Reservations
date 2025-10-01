# Plugin Audit Report — FP Restaurant Reservations — 2025-10-01

## Summary
- Files scanned: 138/138 (audit complete)
- Issues found: 4 (Critical: 0 | High: 2 | Medium: 2 | Low: 0)
- Key risks:
  - Survey escalation emails fail silently, leaving negative customer feedback unseen.
  - Attribution cookies are set without checking consent, creating GDPR exposure.
  - Mismatched plugin version metadata breaks cache-busting and complicates support.
- Recommended priorities: 1) Restore survey alert recipients, 2) Gate attribution cookies on consent, 3) Align reported plugin version and update the release script to keep constants in sync.

## Manifest mismatch
- Previous manifest hash `6d11ed4956fa97a10bf1b63ee97f13e1b2ae5e54e9cba8e5bbd95cbebc02acb8` omitted QA tooling (`tests/**`, `tools/bump-version.php`) and vendor assets (`assets/vendor/chart.*`).
- Rebuilt manifest `5ad9d334137b619637165039cac9eea10c430b496fccea3b7c4d2ee977d2558a` now tracks 138 files; audit resumed from the first missing path and completed.

## Issues
### [High] Survey alert recipients reduced to literal "Array"
- ID: ISSUE-001
- File: src/Domain/Brevo/AutomationService.php:441-483
- Snippet:
  ```php
  $emails   = $this->parseEmails((string) ($settings['restaurant_emails'] ?? ''));
  $emails   = array_merge($emails, $this->parseEmails((string) ($settings['webmaster_emails'] ?? '')));
  ...
  private function parseEmails(string $list): array {
      if ($list === '') {
          return [];
      }

      $parts = array_map('trim', explode(',', $list));
      return array_values(array_filter($parts, static fn (string $email): bool => $email !== ''));
  }
  ```
- Diagnosis: The notification settings store `restaurant_emails` / `webmaster_emails` as arrays (sanitized via `sanitizeEmailList`). Casting them to string yields the literal `"Array"`, so `parseEmails()` returns `['Array']`. The ensuing `wp_mail()` call therefore targets an invalid recipient and survey alerts never leave the site.
- Impact: Functional / UX — negative NPS submissions are never surfaced to staff, undermining follow-up workflows and automation triggers.
- Repro steps:
  1. Configure at least one restaurant email in the admin notifications screen.
  2. Submit a negative survey (score < threshold).
  3. Inspect the Brevo mail log / email inbox: no alert is delivered; logs show target `Array`.
- Proposed fix (concise):
  ```php
  $emails = [];
  $restaurant = $settings['restaurant_emails'] ?? [];
  $webmaster  = $settings['webmaster_emails'] ?? [];

  if (is_array($restaurant)) {
      $emails = array_merge($emails, $restaurant);
  }
  if (is_array($webmaster)) {
      $emails = array_merge($emails, $webmaster);
  }

  $emails = array_values(array_unique(array_filter($emails, 'is_email')));
  ```
- Side effects / Regression risk: Low — only affects alert recipients; other Brevo flows remain untouched when the array guard is added.
- Est. effort: S
- Tags: #notifications #brevo #email #functional

### [High] Attribution cookie ignores consent state
- ID: ISSUE-002
- File: src/Domain/Tracking/Manager.php:67-103
- Snippet:
  ```php
  add_action('init', [$this, 'captureAttribution']);
  ...
  public function captureAttribution(): void {
      ...
      DataLayer::storeAttribution($params, (int) ($this->settings()['tracking_utm_cookie_days'] ?? 90));
  }
  ```
- Diagnosis: `captureAttribution()` runs on every front-end page load and immediately persists UTM parameters via `DataLayer::storeAttribution`, which sets the `fp_resv_utm` cookie unconditionally. No consent check (e.g. `Consent::has('ads')`) is performed, so marketing identifiers are saved before the visitor opts in.
- Impact: Security / Compliance — violates GDPR/ePrivacy consent requirements on EU-hosted sites, exposing merchants to regulatory fines.
- Proposed fix (concise):
  ```php
  if (!Consent::has('ads') && !Consent::has('analytics')) {
      return;
  }
  DataLayer::storeAttribution(...);
  ```
  Alternatively, defer storing until consent is granted and keep UTM data transient in memory meanwhile.
- Side effects / Regression risk: Medium — gating on consent alters attribution capture for opt-out users; analytics dashboards must tolerate missing data when consent is denied.
- Est. effort: M
- Tags: #privacy #consent #tracking #gdpr

### [Medium] Plugin header version and runtime constant diverge
- ID: ISSUE-003
- File: fp-restaurant-reservations.php:3-13 & src/Core/Plugin.php:58-60
- Snippet:
  ```php
  * Version: 0.1.1
  ...
  final class Plugin {
      public const VERSION = '0.1.0';
  }
  ```
- Diagnosis: The bootstrap header advertises v0.1.1, while `Plugin::VERSION` (used for asset cache busting, diagnostic output, REST metadata, etc.) still reports 0.1.0. This mismatch causes stale assets to be served after upgrades and confuses support/debug logging.
- Impact: Functional / Supportability — browsers may keep cached CSS/JS despite an update; system health checks report the wrong version.
- Proposed fix (concise): Update `Plugin::VERSION` (and any related constants/tests) to `0.1.1` and keep header + constant in sync for future releases.
- Side effects / Regression risk: Low — purely metadata alignment.
- Est. effort: S
- Tags: #release #versioning #cache

### [Medium] Version bump script never updates runtime constant
- ID: ISSUE-004
- File: tools/bump-version.php:6-73
- Snippet:
  ```php
  $pattern = '/^(\s*\*\s*Version:\s*)([^\r\n]+)/mi';
  if (!preg_match($pattern, $contents, $matches)) {
      fwrite(STDERR, "Version line not found in plugin header.\n");
      exit(1);
  }

  $updated = preg_replace_callback(
      $pattern,
      static function (array $match) use ($newVersion): string {
          return $match[1] . $newVersion;
      },
      $contents,
      1,
      $count
  );
  ```
- Diagnosis: The release helper only rewrites the header line inside `fp-restaurant-reservations.php`. It never adjusts `Plugin::VERSION` (and related asset enqueue versions), so each automated bump reintroduces the mismatch flagged in ISSUE-003 and leaves browsers serving stale bundles.
- Impact: Release / Supportability — scripted releases silently regress asset cache-busting and misreport the running version until someone edits the constant by hand.
- Proposed fix (concise): Update the script to also replace the version string in `src/Core/Plugin.php` (and any companion constants/tests) before writing the new tag.
- Side effects / Regression risk: Low — touches release tooling only; no runtime behavior when unused.
- Est. effort: S
- Tags: #release #automation #versioning

## Conflicts & Duplicates
None observed.

## Deprecated & Compatibility
- The plugin header still lists `Requires at least: 6.5`; update to `6.6` to match the stated support target.
- No deprecated WordPress/PHP APIs detected in the scanned files.

## Performance Hotspots
No blocking performance issues identified in this pass.

## i18n & A11y
- Strings in templates and admin screens use the declared text domain consistently.
- No accessibility regressions noted during static review.

## Test Coverage (if present)
- PHPUnit scaffolding exists but automated coverage for Brevo automation and consent flows is absent. Consider adding unit tests around notification routing and consent-gated attribution.

## Next Steps (per fase di FIX)
- Priority order: ISSUE-001 → ISSUE-002 → ISSUE-003 → ISSUE-004.
- Safe-fix batch plan:
  - Batch 1: ISSUE-001 (Brevo automation email array handling).
  - Batch 2: ISSUE-002 (consent-aware attribution) — coordinate with tracking QA.
  - Batch 3: ISSUE-003 (version constant alignment) and ISSUE-004 (teach bump script to keep constants in sync).
