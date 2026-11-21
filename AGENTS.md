# AGENTS.md

## Purpose
Guide AI agents working in `snippen-booking`. Keep this root file as a concise router; load detailed procedures from `.agents/` sub-guidelines and `skills/` on demand.

## Always-On Rules
- **WordPress & PSR-4**: Use `SnippenBooking\` namespace under `src/wp-content/plugins/booking-plugin/inc/`.
- **Database Rules**: Always include `created_at` and `modified_at` timestamps on custom database tables.
- **Formatting & Quality**: Run `composer lint:fix` (`phpcbf`) routinely on modified PHP files; resolve all `composer lint` (`phpcs`) errors/warnings and `composer test` failures before completion.
- **UI Testing Scope**: Run `npm run test:ui:fast` **only** when modifying UI, CSS, templates, or frontend logic (use `npm run test:ui:update` for intentional visual changes).
- **GitHub Issue Workflow**: Follow `gh-issue/<id>` branching, Conventional Commits (`<type>[scope]: <description> (#<id>)`), **Rebase and merge** (`gh pr merge <id> --rebase --delete-branch`), and stop after `gh pr create`.
- **Documentation & Versioning**: Keep `README.md`, `DEV_README.md`, and architecture docs in sync; bump version in `src/wp-content/plugins/booking-plugin/booking-plugin.php` and add a `CHANGELOG.md` entry under `## [X.Y.Z] - YYYY-MM-DD` on functional changes.

## Multi-Agent & Complexity Triage
- **Automatic Triage**: The primary agent automatically selects Level 1 (Single Agent Fast Path), Level 2 (Coordinator + `Researcher`/`Verifier` subagents), or Level 3 (Multi-Agent Team) per [ROLES.md](file:///.agents/common-agent-instructions/ROLES.md) and [PLAYBOOK.md](file:///.agents/common-agent-instructions/PLAYBOOK.md).
- **Approval Gate**: On Level 2/3 tasks, Coordinator runs `/plan` and stops **once** for user approval before launching parallel implementation workers.
- **Isolation**: Parallel `Implementer` workers (e.g., PHP backend vs. JS/UI frontend) must use isolated workspaces (`workspace: "share"` / `git worktree`); readers and `Verifier` share `inherit`.

## Modular Sub-guidelines

### Common Guidelines
- 🔄 **[Workflow Guidelines](file:///.agents/common-agent-instructions/WORKFLOW.md)**: Issue-driven flow, Fast Path vs. Multi-Agent Path, submodule updates, and PR rules.
- 🤝 **[Multi-Agent Roles](file:///.agents/common-agent-instructions/ROLES.md)**: Complexity triage, role contracts, hand-offs, and Antigravity/Copilot portability.
- 📖 **[Developer Playbook](file:///.agents/common-agent-instructions/PLAYBOOK.md)**: Practical developer manual for `/plan`, `/teamwork`, `/grill-me`, and Copilot.
- 📝 **[Documentation Standards](file:///.agents/common-agent-instructions/DOCUMENTATION.md)**: Documentation sync and Mermaid rules.
- 🧪 **[Quality & Testing Principles](file:///.agents/common-agent-instructions/TESTING.md)**: Automated tests, zero-lint policy, and formatting hygiene.
- 📐 **[Common Architecture](file:///.agents/common-agent-instructions/ARCHITECTURE.md)**: Modularity, decoupling, and universal DB timestamps.
- 📊 **[Diagram Guidelines](file:///.agents/diagram-agent-instructions/DIAGRAM_GUIDELINES.md)**: Mermaid vs. D2 vs. Python diagrams and visual verification.

### Technology & Project Guidelines
- 🛠️ **[PHP Guidelines](file:///.agents/php-agent-instructions/README.md)**: Environment, PSR-4, PHPCS/PHPCBF, PHPUnit, architecture, and Apache/DB debugging.
- 📦 **[Node.js & UI Testing](file:///.agents/node-agent-instructions/TESTING.md)**: Playwright visual regression testing and zero-lint policy.
- 📐 **[Project Architecture](file:///.agents/ARCHITECTURE.md)**: Plugin structure, Vipps ePayment, booking types, and CSS styling.
- 🧪 **[Project Testing & QA](file:///.agents/TESTING.md)**: Exact `composer` / `npm` test commands and log locations.
