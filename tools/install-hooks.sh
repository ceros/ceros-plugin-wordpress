#!/usr/bin/env sh
#
# Point git at the repo's tracked hooks directory.
#
# Runs automatically from Composer's post-install-cmd / post-update-cmd, and
# can be re-run on demand with `composer install-hooks`.
#
# `core.hooksPath` is local git config, so it cannot be committed — every clone
# has to set it once. Doing it here means a developer gets the hooks by running
# `composer install`, which they already have to do.
#
# Never fails the Composer run: no git on PATH, a tarball export with no .git,
# or a developer who has deliberately set their own hooksPath are all reported
# and skipped. Breaking `composer install` over a local convenience gate would
# be worse than not installing the hooks.

set -u

HOOKS_DIR=.githooks

say() {
	echo "install-hooks: $1"
}

if ! command -v git >/dev/null 2>&1; then
	say "git not found — skipping hook setup."
	exit 0
fi

# An exported tarball legitimately has no .git.
if [ "$(git rev-parse --is-inside-work-tree 2>/dev/null || echo false)" != "true" ]; then
	say "not a git checkout — skipping hook setup."
	exit 0
fi

if [ ! -d "$HOOKS_DIR" ]; then
	say "$HOOKS_DIR/ is missing — skipping hook setup."
	exit 0
fi

current=$(git config --local --get core.hooksPath 2>/dev/null || true)

# Respect a hooksPath the developer set themselves (husky, a personal setup).
# Clobbering another tool's config silently would be worse than doing nothing.
if [ -n "$current" ] && [ "$current" != "$HOOKS_DIR" ]; then
	say "core.hooksPath is already set to \"$current\" — leaving it alone."
	say "To use this repo's hooks: git config core.hooksPath $HOOKS_DIR"
	exit 0
fi

# Restore the executable bit. Git records it, but a zip export or a
# permission-less filesystem can lose it, and git skips non-executable hooks.
for hook in "$HOOKS_DIR"/*; do
	if [ -f "$hook" ] && [ ! -x "$hook" ]; then
		chmod 755 "$hook"
	fi
done

if [ "$current" = "$HOOKS_DIR" ]; then
	exit 0
fi

if ! git config core.hooksPath "$HOOKS_DIR" 2>/dev/null; then
	say "could not set core.hooksPath — skipping hook setup."
	exit 0
fi

say "git hooks enabled (core.hooksPath -> $HOOKS_DIR)."
exit 0
