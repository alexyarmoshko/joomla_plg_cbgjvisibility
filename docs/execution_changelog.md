# Execution Changelog

## 2026-02-18 (code review v3 fixes)

- Fixed follow-up: corrected WebAsset script URI from `plg_system_cbgjvisibility/js/sanitization-test.js` to `plg_system_cbgjvisibility/sanitization-test.js` so Joomla resolves the installed media path correctly and the Testing tab button handler binds.
- Fixed Low (#5): moved `SanitizationTestField` inline JavaScript to external asset `media/js/sanitization-test.js` loaded via Joomla WebAssetManager.
- Fixed Low (#5): replaced legacy `.well` wrapper with Bootstrap 5 `card card-body` container in admin Testing tab UI.
- Fixed Low (#5): removed inline `<pre style="...">` and switched to Bootstrap utility classes.
- Added `<media>` section in `cbgjvisibility.xml` to package/install the new admin JavaScript asset.
- Updated `Makefile` `PLUGIN_FILES` to include `media/` so release ZIPs ship the new JS asset.
- Updated `docs/execution_plan.md` repository layout and Makefile snippet to include `media/`.
- Findings #1-#4 from `docs/code_review.v3.md` were already addressed in the current working tree; no extra code changes were required for those items.

## 2026-02-16

- Implemented plugin scaffolding from the execution plan:
  - `cbgjvisibility.xml`
  - `services/provider.php`
  - `src/Extension/Cbgjvisibility.php`
  - `src/Field/VerifyField.php`
  - `language/en-GB/plg_system_cbgjvisibility.ini`
  - `language/en-GB/plg_system_cbgjvisibility.sys.ini`
- Implemented `onAfterRender` admin-first branching and guest HTML stripping.
- Added marker-based early exit and selector-driven stripping with nested block handling for description.
- Implemented `onAjaxCbgjvisibility` compatibility scanner with:
  - admin-context enforcement
  - `core.manage` ACL check for `com_plugins`
  - CSRF token validation
  - per-file found/missing class report
  - verified-version persistence in `#__extensions` params on successful verification.
- Added Compatibility tab verify UI via custom form field.
- Added packaging files: `Makefile`, `plg_system_cbgjvisibility.update.xml`.
- Updated documentation artifacts: `README.md`, `RELEASE.md`, and execution-plan status.
- Added `.env` to `.gitignore` to enforce secret-file hygiene.
- Hardened compatibility verification after staging feedback:
  - Added additional AJAX event subscription alias (`onAjaxcbgjvisibility`) for case-variant dispatch compatibility.
  - Normalized verify URL plugin parameter handling (`plugin=cbgjvisibility`) in admin field.
  - Improved verifier response rendering to show full JSON when `data` is empty (instead of rendering only `[]`).
  - Made installed-version resolution resilient to CB element naming differences in `#__comprofiler_plugin`.
  - Added explicit Compatibility-tab warning when the plugin is disabled.
  - Added explicit empty-data hint so `{"data":[]}` points to plugin-enable/cache steps.
  - Removed forced page auto-reload after successful verify so payload remains visible for troubleshooting.
  - Added robust CB version detection for environments where `#__comprofiler_plugin` has no `version` column:
    - resolves versions from DB `params` when available,
    - falls back to CB manifest XML files (`cbgroupjive.xml`, `cbgroupjiveevents.xml`, `cbactivity.xml`),
    - blocks saving verified snapshots when any tracked version remains unavailable.
  - Fixed form overwrite issue: after successful Verify, JS now writes resolved versions into hidden params inputs so a subsequent Joomla "Save" does not clear verified versions.

## 2026-02-16 (v0.2.0)

- Removed compatibility verification system (~50% of codebase):
  - Deleted `src/Field/VerifyField.php` (337 lines).
  - Removed 12 methods, 4 constants, and admin warning logic from `Cbgjvisibility.php`.
  - Removed Compatibility tab (3 hidden fields, verify button, version table) from manifest.
  - Removed 28 language strings related to verification/compatibility.
- Replaced with live sanitization testing:
  - New AJAX endpoint (`onAjaxCbgjvisibility`) fetches front page as guest, checks marker presence and CSS class absence.
  - New `src/Field/SanitizationTestField.php` — simple button + result display (~95 lines).
  - New Testing tab in plugin settings.
- Added 12 new language strings for testing UI.
- Updated README and execution plan.
- Bumped version to 0.2.0.

## 2026-02-17 (code review v1 fixes)

- Fixed High: `fetchFrontPageAsGuest()` now strips `/administrator/` from `Uri::root()` to ensure it always fetches the public site root, even when called from admin context.
- Fixed Low: sanitization test class check changed from substring `strpos()` to regex with `class="..."` attribute matching and word boundaries, preventing false positives from class names appearing in unrelated text/script contexts.
- Fixed Low: README.md `RELEASE.md` path corrected to `docs/RELEASE.md`.
- Fixed Medium: `execution_plan.md` cleaned up — removed references to the deleted compatibility verification system (Step 3, test matrix, success criteria, risks) and replaced with current sanitization test descriptions.
- Skipped #2 (service provider constructor pattern): Joomla 5.3 official docs still use the same `new Plugin($dispatcher, $config)` pattern; not actionable yet.
- Added maintenance mode detection: `fetchFrontPageAsGuest()` now returns HTTP status code; `runSanitizationTest()` checks for 503 and reports a clear "site offline" error instead of a misleading inconclusive result.

## 2026-02-17 (code review v2 fixes)

- Fixed High (#1): `fetchFrontPageAsGuest()` URL resolution hardened — now uses `parse_url()` to isolate the path component and strip `/administrator` safely, avoiding false matches in the domain name.
- Fixed Medium (#2): service provider now uses `new Cbgjvisibility($config)` + `setDispatcher()` instead of passing `DispatcherInterface` as constructor arg. The old pattern triggers `E_USER_DEPRECATED` and will break in Joomla 7.0 (CMSPlugin will drop `DispatcherAwareInterface`).
- Fixed Medium (#3): `execution_plan.md` — corrected "General + Compatibility tabs" to "General, Selectors, and Testing tabs"; replaced "Verify button" reference with Test Sanitization; fixed "AJAX + CLI" to "AJAX from admin Testing tab".
- Fixed Low (#4): replaced hard-coded English fallback `"No event data found on page."` in `SanitizationTestField.php` JS with the localized `PLG_SYSTEM_CBGJVISIBILITY_TEST_INCONCLUSIVE` language string.
- Fixed Low (#5): removed `verify_peer => false` / `verify_peer_name => false` from the `file_get_contents` SSL stream context fallback, restoring default peer verification.

## Deferred

- Environment-backed functional validation (DDEV/site checks, end-to-end guest/admin behavior) was intentionally skipped per user request because test environment is not available.
