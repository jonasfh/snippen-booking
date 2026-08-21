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
- **Run tests and linting to verify all changes before completion**: Always run `composer lint` and ensure all PHPCS errors and warnings are resolved before committing.
- **Always include created_at and modified_at timestamps on database tables**
- **Check for latest versions**: Always ensure that the latest available versions of frameworks, libraries, and GitHub Actions are used, unless there is a specific reason not to. If old versions are discovered, notify the developer.
- **DO NOT commit or push changes** (the USER handles version control), **EXCEPT** when following the "GitHub Issue Workflow" below.
- **ALWAYS suggest a commit message** Focus on the problem solved. Use the header for the issue/outcome, and the body for implementation details and rationale. Format it as plain text in a copy-pasteable code block, without qusotes.
- **GitHub Issue Workflow**: When tasked with solving a specific GitHub issue (e.g., "solve issue #15"), follow the dedicated workflow below.
- **Always make sure new branches get updated version number and changelog when changes to src/ folder is made (see src/inc/booking-plugin.php and CHANGELOG.md)**
- **Always keep README.md updated with user-related changes**
- **Always keep DEV_README.md updated with developer-related changes**

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

# Run all tests (Unit + Integration - used in CI/CD)
composer test

# Run only unit tests (fast local feedback)
composer test:unit

# Run fast unit tests (stops on first failure)
composer test:fast

# Run only integration tests
composer test:integration

# Run JavaScript tests (Jest)
npm run test:js

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
- Always run tests after changes in code

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

## Debugging & Logging

When investigating runtime errors or HTTP 500 AJAX failures in the devcontainer:

- **PHP / Web Server Errors**: Apache error log contains PHP notices, warnings, and uncaught fatal stack traces.
  ```bash
  tail -f /var/log/apache2/error.log
  ```
- **WordPress Debug Log**: If `WP_DEBUG_LOG` is enabled in `wp-config.php`, output written via `error_log()` will be saved to `/wordpress/wp-content/debug.log`.
- **Nginx Errors**: If running under Nginx, logs are located at `/var/log/nginx/error.log`.
- **Inspection tip**: Filter recent PHP fatal errors quickly:
  ```bash
  tail -n 100 /var/log/apache2/error.log | grep -i "fatal error"
  ```


## GitHub Issue Workflow

All changes made to the project MUST be based on a GitHub issue. If a task or instruction is given without an associated issue, the agent MUST first create a GitHub issue containing the details of the work to be done (using `gh issue create`) before starting execution.

Once a GitHub issue is identified or created, the following workflow **MUST** be followed:

1.  **Preparation**:
    - For all issues, start in the `main` branch and ensure `main` is up to date: `git pull origin main`.
    - Check if the terminal is logged in to GitHub: `gh auth status`. If not, inform the developer and ask them to run `gh auth login`.
    - To read issue details, ALWAYS use `gh issue view <id> --json title,body` instead of `gh issue view <id>`. This prevents the command from failing due to deprecation warnings related to Projects (classic).
2.  **Branching**:
    - Check if a branch already exists for the issue.
    - If not, create a new branch using the pattern: `gh-issue/<id>` (e.g., `gh-issue/127`). Base this branch off `main`.
3.  **Implementation**:
    - Solve the issue as requested.
    - Create tests for new functionality and run all tests to verify.
4.  **Submission**:
    - Commit changed files.
    - **Commit Message**: The message **MUST** start with the specific issue reference in parentheses for issue-related commits, e.g., `(#127) Fixed xxx...`. When multiple issues are addressed in the same branch, make separate commits for each issue referencing its specific issue number (e.g., `(#187) ...`, `(#194) ...`). General updates not tied to a specific issue (such as updates to `AGENTS.md`) do not require an issue reference header.
    - Push the branch to origin.
    - Create a Pull Request using `gh`, target `main` (default): `gh pr create --body "Closes #<id>" --title "(#<id>) <Issue Title>"`.
    - Make sure PR has updated version and changelog in `src/inc/booking-plugin.php` and `CHANGELOG.md` if needed.
5.  **Update github issues with implementation notes**: Add implementation details and a summary of the changes made to resolve the issue.
6.  **Merging Pull Requests**: When instructed to merge a Pull Request, the agent MUST first check that all PR checks have passed (`gh pr checks <id>`) and that the PR can be merged cleanly. Then execute `gh pr merge <id> --rebase --delete-branch`.

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

## Documentation

Use `README.md` for user-facing documentation (installation, usage, FAQs) and `DEV_README.md` for developer-facing documentation (architecture, coding standards, contribution guidelines). Keep both updated with any relevant changes.

Include diagrams to make docs easier to read and understand.

### Mermaid diagrams

Use Mermaid diagrams for visual documentation of database schema, class relationships, and workflows. Follow the rules below to ensure Mermaid diagrams are correctly parsed and rendered.

#### Mermaid `erDiagram` rules

When generating Mermaid `erDiagram` diagrams:

- ALL relationships MUST be written on a single physical line.
- Never insert line breaks inside relationship definitions.
- Mermaid `erDiagram` parser is extremely sensitive to newlines.

Correct:

\```mermaid
erDiagram
    users ||--o{ orders : places
\```

Incorrect:

\```mermaid
erDiagram
    users ||--o{
        orders : places
\```

Also avoid:

\```mermaid
erDiagram
    users ||--o{ orders :
        places
\```

Entity blocks MAY span multiple lines:

\```mermaid
erDiagram

    users {
        INT id PK
        VARCHAR email
    }

    orders {
        INT id PK
        INT user_id FK
    }

    users ||--o{ orders : places
\```

Additional rules:

- Keep every relationship declaration on exactly one line.
- Avoid tabs in Mermaid diagrams.
- Prefer simple relation labels:
  - contains
  - belongs_to
  - has_many
  - references
  - targets
- Avoid quoted labels unless necessary.
- Prefer ASCII-only labels if possible.

Before committing Mermaid diagrams:
- Validate them in Mermaid Live Editor.
- Ensure no automatic formatter has wrapped relationship lines.

## Design & Styling
- Keep custom CSS minimal: Only apply styles that are strictly necessary for functionality.
- Inherit and follow the active WordPress default theme (Twenty Twenty-Five) styling rather than overriding theme defaults with unnecessary custom styles.

## Versioning & Changelog
- **Version Bump**: When making functional changes, you must bump the version number in `src/wp-content/plugins/booking-plugin/booking-plugin.php`, UNLESS this is already done in the current branch.
- **CHANGELOG.md**: Every version bump must be accompanied by an entry in `CHANGELOG.md` under a header like `## [X.Y.Z] - YYYY-MM-DD`.
- **PR Check**: Pull Requests will fail if the version in the plugin file already exists as a Git tag or if the `CHANGELOG.md` is not updated.
