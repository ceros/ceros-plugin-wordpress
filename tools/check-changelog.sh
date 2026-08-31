#!/usr/bin/env bash
#
# Exit non-zero when CHANGELOG.md has no dated entry for the version in
# package.json.
#
# The date says when the version shipped, so an undated heading, an Unreleased
# heading, or no heading at all leaves no record of what a given install holds.
#
# Run from the repository root:
#   bash tools/check-changelog.sh

set -uo pipefail

for f in package.json CHANGELOG.md; do
	[ -f "$f" ] || { echo "check-changelog: $f not found, run this from the repository root." >&2; exit 1; }
done

version=$(sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -n 1)
[ -n "$version" ] || { echo "check-changelog: no version found in package.json." >&2; exit 1; }

# The version is a literal: escaping the dots stops 0.33.0 matching 0033x0.
escaped=${version//./\\.}

if grep -qE "^## \[${escaped}\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$" CHANGELOG.md; then
	echo "check-changelog: CHANGELOG.md has a dated entry for $version."
	exit 0
fi

echo "check-changelog: CHANGELOG.md has no dated entry for $version." >&2
echo >&2
echo "Add a heading of the form '## [$version] - YYYY-MM-DD', dated the day the" >&2
echo "release is tagged. Rename the '## [Unreleased]' heading rather than adding" >&2
echo "a second one." >&2
exit 1
