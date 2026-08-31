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
- **Filter passthrough** (`apply_filters`), which is what WordPress returns when
  no filter is added — and the no-op `add_filter` above means none ever is. Like
  `__`, this is core's own behaviour rather than a stand-in for it.

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

Covered here on top of the URL, sanitizer, store and crypto cases:

- `ceros_get_friendly_error_message` — the cURL-error-to-advice map. Substring
  matching over an ordered array, so the tests pin both the mapping and the
  order that keeps a specific pattern ahead of the generic `curl error` one.
- `ceros_get_allowed_embed_html` — the `wp_kses` allowlist. Pure data, but
  anything missing from it is stripped on save, which is how an embed ends up
  rendering dead. The tests name the attributes that carry a feature
  (`data-flex-manifest-url`, the legacy `scrolling`) and pin the tag list closed.
  How `wp_kses` *interprets* the list is still integration-suite work.

## Required of the integration suite

**Re-run the URL cases from `CerosOwnedUrlTest` and `PublicUrlResolverTest`
against real WordPress.** The unit suite parses URLs through the bootstrap's
`wp_parse_url()` stand-in, so on its own it cannot detect the shim drifting from
core — those tests would stay green while the plugin was wrong.
`BootstrapShimTest` pins the shim's behaviour so a change to it fails loudly,
but only real WordPress can prove the behaviour matches.

**Render an SSR block whose custom body HTML imports by bare specifier under
both a block theme and a classic theme, on a page with one block and with two.**
WordPress prints its own import map in the head under one and below the content
under the other, and `ceros_flex_ssr_render_manifest()` picks between joining
that map and emitting its own on that basis. Under a block theme the page must
carry one map, WordPress's, holding every block's specifiers; under a classic
theme, one per block, each above that block's `type="module"` scripts. Every
specifier must resolve in the browser, which is the part no markup assertion
covers. Run it on the oldest WordPress in `Requires at least` as well as the
newest: the map is assembled by core, and which mechanisms feed it has changed
between releases.

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
- The import-map branch in `ceros_flex_ssr_render_manifest()`. It turns on
  `wp_is_block_theme()` and `wp_is_rest_endpoint()`, neither of which the closed
  shim list above admits, and the branch is only meaningful against a real theme.

And one branch that is unreachable by mistake rather than by choice.
`ceros_get_friendly_error_message`'s third pattern is commented "cURL error 28:
Operation timeout" but reads `timeout`, while cURL emits "Operation timed out",
so a plugin timeout gets the generic connection advice instead. The test named
`test_curl_28_does_not_reach_the_timeout_message` pins the current behaviour and
says to delete itself once the pattern is fixed.


## The JavaScript suite

Vitest with happy-dom, in `tests/js`. The `@wordpress/*` packages are webpack
externals mapped to `window.wp.*` at build time and are not installed, so what
the suite can reach is decided by which of them a file imports.

Covered:

- `constants.js` — `manifestUrlFromInline()`, the editor-side fallback for
  recovering a manifest URL from a snippet the plugin already sanitized. It is
  not a port of the PHP `ceros_manifest_url_from_inline()`, which reads the raw
  API snippet and so accepts either quote style; the tests pin the narrower
  contract and say why, and cover the entity decoding the two do share. Plus the
  option and delivery-mode values, which are persisted on the block and read by
  the PHP renderer.
- `tree-view`, `embed-options`, `delivery-options`, `modal-header`, `modal-body`.
- `modal-footer` — the delivery/sizing branching, and the `setAttributes`
  payload. That payload is the contract `render.php` reads, so the commit test
  asserts the whole object rather than individual keys: a dropped attribute
  fails here instead of rendering a dead embed.
- `preview` — the fallback chain that picks which embed variant to show, and the
  effect that rebuilds `<script>` tags because scripts assigned via `innerHTML`
  never run.
- `modal` — that the overlay is portalled to `document.body` rather than left in
  the block's DOM, and that the backdrop closes while the panel does not.
- `error-boundary` — the fallback, the log, and the retry.
- `save` — returns `null`. The block is dynamic, so markup here would have the
  editor validate serialized HTML against the PHP render.

Out of scope: anything importing `@wordpress/components`, `block-editor`, `data`
or `api-fetch` — `edit.js`, `sidebar-controls`, `store-controls`,
`paste-url-panel` and `ssr-preview`. Installing them is a decision worth making
on evidence rather than up front, since `@wordpress/components` is a large tree.

### The two stand-in modules

`tests/js/stubs/` holds replacements for the two externals that are
passthroughs rather than implementations, aliased in `vitest.config.mjs`. Same
rule as `tests/bootstrap.php`: a stand-in is allowed only where it *is* what the
real thing does, not an approximation of it.

- `wp-element.js` re-exports React, which is exactly what `@wordpress/element`
  does. `createPortal` comes from react-dom, which is why this is a module and
  not a plain alias to `react`. The package's own additions (`RawHTML`,
  `renderToString`) are deliberately absent.
- `wp-i18n.js` returns its input, which is what the real package does with no
  translations loaded. `sprintf` is deliberately absent: it is a real
  implementation, so stubbing it would mean asserting against the stub.

Anything needing a missing export fails with a missing-export error, which is
the signal to install the package rather than grow the stub.

`@wordpress/api-fetch` and `@wordpress/url` are installed for that reason:
both are real implementations, so a stand-in would mean asserting against the
stand-in. Tests that reach `api-fetch` call its own `setFetchHandler` to replace
the final request step, which keeps the package's middleware chain in play and
the network out of it.

### Three things about the setup

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

`environmentOptions.happyDOM` turns off script, stylesheet and child-frame
loading. The embed snippets under test carry real `view.ceros.com` URLs and
happy-dom would otherwise fetch them, which would put the network in the
pre-push path and break the suite wherever those hosts are unreachable. Nothing
asserts on a loaded resource; the `preview` tests check that a rebuilt script
kept its attributes, not that it ran. Note that happy-dom disables JavaScript
evaluation by default, so a test cannot assert a script executed.
