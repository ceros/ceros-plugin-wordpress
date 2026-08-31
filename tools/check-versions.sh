#!/usr/bin/env bash
#
# Exit non-zero when the plugin's version declarations disagree.
#
# WordPress reads the ceros.php header to decide whether to offer an update, the
# plugin directory reads readme.txt's Stable tag, and the release workflow reads
# package.json. A release where those differ ships an install whose behaviour
# cannot be identified from the number it reports.
#
# block.json's version stamps the block's editor and front-end scripts, so a
# stale one leaves an upgraded install serving the previous build from cache.
#
# package.json is the reference, because the release workflow names both the tag
# and the ZIP from it.
#
# Fails closed: a declaration that is missing or unparseable exits non-zero
# rather than reading as agreement.
#
# Needs bash only, so the pre-push hook can run it without node or composer.
# Run from the repository root:
#   bash tools/check-versions.sh

set -uo pipefail

FAILED=0

fail() {
	echo "check-versions: $1" >&2
	FAILED=1
}

# A PHP file with its block comments removed: same-line /* */ first, so a
# single-line comment cannot open a range that then runs to the end of the file,
# and the multi-line ones after. Not used for the plugin header, which is itself
# inside a docblock.
#
# The first expression is the standard one for a complete /* ... */, which is
# what it takes to match a one-line /** doc */ and to stop at the nearest */
# rather than the last one on the line. The second only opens where /* begins a
# line, so a /* inside a string or a // comment stays code. The cost is that a
# block comment opened after code on the same line is not stripped, and telling
# that apart from a /* inside a string needs a PHP parser rather than sed.
php_code() {
	sed 's%/\*[^*]*\*\**\([^/*][^*]*\*\**\)*/%%g' "$1" \
		| sed '\%^[[:space:]]*/\*%,\%\*/%d'
}

for f in package.json readme.txt ceros.php README.md src/ceros/block.json tests/bootstrap.php; do
	[ -f "$f" ] || { echo "check-versions: $f not found, run this from the repository root." >&2; exit 1; }
done

pkg=$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' readme.txt | head -n 1)
header=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' ceros.php | head -n 1)
readme_md=$(sed -n 's/^\*\*Current Version:[[:space:]]*\([^*[:space:]]*\).*/\1/p' README.md | head -n 1)
block=$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' src/ceros/block.json | head -n 1)
constant=$(php_code ceros.php | sed -n "s/^[[:space:]]*define( 'CEROS_PLUGIN_VERSION', '\([^']*\)' ).*/\1/p" | head -n 1)
bootstrap=$(php_code tests/bootstrap.php | sed -n "s/^[[:space:]]*define( 'CEROS_PLUGIN_VERSION', '\([^']*\)' ).*/\1/p" | head -n 1)

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
check 'ceros.php CEROS_PLUGIN_VERSION' "$constant"
check 'tests/bootstrap.php CEROS_PLUGIN_VERSION' "$bootstrap"

# The pin is declared twice as well. It is not a plugin version, so it is
# compared against ceros.php rather than package.json: a stale copy in the
# bootstrap leaves the suite asserting the wrong contract while staying green.
pin=$(php_code ceros.php | sed -n "s/^[[:space:]]*define( 'CEROS_API_VERSION', '\([^']*\)' ).*/\1/p" | head -n 1)
pin_bootstrap=$(php_code tests/bootstrap.php | sed -n "s/^[[:space:]]*define( 'CEROS_API_VERSION', '\([^']*\)' ).*/\1/p" | head -n 1)

if [ -z "$pin" ]; then
	fail "no API version found in ceros.php."
elif [ -z "$pin_bootstrap" ]; then
	fail "no API version found in tests/bootstrap.php."
elif [ "$pin" != "$pin_bootstrap" ]; then
	fail "tests/bootstrap.php pins API version $pin_bootstrap, ceros.php pins $pin."
fi

if [ "$FAILED" -ne 0 ]; then
	echo >&2
	echo "Every declaration has to move together. The ceros.php header is the one" >&2
	echo "WordPress reads to decide whether to offer an update." >&2
	exit 1
fi

echo "check-versions: every version declaration agrees at $pkg, and the API pin matches."
