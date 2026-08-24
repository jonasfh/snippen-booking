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
2. **Branching & Workflow**:
   - Create a dedicated branch using an associated GitHub Issue (`gh-issue/<id>`) OR directly using alert IDs: `dep-<id1>-<id2>-fix-dependabot-issues` (e.g. `dep-1-2-3-fix-dependabot-issues`).
3. **Commit, PR & Alert Referencing Rule**:
   - Do NOT reference security/dependabot alert numbers with shorthand issue syntax like `#3` (as GitHub issue #3 is completely distinct).
   - Commit messages should start with `(#<id>)` or `(dep-<ids>)`, e.g. `(dep-1-2-3) Fix dependabot issues`.
   - Push branch and create PR (`gh pr create`).
   - ALWAYS use full URLs when referencing alerts in titles, descriptions, and comments:
     - Dependabot: `https://github.com/jonasfh/snippen-booking/security/dependabot/<alert_id>`

## Code Scanning & Security Alerts Workflow

Security & Code Scanning alerts (such as CodeQL or Secret Scanning alerts) can be handled either via GitHub Issues or directly using Code Scanning alert IDs:

1. **Branching**:
   - Create a dedicated branch using an associated GitHub Issue (`gh-issue/<id>`) OR directly using alert IDs: `sec-<id1>-<id2>-fix-some-code-scanning-issues` (e.g. `sec-12-17-19-fix-some-code-scanning-issues`).
2. **Alert Link Rules**:
   - NEVER reference security alert numbers with `#<id>` (e.g. `#3`). Always use full URLs:
     - `https://github.com/jonasfh/snippen-booking/security/code-scanning/<id>`
3. **Implementation & Quality Control**:
   - Implement fixes for the identified security/code scanning alerts.
   - Bump plugin version in `booking-plugin.php` and document changes in `CHANGELOG.md` if relevant.
   - Run verification suite: `npm run test:js`, `composer test`, and `composer lint`.
4. **Commit, Push & PR**:
   - Commit with format `(#<id>) Description` or `(sec-<ids>) Description`, e.g. `(sec-12-17-19) Fix code scanning alerts`.
   - Push branch and create PR (`gh pr create`).
   - When dependabot/security issues are resolved, commit and push just like regular GitHub issues.
