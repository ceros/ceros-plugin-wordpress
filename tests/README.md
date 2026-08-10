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
- `ceros_sanitize_staging_api_url` — `sanitize_text_field`, `esc_url_raw` and
  `add_settings_error`.
- `ceros_resolve_public_experience_url`, `ceros_fetch_flex_manifest`,
  `ceros_render_flex_*` — outbound HTTP.
- `ceros_store_is_allowed_asset_url` — reachable, but its Ceros-host cases go
  through `gethostbyname()`, so it would put DNS in the pre-push path.
- `ceros_store_experience_key`, `ceros_store_version`,
  `ceros_store_slug_filename` — `sanitize_key`, `sanitize_file_name`,
  `wp_json_encode`.
- `ceros_flex_ssr_styles` / `_head_scripts` / `_body_scripts` / `_style_tag` /
  `_script_tag` — escaping; `ceros_flex_ssr_requested_slug` — request
  superglobals.
- `Ceros_Encryption::get_api_key()` / `save_api_key()` — the options table. The
  crypto underneath them *is* covered here.
- The REST handlers — real `WP_REST_Request` objects.

## Required of the integration suite

**Re-run the URL cases from `CerosOwnedUrlTest` and `PublicUrlResolverTest`
against real WordPress.** The unit suite parses URLs through the bootstrap's
`wp_parse_url()` stand-in, so on its own it cannot detect the shim drifting from
core — those tests would stay green while the plugin was wrong.
`BootstrapShimTest` pins the shim's behaviour so a change to it fails loudly,
but only real WordPress can prove the behaviour matches.

## Known-untested branches

Deliberate, so they are not silently missing:

- The `gethostbyname()` path in `ceros_is_public_host()`. Any test would put DNS
  in the pre-push path, and a wildcard resolver could make it pass wrongly.
- The sodium-unavailable fallbacks in `Ceros_Encryption`, unreachable while the
  extension is loaded.
- `Ceros_Encryption::get_encryption_key()` throwing when both salt constants are
  absent. Constants cannot be undefined once set, so this needs a subprocess or
  the integration suite. It guards a deliberate fail-closed decision, so it is
  worth covering there.
