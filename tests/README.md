# Tests

PHP: `composer test`. JavaScript: `npm run test:js`.

## Suites

| Suite | Needs | Runs in | Where |
| --- | --- | --- | --- |
| PHP `unit` | nothing | milliseconds | pre-push hook + CI |
| JS (`tests/js`) | happy-dom | under a second | pre-push hook + CI |
| Shell (`tests/shell`) | bash, jq | about a second | CI |

The shell suite covers `tools/check-tested-upto.sh`. It stubs the WordPress
version API on `PATH` rather than calling it, so it makes no network request,
and every case names the guard it covers.

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


## The JavaScript suite

Vitest with happy-dom, in `tests/js`. It covers the block editor code that
imports no `@wordpress/*` package, since those are webpack externals mapped to
`window.wp.*` at build time and are not installed:

- `constants.js` — `manifestUrlFromInline()`, the editor-side fallback for
  recovering a manifest URL from a snippet the plugin already sanitized. It is
  not a port of the PHP `ceros_manifest_url_from_inline()`, which reads the raw
  API snippet and so accepts either quote style; the tests pin the narrower
  contract and say why, and cover the entity decoding the two do share. Plus the
  option and delivery-mode values, which are persisted on the block and read by
  the PHP renderer.
- `tree-view`, `embed-options`, `delivery-options`, `modal-header`.

Anything importing `@wordpress/components`, `block-editor`, `data` or
`api-fetch` is out of scope until those are installed as devDependencies — a
decision worth making on evidence rather than up front, since
`@wordpress/components` is a large tree and only `edit.js` and the control
panels need it.

### Two things about the setup

`vitest.config.mjs` carries a small `ceros:jsx-in-js` plugin. The sources are
`.js` files containing JSX, Vite picks its loader from the file extension, and
no supported config option changes that — `OxcOptions` omits `lang` and
`esbuild.loader` does not reach them. Renaming the sources to `.jsx` is the
alternative, but `src/ceros/index.js` is the block entry wp-scripts finds by
name.

`tests/js/setup.js` registers Testing Library's `cleanup` by hand. It only
self-registers when a global `afterEach` exists, and this suite uses explicit
imports rather than Vitest globals, so without it every render stays in the
document and later queries match earlier tests' DOM.
