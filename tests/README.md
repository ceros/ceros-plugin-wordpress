# Tests

Run with `composer test`.

## Suites

| Suite | Needs | Runs in | Where |
| --- | --- | --- | --- |
| `unit` | nothing | milliseconds | pre-push hook + CI |

The `unit` suite runs with **no WordPress loaded** — no Docker, no database, no
network. That is what makes it safe to run from `.githooks/pre-push`.

## What belongs in the unit suite

A function qualifies when its logic does not depend on WordPress behaviour.
`tests/bootstrap.php` supplies a deliberately small set of stand-ins, and the
list is closed:

- **Hook registration** (`add_action`, `add_filter`) as no-ops. Nothing under
  test observes the hook registry.
- **Thin wrappers over PHP** where an exact equivalent exists (`wp_parse_url`,
  `plugin_basename`).
- **Translation passthrough** (`__`), which is what WordPress itself returns
  with no textdomain loaded.

## What does not belong here

Anything depending on escaping or sanitizing (`esc_*`, `sanitize_*`,
`wp_kses`), HTTP (`wp_remote_*`), options (`get_option`), or the uploads
directory. Stubbing those would mean asserting against the stub rather than
against WordPress — a suite that stays green while the plugin is broken. Those
need an `integration` suite running against real WordPress, which can be added
as a second `<testsuite>` in `phpunit.xml.dist` with its own bootstrap.

Currently deferred to that suite:

- `ceros_build_flex_iframe_snippet`, `ceros_build_flex_inline_snippet`,
  `ceros_build_flex_embed_codes`, `ceros_build_legacy_embed_codes` — correctness
  here *is* the escaping.
- `ceros_sanitize_embed_code` and friends — wrap `wp_kses` with a custom
  allowlist.
- `ceros_resolve_public_experience_url`, `ceros_fetch_flex_manifest`,
  `ceros_render_flex_*` — outbound HTTP.
- `ceros_store_is_allowed_asset_url` — reachable, but its Ceros-host cases go
  through `gethostbyname()`, so it would put DNS in the pre-push path.
- The REST handlers — real `WP_REST_Request` objects.
