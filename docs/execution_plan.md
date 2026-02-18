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
├── media/
│   └── js/
│       └── sanitization-test.js       # Admin Testing tab JS (AJAX button behavior)
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
- Parameters (Testing tab):
  - "Test Sanitization" button — fetches front page as guest and checks CSS class absence

### Joomla Event Contract

The plugin subscribes to the following Joomla events (using Joomla 5 event subscriber pattern):

| Event | Handler | Context | Purpose |
| --- | --- | --- | --- |
| `onAfterRender` | `onAfterRender()` | Site (guest) | Strip configured HTML blocks from response body |
| `onAjaxCbgjvisibility` | `onAjaxCbgjvisibility()` | Admin | AJAX endpoint for sanitization test button |

### Plugin Class (Cbgjvisibility.php)

#### `onAfterRender()`

```text
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

1. **Site client guard**: Early exit if not site client (e.g., API, CLI, admin).
2. **Guest guard**: Early exit if user is logged in (`!$this->getApplication()->getIdentity()->guest`).
3. **Content-Type guard**: Early exit if response is not HTML.
4. **Marker guard**: Early exit if `strpos($body, $markerString) === false` — `$markerString` read from `marker_string` param (default: `gjGroupEvent`).
5. **Strip**: For each enabled parameter, run regex/nesting-aware replacement to remove the corresponding HTML block.
6. **Commit**: `setBody($body)`.

#### Regex Patterns

Each target is a self-contained `<div>` with a unique class. Use non-greedy matching within the div:

```php
// Pattern template (per CSS class), using configurable element and class:
'#<' . $element . '\s+class="[^"]*\b' . preg_quote($class) . '\b[^"]*">.*?</' . $element . '>#s'
```

The `s` (DOTALL) flag handles multi-line content. The element tag and class name are read from plugin parameters, with defaults matching current CB markup. For simple targets (Host, Group, Guests) that don't contain nested elements of the same type, non-greedy matching is safe.

**Assumption**: The pattern expects `class` as an attribute with double quotes (e.g., `<div class="...gjGroupEventHost...">`). This matches CB's consistent output across all 5 templates. If a future CB version changes attribute quoting or order, the regex will silently fail (no match = info leakage, no breakage), and the Test Sanitization button will detect the classes still present in guest-rendered HTML, alerting the admin.

For `gjGroupEventDescription`, the content includes nested divs (cbMoreLess), so a nesting-aware PHP function is used instead of regex: find the opening tag, count element nesting depth, and find the matching closing tag. This is more robust than regex for nested elements and works regardless of the configured element type.

### Sanitization Testing (v0.2.0)

> **Note**: The compatibility verification system (version tracking, template file scanning, admin warnings) was removed in v0.2.0. It was replaced with a simpler live sanitization test that checks actual rendered output.

The plugin's Testing tab provides a "Test Sanitization" button that:

1. Fetches the site's front page as a guest (HTTP request with no cookies)
2. Checks for the marker string — confirms event data is present on the page
3. Checks that each enabled hidden CSS class is absent from the HTML
4. Returns per-class PASS/FAIL results

This approach tests what actually matters (is the sanitized HTML correct for guests?) rather than whether CSS class strings exist in template source files.

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
PLUGIN_FILES := cbgjvisibility.xml services/ src/ language/ media/

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

- `cbgjvisibility.xml` manifest with namespace, service provider, and config params (General, Selectors, and Testing tabs)
- `services/provider.php` for Joomla DI
- `src/Extension/Cbgjvisibility.php` with empty plugin class extending `CMSPlugin`
- `language/en-GB/plg_system_cbgjvisibility.ini` with all language strings

### Step 2: Implement onAfterRender — guest HTML stripping

- Guest check + Content-Type check + early return
- Read config params for which sections to hide
- Build regex patterns for enabled sections
- Apply replacements to response body
- Use nesting-aware PHP function for `gjGroupEventDescription` (nested divs)

### Step 3: Implement sanitization test (replaced in v0.2.0)

> **Note**: The original compatibility verification system (version tracking, template file scanning, admin warnings) was removed in v0.2.0 and replaced with a live sanitization test.

- AJAX endpoint (`onAjaxCbgjvisibility`): fetches front page as guest, checks marker string presence and hidden CSS class absence
- Plugin config Testing tab: "Test Sanitization" button triggers AJAX call and displays per-class PASS/FAIL results

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
| Admin panel — Test Sanitization (marker found, classes absent) | All PASS |
| Admin panel — Test Sanitization (marker found, class present) | Per-class FAIL shown |
| Admin panel — Test Sanitization (marker not found) | Inconclusive result |
| Admin panel — Test Sanitization (fetch fails) | Error message displayed |

### Step 5: Create installable ZIP package

- Create `Makefile`, `plg_system_cbgjvisibility.update.xml`, and `.gitignore` per the Packaging & Setup section
- Run `make dist` to build the ZIP and update the update server XML
- Verify ZIP contents include only plugin files (manifest, services, src, language)

### Step 6: Install and configure on DDEV site

Install plugin, enable it, configure parameters, run Test Sanitization from the Testing tab, confirm all 5 rendering-path behaviors (4 stripped + attending unchanged).

## Risks and Mitigations

| Risk | Likelihood | Mitigation |
| - | - | - |
| CB renames CSS classes in a future update | Low (stable for years) | Test Sanitization detects missing classes via live guest-page check; admin can update class names and element types via Selectors tab without code changes |
| Nested div matching fails for description block | Medium | Use PHP nesting-aware function instead of pure regex for that one case |
| Plugin fires on non-page responses (JSON, AJAX) | Low | Content-Type check in early exit guards against this |
| Performance on very large pages | Negligible | Regex on <1MB strings is sub-millisecond |
| Template file missing or relocated | Low | Test Sanitization detects when expected classes are absent from rendered output; admin alerted to investigate |

## Success Criteria

- [ ] All 4 applicable event rendering paths hide configured sections from guests (attending.php has no target classes - no stripping expected)
- [ ] Logged-in users see all event details as before
- [ ] Plugin survives a CB GroupJive test update without intervention
- [ ] Plugin configurable via Joomla admin (Extensions > Plugins)
- [ ] Test Sanitization button correctly reports per-class PASS/FAIL for guest-rendered HTML

## Implementation Status (2026-02-16)

- [x] Step 1 implemented: plugin files scaffolded (`cbgjvisibility.xml`, `services/`, `src/`, language files).
- [x] Step 2 implemented: `onAfterRender` guest stripping with marker fast-path, selector params, and nesting-aware description removal.
- [x] Step 3 replaced (v0.2.0): compatibility verification system removed; replaced with live sanitization test (AJAX from admin Testing tab).
- [x] Step 5 implemented: `Makefile`, update XML, and packaging paths added.
- [ ] Step 6 intentionally not executed: DDEV installation/configuration/functional environment checks were skipped because test environment is unavailable.
