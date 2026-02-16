# joomla_plg_cbgjvisibility

Joomla 5 system plugin (`plg_system_cbgjvisibility`) that hides selected CB GroupJive event details from guest users by post-processing rendered HTML in `onAfterRender`.

## What it does

- Hides event host, group link, and guest count from anonymous visitors (enabled by default).
- Optionally hides event description blocks.
- Keeps logged-in users untouched.
- Provides a compatibility verification tool in plugin settings to detect template/class drift after CB updates.
- Warns administrators when installed CB plugin versions differ from the last verified snapshot.

## Repository layout

- `cbgjvisibility.xml`: Plugin manifest and config parameters.
- `services/provider.php`: Joomla DI registration for the plugin class.
- `src/Extension/Cbgjvisibility.php`: Main plugin logic.
- `src/Field/VerifyField.php`: Admin custom field with Verify button and JSON report output.
- `language/en-GB/`: Plugin language strings.
- `plg_system_cbgjvisibility.update.xml`: Joomla update server descriptor.
- `Makefile`: Packaging and update XML automation.
- `docs/execution_plan.md`: Implementation plan.
- `docs/execution_changelog.md`: Implementation progress log.
- `RELEASE.md`: Release notes.

## Build commands

Run from repository root using a Unix-like shell (WSL/Git Bash/macOS/Linux):

- `make info`: Print plugin/version/package paths.
- `make dist`: Build installable ZIP in `installation/` and update update-server XML version/url/sha256.
- `make clean`: Remove the generated ZIP.

## Configuration

Plugin settings are split into:

- `General`: Toggle `hide_host`, `hide_group`, `hide_guests`, `hide_description`.
- `Selectors`: Configure wrapper element/class and marker string used by the stripping logic.
- `Compatibility`: Check installed CB versions vs. verified versions and run Verify/Acknowledge.

## Verification flow

1. Open plugin settings in Joomla administrator.
2. In the Compatibility tab, click "Verify and acknowledge current versions".
3. The plugin scans these CB template files:
   - `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/events.php`
   - `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/module.php`
   - `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/activity.php`
   - `plug_cbactivity/templates/default/activity/core/group/event.php`
   - `plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/attending.php`
4. If expected classes are present in applicable files, verified version params are updated.

## Notes

- The HTML stripping assumes target `class="..."` attributes use double quotes in CB templates.
- Description block stripping uses nesting-aware parsing to handle nested `<div>` content.
