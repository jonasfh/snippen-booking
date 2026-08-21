# Agent Workflow Guidelines

## GitHub Issue Workflow

All changes made to the project MUST be based on a GitHub issue. If a task or instruction is given without an associated issue, create a GitHub issue containing the details of the work to be done (`gh issue create`) before starting.

Once a GitHub issue is identified or created, follow this workflow:

1. **Preparation**:
   - Start in `main` branch and pull latest: `git pull origin main`.
   - Verify GitHub authentication: `gh auth status`.
   - Read issue details using JSON mode: `gh issue view <id> --json title,body`.
2. **Branching**:
   - Create/use branch following pattern: `gh-issue/<id>` (e.g. `gh-issue/127`), based off `main`.
3. **Implementation**:
   - Resolve the issue.
   - Create/update tests and run `composer test` and `composer lint`.
4. **Submission & Commit Messages**:
   - Commit changed files.
   - **Commit Message**: Issue commits MUST start with `(#<id>)`, e.g., `(#127) Fixed xxx...`. Make separate commits for different issue numbers. General repo updates not tied to an issue do not require the header.
   - **Commit Suggestion**: ALWAYS suggest a commit message as plain text in a copy-pasteable code block. Focus on the problem solved in the header, with rationale in the body.
   - Push branch: `git push origin gh-issue/<id>`.
   - Create Pull Request: `gh pr create --body "Closes #<id>" --title "(#<id>) <Issue Title>"`.
5. **Issue Status**:
   - Add implementation notes and summary to the GitHub issue (`gh issue comment <id> --body "..."`).
6. **Merging Pull Requests**:
   - When instructed to merge, check PR status (`gh pr checks <id>`).
   - Merge cleanly: `gh pr merge <id> --rebase --delete-branch`.

## Versioning & Changelog

- **Version Bump**: Bump version in `src/wp-content/plugins/booking-plugin/booking-plugin.php` for functional changes (unless already bumped in branch).
- **CHANGELOG.md**: Every version bump must have an entry in `CHANGELOG.md` under `## [X.Y.Z] - YYYY-MM-DD`.

## Dependabot & Dependency Updates

Automated Dependabot PRs are intentionally disabled (`open-pull-requests-limit: 0` in `.github/dependabot.yml`). AI agents are responsible for reading Dependabot alerts and performing manual dependency updates.

1. **Checking Alerts**:
   - Inspect active Dependabot alerts via GitHub CLI: `gh api /repos/{owner}/{repo}/dependabot/alerts` (or `gh api repos/{owner}/{repo}/dependabot/alerts?state=open`).
2. **Issue Creation & Workflow**:
   - Create a GitHub issue for dependency updates if one does not exist (`gh issue create --title "Update dependency <pkg>" ...`).
   - Create a dedicated branch (`gh-issue/<id>`).
3. **Execution & Quality Assurance**:
   - Perform the update using package managers (`composer update <pkg>`, `npm update <pkg>`, or updating action versions in workflow files).
   - Bump plugin version in `booking-plugin.php` and document in `CHANGELOG.md`.
   - Run verification suite: `composer test` and `composer lint`.
   - Submit PR following standard GitHub Issue Workflow.

## Code Scanning & Security Alerts Workflow

Security & Code Scanning alerts (such as CodeQL, Dependabot, or Secret Scanning alerts) MUST follow the standard GitHub Issue Workflow:

1. **Issue Creation**:
   - Create a GitHub issue for the alerts if one does not already exist (`gh issue create --title "Fix Code Scanning alerts #3, #4, and #5" ...`).
2. **Branching & Implementation**:
   - Create a dedicated branch following `gh-issue/<id>` (e.g. `gh-issue/240`).
   - Implement fixes for the identified security/code scanning alerts.
3. **Versioning & Quality Control**:
   - Bump plugin version in `booking-plugin.php` and document changes in `CHANGELOG.md` referencing `(#<id>)`.
   - Run verification suite: `npm run test:js`, `composer test`, and `composer lint`.
4. **Commit, PR & Issue Comment**:
   - Commit with format `(#<id>) Description`.
   - Push branch and create PR (`gh pr create --body "Closes #<id>" --title "(#<id>) ..."`).
   - Add summary comment on the GitHub issue (`gh issue comment <id> --body "..."`).
