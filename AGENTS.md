# AGENTS.md

## Purpose
Guide AI agents working in this repository.

## Tech Stack
- PHP 7.4+
- WordPress
- Composer
- PHPUnit 9.x
- Docker / Devcontainer

## Key Rules
- Follow WordPress plugin best practices
- Use PSR-4 autoloading
- Keep logic modular and testable
- Avoid unnecessary dependencies
- All plugin logic in `inc/` directory (WordPress standard)
- **Always create tests for new functionality**
- **Update existing tests when changing functionality**
- **Run tests to verify all changes before completion**
- **Always include created_at and modified_at timestamps on database tables**
- **Check for latest versions**: Always ensure that the latest available versions of frameworks, libraries, and GitHub Actions are used, unless there is a specific reason not to. If old versions are discovered, notify the developer.
- **DO NOT commit or push changes** (the USER handles version control), **EXCEPT** when following the "GitHub Issue Workflow" below.
- Always suggest a commit message focused on the problem solved. Use the header for the issue/outcome, and the body for implementation details and rationale. Format it as plain text in a copy-pasteable code block, without quotes.
- **GitHub Issue Workflow**: When tasked with solving a specific GitHub issue (e.g., "solve issue #15"), follow the dedicated workflow below.

## Plugin Structure

```
src/wp-content/plugins/booking-plugin/
├── booking-plugin.php        # Entry point (18 lines, loads autoloader)
├── autoloader.php            # PSR-4 autoloader for SnippenBooking namespace
├── composer.json             # Dependencies and scripts
├── inc/                       # Application logic (PSR-4 mapped to SnippenBooking\)
│   ├── Plugin.php            # Bootstrapper - registers all hooks
│   ├── Assets/AssetLoader.php           # Script/style enqueuing
│   ├── Shortcode/BookingShortcode.php   # Shortcode rendering
│   ├── Database/Install.php             # Activation & table creation
│   └── Api/
│       ├── AvailabilityApi.php          # AJAX availability endpoint
│       └── BookingApi.php               # AJAX booking submission
├── css/booking.css
├── js/booking.js
```

## Namespace & Autoloading
- PSR-4 namespace: `SnippenBooking\`
- Maps to: `src/wp-content/plugins/booking-plugin/inc/`
- All classes must follow `SnippenBooking\{Namespace}\ClassName` pattern
- Autoloader in `autoloader.php` - loaded by entry point

**Example:**
- Class: `SnippenBooking\Api\AvailabilityApi`
- File: `inc/Api/AvailabilityApi.php`

## Testing

### Test Structure
```
tests/
├── bootstrap.php             # Test bootstrap - loads autoloader & defines constants
├── TestCase.php              # Base test case class (extend this)
├── Unit/                     # Unit tests (classes in isolation)
└── Integration/              # Integration tests (with WordPress)
```

### Running Tests
Composer is available and configured with correct paths in the devcontainer, so `composer` can be used to run tests.

```bash
# Install dependencies first
composer install

# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration

# Code style check
composer lint

# Auto-fix code style
composer lint:fix
```

### Writing Tests
1. Create test file in `tests/Unit/` or `tests/Integration/`
2. Extend `SnippenBooking\Tests\TestCase`
3. Follow naming: `ClassNameTest.php` for `ClassName` class
4. Test methods start with `test`: `testMethodName()`

**Example:**
```php
namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\AvailabilityApi;

class AvailabilityApiTest extends TestCase {
    public function testGetAvailability() {
        // Test logic
    }
}
```

## Development Workflow
- Code runs inside Docker container via devcontainer
- WordPress installation is located at `/wordpress`
- Plugin is symlinked into WordPress at `/wordpress/wp-content/plugins/snippen-booking`
- Use `wp-cli` for WordPress operations
- Use `composer` for dependencies and test commands
- Always run tests before committing

## Common Commands (in container terminal)

```bash
# Install/update dependencies
composer install

# Run tests
composer test

# Activate/deactivate plugin
wp plugin activate snippen-booking --allow-root
wp plugin deactivate snippen-booking --allow-root

# See plugin status
wp plugin list --allow-root

# Reset WordPress (warning: deletes database)
/entrypoint.sh reset

# Reinstall WordPress
/entrypoint.sh setup
```

## GitHub Issue Workflow

When an agent is asked to solve a specific GitHub issue, the following workflow **MUST** be followed:

1.  **Preparation**:
    - Start in the `main` branch.
    - Ensure `main` is up to date: `git pull origin main`.
    - Check if the terminal is logged in to GitHub: `gh auth status`. If not, inform the developer and ask them to run `gh auth login`.
2.  **Branching**:
    - Check if a branch already exists for the issue.
    - If not, create a new branch using the pattern: `gh-issue/<id>` (e.g., `gh-issue/15`).
3.  **Implementation**:
    - Solve the issue as requested.
    - Create tests for new functionality and run all tests to verify.
4.  **Submission**:
    - Commit changed files.
    - **Commit Message**: The message **MUST** start with the issue reference in parentheses, e.g., `(#15) Fixed xxx...`.
    - Push the branch to origin.
    - Create a Pull Request using `gh`: `gh pr create --body "Closes #<id>" --title "(#<id>) <Issue Title>"`.
    - Checkout `main` and return to the starting state.

## Key Classes Overview

| Class | Purpose | Tests |
|-------|---------|-------|
| `Plugin` | Bootstrap & hook registration | N/A |
| `Install` | DB table creation on activation | Unit tests |
| `AssetLoader` | Enqueue scripts/styles | Unit tests |
| `BookingShortcode` | Shortcode rendering | Unit + Integration |
| `AvailabilityApi` | AJAX availability endpoint | Integration |
| `BookingApi` | AJAX booking submission | Integration |

## Notes
- This is a custom plugin, not a theme
- Focus on maintainability over cleverness
- All logic should be testable (avoid direct WordPress actions when possible)
- Use dependency injection patterns where practical
- Keep AJAX handlers (in Api classes) thin - delegate to other classes

## Versioning & Changelog
- **Version Bump**: When making functional changes, you must bump the version number in `src/wp-content/plugins/booking-plugin/booking-plugin.php`, UNLESS this is already done in the current branch.
- **CHANGELOG.md**: Every version bump must be accompanied by an entry in `CHANGELOG.md` under a header like `## [X.Y.Z] - YYYY-MM-DD`.
- **PR Check**: Pull Requests will fail if the version in the plugin file already exists as a Git tag or if the `CHANGELOG.md` is not updated.
