# Testing & Quality Assurance Guidelines

## Test Commands

Use `composer` in the devcontainer terminal to run linting and tests:

```bash
# Run all tests (Unit + Integration - run in CI/CD)
composer test

# Run fast unit tests (in-memory, < 1s)
composer test:unit
composer test:fast

# Run tests relevant only to changed files in git
composer test:changed

# Run integration tests (database, services, migrations)
composer test:integration

# Run JavaScript tests (Jest)
npm run test:js

# Run fast UI & visual regression tests (Playwright - < 5s)
npm run test:ui:fast

# Update visual reference snapshots (when UI changes are intentional)
npm run test:ui:update

# Code style check (PHPCS - cached & parallelized)
composer lint
# or: phpcs / npm run lint

# Auto-fix code style (PHPCBF)
composer lint:fix
# or: phpcbf / npm run lint:fix
```

## Mandatory Rules
- **Create tests**: Create unit or integration tests for all new functionality.
- **Update tests**: Update existing tests when modifying functionality.
- **Code formatting**: Routinely run `phpcbf` (or `composer lint:fix`) on code changes before checking code quality or committing.
- **Local fast feedback**: During active coding, use `composer test:changed` or `composer test:fast` for immediate verification without waiting for the full integration suite.
- **UI testing policy**: Run `npm run test:ui:fast` **only** when actively developing or modifying UI, CSS, templates, or frontend logic. Never run visual regression suites for purely backend, PHP, or database tasks.
- **Linting check**: Always run `composer lint` and resolve all PHPCS errors and warnings before completing a task.

## Writing Tests
- Locate tests in `tests/Unit/` (pure in-memory unit tests, `$requires_db = false`) or `tests/Integration/` (database, API, or WordPress integration).
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
