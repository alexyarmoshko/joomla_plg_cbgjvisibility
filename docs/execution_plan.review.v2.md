# Review: `execution_plan.md` (v2)

## Scope
Reviewed updated `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md` after applying v1 feedback, using the Joomla reference site context at `C:\Users\alex\repos\ecskc.eu.sites\prod-html`.

## Findings (ordered by severity)

### 1. High: `onAfterRender()` flow is still ambiguous and can skip admin version checks
- Plan lines:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:93`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:94`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:101`
- Issue:
  - Event contract states `onAfterRender()` handles both site guest stripping and admin mismatch warnings.
  - But the step list starts with guest-only early-exit logic and does not explicitly branch admin first.
- Risk:
  - If implemented literally, admin version check path may not execute.
- Fix:
  - Define explicit control flow in plan:
    1. If admin client: run version-check/warning logic and return.
    2. Else if site client + guest + HTML response: run stripping.
    3. Else return.

### 2. Medium: Legacy verified param names remain in Compatibility section
- Plan lines:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:129`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:150`
- Current manifest params:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:83`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:84`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:85`
- Issue:
  - Compatibility prose still references `verified_cb_version`, which is no longer the canonical name.
- Fix:
  - Replace all legacy references with:
    - `verified_cbgroupjive_version`
    - `verified_cbgroupjiveevents_version`
    - `verified_cbactivity_version`

### 3. Medium: Success criteria still conflict with attending-page expected behavior
- Plan lines:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:272`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:300`
- Issue:
  - Test matrix correctly says attending page should remain unchanged (no target classes).
  - Success criterion still says all 5 rendering paths hide configured sections.
- Fix:
  - Update success criterion to “all applicable rendering paths hide configured sections for guests” and explicitly exclude `attending.php` from stripping expectations.

## Validation notes on applied changes

The following v1 requests appear correctly implemented:

- Guest check switched to truthy style:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:101`
- Security section added with admin client, ACL, CSRF, structured errors:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:152`-`159`
- Joomla event contract table added:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:87`-`96`
- `cbgroupjiveevents` added to version tracking + hidden verified param:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:84`
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:131`-`137`
- `TEMPLATE_MAP` moved to selector-key model (runtime class resolution):
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:187`-`212`
- Regex assumption documented as accepted risk:
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:119`
- Full functional test matrix added (includes attending/admin/version lifecycle):
  - `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:262`-`280`

## Recommended final edits before implementation

1. Add a short pseudocode block for `onAfterRender()` branch ordering (admin vs site/guest).
2. Run a global rename pass in the doc to remove all stale `verified_cb_version` references.
3. Align Success Criteria with test matrix language around `attending.php` (no stripping expected).

## Overall verdict
The plan is substantially improved and close to implementation-ready. After the 3 consistency fixes above, it should be decision-complete and safe to execute.

## Outcomes

### CC

All three v2 findings fixed:

onAfterRender() control flow — already addressed in the previous round (pseudocode with admin-first branching was added). The review confirmed this needed the explicit branch ordering, which is now there.
Stale verified_cb_version — found and replaced 2 remaining occurrences with the correct param names.
Success criteria — changed "All 5 rendering paths" to "All 4 applicable rendering paths" with explicit note that attending.php has no target classes.

### Codex

Findings

Low: One remaining wording inconsistency between implementation steps and success criteria.
execution_plan.md (line 313) still says “confirm all 5 rendering paths,” while execution_plan.md (line 327) correctly defines hiding behavior for 4 applicable paths (with attending.php excluded from stripping).
Suggested tweak: change Step 6 to “confirm all 5 rendering-path behaviors (4 stripped + attending unchanged)” or “confirm all 4 applicable stripping paths.”
Verdict

The three v2 issues are fixed:

Admin-first onAfterRender() control flow is now explicit (execution_plan.md (line 101)).
Stale verified_cb_version references are gone; compatibility params are consistent (execution_plan.md (line 83)).
Success criteria now correctly call out 4 applicable stripping paths (execution_plan.md (line 327)).
After the single wording cleanup above, this plan is effectively final and implementation-ready.