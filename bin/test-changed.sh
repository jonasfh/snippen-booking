#!/usr/bin/env bash
#
# Run only PHPUnit tests relevant to changed files in git.
# Usage: composer test:changed [extra phpunit args]
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# 1. Collect changed/uncommitted PHP files
CHANGED_FILES=$(git status --porcelain | awk '{print $2}' | grep '\.php$' || true)

# If working tree is clean, check against HEAD~1 or origin/main
if [ -z "$CHANGED_FILES" ]; then
  BASE_BRANCH="origin/main"
  if git rev-parse --verify "$BASE_BRANCH" >/dev/null 2>&1; then
    CHANGED_FILES=$(git diff --name-only "$BASE_BRANCH"...HEAD | grep '\.php$' || true)
  else
    CHANGED_FILES=$(git diff --name-only HEAD~1 | grep '\.php$' || true)
  fi
fi

if [ -z "$CHANGED_FILES" ]; then
  echo "Ingen endrede PHP-filer funnet i git. Kjører raske enhetstester (Unit)..."
  exec vendor/bin/phpunit --testsuite=Unit "$@"
fi

TEST_FILES=()

for FILE in $CHANGED_FILES; do
  # If the changed file itself is a test file
  if [[ "$FILE" =~ tests/.*Test\.php$ ]] && [ -f "$FILE" ]; then
    TEST_FILES+=("$FILE")
    continue
  fi

  # If it's a plugin source file, extract class/basename and find related test
  BASENAME=$(basename "$FILE" .php)
  MATCHES=$(find tests -type f -name "*${BASENAME}*Test.php" || true)
  if [ -n "$MATCHES" ]; then
    while IFS= read -r MATCH; do
      [ -n "$MATCH" ] && TEST_FILES+=("$MATCH")
    done <<< "$MATCHES"
  fi
done

# Remove duplicate test files
UNIQUE_TESTS=($(echo "${TEST_FILES[@]:-}" | tr ' ' '\n' | sort -u | tr '\n' ' '))

if [ ${#UNIQUE_TESTS[@]} -eq 0 ]; then
  echo "Ingen spesifikke testfiler matchet de endrede PHP-filene:"
  echo "$CHANGED_FILES"
  echo "Kjører enhetstester (Unit) som fallback..."
  exec vendor/bin/phpunit --testsuite=Unit "$@"
else
  echo "Kjører berørte tester for endrede filer:"
  for T in "${UNIQUE_TESTS[@]}"; do
    echo "  - $T"
  done
  exec vendor/bin/phpunit "${UNIQUE_TESTS[@]}" "$@"
fi
