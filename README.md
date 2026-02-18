# joomla_plg_cbgjvisibility

Joomla 5 system plugin (`plg_system_cbgjvisibility`) that hides selected CB GroupJive event details from guest users by post-processing rendered HTML in `onAfterRender`.

## What it does

- Hides event host, group link, and guest count from anonymous visitors (enabled by default).
- Optionally hides event description blocks.
- Keeps logged-in users untouched.
- Provides a "Test Sanitization" button in plugin settings that fetches the front page as a guest and verifies hidden CSS classes are absent.

## Repository layout

- `cbgjvisibility.xml`: Plugin manifest and config parameters.
- `services/provider.php`: Joomla DI registration for the plugin class.
- `src/Extension/Cbgjvisibility.php`: Main plugin logic.
- `src/Field/SanitizationTestField.php`: Admin custom field with Test Sanitization button.
- `media/js/sanitization-test.js`: Admin Testing tab behavior (AJAX sanitization test UI).
- `language/en-GB/`: Plugin language strings.
- `plg_system_cbgjvisibility.update.xml`: Joomla update server descriptor.
- `Makefile`: Packaging and update XML automation.
- `docs/execution_plan.md`: Implementation plan.
- `docs/execution_changelog.md`: Implementation progress log.
- `docs/RELEASE.md`: Release notes.

## Build commands

Run from repository root using a Unix-like shell (WSL/Git Bash/macOS/Linux):

- `make info`: Print plugin/version/package paths.
- `make dist`: Build installable ZIP in `installation/` and update update-server XML version/url/sha256.
- `make clean`: Remove the generated ZIP.

## Configuration

Plugin settings are split into:

- `General`: Toggle `hide_host`, `hide_group`, `hide_guests`, `hide_description`.
- `Selectors`: Configure wrapper element/class and marker string used by the stripping logic.
- `Testing`: Run a live sanitization test against the front page.

## Testing sanitization

1. Open plugin settings in Joomla administrator.
2. Go to the Testing tab and click "Test sanitization".
3. The plugin fetches the site's front page as a guest and checks that hidden CSS classes are absent from the HTML.

## Notes

- The HTML stripping assumes target `class="..."` attributes use double quotes in CB templates.
- Description block stripping uses nesting-aware parsing to handle nested `<div>` content.
  