#!/usr/bin/env bash
#
# Tests for tools/check-tested-upto.sh.
#
# Every case names the guard it covers. A case that stops failing after an edit
# names the guard that stopped working, which is the only reason these are worth
# keeping: the script's failure mode is a silent pass, so a green run has to
# mean something.
#
# The version API is replaced by a stub on PATH, so these assert this script's
# parsing and comparison rather than the API's behaviour. No network, no
# WordPress, runs in about a second.
#
# Run from the repository root: bash tests/shell/check-tested-upto.test.sh

set -uo pipefail

SCRIPT=tools/check-tested-upto.sh
[ -f "$SCRIPT" ] || { echo "run this from the repository root" >&2; exit 1; }
SCRIPT_ABS=$PWD/$SCRIPT

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

# Stub curl. Replays $STUB_BODY, or exits $STUB_RC to stand in for a transport
# failure the way --fail would.
mkdir -p "$WORK/bin"
cat > "$WORK/bin/curl" <<'STUB'
#!/usr/bin/env bash
# Honours --fail, so removing that flag from the script under test is visible
# here rather than silently passing an error page on to the parser.
fail_flag=0
for arg in "$@"; do
	[ "$arg" = "--fail" ] && fail_flag=1
done
if [ "${STUB_RC:-0}" != "0" ]; then
	echo "curl: (${STUB_RC}) stub failure" >&2
	exit "${STUB_RC}"
fi
if [ "${STUB_STATUS:-200}" -ge 400 ] && [ "$fail_flag" = 1 ]; then
	echo "curl: (22) The requested URL returned error: ${STUB_STATUS}" >&2
	exit 22
fi
printf '%s' "${STUB_BODY:-}"
STUB
chmod +x "$WORK/bin/curl"

CURRENT_ONLY='{"offers":[{"current":"7.1"}]}'
PASS=0
FAIL=0

# case <name> <readme-value|--no-header|--no-file> <api-body> <rc> <want-exit> <want-text> [http-status]
case_() {
	local name=$1 header=$2 body=$3 rc=$4 want_exit=$5 want_text=$6 status=${7:-200}
	local dir="$WORK/case"
	rm -rf "$dir"; mkdir -p "$dir"

	case $header in
		--no-file)   : ;;
		--no-header) printf '=== Ceros ===\nStable tag: 0.31.0\n' > "$dir/readme.txt" ;;
		*)           printf '=== Ceros ===\nTested up to:      %s\nStable tag: 0.31.0\n' "$header" > "$dir/readme.txt" ;;
	esac

	local out got
	out=$( cd "$dir" && PATH="$WORK/bin:$PATH" STUB_BODY="$body" STUB_RC="$rc" \
		STUB_STATUS="$status" bash "$SCRIPT_ABS" 2>&1 )
	got=$?

	if [ "$got" = "$want_exit" ] && [[ $out == *"$want_text"* ]]; then
		PASS=$((PASS + 1))
		printf '  ok   %s\n' "$name"
	else
		FAIL=$((FAIL + 1))
		printf '  FAIL %s\n       wanted exit %s containing "%s"\n       got    exit %s: %s\n' \
			"$name" "$want_exit" "$want_text" "$got" "$(printf '%s' "$out" | head -1)"
	fi
}

echo "check-tested-upto"

# Reports the comparison it was asked to make.
case_ 'behind the current release'    6.9   "$CURRENT_ONLY" 0 1 'but WordPress 7.1 is current'
case_ 'matching the current release'  7.1   "$CURRENT_ONLY" 0 0 'matches the current WordPress release'
case_ 'ahead of the current release'  7.2   "$CURRENT_ONLY" 0 0 'is ahead of the current WordPress release'
case_ 'patch version reduced to major' 7.1.2 "$CURRENT_ONLY" 0 0 'matches the current WordPress release'
case_ 'release candidate reduced'     7.1-RC1 "$CURRENT_ONLY" 0 0 'matches the current WordPress release'

# Drops if the readme-exists check is removed.
case_ 'run outside the plugin root'   --no-file "$CURRENT_ONLY" 0 1 'not found'

# Drops if the header-present check is removed: without it the value falls
# through to the version guard and reports the wrong reason.
case_ 'header missing entirely'       --no-header "$CURRENT_ONLY" 0 1 'no "Tested up to" header found'

# Drops if the version guard on the readme value is removed. A non-numeric
# value would otherwise sort as newer than a real version and pass as "ahead".
case_ 'header is not a version'       trunk "$CURRENT_ONLY" 0 1 'is not a version number'
case_ 'header uses a comma'           6,9   "$CURRENT_ONLY" 0 1 'is not a version number'

# Drops if the prerelease filter is removed: the beta would become the current
# release and a correct readme would be reported as behind.
case_ 'prerelease among the offers'   7.1 '{"offers":[{"current":"7.1"},{"current":"7.2-beta1"}]}' 0 0 'matches the current WordPress release'

# Drops if the version guard on API values is removed, for the same reason.
case_ 'non-version among the offers'  7.1 '{"offers":[{"current":"7.1"},{"current":"trunk"}]}' 0 0 'matches the current WordPress release'

# Drops if the highest offer stops being preferred over the first. The offers
# carry one entry per release branch and the order is not guaranteed.
case_ 'maintenance branch listed first' 6.9 '{"offers":[{"current":"6.9"},{"current":"7.1"}]}' 0 1 'but WordPress 7.1 is current'

# Fails closed. Each of these would otherwise leave the version empty and
# compare against nothing.
case_ 'api returns an empty body'     6.9 ''                    0 1 'no usable version'
case_ 'api returns html'              6.9 '<html>503</html>'    0 1 'no usable version'
case_ 'api returns no offers'         6.9 '{"offers":[]}'       0 1 'no usable version'
case_ 'api returns only a prerelease' 6.9 '{"offers":[{"current":"7.2-beta1"}]}' 0 1 'no usable version'
case_ 'api is unreachable'            6.9 ''                    7 1 'could not reach'

# Drop if the version sort stops being a version sort. Lexically "9.9" beats
# "10.0" and "6.9" beats "6.10", so a plain sort picks the wrong release the
# first time WordPress crosses one of those boundaries.
case_ 'offers cross a version boundary' 9.9 '{"offers":[{"current":"9.9"},{"current":"10.0"}]}' 0 1 'but WordPress 10.0 is current'
case_ 'ahead across a version boundary' 10.0 '{"offers":[{"current":"9.9"}]}' 0 0 'is ahead of the current WordPress release'
case_ 'two digit minor is newer'        6.9 '{"offers":[{"current":"6.10"}]}' 0 1 'but WordPress 6.10 is current'

# Drops if --fail is removed from the request. An error response whose body
# happens to parse would otherwise be treated as the current release, and this
# readme would pass while being stale.
case_ 'error status with a usable body' 6.9 '{"offers":[{"current":"6.9"}]}' 0 1 'could not reach' 500

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
