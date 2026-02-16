# Review: `execution_plan.md` (v1)

## Scope
Reviewed `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md` against the Joomla reference site at `C:\Users\alex\repos\ecskc.eu.sites\prod-html`.

## Findings (ordered by severity)

### 1. High: Guest check can mis-detect guests
- Plan line: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:92`
- Current text uses strict compare: `guest !== 1`.
- Risk: If Joomla identity exposes boolean `true` for guests, strict compare fails and plugin exits early for guests.
- Fix: Use truthy guest check (`if (!$app->getIdentity()->guest) return;`) or cast before compare.

### 2. High: AJAX verify endpoint is missing explicit security requirements
- Plan lines: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:143`-`164`
- Endpoint behavior is described, but auth/ACL/CSRF requirements are not explicitly required.
- Risk: Unauthorized probing of filesystem checks and/or untrusted state changes.
- Fix: Require admin client + ACL check (`core.manage`/`core.admin`) + CSRF token validation for verification and any persisted updates.

### 3. Medium: Event/method contract is internally inconsistent
- Plan line says one method only: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:88`
- Later requires additional handlers: `onAjaxGjvisibility` (`:143`-`:145`) and uses `onExtensionAfterSave` logic (`:120`, `:229`-`:235`).
- Fix: Add explicit event surface section listing all handlers and signatures (Joomla 5 subscriber style).

### 4. Medium: Version tracking likely misses relevant plugin
- Query in plan tracks only `cbgroupjive` and `cbactivity`: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:120`-`124`
- The target templates are under `cbgroupjiveevents` paths; this plugin should be explicitly considered in version tracking.
- Fix: Track `cbgroupjiveevents` too, or document why parent `cbgroupjive` alone is guaranteed sufficient.

### 5. Medium: Verification design conflicts (parameter-driven vs hardcoded)
- Parameter-driven class checks described: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:148`
- Hardcoded class map also defined: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:170`-`187`
- Fix: Choose one model clearly:
  - strict canonical class map, or
  - fully parameter-driven verification map.

### 6. Medium: Regex pattern is too rigid
- Pattern: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:105`
- Assumes exact attribute shape/order and double-quoted class attribute.
- Fix: Make matching tolerant to attribute order/spacing/quotes and keep description block handled by nesting-aware parser.

### 7. Low: `attending.php` expectations are under-defined
- Map comment says no `gjGroupEvent*` classes there: `C:\Users\alex\repos\joomla_plug_gjvisibility\docs\execution_plan.md:184`-`186`
- But success criteria/tests still include attending path broadly.
- Fix: Add explicit acceptance criteria for attending view (expected visible/hidden behavior).

## Validation notes from reference site

Confirmed target class usage in current site templates:
- `components/com_comprofiler/plugin/user/plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/events.php`
- `components/com_comprofiler/plugin/user/plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/module.php`
- `components/com_comprofiler/plugin/user/plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/activity.php`
- `components/com_comprofiler/plugin/user/plug_cbactivity/templates/default/activity/core/group/event.php`

Confirmed `attending.php` does not contain `gjGroupEventHost|Group|Guests|Description` wrappers:
- `components/com_comprofiler/plugin/user/plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/attending.php`

## Recommended plan improvements (actionable)

1. Add a dedicated **Security** section for AJAX verify endpoint (ACL + CSRF + admin-only + error handling).
2. Add a **Joomla Event Contract** section listing every subscribed event and handler signature.
3. Clarify **Version Source of Truth** (`cbgroupjive`, `cbgroupjiveevents`, `cbactivity`) and exact mismatch logic.
4. Resolve **verification model** ambiguity (hardcoded canonical map vs selector-param driven checks).
5. Add an explicit **test matrix**:
   - guest vs logged-in
   - site vs admin client
   - HTML vs non-HTML responses
   - version mismatch warning lifecycle
   - missing file / missing class / changed element handling.

## Overall verdict
Plan is strong and directionally correct, but should be updated for security hardening and decision clarity before implementation.

## Outcomes

### CC

All review findings addressed. Summary of changes:

Finding 1: Changed guest check from guest !== 1 to !guest (truthy)
Finding 2: Added Security section requiring admin client check, ACL core.manage on com_plugins, CSRF token validation, and structured error responses
Finding 3: Added Joomla Event Contract table listing all 3 subscribed events/handlers; removed "one method" claim
Finding 4: Added cbgroupjiveevents to version tracking (3 plugins tracked now), added verified_cbgroupjiveevents_version param
Finding 5: Changed TEMPLATE_MAP to use selector keys ('host', 'group', etc.) resolved at runtime from Selectors params — no more hardcoded class names in the map
Finding 6: Added note documenting the double-quote/attribute-order assumption with explanation of safe failure mode (partial accept — not changing the regex itself)
Finding 7: Replaced simple test checklist with a full test matrix (15 scenarios) including explicit attending.php expected behavior and admin/version lifecycle scenarios

### Codex

Findings (ordered by severity)

High: onAfterRender() flow conflicts with admin version-check goal.
execution_plan.md (line 93) and execution_plan.md (line 94) say the same handler runs for site guest stripping and admin mismatch warnings, but execution_plan.md (line 101) starts with guest-only early exit logic.
If implemented in that order, admin version checks will never run.
Fix: define explicit branch order in onAfterRender():

if admin client: run version-check/warning logic and return
else if site client + guest: run stripping logic.
Medium: Old parameter names remain in compatibility text.
execution_plan.md (line 129) and execution_plan.md (line 150) still reference verified_cb_version, which conflicts with the new parameter set at execution_plan.md (line 83) to execution_plan.md (line 85).
Fix: replace legacy names with verified_cbgroupjive_version, verified_cbgroupjiveevents_version, verified_cbactivity_version everywhere.

Medium: Success criteria contradict attending-page behavior.
execution_plan.md (line 272) correctly says attending page should remain unchanged, but execution_plan.md (line 300) says all 5 paths hide configured sections.
Fix: change success criterion to “all applicable rendering paths” and explicitly exclude attending from stripping.

What is improved and now solid

Guest check fix is in place (execution_plan.md (line 101)).
Security section is materially stronger (execution_plan.md (line 152) to execution_plan.md (line 159)).
Event contract is clearer (execution_plan.md (line 87) to execution_plan.md (line 96)).
cbgroupjiveevents tracking and new verified param were added (execution_plan.md (line 84), execution_plan.md (line 134) to execution_plan.md (line 137)).
Selector-key TEMPLATE_MAP is cleaner and consistent with configurable selectors (execution_plan.md (line 187) to execution_plan.md (line 212)).
Test matrix is much better and explicit (execution_plan.md (line 262) onward).