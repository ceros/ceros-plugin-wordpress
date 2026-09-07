#!/usr/bin/env bash
#
# Read and write .env, for the scripts that own state the suite reads.
#
# A variable that mirrors something live — which mu-plugin is installed, how the
# plugin is configured — is written here by whatever changes it. Otherwise the
# two drift and a spec fails on a missing locator rather than on anything real.

ENV_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/.env"
ENV_EXAMPLE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/.env.example"

env_ensure() {
	if [ ! -f "$ENV_FILE" ]; then
		cp "$ENV_EXAMPLE" "$ENV_FILE"
		chmod 600 "$ENV_FILE"
		echo "created .env from .env.example"
	fi
}

env_read() {
	[ -f "$ENV_FILE" ] || return 0
	sed -n "s/^$1=\(.*\)$/\1/p" "$ENV_FILE" | tail -1
}

# Replace in place rather than appending, so repeated runs leave one entry.
env_write() {
	env_ensure
	local tmp
	tmp="$(mktemp)"
	grep -v "^$1=" "$ENV_FILE" >"$tmp" || true
	printf '%s=%s\n' "$1" "$2" >>"$tmp"
	mv "$tmp" "$ENV_FILE"
	chmod 600 "$ENV_FILE"
}
