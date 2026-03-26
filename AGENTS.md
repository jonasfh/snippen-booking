# AGENTS.md

## Purpose
Guide AI agents working in this repository.

## Tech Stack
- PHP 8.2
- WordPress
- Composer
- PHPUnit
- Docker / Devcontainer

## Key Rules
- Follow WordPress plugin best practices
- Use PSR-4 autoloading
- Keep logic modular and testable
- Avoid unnecessary dependencies

## Structure
- booking-plugin.php is the entrypoint
- src/ contains application logic
- tests/ contains PHPUnit tests

## Development Workflow
- Code runs inside Docker container
- Use wp-cli for WordPress operations
- Use composer for dependencies

## Notes
- This is a custom plugin, not a theme
- Focus on maintainability over cleverness
