# Execution Plan: Yak Shaver CB GroupJive Event Visibility plugin (plg_cbgjvisibility)

Joomla system plugin to hide selected CB GroupJive event details from guest (non-logged-in) users via `onAfterRender` HTML post-processing.

## Problem Statement

The ECSKC site currently hides event Host, Group link, Guest count, and event description from anonymous visitors by directly modifying CB GroupJive template files. These modifications break on every CB update because the installer overwrites them. Five separate template files render event details across three different plugins, and CB's trigger system cannot cover all of them.

## Approaches Considered

| Approach | Verdict | Reason |
| --- | --- | --- |
| **Joomla system plugin (onAfterRender)** | **Selected** | Covers all 5 rendering paths in one place; fully independent of CB updates; negligible performance cost |
| Standalone CB plugin (trigger-based) | Rejected | CB triggers (`gj_onAfterDisplayEvents`, `gj_onDisplayEvent`) only cover the group events tab; module, activity stream, and CB Activity core templates have no usable post-render triggers |
| CB Auto Actions (no-code) | Rejected | Same trigger coverage gap as above; also fragile HTML manipulation in a UI text field |
| Joomla template override | Rejected | CB plugins don't use Joomla's `/html/` override system; not applicable |
| Keep modifying core files + update script | Rejected | Still fragile; requires manual re-application after updates; 5 files across 2 plugins to maintain |

## Affected Rendering Locations

All templates output event cards with the same CSS class structure:

| # | Template File (relative to com_comprofiler/plugin/user/) | Plugin | Context |
| --- | --- | --- | --- |
| 1 | `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/events.php` | cbgroupjiveevents | Group events tab / all-events page |
| 2 | `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/module.php` | cbgroupjiveevents | Events Joomla module |
| 3 | `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/activity.php` | cbgroupjiveevents | GJ activity stream embed |
| 4 | `plug_cbactivity/templates/default/activity/core/group/event.php` | cbactivity | CB Activity stream core template |
| 5 | `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/attending.php` | cbgroupjiveevents | "Who's attending" list |

## Target CSS Classes to Strip

These are the `<div>` wrapper classes used consistently across all templates:

| CSS Class | Content | Default: hide from guests |
| --- | --- | --- |
| `gjGroupEventHost` | Event host name with profile link | Yes |
| `gjGroupEventGroup` | Group name with link | Yes |
| `gjGroupEventGuests` | Guest count / attending link | Yes |
| `gjGroupEventDescription` | Event description (cbMoreLess block) | Configurable (currently hidden in module/activity only) |

## Plugin Design

### Directory Structure

```text
plg_cbgjvisibility/
├── src/
│   └── Extension/
│       └── Cbgjvisibility.php         # Plugin class (onAfterRender + validation)
├── services/
│   └── provider.php                 # Joomla DI service provider
├── cbgjvisibility.xml                 # Manifest with config params
├── language/
│   └── en-GB/
│       └── plg_system_cbgjvisibility.ini    # Language strings
└── docs/
    └── execution_plan.md            # This file
```

### Manifest (cbgjvisibility.xml)

- Type: `system`
- Group: `system`
- Joomla compatibility: 5.x
- PHP minimum: 8.1
- Parameters (General tab):
  - `hide_host` (yes/no, default: yes) — Hide event host from guests
  - `hide_group` (yes/no, default: yes) — Hide group link from guests
  - `hide_guests` (yes/no, default: yes) — Hide guest count from guests
  - `hide_description` (yes/no, default: no) — Hide event description from guests
- Parameters (Selectors tab):
  - `element_host` (text, default: `div`) — HTML element wrapping event host
  - `class_host` (text, default: `gjGroupEventHost`) — CSS class of event host wrapper
  - `element_group` (text, default: `div`) — HTML element wrapping group link
  - `class_group` (text, default: `gjGroupEventGroup`) — CSS class of group link wrapper
  - `element_guests` (text, default: `div`) — HTML element wrapping guest count
  - `class_guests` (text, default: `gjGroupEventGuests`) — CSS class of guest count wrapper
  - `element_description` (text, default: `div`) — HTML element wrapping event description
  - `class_description` (text, default: `gjGroupEventDescription`) — CSS class of description wrapper
  - `marker_string` (text, default: `gjGroupEvent`) — Quick-check marker string for `strpos` early exit; should be a common substring of all target class names
- Parameters (Compatibility tab):
  - `verified_cbgroupjive_version` (hidden text) — Last CB GroupJive version verified by admin
  - `verified_cbgroupjiveevents_version` (hidden text) — Last CB GroupJive Events version verified by admin
  - `verified_cbactivity_version` (hidden text) — Last CB Activity version verified by admin

