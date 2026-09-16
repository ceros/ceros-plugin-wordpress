#!/usr/bin/env bash
#
# Provision a local wp-env instance for the suite: mint an application password
# and write it to .env.
#
# Run once after `npm run env:start`. The suite itself provisions nothing, so
# this is the only piece that knows wp-env exists. Against any other WordPress,
# fill in .env yourself and skip this.

set -euo pipefail

SUITE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$SUITE_DIR/../.." && pwd)"
APP_PASSWORD_LABEL="ceros-e2e"

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

# The username is interpolated into PHP below: wp-env does not forward host
# environment variables into the container, so getenv() is not an option.
if ! printf '%s' "$WP_USER" | grep -qE '^[A-Za-z0-9._@-]+$'; then
	echo "E2E_WP_USER is not a plain username: $WP_USER" >&2
	exit 1
fi

if ! curl -fsS --max-time 5 "$BASE_URL/wp-login.php" >/dev/null 2>&1; then
	echo "WordPress is not reachable at $BASE_URL." >&2
	echo "Start it with 'npm run env:start', or change BASE_URL in $ENV_FILE." >&2
	exit 1
fi

# Replace any previous password with this label so repeated runs do not pile up.
PHP=$(
	cat <<PHP_EOF
\$user = get_user_by( 'login', '$WP_USER' );
if ( ! \$user ) { WP_CLI::error( 'no such user: $WP_USER' ); }
foreach ( WP_Application_Passwords::get_user_application_passwords( \$user->ID ) as \$existing ) {
	if ( \$existing['name'] === '$APP_PASSWORD_LABEL' ) {
		WP_Application_Passwords::delete_application_password( \$user->ID, \$existing['uuid'] );
	}
}
\$created = WP_Application_Passwords::create_new_application_password( \$user->ID, array( 'name' => '$APP_PASSWORD_LABEL' ) );
echo "\\n" . 'CEROS' . 'PW=' . \$created[0] . "\\n";
PHP_EOF
)

# wp-env echoes the eval'd source back alongside the output, so any marker
# written literally in the source also appears in it. Splitting the marker in
# PHP means the joined form exists only in the output.
if ! OUTPUT="$(npx wp-env run cli wp eval "$PHP" 2>&1)"; then
	echo "Could not mint an application password. wp-env said:" >&2
	printf '%s\n' "$OUTPUT" >&2
	exit 1
fi

APP_PASSWORD="$(printf '%s' "$OUTPUT" | grep -oE 'CEROSPW=[A-Za-z0-9]+' | head -1 | cut -d= -f2)"

if [ -z "$APP_PASSWORD" ]; then
	echo "Minted no application password. wp-env said:" >&2
	printf '%s\n' "$OUTPUT" >&2
	echo "Set E2E_WP_APP_PASSWORD in $ENV_FILE yourself to carry on." >&2
	exit 1
fi

env_write E2E_WP_APP_PASSWORD "$APP_PASSWORD"
echo "wrote E2E_WP_APP_PASSWORD to .env (label: $APP_PASSWORD_LABEL)"

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
