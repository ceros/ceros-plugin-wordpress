#!/usr/bin/env bash
#
# Tests for tools/check-pin-bump.sh.
#
# Builds a throwaway git repository per case. No network.
#
# Run from the repository root: bash tests/shell/check-pin-bump.test.sh

set -uo pipefail

SCRIPT=tools/check-pin-bump.sh
[ -f "$SCRIPT" ] || { echo "run this from the repository root" >&2; exit 1; }
SCRIPT_ABS=$PWD/$SCRIPT

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

# Stub awk. BSD awk rejects a literal newline in a -v assignment and GNU awk
# accepts it, so without this the guard below only holds on a BSD platform.
# Resolved before the stub exists, or the stub would exec itself.
REAL_AWK=$(command -v awk)
mkdir -p "$WORK/bin"
cat > "$WORK/bin/awk" <<STUB
#!/usr/bin/env bash
nl='
'
prev=
for arg in "\$@"; do
	case \$prev:\$arg in
		-v:*"\$nl"*) echo "awk: newline in string" >&2; exit 2 ;;
	esac
	# The value can also ride on the flag as one argument. No awk program text
	# begins with -v, so this cannot match a real one.
	case \$arg in
		-v?*"\$nl"*) echo "awk: newline in string" >&2; exit 2 ;;
	esac
	prev=\$arg
done
exec "$REAL_AWK" "\$@"
STUB
chmod +x "$WORK/bin/awk"

PASS=0
FAIL=0

# write_files <dir> <pin> <entry> <unrelated> <released_extra> [guard]
# <entry>: a bullet, --wrapped for one bullet split over two lines, or one of
# --none (empty Unreleased heading), --released
# (heading renamed to a dated one with an entry), --released-empty (renamed with
# no entry), --undated (a version heading with no date), --subheading (a level-3
# heading with no bullet), --nochangelog (no CHANGELOG.md at all).
# <released_extra>: a second bullet for the released section, --redate to move
# that section's date, or --renumber to change the older section's version. The
# older section's bullet is constant, so --renumber changes a heading and
# nothing else.
write_files() {
	local dir=$1 pin=$2 entry=$3 unrelated=$4 released_extra=$5 guard=${6:-"if ( ! defined( 'CEROS_API_VERSION' ) ) {"} indent=${7:-}
	printf '<?php\n%s\n%sdefine( '"'"'CEROS_API_VERSION'"'"', '"'"'%s'"'"' );\n}\n' "$guard" "$indent" "$pin" > "$dir/ceros.php"
	printf '{\n\t"version": "0.32.0"\n}\n' > "$dir/package.json"

	# The released section carries <unrelated>, so every case has a CHANGELOG.md
	# that differs between the two trees.
	local released_date=2026-08-31 older_version=0.31.0
	case $released_extra in
		--redate)   released_date=2026-09-01; released_extra= ;;
		--renumber) older_version=0.31.1;      released_extra= ;;
	esac

	if [ "$entry" = "--nochangelog" ]; then
		rm -f "$dir/CHANGELOG.md"
		printf '%s\n' "$unrelated" > "$dir/README.md"
	else
		{
			printf '# Changelog\n'
			case $entry in
				--released)       printf '\n## [0.33.0] - 2026-09-14\n\n### Added\n- Talk to the newer Ceros API.\n' ;;
				--released-empty) printf '\n## [0.33.0] - 2026-09-14\n' ;;
				--undated)        printf '\n## [Unreleased]\n\n## [0.33.0]\n' ;;
				--subheading)     printf '\n## [Unreleased]\n\n### Fixed\n' ;;
				--none)           printf '\n## [Unreleased]\n' ;;
				--wrapped)        printf '\n## [Unreleased]\n\n### Added\n- Earlier work that someone\n  split over two lines.\n' ;;
				*)                printf '\n## [Unreleased]\n\n### Added\n- %s\n' "$entry" ;;
			esac
			printf '\n## [0.32.0] - %s\n\n### Added\n- %s\n' "$released_date" "$unrelated"
			[ -n "$released_extra" ] && printf -- '- %s\n' "$released_extra"
			printf '\n## [%s] - 2026-06-12\n\n### Added\n- A bullet that never changes.\n' "$older_version"
			return 0
		} > "$dir/CHANGELOG.md"
	fi
}

