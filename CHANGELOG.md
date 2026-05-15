# Changelog

All notable changes to this project will be documented in this file.

## [0.2.2] - 2026-05-15
### Fixed
- Fatal error `Class "Parsedown" not found` in `plugin-update-checker` by restoring missing dependency files.
### Added
- Forced update check trigger for development testing (`?check_booking_updates=1` query param when `WP_DEBUG` is on).
- Documented WordPress path and preferred testing commands in `AGENTS.md`.

## [0.2.1] - 2026-05-15

### Changed
- **Admin UI**: Removed the "Tøm demo-data" button from the Booking Overview page to prevent accidental data loss.
- **Mobile View**: Timeslots in the calendar are now visible by default on small screens, removing the need to click each day to check availability.
- **Calendar UX**: Removed the day-header click toggle on mobile for a more streamlined booking experience.

## [0.2.0] - 2026-05-10
- Initial beta release with core booking functionality.
