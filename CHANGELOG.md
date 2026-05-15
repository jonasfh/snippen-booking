# Changelog

All notable changes to this project will be documented in this file.

## [1.1.3] - 2026-05-15
### Added
- **Admin Calendar Info**: Display associated locales/objects in the booking details modal.
### Fixed
- **AJAX Performance**: Optimized availability API to avoid duplicate booking rows and fetch object names efficiently.

## [1.1.2] - 2026-05-15
### Fixed
- **Auto-Update Fix**: Removed `setBranch('main')` configuration. This ensures the update checker uses the correctly packaged GitHub Release ZIP assets instead of downloading the raw repository source, which was causing installation failures.

## [1.1.1] - 2026-05-15
### Fixed
- **Asset Caching**: Fixed an issue where old JS/CSS files remained cached in browsers after updates.
- **JS Attribute Safety**: Improved handling of special characters in customer names to prevent breaking calendar interactions.

## [1.1.0] - 2026-05-15
### Added
- **Admin Calendar Info**: Display customer names directly in the calendar for administrators.
- **Booking Detail Modal**: View complete booking information by clicking booked slots in the calendar (admin only).
- **Improved Bookings List**: Default sorting by date (ASC) and automatic 14-day history filtering.
- **History Toggle**: Easily show/hide older bookings in the admin dashboard.

## [1.0.0] - 2026-05-15
### Added
- **Admin Booking**: Admins can now create bookings on behalf of other residents directly from the calendar.
- **User Search**: Integrated a premium user search box in the booking form for admins.
- **Migration System**: New robust database migration system to handle schema and data updates.
- **Mandatory User ID**: All bookings are now linked to a registered WordPress user for better tracking.
- **Demo User Generation**: Added `php bin/demo-data.php users` command to generate subscriber users.
- **Enhanced Demo Data**: Generated bookings are now randomly assigned to subscriber users.

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
