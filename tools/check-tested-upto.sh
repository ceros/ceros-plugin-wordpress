#!/usr/bin/env bash
#
# Exit non-zero when readme.txt's "Tested up to" header names a WordPress
# version older than the current release.
#
# WordPress treats this field as numbers only and ignores minor versions, so
# the comparison is major against major. Only "behind" fails; a header ahead of
# the current release is allowed.
#
# Fails closed: an unresolved version lookup, or a header that is not a version
# number, exits non-zero.
#
# Needs curl and jq. Run from the repository root:
#   bash tools/check-tested-upto.sh

set -euo pipefail

README=readme.txt
VERSION_API='https://api.wordpress.org/core/version-check/1.7/'

# The separator is a bracket expression because an unquoted \. inside [[ =~ ]]
# loses its backslash and matches any character, accepting "6,9" as a version.
VERSION_RE='^[0-9]+([.][0-9]+)?$'

fail() {
	echo "check-tested-upto: $1" >&2
	exit 1
}

[ -f "$README" ] || fail "$README not found, run this from the repository root."
command -v curl >/dev/null 2>&1 || fail "curl is required."
command -v jq >/dev/null 2>&1 || fail "jq is required."

# "6.9-RC1" and "6.9.1" both reduce to "6.9".
major() {
	printf '%s' "$1" | sed -e 's/-.*$//' -e 's/^\([0-9][0-9]*\.[0-9][0-9]*\).*$/\1/'
}

tested_raw=$(sed -n 's/^Tested up to:[[:space:]]*\([^[:space:]]*\).*$/\1/p' "$README" | head -n 1)
[ -n "$tested_raw" ] || fail "no \"Tested up to\" header found in $README."

tested=$(major "$tested_raw")

# A non-numeric value sorts unpredictably against a real version and can come
# out looking newer, passing a typo through the comparison below.
[[ $tested =~ $VERSION_RE ]] ||
	fail "\"Tested up to: $tested_raw\" in $README is not a version number."

# --fail turns an HTTP error into a non-zero exit instead of an error page.
response=$(curl -sS --fail --max-time 30 "$VERSION_API") ||
	fail "could not reach the WordPress version API, cannot confirm the current release."

# Each offer carries the newest version of one release branch. Take the highest
# rather than the first, because the order is not guaranteed and reading a
# maintenance branch as current would pass a stale header. Suffixed versions
# ("7.2-beta1") are prereleases. jq diagnostics are dropped so a non-JSON body
# reports as the failure below rather than a parse error first.
current=$(
	{
		printf '%s' "$response" \
			| jq -r '.offers[].current // empty | select(test("-") | not)' 2>/dev/null || true
	} | {
		while IFS= read -r offer; do
			candidate=$(major "$offer")
			if [[ $candidate =~ $VERSION_RE ]]; then
				printf '%s\n' "$candidate"
			fi
		done
	} | sort -V | tail -n 1
)
[ -n "$current" ] || fail "the WordPress version API returned no usable version."

if [ "$tested" = "$current" ]; then
	echo "check-tested-upto: \"Tested up to: $tested\" matches the current WordPress release."
	exit 0
fi

# sort -V compares version strings field by field, so 6.9 sorts before 6.10.
oldest=$(printf '%s\n%s\n' "$tested" "$current" | sort -V | head -n 1)

if [ "$oldest" = "$current" ]; then
	echo "check-tested-upto: \"Tested up to: $tested\" is ahead of the current WordPress release ($current)."
	exit 0
fi

echo "check-tested-upto: $README declares \"Tested up to: $tested_raw\", but WordPress $current is current." >&2
echo >&2
echo "Test the plugin against WordPress $current, then set \"Tested up to: $current\" in $README." >&2
echo "Raising the header without testing makes it a false claim about what was verified." >&2
exit 1
