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

# case_ <name> <version> <changelog-heading-block> <want_exit> <want_text>...
# Every want_text has to appear, so a case can assert several problems at once.
case_() {
	local name=$1 version=$2 block=$3 want_exit=$4
	shift 4
	# Without this a case with no want_text passes on its exit code alone.
	[ "$#" -gt 0 ] || { echo "case_ $name: no want_text given" >&2; exit 1; }
	local dir="$WORK/case"
	rm -rf "$dir"; mkdir -p "$dir"

	printf '{\n\t"version": "%s"\n}\n' "$version" > "$dir/package.json"
	printf '# Changelog\n\n%s\n\n### Added\n- something\n' "$block" > "$dir/CHANGELOG.md"

	local out got
	out=$( cd "$dir" && bash "$SCRIPT_ABS" 2>&1 )
	got=$?

	local want wanted= missing=
	for want in "$@"; do
		# An empty want_text matches anything, so it asserts nothing.
		[ -n "$want" ] || { echo "case_ $name: empty want_text given" >&2; exit 1; }
		wanted="$wanted $(printf '%q' "$want")"
		[[ $out == *"$want"* ]] || missing="$missing $(printf '%q' "$want")"
	done

	if [ "$got" = "$want_exit" ] && [ -z "$missing" ]; then
		PASS=$((PASS + 1))
		printf 'ok   %s\n' "$name"
	else
		FAIL=$((FAIL + 1))
		printf 'FAIL %s\n     want exit %s containing%s\n     got  exit %s: %s\n' \
			"$name" "$want_exit" "$wanted" "$got" "$out"
		[ -z "$missing" ] || printf '     missing%s\n' "$missing"
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

# A heading for a version nothing else declares: a release that cannot be built.
case_ 'entry ahead of package.json' 0.32.0 '## [0.33.0] - 2026-09-14

## [0.32.0] - 2026-08-31' 1 'ahead of package.json'

# The other comparison read lexically: 0.10.0 sorts below 0.9.0, so the highest
# heading reads as the current version and the entry ahead of it goes unreported.
case_ 'ahead check is version-aware' 0.9.0 '## [0.10.0] - 2026-09-14

## [0.9.0] - 2026-08-01' 1 'ahead of package.json'

# A level-3 heading is not a release heading. Pins the '^' on the dated pattern.
case_ 'deeper heading is not an entry' 0.33.0 '### [0.33.0] - 2026-09-14' 1 'no dated entry for 0.33.0'

# Anything after the date other than [YANKED] is prose. Pins the '$'.
case_ 'text after the date is not an entry' 0.33.0 '## [0.33.0] - 2026-09-14 (see the notes)' 1 'no dated entry for 0.33.0'

# A withdrawn release still shipped, and the entry still records when.
case_ 'a yanked release is still dated' 0.33.0 '## [0.33.0] - 2026-09-14 [YANKED]' 0 'has a dated entry for 0.33.0'

# Only an exact 'Unreleased' is that section. Pins the -x on the count and the
# '$' on the sections pattern.
case_ 'unreleased is matched exactly' 0.33.0 '## [Unreleased notes]

## [Unreleased]

## [0.33.0] - 2026-09-14' 0 'headings are in order'

# Two headings for one version leave no single answer to what that release held.
case_ 'version named twice' 0.33.0 '## [0.33.0] - 2026-09-14

## [0.33.0] - 2026-08-31' 1 'more than once'

# Newest first, so the top of the file is the current release.
case_ 'headings out of order' 0.33.0 '## [0.32.0] - 2026-08-31

## [0.33.0] - 2026-09-14' 1 'not in descending order'

# Ordering is version-aware: lexically 0.9.0 sorts above 0.10.0, and reading it
# that way would pass a file that is out of order.
case_ 'ten sorts above nine' 0.10.0 '## [0.9.0] - 2026-08-31

## [0.10.0] - 2026-09-14' 1 'not in descending order'

# The mirror of the case above, and the earliest headings carry no date, so
# ordering has to hold without one.
case_ 'undated tail stays in order' 0.10.0 '## [0.10.0] - 2026-09-14

## [0.9.0]

## [0.1.0]' 0 'headings are in order'

# Carries a number but is no version. The digit must be anchored to the start,
# or this sorts above every real release.
case_ 'bracketed non-versions are not versions' 0.33.0 '## [0.33.0] - 2026-09-14

## [Keep a Changelog 1.1.0]

## Support' 0 'headings are in order'

# A non-version above Unreleased strands nothing. Its number is why the anchor
# on the sections pattern matters.
case_ 'bracketed non-version above unreleased' 0.33.0 '## [Keep a Changelog 1.1.0]

## [Unreleased]

## [0.33.0] - 2026-09-14' 0 'headings are in order'

# A release renames the first Unreleased heading, stranding a second one.
case_ 'unreleased named twice' 0.33.0 '## [Unreleased]

## [0.33.0] - 2026-09-14

## [Unreleased]' 1 'never ship'

# The same stranding, with one Unreleased heading left below a release.
case_ 'unreleased below a release' 0.33.0 '## [0.33.0] - 2026-09-14

## [Unreleased]' 1 'sits below a released version'

# The checks accumulate, so one run reports every problem.
case_ 'problems are reported together' 0.34.0 '## [0.35.0] - 2026-09-14

## [0.35.0] - 2026-09-14

## [0.32.0] - 2026-08-31' 1 'no dated entry for 0.34.0' 'more than once' 'ahead of package.json'

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
