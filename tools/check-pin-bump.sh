#!/usr/bin/env bash
#
# Exit non-zero when a diff moves CEROS_API_VERSION without moving the plugin
# version.
#
# The pin decides which server contract an installed copy talks to, so moving it
# changes user-visible behaviour and has to ship as a release. Two installs
# reporting the same version must not be talking to different contracts.
#
# Usage, from the repository root:
#   bash tools/check-pin-bump.sh <base-ref>

set -uo pipefail

base=${1:-}
[ -n "$base" ] || { echo "check-pin-bump: usage: check-pin-bump.sh <base-ref>" >&2; exit 1; }

merge_base=$(git merge-base "$base" HEAD 2>/dev/null) || {
	echo "check-pin-bump: cannot find a merge base with $base." >&2
	exit 1
}

# Anchored on the define, not the constant name: the
# `if ( ! defined( 'CEROS_API_VERSION' ) )` guard line above it also mentions the
# name, so a reformat of that block would otherwise read as a pin change and
# demand a version bump for nothing.
#
# No [^+-] guard against the diff's own --- and +++ header lines: neither
# contains `define(` or a quoted "version", and adding one would consume the
# first character of the token being matched.
pin_changed=$(git diff "$merge_base"..HEAD -- ceros.php | grep -cE "^[+-].*define\( 'CEROS_API_VERSION'," || true)
version_changed=$(git diff "$merge_base"..HEAD -- package.json | grep -c '^[+-].*"version"' || true)

if [ "$pin_changed" -eq 0 ]; then
	echo "check-pin-bump: no API pin change in this diff."
	exit 0
fi

if [ "$version_changed" -eq 0 ]; then
	echo "check-pin-bump: CEROS_API_VERSION moved without a plugin version bump." >&2
	echo >&2
	echo "The pin decides which server contract an installed copy talks to, so it" >&2
	echo "ships as a release. Bump the version everywhere it is declared, which" >&2
	echo "'bash tools/check-versions.sh' will list, and add a CHANGELOG.md entry." >&2
	exit 1
fi

echo "check-pin-bump: the API pin moves together with the plugin version."
