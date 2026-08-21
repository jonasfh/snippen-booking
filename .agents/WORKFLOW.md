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
