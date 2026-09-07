# End-to-end tests

Playwright tests for the Ceros block in the WordPress editor and on the published page,
run against a local `wp-env` instance.

**Not wired into CI or the pre-push hook, and not a required check.** These need WordPress,
Docker and network access, which the pre-push hook explicitly rules out. Adding a workflow is
the only remaining step if that changes — see [CI](#ci) below.

## Layout

```
constants/   timeout tiers and block vocabulary
fixtures/    the fixture chain and the barrel every spec imports
pages/       page objects: locators and readiness waits, no assertions
actions/     multi-step workflows composed from page objects
utils/       REST client, block serializer, environment helpers
rig/         switches the plugin between a stubbed and a live Ceros API
tests/       specs
```

## Prerequisites

```bash
npm ci && npm run build        # repository root; the plugin fatals without build/
npm run env:start              # repository root
npm install                    # here
bash scripts/bootstrap-wp.sh   # here; creates .env and mints the app password
```

A Ceros API key in `.wp-env.override.json` (gitignored) if you want the live half.

The suite provisions nothing — it reads a URL and credentials from the environment, so
the same specs run against local `wp-env`, another WordPress, or one built by CI.
`bootstrap-wp.sh` is the only piece that knows `wp-env` exists; against anything else,
fill in `.env` yourself and skip it.

## Running

```bash
npm test                                   # everything
npx playwright test --project=wordpress    # the only project
npx playwright test --grep @rendered
npm run type-check
npm run lint
```

Switch the Ceros API between stubbed and live, and export what it prints:

```bash
bash rig/mode.sh reject   # every Ceros call returns 403
bash rig/mode.sh live     # talk to the real API
```

The container state and `E2E_API_MODE` are separate things and a spec trusts the variable.
Set them together, or a spec runs against the wrong rig and fails on a missing locator.

## Environment variables

`.env.example` is the template. Copy it to `.env` (gitignored) and the defaults target a
local `wp-env`. Real environment variables take precedence over the file, so CI sets its
own values without `.env` interfering.

**There are two WordPress credentials and they are not interchangeable.**
`E2E_WP_PASSWORD` is the account password, used for browser login.
`E2E_WP_APP_PASSWORD` is a WordPress application password, used for REST Basic auth —
`wp_authenticate_application_password()` compares against hashed application passwords
only, so the account password returns 401. It is generated per install, which is why it
has no default and `bootstrap-wp.sh` mints it.

| Variable                          | Default                     | Purpose                                                                     |
| --------------------------------- | --------------------------- | --------------------------------------------------------------------------- |
| `BASE_URL`                        | `http://localhost:8894`     | The WordPress instance. `http`, not `https` — wp-env serves plain HTTP.     |
| `E2E_WP_USER` / `E2E_WP_PASSWORD` | `admin` / `password`        | Browser login. wp-env's documented defaults.                                |
| `E2E_WP_APP_PASSWORD`             | —                           | Application password for REST calls. Required; `bootstrap-wp.sh` writes it. |
| `E2E_API_MODE`                    | `live`                      | `reject` or `live`. Must match `rig/mode.sh`.                               |
| `E2E_PLUGIN_ENV`                  | `production`                | The plugin's configured environment, which changes the error text.          |
| `E2E_TIMEOUT_*`                   | see `constants/timeouts.ts` | Override any timeout tier, in milliseconds.                                 |

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

`.github/workflows/e2e.yml` runs both specs on every pull request that touches the
plugin or this suite, and on pushes to the default branch. It needs no secrets: the API
key it configures is a deliberately invalid placeholder, and the stub rejects by hostname
so the value never matters.

**A placeholder key is required, not optional.** With no key configured the block skips
the fetch and offers the paste-a-URL flow instead, so the stub never fires and there is no
error panel to assert on.

The workflow pins the WordPress version, because `.wp-env.json` leaves it unset and that
has resolved to a version with no tag in the mirror. It is deliberately **not** a required
check while ownership of a test that spans two products is unresolved.
