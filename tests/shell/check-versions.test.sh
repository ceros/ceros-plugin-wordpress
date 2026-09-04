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

# tree <dir> <pkg> <stable> <header> <readme_md> <constant> <bootstrap> <block> [bootstrap_pin]
# A value of --omit leaves that declaration out of the file entirely. A
# <constant> of --commented or --blockcommented instead leaves a stale copy of
# each PHP declaration above the live one, which is what a real file
# accumulates, commented with // and with /* */ respectively.
# bootstrap_pin defaults to the same API pin ceros.php gets, so only the case
# that cares about pin drift has to mention it.
tree() {
	local dir=$1 pkg=$2 stable=$3 header=$4 readme_md=$5 constant=$6 bootstrap=$7 block=$8
	local bootstrap_pin=${9:-2026-08-06-09-00} lock=${10:-} lock_self=${11:-}
	local leftover_php= leftover_bootstrap= wrap_php=
	rm -rf "$dir"; mkdir -p "$dir/tests" "$dir/src/ceros"

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

	# npm keeps the root version and the "" self entry in step, so the fixture
	# carries both and either can be omitted alone. A dependency always follows
	# the "" entry, so a read that overshoots its object is visible.
	local lock_root=${lock:-$ref} lock_entry=${lock_self:-$ref}
	{
		printf '{\n\t"name": "ceros",\n'
		[ "$lock_root" != "--omit" ] && printf '\t"version": "%s",\n' "$lock_root"
		printf '\t"packages": {\n\t\t"": {\n'
		[ "$lock_entry" != "--omit" ] && printf '\t\t\t"version": "%s"\n' "$lock_entry"
		printf '\t\t},\n\t\t"node_modules/example": {\n\t\t\t"version": "9.9.9"\n\t\t}\n\t}\n}\n'
	} > "$dir/package-lock.json"

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

	# The two stale pins differ. The pin is compared against itself, so a pair of
	# reads that both took the leftover would agree and report nothing.
	case $constant in
		--commented)
			constant=$ref
			leftover_php="// define( 'CEROS_PLUGIN_VERSION', '0.30.0' );\n// define( 'CEROS_API_VERSION', '2025-12-10-09-11' );\n"
			leftover_bootstrap="// define( 'CEROS_PLUGIN_VERSION', '0.30.0' );\n// define( 'CEROS_API_VERSION', '2025-01-01-00-00' );\n"
			;;
		--blockcommented)
			constant=$ref
			# The single-line comment trails the block, with nothing closing it
			# below. A range delete on its own opens there and swallows every live
			# declaration under it. Ahead of the block, the block's own */ would
			# close the range and the shape would prove nothing.
			leftover_php="/*\ndefine( 'CEROS_PLUGIN_VERSION', '0.30.0' );\ndefine( 'CEROS_API_VERSION', '2025-12-10-09-11' );\n*/\n/* a note */\n"
			leftover_bootstrap="/*\ndefine( 'CEROS_PLUGIN_VERSION', '0.30.0' );\ndefine( 'CEROS_API_VERSION', '2025-01-01-00-00' );\n*/\n/* a note */\n"
			;;
		--starsinstrings)
			constant=$ref
			# A /* that opens nothing, because it is inside a string. The same shape
			# reaches a trailing // comment. Nothing closes it, so a strip that reads
			# /* anywhere deletes every declaration below.
			leftover_php="\$c = '/*';\n"
			leftover_bootstrap="\$c = '/*';\n"
			;;
		--starsaroundcode)
			constant=$ref
			wrap_php=1
			;;
		--onelinedocs)
			constant=$ref
			# One doc block on one line, carrying a second star at the open and
			# another inside. Either defeats a strip that stops at the first star it
			# meets, and the line then opens a range nothing below closes. One, not
			# two: a second would close the first one's range and prove nothing.
			leftover_php="/** Shared with tests/bootstrap.php. Separator is * here. */\n"
			leftover_bootstrap="/** Shared with ceros.php. Separator is * here. */\n"
			;;
	esac

	# ceros.php declares inside defined() guards, so its defines are indented and
	# the bootstrap's are not, covering both ends of what the read allows.
	printf '%b' "$leftover_php" >> "$dir/ceros.php"
	if [ "$constant" != "--omit" ]; then
		if [ -n "${wrap_php:-}" ]; then
			# Live code between two comments on one line. A strip that runs from the
			# first /* to the last */ takes the declaration with them.
			printf "/* one */\tdefine( 'CEROS_PLUGIN_VERSION', '%s' ); /* two */\n" "$constant" >> "$dir/ceros.php"
		else
			printf "\tdefine( 'CEROS_PLUGIN_VERSION', '%s' );\n" "$constant" >> "$dir/ceros.php"
		fi
	fi
	printf "\tdefine( 'CEROS_API_VERSION', '2026-08-06-09-00' );\n" >> "$dir/ceros.php"

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

	printf '<?php\n' > "$dir/tests/bootstrap.php"
	printf '%b' "$leftover_bootstrap" >> "$dir/tests/bootstrap.php"
	if [ "$bootstrap" != "--omit" ]; then
		printf "define( 'CEROS_PLUGIN_VERSION', '%s' );\n" "$bootstrap" >> "$dir/tests/bootstrap.php"
	fi
	if [ "$bootstrap_pin" != "--omit" ]; then
		printf "define( 'CEROS_API_VERSION', '%s' );\n" "$bootstrap_pin" >> "$dir/tests/bootstrap.php"
	fi
}