### Joomla Event Contract

The plugin subscribes to the following Joomla events (using Joomla 5 event subscriber pattern):

| Event | Handler | Context | Purpose |
| --- | --- | --- | --- |
| `onAfterRender` | `onAfterRender()` | Site (guest) | Strip configured HTML blocks from response body |
| `onAfterRender` | `onAfterRender()` | Admin | Check installed CB versions vs verified versions; enqueue warning if mismatch |
| `onAjaxCbgjvisibility` | `onAjaxCbgjvisibility()` | Admin | AJAX endpoint for Verify & Acknowledge button |

### Plugin Class (Cbgjvisibility.php)

#### `onAfterRender()`

```text
if admin client:
    run version-check/warning logic (compare installed vs verified versions)
    return

if not site client:
    return

if user is logged in (!guest):
    return

if response is not HTML (Content-Type check):
    return

body = getBody()

if strpos(body, markerString) === false:
    return

for each enabled hide_* param:
    strip matching HTML block from body

setBody(body)
```

Flow details:

1. **Admin branch** (runs first): If admin client, check installed CB versions against verified versions via DB query; if mismatch, `enqueueMessage` with warning. Return immediately — no HTML stripping in admin.
2. **Site client guard**: Early exit if not site client (e.g., API, CLI).
3. **Guest guard**: Early exit if user is logged in (`!$this->getApplication()->getIdentity()->guest`).
4. **Content-Type guard**: Early exit if response is not HTML.
5. **Marker guard**: Early exit if `strpos($body, $markerString) === false` — `$markerString` read from `marker_string` param (default: `gjGroupEvent`).
6. **Strip**: For each enabled parameter, run regex/nesting-aware replacement to remove the corresponding HTML block.
7. **Commit**: `setBody($body)`.

#### Regex Patterns

Each target is a self-contained `<div>` with a unique class. Use non-greedy matching within the div:

```php
// Pattern template (per CSS class), using configurable element and class:
'#<' . $element . '\s+class="[^"]*\b' . preg_quote($class) . '\b[^"]*">.*?</' . $element . '>#s'
```

The `s` (DOTALL) flag handles multi-line content. The element tag and class name are read from plugin parameters, with defaults matching current CB markup. For simple targets (Host, Group, Guests) that don't contain nested elements of the same type, non-greedy matching is safe.

**Assumption**: The pattern expects `class` as an attribute with double quotes (e.g., `<div class="...gjGroupEventHost...">`). This matches CB's consistent output across all 5 templates. If a future CB version changes attribute quoting or order, the regex will silently fail (no match = info leakage, no breakage), and the Verify button will still detect the class strings in the source files, alerting the admin.

For `gjGroupEventDescription`, the content includes nested divs (cbMoreLess), so a nesting-aware PHP function is used instead of regex: find the opening tag, count element nesting depth, and find the matching closing tag. This is more robust than regex for nested elements and works regardless of the configured element type.

### Compatibility Self-Check

The plugin's main risk is silent failure if CB changes the CSS class names it targets. To mitigate this, the plugin includes a built-in compatibility verification system.

#### How It Works

1. **Version tracking**: The plugin stores the last verified versions in hidden params `verified_cbgroupjive_version`, `verified_cbgroupjiveevents_version`, and `verified_cbactivity_version`.

2. **Installed version detection**: On each admin page load (via `onAfterRender` in admin context), the plugin queries `#__comprofiler_plugin` for the currently installed versions of all three relevant plugins:

   ```sql
   SELECT element, version FROM #__comprofiler_plugin
   WHERE element IN ('cbgroupjive', 'cbgroupjiveevents', 'cbactivity')
   ```

   `cbgroupjiveevents` is tracked separately because it owns 4 of the 5 target template files and has its own version number, even though it's distributed as part of the `cbgroupjive` package.

3. **Mismatch warning**: If any installed version differs from its last verified version, the plugin injects a Joomla system message (via `enqueueMessage`) on admin pages:
   > "GJ Visibility: CB GroupJive Events has been updated to 2.9.11 (verified: 2.9.10). Please verify that guest visibility rules still work and acknowledge the new version in plugin settings."

4. **Verify button**: The plugin's configuration (Compatibility tab) shows:
   - Current installed CB GroupJive / CB GroupJive Events / CB Activity versions (read-only display)
   - Last verified versions for each
   - A custom "Verify & Acknowledge" button that:
     - Scans all 5 CB template source files for the target CSS class strings
     - Reports per-file, per-class results (found/missing)
     - If all expected classes are present in their respective files, updates `verified_cbgroupjive_version` / `verified_cbgroupjiveevents_version` / `verified_cbactivity_version` to the current installed versions and shows a success message
     - If any classes are missing, shows a warning identifying which file(s) and class(es) changed

