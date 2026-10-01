# End-to-end tests

Playwright tests for the Ceros block in the WordPress editor and on the published page,
run against a local `wp-env` instance.

**Not wired into CI or the pre-push hook, and not a required check.** These need WordPress,
Docker and network access, which the pre-push hook explicitly rules out. See [CI](#ci) below.

## Layout

```
constants/   timeout tiers and block vocabulary
fixtures/    the fixture chain and the barrel every spec imports
pages/       page objects: locators and readiness waits, no assertions
actions/     multi-step workflows composed from page objects
utils/       REST client, block serializer, environment helpers
tests/       specs
```

## Prerequisites

```bash
npm ci && npm run build        # repository root; the plugin fatals without build/
npm run env:start              # repository root
npm install                    # here (tests/e2e)
npx playwright install chrome  # here; the suite runs on installed Google Chrome
bash scripts/bootstrap-wp.sh   # here; creates .env and configures the plugin
```

The specs call the real Ceros API. `bootstrap-wp.sh` points the plugin at the Ceros
environment named by `E2E_CEROS_ENV` (default `latest`) — both the experience URLs and the
API host derive from it — and, when `E2E_CEROS_API_KEY` is set, configures that key. The
paste-URL and not-found specs run without a key, so the steps above are enough for them.
Whatever runs the suite must be able to reach that environment's hosts.

The **browse-picker** specs need a real API key. Add your environment's key to `.env`
(`E2E_CEROS_API_KEY=…`) and re-run `bootstrap-wp.sh` — it sets the key on the plugin. The
key is a secret, so `.env` is gitignored and it has no default.

The suite provisions nothing else: it reads a URL and credentials from the environment,
so the block specs run against local `wp-env`, another WordPress, or one built by CI.
`bootstrap-wp.sh` and the settings fixture are the only pieces that know `wp-env` exists;
against anything else, fill in `.env` yourself and skip bootstrap.

### Settings specs: a disposable instance only

`settings.spec` saves, rejects, and removes API keys and switches the API environment.
Those settings are site-wide, so the specs must run against a WordPress set up for the
run (local `wp-env`, or the one CI builds), never a shared or live site:

- **They change the site for everyone on it.** Anyone using that site while the specs run
  loses a working key or finds the plugin pointed at another environment.
- **They set up and restore state through `wp-env`.** The `plugin-settings` fixture reads
  the plugin's settings before each test, applies the key state the test needs, and puts
  everything back afterwards, even when the test fails. It does this with WP-CLI in the
  local `wp-env` instance (`utils/wp-cli.ts`). Pointed at a remote site while a local
  `wp-env` is running, the fixture would reset the local instance while the test changes
  the remote one.
- **One worker per instance.** Two workers on one instance would change settings under
  each other's tests. Scale out with shards that each run their own `wp-env`.
- **They need the bootstrapped key.** The fixture takes the key from the `CEROS_API_KEY`
  constant `bootstrap-wp.sh` sets, so run bootstrap with `E2E_CEROS_API_KEY` first.

Running against a remote site already takes a deliberate `BASE_URL` plus that site's
credentials. Exclude these specs there with `--grep-invert @settings`.

Tracing and video are off for `settings.spec`, because the real key is entered on the page.

The Editor-role test changes no settings: it creates its own Editor user over REST, checks
the settings surfaces refuse it, and deletes the user afterwards.

## Running

```bash
npm test                                   # everything (browse-picker needs the API key; see Prerequisites)
npx playwright test --project=wordpress    # the test project; global setup logs in first
npx playwright test --grep @rendered       # filter by tag
npm run type-check
npm run lint
```

## Environment variables

`.env.example` is the template. Copy it to `.env` (gitignored) and the defaults target a
local `wp-env`. Real environment variables take precedence over the file, so CI sets its
own values without `.env` interfering.

Login is once-per-run: global setup signs in with `E2E_WP_USER` / `E2E_WP_PASSWORD` and
persists the session (cookies + REST nonce), which authenticates both the browser and the
REST calls, so no separate REST credential has to be minted or stored.

| Variable                          | Default                     | Purpose                                                                                |
| --------------------------------- | --------------------------- | -------------------------------------------------------------------------------------- |
| `BASE_URL`                        | `http://localhost:8894`     | The WordPress instance. `http`, not `https` — wp-env serves plain HTTP.                |
| `E2E_WP_USER` / `E2E_WP_PASSWORD` | `admin` / `password`        | Admin login (browser + REST session). wp-env's documented defaults.                    |
| `E2E_CEROS_ENV`                   | `latest`                    | The Ceros dev environment; the experience URLs and API host derive from it.            |
| `E2E_CEROS_API_KEY`               | —                           | Bearer key for that env (a secret). Required for the browse-picker and settings specs. |
| `E2E_TIMEOUT_*`                   | see `constants/timeouts.ts` | Override any timeout tier, in milliseconds.                                            |

## Conventions

- Specs import `test` and `expect` from `@fixtures/fixtures`, never from `@playwright/test`.
- Page objects declare `readonly` locators in the constructor and hold no assertions —
  only readiness waits. Behaviour is asserted in specs.
- Multi-step workflows live in `actions/`, wrapped in `test.step()`.
- Test data is created per test by fixtures and removed in teardown. Nothing is seeded
  out of band, and no post ID is passed in through the environment.
- No numeric timeout literals and no `waitForTimeout`. Use `TIMEOUTS` from `@constants/timeouts`.
- Tag every `test.describe` from `@utils/test-tags`.
- Prefer `getByRole` and `getByLabel`; fall back to a class selector only where the markup
  offers nothing better, and say why.

Two deliberate differences from the other Playwright suites here: credentials come from
`.env.example` rather than a credentials file you have to obtain, because wp-env's
defaults are public and `bootstrap-wp.sh` mints the one that is not; and page objects do
not extend a shared base class, because at this size it would hold nothing.

## CI

The suite is not wired into CI and is not a required check — it spans two products (the
plugin and Ceros), and that ownership is unresolved. Running it anywhere, CI or another
machine, needs the same inputs as a local run: `E2E_CEROS_ENV`, the secret `E2E_CEROS_API_KEY`,
network access to that environment, and a pinned WordPress version (`.wp-env.json` leaves core
unset, which has resolved to a version with no tag in the mirror).
