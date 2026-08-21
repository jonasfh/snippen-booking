# Architecture & Coding Standards

## Tech Stack & Environment
- **PHP**: 7.4+ (devcontainer uses PHP 8.3)
- **WordPress**: Custom plugin (symlinked at `/wordpress/wp-content/plugins/snippen-booking`)
- **Autoloading**: PSR-4 (`SnippenBooking\` namespace) mapped to `src/wp-content/plugins/booking-plugin/inc/`

## Plugin Structure

```
src/wp-content/plugins/booking-plugin/
├── booking-plugin.php        # Entry point (loads autoloader)
├── autoloader.php            # PSR-4 autoloader
├── composer.json             # Dependencies & scripts
├── inc/                      # Application logic (PSR-4 SnippenBooking\)
│   ├── Plugin.php            # Bootstrapper - hook registration
│   ├── Assets/AssetLoader.php           # Enqueue scripts/styles
│   ├── Shortcode/BookingShortcode.php   # Shortcode rendering
│   ├── Database/Install.php             # Activation & table creation
│   └── Api/
│       ├── AvailabilityApi.php          # AJAX availability endpoint
│       └── BookingApi.php               # AJAX booking submission
├── css/booking.css
└── js/booking.js
```

## Core Architectural Rules
- **Modular & Testable**: Keep logic modular and testable. Avoid direct WordPress hook side-effects inside domain classes where practical.
- **Database Tables**: Always include `created_at` and `modified_at` timestamp columns on custom database tables.
- **Thin Controllers**: Keep AJAX handlers in `inc/Api/` thin and delegate logic to domain classes.
- **Design & Styling**: Inherit styles from the active WordPress default theme (Twenty Twenty-Five). Keep custom CSS minimal and functional.
