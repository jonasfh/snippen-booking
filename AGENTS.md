# AGENTS.md

## Purpose
Guide AI agents working in this repository.

## Key Rules & Guidelines

- **Follow WordPress Plugin Best Practices**: Use PSR-4 autoloading (`SnippenBooking\` namespace) under `src/wp-content/plugins/booking-plugin/inc/`.
- **Testing & Quality Control**:
  - Always write tests for new functionality and update existing tests when modifying functionality.
  - Run `composer lint` and `composer test` to verify all changes before completion. Resolving all PHPCS errors and warnings is mandatory.
- **Database Rules**: Always include `created_at` and `modified_at` timestamps on custom database tables.
- **GitHub Issue Workflow**: All development MUST follow an associated GitHub Issue. Create branches like `gh-issue/<id>`, create PRs, and format commit messages as `(#<id>) Description`.
- **Documentation**: Keep `README.md` (user-facing) and `DEV_README.md` (developer-facing) updated with changes.

## Modular Sub-guidelines

Detailed guidelines are split into specialized modules under `.agents/`:

- 📐 **[Architecture & Coding Standards](file:///.agents/ARCHITECTURE.md)**: Tech stack, plugin directory structure, namespace mapping, DB rules, and CSS styling.
- 🔄 **[GitHub Issue Workflow](file:///.agents/WORKFLOW.md)**: Branching strategy, commit message rules, PR requirements, versioning, and CHANGELOG updates.
- 🧪 **[Testing & Quality Assurance](file:///.agents/TESTING.md)**: Running tests and linting (`composer test`, `composer lint`), writing unit/integration tests, and debugging/logging.
- 📝 **[Documentation Standards & Diagrams](file:///.agents/DOCUMENTATION.md)**: README/DEV_README maintenance and Mermaid `erDiagram` syntax constraints.

## Versioning & Changelog
- **Version Bump**: Update version in `src/wp-content/plugins/booking-plugin/booking-plugin.php` on functional changes.
- **CHANGELOG.md**: Add an entry under `## [X.Y.Z] - YYYY-MM-DD` for every version bump.
