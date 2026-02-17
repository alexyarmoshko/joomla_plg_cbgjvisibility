# Execution Changelog

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
- Updated README, execution plan, and CLAUDE.md.
- Bumped version to 0.2.0.

## Deferred

- Environment-backed functional validation (DDEV/site checks, end-to-end guest/admin behavior) was intentionally skipped per user request because test environment is not available.
