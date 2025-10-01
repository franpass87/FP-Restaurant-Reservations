# Future Improvement Tasks

This list reflects the current codebase as of the latest repository refresh, ensuring the suggestions remain compatible with the present implementation.

Below are ten proposed enhancements for the plugin, captured as actionable task stubs for future pull requests.

:::task-stub{title="Add REST parameter schema for reservation creation"}
- In `src/Domain/Reservations/REST.php`, extend the `/reservations` route registration so the `args` key lists every accepted field (date, time, party, contact fields, consent flags, etc.) with appropriate `sanitize_callback`/`validate_callback`.
- Where useful, reuse the existing `param()` helper or dedicated static callbacks to keep sanitization logic consistent with `handleCreateReservation`.
- Update or add integration tests under `tests/Integration/Reservations/RestTest.php` to cover success and failure paths when required arguments are missing or invalid, ensuring the new schema works as intended.
:::

:::task-stub{title="Introduce configurable management capability"}
- Create a single helper (e.g., `Security::managementCapability()`) that returns a filterable capability string with `manage_options` as the default, and use it in `Security::currentUserCanManage()`.
- Replace every direct `'manage_options'` reference across admin controllers and REST permission callbacks (Reservations, Tables, Closures, Reports, Diagnostics, QA, etc.) with the new helper result.
- Document the filter name in the README so administrators know how to override the capability for custom roles.
:::

:::task-stub{title="Wire up script translations"}
- After registering/enqueuing the frontend handles in `src/Frontend/WidgetController.php::enqueueAssets()`, call `wp_set_script_translations()` for `fp-resv-onepage-module` (and the legacy handle if kept) using the `fp-restaurant-reservations` text domain and the plugin languages directory.
- Repeat the same for each admin JS entry point (agenda, tables, closures, reports, settings, etc.) right after their `wp_enqueue_script()` calls so those bundles can load translated strings.
- Ensure the build pipeline produces the matching `*.json` files under `languages/` and adjust any documentation that lists build prerequisites.
:::

:::task-stub{title="Add filters for REST rate limits"}
- In `src/Domain/Reservations/REST.php`, wrap the magic numbers used in the `RateLimiter::allow()` calls with filters like `fp_resv_rate_limit_availability` and `fp_resv_rate_limit_reservations`, passing the limit, window seconds, and request context.
- Apply the filtered values when setting `Retry-After` headers so clients get consistent guidance.
- Document the new filters (README or docs/QA) with example snippets for raising or lowering the thresholds.
:::

:::task-stub{title="Refine reservation query by room"}
- Update `loadReservations()` in `src/Domain/Reservations/Availability.php` to append a prepared `AND room_id = %d` clause (and binding) when `$roomId` is supplied, instead of filtering after the query.
- Keep the current behaviour for reservations without a room assignment (so global counts still work) by adjusting the SQL logic accordingly.
- Verify availability responses through integration tests (or new ones) to confirm the results stay unchanged while query performance improves.
:::

:::task-stub{title="Introduce Availability service integration tests"}
- Create a new test case (e.g., `tests/Integration/Reservations/AvailabilityTest.php`) that boots the `Availability` service with a fake `$wpdb` dataset covering multiple rooms, closures, and overlapping reservations.
- Assert that the service returns blocked/full/available statuses, respects waitlist flags, and applies closure capacity reductions as expected.
- Include regression tests for tricky inputs (invalid dates, party sizes) to complement the service’s input validation.
:::

:::task-stub{title="Translate reservation validation exceptions"}
- Replace the hard-coded exception messages in `assertPayload()` with calls to `__()` using the plugin text domain, keeping the exception types intact.
- Update any tests that assert on exact strings to expect the translated versions (or use translation helpers to keep tests locale-agnostic).
- Scan nearby validation paths for similar plain-English strings and bring them into the translation system for consistency.
:::

:::task-stub{title="Localise domain runtime exceptions"}
- Wrap each user-facing `RuntimeException`/`InvalidArgumentException` message in the affected services (`src/Domain/Payments/StripeService.php`, `src/Domain/Closures/Service.php`, `src/Domain/Tables/LayoutService.php`, etc.) with `__()` and the plugin text domain.
- For formatted strings (e.g., Stripe API errors), ensure placeholders remain intact and translators can understand the context by adding translator comments if necessary.
- Add regression tests or adjust existing ones so they no longer rely on exact English strings, instead checking codes or using translation helpers.
:::

:::task-stub{title="Add Retry-After to reservation throttling response"}
- In `src/Domain/Reservations/REST.php::handleCreateReservation()`, convert the rate-limit branch to return a `WP_REST_Response` (or modify the error) with a `Retry-After` header matching the cooldown window.
- Mirror the header logic in any other rate-limited endpoints (e.g., QA seeder) for consistent client behaviour.
- Extend the REST tests to confirm the header is emitted alongside the 429 status.
:::

:::task-stub{title="Cover StripeService amount logic in tests"}
- Add a dedicated test class (e.g., `tests/Unit/Payments/StripeServiceTest.php`) that exercises `calculateReservationAmount()`, `shouldRequireReservationPayment()`, and `toMinorUnits()` under different capture strategies, deposits, and zero-decimal currencies.
- Mock the repository dependency so tests can focus purely on computation without hitting the database.
- Ensure the new tests guard against regressions when Stripe settings or pricing rules change.
:::