# case_ <name> <pkg> <stable> <header> <readme_md> <constant> <bootstrap> <block> <want_exit> <want_text> [bootstrap_pin] [lock] [lock_self]
case_() {
	local name=$1 pkg=$2 stable=$3 header=$4 readme_md=$5 constant=$6 bootstrap=$7 block=$8
	local want_exit=$9 want_text=${10}
	local bootstrap_pin=${11:-2026-08-06-09-00} lock=${12:-} lock_self=${13:-}
	local dir="$WORK/case"
	tree "$dir" "$pkg" "$stable" "$header" "$readme_md" "$constant" "$bootstrap" "$block" "$bootstrap_pin" "$lock" "$lock_self"

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
case_ 'every declaration agrees' 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'

# Stale declarations above the live ones, in each of the three shapes a PHP file
# comments something out with. Each drops if a PHP read stops telling a comment
# from the code around it.
case_ 'commented-out declarations above the live ones' \
	0.32.0 0.32.0 0.32.0 0.32.0 --commented 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'
case_ 'block-commented declarations above the live ones' \
	0.32.0 0.32.0 0.32.0 0.32.0 --blockcommented 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'
case_ 'doc blocks on one line' \
	0.32.0 0.32.0 0.32.0 0.32.0 --onelinedocs 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'

# A /* that is not a comment opener, and a pair of comments around live code.
# Both drop if the strip stops telling a comment from a star in the source.
case_ 'a star inside a string' \
	0.32.0 0.32.0 0.32.0 0.32.0 --starsinstrings 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'
case_ 'comments either side of a live declaration' \
	0.32.0 0.32.0 0.32.0 0.32.0 --starsaroundcode 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'

# One case per declaration. Each drops if that file stops being compared, which
# is the exact failure this script exists to prevent.
case_ 'readme.txt stable tag behind'   0.32.0 0.31.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'readme.txt Stable tag says 0.31.0'
case_ 'plugin header behind'           0.32.0 0.32.0 0.31.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'ceros.php Version says 0.31.0'
case_ 'README.md marker behind'        0.32.0 0.32.0 0.32.0 0.30.0 0.32.0 0.32.0 0.32.0 1 'README.md Current Version says 0.30.0'
case_ 'plugin version constant behind' 0.32.0 0.32.0 0.32.0 0.32.0 0.31.0 0.32.0 0.32.0 1 'ceros.php CEROS_PLUGIN_VERSION says 0.31.0'
case_ 'test bootstrap constant behind' 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.31.0 0.32.0 1 'tests/bootstrap.php CEROS_PLUGIN_VERSION says 0.31.0'
case_ 'block.json behind'              0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.31.0 1 'src/ceros/block.json says 0.31.0'
case_ 'lockfile behind'                0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'package-lock.json says 0.31.0' '' 0.31.0
case_ 'lockfile self entry behind'     0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'package-lock.json self entry says 0.31.0' '' '' 0.31.0

# Two at once reports both, so fixing one does not hide the other.
case_ 'two behind at once' 0.32.0 0.31.0 0.31.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'ceros.php Version says 0.31.0'

# Fails closed. A missing declaration must not read as agreement.
case_ 'package.json has no version'      --omit 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in package.json'
case_ 'readme.txt has no stable tag'     0.32.0 --omit 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in readme.txt Stable tag'
case_ 'plugin header missing'            0.32.0 0.32.0 --omit 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in ceros.php Version'
case_ 'README.md marker missing'         0.32.0 0.32.0 0.32.0 --omit 0.32.0 0.32.0 0.32.0 1 'no version found in README.md Current Version'
case_ 'plugin version constant missing'  0.32.0 0.32.0 0.32.0 0.32.0 --omit 0.32.0 0.32.0 1 'no version found in ceros.php CEROS_PLUGIN_VERSION'
case_ 'bootstrap constant missing'       0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 --omit 0.32.0 1 'no version found in tests/bootstrap.php CEROS_PLUGIN_VERSION'
case_ 'block.json version missing'       0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 --omit 1 'no version found in src/ceros/block.json'
case_ 'lockfile version missing'         0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in package-lock.json' '' --omit
case_ 'lockfile self entry missing'      0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in package-lock.json self entry' '' '' --omit

# The pin is compared against ceros.php rather than package.json, so its cases
# move the bootstrap's copy rather than a version.
case_ 'bootstrap pins a stale API version' 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'tests/bootstrap.php pins API version 2025-12-10-09-11' 2025-12-10-09-11
case_ 'bootstrap has no API pin'           0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'no API version found in tests/bootstrap.php' --omit

# A deeper "version" below the real one, in each JSON file. Drops if that read
# stops taking the first match and starts concatenating both.
case_ 'block.json has a deeper version below the real one' \
	0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 --nested 0 'every version declaration agrees at 0.32.0, and the API pin matches'
case_ 'package.json has a deeper version below the real one' \
	--nested 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'

# The same documents on one line, where there is no first match to take. Drops
# if the read stops being anchored, because it then reaches past the real key
# and reports 9.9.9 as the declared version.
case_ 'block.json written on one line' \
	0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 --minified 1 'no version found in src/ceros/block.json'
case_ 'package.json written on one line' \
	--minified 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 1 'no version found in package.json'

# Both files shaped at once. Drops if a shaped fixture goes back to declaring
# the reference argument, which is a sentinel here and not a version.
case_ 'both JSON files shaped at once' \
	--nested 0.32.0 0.32.0 0.32.0 0.32.0 0.32.0 --nested 0 'every version declaration agrees at 0.32.0, and the API pin matches'

# A shaped comment and a shaped package.json at once. Drops if a shaped
# branch goes back to declaring the reference argument.
case_ 'a shaped comment beside a shaped package.json' \
	--nested 0.32.0 0.32.0 0.32.0 --commented 0.32.0 0.32.0 0 'every version declaration agrees at 0.32.0, and the API pin matches'

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
