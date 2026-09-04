#!/usr/bin/env bash
#
# Exit non-zero when CHANGELOG.md has no dated entry for the version in
# package.json, or when its headings contradict each other.
#
# The date is what says when a version shipped, so an undated heading leaves no
# record of what a given install holds.
#
# Needs bash, sed, grep and coreutils, so it runs on a bare checkout.
# Run from the repository root:
#   bash tools/check-changelog.sh

set -uo pipefail

FAILED=0

fail() {
	echo "check-changelog: $1" >&2
	FAILED=1
}

for f in package.json CHANGELOG.md; do
	[ -f "$f" ] || { echo "check-changelog: $f not found, run this from the repository root." >&2; exit 1; }
done

version=$(sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)
[ -n "$version" ] || { echo "check-changelog: no version found in package.json." >&2; exit 1; }

# The version is a literal: escaping the dots stops 0.33.0 matching 0033x0.
escaped=${version//./\\.}

# [YANKED] is how Keep a Changelog marks a withdrawn release. It still shipped.
if ! grep -qE "^## \[${escaped}\] - [0-9]{4}-[0-9]{2}-[0-9]{2}( \[YANKED\])?$" CHANGELOG.md; then
	fail "CHANGELOG.md has no dated entry for $version, which reads '## [$version] - YYYY-MM-DD'."
fi

# sections is headings plus Unreleased in file order, which is what the
# placement check needs; a bracketed heading that is neither stays out of it.
bracketed=$(sed -n 's/^## \[\([^]]*\)\].*/\1/p' CHANGELOG.md)
headings=$(printf '%s\n' "$bracketed" | grep -E '^[0-9]' || true)
sections=$(printf '%s\n' "$bracketed" | grep -E '^([0-9]|Unreleased$)' || true)

# A release renames the first Unreleased heading, so entries under any other
# never ship.
unreleased=$(printf '%s\n' "$bracketed" | grep -cxF 'Unreleased' || true)

if [ "$unreleased" -gt 1 ]; then
	fail "CHANGELOG.md has $unreleased '## [Unreleased]' headings, so entries under all but the first never ship."
elif [ "$unreleased" -eq 1 ] && [ "$(printf '%s\n' "$sections" | head -n 1)" != "Unreleased" ]; then
	fail "CHANGELOG.md's '## [Unreleased]' heading sits below a released version, so entries under it never ship."
fi

duplicates=$(printf '%s\n' "$headings" | sort | uniq -d | paste -sd' ' -)
[ -z "$duplicates" ] || fail "CHANGELOG.md names these versions more than once: $duplicates."

# -V so 0.9.0 sorts below 0.10.0, which a lexical sort has the wrong way round.
highest=$(printf '%s\n' "$headings" | sort -V | tail -n 1)
newest=$(printf '%s\n%s\n' "$version" "$highest" | sort -V | tail -n 1)

# The second clause is not redundant: once the dated-entry check has failed, the
# newest heading can sit behind package.json rather than ahead.
if [ "$highest" != "$version" ] && [ "$newest" = "$highest" ]; then
	fail "CHANGELOG.md has an entry for $highest, ahead of package.json's $version."
fi

if [ "$headings" != "$(printf '%s\n' "$headings" | sort -Vr)" ]; then
	fail "CHANGELOG.md's version headings are not in descending order."
fi

if [ "$FAILED" -ne 0 ]; then
	echo >&2
	echo "Version headings run newest first, one per version, none ahead of" >&2
	echo "package.json, below at most one '## [Unreleased]' at the top. A release" >&2
	echo "renames that heading and dates it rather than adding a second one." >&2
	exit 1
fi

echo "check-changelog: CHANGELOG.md has a dated entry for $version, and its headings are in order."
