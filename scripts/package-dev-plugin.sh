#!/usr/bin/env bash
set -euo pipefail

# Build an installable WordPress plugin zip for manual/dev upload (e.g. to picobell.no).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_SRC="$REPO_ROOT/src/wp-content/plugins/booking-plugin"
BUILD_DIR="$REPO_ROOT/build"
STAGE_DIR="$BUILD_DIR/snippen-booking"
OUT_ZIP="$BUILD_DIR/snippen-booking-dev.zip"

if [ ! -d "$PLUGIN_SRC" ]; then
  echo "Error: Plugin source directory not found: $PLUGIN_SRC" >&2
  exit 1
fi

rm -rf "$STAGE_DIR" "$OUT_ZIP"
mkdir -p "$STAGE_DIR"
cp -r "$PLUGIN_SRC"/. "$STAGE_DIR"/

if command -v zip >/dev/null 2>&1; then
  (cd "$BUILD_DIR" && zip -rq "$(basename "$OUT_ZIP")" snippen-booking/)
elif command -v python3 >/dev/null 2>&1; then
  python3 -c '
import os, sys, zipfile
build_dir, out_zip = sys.argv[1], sys.argv[2]
root_folder = os.path.join(build_dir, "snippen-booking")
with zipfile.ZipFile(out_zip, "w", zipfile.ZIP_DEFLATED) as zf:
    for dirpath, _, filenames in os.walk(root_folder):
        for fn in filenames:
            full = os.path.join(dirpath, fn)
            rel = os.path.relpath(full, build_dir)
            zf.write(full, rel)
' "$BUILD_DIR" "$OUT_ZIP"
else
  echo "Error: Neither 'zip' nor 'python3' is available." >&2
  exit 1
fi

rm -rf "$STAGE_DIR"
echo "Bygget $OUT_ZIP (klar for opplasting i WordPress Admin -> Utvidelser -> Last opp)"
