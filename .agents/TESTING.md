# Testing & Quality Assurance Guidelines

## Test Commands

Use `composer` in the devcontainer terminal to run linting and tests:

```bash
# Run all tests (Unit + Integration - run in CI/CD)
composer test

# Run fast unit tests
composer test:unit
composer test:fast

# Run integration tests
composer test:integration

# Run JavaScript tests (Jest)
npm run test:js

# Run fast UI & visual regression tests (Playwright - < 5s)
npm run test:ui:fast

# Update visual reference snapshots (when UI changes are intentional)
npm run test:ui:update

# Code style check (PHPCS)
composer lint

# Auto-fix code style (PHPCBF)
composer lint:fix
```

## Mandatory Rules
- **Create tests**: Create unit or integration tests for all new functionality.
- **Update tests**: Update existing tests when modifying functionality.
- **UI testing policy**: Run `npm run test:ui:fast` **only** when actively developing or modifying UI, CSS, templates, or frontend logic. Never run visual regression suites for purely backend, PHP, or database tasks.
- **Linting check**: Always run `composer lint` and resolve all PHPCS errors and warnings before completing a task.

## Writing Tests
- Locate tests in `tests/Unit/` or `tests/Integration/`.
- Extend `SnippenBooking\Tests\TestCase`.
- Class name matches file name: `ClassNameTest.php` for `ClassName`.
- Method names start with `test`: `testMethodName()`.

## Debugging & Logging
- **Apache error log**: `/var/log/apache2/error.log` (Notices, warnings, fatal PHP errors).
- **WP Debug log**: `/wordpress/wp-content/debug.log` (when `WP_DEBUG_LOG` is active).
- Quick check for PHP fatal errors:
  ```bash
  tail -n 100 /var/log/apache2/error.log | grep -i "fatal error"
  ```
