# Code Review: `plg_system_cbgjvisibility` (v2)

Date: 2026-02-17
Reviewer scope:
- Plugin repo: `c:\Users\alex\repos\joomla_plg_cbgjvisibility`
- Synced site copy: `c:\Users\alex\repos\ecskc.eu.sites\prod-html\plugins\system\cbgjvisibility`

Sync status:
- Re-checked in this pass: plugin exists in site repo and key files remain synced.

## Findings (ordered by severity)

### 1. High: Sanitization test can target admin root, not the public front page
- `src/Extension/Cbgjvisibility.php:98` restricts the test to administrator context.
- `src/Extension/Cbgjvisibility.php:166` uses `Uri::root()` to build the fetch URL.
- In Joomla admin app context, base/root resolution includes `/administrator` (`c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Uri\Uri.php:141`, `c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Uri\Uri.php:188`).
- Impact: the test may evaluate admin/login HTML instead of guest-facing site HTML, yielding misleading PASS/FAIL/inconclusive output.

### 2. Medium: Service provider uses deprecated constructor style for CMSPlugin
- `services/provider.php:26` instantiates plugin with `DispatcherInterface` as first argument.
- Joomla marks this constructor pathway as deprecated (`c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Plugin\CMSPlugin.php:121`).
- Impact: forward-compatibility risk for future Joomla major versions and unnecessary complexity today.

### 3. Medium: `execution_plan.md` still has outdated compatibility-era instructions
- `docs/execution_plan.md:307` still says manifest/config uses “General + Compatibility tabs”, but current plugin uses Testing tab.
- `docs/execution_plan.md:139` still references a “Verify button” scanning source files, which no longer exists in v0.2.0 flow.
- `docs/execution_plan.md:378` says replacement is “AJAX + CLI”, but this repo currently contains no `tests/test_sanitization.sh` (or other CLI test script).
- Impact: maintainers may follow obsolete guidance and misunderstand current feature behavior.

### 4. Low: Non-localized fallback UI text remains in admin JS
- `src/Field/SanitizationTestField.php:85` has hard-coded English fallback: `"No event data found on page."`.
- Impact: partial localization gap in administrator UX.

### 5. Low: Insecure SSL fallback settings in `file_get_contents` path
- `src/Extension/Cbgjvisibility.php:197` and `src/Extension/Cbgjvisibility.php:198` disable peer verification in SSL stream context.
- Impact: weaker transport integrity if fallback path is used over HTTPS.

## Notes

- Prior v1 item about README release-notes path is no longer valid; `README.md` now points to `docs/RELEASE.md` (`README.md:23`).
- The plugin code remains generally compact and readable; primary correctness risk is still the test target URL selection.
