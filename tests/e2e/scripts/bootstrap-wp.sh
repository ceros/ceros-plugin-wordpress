#!/usr/bin/env bash
#
# Provision a local wp-env instance for the suite: persist the connection
# details and point the plugin at a Ceros environment.
#
# Run once after `npm run env:start`. The suite itself provisions nothing, so
# this is the only piece that knows wp-env exists. Against any other WordPress,
# fill in .env yourself and skip this.

set -euo pipefail

SUITE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$SUITE_DIR/../.." && pwd)"

# shellcheck source=scripts/env-file.sh
. "$SUITE_DIR/scripts/env-file.sh"

cd "$REPO_ROOT"

env_ensure

# A real environment variable wins over .env, matching how the suite itself
# resolves these, so CI can point this at its own instance without a file.
BASE_URL="${BASE_URL:-$(env_read BASE_URL)}"
BASE_URL="${BASE_URL:-http://localhost:8894}"
WP_USER="${E2E_WP_USER:-$(env_read E2E_WP_USER)}"
WP_USER="${WP_USER:-admin}"

# Persist them so the suite and this script cannot disagree later.
env_write BASE_URL "$BASE_URL"
env_write E2E_WP_USER "$WP_USER"

if ! curl -fsS --max-time 5 "$BASE_URL/wp-login.php" >/dev/null 2>&1; then
	echo "WordPress is not reachable at $BASE_URL." >&2
	echo "Start it with 'npm run env:start', or change BASE_URL in $ENV_FILE." >&2
	exit 1
fi

# Point the plugin at a Ceros environment. The browse-picker specs need a real
# key and the account's API; the paste-URL and not-found specs need neither.
#
# The API host is derived from E2E_CEROS_ENV (the same env the experience URLs
# use), defaulting to `latest`. The key has no default — it is a secret, kept in
# .env locally and a CI secret in the pipeline; a different env needs its key.
#
# Production is hard-wired to rest.ceros.com, so a dev-env host goes through the
# plugin's "staging" mode. The key is a wp-config constant.
CEROS_ENV="${E2E_CEROS_ENV:-$(env_read E2E_CEROS_ENV)}"
CEROS_ENV="${CEROS_ENV:-latest}"
CEROS_API_BASE_URL="https://api-${CEROS_ENV}.dev.flex.cerosdev.com"
CEROS_KEY="${E2E_CEROS_API_KEY:-$(env_read E2E_CEROS_API_KEY)}"

if [ -n "$CEROS_KEY" ]; then
	npx wp-env run cli wp option update ceros_api_environment staging >/dev/null 2>&1
	npx wp-env run cli wp option update ceros_staging_api_url "$CEROS_API_BASE_URL" >/dev/null 2>&1
	# Redirected so the key never reaches the log: wp-env echoes the command back.
	npx wp-env run cli wp config set CEROS_API_KEY "$CEROS_KEY" --type=constant >/dev/null 2>&1
	echo "configured the plugin for $CEROS_API_BASE_URL (key set; all specs can run)"
else
	# No key: clear any stale one so the block shows the paste panel rather than an
	# API-key error. The paste-URL and not-found specs still run; browse-picker does not.
	npx wp-env run cli wp config delete CEROS_API_KEY >/dev/null 2>&1 || true
	echo "no E2E_CEROS_API_KEY set; cleared the key. paste-URL and not-found specs run; browse-picker needs a key."
fi
