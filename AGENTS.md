# AGENTS.md

## Purpose
Guide AI agents working in this repository.

## Key Rules & Guidelines

- **Follow WordPress Plugin Best Practices**: Use PSR-4 autoloading (`SnippenBooking\` namespace) under `src/wp-content/plugins/booking-plugin/inc/`.
- **Testing & Quality Control**:
  - Always write tests for new functionality and update existing tests when modifying functionality.
  - Run `phpcbf` (or `composer lint:fix`) routinely on modified files to automatically fix coding standards and formatting violations.
  - Run `composer lint` (or `phpcs`) and `composer test` to verify all changes before completion. Resolving all PHPCS errors and warnings is mandatory.
- **UI & Visual Regression Testing**:
  - Run visual UI tests (`npm run test:ui:fast`) **only** when actively modifying UI, CSS, templates, or frontend logic. Never run visual suites for purely backend, PHP, API, or database changes.
  - Use `npm run test:ui:update` to update golden baselines when UI changes are intentional.
- **Database Rules**: Always include `created_at` and `modified_at` timestamps on custom database tables.
- **GitHub Issue Workflow**: All development MUST follow an associated GitHub Issue or direct Code Scanning / Dependabot alert IDs. Create branches like `gh-issue/<id>`, `dep-<ids>-fix-dependabot-issues`, or `sec-<ids>-fix-code-scanning-issues`, create PRs, and format commit messages accordingly (`(#<id>) Description` or `(sec-<ids>) Description` / `(dep-<ids>) Description`).
- **PR Merging Strategy**: When merging PRs, ALWAYS use **Rebase and merge** (`gh pr merge <id> --rebase --delete-branch`) by default. If rebasing issues or conflicts arise, create a standard merge commit (`gh pr merge <id> --merge --delete-branch`). Do NOT use squash and merge (`--squash`) unless explicitly instructed or required for a specific reason.
- **Documentation**: Always update documentation (`README.md`, `DEV_README.md`, and architecture documents) whenever implementing new features, endpoints, data models, or database schemas. Keeping documentation in sync with the codebase is mandatory.

## Modular Sub-guidelines

### Common (Technology-Agnostic) Submodule Guidelines
- 🔄 **[Workflow Guidelines](file:///.agents/common-agent-instructions/WORKFLOW.md)**: GitHub issue-driven workflow, branching, commit conventions, PR merge rules, SemVer, and CHANGELOG maintenance.
- 📝 **[Documentation Standards & Diagrams](file:///.agents/common-agent-instructions/DOCUMENTATION.md)**: Documentation synchronization, README/DEV_README maintenance, and Mermaid syntax rules.
- 🧪 **[Quality & Testing Principles](file:///.agents/common-agent-instructions/TESTING.md)**: Automated test requirements, zero-linting policy, and formatting hygiene.
- 📐 **[Common Architecture](file:///.agents/common-agent-instructions/ARCHITECTURE.md)**: Modularity, decoupling, and universal database timestamp rules.

### Node.js & UI Testing Guidelines
- 📦 **[Node.js Testing & Quality Assurance](file:///.agents/node-agent-instructions/TESTING.md)**: Playwright visual regression testing, snapshot tolerances, and zero-lint policy.

### Project-Specific Guidelines
- 📐 **[Architecture & Coding Standards](file:///.agents/ARCHITECTURE.md)**: Tech stack, plugin directory structure, namespace mapping, DB rules, and CSS styling.
- 🧪 **[Testing & Quality Assurance](file:///.agents/TESTING.md)**: Running tests and linting (`composer test`, `composer lint`), writing unit/integration tests, and debugging/logging.

## Versioning & Changelog
- **Version Bump**: Update version in `src/wp-content/plugins/booking-plugin/booking-plugin.php` on functional changes.
- **CHANGELOG.md**: Add an entry under `## [X.Y.Z] - YYYY-MM-DD` for every version bump.
