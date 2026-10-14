#!/usr/bin/env bash
# Builds the two release packages of Fast Index.
#
#   bin/package.sh [output-dir]        (default: dist/ in the plugin folder; composer package does the same)
#
#   fast-index-<version>.zip           WordPress.org package: wp dist-archive with .distignore, so includes/premium/
#                                      and every development file are left out.
#   fast-index-premium-<version>.zip   Premium package for the Freemius dashboard: the same files plus
#                                      includes/premium/.
#
# Both unpack to a fast-index/ folder. They are built from a clean copy of the files git knows (tracked, or new
# and not ignored), so vendor/, dist/ and local caches never get in. The script fails when the WordPress.org
# package contains a premium path (outside the Freemius SDK) or the premium package lacks the module.
#
# Needs bash, git, WP-CLI with the dist-archive command (WP_CLI=/path/to/wp to choose one) and unzip.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/dist}"
WP="${WP_CLI:-wp}"
SLUG="fast-index"
VERSION="$(sed -n 's/^ \* Version: *\([0-9.]*\).*/\1/p' "$ROOT/index.php")"

if [ -z "$VERSION" ]; then
	echo "package.sh: no Version in index.php" >&2
	exit 1
fi

if ! command -v "$WP" >/dev/null 2>&1; then
	echo "package.sh: WP-CLI not found; set WP_CLI" >&2
	exit 1
fi

mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# Copies the files git knows into $1, keeping their paths.
copy_tree() {
	mkdir -p "$1"
	( cd "$ROOT" && git ls-files -z --cached --others --exclude-standard ) |
		while IFS= read -r -d '' file; do
			if [ -f "$ROOT/$file" ]; then
				mkdir -p "$1/$(dirname "$file")"
				cp -p "$ROOT/$file" "$1/$file"
			fi
		done
}

copy_tree "$WORK/org/$SLUG"
copy_tree "$WORK/premium/$SLUG"

# The premium package keeps the module: same .distignore without the includes/premium line.
grep -vx 'includes/premium' "$ROOT/.distignore" > "$WORK/premium/$SLUG/.distignore"

ORG_ZIP="$OUT/$SLUG-$VERSION.zip"
PREMIUM_ZIP="$OUT/$SLUG-premium-$VERSION.zip"

( cd "$WORK" && "$WP" dist-archive "$WORK/org/$SLUG" "$ORG_ZIP" --plugin-dirname="$SLUG" --force --quiet )
( cd "$WORK" && "$WP" dist-archive "$WORK/premium/$SLUG" "$PREMIUM_ZIP" --plugin-dirname="$SLUG" --force --quiet )

# Only the bundled Freemius SDK may have "premium" in a file name (its own templates).
if unzip -Z1 "$ORG_ZIP" | grep -v "^$SLUG/freemius-sdk/" | grep -qi 'premium'; then
	echo "package.sh: $ORG_ZIP contains a premium path" >&2
	exit 1
fi

if ! unzip -Z1 "$PREMIUM_ZIP" | grep -qx "$SLUG/includes/premium/class-fast-index-premium.php"; then
	echo "package.sh: $PREMIUM_ZIP has no premium module" >&2
	exit 1
fi

echo "$ORG_ZIP"
echo "$PREMIUM_ZIP"
