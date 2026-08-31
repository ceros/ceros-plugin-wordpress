#!/usr/bin/env bash
#
# Tests for tools/check-versions.sh.
#
# Every case names the guard it covers. The script's failure mode is a silent
# pass, so a green run only means something if removing a guard turns a case red.
#
# Builds a throwaway tree per case and runs the script inside it, so these assert
# the parsing rather than the state of this repository. No network, no WordPress.
#
# Run from the repository root: bash tests/shell/check-versions.test.sh

set -uo pipefail

SCRIPT=tools/check-versions.sh
[ -f "$SCRIPT" ] || { echo "run this from the repository root" >&2; exit 1; }
SCRIPT_ABS=$PWD/$SCRIPT

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

# tree <dir> <pkg> <stable> <header> <readme_md>
# A value of --omit leaves that declaration out of the file entirely.
tree() {
	local dir=$1 pkg=$2 stable=$3 header=$4 readme_md=$5
	rm -rf "$dir"; mkdir -p "$dir"

	if [ "$pkg" != "--omit" ]; then
		printf '{\n\t"name": "ceros",\n\t"version": "%s"\n}\n' "$pkg" > "$dir/package.json"
	else
		printf '{\n\t"name": "ceros"\n}\n' > "$dir/package.json"
	fi

	{
		printf '=== Ceros ===\n'
		printf 'Tested up to:      6.9\n'
		[ "$stable" != "--omit" ] && printf 'Stable tag:        %s\n' "$stable"
		printf 'Requires at least: 6.7\n'
	} > "$dir/readme.txt"

	{
		printf '<?php\n/**\n * Plugin Name:       Ceros\n'
		[ "$header" != "--omit" ] && printf ' * Version:           %s\n' "$header"
		printf ' */\n'
	} > "$dir/ceros.php"

	{
		printf '# Ceros WordPress Plugin\n\n## Version History\n\n'
		[ "$readme_md" != "--omit" ] && printf '**Current Version: %s**\n' "$readme_md"
	} > "$dir/README.md"
}

# case_ <name> <pkg> <stable> <header> <readme_md> <want_exit> <want_text>
case_() {
	local name=$1 pkg=$2 stable=$3 header=$4 readme_md=$5 want_exit=$6 want_text=$7
	local dir="$WORK/case"
	tree "$dir" "$pkg" "$stable" "$header" "$readme_md"

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

# The passing case. Drops if the script stops reading any one declaration.
case_ 'all four agree' 0.32.0 0.32.0 0.32.0 0.32.0 0 'all declare 0.32.0'

# One case per declaration. Each drops if that file stops being compared, which
# is the exact failure this script exists to prevent.
case_ 'readme.txt stable tag behind' 0.32.0 0.31.0 0.32.0 0.32.0 1 'readme.txt Stable tag says 0.31.0'
case_ 'plugin header behind'         0.32.0 0.32.0 0.31.0 0.32.0 1 'ceros.php Version says 0.31.0'
case_ 'README.md marker behind'      0.32.0 0.32.0 0.32.0 0.30.0 1 'README.md Current Version says 0.30.0'

# Two at once reports both, so fixing one does not hide the other.
case_ 'two behind at once' 0.32.0 0.31.0 0.31.0 0.32.0 1 'ceros.php Version says 0.31.0'

# Fails closed. A missing declaration must not read as agreement.
case_ 'package.json has no version' --omit 0.32.0 0.32.0 0.32.0 1 'no version found in package.json'
case_ 'readme.txt has no stable tag' 0.32.0 --omit 0.32.0 0.32.0 1 'no version found in readme.txt Stable tag'
case_ 'plugin header missing'        0.32.0 0.32.0 --omit 0.32.0 1 'no version found in ceros.php Version'
case_ 'README.md marker missing'     0.32.0 0.32.0 0.32.0 --omit 1 'no version found in README.md Current Version'

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
