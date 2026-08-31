#!/usr/bin/env bash
#
# Tests for tools/check-changelog.sh.
#
# Every case names the guard it covers. Builds a throwaway tree per case, so
# these assert the parsing rather than this repository's changelog.
#
# Run from the repository root: bash tests/shell/check-changelog.test.sh

set -uo pipefail

SCRIPT=tools/check-changelog.sh
[ -f "$SCRIPT" ] || { echo "run this from the repository root" >&2; exit 1; }
SCRIPT_ABS=$PWD/$SCRIPT

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

# case_ <name> <version> <changelog-heading-block> <want_exit> <want_text>
case_() {
	local name=$1 version=$2 block=$3 want_exit=$4 want_text=$5
	local dir="$WORK/case"
	rm -rf "$dir"; mkdir -p "$dir"

	printf '{\n\t"version": "%s"\n}\n' "$version" > "$dir/package.json"
	printf '# Changelog\n\n%s\n\n### Added\n- something\n' "$block" > "$dir/CHANGELOG.md"

	local out got
	out=$( cd "$dir" && bash "$SCRIPT_ABS" 2>&1 )
	got=$?

	if [ "$got" = "$want_exit" ] && [[ $out == *"$want_text"* ]]; then
		PASS=$((PASS + 1))
		printf 'ok   %s\n' "$name"
	else
		FAIL=$((FAIL + 1))
		printf 'FAIL %s\n     want exit %s containing %q\n     got  exit %s: %s\n' \
			"$name" "$want_exit" "$want_text" "$got" "$out"
	fi
}

# The passing case.
case_ 'dated entry present' 0.33.0 '## [0.33.0] - 2026-09-14' 0 'has a dated entry for 0.33.0'

# A release whose version has no entry at all.
case_ 'no entry for this version' 0.33.0 '## [0.32.0] - 2026-08-31' 1 'no dated entry for 0.33.0'

# Tagging before renaming the Unreleased heading, which is the mistake the
# bump-at-release convention makes possible.
case_ 'entry still says Unreleased' 0.33.0 '## [Unreleased]' 1 'no dated entry for 0.33.0'

# A heading with no date does not count: the date is what says when it shipped.
case_ 'entry has no date' 0.33.0 '## [0.33.0]' 1 'no dated entry for 0.33.0'

# The version is a literal, not a pattern, so dots must not match any character.
case_ 'dots are not wildcards' 0.33.0 '## [0033.0] - 2026-09-14' 1 'no dated entry for 0.33.0'

# An Unreleased heading above a real entry is the normal mid-development state
# and must not stop a release of the version below it.
case_ 'unreleased above a real entry' 0.33.0 '## [Unreleased]

## [0.33.0] - 2026-09-14' 0 'has a dated entry for 0.33.0'

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
