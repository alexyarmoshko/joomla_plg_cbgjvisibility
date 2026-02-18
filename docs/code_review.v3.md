# Code Review: `plg_system_cbgjvisibility` (v3)

Date: 2026-02-17
Reviewer scope:
- Plugin repo: `c:\Users\alex\repos\joomla_plg_cbgjvisibility`
- Synced site copy: `c:\Users\alex\repos\ecskc.eu.sites\prod-html\plugins\system\cbgjvisibility`

## Sync Check

Re-validated in this pass: synced files still match between both repos.
- `cbgjvisibility.xml`
- `services/provider.php`
- `src/Extension/Cbgjvisibility.php`
- `src/Field/SanitizationTestField.php`
- `language/en-GB/plg_system_cbgjvisibility.ini`
- `language/en-GB/plg_system_cbgjvisibility.sys.ini`

## Findings (ordered by severity)

### 1. High: Sanitization test can hit admin root instead of guest-facing site front page
- `src/Extension/Cbgjvisibility.php:98` allows the test only in administrator context.
- `src/Extension/Cbgjvisibility.php:166` uses `Uri::root()` to build the fetch URL.
- In Joomla admin context, root resolution is derived from admin base and can include `/administrator` (`c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Uri\Uri.php:141`, `c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Uri\Uri.php:188`).
- Risk: false PASS/FAIL/inconclusive outcomes because the test may analyze admin/login HTML instead of public guest output.

### 2. Medium: DI provider uses deprecated CMSPlugin construction pathway
- `services/provider.php:26` passes `DispatcherInterface` into plugin constructor.
- Joomla marks this constructor route deprecated (`c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Plugin\CMSPlugin.php:121`).
- Risk: forward-compatibility issue with future Joomla major versions.

### 3. Medium: `execution_plan.md` still mixes removed compatibility-era details with current v0.2.0 design
- `docs/execution_plan.md:307` still refers to "General + Compatibility tabs".
- `docs/execution_plan.md:139` still references a "Verify button" scanning template source files.
- `docs/execution_plan.md:378` states replacement includes "AJAX + CLI", but there is no `tests/test_sanitization.sh` or equivalent CLI test script in this repo.
- Risk: maintainers may follow obsolete instructions and misinterpret expected behavior.

### 4. Low: SSL verification is disabled in non-cURL HTTP fallback
- `src/Extension/Cbgjvisibility.php:197`
- `src/Extension/Cbgjvisibility.php:198`
- Risk: weaker transport security if execution falls back to `file_get_contents()` over HTTPS.

### 5. Low: Admin custom field uses inline script/style and legacy wrapper class
- Inline style and script are embedded in form field output (`src/Field/SanitizationTestField.php:48`, `src/Field/SanitizationTestField.php:50`).
- Uses legacy `.well` wrapper (`src/Field/SanitizationTestField.php:43`) in Joomla 5 admin (Bootstrap 5 era).
- Risk: maintainability/CSP friction and inconsistent backend UI styling.

## Notes

- Previous v2 localization finding is resolved: the inconclusive fallback now uses language key `PLG_SYSTEM_CBGJVISIBILITY_TEST_INCONCLUSIVE` (`src/Field/SanitizationTestField.php:36`, `src/Field/SanitizationTestField.php:87`).
- Core sanitization logic remains compact and readable; the primary correctness concern is still the front-page test target URL.