5. **First-run**: On initial install, all `verified_*_version` params are empty, which triggers the verification prompt immediately so the admin must confirm it works.

#### Security

The AJAX verification endpoint (`onAjaxCbgjvisibility`) must enforce:

- **Admin client only**: Early exit if `!$this->getApplication()->isClient('administrator')`
- **ACL check**: Require `core.manage` permission on `com_plugins` (`$this->getApplication()->getIdentity()->authorise('core.manage', 'com_plugins')`)
- **CSRF token validation**: Validate via `Session::checkToken('get')` or `Session::checkToken('post')` — the Verify button JS must include the token in the request
- **Error handling**: Return structured JSON error responses (not exceptions) for missing files, permission failures, etc.

#### Implementation Details

The verification logic lives in an AJAX endpoint handled by the plugin:

- Register a custom `onAjax` handler (`onAjaxCbgjvisibility`)
- The "Verify" button in plugin settings calls this via `index.php?option=com_ajax&plugin=cbgjvisibility&group=system&format=json&[token]=1`
- The handler:
  1. Resolves the absolute paths to all 5 template files using `JPATH_SITE . '/components/com_comprofiler/plugin/user/...'`
  2. For each file, reads its contents with `file_get_contents()` and searches for each target CSS class string (read from Selectors params: `class_host`, `class_group`, `class_guests`, `class_description`)
  3. Returns JSON result:

     ```json
     {
       "files": {
         "events.php": { "found": ["gjGroupEventHost", "gjGroupEventGuests"], "missing": [] },
         "module.php": { "found": [...], "missing": [...] },
         ...
       },
       "all_ok": true,
       "cbgroupjive_version": "2.9.10",
       "cbgroupjiveevents_version": "2.9.10",
       "cbactivity_version": "3.0.2"
     }
     ```

  4. Also checks whether the file exists at all (CB plugin may have been uninstalled or relocated)
- The button's JavaScript displays the result and, if `all_ok`, triggers a save of the verified versions

#### Template File Map

The plugin maintains a constant map of template paths and which **selector keys** each should contain. The actual class names are resolved at runtime from the Selectors tab params (`class_host`, `class_group`, etc.):

```php
private const TEMPLATE_MAP = [
    // relative to JPATH_SITE/components/com_comprofiler/plugin/user/
    'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/events.php' => [
        'host', 'group', 'guests', 'description',
    ],
    'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/module.php' => [
        'host', 'group', 'guests', 'description',
    ],
    'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/activity.php' => [
        'host', 'group', 'guests', 'description',
    ],
    'plug_cbactivity/templates/default/activity/core/group/event.php' => [
        'host', 'group', 'guests', 'description',
    ],
    'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/attending.php' => [
        // no target classes — attending list shows user names only
        // included in map so Verify reports its file existence status
    ],
];
```

At verification time, the handler resolves each key to its class value (e.g., `'host'` → `$this->params->get('class_host', 'gjGroupEventHost')`) and searches the file for that string.

#### Why File Scanning Over HTTP Request

An earlier design used an HTTP GET to a public page to check rendered output. File scanning is superior because:

- No real events needed in the database
- No configurable test URL to maintain
- No HTTP loopback issues (blocked by firewall, host resolution, etc.)
- Directly answers "do the templates still use these CSS classes?"
- Faster (reads 5 small files vs. rendering a full page)
- The only scenario it wouldn't catch is if the class strings exist in the file but the surrounding HTML structure changes (e.g. `<div>` becomes `<span>`). This is extremely unlikely given CB's stable template patterns, and if it happens the regex simply won't match — causing no harm, just info leakage, same as a missing class.

#### Edge Cases

- If a template file is missing (CB plugin uninstalled or relocated), the verification reports it as an error with the missing file path.
- The admin warning only appears when viewing the admin panel, not on the frontend, to avoid confusing regular users.

### Performance Notes

- Logged-in users: zero overhead (early return before any string processing)
- Guests: 2-4 `preg_replace` calls on ~50-200KB HTML; sub-millisecond cost
- No DOM parsing needed; regex is sufficient for the well-structured CB output

## Packaging & Setup

### Repository Layout

