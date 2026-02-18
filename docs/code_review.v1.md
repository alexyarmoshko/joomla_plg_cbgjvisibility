# Code Review: `plg_system_cbgjvisibility` (v1)

Date: 2026-02-17
Reviewer scope:
- Plugin repo: `c:\Users\alex\repos\joomla_plg_cbgjvisibility`
- Synced site copy: `c:\Users\alex\repos\ecskc.eu.sites\prod-html\plugins\system\cbgjvisibility`

## Sync Check

The plugin is present in the Joomla site repo and appears synced.

Verified files (SHA-256 match between both repos):
- `cbgjvisibility.xml`
- `services/provider.php`
- `src/Extension/Cbgjvisibility.php`
- `src/Field/SanitizationTestField.php`
- `language/en-GB/plg_system_cbgjvisibility.ini`
- `language/en-GB/plg_system_cbgjvisibility.sys.ini`

## Findings (ordered by severity)

### 1. High: Sanitization test may fetch admin root instead of public front page
- `src/Extension/Cbgjvisibility.php:98` enforces admin context for running the test.
- `src/Extension/Cbgjvisibility.php:166` uses `Uri::root()` for the fetch URL.
- In Joomla admin context, URI base/root resolution can include `/administrator` (`c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Uri\Uri.php:141`, `c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Uri\Uri.php:188`).
- Impact: test may validate the wrong page and return misleading PASS/FAIL/inconclusive results.

### 2. Medium: Service provider uses deprecated plugin constructor pattern
- `services/provider.php:26` constructs plugin with dispatcher argument.
- Joomla CMSPlugin marks this signature path as deprecated (`c:\Users\alex\repos\ecskc.eu.sites\prod-html\libraries\src\Plugin\CMSPlugin.php:121`).
- Impact: forward-compatibility risk for future Joomla major versions.

### 3. Medium: `execution_plan.md` still contains removed compatibility-system implementation steps
- Removed system is still described as active implementation in:
  - `docs/execution_plan.md:320`
  - `docs/execution_plan.md:324`
  - `docs/execution_plan.md:361`
- The same file also says that system was removed in v0.2.0:
  - `docs/execution_plan.md:387`
- Impact: maintainers can follow outdated instructions and reintroduce dead design paths.

### 4. Low: README points to wrong release-notes path
- `README.md:23` references `RELEASE.md` at repo root.
- Actual file is `docs/RELEASE.md`.
- Impact: minor comprehension/documentation friction.

### 5. Low: Sanitization test class check is substring-based
- `src/Extension/Cbgjvisibility.php:141` uses `strpos($html, $className)`.
- Impact: possible false positives/negatives when class tokens appear in unrelated text/script contexts.

## Comprehension & Maintainability Notes

- Code organization is clear and compact for v0.2.0 (`src/Extension/Cbgjvisibility.php`, `src/Field/SanitizationTestField.php`).
- Naming and guard clauses are readable.
- Main maintainability gap is documentation drift in `docs/execution_plan.md`.

## Residual Risk / Test Gaps

- No automated test suite for the sanitizer/test endpoint behavior was found in this repo.
- The guest sanitization logic currently relies on regex/string matching and is sensitive to markup changes in upstream CB templates.
