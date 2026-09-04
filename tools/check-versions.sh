#!/usr/bin/env bash
#
# Exit non-zero when the plugin's version declarations disagree.
#
# WordPress reads the ceros.php header to decide whether to offer an update, the
# plugin directory reads readme.txt's Stable tag, and the release workflow reads
# package.json. A release where those differ ships an install whose behaviour
# cannot be identified from the number it reports.
#
# block.json's version is the ?ver= on the block's stylesheets, which have no
# generated asset file to supply one the way its scripts do, so a stale one
# leaves an upgraded install serving the previous CSS from cache.
#
# package.json is the reference, because the release workflow names both the tag
# and the ZIP from it.
#
# Fails closed: a declaration that is missing or unparseable exits non-zero
# rather than reading as agreement.
#
# Needs bash, sed, awk and coreutils, so the pre-push hook can run it without
# node or composer.
# Run from the repository root:
#   bash tools/check-versions.sh

set -uo pipefail

FAILED=0

fail() {
	echo "check-versions: $1" >&2
	FAILED=1
}

for f in package.json package-lock.json readme.txt ceros.php README.md src/ceros/block.json; do
	[ -f "$f" ] || { echo "check-versions: $f not found, run this from the repository root." >&2; exit 1; }
done

pkg=$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' readme.txt | head -n 1)
header=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' ceros.php | head -n 1)
readme_md=$(sed -n 's/^\*\*Current Version:[[:space:]]*\([^*[:space:]]*\).*/\1/p' README.md | head -n 1)
block=$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' src/ceros/block.json | head -n 1)
# npm rewrites the lockfile's version on the next install, so a stale one turns
# up as noise in whichever pull request runs it. It declares this package twice,
# at the root and again in the "" self entry, and both have to move.
#
# Each read is bounded to the object that owns it. The root read stops at
# "packages" and the self read stops where that entry closes, so neither can
# reach a dependency's version and report it as this package's.
lock=$(sed -n '/^[[:space:]]*"packages"[[:space:]]*:/q
	s/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' package-lock.json | head -n 1)
lock_self=$(awk '
	/^[[:space:]]*""[[:space:]]*:[[:space:]]*\{/ { inside = 1; next }
	inside && /^[[:space:]]*("[^"]*"[[:space:]]*:[[:space:]]*\{|\})/ { inside = 0 }
	inside && /^[[:space:]]*"version"[[:space:]]*:/ {
		sub(/^[^:]*:[[:space:]]*"/, ""); sub(/".*/, ""); print; exit }' package-lock.json)

if [ -z "$pkg" ]; then
	echo "check-versions: no version found in package.json." >&2
	exit 1
fi

check() {
	local label=$1 value=$2
	if [ -z "$value" ]; then
		fail "no version found in $label."
	elif [ "$value" != "$pkg" ]; then
		fail "$label says $value, package.json says $pkg."
	fi
}

check 'readme.txt Stable tag' "$stable"
check 'ceros.php Version' "$header"
check 'README.md Current Version' "$readme_md"
check 'src/ceros/block.json' "$block"
check 'package-lock.json' "$lock"
check 'package-lock.json self entry' "$lock_self"

if [ "$FAILED" -ne 0 ]; then
	echo >&2
	echo "Every declaration has to move together. The ceros.php header is the one" >&2
	echo "WordPress reads to decide whether to offer an update." >&2
	exit 1
fi

echo "check-versions: package.json, package-lock.json, readme.txt, ceros.php, README.md and src/ceros/block.json all declare $pkg."
