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

# The plugin's configured environment decides which error text reaches the
# editor, and the error specs assert on that difference. Read it rather than
# have anyone keep a second copy in step by hand.
PLUGIN_ENV="$(npx wp-env run cli wp option get ceros_api_environment 2>/dev/null | tr -d '\r\n' | grep -oE '^(production|staging)' || true)"
if [ -n "$PLUGIN_ENV" ]; then
	env_write E2E_PLUGIN_ENV "$PLUGIN_ENV"
	echo "wrote E2E_PLUGIN_ENV=$PLUGIN_ENV to .env (read from WordPress)"
else
	echo "could not read ceros_api_environment; leaving E2E_PLUGIN_ENV as it is" >&2
fi

# The live specs need a Ceros API key. It is a wp-config constant, not a test
# variable, so report it rather than setting it: the key must never pass through
# this script or reach a tracked file.
# The marker is split in the PHP so the joined form exists only in the output;
# wp-env echoes the source back, and a literal would match there too.
KEY_STATE="$(npx wp-env run cli wp eval 'echo "KEY" . "=" . ( defined("CEROS_API_KEY") && CEROS_API_KEY ? "yes" : "no" );' 2>&1 | tr -d '\r\n')"
KEY_SET="$(printf '%s' "$KEY_STATE" | grep -oE 'KEY=(yes|no)' | head -1 | cut -d= -f2)"

if [ "$KEY_SET" = yes ]; then
	echo "CEROS_API_KEY is configured; the live specs can run."
else
	echo "CEROS_API_KEY is not configured. The driver-free specs still run."
	echo "For the live half, add it to .wp-env.override.json (gitignored) and restart wp-env."
fi