# case_ <name> <base_pin> <base_entry> <head_pin> <head_entry> <want_exit> <want_text> [head_guard] [head_released_extra] [uncommitted] [head_indent]
case_() {
	local name=$1 base_pin=$2 base_entry=$3 head_pin=$4 head_entry=$5 want_exit=$6 want_text=$7
	local dir="$WORK/case"
	rm -rf "$dir"; mkdir -p "$dir"

	git -C "$dir" init -q -b main
	git -C "$dir" config user.email test@example.com
	git -C "$dir" config user.name Test
	write_files "$dir" "$base_pin" "$base_entry" base ""
	git -C "$dir" add -A
	git -C "$dir" commit -qm base

	git -C "$dir" switch -qc feature
	write_files "$dir" "$head_pin" "$head_entry" changed "${9:-}" "${8:-}" "${11:-}"
	git -C "$dir" add -A
	git -C "$dir" commit -qm change

	# Left in the checkout, never committed, under the Unreleased heading.
	if [ -n "${10:-}" ]; then
		awk -v line="- ${10}" '{ print } /^## \[Unreleased\]$/ { print ""; print line }' \
			"$dir/CHANGELOG.md" > "$dir/CHANGELOG.next"
		mv "$dir/CHANGELOG.next" "$dir/CHANGELOG.md"
	fi

	local out got
	out=$( cd "$dir" && PATH="$WORK/bin:$PATH" bash "$SCRIPT_ABS" main 2>&1 )
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

case_ 'pin moved with nothing added under Unreleased' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --none 1 'no changelog entry for it'

case_ 'pin moved and an entry was added' \
	2025-12-10-09-11 --none 2026-08-06-09-00 'Talk to the newer Ceros API.' 0 'has a changelog entry'

case_ 'pin moved and only a released section grew' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --none 1 'no changelog entry for it' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) {" 'A later note.'

case_ 'pin moved and an entry was rewritten' \
	2025-12-10-09-11 'Earlier work.' 2026-08-06-09-00 'Talk to the newer Ceros API.' 0 'has a changelog entry'

case_ 'pin moved and a second entry was added' \
	2025-12-10-09-11 'Earlier work.' 2026-08-06-09-00 "$( printf 'Earlier work.\n- Talk to the newer Ceros API.' )" 0 'has a changelog entry'

case_ 'pin moved in a release pull request' \
	2025-12-10-09-11 'Earlier work.' 2026-08-06-09-00 --released 0 'has a changelog entry'

case_ 'pin moved and a release section was left empty' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --released-empty 1 'no changelog entry for it'

case_ 'pin moved and an undated heading was added' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --undated 1 'no changelog entry for it'

case_ 'pin moved and only a subheading was added' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --subheading 1 'no changelog entry for it'

case_ 'pin did not move and there is no changelog' \
	2026-08-06-09-00 --nochangelog 2026-08-06-09-00 --nochangelog 0 'no API pin change in this diff'

case_ 'pin moved and an old release date was corrected' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --none 1 'no changelog entry for it' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) {" --redate

case_ 'pin moved and an old release was renumbered' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --none 1 'no changelog entry for it' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) {" --renumber

# Two new version headings at once: the release heading plus an older one
# renumbered. The list of them reaches awk as a multi-line value, which a -v
# assignment rejects, leaving the bullets empty so a documented pin reads as
# undocumented.
case_ 'pin moved with two new version headings' \
	2025-12-10-09-11 'Earlier work.' 2026-08-06-09-00 --released 0 'has a changelog entry' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) {" --renumber

case_ 'pin moved, entries pruned, record added' \
	2025-12-10-09-11 "$( printf 'Stale one.\n- Stale two.\n- Stale three.' )" \
	2026-08-06-09-00 'Talk to the newer Ceros API.' 0 'has a changelog entry'

case_ 'pin moved and an entry was only reindented' \
	2025-12-10-09-11 'Earlier work.' 2026-08-06-09-00 '  Earlier work.  ' 1 'no changelog entry for it'

case_ 'pin moved and an entry was only wrapped' \
	2025-12-10-09-11 'Earlier work that someone split over two lines.' 2026-08-06-09-00 --wrapped 1 'no changelog entry for it'

case_ 'pin moved and there is no changelog' \
	2025-12-10-09-11 --nochangelog 2026-08-06-09-00 --nochangelog 1 'no CHANGELOG.md at HEAD'

case_ 'pin moved and an entry was removed' \
	2025-12-10-09-11 'Talk to the newer Ceros API.' 2026-08-06-09-00 --none 1 'no changelog entry for it'

case_ 'pin moved with the entry left uncommitted' \
	2025-12-10-09-11 --none 2026-08-06-09-00 --none 1 'no changelog entry for it' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) {" '' 'Talk to the newer Ceros API.'

case_ 'pin did not move' \
	2026-08-06-09-00 --none 2026-08-06-09-00 --none 0 'no API pin change in this diff'

# The define itself reindented, value untouched. Counting diff lines reads that
# as a pin change and demands a changelog entry for a pin that did not move.
case_ 'the define line is reindented' \
	2026-08-06-09-00 --none 2026-08-06-09-00 --none 0 'no API pin change in this diff' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) {" '' '' '	'

case_ 'the defined() guard line is reformatted' \
	2026-08-06-09-00 --none 2026-08-06-09-00 --none 0 'no API pin change in this diff' \
	"if ( ! defined( 'CEROS_API_VERSION' ) ) { // reformatted"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
