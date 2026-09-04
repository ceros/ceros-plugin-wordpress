#!/usr/bin/env bash
#
# Exit non-zero when a diff moves CEROS_API_VERSION with no changelog entry
# added for it.
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

changelog_at() {
	git show "$1:CHANGELOG.md" 2>/dev/null
}

# Matches the define rather than the constant name, which the defined() guard
# line above it also carries.
pin_changed=$(git diff "$merge_base"..HEAD -- ceros.php | grep -cE "^[+-].*define\( 'CEROS_API_VERSION'," || true)

if [ "$pin_changed" -eq 0 ]; then
	echo "check-pin-bump: no API pin change in this diff."
	exit 0
fi

changelog_at HEAD > /dev/null || { echo "check-pin-bump: no CHANGELOG.md at HEAD." >&2; exit 1; }

versions_at() {
	changelog_at "$1" | sed -n 's/^## \[\([0-9][^]]*\)\].*/\1/p'
}

# One bullet per line, wrapped continuations joined onto theirs, whitespace
# flattened. Any heading ends the bullet it follows.
bullets() {
	awk '
		/^[[:space:]]*[-*][[:space:]]/ { if (entry != "") print entry; entry = $0; next }
		/^[[:space:]]*#/ { if (entry != "") print entry; entry = ""; next }
		/[^[:space:]]/ { if (entry != "") entry = entry " " $0; next }
		END { if (entry != "") print entry }
	' | sed 's/[[:space:]]\{1,\}/ /g; s/^ //; s/ $//'
}

# Bullets under the Unreleased heading, and under any version heading named in
# $2, which is where a release pull request moves them.
pending_bullets_at() {
	# Through the environment, not -v: the value is a newline-separated list, and
	# a literal newline in a -v assignment is rejected by BSD awk.
	changelog_at "$1" | FRESH="$2" awk '
		BEGIN { split(ENVIRON["FRESH"], f, "\n"); for (i in f) if (f[i] != "") moved[f[i]] = 1 }
		/^## \[/ {
			heading = $0
			sub(/^## \[/, "", heading)
			sub(/\].*/, "", heading)
			inside = (heading == "Unreleased" || heading in moved)
			next
		}
		inside
	' | bullets
}

new_versions=$(versions_at HEAD | grep -vxF -f <(versions_at "$merge_base") || true)

# Every bullet the base already had, wherever it sat. Renumbering a heading makes
# it new, and its bullets must not read as arrivals because of that.
before=$(changelog_at "$merge_base" | bullets)
after=$(pending_bullets_at HEAD "$new_versions")

added=$(printf '%s\n' "$after" | grep -vxF -f <(printf '%s\n' "$before") || true)

if [ -z "$added" ]; then
	echo "check-pin-bump: CEROS_API_VERSION moved with no changelog entry for it." >&2
	echo >&2
	echo "The pin decides which server contract an installed copy talks to, so the" >&2
	echo "release that carries it has to say so. Add the entry under" >&2
	echo "'## [Unreleased]'; the release renames that heading and dates it." >&2
	exit 1
fi

echo "check-pin-bump: the API pin change has a changelog entry."
