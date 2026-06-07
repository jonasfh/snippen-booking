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
- **ALWAYS suggest a commit message** Focus on the problem solved. Use the header for the issue/outcome, and the body for implementation details and rationale. Format it as plain text in a copy-pasteable code block, without qusotes.
- **GitHub Issue Workflow**: When tasked with solving a specific GitHub issue (e.g., "solve issue #15"), follow the dedicated workflow below.
- **ALWAYS update version and changelog when changes to src/ folder is made**
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

# Run all tests
composer test

# Run only unit tests
composer test:unit

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

## GitHub Issue Workflow

All changes made to the project MUST be based on a GitHub issue. If a task or instruction is given without an associated issue, the agent MUST first create a GitHub issue containing the details of the work to be done (using `gh issue create`) before starting execution.

Once a GitHub issue is identified or created, the following workflow **MUST** be followed:

1.  **Preparation**:
    - For issues 127, 128, 129, 130, 142, 144: start in the `v2.0` branch instead of `main`. Ensure `v2.0` is up to date: `git pull origin v2.0`.
    - For all other issues, start in the `main` branch and ensure `main` is up to date: `git pull origin main`.
    - Check if the terminal is logged in to GitHub: `gh auth status`. If not, inform the developer and ask them to run `gh auth login`.
    - To read issue details, ALWAYS use `gh issue view <id> --json title,body` instead of `gh issue view <id>`. This prevents the command from failing due to deprecation warnings related to Projects (classic).
2.  **Branching**:
    - Check if a branch already exists for the issue.
    - If not, create a new branch using the pattern: `gh-issue/<id>` (e.g., `gh-issue/127`). Base this branch off `v2.0` for issues 127, 128, 129, and 130, or off `main` for other issues.
3.  **Implementation**:
    - Solve the issue as requested.
    - Create tests for new functionality and run all tests to verify.
4.  **Submission**:
    - Commit changed files.
    - Update changelog in `CHANGELOG.md` and version in `booking-plugin.php`.
    - **Commit Message**: The message **MUST** start with the issue reference in parentheses, e.g., `(#127) Fixed xxx...`.
    - Push the branch to origin.
    - Create a Pull Request using `gh`. For issues 127, 128, 129, and 130, target the `v2.0` branch: `gh pr create --base v2.0 --body "Closes #<id>" --title "(#<id>) <Issue Title>"`. For other issues, target `main` (default): `gh pr create --body "Closes #<id>" --title "(#<id>) <Issue Title>"`.
5.  **Update github issues with implementation notes**: Add implementation details and a summary of the changes made to resolve the issue.

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

## Versioning & Changelog
- **Version Bump**: When making functional changes, you must bump the version number in `src/wp-content/plugins/booking-plugin/booking-plugin.php`, UNLESS this is already done in the current branch.
- **CHANGELOG.md**: Every version bump must be accompanied by an entry in `CHANGELOG.md` under a header like `## [X.Y.Z] - YYYY-MM-DD`.
- **PR Check**: Pull Requests will fail if the version in the plugin file already exists as a Git tag or if the `CHANGELOG.md` is not updated.
