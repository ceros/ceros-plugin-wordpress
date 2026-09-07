#!/usr/bin/env bash
#
# Switch how the plugin reaches the Ceros API inside the wp-env container.
#
#   reject  install the stub so every Ceros call returns 403 "Forbidden resource"
#   live    remove the stub and talk to the real API
#
# A spec trusts E2E_API_MODE rather than inspecting the container, so this
# writes the variable to .env as it changes the container. The two cannot drift.

set -euo pipefail

MODE="${1:?usage: rig/mode.sh reject|live}"
RIG_DIR="$(cd "$(dirname "$0")" && pwd)"

# shellcheck source=scripts/env-file.sh
. "$RIG_DIR/../scripts/env-file.sh"
MU_PLUGINS=/var/www/html/wp-content/mu-plugins

CONTAINER=$(docker ps --format '{{.Names}}' | grep -E '^[a-f0-9]+-wordpress-1$' | head -1)
if [ -z "$CONTAINER" ]; then
	echo "No wp-env container is running. Start one with 'npm run env:start'." >&2
	exit 1
fi

case "$MODE" in
	reject)
		# A fresh container has no mu-plugins directory, and docker cp will not create it.
		docker exec "$CONTAINER" mkdir -p "$MU_PLUGINS"
		docker cp "$RIG_DIR/api-stub.php" "$CONTAINER:$MU_PLUGINS/ceros-api-stub.php" >/dev/null
		;;
	live)
		docker exec "$CONTAINER" rm -f "$MU_PLUGINS/ceros-api-stub.php"
		;;
	*)
		echo "Unknown mode: $MODE (expected reject or live)" >&2
		exit 2
		;;
esac

env_write E2E_API_MODE "$MODE"
echo "rig mode: $MODE (wrote E2E_API_MODE to .env)"

# Report what is actually loaded. A stub left behind under another name keeps
# returning 403 while the mode says live, and the specs then fail on a missing
# locator rather than on anything real.
remaining=$(docker exec "$CONTAINER" ls -1 "$MU_PLUGINS" 2>/dev/null || true)
if [ -z "$remaining" ]; then
	echo "mu-plugins: (none)"
else
	echo "$remaining" | sed 's/^/mu-plugins: /'
fi
