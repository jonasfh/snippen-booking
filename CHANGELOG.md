# Changelog

## [1.4.1] - 2026-05-17
### Fixed
- **Norwegian Holiday Timezone Bug**: Resolved a timezone mismatch/DST transition bug in `HolidayService` where Easter-based moving holidays (such as 2. pinsedag / Whit Monday) would shift to the wrong date if the PHP default timezone was different from the system timezone (a common scenario in WordPress sites set to UTC). Implemented a robust, mathematically exact, and timezone-independent calculation using `easter_days()` and `DateTimeImmutable`.
- **Unit Tests**: Added complete test coverage for moving and fixed holidays across multiple years under mismatched timezone conditions.

## [1.4.0] - 2026-05-17
### Added
- **Door Code Support for Venues**: Added a `door_code` field to booking objects (rooms/venues) inside the Admin booking objects screen.
- **Configurable Door Code Time Window**: Introduced Admin configuration options under "Innstillinger" to customize how many hours before booking start (**x**) and how many hours after booking end (**y**) the door code should be active.
- **Secure Dynamic Synchronizer**: Built a robust `DoorCodeService` that synchronizes room door codes into active upcoming bookings within the active display window, while keeping them hidden and secure outside the window. Handles multiple assigned rooms, sanitizing and automatically deduplicating identical door codes to a single entry.
- **Front-End & Admin Displays**: Integrated beautiful door code displays within both the public front-end booking details modal overlay and the "Mine Bookinger" expanded row layouts, showing the code inside the active window and `<Koden er ikke tilgjengelig før nærmere booking start>` outside of it.
- **Unit and Integration Tests**: Implemented comprehensive unit and integration test coverage for settings options, time windows, multi-room code combinations, database migrations, and front-end rendering.

## [1.3.2] - 2026-05-17
### Added
- **Secure Single Booking Popup**: Implemented a secure read-only single booking popup overlay on the front-end when `booking_uuid` is detected in the URL query parameters.
- **Access Control & Redirect**: Restricts booking details access to only the booking owner or administrators, prompting guest visitors with a premium Vipps login overlay that redirects back seamlessly after login.
- **Database & Data Migration**: Added a unique `uuid` column to the bookings database table and automatically backfilled existing records with unique UUIDs.
- **SMS Integration**: Updated the booking confirmation SMS to include the direct, non-guessable popup link to their booking.
- **Demo Generator UUIDs**: Enforced secure UUID generation in `demo-bookings` creation script.

## [1.3.1] - 2026-05-17
### Added
- **Tagged Booking Pages**: Enabled tag (post_tag) support for WordPress pages. Pages tagged with `snippen-booking` will now display as quick links at the top of the Admin Booking overview page.

## [1.3.0] - 2026-05-17
### Added
- **User Account Confirmation**: Added functionality for users to confirm their accounts via a shortcode. Confirmed users can then create bookings.
- **Granular SMS Settings**: Added "SMS Innstillinger" page for enabling/disabling specific SMS notification types.
- **Booking Confirmation SMS**: Added SMS confirmation to users upon successful booking.

## [1.2.2] - 2026-05-16
### Added
- **Demo Commands**: Added `composer demo:sms`, `composer demo:me`, and `composer demo:env` for easier development setup.
- **Environment Support**: Added `.env` file support for demo scripts via `.env.example`.

## [1.2.1] - 2026-05-17
### Fixed
- **SMS Service**: Trim + form phone numbers before sending. SMS messages are now sent reliably.

## [1.2.0] - 2026-05-16
### Added
- **SMS Service**: Now working with KeySMS.

## [1.1.9] - 2026-05-16
### Added
- **SMS Service**: Integrated KeySMS API for automated SMS notifications.
- **Admin Settings**: Added a new "Innstillinger" page to manage SMS API keys and sender info.
- **Booking Notifications**: Automatic SMS confirmation to users upon successful booking.

## [1.1.8] - 2026-05-16
### Added
- Enable AI agents to solve GitHub issues directly.
- Added GitHub CLI (`gh`) to devcontainer.
- Updated `AGENTS.md` and `DEV_README.md` with GitHub issue workflow.
All notable changes to this project will be documented in this file.

## [1.1.7] - 2026-05-16
### Added
- **My Bookings Page**: New admin page for users to view their own bookings and details.
- **Self-Cancellation**: Enabled users to cancel their own bookings directly from the "Mine Bookinger" page.
- **Security**: Implemented ownership verification for AJAX booking actions to prevent unauthorized cancellations.

## [1.1.6] - 2026-05-16
### Added
- **User Phone Numbers**: Integrated secure phone number handling linked to user profiles.
- **Admin User Editing**: Added a custom field to the WordPress user profile screen so admins can edit phone numbers.
- **Booking Security**: The phone number field in the booking form is now read-only and fetched securely from user metadata, E.164 standard (+47 prefix) format required.
- **Validation**: Prevented bookings from being submitted by users without a registered phone number.
- **Demo Data Enhancement**: Updated `demo:users` to generate random Norwegian phone numbers.
### Fixed
- **Form UI**: Improved form resetting and fixed aggressive "snap-back" when clearing the admin user search.
- **Validation**: Enforced phone number validation immediately upon form loading for all users.
- **Error Messages**: Ensured phone number validation errors are cleared correctly when an admin selects a different user or starts a new search.

## [1.1.5] - 2026-05-15
### Added
- **Admin Calendar Info**: Display associated locales/objects in the booking details modal.
### Fixed
- **AJAX Performance**: Optimized availability API to avoid duplicate booking rows and fetch object names efficiently.

## [1.1.5] - 2026-05-15
### Fixed
- **Pricing Admin**: Fixed a bug in the price list view where date-based conditions caused an "Undefined property" warning due to mismatched database column names.

## [1.1.4] - 2026-05-15
### Changed
- **Release Assets**: Updated GitHub Release workflow to name ZIP files with version numbers (e.g., `snippen-booking-v1.1.4.zip`).
- **Update Checker**: Configured PUC to use a regular expression (`/^snippen-booking-.*\.zip$/i`) to identify release assets, making it more robust against future naming changes.

## [1.1.3] - 2026-05-15
### Changed
- **Auto-Update**: Explicitly enabled GitHub Release assets in Plugin Update Checker configuration.

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
