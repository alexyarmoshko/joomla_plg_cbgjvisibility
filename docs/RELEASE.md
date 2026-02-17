# Release Notes

## [0.2.0] - 2026-02-16

### Removed

- Compatibility verification system (version tracking, template file scanning, admin version mismatch warnings).
- `src/Field/VerifyField.php` custom form field.
- Compatibility tab with hidden verified version parameters.
- 28 compatibility-related language strings.

### Added

- "Test Sanitization" button in plugin settings (Testing tab) — fetches front page as guest and checks CSS class absence.
- `src/Field/SanitizationTestField.php` custom form field.
- 12 new language strings for testing UI.

### Changed

- Plugin class reduced from ~635 to ~335 lines.
- Updated documentation (README, CLAUDE.md, execution plan, changelog).

## [0.1.0] - 2026-02-16

### Added

- Initial implementation of `plg_system_cbgjvisibility` for Joomla 5.
- Guest-facing HTML stripping for CB GroupJive event details (host, group, guests, optional description).
- Admin compatibility warning system comparing installed and verified CB plugin versions.
- Admin Verify/Acknowledge flow with AJAX scanning of CB template files and verified-version persistence.
- Packaging automation (`Makefile`) and update-server descriptor (`plg_system_cbgjvisibility.update.xml`).