```text
joomla_plg_cbgjvisibility/          # Git repo root
├── src/
│   └── Extension/
│       └── Cbgjvisibility.php
├── services/
│   └── provider.php
├── language/
│   └── en-GB/
│       └── plg_system_cbgjvisibility.ini
├── cbgjvisibility.xml                  # Joomla manifest (version source of truth)
├── plg_system_cbgjvisibility.update.xml  # Joomla update server XML
├── Makefile
├── .gitignore
├── README.md
├── LICENSE
├── installation/                     # Build output (gitignored)
│   └── plg_system_cbgjvisibility-v*.zip
└── docs/
    └── execution_plan.md
```

### Makefile

Adapted from the `joomla_plug_cbbeforeregverify` pattern. Key differences: this is a Joomla system plugin (source at repo root, not under `components/`), version read from `<version>` tag (Joomla standard).

```makefile
PLUGIN_NAME := cbgjvisibility
PLUGIN_TYPE := system
PLUGIN_MANIFEST_XML := cbgjvisibility.xml
PLUGIN_UPDATE_XML := plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME).update.xml
INSTALL_DIR := installation

PLUGIN_VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(PLUGIN_MANIFEST_XML))

ZIP_VERSION := $(subst .,-,$(PLUGIN_VERSION))
ZIP_NAME := plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME)-v$(ZIP_VERSION).zip
ZIP_PATH := $(INSTALL_DIR)/$(ZIP_NAME)

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO ?= joomla_plg_cbgjvisibility
GITHUB_REF ?= $(PLUGIN_VERSION)

# Files/dirs to include in the ZIP (plugin installable content only)
PLUGIN_FILES := cbgjvisibility.xml services/ src/ language/

.PHONY: dist info clean

info:
	@echo "Plugin:          plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME)"
	@echo "Plugin version:  $(PLUGIN_VERSION)"
	@echo "Source:          $(PLUGIN_FILES)"
	@echo "Package output:  $(ZIP_PATH)"
	@echo "Manifest output: $(PLUGIN_UPDATE_XML)"

dist: $(ZIP_PATH)
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(ZIP_PATH)" || shasum -a 256 "$(ZIP_PATH)") | awk '{print $$1}' )"; \
	DOWNLOAD_URL="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(GITHUB_REF)/$(ZIP_NAME)"; \
	awk -v version="$(PLUGIN_VERSION)" -v url="$$DOWNLOAD_URL" -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(PLUGIN_UPDATE_XML)" > "$(PLUGIN_UPDATE_XML).tmp" && mv "$(PLUGIN_UPDATE_XML).tmp" "$(PLUGIN_UPDATE_XML)"
	@echo "Updated $(PLUGIN_UPDATE_XML)"

$(ZIP_PATH):
	@mkdir -p "$(INSTALL_DIR)"
	@rm -f "$(ZIP_PATH)"
	@zip -qr -X "$(ZIP_PATH)" $(PLUGIN_FILES) -x "*.DS_Store" -x "*/.DS_Store"
	@echo "Built $(ZIP_PATH)"

clean:
	@rm -f "$(ZIP_PATH)"
```

Key difference from the CB plugin Makefile: the `zip` command runs from the repo root and includes only `PLUGIN_FILES` (no `cd` into a subdirectory), because the Joomla system plugin source is at the repo root.

### Update Server XML (`plg_system_cbgjvisibility.update.xml`)

```xml
<?xml version="1.0" encoding="utf-8"?>
<updates>
    <update>
        <name>Yak Shaver CB GJ Visibility</name>
        <description>Hide selected CB GroupJive event details from guest users.</description>
        <element>cbgjvisibility</element>
        <type>plugin</type>
        <folder>system</folder>
        <client>site</client>
        <version>0.1.0</version>
        <downloads>
            <downloadurl type="full" format="zip">https://github.com/alexyarmoshko/joomla_plg_cbgjvisibility/releases/download/0.1.0/plg_system_cbgjvisibility-v0-1-0.zip</downloadurl>
        </downloads>
        <tags>
            <tag>stable</tag>
        </tags>
        <sha256>PLACEHOLDER</sha256>
        <targetplatform name="joomla" version="((5\.(0|1|2|3|4|5|6|7|8|9))|(6\.(0|1|2|3|4|5|6|7|8|9)))" />
    </update>
</updates>
```

### .gitignore

```text
.env
.DS_Store
Thumbs.db
installation/
.vscode/
.claude/
.codex/
.agent/
```

### Build Prerequisites

Requires a Unix-like shell with: `awk`, `zip`, `sha256sum` (Linux) or `shasum` (macOS), `mkdir`, `rm`. On Windows, use WSL or Git Bash.

### Build & Release Workflow

**Tag format**: plain semver without `v` prefix (e.g., `0.1.0`, `1.0.0`). This matches `GITHUB_REF` which defaults to `$(PLUGIN_VERSION)`, ensuring the download URL in the update XML resolves correctly.

