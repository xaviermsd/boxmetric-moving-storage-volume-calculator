#!/usr/bin/env bash
# Build the release zip for WordPress.org: copies the plugin without the files
# listed in .distignore into build/moving-storage-volume-calculator and zips it.
set -euo pipefail

SLUG="moving-storage-volume-calculator"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$ROOT/build"

rm -rf "$BUILD"
mkdir -p "$BUILD/$SLUG"

# Every line in .distignore names a file or folder at the top of the plugin.
EXCLUDES=()
while IFS= read -r line || [ -n "$line" ]; do
	line="${line%%#*}"
	line="$(echo "$line" | xargs)"
	[ -z "$line" ] && continue
	EXCLUDES+=("--exclude=./${line#/}")
done < "$ROOT/.distignore"

tar -C "$ROOT" --anchored "${EXCLUDES[@]}" -cf - . | tar -C "$BUILD/$SLUG" -xf -

if command -v zip >/dev/null 2>&1; then
	(cd "$BUILD" && zip -rq "$SLUG.zip" "$SLUG")
else
	# No zip tool (for example Git Bash on Windows): use Python instead.
	(cd "$BUILD" && python -c "import shutil, sys; shutil.make_archive(sys.argv[1], 'zip', '.', sys.argv[1])" "$SLUG")
fi
echo "Built $BUILD/$SLUG.zip"
