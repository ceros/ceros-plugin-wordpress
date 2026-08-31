#!/usr/bin/env bash
#
# Tests for tools/check-pin-bump.sh.
#
# Builds a throwaway git repository per case and commits on a branch, so these
# assert the diff reading rather than the state of this repository. No network.
#
# Run from the repository root: bash tests/shell/check-pin-bump.test.sh

set -uo pipefail

SCRIPT=tools/check-pin-bump.sh
[ -f "$SCRIPT" ] || { echo "run this from the repository root" >&2; exit 1; }
SCRIPT_ABS=$PWD/$SCRIPT

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

write_files() {
	local dir=$1 pin=$2 version=$3 unrelated=${4:-base} guard=${5:-"if ( ! defined( 'CEROS_API_VERSION' ) ) {"}
	# The guard line mentions the constant without setting it, which is why the
	# grep anchors on the define rather than on the name.
	printf '<?php\n%s\ndefine( '"'"'CEROS_API_VERSION'"'"', '"'"'%s'"'"' );\n}\n' "$guard" "$pin" > "$dir/ceros.php"
	printf '{\n\t"version": "%s"\n}\n' "$version" > "$dir/package.json"
	# Something for a case to change when neither the pin nor the version moves,
	# so "no pin change" is asserted against a real diff rather than an empty one.
	printf '%s\n' "$unrelated" > "$dir/README.md"
}

# case_ <name> <base_pin> <base_version> <head_pin> <head_version> <want_exit> <want_text> [head_guard]
case_() {
	local name=$1 base_pin=$2 base_version=$3 head_pin=$4 head_version=$5 want_exit=$6 want_text=$7
	set -- "$@" "${8:-}"
	local dir="$WORK/case"
	rm -rf "$dir"; mkdir -p "$dir"

	git -C "$dir" init -q -b main
	git -C "$dir" config user.email test@example.com
	git -C "$dir" config user.name Test
	write_files "$dir" "$base_pin" "$base_version"
	git -C "$dir" add -A
	git -C "$dir" commit -qm base

	git -C "$dir" switch -qc feature
	write_files "$dir" "$head_pin" "$head_version" changed "${8:-}"
	git -C "$dir" add -A
	git -C "$dir" commit -qm change

	local out got
	out=$( cd "$dir" && bash "$SCRIPT_ABS" main 2>&1 )
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

# The failure this gate exists for: the pin moved and the version did not.
case_ 'pin moved alone' \
	2025-12-10-09-11 0.31.0 2026-08-06-09-00 0.31.0 1 'moved without a plugin version bump'

# Allowed: they moved together.
case_ 'pin and version moved together' \
	2025-12-10-09-11 0.31.0 2026-08-06-09-00 0.32.0 0 'moves together with the plugin version'

# Allowed: an ordinary change that touches neither. Drops if the script starts
# failing every unrelated diff.
case_ 'neither moved' \
	2026-08-06-09-00 0.32.0 2026-08-06-09-00 0.32.0 0 'no API pin change in this diff'

# Allowed: a version-only bump, which is exactly what a release PR is.
case_ 'version moved alone' \
	2026-08-06-09-00 0.32.0 2026-08-06-09-00 0.33.0 0 'no API pin change in this diff'

# Reformatting the guard line changes a line that names the constant without
# setting it. Drops if the grep goes back to matching the name.
case_ 'the defined() guard line is reformatted' \
	2026-08-06-09-00 0.32.0 2026-08-06-09-00 0.32.0 0 'no API pin change in this diff' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) { // reformatted"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