1. `make info` — verify version and paths
2. `make dist` — build ZIP in `installation/`, update `plg_system_cbgjvisibility.update.xml` with version, download URL, and SHA-256
3. `git add . && git commit` — commit updated update XML and any version bumps
4. `git tag 0.1.0 && git push --tags` — tag with plain semver (must match version in manifest)
5. Create GitHub release from the tag, upload the ZIP from `installation/`
6. `make clean` — (optional) remove built ZIP after release is published

## Implementation Steps

### Step 1: Scaffold plugin files

Create Joomla 5 namespace-based plugin structure:

- `cbgjvisibility.xml` manifest with namespace, service provider, and config params (General + Compatibility tabs)
- `services/provider.php` for Joomla DI
- `src/Extension/Cbgjvisibility.php` with empty plugin class extending `CMSPlugin`
- `language/en-GB/plg_system_cbgjvisibility.ini` with all language strings

### Step 2: Implement onAfterRender — guest HTML stripping

- Guest check + Content-Type check + early return
- Read config params for which sections to hide
- Build regex patterns for enabled sections
- Apply replacements to response body
- Use nesting-aware PHP function for `gjGroupEventDescription` (nested divs)

### Step 3: Implement compatibility self-check

- Version detection: query `#__comprofiler_plugin` for `cbgroupjive`, `cbgroupjiveevents`, and `cbactivity` versions
- Admin warning: inject `enqueueMessage` when installed versions differ from verified versions
- AJAX verification endpoint (`onAjaxCbgjvisibility`):
  - Read all 5 template files via `file_get_contents()`
  - Check each file for its expected CSS class strings
  - Return per-file found/missing report as JSON
- Plugin config Compatibility tab: display versions, Verify & Acknowledge button with JS handler
- Save verified versions on successful verification

### Step 4: Test

#### Functional test matrix

| Scenario | Expected result |
| - | - |
| Group events tab — guest | Host, Group, Guests hidden; title, date, location visible |
| Group events tab — logged-in user | All details visible |
| Events module — guest | Host, Group, Guests hidden; Description hidden (if `hide_description` = yes) |
| Events module — logged-in user | All details visible |
| GJ activity stream — guest | Host, Group, Guests hidden |
| CB Activity stream — guest | Host, Group, Guests hidden |
| Attending page — guest | No change (no target CSS classes in this template); user names visible via CB's own access control |
| Non-HTML response (JSON, AJAX) | Plugin does not process; response unchanged |
| Admin panel pages — guest not applicable | No stripping; version check runs |
| Admin panel — version mismatch | Warning message displayed |
| Admin panel — versions match | No warning |
| Verify button — all classes present | Success message; versions saved |
| Verify button — class missing | Warning with file/class details; versions NOT saved |
| Verify button — template file missing | Error with file path |
| First install (no verified versions) | Version mismatch warning on first admin page load |

### Step 5: Create installable ZIP package

- Create `Makefile`, `plg_system_cbgjvisibility.update.xml`, and `.gitignore` per the Packaging & Setup section
- Run `make dist` to build the ZIP and update the update server XML
- Verify ZIP contents include only plugin files (manifest, services, src, language)

### Step 6: Install and configure on DDEV site

Install plugin, enable it, configure parameters, run Verify & Acknowledge, confirm all 5 rendering-path behaviors (4 stripped + attending unchanged).

## Risks and Mitigations

| Risk | Likelihood | Mitigation |
| - | - | - |
| CB renames CSS classes in a future update | Low (stable for years) | Compatibility self-check detects version change and warns admin; Verify button confirms classes are still present; admin can update class names and element types via Selectors tab without code changes |
| Nested div matching fails for description block | Medium | Use PHP nesting-aware function instead of pure regex for that one case |
| Plugin fires on non-page responses (JSON, AJAX) | Low | Content-Type check in early exit guards against this |
| Performance on very large pages | Negligible | Regex on <1MB strings is sub-millisecond |
| Template file missing or relocated | Low | Verify handler reports missing files with full path; admin alerted to investigate |

## Success Criteria

- [ ] All 4 applicable event rendering paths hide configured sections from guests (attending.php has no target classes — no stripping expected)
- [ ] Logged-in users see all event details as before
- [ ] Plugin survives a CB GroupJive test update without intervention
- [ ] Plugin configurable via Joomla admin (Extensions > Plugins)
- [ ] Admin sees a warning after CB version change until verification is re-run
- [ ] Verify & Acknowledge button correctly reports CSS class presence/absence
- [ ] First-time install prompts for verification
