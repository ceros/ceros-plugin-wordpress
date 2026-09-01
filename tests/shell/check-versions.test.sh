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

# tree <dir> <pkg> <stable> <header> <readme_md> <block>
# A value of --omit leaves that declaration out of the file entirely. The two
# JSON declarations also take --nested and --minified, the same document
# indented and on one line, both carrying a second and deeper "version".
tree() {
	local dir=$1 pkg=$2 stable=$3 header=$4 readme_md=$5 block=$6
	rm -rf "$dir"; mkdir -p "$dir/src/ceros"

	# The shaped fixtures below declare whatever the reference declares, so they
	# agree unless the shape itself defeats the read. A sentinel is not a version,
	# so it cannot be the thing they agree with.
	local ref=$pkg
	case $pkg in --*) ref=0.32.0 ;; esac

	case $pkg in
		--omit)
			printf '{\n\t"name": "ceros"\n}\n' > "$dir/package.json"
			;;
		--nested)
			printf '{\n\t"version": "%s",\n\t"dependencies": {\n\t\t"version": "9.9.9"\n\t}\n}\n' "$ref" \
				> "$dir/package.json"
			;;
		--minified)
			printf '{"version":"%s","dependencies":{"version":"9.9.9"}}\n' "$ref" > "$dir/package.json"
			;;
		*)
			printf '{\n\t"name": "ceros",\n\t"version": "%s"\n}\n' "$pkg" > "$dir/package.json"
			;;
	esac

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

	# --nested and --minified are the same document, one indented and one on a
	# single line. Both carry a second, deeper "version" that agrees with nothing:
	# indented, the first match still has to win; on one line, the read has to come
	# back empty rather than reaching past the real key to the deeper one.
	case $block in
		--nested)
			printf '{\n\t"version": "%s",\n\t"example": {\n\t\t"attributes": {\n\t\t\t"version": "9.9.9"\n\t\t}\n\t}\n}\n' "$ref" \
				> "$dir/src/ceros/block.json"
			;;
		--minified)
			printf '{"version":"%s","example":{"attributes":{"version":"9.9.9"}}}\n' "$ref" \
				> "$dir/src/ceros/block.json"
			;;
		*)
			# apiVersion is a neighbouring key the real file has; schemaVersion is one
			# it does not, quoted directly so a pattern relaxed to tolerate a renamed
			# key captures 9.9.9 here instead of shipping that relaxation.
			{
				printf '{\n\t"apiVersion": 3,\n\t"schemaVersion": "9.9.9",\n'
				[ "$block" != "--omit" ] && printf '\t"version": "%s",\n' "$block"
				printf '\t"name": "create-block/ceros"\n}\n'
			} > "$dir/src/ceros/block.json"
			;;
	esac
}

# case_ <name> <pkg> <stable> <header> <readme_md> <block> <want_exit> <want_text>
case_() {
	local name=$1 pkg=$2 stable=$3 header=$4 readme_md=$5 block=$6 want_exit=$7 want_text=$8
	local dir="$WORK/case"
	tree "$dir" "$pkg" "$stable" "$header" "$readme_md" "$block"

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
case_ 'all five agree' 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0 'all declare 0.32.0'

# One case per declaration. Each drops if that file stops being compared, which
# is the exact failure this script exists to prevent.
case_ 'readme.txt stable tag behind' 0.32.0 0.31.0 0.32.0 0.32.0 0.32.0 1 'readme.txt Stable tag says 0.31.0'
case_ 'plugin header behind'         0.32.0 0.32.0 0.31.0 0.32.0 0.32.0 1 'ceros.php Version says 0.31.0'
case_ 'README.md marker behind'      0.32.0 0.32.0 0.32.0 0.30.0 0.32.0 1 'README.md Current Version says 0.30.0'
case_ 'block.json behind'            0.32.0 0.32.0 0.32.0 0.32.0 0.31.0 1 'src/ceros/block.json says 0.31.0'

# A deeper "version" below the real one. Drops if the read stops taking the
# first match and starts concatenating both.
case_ 'block.json has a deeper version below the real one' \
	0.32.0 0.32.0 0.32.0 0.32.0 --nested 0 'all declare 0.32.0'

# The same document on one line, where there is no first match to take. Drops if
# the read stops being anchored, because it then reaches past the real key and
# reports 9.9.9 as the declared version.
case_ 'block.json written on one line' \
	0.32.0 0.32.0 0.32.0 0.32.0 --minified 1 'no version found in src/ceros/block.json'

# The reference declaration gets the same two shapes: it is read by the same
# kind of pattern, so it carries the same two ways of reading the wrong key.
case_ 'package.json has a deeper version below the real one' \
	--nested 0.32.0 0.32.0 0.32.0 0.32.0 0 'all declare 0.32.0'
case_ 'package.json written on one line' \
	--minified 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in package.json'

# Two at once reports both, so fixing one does not hide the other.
case_ 'two behind at once' 0.32.0 0.31.0 0.31.0 0.32.0 0.32.0 1 'ceros.php Version says 0.31.0'

# Fails closed. A missing declaration must not read as agreement.
case_ 'package.json has no version' --omit 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in package.json'
case_ 'readme.txt has no stable tag' 0.32.0 --omit 0.32.0 0.32.0 0.32.0 1 'no version found in readme.txt Stable tag'
case_ 'plugin header missing'        0.32.0 0.32.0 --omit 0.32.0 0.32.0 1 'no version found in ceros.php Version'
case_ 'README.md marker missing'     0.32.0 0.32.0 0.32.0 --omit 0.32.0 1 'no version found in README.md Current Version'
case_ 'block.json version missing'   0.32.0 0.32.0 0.32.0 0.32.0 --omit 1 'no version found in src/ceros/block.json'

# Both files shaped at once. Drops if a shaped fixture goes back to declaring
# the reference argument, which is a sentinel here and not a version.
case_ 'both JSON files shaped at once' \
	--nested 0.32.0 0.32.0 0.32.0 --nested 0 'all declare 0.32.0'

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
