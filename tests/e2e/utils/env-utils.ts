import { WP_DEFAULT_PASSWORD, WP_DEFAULT_USER, WP_ROUTES } from '@constants/wordpress-constants'

/** wp-env's development port. Everything else derives from BASE_URL. */
export const DEFAULT_BASE_URL = 'http://localhost:8894'

/**
 * Normalise BASE_URL: trim, drop trailing slashes, add a scheme if missing.
 * The scheme defaults to http, not https — wp-env serves plain HTTP and a TLS
 * failure on the first navigation reads like a plugin bug.
 */
export const BASE_URL = (() => {
  const candidate = (process.env.BASE_URL ?? DEFAULT_BASE_URL).trim()
  if (!candidate) return DEFAULT_BASE_URL
  const raw = candidate.replace(/\/+$/, '')
  return /^https?:\/\//.test(raw) ? raw : `http://${raw}`
})()

/** Browser login only. WordPress rejects this for REST Basic auth. */
export const wpUser = () => process.env.E2E_WP_USER ?? WP_DEFAULT_USER
export const wpPassword = () => process.env.E2E_WP_PASSWORD ?? WP_DEFAULT_PASSWORD

/**
 * A WordPress application password, for REST Basic auth. Not interchangeable
 * with the account password: `wp_authenticate_application_password()` compares
 * against hashed application passwords only, so the account password 401s.
 *
 * Generated per install, so it has no default. `scripts/bootstrap-wp.sh` mints
 * one and writes it to `.env`.
 */
export const wpAppPassword = (): string => {
  const value = process.env.E2E_WP_APP_PASSWORD?.trim()
  if (!value) {
    throw new Error(
      'E2E_WP_APP_PASSWORD is not set.\n' +
        'Run "bash scripts/bootstrap-wp.sh" to mint one and write it to .env, ' +
        'or set it yourself. The account password will not work: WordPress only ' +
        'accepts application passwords for REST Basic auth.',
    )
  }
  return value
}

export const restUrl = (path = '') => `${BASE_URL}${WP_ROUTES.restPosts}${path}`
export const postEditUrl = (postId: number) => `${BASE_URL}${WP_ROUTES.postEdit(postId)}`
export const permalink = (postId: number) => `${BASE_URL}${WP_ROUTES.permalink(postId)}`

/**
 * `reject` installs a mu-plugin that 403s every Ceros call, reproducing a
 * rejected API key without swapping the key. `live` talks to the real API.
 */
export type ApiMode = 'live' | 'reject'
export const apiMode = (): ApiMode => (process.env.E2E_API_MODE === 'reject' ? 'reject' : 'live')

/** The plugin's configured environment changes which error text reaches the editor. */
export type PluginEnv = 'production' | 'staging'
export const pluginEnv = (): PluginEnv =>
  process.env.E2E_PLUGIN_ENV === 'staging' ? 'staging' : 'production'
