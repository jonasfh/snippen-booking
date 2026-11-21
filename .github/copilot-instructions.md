# GitHub Copilot Instructions

Follow all project rules, architectural conventions, and multi-agent role contracts defined in:
- [AGENTS.md](file:///../AGENTS.md)
- [WORKFLOW.md](file:///../.agents/common-agent-instructions/WORKFLOW.md)
- [ROLES.md](file:///../.agents/common-agent-instructions/ROLES.md)

## Quick Reminders
- Always work on a `gh-issue/<id>` branch and format commits as `(#<id>) Description`.
- Automatically triage task complexity (Level 1 Fast Path vs. Level 2/3 delegation) per `ROLES.md`.
- Run `composer lint` and `composer test` before completing PHP changes; run `npm run test:ui:fast` only when UI/CSS/templates/frontend JS changed.
