# End-to-end tests

Playwright tests for the Ceros block in the WordPress editor and on the published page,
run against a local `wp-env` instance.

**Runs in CI on pull requests that are ready for review, but is not a required check or part of
the pre-push hook.** These need WordPress, Docker and network access, which the pre-push hook
explicitly rules out. See [CI](#ci) below.

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
`bootstrap-wp.sh`, the settings fixture, the HTTP-stub fixture and the flex-store fixture are
the only pieces that know `wp-env` exists; against anything else, fill in `.env` yourself and skip bootstrap.

## Running

```bash
npm test                                   # everything (browse-picker needs the API key; see Prerequisites)
npx playwright test --project=parallel     # the tests that run in parallel (the serial ones still follow)
npx playwright test --project=serial       # only the tests that run one at a time
npx playwright test --grep @rendered       # filter by tag
npm run type-check
npm run lint
```

Filters such as `--grep`, file names and `--last-failed` only apply to the `parallel`
project: every serial test still runs after it. Add `--project=parallel --no-deps` to run
only the matching parallel tests. Never pass `--no-deps` without `--project=parallel`: the
serial tests would then run at the same time as the parallel ones.

Against a site other than local `wp-env`, exclude the specs that need one:
`npx playwright test --project=parallel --no-deps --grep-invert "@settings|@stubbedHttp|@storesFiles"`. See
[Where the specs can run](#where-the-specs-can-run).

## Environment variables

`.env.example` is the template. Copy it to `.env` (gitignored) and the defaults target a
local `wp-env`. Real environment variables take precedence over the file, so CI sets its
own values without `.env` interfering.

Login is once-per-run: global setup signs in with `E2E_WP_USER` / `E2E_WP_PASSWORD` and
persists the session (cookies + REST nonce), which authenticates the REST calls and the
administrator specs, so no separate REST credential has to be minted or stored. Global setup also
creates (or resets the password and role of) an `e2e-author` user with the Author role on the
target site, so run only one suite at a time per WordPress site: the reset ends other runs' sessions.

| Variable                          | Default                     | Purpose                                                                                |
| --------------------------------- | --------------------------- | -------------------------------------------------------------------------------------- |
| `BASE_URL`                        | `http://localhost:8894`     | The WordPress instance. `http`, not `https` — wp-env serves plain HTTP.                |
| `E2E_WP_USER` / `E2E_WP_PASSWORD` | `admin` / `password`        | Admin login (REST session, administrator specs). wp-env's documented defaults.         |
| `E2E_CEROS_ENV`                   | `latest`                    | The Ceros dev environment; the experience URLs and API host derive from it.            |
| `E2E_CEROS_API_KEY`               | —                           | Bearer key for that env (a secret). Required for the browse-picker and settings specs. |
| `E2E_TIMEOUT_*`                   | see `constants/timeouts.ts` | Override any timeout tier, in milliseconds.                                            |

## Where the specs can run

Most specs run against any WordPress with the plugin. These tags need more:

| Tag            | Needs                       | Why                                                    |
| -------------- | --------------------------- | ------------------------------------------------------ |
| `@settings`    | a disposable local `wp-env` | changes the site-wide API key, restored through WP-CLI |
| `@stubbedHttp` | local `wp-env`              | answers outbound requests through a test-only plugin   |
| `@storesFiles` | local `wp-env`              | writes to uploads, deleted afterwards through WP-CLI   |

### Settings specs

The `@settings` specs change the plugin's API key: `settings.spec` saves, rejects, and
removes keys and switches the API environment, and `invalid-key.spec` saves a key the API
rejects.
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
- **They run alone.** Two tests changing settings at once would change them under each
  other. Tag any test that changes site-wide state with `@runsAlone`: the `serial` project
  runs those one at a time, after the `parallel` project has finished.
- **They need the bootstrapped key.** The fixture takes the key from the `CEROS_API_KEY`
  constant `bootstrap-wp.sh` sets, so run bootstrap with `E2E_CEROS_API_KEY` first.

Tracing and video are off for `settings.spec`, because the real key is entered on the page.

The Editor-role test changes no settings: it creates its own Editor user over REST, checks
the settings surfaces refuse it, and deletes the user afterwards.

### Stubbed responses

The `@stubbedHttp` specs fix what WordPress's outbound requests get back, to reach errors
a real Ceros URL never produces. `.wp-env.json` loads a test-only plugin
(`plugins/ceros-e2e-http-stub`) that answers a request when an option named
`ceros_e2e_http_stub_<md5 of the URL>` exists, and does nothing otherwise. The `http-stub`
fixture writes those options with WP-CLI and deletes them after each test, even when the
test fails. Each test stubs URLs under a path unique to it (`/ceros-e2e/<test id>/`), so
the specs run in parallel without seeing each other's stubs. The plugin ships with neither
the distributable nor any other site, so these specs need local `wp-env`.

### Stored files

The `@storesFiles` specs store a Flex experience on the site, which writes it under
`uploads/ceros-flex/<postId>/`. Deleting the post leaves that folder behind, so the
`flex-store` fixture lists it and deletes it with WP-CLI after each test, even when the
test fails.

## Roles

The browser is logged in as `e2e-author`, an Author, by default: the least privileged role
that can publish, and one without `unfiltered_html`, so saves go through the same filtering as
on hosts like WordPress.com. The post fixture hands the test's post to that user, since an
Author can edit only their own posts. Fixture setup and read-back over REST stay on the admin.

`settings.spec` opts into the admin with `test.use({ actor: 'administrator' })`, since the
settings page needs `manage_options`.

Read-only specs still run logged out with an empty `storageState` (see [Conventions](#conventions)).

## Layout

```
constants/   timeout tiers and block vocabulary
fixtures/    the fixture chain and the barrel every spec imports
pages/       page objects: locators and readiness waits, no assertions
actions/     multi-step workflows composed from page objects
utils/       REST client, block serializer, environment helpers
tests/       specs
```

## Conventions

- Specs import `test` and `expect` from `@fixtures/fixtures`, never from `@playwright/test`.
- Page objects declare `readonly` locators in the constructor and hold no assertions —
  only readiness waits. Behaviour is asserted in specs.
- Multi-step workflows live in `actions/`, wrapped in `test.step()`.
- Test data is created per test by fixtures and removed in teardown. Nothing is seeded
  out of band, and no post ID is passed in through the environment.
- No numeric timeout literals and no `waitForTimeout`. Use `TIMEOUTS` from `@constants/timeouts`.
- Tag every `test.describe` from `@utils/test-tags`.
- A test that only reads the published page runs logged out with
  `test.use({ storageState: { cookies: [], origins: [] } })`. A test that also drives the
  editor takes the `reader` fixture, a logged-out page beside the logged-in one.
- Prefer `getByRole` and `getByLabel`; fall back to a class selector only where the markup
  offers nothing better, and say why.

Two deliberate differences from the other Playwright suites here: credentials come from
`.env.example` rather than a credentials file you have to obtain, because wp-env's
defaults are public and `bootstrap-wp.sh` mints the one that is not; and page objects do
not extend a shared base class, because at this size it would hold nothing.

## CI

`e2e-tests.yml` runs the suite on pull requests from this repository once they are ready for
review and change the plugin, its build inputs or the suite. Drafts and fork pull requests are
skipped, since a fork gets no secrets. A new push cancels the run it replaces. It can also be
run by hand against another Ceros environment (`ceros_env`).

It is not a required check: it spans two products (the plugin and Ceros), and that ownership
is unresolved. Running it anywhere, CI or another
machine, needs the same inputs as a local run: `E2E_CEROS_ENV`, the secret `E2E_CEROS_API_KEY`,
network access to that environment, and a pinned WordPress version (`.wp-env.json` leaves core
unset, which has resolved to a version with no tag in the mirror).
